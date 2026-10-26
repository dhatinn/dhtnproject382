/**
 * GradualBlur.js - Multi-layer Progressive Backdrop Blur Engine
 * Port of the GradualBlur React component for vanilla HTML/JS
 * Provides smooth progressive backdrop blur vignettes with curves and auto-hide
 */

(function () {
  'use strict';

  const CURVES = {
    linear: t => t,
    bezier: t => t * t * (3 - 2 * t),
    'ease-in': t => t * t,
    'ease-out': t => 1 - (1 - t) * (1 - t),
    'ease-in-out': t => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2)
  };

  const DIRECTIONS = {
    top: 'to top',
    bottom: 'to bottom',
    left: 'to left',
    right: 'to right'
  };

  const clamp01 = value => Math.min(Math.max(value, 0), 1);
  const toLength = value => (typeof value === 'number' ? `${value}px` : value);
  const toTime = value => (typeof value === 'number' ? `${value}s` : value);

  function createGradualBlur(options = {}) {
    const config = Object.assign(
      {
        position: 'bottom',
        target: 'page',
        height: '6rem',
        width: null,
        strength: 2,
        layers: 8,
        curve: 'linear',
        exponential: true,
        opacity: 1,
        saturation: 1,
        tint: 'transparent',
        autoHide: false,
        container: null,
        hoverIntensity: 1,
        duration: 0.3,
        easing: 'ease-out',
        zIndex: 50,
        className: '',
        mountTo: document.body
      },
      options
    );

    const edge = DIRECTIONS[config.position] ? config.position : 'bottom';
    const vertical = edge === 'top' || edge === 'bottom';
    const count = Math.min(Math.max(Math.round(config.layers) || 1, 1), 16);
    const shape = CURVES[config.curve] || CURVES.linear;
    const maxBlur = Math.max(config.strength, 0) * 12;

    const level = u => {
      const t = shape(clamp01(u));
      return maxBlur * (config.exponential ? (Math.pow(2, 4 * t) - 1) / 15 : t);
    };

    const boost = Math.pow(Math.max(config.saturation, 0), 1 / count);
    const fade = `${toTime(config.duration)} ${config.easing}`;
    const direction = DIRECTIONS[edge];

    // Build root element
    const root = document.createElement('div');
    root.className = `gradual-blur ${config.className}`.trim();
    root.setAttribute('aria-hidden', 'true');

    const depth = toLength(vertical ? config.height : (config.width ?? config.height));
    const span = vertical && config.width != null ? toLength(config.width) : null;
    const rounded = config.target !== 'page' && !span;
    const corner = (a, b) => (rounded && (edge === a || edge === b) ? 'inherit' : undefined);

    Object.assign(root.style, {
      position: config.target === 'page' ? 'fixed' : 'absolute',
      zIndex: String(config.zIndex),
      top: edge === 'bottom' ? 'auto' : '0',
      bottom: edge === 'top' ? 'auto' : '0',
      left: edge === 'right' ? 'auto' : '0',
      right: edge === 'left' ? 'auto' : '0',
      height: vertical ? depth : '100%',
      width: vertical ? (span ?? '100%') : depth
    });

    if (span) {
      root.style.marginInline = 'auto';
    }
    if (corner('top', 'left')) root.style.borderTopLeftRadius = 'inherit';
    if (corner('top', 'right')) root.style.borderTopRightRadius = 'inherit';
    if (corner('bottom', 'left')) root.style.borderBottomLeftRadius = 'inherit';
    if (corner('bottom', 'right')) root.style.borderBottomRightRadius = 'inherit';

    // Generate blur layers
    for (let i = 0; i < count; i++) {
      const from = level(i / count);
      const to = level((i + 1) / count);
      const radius = Math.sqrt(Math.max(to * to - from * from, 0));

      const filter = [
        `blur(calc(${radius.toFixed(3)}px * var(--gradual-blur-reveal, 1) * var(--gradual-blur-hover, 1)))`,
        boost !== 1 ? `saturate(calc(1 + ${(boost - 1).toFixed(4)} * var(--gradual-blur-reveal, 1)))` : ''
      ]
        .filter(Boolean)
        .join(' ');

      const mask = `linear-gradient(${direction}, transparent ${((i / count) * 100).toFixed(2)}%, #000 ${(((i + 1) / count) * 100).toFixed(2)}%)`;

      const layer = document.createElement('div');
      layer.className = 'gradual-blur-layer';
      Object.assign(layer.style, {
        position: 'absolute',
        inset: '0',
        borderRadius: 'inherit',
        backdropFilter: filter,
        webkitBackdropFilter: filter,
        maskImage: mask,
        webkitMaskImage: mask,
        opacity: String(config.opacity),
        transition: `backdrop-filter ${fade}, -webkit-backdrop-filter ${fade}`
      });
      root.appendChild(layer);
    }

    // Tint wash layer if defined
    if (config.tint && config.tint !== 'transparent') {
      const stops = Array.from({ length: 9 }, (_, k) => {
        const u = k / 8;
        const t = shape(u);
        const alpha = t * t * t * (t * (t * 6 - 15) + 10);
        return `rgba(0, 0, 0, ${alpha.toFixed(3)}) ${(u * 100).toFixed(1)}%`;
      }).join(', ');
      const tintMask = `linear-gradient(${direction}, ${stops})`;

      const wash = document.createElement('div');
      wash.className = 'gradual-blur-wash';
      Object.assign(wash.style, {
        position: 'absolute',
        inset: '0',
        borderRadius: 'inherit',
        background: config.tint,
        maskImage: tintMask,
        webkitMaskImage: tintMask,
        opacity: 'calc(var(--gradual-blur-reveal, 1) * min(var(--gradual-blur-hover, 1), 1))',
        transition: `opacity ${fade}`
      });
      root.appendChild(wash);
    }

    // Mount to DOM
    const mountTarget = typeof config.mountTo === 'string' ? document.querySelector(config.mountTo) : config.mountTo;
    if (mountTarget) {
      mountTarget.appendChild(root);
    }

    // Scroll reveal logic (autoHide)
    if (config.autoHide) {
      const start = edge === 'top' || edge === 'left';
      const updateScroll = () => {
        const page = document.documentElement;
        const offset = vertical ? window.scrollY : window.scrollX;
        const limit = vertical ? page.scrollHeight - window.innerHeight : page.scrollWidth - window.innerWidth;
        const box = root.getBoundingClientRect();
        const reach = Math.max(vertical ? box.height : box.width, 1);
        const room = start ? offset : limit - offset;
        root.style.setProperty('--gradual-blur-reveal', clamp01(room / reach).toFixed(3));
      };

      updateScroll();
      window.addEventListener('scroll', updateScroll, { passive: true });
      window.addEventListener('resize', updateScroll, { passive: true });
    }

    // Hover intensity
    if (config.hoverIntensity !== 1) {
      let frame = 0;
      let inside = false;
      let point = null;

      const checkHover = () => {
        frame = 0;
        if (!point) return;
        const box = root.getBoundingClientRect();
        const now = point.x >= box.left && point.x <= box.right && point.y >= box.top && point.y <= box.bottom;
        if (now === inside) return;
        inside = now;
        root.style.setProperty('--gradual-blur-hover', inside ? String(Math.max(config.hoverIntensity, 0)) : '1');
      };

      const onMove = event => {
        point = { x: event.clientX, y: event.clientY };
        if (!frame) frame = requestAnimationFrame(checkHover);
      };

      const onLeave = () => {
        point = null;
        inside = false;
        root.style.setProperty('--gradual-blur-hover', '1');
      };

      window.addEventListener('pointermove', onMove, { passive: true });
      document.documentElement.addEventListener('pointerleave', onLeave);
    }

    return root;
  }

  // Auto initialize on DOM ready for data attributes
  function autoInit() {
    const blurNodes = document.querySelectorAll('[data-gradual-blur]');
    blurNodes.forEach(node => {
      const ds = node.dataset;
      createGradualBlur({
        position: ds.gradualBlurPosition || 'bottom',
        target: ds.gradualBlurTarget || 'page',
        height: ds.gradualBlurHeight || '5.5rem',
        strength: Number(ds.gradualBlurStrength) || 2,
        layers: Number(ds.gradualBlurLayers) || 8,
        curve: ds.gradualBlurCurve || 'bezier',
        autoHide: ds.gradualBlurAutohide === 'true',
        tint: ds.gradualBlurTint || 'transparent',
        zIndex: Number(ds.gradualBlurZindex) || 50,
        mountTo: node.parentElement || document.body
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  window.GradualBlur = {
    create: createGradualBlur,
    CURVES,
    DIRECTIONS
  };
})();
