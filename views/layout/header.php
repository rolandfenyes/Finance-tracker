<?php $app = require __DIR__ . '/../../config/config.php'; ?>
<!doctype html>
<html lang="<?= htmlspecialchars(app_locale(), ENT_QUOTES) ?>" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover"
  />
  <title><?= htmlspecialchars($app['app']['name']) ?></title>
  
  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=2">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=2">
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">

  <!-- iOS Home Screen -->
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
  <link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png?v=2">
  <link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png?v=2">

  <!-- Android / PWA -->
  <link rel="manifest" href="/manifest.php?v=3">

  <!-- Branding -->
  <meta name="apple-mobile-web-app-title" content="MyMoneyMap">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-orientation" content="portrait">
  <meta http-equiv="ScreenOrientation" content="autoRotate:disabled">
  <meta name="screen-orientation" content="portrait">
  <meta name="x5-orientation" content="portrait">
  <meta name="x5-fullscreen" content="true">
  <meta name="full-screen" content="yes">
  <?php
    $themeDefinitions = available_themes();
    $selectedTheme = current_theme_slug();
    $selectedThemeMeta = $themeDefinitions[$selectedTheme] ?? [];
    $initialLightThemeColor = $selectedThemeMeta['muted'] ?? '#f8fbf9';
    $initialDarkThemeColor = $selectedThemeMeta['deep'] ?? ($selectedThemeMeta['base'] ?? '#0f1e18');
  ?>
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta
    name="theme-color"
    content="<?= htmlspecialchars($initialLightThemeColor, ENT_QUOTES) ?>"
    data-theme-color="default"
  >
  <meta
    name="theme-color"
    media="(prefers-color-scheme: light)"
    content="<?= htmlspecialchars($initialLightThemeColor, ENT_QUOTES) ?>"
    data-theme-color="light"
  >
  <meta
    name="theme-color"
    media="(prefers-color-scheme: dark)"
    content="<?= htmlspecialchars($initialDarkThemeColor, ENT_QUOTES) ?>"
    data-theme-color="dark"
  >



  <!-- Tailwind CDN (JIT) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    window.__MYMONEYMAP_THEME_BASES = <?= json_encode($themeDefinitions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
    window.__MYMONEYMAP_SELECTED_THEME = <?= json_encode($selectedTheme, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="/theme.js"></script>
  <script>
    (function(){
      const tokens = window.MyMoneyMapTheme || {};
      const brand = tokens.brand || {};
      const palette = brand.palette || {};
      const typography = tokens.typography || {};
      const shadows = tokens.shadows || {};
      const radii = tokens.radii || {};
      const blur = tokens.blur || {};
      const gradients = (tokens.gradients && tokens.gradients.mesh) || {};

      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              brand: palette,
              jade: brand.primary || '#4b966e',
              'brand-muted': brand.muted || '#e6f1eb',
              'brand-deep': brand.deep || '#163428',
              accent: brand.accent || '#3c7b5b'
            },
            fontFamily: {
              brand: typography.fontStack || ['"IBM Plex Sans"', 'sans-serif']
            },
            boxShadow: {
              glass: shadows.glass || '0 30px 60px -25px rgba(17, 36, 29, 0.45)',
              'brand-glow': shadows.brandGlow || '0 20px 45px -20px rgba(75, 150, 110, 0.65)'
            },
            borderRadius: {
              '3xl': radii['3xl'] || '1.75rem',
              '4xl': radii['4xl'] || '2.5rem'
            },
            backdropBlur: {
              xs: blur.xs || '4px'
            },
            backgroundImage: {
              'mesh-light': gradients.light || 'radial-gradient(120% 120% at 10% 20%, rgba(75,150,110,0.18) 0%, transparent 55%), radial-gradient(90% 90% at 90% 0%, rgba(42,94,70,0.12) 0%, transparent 65%), linear-gradient(135deg, #f8fbf9 0%, #eef5f1 50%, #f6faf6 100%)',
              'mesh-dark': gradients.dark || 'radial-gradient(120% 120% at 0% 0%, rgba(75,150,110,0.35) 0%, transparent 60%), radial-gradient(90% 90% at 100% 20%, rgba(28,66,50,0.5) 0%, transparent 70%), linear-gradient(160deg, #060d0b 0%, #0f1e18 55%, #0a1612 100%)'
            }
          }
        }
      };
    })();
  </script>
  <script>
    (function() {
      const nav = window.navigator || {};
      const ua = nav.userAgent || '';
      const platform = nav.platform || '';
      const maxTouchPoints = typeof nav.maxTouchPoints === 'number' ? nav.maxTouchPoints : 0;
      const isIOS = /iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && maxTouchPoints > 1);
      document.documentElement.classList.toggle('is-ios', Boolean(isIOS));
    })();
  </script>
  <script>
    (function() {
      const root = document.documentElement;
      const init = () => {
        if (!root.classList.contains('is-ios')) {
          return;
        }

        const safeAreaVar = '--mm-safe-area-baseline';
        const keyboardVar = '--mm-ios-keyboard-offset';
        const probe = document.createElement('div');
        probe.style.position = 'absolute';
        probe.style.top = '0';
        probe.style.left = '0';
        probe.style.width = '0';
        probe.style.height = '0';
        probe.style.visibility = 'hidden';
        probe.style.pointerEvents = 'none';
        probe.style.paddingBottom = 'env(safe-area-inset-bottom)';
        (document.body || root).appendChild(probe);
        const sampleSafeArea = () => {
          root.style.removeProperty(safeAreaVar);
          const computed = getComputedStyle(probe).paddingBottom;
          const numeric = parseFloat(computed);
          const value = Number.isFinite(numeric) ? numeric : 0;
          root.style.setProperty(safeAreaVar, `${value}px`);
          return value;
        };

        sampleSafeArea();
        let lastKeyboardOffset = 0;
        let rafId = 0;

        const applyKeyboardOffset = () => {
          rafId = 0;
          const viewport = window.visualViewport;
          if (!viewport) {
            return;
          }
          const rawOffset = window.innerHeight - (viewport.height + viewport.offsetTop);
          const offset = rawOffset > 1 ? rawOffset : 0;
          if (offset !== lastKeyboardOffset) {
            root.style.setProperty(keyboardVar, `${offset}px`);
            root.classList.toggle('ios-keyboard-open', offset > 0);
            lastKeyboardOffset = offset;
          }
          if (offset === 0) {
            sampleSafeArea();
          }
        };

        const queueKeyboardUpdate = () => {
          if (rafId) {
            cancelAnimationFrame(rafId);
          }
          rafId = requestAnimationFrame(applyKeyboardOffset);
        };

        const refreshSafeArea = () => {
          if (lastKeyboardOffset > 0) {
            return;
          }
          sampleSafeArea();
        };

        if (window.visualViewport) {
          window.visualViewport.addEventListener('resize', queueKeyboardUpdate);
          window.visualViewport.addEventListener('scroll', queueKeyboardUpdate);
          queueKeyboardUpdate();
        }

        window.addEventListener('orientationchange', () => {
          setTimeout(() => {
            refreshSafeArea();
            queueKeyboardUpdate();
          }, 200);
        });

        window.addEventListener('resize', () => {
          if (!window.visualViewport) {
            refreshSafeArea();
          }
        });

        document.addEventListener('focusin', queueKeyboardUpdate);
        document.addEventListener('focusout', queueKeyboardUpdate);
      };

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
      } else {
        init();
      }
    })();
  </script>
  <script>
    (function() {
      const storageKey = 'mymoneymap-theme';
      const root = document.documentElement;
      const getStored = () => {
        try { return localStorage.getItem(storageKey); } catch (e) { return null; }
      };
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const stored = getStored();
      const initial = stored || (prefersDark ? 'dark' : 'light');
      root.classList.toggle('dark', initial === 'dark');
      root.dataset.theme = initial;
      window.__mymoneymapTheme = { storageKey, initial };
    })();
  </script>
  <script>
    (function () {
      const preventZoom = (event) => {
        if (!event) return;
        if (event.touches && event.touches.length > 1) {
          event.preventDefault();
        }
        if (typeof event.scale === 'number' && event.scale !== 1) {
          event.preventDefault();
        }
      };

      document.addEventListener('gesturestart', preventZoom, { passive: false });
      document.addEventListener('gesturechange', preventZoom, { passive: false });
      document.addEventListener('gestureend', preventZoom, { passive: false });
      document.addEventListener('touchstart', preventZoom, { passive: false });
      document.addEventListener('touchmove', preventZoom, { passive: false });

      let lastTouchEnd = 0;
      document.addEventListener('touchend', (event) => {
        const now = Date.now();
        if (now - lastTouchEnd <= 350) {
          event.preventDefault();
        }
        lastTouchEnd = now;
      }, { passive: false });

      const lockOrientation = () => {
        if (window.screen && window.screen.orientation && typeof window.screen.orientation.lock === 'function') {
          window.screen.orientation.lock('portrait').catch(() => {});
        }
      };

      window.addEventListener('orientationchange', lockOrientation);
      window.addEventListener('load', lockOrientation);

      if (document.documentElement) {
        document.documentElement.style.touchAction = 'manipulation';
      }
    })();
  </script>
  <style type="text/tailwindcss">
    @layer base {
      :root {
        color-scheme: light;
        --mm-safe-area-bottom: env(safe-area-inset-bottom);
        --mm-safe-area-baseline: env(safe-area-inset-bottom);
        --mm-ios-keyboard-offset: 0px;
      }
      :root[data-theme='dark'] {
        color-scheme: dark;
      }
      html {
        touch-action: manipulation;
      }
      body {
        @apply font-brand antialiased min-h-screen transition-colors duration-300 ease-out;
        color: var(--mm-text-color);
        background-image: var(--mm-mesh-background);
        background-attachment: fixed;
        background-size: cover;
        padding-bottom: var(--mm-safe-area-bottom, env(safe-area-inset-bottom));
      }
      html.is-ios body {
        padding-bottom: var(--mm-safe-area-baseline, 0px);
      }
      @media (max-width: 767px) {
        body.has-mobile-nav {
          padding-bottom: calc(var(--mm-safe-area-bottom, env(safe-area-inset-bottom)) + 5.5rem);
        }
        html.is-ios body.has-mobile-nav {
          padding-bottom: calc(var(--mm-safe-area-baseline, 0px) + 5.5rem);
        }
        @supports (bottom: env(keyboard-inset-height)) {
          html:not(.is-ios) body.has-mobile-nav {
            padding-bottom: calc(var(--mm-safe-area-bottom, env(safe-area-inset-bottom)) + env(keyboard-inset-height) + 5.5rem);
          }
        }
      }
      :root[data-theme='dark'] body {
        color: var(--mm-text-color);
        background-image: var(--mm-mesh-background-dark);
      }
      body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -2;
        background: var(--mm-body-glow);
        opacity: 0.85;
        pointer-events: none;
        transition: opacity 0.4s ease;
      }
      :root[data-theme='dark'] body::before {
        opacity: 0.55;
      }
      body.overlay-open {
        overflow: hidden;
      }
      @media (max-width: 640px) {
        body.overlay-open header {
          opacity: 0;
          pointer-events: none;
          transform: translateY(-0.75rem);
        }
        body.overlay-open .mobile-nav {
          transform: translateY(calc(100% + 1.5rem));
          opacity: 0;
          pointer-events: none;
        }
      }
      main {
        @apply flex-1;
      }
      @media (orientation: landscape) and (max-width: 960px) {
        body::after {
          content: attr(data-rotate-message);
          position: fixed;
          inset: 0;
          z-index: 60;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 2.5rem;
          background: rgba(0, 0, 0, 0.8);
          color: #ffffff;
          font-size: 1.125rem;
          text-align: center;
          line-height: 1.6;
        }
      }
    }

    @layer components {
      .chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: 9999px;
        border: 1px solid var(--mm-icon-border);
        background: var(--mm-icon-bg);
        color: var(--mm-icon-color);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        backdrop-filter: blur(var(--mm-blur-xs));
        box-shadow: 0 10px 20px -18px rgba(17, 36, 29, 0.45);
      }
      .edit-panel {
        margin-top: 0.75rem;
        border-radius: 1.25rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        padding: 1rem;
        backdrop-filter: blur(var(--mm-blur-md));
        box-shadow: 0 20px 35px -28px rgba(17, 36, 29, 0.45);
      }
      .color-input {
        height: 2.75rem;
        width: 4rem;
        border-radius: 0.85rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        box-shadow: inset 0 1px 2px rgba(17, 36, 29, 0.12);
      }
      .tab-btn.active {
        font-weight: 600;
        border-bottom-width: 2px;
        border-color: var(--mm-brand-primary, #4b966e);
        color: var(--mm-brand-primary, #4b966e);
      }

      .card {
        position: relative;
        overflow: hidden;
        border-radius: var(--mm-radius-3xl, 1.75rem);
        padding: 1.5rem;
        border: 1px solid var(--mm-card-border);
        background: var(--mm-card-surface);
        box-shadow: var(--mm-shadow-glass);
        backdrop-filter: blur(var(--mm-blur-xl));
        transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
      }
      .card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(135deg, rgba(255,255,255,0.6) 0%, rgba(255,255,255,0) 45%);
        opacity: 0.35;
        pointer-events: none;
      }
      .tile {
        position: relative;
        overflow: hidden;
        border-radius: 1.25rem;
        padding: 1rem;
        border: 1px solid var(--mm-tile-border);
        background: var(--mm-tile-surface);
        backdrop-filter: blur(var(--mm-blur-md));
        box-shadow: 0 18px 32px -26px rgba(17, 36, 29, 0.42);
        transition: background 0.3s ease, border-color 0.3s ease;
      }
      .tile::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(150deg, rgba(75,150,110,0.12), transparent 65%);
        pointer-events: none;
      }
      .panel {
        border-radius: 1.25rem;
        border: 1px solid var(--mm-panel-border);
        background: var(--mm-panel-surface);
        padding: 1.25rem;
        box-shadow: 0 14px 28px -24px rgba(17, 36, 29, 0.42);
        backdrop-filter: blur(var(--mm-blur-md));
      }
      .panel-ghost {
        border-radius: 1.25rem;
        border: 1px solid var(--mm-panel-ghost-border);
        background: var(--mm-panel-ghost-surface);
        padding: 1.25rem;
        box-shadow: 0 12px 24px -20px rgba(17, 36, 29, 0.35);
        backdrop-filter: blur(var(--mm-blur-xs));
      }

      .glass-stack {
        border-radius: 1.25rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-surface);
        overflow: hidden;
        backdrop-filter: blur(var(--mm-blur-md));
        box-shadow: 0 18px 36px -28px rgba(17, 36, 29, 0.4);
      }
      .glass-stack__item {
        position: relative;
        padding: 0.85rem 1.1rem;
        background: var(--mm-list-item);
        transition: background 0.3s ease;
      }
      .glass-stack__item:nth-child(even) {
        background: var(--mm-list-item-alt);
      }
      .glass-stack__item + .glass-stack__item {
        border-top: 1px solid var(--mm-list-divider);
      }

      .card-list {
        border-radius: 1.25rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-surface);
        overflow: hidden;
        backdrop-filter: blur(var(--mm-blur-md));
      }
      .card-list .list-row + .list-row {
        border-top: 1px solid var(--mm-list-divider);
      }
      .list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 1.1rem;
        font-size: 0.95rem;
        color: var(--mm-text-color);
        background: var(--mm-list-item);
      }
      .card-list .list-row:nth-child(even) {
        background: var(--mm-list-item-alt);
      }

      .card-kicker {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3em;
        color: var(--mm-brand-primary, #4b966e);
      }
      .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--mm-text-color);
      }
      .card-subtle {
        font-size: 0.95rem;
        color: var(--mm-subtle-text);
      }

      .field {
        display: grid;
        gap: 0.375rem;
      }
      .label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--mm-subtle-text);
      }
      .help {
        font-size: 0.75rem;
        color: var(--mm-subtle-text);
        opacity: 0.85;
      }

      .input,
      .select,
      .input-select,
      .textarea {
        width: 100%;
        border-radius: 1.1rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        padding: 0.65rem 1rem;
        font-size: 0.95rem;
        color: var(--mm-text-color);
        box-shadow: inset 0 1px 2px rgba(17, 36, 29, 0.08);
        backdrop-filter: blur(var(--mm-blur-xs));
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }
      input[type='date'].input,
      input[type='datetime-local'].input,
      input[type='month'].input,
      input[type='time'].input {
        min-width: 0;
      }
      .input::placeholder,
      .select::placeholder,
      .textarea::placeholder {
        color: var(--mm-subtle-text);
        opacity: 0.7;
      }
      .input:focus,
      .select:focus,
      .input-select:focus,
      .textarea:focus {
        border-color: var(--mm-brand-primary, #4b966e);
        box-shadow: 0 0 0 3px rgba(75, 150, 110, 0.18);
        outline: none;
      }
      .textarea {
        min-height: 104px;
      }

      .input-group {
        position: relative;
        display: flex;
        align-items: stretch;
        overflow: hidden;
        border-radius: 1.1rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        backdrop-filter: blur(var(--mm-blur-xs));
        box-shadow: inset 0 1px 2px rgba(17,36,29,0.08);
      }
      .input-group > .input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 0.65rem 0.9rem;
        box-shadow: none;
      }
      .input-select {
        border: none;
        border-left: 1px solid var(--mm-list-border);
        background: rgba(75,150,110,0.12);
        font-weight: 600;
        color: var(--mm-brand-primary, #4b966e);
      }
      .input-group .ig-input,
      .input-group .ig-select {
        height: 2.75rem;
        background: transparent;
        padding: 0 0.75rem;
        font-size: 0.95rem;
        color: var(--mm-text-color);
        outline: none;
      }
      .input-group .ig-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
      }
      .ig-input[type=number]::-webkit-outer-spin-button,
      .ig-input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
      }
      .ig-input[type=number] {
        -moz-appearance: textfield;
      }

      .btn {
        @apply inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition-all duration-200 ease-out;
      }
      .btn-primary {
        @apply bg-brand-600 text-white shadow-brand-glow hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-300 dark:bg-brand-500 dark:hover:bg-brand-400;
      }
      .btn-ghost {
        @apply border border-white/60 bg-white/60 text-brand-700 hover:bg-brand-50/60 hover:text-brand-800 dark:border-slate-700 dark:bg-slate-900/50 dark:text-brand-100 dark:hover:bg-slate-800;
      }
      .btn-danger {
        @apply border border-rose-200/80 bg-rose-500/90 text-white shadow-sm hover:bg-rose-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-200 dark:border-rose-500/70 dark:bg-rose-500;
      }
      .btn-emerald {
        @apply bg-brand-500 text-white shadow-brand-glow hover:bg-brand-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-200;
      }
      .btn-muted {
        @apply border border-brand-100/70 bg-brand-50/60 text-brand-700 hover:bg-brand-100/70 dark:border-slate-700 dark:bg-slate-900/50 dark:text-brand-100 dark:hover:bg-slate-800;
      }

      .modal {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
        overflow-y: auto;
      }
      .modal-backdrop {
        position: absolute;
        inset: 0;
        background: var(--mm-modal-backdrop);
        backdrop-filter: blur(var(--mm-blur-md));
      }
      .modal-panel {
        position: relative;
        margin: 0;
        max-width: 52rem;
        width: calc(100% - 2rem);
        max-height: calc(100dvh - 12rem);
        min-height: 0;
        border-radius: 1.5rem;
        overflow: hidden;
        border: 1px solid var(--mm-modal-border);
        background: var(--mm-modal-surface);
        box-shadow: 0 40px 80px -40px rgba(11,31,26,0.6);
        backdrop-filter: blur(var(--mm-blur-xl));
        display: flex;
        flex-direction: column;
        color: var(--mm-text-color);
      }
      .modal-header {
        position: sticky;
        top: 0;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        padding: 1rem 1.5rem;
        color: var(--mm-text-color);
      }
      .modal-body {
        flex: 1;
        overflow-y: auto;
        min-height: 0;
        padding: 1.25rem 1.5rem;
        color: var(--mm-text-color);
      }
      .modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 10;
        margin-top: auto;
        border-top: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        padding: 1rem 1.5rem;
      }
      @media (max-width: 640px) {
        .modal {
          align-items: flex-end;
          padding: 0;
        }
        .modal-panel {
          max-width: none;
          width: 100%;
          min-height: 100%;
          max-height: 100vh;
          border-radius: 0;
          border-left: none;
          border-right: none;
        }
        .modal-body {
          max-height: none;
        }
      }

      dialog {
        border: none;
        padding: 0;
      }
      dialog::backdrop {
        background: var(--mm-modal-backdrop);
        backdrop-filter: blur(var(--mm-blur-xl));
      }
      dialog[open] {
        position: fixed;
        inset: 0;
        margin: auto;
        border: 1px solid var(--mm-modal-border);
        padding: 0;
        display: flex;
        flex-direction: column;
        max-height: calc(100dvh - 3rem);
        overflow: hidden;
        background: var(--mm-modal-surface);
        color: var(--mm-text-color);
        z-index: 60;
        backdrop-filter: blur(var(--mm-blur-xl));
      }
      dialog[open] .modal-body {
        flex: 1;
        min-height: 0;
      }
      dialog[open] .modal-footer {
        margin-top: 0;
      }
      @media (max-width: 640px) {
        dialog[open] {
          margin: 0;
          width: 100vw !important;
          max-width: none !important;
          height: 100dvh;
          max-height: none;
          border-radius: 0 !important;
          border: none;
        }
      }

      .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 1rem;
        border: 1px solid var(--mm-icon-border);
        background: var(--mm-icon-bg);
        color: var(--mm-icon-color);
        transition: transform 0.2s ease, box-shadow 0.3s ease, background 0.3s ease, border-color 0.3s ease;
        box-shadow: 0 14px 28px -22px rgba(17, 36, 29, 0.45);
      }
      .icon-btn:hover {
        background: var(--mm-icon-hover);
        transform: translateY(-1px);
        box-shadow: var(--mm-shadow-brand-glow);
      }
      .icon-btn:focus-visible {
        outline: 2px solid rgba(75,150,110,0.35);
        outline-offset: 2px;
      }

      .icon-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.9rem;
        border: 1px solid var(--mm-icon-border);
        background: var(--mm-icon-bg);
        color: var(--mm-icon-color);
        transition: transform 0.2s ease, box-shadow 0.3s ease, background 0.3s ease, border-color 0.3s ease;
        box-shadow: 0 12px 24px -20px rgba(17, 36, 29, 0.4);
      }
      .icon-action:hover {
        background: var(--mm-icon-hover);
        transform: translateY(-1px);
        box-shadow: var(--mm-shadow-brand-glow);
      }
      .icon-action:focus-visible {
        outline: 2px solid rgba(75,150,110,0.35);
        outline-offset: 2px;
      }
      .icon-action--primary {
        border-color: var(--mm-icon-primary-border);
        background: var(--mm-icon-primary-bg);
        color: var(--mm-icon-primary-color);
      }
      .icon-action--primary:hover {
        background: var(--mm-icon-primary-hover);
      }
      .icon-action--danger {
        border-color: var(--mm-icon-danger-border);
        background: var(--mm-icon-danger-bg);
        color: var(--mm-icon-danger-color);
      }
      .icon-action--danger:hover {
        background: var(--mm-icon-danger-hover);
      }

      table.table-glass thead tr {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.16em;
        color: var(--mm-subtle-text);
      }
      table.table-glass tbody tr {
        border-bottom: 1px solid var(--mm-list-divider);
      }
      table.table-glass tbody tr:last-child {
        border-bottom: 0;
      }
    }
  </style>

  <style type="text/tailwindcss">
    @layer components {
      .mobile-nav {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        padding-top: 0.4rem;
        padding-bottom: 0.6rem;
        padding-bottom: calc(var(--mm-safe-area-bottom, env(safe-area-inset-bottom)) + 0.6rem);
        box-shadow: 0 -20px 36px -24px rgba(17, 36, 29, 0.45);
        transition: transform 0.3s ease, opacity 0.3s ease;
        transform: translateZ(0);
        will-change: transform;
        contain: layout paint;
        backface-visibility: hidden;
      }
      @supports (bottom: env(keyboard-inset-height)) {
        html:not(.is-ios) .mobile-nav {
          padding-bottom: calc(var(--mm-safe-area-bottom, env(safe-area-inset-bottom)) + env(keyboard-inset-height) + 0.6rem);
        }
      }
      html.is-ios .mobile-nav {
        bottom: var(--mm-ios-keyboard-offset, 0px);
        padding-bottom: calc(var(--mm-safe-area-baseline, 0px) + 0.6rem);
      }
      .dark .mobile-nav {
        box-shadow: 0 -20px 40px -26px rgba(0, 0, 0, 0.65);
      }
      .mobile-nav__items {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        overflow-x: auto;
        padding: 0 0.5rem;
        scrollbar-width: none;
        -ms-overflow-style: none;
      }
      .mobile-nav__items::-webkit-scrollbar {
        display: none;
      }
      .mobile-nav__link {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-width: 4.5rem;
        border-radius: 1.25rem;
        padding: 0.65rem 0.55rem 0.55rem;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
        color: rgba(51, 65, 85, 0.72);
      }
      .mobile-nav__icon {
        transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
        color: inherit;
      }
      .mobile-nav__link:hover,
      .mobile-nav__link:focus-visible {
        color: var(--mm-brand-primary, #4b966e);
        transform: translateY(-2px);
        background: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.08);
      }
      .mobile-nav__link--active {
        color: var(--mm-brand-primary, #4b966e);
        background: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.12);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.45);
      }
      .mobile-nav__link--active .mobile-nav__icon {
        border-color: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.35);
        background: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.18);
        box-shadow: 0 6px 16px rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.24);
        color: var(--mm-brand-primary, #4b966e);
      }
      .dark .mobile-nav__link {
        color: rgba(226, 232, 240, 0.7);
      }
      .dark .mobile-nav__link:hover,
      .dark .mobile-nav__link:focus-visible {
        background: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.14);
      }
      .dark .mobile-nav__link--active {
        background: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.24);
        box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.18);
      }
      .dark .mobile-nav__link--active .mobile-nav__icon {
        border-color: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.45);
        background: rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.32);
        box-shadow: 0 8px 20px rgba(var(--mm-brand-primary-rgb, 75, 150, 110), 0.32);
        color: var(--mm-brand-50, var(--mm-brand-primary, #4b966e));
      }
    }

    @layer utilities {
      .text-gray-500 { color: #5a7466; }
      .dark .text-gray-500 { color: #9fb6a9; }
      .text-gray-600 { color: #465f55; }
      .dark .text-gray-600 { color: #b4c5bc; }
      .text-gray-700 { color: #2f443a; }
      .dark .text-gray-700 { color: #d1e0d6; }
      .text-gray-400 { color: #7f978c; }
      .dark .text-gray-400 { color: #879c92; }
      .border-gray-200 { border-color: rgba(206, 229, 219, 0.7); }
      .dark .border-gray-200 { border-color: rgba(56, 84, 73, 0.6); }
      .border-gray-300 { border-color: rgba(178, 209, 195, 0.7); }
      .dark .border-gray-300 { border-color: rgba(64, 96, 83, 0.65); }
      .border-gray-100 { border-color: rgba(230, 241, 235, 0.7); }
      .dark .border-gray-100 { border-color: rgba(46, 72, 62, 0.55); }
      .bg-gray-50 { background-color: rgba(241, 247, 244, 0.7); backdrop-filter: blur(14px); }
      .dark .bg-gray-50 { background-color: rgba(20, 33, 28, 0.55); }
      .bg-gray-200 { background-color: rgba(221, 236, 229, 0.65); }
      .dark .bg-gray-200 { background-color: rgba(37, 55, 48, 0.7); }
      .bg-white { background-color: rgba(255, 255, 255, 0.82); backdrop-filter: blur(18px); }
      .dark .bg-white { background-color: rgba(14, 27, 23, 0.72); }
      .bg-gray-900 { background-color: #163428; }
      .dark .bg-gray-900 { background-color: #11241d; }
    }
  </style>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
  <script>
    function themeState() {
      return {
        theme: document.documentElement.dataset.theme || (document.documentElement.classList.contains('dark') ? 'dark' : 'light'),
        init() {
          this.applyTheme(this.theme, true);
          document.addEventListener('mymoneymap:set-theme', (event) => {
            const requested = event && event.detail ? event.detail.theme : null;
            if (requested) {
              this.applyTheme(requested);
            }
          });
          const self = this;
          window.MyMoneyMapThemeController = {
            apply(mode) {
              self.applyTheme(mode);
            },
            toggle() {
              self.toggleTheme();
            },
            current() {
              return self.theme;
            }
          };
        },
        applyTheme(value, isInit = false) {
          const previous = document.documentElement.dataset.theme;
          this.theme = value;
          document.documentElement.dataset.theme = value;
          document.documentElement.classList.toggle('dark', value === 'dark');
          try { localStorage.setItem('mymoneymap-theme', value); } catch (e) {}
          if (window.MyMoneyMapThemeController) {
            window.MyMoneyMapThemeController.theme = value;
          }
          if (isInit || previous !== value) {
            document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: value } }));
          }
        },
        toggleTheme() {
          this.applyTheme(this.theme === 'dark' ? 'light' : 'dark');
        }
      }
    }
  </script>
  <script>
    (function(){
      const registry = new Map();

      const getTokens = () => window.MyMoneyMapTheme || {};
      const getPaletteScale = () => (getTokens().brand && getTokens().brand.palette) || {};
      const clampAlpha = (value) => Math.max(0, Math.min(1, value));
      const withAlpha = (hex, alpha) => {
        const fallback = '#4b966e';
        if (!hex) {
          return clampAlpha(alpha) >= 1 ? fallback : `rgba(75,150,110,${clampAlpha(alpha)})`;
        }
        let value = String(hex).trim();
        if (!value) return fallback;
        if (value.startsWith('#')) value = value.slice(1);
        if (value.length === 3) {
          value = value.split('').map((c) => c + c).join('');
        }
        const alphaHex = Math.round(clampAlpha(alpha) * 255).toString(16).padStart(2, '0');
        return `#${value}${alphaHex}`;
      };

      window.getChartPalette = function() {
        const tokens = getTokens();
        const scale = getPaletteScale();
        const isDark = document.documentElement.classList.contains('dark');
        const axisLight = (tokens.neutrals && tokens.neutrals.text && tokens.neutrals.text.light) || 'rgba(35, 66, 52, 0.82)';
        const axisDark = (tokens.neutrals && tokens.neutrals.text && tokens.neutrals.text.dark) || 'rgba(226, 244, 236, 0.95)';
        const brandPrimary = (tokens.brand && tokens.brand.primary) || '#4b966e';
        const brandSoft = scale[200] || '#c0ddcc';
        const brandAccent = scale[400] || '#69a986';
        const brandDeep = scale[700] || '#32644b';
        const doughnutLights = [scale[500] || brandPrimary, scale[400] || '#69a986', scale[300] || '#94c3aa', scale[200] || '#c0ddcc', scale[600] || '#3c7b5b'];
        const doughnutDarks = [brandSoft, scale[300] || '#94c3aa', scale[400] || '#69a986', scale[500] || brandPrimary, scale[600] || '#3c7b5b'];
        const lightAlphas = [0.88, 0.82, 0.78, 0.74, 0.85];
        const darkAlphas = [0.92, 0.9, 0.86, 0.82, 0.88];

        return {
          isDark,
          axis: isDark ? axisDark : axisLight,
          grid: isDark ? 'rgba(226, 244, 236, 0.15)' : 'rgba(17, 36, 29, 0.08)',
          incomeBar: isDark ? withAlpha(brandSoft, 0.9) : withAlpha(brandAccent, 0.82),
          spendBar: isDark ? 'rgba(255, 169, 190, 0.85)' : 'rgba(236, 104, 134, 0.7)',
          netLine: isDark ? withAlpha(brandSoft, 1) : brandDeep,
          netFillTop: isDark ? withAlpha(brandSoft, 0.24) : withAlpha(brandPrimary, 0.28),
          netFillBottom: isDark ? withAlpha(brandSoft, 0.08) : withAlpha(brandPrimary, 0.04),
          tooltipBg: isDark ? 'rgba(12, 24, 20, 0.94)' : 'rgba(255, 255, 255, 0.96)',
          tooltipText: isDark ? axisDark : axisLight,
          tooltipBorder: isDark ? withAlpha(brandSoft, 0.45) : withAlpha(brandPrimary, 0.35),
          doughnutBorder: isDark ? withAlpha(brandSoft, 0.35) : withAlpha(brandAccent, 0.22),
          doughnutSegments: (isDark ? doughnutDarks : doughnutLights).map((hex, idx) => withAlpha(hex, (isDark ? darkAlphas : lightAlphas)[idx] || 0.85))
        };
      };

      window.registerChartTheme = function(key, reinitializer) {
        if (!key || typeof reinitializer !== 'function') return;
        registry.set(key, reinitializer);
      };

      function reinitializeCharts() {
        for (const [key, fn] of registry.entries()) {
          try {
            const result = fn();
            if (result === false || result == null) {
              registry.delete(key);
            }
          } catch (error) {
            console.error(error);
            registry.delete(key);
          }
        }
      }

      window.updateChartGlobals = function() {
        if (!window.Chart) return;
        const palette = window.getChartPalette();
        const tokens = getTokens();
        const typography = tokens.typography || {};
        const fontStack = Array.isArray(typography.fontStack) ? typography.fontStack.join(', ') : (typography.fontFamily || 'IBM Plex Sans, sans-serif');
        Chart.defaults.color = palette.axis;
        Chart.defaults.borderColor = palette.grid;
        Chart.defaults.font.family = fontStack;
        Chart.defaults.font.weight = '500';
      };

      const refreshCharts = () => {
        window.updateChartGlobals();
        reinitializeCharts();
      };

      document.addEventListener('themechange', refreshCharts);
      document.addEventListener('brandthemechange', refreshCharts);

      document.addEventListener('DOMContentLoaded', () => {
        refreshCharts();
      });
    })();
  </script>
  <script>
    (function(){
      const state = { count: 0 };

      const updateBody = () => {
        const body = document.body;
        if (!body) return;
        if (state.count > 0) {
          body.classList.add('overlay-open');
        } else {
          body.classList.remove('overlay-open');
        }
      };

      const openOverlay = (dialog) => {
        if (dialog) {
          if (dialog.__mmOverlayActive) return;
          dialog.__mmOverlayActive = true;
        }
        state.count += 1;
        updateBody();
      };

      const closeOverlay = (dialog) => {
        if (dialog) {
          if (!dialog.__mmOverlayActive) return;
          dialog.__mmOverlayActive = false;
        }
        state.count = Math.max(0, state.count - 1);
        updateBody();
      };

      window.MyMoneyMapOverlay = {
        open: () => openOverlay(),
        close: () => closeOverlay()
      };

      const trackDialog = (dialog) => {
        if (!(dialog instanceof HTMLDialogElement) || dialog.__mmOverlayTracked) return;
        dialog.__mmOverlayTracked = true;
        dialog.addEventListener('close', () => closeOverlay(dialog));
        dialog.addEventListener('cancel', () => closeOverlay(dialog));
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.attributeName === 'open') {
              if (dialog.hasAttribute('open')) {
                openOverlay(dialog);
              } else {
                closeOverlay(dialog);
              }
            }
          });
        });
        observer.observe(dialog, { attributes: true, attributeFilter: ['open'] });
        if (dialog.hasAttribute('open')) {
          openOverlay(dialog);
        }
      };

      document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('dialog').forEach(trackDialog);
        if (document.body) {
          const watcher = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
              mutation.addedNodes && mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLDialogElement) {
                  trackDialog(node);
                }
              });
            });
          });
          watcher.observe(document.body, { childList: true, subtree: true });
        }
      });

      if (typeof HTMLDialogElement !== 'undefined') {
        ['show', 'showModal'].forEach((method) => {
          const original = HTMLDialogElement.prototype[method];
          if (typeof original !== 'function') return;
          HTMLDialogElement.prototype[method] = function() {
            openOverlay(this);
            return original.apply(this, arguments);
          };
        });
        const originalClose = HTMLDialogElement.prototype.close;
        if (typeof originalClose === 'function') {
          HTMLDialogElement.prototype.close = function() {
            const result = originalClose.apply(this, arguments);
            closeOverlay(this);
            return result;
          };
        }
      }
    })();
  </script>
  <style>[x-cloak]{display:none!important}</style>
  <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $onboarding  = str_starts_with($currentPath, '/onboard');
    $hideMenus   = ($onboarding && $currentPath !== '/onboard/done');

    $desktopItems = [
      ['href'=>'/',              'label'=>'Dashboard',        'match'=>'#^/$#',                    'icon' => 'layout-dashboard'],
      ['href'=>'/current-month', 'label'=>'Months',    'match'=>'#^/current-month$#',       'icon' => 'calendar'],
      ['href'=>'/goals',         'label'=>'Goals',            'match'=>'#^/goals(?:/.*)?$#',       'icon' => 'goal'],
      ['href'=>'/loans',         'label'=>'Loans',            'match'=>'#^/loans(?:/.*)?$#',       'icon' => 'landmark'],
      ['href'=>'/emergency',     'label'=>'Emergency Fund',   'match'=>'#^/emergency(?:/.*)?$#',   'icon' => 'life-buoy'],
      ['href'=>'/scheduled',     'label'=>'Scheduled',        'match'=>'#^/scheduled(?:/.*)?$#',   'icon' => 'calendar-clock'],
      ['href'=>'/feedback',      'label'=>'Feedback',         'match'=>'#^/feedback$#',            'icon' => 'message-circle'],
      ['href'=>'/settings',      'label'=>'Settings',         'match'=>'#^/settings$#',            'icon' => 'settings'],
    ];
    $mobileNavItems = [
      ['href'=>'/',              'label'=>'Dashboard',      'match'=>'#^/$#',                                                        'icon' => 'layout-dashboard'],
      ['href'=>'/current-month', 'label'=>'Months',  'match'=>'#^/(current-month(?:/.*)?|months(?:/.*)?|years(?:/.*)?)$#', 'icon' => 'calendar-range'],
      ['href'=>'/goals',         'label'=>'Goals',          'match'=>'#^/goals(?:/.*)?$#',                                           'icon' => 'goal'],
      // ['href'=>'/emergency',     'label'=>'Emergency', 'match'=>'#^/emergency(?:/.*)?$#',                                       'icon' => 'life-buoy'],
      ['href'=>'/more',          'label'=>'More',           'match'=>'#^/more(?:/.*)?$#',                                            'icon' => 'ellipsis'],
    ];
    function nav_link(array $item, string $currentPath, string $extra=''): string {
      $active = preg_match($item['match'], $currentPath) === 1;
      $base = 'px-3 py-2 rounded-2xl transition-colors text-sm font-medium flex items-center gap-2';
      $cls  = $active
        ? 'bg-brand-600 text-white shadow-brand-glow'
        : 'text-slate-600 hover:text-brand-700 hover:bg-brand-50/70 dark:text-slate-300 dark:hover:text-brand-100 dark:hover:bg-slate-800/70';
      $label = __($item['label']);
      return '<a class="'.$base.' '.$cls.' '.$extra.'" href="'.$item['href'].'"'.($active?' aria-current="page"':'').'>'.htmlspecialchars($label).'</a>';
    }
  $hasMobileNav = (is_logged_in() && !$hideMenus);
  ?>
</head>
<body
  x-data="themeState()"
  x-init="init()"
  class="relative flex min-h-screen flex-col <?= $hasMobileNav ? 'has-mobile-nav' : '' ?>"
  data-rotate-message="<?= htmlspecialchars(__('Please rotate back to portrait for the best experience.'), ENT_QUOTES) ?>"
>
  <div class="pointer-events-none fixed inset-0 -z-10 opacity-60 mix-blend-normal">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(75,150,110,0.18),transparent_55%),radial-gradient(circle_at_80%_0%,rgba(50,100,75,0.22),transparent_65%)] dark:bg-[radial-gradient(circle_at_15%_15%,rgba(75,150,110,0.35),transparent_60%),radial-gradient(circle_at_85%_5%,rgba(18,36,29,0.55),transparent_70%)]"></div>
  </div>

<?php if (is_logged_in()): ?>
  <header class="sticky top-0 z-40 border-b border-white/40 bg-white/60 backdrop-blur-xl transition dark:border-slate-800/60 dark:bg-slate-900/50 hidden md:block">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
        <div class="h-12 w-12 p-2 rounded-xl bg-brand-500 flex flex-col items-center justify-center">
          <img src="/logo.png" alt="App logo" class="h-10 w-10 object-contain" />
        </div>
        
        <span><?= htmlspecialchars($app['app']['name']) ?></span>
      </a>

      <?php if (!$hideMenus): ?>
        <nav class="hidden items-center gap-3 text-sm sm:flex">
          <?php if (is_logged_in()): ?>
            <?php foreach ($desktopItems as $it): echo nav_link($it, $currentPath); endforeach; ?>
            <button type="button" class="icon-btn" @click="toggleTheme()" x-data="{}">
              <span class="sr-only">Toggle theme</span>
              <span x-cloak x-show="theme === 'light'" class="inline-flex">
                <i data-lucide="sun" class="h-5 w-5"></i>
              </span>
              <span x-cloak x-show="theme === 'dark'" class="inline-flex">
                <i data-lucide="moon" class="h-5 w-5"></i>
              </span>
            </button>
            <form action="/logout" method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <button class="btn btn-primary ml-1">
                <?= __('Logout') ?>
              </button>
            </form>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

      <?php if (!$hideMenus): ?>
        <div x-data="{open:false}" class="relative sm:hidden">
          <button @click="open = !open" class="icon-btn">
            <span class="sr-only">Open menu</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div x-cloak x-show="open" @click.outside="open=false" x-transition class="absolute right-0 mt-3 w-64 space-y-2 rounded-3xl border border-white/70 bg-white/95 p-3 shadow-glass backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-900/90">
            <?php if (is_logged_in()): ?>
              <div class="grid gap-2 text-sm">
                <?php foreach ($desktopItems as $it): ?>
                  <?= nav_link($it, $currentPath, 'w-full') ?>
                <?php endforeach; ?>
                <button type="button" class="icon-btn w-full justify-center" @click="toggleTheme(); open=false">
                  <span class="sr-only">Toggle theme</span>
                  <span x-show="theme === 'light'" class="flex items-center gap-2" x-cloak>
                    <i data-lucide="sun" class="h-5 w-5"></i>
                    <span><?= __('Light mode') ?></span>
                  </span>
                  <span x-show="theme === 'dark'" class="flex items-center gap-2" x-cloak>
                    <i data-lucide="moon" class="h-5 w-5"></i>
                    <span><?= __('Dark mode') ?></span>
                  </span>
                </button>
                <form action="/logout" method="post" class="pt-1">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <button class="btn btn-primary w-full"><?= __('Logout') ?></button>
                </form>
              </div>
            <?php else: ?>
              <a class="btn btn-muted w-full" href="/login"><?= __('Login') ?></a>
              <a class="btn btn-primary w-full" href="/register"><?= __('Register') ?></a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>
<?php endif; ?>
<?php if (is_logged_in() && !$hideMenus): ?>
  <nav
    class="mobile-nav fixed inset-x-0 bottom-0 z-40 border-t border-white/60 bg-white/85 backdrop-blur-xl dark:border-slate-800/70 dark:bg-slate-950/70 md:hidden"
    aria-label="<?= htmlspecialchars(__('Primary navigation'), ENT_QUOTES) ?>"
  >
    <ul class="mobile-nav__items w-full justify-between">
      <?php foreach ($mobileNavItems as $it):
        $active = preg_match($it['match'], $currentPath) === 1;
        $label = __($it['label']);
        $icon  = $it['icon'] ?? 'circle';
        $href  = htmlspecialchars($it['href'], ENT_QUOTES);
      ?>
        <li class="flex-1 min-w-[4.5rem]">
          <a
            class="mobile-nav__link <?= $active ? 'mobile-nav__link--active' : '' ?>"
            href="<?= $href ?>"
            title="<?= htmlspecialchars($label) ?>"
            <?= $active ? 'aria-current="page"' : '' ?>
          >
            <span class="mobile-nav__icon flex h-9 w-9 items-center justify-center rounded-full border border-white/60 bg-white/80 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
              <i data-lucide="<?= htmlspecialchars($icon) ?>" class="h-4 w-4"></i>
            </span>
            <span class="text-[0.65rem] leading-3 tracking-wide uppercase">
              <?= htmlspecialchars($label) ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
<?php endif; ?>
  <?php $mainPadding = (is_logged_in() && !$hideMenus) ? 'pb-8' : 'pb-16'; ?>
  <main class="relative z-10 mx-auto w-full max-w-6xl flex-1 px-4 pt-8 <?= $mainPadding ?>">
