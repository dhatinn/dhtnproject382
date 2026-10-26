/**
 * MagicBento.js - Standalone Interactive Bento Grid Engine
 * Implements 3D Spring Tilt, Magnetic Pull, Dynamic Spotlight, Dual Glow Borders, Twinkle Stars & Interactive Chart Scrubbing
 */

(function () {
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const TILT = 6;
  const MAGNET = 6;
  const STAGGER = 80;

  class MagicBentoEngine {
    constructor(rootElement, options = {}) {
      this.root = rootElement;
      if (!this.root) return;

      this.options = Object.assign(
        {
          columns: 4,
          rowHeight: 220,
          gap: 16,
          radius: 20,
          spotlightRadius: 320,
          particleCount: 28,
          enableTilt: true,
          enableMagnetism: true,
          enableStars: true,
          enableSpotlight: true,
          enableBorderGlow: true,
          clickEffect: true,
          intro: true,
          glow: [212, 175, 55] // Gold / Amber Glow
        },
        options
      );

      this.cells = Array.from(this.root.querySelectorAll('.magic-bento__cell'));
      this.cards = Array.from(this.root.querySelectorAll('.magic-bento__card'));
      this.starCanvases = this.cells.map(c => c.querySelector('.magic-bento__stars'));

      this.pointer = { x: -1e5, y: -1e5, inside: false, down: -1 };
      this.light = { x: -1e5, y: -1e5, near: 0, ready: false };
      this.states = [];
      this.wave = { x: 0, y: 0, t: -1, duration: 1.1, reach: 800 };
      this.rects = [];
      this.bounds = null;
      this.dirty = true;
      this.raf = 0;
      this.last = 0;
      this.visible = true;
      this.entered = false;

      this.init();
    }

    stateFor(index) {
      if (!this.states[index]) {
        this.states[index] = {
          hot: 0,
          rx: 0,
          ry: 0,
          tx: 0,
          ty: 0,
          vrx: 0,
          vry: 0,
          vtx: 0,
          vty: 0,
          press: 1,
          vpress: 0,
          stars: 0,
          particles: null,
          active: false,
          written: {}
        };
      }
      return this.states[index];
    }

    write(element, state, name, value) {
      if (state.written[name] === value) return;
      state.written[name] = value;
      element.style.setProperty(name, value);
    }

    measure() {
      this.dirty = false;
      this.bounds = this.root.getBoundingClientRect();
      this.rects = this.cells.map(cell => (cell ? cell.getBoundingClientRect() : null));
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      this.starCanvases.forEach((canvas, index) => {
        const rect = this.rects[index];
        if (!canvas || !rect) return;
        const width = Math.max(1, Math.round(rect.width * ratio));
        const height = Math.max(1, Math.round(rect.height * ratio));
        if (canvas.width !== width || canvas.height !== height) {
          canvas.width = width;
          canvas.height = height;
        }
      });
    }

    seed(index, rect) {
      const count = this.options.particleCount;
      const state = this.stateFor(index);
      if (state.particles && state.particles.length === count) return state.particles;
      state.particles = Array.from({ length: count }, () => ({
        x: Math.random() * rect.width,
        y: Math.random() * rect.height,
        vx: (Math.random() - 0.5) * 6,
        vy: -4 - Math.random() * 10,
        size: 0.6 + Math.random() * 1.1,
        phase: Math.random() * Math.PI * 2,
        rate: 1.2 + Math.random() * 2.4
      }));
      return state.particles;
    }

    drawStars(index, rect, state, lx, ly, dt, now) {
      const canvas = this.starCanvases[index];
      if (!canvas) return;
      const context = canvas.getContext('2d');
      if (!context) return;
      const ratio = canvas.width / Math.max(1, rect.width);
      context.setTransform(1, 0, 0, 1, 0, 0);
      context.clearRect(0, 0, canvas.width, canvas.height);
      if (state.stars < 0.004) return;
      const reach = this.options.spotlightRadius * 0.75;
      const [r, g, b] = this.options.glow;
      const particles = this.seed(index, rect);
      context.setTransform(ratio, 0, 0, ratio, 0, 0);

      for (const particle of particles) {
        particle.x += particle.vx * dt;
        particle.y += particle.vy * dt;
        if (particle.y < -4) {
          particle.y = rect.height + 4;
          particle.x = Math.random() * rect.width;
        }
        if (particle.x < -4) particle.x = rect.width + 4;
        if (particle.x > rect.width + 4) particle.x = -4;
        const distance = Math.hypot(particle.x - lx, particle.y - ly) / reach;
        if (distance >= 1) continue;
        const falloff = 1 - distance * distance * (3 - 2 * distance);
        const twinkle = 0.55 + 0.45 * Math.sin(now * 0.001 * particle.rate + particle.phase);
        const alpha = state.stars * falloff * twinkle;
        if (alpha < 0.01) continue;
        context.fillStyle = `rgba(${r}, ${g}, ${b}, ${alpha.toFixed(3)})`;
        context.beginPath();
        context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
        context.fill();
      }
    }

    startWave(x, y, duration = 1.1) {
      if (!this.bounds) return;
      this.wave.x = x;
      this.wave.y = y;
      this.wave.t = 0;
      this.wave.duration = duration;
      this.wave.reach =
        Math.max(
          Math.hypot(x - this.bounds.left, y - this.bounds.top),
          Math.hypot(x - this.bounds.right, y - this.bounds.top),
          Math.hypot(x - this.bounds.left, y - this.bounds.bottom),
          Math.hypot(x - this.bounds.right, y - this.bounds.bottom)
        ) + 140;
    }

    frame(now) {
      this.raf = 0;
      const dt = Math.min(0.05, (now - this.last) / 1000 || 0);
      this.last = now;
      if (this.dirty) this.measure();
      let busy = false;

      const margin = this.options.spotlightRadius * 0.5;
      const near =
        this.pointer.inside &&
        this.bounds &&
        this.pointer.x > this.bounds.left - margin &&
        this.pointer.x < this.bounds.right + margin &&
        this.pointer.y > this.bounds.top - margin &&
        this.pointer.y < this.bounds.bottom + margin
          ? 1
          : 0;

      if (!this.light.ready && near) {
        this.light.x = this.pointer.x;
        this.light.y = this.pointer.y;
        this.light.ready = true;
      }
      const follow = 1 - Math.exp(-dt / 0.05);
      this.light.x += (this.pointer.x - this.light.x) * follow;
      this.light.y += (this.pointer.y - this.light.y) * follow;
      const fade = 1 - Math.exp(-dt / (near > this.light.near ? 0.12 : 0.4));
      this.light.near += (near - this.light.near) * fade;
      if (Math.abs(near - this.light.near) < 0.002) this.light.near = near;
      else busy = true;
      if (Math.abs(this.pointer.x - this.light.x) + Math.abs(this.pointer.y - this.light.y) > 0.3) busy = true;

      let waveRadius = 0;
      let waveAlpha = 0;
      if (this.wave.t >= 0) {
        this.wave.t += dt;
        const progress = Math.min(1, this.wave.t / this.wave.duration);
        waveRadius = this.wave.reach * (1 - Math.pow(1 - progress, 2.2));
        waveAlpha = Math.pow(1 - progress, 1.3);
        if (progress >= 1) {
          this.wave.t = -1;
          waveAlpha = 0;
        } else busy = true;
      }

      this.cards.forEach((card, index) => {
        const rect = this.rects[index];
        if (!card || !rect) return;
        const state = this.stateFor(index);
        const lx = this.light.x - rect.left;
        const ly = this.light.y - rect.top;
        const px = this.pointer.x - rect.left;
        const py = this.pointer.y - rect.top;
        const inside = this.pointer.inside && this.entered && px >= 0 && py >= 0 && px <= rect.width && py <= rect.height;

        if (inside !== state.active) {
          state.active = inside;
          if (inside) card.dataset.active = '';
          else delete card.dataset.active;
        }

        const hotTarget = inside && this.options.enableSpotlight ? 1 : 0;
        state.hot += (hotTarget - state.hot) * (1 - Math.exp(-dt / (hotTarget > state.hot ? 0.14 : 0.32)));
        if (Math.abs(hotTarget - state.hot) < 0.002) state.hot = hotTarget;
        else busy = true;

        const u = clamp(px / Math.max(1, rect.width), 0, 1) - 0.5;
        const v = clamp(py / Math.max(1, rect.height), 0, 1) - 0.5;
        const tilt = inside && this.options.enableTilt;
        const magnet = inside && this.options.enableMagnetism;

        const targets = [
          tilt ? -v * 2 * TILT : 0,
          tilt ? u * 2 * TILT : 0,
          magnet ? u * 2 * MAGNET : 0,
          magnet ? v * 2 * MAGNET : 0
        ];
        const keys = [
          ['rx', 'vrx'],
          ['ry', 'vry'],
          ['tx', 'vtx'],
          ['ty', 'vty']
        ];
        const steps = Math.max(1, Math.ceil(dt / (1 / 240)));
        const h = dt / steps;
        keys.forEach(([key, velocity], k) => {
          for (let step = 0; step < steps; step++) {
            state[velocity] += (170 * (targets[k] - state[key]) - 20 * state[velocity]) * h;
            state[key] += state[velocity] * h;
          }
          if (Math.abs(targets[k] - state[key]) < 0.002 && Math.abs(state[velocity]) < 0.01) {
            state[key] = targets[k];
            state[velocity] = 0;
          } else busy = true;
        });

        const pressTarget = this.pointer.down === index && this.options.clickEffect ? 0.985 : 1;
        for (let step = 0; step < steps; step++) {
          state.vpress += (420 * (pressTarget - state.press) - 28 * state.vpress) * h;
          state.press += state.vpress * h;
        }
        if (Math.abs(pressTarget - state.press) < 0.0002 && Math.abs(state.vpress) < 0.001) {
          state.press = pressTarget;
          state.vpress = 0;
        } else busy = true;

        this.write(card, state, '--mb-x', `${lx.toFixed(1)}px`);
        this.write(card, state, '--mb-y', `${ly.toFixed(1)}px`);
        this.write(card, state, '--mb-near', (this.options.enableBorderGlow ? this.light.near : 0).toFixed(3));
        this.write(card, state, '--mb-hot', state.hot.toFixed(3));
        this.write(card, state, '--mb-rx', `${state.rx.toFixed(3)}deg`);
        this.write(card, state, '--mb-ry', `${state.ry.toFixed(3)}deg`);
        this.write(card, state, '--mb-tx', `${state.tx.toFixed(2)}px`);
        this.write(card, state, '--mb-ty', `${state.ty.toFixed(2)}px`);
        this.write(card, state, '--mb-press', state.press.toFixed(4));
        this.write(card, state, '--mb-wx', `${(this.wave.x - rect.left).toFixed(1)}px`);
        this.write(card, state, '--mb-wy', `${(this.wave.y - rect.top).toFixed(1)}px`);
        this.write(card, state, '--mb-wr', `${waveRadius.toFixed(1)}px`);
        this.write(card, state, '--mb-wa', waveAlpha.toFixed(3));

        if (this.options.enableStars && this.starCanvases[index]) {
          const starTarget = inside ? 1 : 0;
          state.stars += (starTarget - state.stars) * (1 - Math.exp(-dt / (starTarget ? 0.3 : 0.5)));
          if (state.stars < 0.004 && !starTarget) state.stars = 0;
          if (state.stars > 0 || starTarget) {
            this.drawStars(index, rect, state, lx, ly, dt, now);
            state.drawn = true;
            busy = true;
          } else if (state.drawn) {
            this.drawStars(index, rect, state, lx, ly, dt, now);
            state.drawn = false;
          }
        }
      });

      if (busy && this.visible) this.raf = requestAnimationFrame(this.frame.bind(this));
    }

    wake() {
      if (this.raf || !this.visible) return;
      this.last = performance.now();
      this.raf = requestAnimationFrame(this.frame.bind(this));
    }

    cardIndex(target) {
      if (!(target instanceof Element)) return -1;
      const card = target.closest('.magic-bento__card');
      return card instanceof HTMLElement ? this.cards.indexOf(card) : -1;
    }

    init() {
      this.root.dataset.motion = '';
      this.root.dataset.intro = '';

      // Set initial entry states
      let order = 0;
      this.cells.forEach(cell => {
        if (!cell) return;
        cell.style.setProperty('--mb-delay', `${order++ * STAGGER}ms`);
        cell.dataset.state = 'in';
      });

      setTimeout(() => {
        this.entered = true;
        this.measure();
        if (this.bounds) this.startWave(this.bounds.left + this.bounds.width / 2, this.bounds.top + this.bounds.height / 2, 1.4);
        this.wake();
      }, 200);

      // Event listeners
      window.addEventListener('pointermove', e => {
        this.pointer.x = e.clientX;
        this.pointer.y = e.clientY;
        this.pointer.inside = true;
        this.wake();
      }, { passive: true });

      window.addEventListener('pointerdown', e => {
        this.pointer.x = e.clientX;
        this.pointer.y = e.clientY;
        this.pointer.inside = true;
        this.pointer.down = this.cardIndex(e.target);
        this.wake();
      }, { passive: true });

      window.addEventListener('pointerup', () => {
        this.pointer.down = -1;
        this.wake();
      }, { passive: true });

      document.addEventListener('pointerout', e => {
        if (e.relatedTarget) return;
        this.pointer.inside = false;
        this.pointer.down = -1;
        this.wake();
      });

      window.addEventListener('resize', () => {
        this.dirty = true;
        this.wake();
      }, { passive: true });

      this.root.addEventListener('click', e => {
        const index = this.cardIndex(e.target);
        if (index > -1 && this.options.clickEffect) {
          this.startWave(e.clientX, e.clientY, 1.1);
          this.wake();
        }
      });

      // Init interactive chart if present
      this.initChartScrubber();
    }

    initChartScrubber() {
      const chart = this.root.querySelector('.magic-bento__chart');
      if (!chart) return;

      const plot = chart.querySelector('.magic-bento__plot');
      const scrubLine = chart.querySelector('.magic-bento__scrub');
      const dot = chart.querySelector('.magic-bento__scrub-dot');
      const valLabel = chart.querySelector('.magic-bento__figure-value');
      const noteLabel = chart.querySelector('.magic-bento__figure-note');

      const CHART_DATA = [320, 480, 620, 850, 1140, 980, 1450, 1680, 1520, 1840];
      const HOURS = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '16:00', '18:00', '19:00', '20:00'];
      const initialVal = valLabel ? valLabel.textContent : 'Rp 0';
      const initialNote = noteLabel ? noteLabel.textContent : '+24% vs Kemarin';

      const scrub = e => {
        if (!plot || !scrubLine || !dot) return;
        const rect = plot.getBoundingClientRect();
        const x = clamp(e.clientX - rect.left, 0, rect.width);
        const position = (x / Math.max(1, rect.width)) * (CHART_DATA.length - 1);
        const index = Math.floor(position);
        const next = Math.min(CHART_DATA.length - 1, index + 1);
        const ratio = position - index;
        const val = CHART_DATA[index] + (CHART_DATA[next] - CHART_DATA[index]) * ratio;

        chart.dataset.scrub = '';
        scrubLine.style.transform = `translateX(${x.toFixed(1)}px)`;
        const height = rect.height;
        const max = Math.max(...CHART_DATA);
        const min = Math.min(...CHART_DATA) * 0.7;
        const y = height - ((val - min) / (max - min)) * (height - 16) - 8;
        dot.style.transform = `translateY(${y.toFixed(1)}px)`;

        if (valLabel) valLabel.textContent = 'Rp ' + Math.round(val * 1000).toLocaleString('id-ID');
        if (noteLabel) noteLabel.textContent = `Jam ${HOURS[Math.round(position)] || '12:00'} WIB`;
      };

      const reset = () => {
        delete chart.dataset.scrub;
        if (valLabel) valLabel.textContent = initialVal;
        if (noteLabel) noteLabel.textContent = initialNote;
      };

      chart.addEventListener('pointermove', scrub);
      chart.addEventListener('pointerdown', scrub);
      chart.addEventListener('pointerleave', reset);
    }
  }

  // Auto initialize on DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    const bentoRoot = document.getElementById('adminMagicBento');
    if (bentoRoot) {
      window.magicBentoInstance = new MagicBentoEngine(bentoRoot);
    }
  });

  window.MagicBentoEngine = MagicBentoEngine;
})();
