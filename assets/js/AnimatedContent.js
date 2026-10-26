/**
 * AnimatedContent.js - GSAP & ScrollTrigger Animation Engine
 * Port of the AnimatedContent React component for vanilla HTML/JS
 * Features: distance, direction, scale, blur, rotate, tilt 3D, wipe masks, stagger, and ScrollTrigger
 */

(function () {
  'use strict';

  if (typeof document !== 'undefined') {
    document.documentElement.classList.add('js-ready');
  }

  const WIPES = {
    up: 'linear-gradient(to top, #000 calc(var(--ac-wipe) * 140% - 40%), transparent calc(var(--ac-wipe) * 140%))',
    down: 'linear-gradient(to bottom, #000 calc(var(--ac-wipe) * 140% - 40%), transparent calc(var(--ac-wipe) * 140%))',
    left: 'linear-gradient(to left, #000 calc(var(--ac-wipe) * 140% - 40%), transparent calc(var(--ac-wipe) * 140%))',
    right: 'linear-gradient(to right, #000 calc(var(--ac-wipe) * 140% - 40%), transparent calc(var(--ac-wipe) * 140%))',
    center:
      'radial-gradient(farthest-corner at 50% 50%, #000 calc(var(--ac-wipe) * 140% - 40%), transparent calc(var(--ac-wipe) * 140%))'
  };

  function parseBool(val, fallback) {
    if (val === undefined || val === null || val === '') return fallback;
    if (val === true || val === 'true' || val === '1') return true;
    if (val === false || val === 'false' || val === '0') return false;
    return fallback;
  }

  function parseNumber(val, fallback) {
    if (val === undefined || val === null || val === '') return fallback;
    const num = Number(val);
    return isNaN(num) ? fallback : num;
  }

  function initElement(el, customOptions = {}) {
    if (!el || el.dataset.acInitialized) return;
    el.dataset.acInitialized = 'true';

    // Make sure GSAP is available
    if (typeof gsap === 'undefined') {
      console.warn('AnimatedContent: GSAP is not loaded. Element will be made visible.');
      el.style.visibility = 'visible';
      return;
    }

    if (typeof ScrollTrigger !== 'undefined' && !gsap.plugins?.scrollTrigger) {
      gsap.registerPlugin(ScrollTrigger);
    }

    const ds = el.dataset;

    const options = Object.assign(
      {
        container: ds.container || null,
        distance: parseNumber(ds.distance, 100),
        direction: ds.direction || 'vertical',
        reverse: parseBool(ds.reverse, false),
        duration: parseNumber(ds.duration, 0.8),
        ease: ds.ease || 'power3.out',
        initialOpacity: parseNumber(ds.initialOpacity, 0),
        animateOpacity: parseBool(ds.animateOpacity, true),
        scale: parseNumber(ds.scale, 1),
        blur: parseNumber(ds.blur, 0),
        rotate: parseNumber(ds.rotate, 0),
        tilt: parseNumber(ds.tilt, 0),
        wipe: ds.wipe || 'none',
        threshold: parseNumber(ds.threshold, 0.1),
        delay: parseNumber(ds.delay, 0),
        stagger: parseNumber(ds.stagger, 0),
        staggerFrom: ds.staggerFrom || 'start',
        trigger: ds.trigger || 'scroll',
        once: parseBool(ds.once, true),
        scrub: ds.scrub === 'true' ? true : parseNumber(ds.scrub, false),
        disappearAfter: parseNumber(ds.disappearAfter, 0),
        disappearDuration: parseNumber(ds.disappearDuration, 0.5),
        disappearEase: ds.disappearEase || 'power3.in'
      },
      customOptions
    );

    let scroller = options.container || document.getElementById('snap-main-container') || null;
    if (typeof scroller === 'string') scroller = document.querySelector(scroller);

    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    const targets = options.stagger > 0 && el.children.length ? Array.from(el.children) : [el];
    const horizontal = options.direction === 'horizontal';
    const axis = horizontal ? 'x' : 'y';
    const sign = options.reverse ? -1 : 1;
    const mask = WIPES[options.wipe];

    gsap.set(el, { visibility: 'visible' });

    if (reduce) {
      options.onComplete?.();
      return;
    }

    const from = {
      [axis]: options.distance * sign,
      scale: options.scale,
      opacity: options.animateOpacity ? options.initialOpacity : 1
    };
    const to = {
      [axis]: 0,
      scale: 1,
      opacity: 1,
      duration: options.duration,
      ease: options.ease
    };

    if (options.blur > 0) {
      from.filter = `blur(${options.blur}px)`;
      to.filter = 'blur(0px)';
    }
    if (options.rotate) {
      from.rotation = options.rotate * sign;
      to.rotation = 0;
    }
    if (options.tilt) {
      const tiltAxis = horizontal ? 'rotationY' : 'rotationX';
      from[tiltAxis] = horizontal ? -options.tilt * sign : options.tilt * sign;
      to[tiltAxis] = 0;
      from.transformPerspective = 900;
      to.transformPerspective = 900;
    }
    if (mask) {
      gsap.set(targets, { maskImage: mask, webkitMaskImage: mask });
      from['--ac-wipe'] = 0;
      to['--ac-wipe'] = 1;
    }
    if (options.stagger > 0) {
      to.stagger = { each: options.stagger, from: options.staggerFrom };
    }

    const settle = () => {
      if (options.scrub) return;
      gsap.set(targets, {
        clearProps: mask ? 'filter,transform,maskImage,webkitMaskImage' : 'filter,transform'
      });
    };

    const disappear = () => {
      if (options.disappearAfter <= 0) return;
      const out = {
        [axis]: -options.distance * sign,
        scale: 0.8,
        opacity: options.animateOpacity ? options.initialOpacity : 0,
        delay: options.disappearAfter,
        duration: options.disappearDuration,
        ease: options.disappearEase,
        onComplete: () => options.onDisappearanceComplete?.()
      };
      if (options.blur > 0) out.filter = `blur(${options.blur}px)`;
      if (options.stagger > 0) out.stagger = { each: options.stagger, from: options.staggerFrom };
      gsap.to(targets, out);
    };

    const tl = gsap.timeline({
      paused: true,
      onStart: () => options.onStart?.(),
      onComplete: () => {
        options.onComplete?.();
        settle();
        if (options.once && !options.scrub) disappear();
      }
    });

    tl.fromTo(targets, from, to, options.scrub ? 0 : Math.max(options.delay, 0));

    if (options.trigger === 'mount') {
      tl.play();
      return;
    }

    if (typeof ScrollTrigger === 'undefined') {
      // Fallback if ScrollTrigger isn't loaded: play immediately
      tl.play();
      return;
    }

    const start = `top ${(1 - options.threshold) * 100}%`;

    if (options.scrub) {
      ScrollTrigger.create({
        trigger: el,
        scroller,
        start,
        end: `top ${Math.max((1 - options.threshold) * 100 - 45, 5)}%`,
        scrub: options.scrub === true ? true : Math.max(Number(options.scrub) || 0, 0.01),
        animation: tl
      });
      return;
    }

    ScrollTrigger.create({
      trigger: el,
      scroller,
      start,
      once: options.once,
      onEnter: () => tl.play(),
      onLeaveBack: options.once
        ? undefined
        : () => {
            if (mask) gsap.set(targets, { maskImage: mask, webkitMaskImage: mask });
            tl.reverse();
          }
    });
  }

  function autoInit() {
    const elements = document.querySelectorAll('[data-animated-content], .animated-content');
    elements.forEach(el => initElement(el));

    // Safety fallback: if anything remained uninitialized after 1.2s, force visibility
    setTimeout(() => {
      document.querySelectorAll('[data-animated-content], .animated-content').forEach(el => {
        if (!el.dataset.acInitialized) {
          el.style.visibility = 'visible';
        }
      });
    }, 1200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  window.AnimatedContent = {
    init: autoInit,
    create: initElement,
    WIPES
  };
})();
