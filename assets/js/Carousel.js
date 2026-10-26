/**
 * Carousel.js - 3D Spring Physics Carousel with Momentum & Multi-transition Effects
 * Port of the React Carousel component for vanilla HTML/JS
 * Supports: tilt, slide, scale, fade, cube transitions, momentum drag, autoplay timer bars & keyboard control
 */

(function () {
  'use strict';

  const EFFECTS = ['tilt', 'slide', 'scale', 'fade', 'cube'];
  const STIFFNESS = 150;
  const DAMPING = 24;

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const wrap = (value, count) => ((value % count) + count) % count;
  const nearest = (offset, count) => wrap(offset + count / 2, count) - count / 2;
  const smootherstep = t => t * t * t * (t * (t * 6 - 15) + 10);

  const edgeMask = size => {
    const stops = [0, 0.15, 0.3, 0.45, 0.6, 0.75, 0.9, 1].map(t => [smootherstep(t).toFixed(3), (t * size).toFixed(1)]);
    const left = stops.map(([alpha, x]) => `rgba(0, 0, 0, ${alpha}) ${x}px`);
    const right = [...stops].reverse().map(([alpha, x]) => `rgba(0, 0, 0, ${alpha}) calc(100% - ${x}px)`);
    return `linear-gradient(to right, ${[...left, ...right].join(', ')})`;
  };

  const band = (value, max) => {
    if (value < 0) return -0.3 * (1 - Math.exp(value / 0.55));
    if (value > max) return max + 0.3 * (1 - Math.exp((max - value) / 0.55));
    return value;
  };

  const pose = (index, position, s) => {
    const offset = s.loop ? nearest(index - position, s.count) : index - position;
    const distance = Math.abs(offset);
    const near = Math.min(distance, 1);
    const result = {
      transform: 'none',
      opacity: 1,
      visible: true,
      layer: 10 - Math.min(9, Math.round(distance * 2)),
      shade: 0,
      focus: 1
    };

    if (s.mode === 'fade') {
      const half = Math.min(1, near * 2);
      result.visible = distance < 1;
      result.opacity = 1 - near * near * (3 - 2 * near);
      result.transform = `scale(${1 - 0.04 * near})`;
      result.focus = 1 - half * half * (3 - 2 * half);
      return result;
    }

    if (s.mode === 'cube') {
      const half = s.cardWidth / 2;
      const turn = Math.sin(Math.PI * (position - Math.floor(position)));
      result.visible = distance < 1;
      result.transform = `scale(${1 - 0.12 * turn}) translateZ(${-half}px) rotateY(${clamp(offset, -1, 1) * 90}deg) translateZ(${half}px)`;
      result.shade = near * s.shade;
      return result;
    }

    let x = offset * s.span;
    result.visible = x > -(s.inset + s.cardWidth + 2) && x < s.width + s.bleed * 2 - s.inset + 2;
    result.focus = 1 - near * near * (3 - 2 * near);

    if (s.mode === 'tilt') {
      result.visible = result.visible && distance < 1;
      result.transform = `translate3d(${x}px, 0, 0) rotateY(${-clamp(offset, -1, 1) * 90}deg)`;
    } else if (s.mode === 'scale') {
      x -= Math.sign(offset) * near * s.cardWidth * 0.06;
      result.transform = `translate3d(${x}px, 0, 0) scale(${1 - 0.12 * near})`;
      result.opacity = 1 - 0.5 * near;
    } else {
      result.transform = `translate3d(${x}px, 0, 0)`;
    }
    return result;
  };

  class CarouselInstance {
    constructor(rootElement, options = {}) {
      this.root = rootElement;
      if (!this.root) return;

      this.stage = this.root.querySelector('.carousel-stage') || this.root;
      this.viewport = this.root.querySelector('.carousel-viewport');
      this.slides = Array.from(this.root.querySelectorAll('.carousel-slide'));
      this.count = this.slides.length;
      if (!this.count || !this.viewport) return;

      const ds = this.root.dataset;
      this.options = Object.assign(
        {
          aspectRatio: parseFloat(ds.aspectRatio) || 1.15,
          gap: parseFloat(ds.gap) || 16,
          peek: parseFloat(ds.peek) || 0,
          effect: ds.effect || 'tilt',
          indicator: ds.indicator || 'bars',
          arrows: ds.arrows === 'true' || true,
          autoplay: ds.autoplay !== 'false',
          autoplayDelay: parseInt(ds.autoplayDelay, 10) || 3500,
          pauseOnHover: ds.pauseOnHover !== 'false',
          loop: ds.loop !== 'false',
          draggable: ds.draggable !== 'false',
          frame: ds.frame !== 'false'
        },
        options
      );

      this.mode = EFFECTS.includes(this.options.effect) ? this.options.effect : 'tilt';
      this.stacked = this.mode === 'fade' || this.mode === 'cube';
      this.ratio = clamp(this.options.aspectRatio, 0.3, 4);

      this.active = 0;
      this.state = {
        p: 0,
        v: 0,
        target: 0,
        shown: 0,
        elapsed: 0,
        hovered: false,
        focused: false,
        visible: true,
        drag: null,
        wheeling: false,
        wheelTimer: 0,
        suppress: false,
        last: 0
      };

      this.raf = 0;
      this.alive = true;
      this.reported = 0;

      this.initControls();
      this.initGeometry();
      this.initEvents();
      this.wake();
    }

    initControls() {
      // Find or build controls
      this.controlsContainer = this.root.querySelector('.carousel-controls');
      if (!this.controlsContainer && this.count > 1) {
        this.controlsContainer = document.createElement('div');
        this.controlsContainer.className = 'carousel-controls';
        this.stage.appendChild(this.controlsContainer);
      }

      if (this.controlsContainer) {
        // Prev button
        if (this.options.arrows && !this.controlsContainer.querySelector('.carousel-arrow--prev')) {
          const prev = document.createElement('button');
          prev.type = 'button';
          prev.className = 'carousel-arrow carousel-arrow--prev';
          prev.setAttribute('aria-label', 'Previous slide');
          prev.innerHTML = '<i class="fa-solid fa-chevron-left" style="font-size: 13px;"></i>';
          prev.addEventListener('click', () => this.step(-1));
          this.controlsContainer.appendChild(prev);
          this.prevBtn = prev;
        }

        // Indicator: Bars or Dots
        if (this.options.indicator === 'bars') {
          let bars = this.controlsContainer.querySelector('.carousel-bars');
          if (!bars) {
            bars = document.createElement('div');
            bars.className = 'carousel-bars';
            for (let i = 0; i < this.count; i++) {
              const b = document.createElement('button');
              b.type = 'button';
              b.className = 'carousel-bar';
              b.setAttribute('aria-label', `Slide ${i + 1}`);
              b.innerHTML = '<span class="carousel-bar-track"><span class="carousel-bar-fill"></span></span>';
              b.addEventListener('click', () => this.goTo(i));
              bars.appendChild(b);
            }
            this.controlsContainer.appendChild(bars);
          }
          this.fillRefs = Array.from(bars.querySelectorAll('.carousel-bar-fill'));
        } else if (this.options.indicator === 'dots') {
          let dots = this.controlsContainer.querySelector('.carousel-dots');
          if (!dots) {
            dots = document.createElement('div');
            dots.className = 'carousel-dots';
            for (let i = 0; i < this.count; i++) {
              const d = document.createElement('button');
              d.type = 'button';
              d.className = 'carousel-dot';
              d.setAttribute('aria-label', `Slide ${i + 1}`);
              d.innerHTML = '<span class="carousel-dot-mark"></span>';
              d.addEventListener('click', () => this.goTo(i));
              dots.appendChild(d);
            }
            this.controlsContainer.appendChild(dots);
          }
          this.dotRefs = Array.from(dots.querySelectorAll('.carousel-dot'));
        }

        // Next button
        if (this.options.arrows && !this.controlsContainer.querySelector('.carousel-arrow--next')) {
          const next = document.createElement('button');
          next.type = 'button';
          next.className = 'carousel-arrow carousel-arrow--next';
          next.setAttribute('aria-label', 'Next slide');
          next.innerHTML = '<i class="fa-solid fa-chevron-right" style="font-size: 13px;"></i>';
          next.addEventListener('click', () => this.step(1));
          this.controlsContainer.appendChild(next);
          this.nextBtn = next;
        }
      }
    }

    initGeometry() {
      const box = this.stage.getBoundingClientRect();
      this.width = Math.max(1, Math.round(box.width || 320));
      const side = this.stacked ? 0 : clamp(this.options.peek, 0, this.width * 0.2);
      this.slot = Math.max(40, this.width - side * 2);
      this.cardWidth = this.slot;
      this.cardHeight = this.slot / this.ratio;
      const clipped = !this.options.frame && !this.stacked;
      this.bleed = clipped ? Math.round(Math.min(48, this.cardWidth * 0.12)) : 0;
      this.inset = this.bleed + (this.width - this.cardWidth) / 2;

      this.settings = {
        count: this.count,
        mode: this.mode,
        width: this.width,
        bleed: this.bleed,
        inset: this.inset,
        cardWidth: this.cardWidth,
        span: this.cardWidth + Math.max(0, this.options.gap),
        loop: this.options.loop && this.count > 1,
        autoplay: this.options.autoplay && this.count > 1,
        autoplayDelay: Math.max(600, this.options.autoplayDelay),
        pauseOnHover: this.options.pauseOnHover,
        draggable: this.options.draggable && this.count > 1,
        bars: this.options.indicator === 'bars',
        shade: 0.6
      };

      this.viewport.style.height = `${this.cardHeight}px`;
      if (this.bleed) this.viewport.style.marginInline = `${-this.bleed}px`;
      if (this.mode === 'cube') {
        this.viewport.style.perspective = `${Math.round(this.cardWidth * 2.6)}px`;
      } else if (this.mode === 'tilt') {
        this.viewport.style.perspective = '1000px';
      }

      this.slides.forEach(slide => {
        slide.style.left = `${this.inset}px`;
        slide.style.width = `${this.cardWidth}px`;
        slide.style.height = `${this.cardHeight}px`;
      });

      if (this.controlsContainer) {
        this.controlsContainer.style.width = `${this.cardWidth}px`;
      }

      this.apply();
    }

    unit() {
      return this.mode === 'fade' || this.mode === 'cube' ? this.cardWidth : this.settings.span;
    }

    limit(value) {
      return this.settings.loop ? value : clamp(value, 0, this.count - 1);
    }

    apply() {
      const s = this.settings;
      if (!s) return;

      for (let i = 0; i < s.count; i++) {
        const slide = this.slides[i];
        if (!slide) continue;
        const next = pose(i, this.state.p, s);
        slide.style.transform = next.transform;
        slide.style.opacity = String(next.opacity);
        slide.style.visibility = next.visible ? 'visible' : 'hidden';
        slide.style.zIndex = String(next.layer);
        slide.style.setProperty('--carousel-focus', String(next.focus));
        slide.dataset.current = i === this.state.shown ? 'true' : 'false';
        const shade = slide.querySelector('.carousel-shade');
        if (shade) shade.style.opacity = String(next.shade);
      }

      // Update indicator bars
      if (s.bars && this.fillRefs) {
        const progress = s.autoplay ? clamp(this.state.elapsed / s.autoplayDelay, 0, 1) : 1;
        for (let i = 0; i < s.count; i++) {
          const fill = this.fillRefs[i];
          if (fill) {
            fill.style.transform = `scaleX(${i < this.state.shown ? 1 : i === this.state.shown ? progress : 0})`;
          }
        }
      }

      // Update indicator dots
      if (this.dotRefs) {
        for (let i = 0; i < s.count; i++) {
          this.dotRefs[i]?.classList.toggle('is-active', i === this.state.shown);
        }
      }

      // Arrows disabled state if not looping
      if (!s.loop) {
        if (this.prevBtn) this.prevBtn.disabled = this.state.shown === 0;
        if (this.nextBtn) this.nextBtn.disabled = this.state.shown === s.count - 1;
      }
    }

    announce() {
      const s = this.settings;
      if (!s || !s.count) return;
      const index = s.loop ? wrap(Math.round(this.state.target), s.count) : clamp(Math.round(this.state.target), 0, s.count - 1);
      if (index === this.state.shown) return;
      this.state.shown = index;
      this.active = index;
    }

    paused() {
      const s = this.settings;
      return (
        (s.pauseOnHover && this.state.hovered) ||
        this.state.focused ||
        Boolean(this.state.drag) ||
        this.state.wheeling ||
        !this.state.visible ||
        document.hidden
      );
    }

    tick(now) {
      this.raf = 0;
      if (!this.alive) return;
      const s = this.settings;
      const dt = this.state.last ? Math.min(0.05, (now - this.state.last) / 1000) : 1 / 60;
      this.state.last = now;
      let busy = false;

      if (!this.state.drag?.moved && !this.state.wheeling) {
        const steps = Math.ceil(dt * 240);
        const h = dt / steps;
        for (let k = 0; k < steps; k++) {
          this.state.v += (STIFFNESS * (this.state.target - this.state.p) - DAMPING * this.state.v) * h;
          this.state.p += this.state.v * h;
        }
        if (Math.abs(this.state.target - this.state.p) < 0.0004 && Math.abs(this.state.v) < 0.004) {
          this.state.p = this.state.target;
          this.state.v = 0;
        } else {
          busy = true;
        }

        if (!busy && s.loop && s.count) {
          this.state.target = wrap(Math.round(this.state.target), s.count);
          this.state.p = this.state.target;
        }
      }

      if (s.autoplay && !this.paused()) {
        this.state.elapsed += dt * 1000;
        if (this.state.elapsed >= s.autoplayDelay) this.step(1, true);
        busy = true;
      }

      this.apply();
      if (busy) this.raf = requestAnimationFrame(n => this.tick(n));
      else this.state.last = 0;
    }

    wake() {
      if (!this.raf && this.alive) {
        this.raf = requestAnimationFrame(n => this.tick(n));
      }
    }

    moveTo(target) {
      this.state.target = target;
      this.state.elapsed = 0;
      this.announce();
      this.apply();
      this.wake();
    }

    step(direction, auto = false) {
      const s = this.settings;
      if (s.count < 2) return;
      let next = Math.round(this.state.target) + direction;
      if (!s.loop) {
        if (auto && next > s.count - 1) next = 0;
        next = clamp(next, 0, s.count - 1);
      }
      this.moveTo(next);
    }

    goTo(index) {
      const s = this.settings;
      if (!s.count) return;
      const goal = clamp(Math.round(index), 0, s.count - 1);
      const current = Math.round(this.state.target);
      this.moveTo(s.loop ? current + nearest(goal - wrap(current, s.count), s.count) : goal);
    }

    initEvents() {
      const v = this.viewport;
      v.setAttribute('data-draggable', this.settings.draggable ? 'true' : 'false');

      const onPointerDown = e => {
        if (!this.settings.draggable || (e.pointerType === 'mouse' && e.button !== 0)) return;
        this.state.drag = {
          id: e.pointerId,
          x: e.clientX,
          y: e.clientY,
          p: this.state.p,
          from: 0,
          moved: false,
          samples: []
        };
      };

      const onPointerMove = e => {
        const drag = this.state.drag;
        if (!drag || e.pointerId !== drag.id) return;
        const s = this.settings;
        if (!drag.moved) {
          const dx = e.clientX - drag.x;
          const dy = e.clientY - drag.y;
          if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
          if (Math.abs(dy) > Math.abs(dx)) {
            this.state.drag = null;
            return;
          }
          drag.moved = true;
          drag.x = e.clientX;
          drag.p = this.state.p;
          drag.from = Math.round(this.state.target);
          v.setPointerCapture?.(e.pointerId);
          v.dataset.dragging = 'true';
        }
        const raw = drag.p - (e.clientX - drag.x) / this.unit();
        this.state.p = s.loop ? raw : band(raw, s.count - 1);
        this.state.v = 0;
        this.state.target = Math.round(this.limit(this.state.p));
        drag.samples.push([e.timeStamp, e.clientX]);
        if (drag.samples.length > 8) drag.samples.shift();
        this.announce();
        this.apply();
      };

      const onPointerUp = e => {
        const drag = this.state.drag;
        if (!drag || e.pointerId !== drag.id) return;
        this.state.drag = null;
        delete v.dataset.dragging;
        if (!drag.moved) {
          this.wake();
          return;
        }
        this.state.suppress = true;
        setTimeout(() => {
          this.state.suppress = false;
        }, 0);
        const s = this.settings;
        const sample = drag.samples.find(([time]) => e.timeStamp - time < 90) ?? [e.timeStamp, e.clientX];
        const elapsed = Math.max(16, e.timeStamp - sample[0]);
        const velocity = clamp(((sample[1] - e.clientX) / elapsed) * (1000 / this.unit()), -12, 12);
        const travelled = this.state.p - drag.from;
        let target = Math.round(this.state.p + velocity * 0.2);
        if (target === drag.from) {
          if (Math.abs(velocity) > 0.8) target += Math.sign(velocity);
          else if (Math.abs(travelled) > 0.18) target += Math.sign(travelled);
        }
        if (!s.loop) target = clamp(target, 0, s.count - 1);
        const inside = s.loop || (this.state.p >= 0 && this.state.p <= s.count - 1);
        this.state.v = inside ? velocity : 0;
        this.moveTo(target);
      };

      v.addEventListener('pointerdown', onPointerDown);
      v.addEventListener('pointermove', onPointerMove);
      v.addEventListener('pointerup', onPointerUp);
      v.addEventListener('pointercancel', onPointerUp);

      v.addEventListener('click', e => {
        if (this.state.suppress) {
          e.preventDefault();
          e.stopPropagation();
          this.state.suppress = false;
        }
      }, true);

      // Slide click to navigate if not active
      this.slides.forEach((slide, idx) => {
        slide.addEventListener('click', e => {
          if (idx !== this.active && !this.state.suppress) {
            e.preventDefault();
            this.goTo(idx);
          }
        });
      });

      // Hover pause
      this.root.addEventListener('mouseenter', () => {
        this.state.hovered = true;
      });
      this.root.addEventListener('mouseleave', () => {
        this.state.hovered = false;
        this.wake();
      });

      // Keyboard
      this.root.addEventListener('keydown', e => {
        if (e.target.closest('input, textarea, select')) return;
        if (e.key === 'ArrowRight') {
          e.preventDefault();
          this.step(1);
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          this.step(-1);
        } else if (e.key === 'Home') {
          e.preventDefault();
          this.goTo(0);
        } else if (e.key === 'End') {
          e.preventDefault();
          this.goTo(this.count - 1);
        }
      });

      // Resize
      const resizeObserver = new ResizeObserver(() => {
        this.initGeometry();
        this.wake();
      });
      resizeObserver.observe(this.stage);

      // Visibility
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
          this.state.last = 0;
          this.wake();
        }
      });
    }

    setEffect(newEffect) {
      if (!EFFECTS.includes(newEffect)) return;
      this.mode = newEffect;
      this.stacked = this.mode === 'fade' || this.mode === 'cube';
      this.options.effect = newEffect;
      this.root.dataset.effect = newEffect;
      this.initGeometry();
      this.wake();
    }
  }

  // Auto initialize on DOM ready
  function autoInit() {
    const containers = document.querySelectorAll('.carousel-container');
    containers.forEach(el => {
      if (!el.carouselInstance) {
        el.carouselInstance = new CarouselInstance(el);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  window.CarouselInstance = CarouselInstance;
  window.initCarousels = autoInit;
  window.switchHeroEffect = function(effectName, btnEl) {
    const container = document.querySelector('.hero-carousel-wrap .carousel-container');
    if (container && container.carouselInstance) {
      container.carouselInstance.setEffect(effectName);
      if (btnEl) {
        btnEl.parentElement.querySelectorAll('.effect-pill').forEach(el => el.classList.remove('active'));
        btnEl.classList.add('active');
      }
    }
  };
})();
