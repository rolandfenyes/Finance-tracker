<?php $app = require __DIR__ . '/../../config/config.php'; ?>
<!doctype html>
<html lang="<?= htmlspecialchars(app_locale(), ENT_QUOTES) ?>" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($app['app']['name']) ?></title>
  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">

  <!-- Tailwind CDN (JIT) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <?php
    $themeDefinitions = available_themes();
    $selectedTheme = current_theme_slug();
  ?>
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
  <style type="text/tailwindcss">
    @layer base {
      :root {
        color-scheme: light;
      }
      :root[data-theme='dark'] {
        color-scheme: dark;
      }
      body {
        @apply font-brand antialiased min-h-screen transition-colors duration-300 ease-out;
        color: var(--mm-text-color);
        background-image: var(--mm-mesh-background);
        background-attachment: fixed;
        background-size: cover;
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
        opacity: 0.35;
        pointer-events: none;
        transition: opacity 0.4s ease;
      }
      :root[data-theme='dark'] body::before {
        opacity: 0.28;
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
      }
      main {
        @apply flex-1;
      }
    }

    @layer components {
      .chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        color: var(--mm-subtle-text);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease;
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
      .tab-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.45rem 1rem;
        border-radius: 9999px;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        color: var(--mm-subtle-text);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
      }
      .tab-btn:hover {
        border-color: rgba(75, 150, 110, 0.35);
        color: var(--mm-brand-primary, #4b966e);
      }
      .tab-btn.active {
        color: #fff;
        background: var(--mm-brand-primary, #4b966e);
        border-color: var(--mm-brand-primary, #4b966e);
        box-shadow: 0 16px 32px -24px rgba(75, 150, 110, 0.45);
      }
      .row-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.4rem 0.95rem;
        border-radius: 0.9rem;
        border: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        color: var(--mm-subtle-text);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
      }
      .row-btn:hover {
        border-color: rgba(75, 150, 110, 0.35);
        color: var(--mm-brand-primary, #4b966e);
      }

      .card {
        position: relative;
        border-radius: var(--mm-radius-3xl, 1.75rem);
        padding: 1.75rem;
        border: 1px solid var(--mm-card-border);
        background: var(--mm-card-surface);
        box-shadow: 0 24px 48px -32px rgba(17, 36, 29, 0.35);
        backdrop-filter: blur(var(--mm-blur-md, 12px));
        transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
      }
      :root[data-theme='dark'] .card {
        box-shadow: 0 24px 48px -28px rgba(3, 7, 5, 0.7);
      }
      .tile {
        position: relative;
        border-radius: 1.25rem;
        padding: 1.25rem;
        border: 1px solid var(--mm-tile-border);
        background: var(--mm-tile-surface);
        backdrop-filter: blur(var(--mm-blur-md));
        box-shadow: 0 20px 36px -30px rgba(17, 36, 29, 0.32);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
      }
      .tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 26px 48px -32px rgba(17, 36, 29, 0.34);
      }
      .panel {
        border-radius: 1.25rem;
        border: 1px solid var(--mm-panel-border);
        background: var(--mm-panel-surface);
        padding: 1.25rem;
        box-shadow: 0 20px 38px -30px rgba(17, 36, 29, 0.28);
        backdrop-filter: blur(var(--mm-blur-md));
      }
      .panel-ghost {
        border-radius: 1.25rem;
        border: 1px solid var(--mm-panel-ghost-border);
        background: var(--mm-panel-ghost-surface);
        padding: 1.25rem;
        box-shadow: 0 18px 32px -28px rgba(17, 36, 29, 0.25);
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
        @apply border border-slate-200/80 bg-white/85 text-slate-700 hover:border-brand-300 hover:text-brand-700 dark:border-slate-800/60 dark:bg-slate-900/55 dark:text-slate-200 dark:hover:border-brand-500/50;
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
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--mm-list-border);
        background: var(--mm-list-item);
        padding: 1rem 1.5rem;
        color: var(--mm-text-color);
      }
      .modal-body {
        max-height: calc(80vh - 7rem);
        overflow-y: auto;
        padding: 1.25rem 1.5rem;
        color: var(--mm-text-color);
      }
      .modal-footer {
        position: sticky;
        bottom: 0;
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
          border-radius: 0;
          border-left: none;
          border-right: none;
        }
        .modal-body {
          max-height: calc(100dvh - 8rem);
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
        overflow: hidden;
        background: var(--mm-modal-surface);
        color: var(--mm-text-color);
        z-index: 60;
        backdrop-filter: blur(var(--mm-blur-xl));
      }
      dialog[open] > :last-child {
        flex: 1;
        overflow-y: auto;
      }
      @media (max-width: 640px) {
        dialog[open] {
          margin: 0;
          width: 100vw !important;
          max-width: none !important;
          height: 100dvh;
          border-radius: 0 !important;
          border: none;
        }
      }

      .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.9rem;
        border: 1px solid var(--mm-icon-border);
        background: var(--mm-icon-bg);
        color: var(--mm-icon-color);
        transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        box-shadow: 0 12px 24px -20px rgba(17, 36, 29, 0.35);
      }
      .icon-btn:hover {
        background: var(--mm-icon-hover);
        transform: translateY(-1px);
      }
      .icon-btn:focus-visible {
        outline: 2px solid rgba(75,150,110,0.35);
        outline-offset: 2px;
      }

      .icon-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.1rem;
        height: 2.1rem;
        border-radius: 0.85rem;
        border: 1px solid var(--mm-icon-border);
        background: var(--mm-icon-bg);
        color: var(--mm-icon-color);
        transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        box-shadow: 0 10px 20px -18px rgba(17, 36, 29, 0.32);
      }
      .icon-action:hover {
        background: var(--mm-icon-hover);
        transform: translateY(-1px);
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
      .border-white\/70 { border-color: rgba(210, 232, 221, 0.82); }
      .dark .border-white\/70 { border-color: rgba(47, 70, 60, 0.65); }
      .border-white\/60 { border-color: rgba(206, 229, 219, 0.72); }
      .dark .border-white\/60 { border-color: rgba(47, 70, 60, 0.58); }
      .border-white\/50 { border-color: rgba(198, 224, 212, 0.62); }
      .dark .border-white\/50 { border-color: rgba(43, 66, 56, 0.5); }
      .bg-gray-50 { background-color: rgba(241, 247, 244, 0.7); backdrop-filter: blur(14px); }
      .dark .bg-gray-50 { background-color: rgba(20, 33, 28, 0.55); }
      .bg-gray-200 { background-color: rgba(221, 236, 229, 0.65); }
      .dark .bg-gray-200 { background-color: rgba(37, 55, 48, 0.7); }
      .bg-white { background-color: rgba(255, 255, 255, 0.82); backdrop-filter: blur(18px); }
      .dark .bg-white { background-color: rgba(14, 27, 23, 0.72); }
      .bg-white\/95 { background-color: rgba(255, 255, 255, 0.92); backdrop-filter: blur(18px); }
      .dark .bg-white\/95 { background-color: rgba(12, 24, 20, 0.85); }
      .bg-white\/90 { background-color: rgba(255, 255, 255, 0.88); backdrop-filter: blur(16px); }
      .dark .bg-white\/90 { background-color: rgba(14, 24, 21, 0.8); }
      .bg-white\/80 { background-color: rgba(250, 252, 250, 0.75); backdrop-filter: blur(16px); }
      .dark .bg-white\/80 { background-color: rgba(16, 28, 25, 0.7); }
      .bg-white\/70 { background-color: rgba(246, 250, 247, 0.68); backdrop-filter: blur(12px); }
      .dark .bg-white\/70 { background-color: rgba(18, 30, 27, 0.6); }
      .bg-white\/60 { background-color: rgba(240, 247, 243, 0.62); backdrop-filter: blur(10px); }
      .dark .bg-white\/60 { background-color: rgba(18, 29, 26, 0.55); }
      .bg-gray-900 { background-color: #163428; }
      .dark .bg-gray-900 { background-color: #11241d; }
    }
  </style>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    function themeState() {
      return {
        theme: document.documentElement.dataset.theme || (document.documentElement.classList.contains('dark') ? 'dark' : 'light'),
        init() {
          this.applyTheme(this.theme, true);
        },
        applyTheme(value, isInit = false) {
          const previous = document.documentElement.dataset.theme;
          this.theme = value;
          document.documentElement.dataset.theme = value;
          document.documentElement.classList.toggle('dark', value === 'dark');
          try { localStorage.setItem('mymoneymap-theme', value); } catch (e) {}
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
  <script>
    (function() {
      document.addEventListener('DOMContentLoaded', () => {
        const dialog = document.getElementById('mm-command-palette');
        if (!dialog) return;

        const searchInput = dialog.querySelector('[data-command-search]');
        const items = Array.from(dialog.querySelectorAll('[data-command-item]'));
        const emptyState = dialog.querySelector('[data-command-empty]');
        const closeButtons = dialog.querySelectorAll('[data-command-close]');
        const openers = document.querySelectorAll('[data-command-open]');
        let lastFocused = null;

        const ensureIcons = () => {
          if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
          }
        };

        const filterItems = (term) => {
          const normalized = (term || '').toLowerCase();
          let visibleCount = 0;
          items.forEach((item) => {
            const label = (item.dataset.commandLabel || '').toLowerCase();
            const keywords = (item.dataset.commandKeywords || '').toLowerCase();
            const haystack = `${label} ${keywords}`.trim();
            const matches = normalized === '' || haystack.includes(normalized);
            item.classList.toggle('hidden', !matches);
            if (matches) {
              visibleCount += 1;
            }
          });
          if (emptyState) {
            emptyState.classList.toggle('hidden', visibleCount > 0);
          }
        };

        const openPalette = () => {
          if (dialog.open) {
            return;
          }
          lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
          dialog.showModal();
          ensureIcons();
          filterItems('');
          if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
          }
        };

        const closePalette = (restoreFocus = true) => {
          if (!dialog.open) {
            return;
          }
          dialog.close();
          if (restoreFocus && lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
          }
        };

        openers.forEach((btn) => {
          btn.addEventListener('click', (event) => {
            event.preventDefault();
            openPalette();
          });
        });

        closeButtons.forEach((btn) => {
          btn.addEventListener('click', () => closePalette());
        });

        dialog.addEventListener('cancel', (event) => {
          event.preventDefault();
          closePalette();
        });

        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) {
            closePalette();
          }
        });

        if (searchInput) {
          searchInput.addEventListener('input', () => {
            filterItems(searchInput.value || '');
          });
          searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
              event.preventDefault();
              const visibleItems = items.filter((item) => !item.classList.contains('hidden'));
              if (visibleItems.length) {
                visibleItems[0].focus();
              }
            } else if (event.key === 'Escape') {
              closePalette();
            }
          });
        }

        items.forEach((item) => {
          item.setAttribute('tabindex', '0');
          item.addEventListener('click', () => {
            const href = item.dataset.commandHref || '#';
            closePalette(false);
            window.location.href = href;
          });
          item.addEventListener('keydown', (event) => {
            const visibleItems = items.filter((node) => !node.classList.contains('hidden'));
            const index = visibleItems.indexOf(item);
            if (event.key === 'ArrowDown') {
              event.preventDefault();
              const next = visibleItems[(index + 1) % visibleItems.length];
              next && next.focus();
            } else if (event.key === 'ArrowUp') {
              event.preventDefault();
              if (index <= 0) {
                searchInput && searchInput.focus();
              } else {
                visibleItems[index - 1].focus();
              }
            } else if (event.key === 'Enter') {
              event.preventDefault();
              item.click();
            } else if (event.key === 'Escape') {
              closePalette();
            }
          });
        });

        document.addEventListener('keydown', (event) => {
          const key = event.key.toLowerCase();
          if ((event.metaKey || event.ctrlKey) && key === 'k') {
            event.preventDefault();
            openPalette();
          } else if (key === 'escape' && dialog.open) {
            event.preventDefault();
            closePalette();
          }
        });
      });
    })();
  </script>
  <style>[x-cloak]{display:none!important}</style>
</head>
<body x-data="themeState()" x-init="init()" class="relative">
  <div class="pointer-events-none fixed inset-0 -z-10 opacity-60 mix-blend-normal">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(75,150,110,0.18),transparent_55%),radial-gradient(circle_at_80%_0%,rgba(50,100,75,0.22),transparent_65%)] dark:bg-[radial-gradient(circle_at_15%_15%,rgba(75,150,110,0.35),transparent_60%),radial-gradient(circle_at_85%_5%,rgba(18,36,29,0.55),transparent_70%)]"></div>
  </div>
  <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $onboarding  = str_starts_with($currentPath, '/onboard');
    $hideMenus   = ($onboarding && $currentPath !== '/onboard/done');

    $items = [
      ['href'=>'/',              'label'=>'Dashboard',      'match'=>'#^/$#'],
      ['href'=>'/current-month', 'label'=>'Current Month',  'match'=>'#^/current-month$#'],
      ['href'=>'/goals',         'label'=>'Goals',          'match'=>'#^/goals(?:/.*)?$#'],
      ['href'=>'/loans',         'label'=>'Loans',          'match'=>'#^/loans(?:/.*)?$#'],
      ['href'=>'/emergency',     'label'=>'Emergency',      'match'=>'#^/emergency(?:/.*)?$#'],
      ['href'=>'/scheduled',     'label'=>'Scheduled',      'match'=>'#^/scheduled(?:/.*)?$#'],
      ['href'=>'/feedback',      'label'=>'Feedback',       'match'=>'#^/feedback$#'],
      ['href'=>'/settings',      'label'=>'Settings',       'match'=>'#^/settings$#'],
    ];
    $journeySteps = [
      [
        'href' => '/',
        'label' => __('Orient'),
        'description' => __('Dashboard & current month'),
        'icon' => 'radar',
        'match' => '#^/(?:$|current-month$)#'
      ],
      [
        'href' => '/goals',
        'label' => __('Plan'),
        'description' => __('Goals & automation'),
        'icon' => 'rocket',
        'match' => '#^/(?:goals|scheduled)(?:/.*)?$#'
      ],
      [
        'href' => '/emergency',
        'label' => __('Protect'),
        'description' => __('Emergency fund & loans'),
        'icon' => 'shield-check',
        'match' => '#^/(?:emergency|loans)(?:/.*)?$#'
      ],
      [
        'href' => '/years',
        'label' => __('Review'),
        'description' => __('History, stocks & insights'),
        'icon' => 'line-chart',
        'match' => '#^/(?:years|stocks|tutorial)(?:/.*)?$#'
      ],
    ];
    function nav_link(array $item, string $currentPath, string $extra=''): string {
      $active = preg_match($item['match'], $currentPath) === 1;
      $base = 'inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition-colors';
      $cls  = $active
        ? 'bg-slate-900 text-white shadow-[0_12px_24px_-18px_rgba(15,36,29,0.45)] dark:bg-brand-500 dark:text-white'
        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800/70';
      $label = __($item['label']);
      return '<a class="'.$base.' '.$cls.' '.$extra.'" href="'.$item['href'].'"'.($active?' aria-current="page"':'').'>'.htmlspecialchars($label).'</a>';
    }

    $commandSections = [
      __('Navigation') => [
        ['label' => __('Dashboard'), 'href' => '/', 'icon' => 'layout-dashboard', 'description' => __('Orient · Net worth, next steps'), 'keywords' => 'orient overview'],
        ['label' => __('Current month'), 'href' => '/current-month', 'icon' => 'calendar-days', 'description' => __('Plan · Budgets & transactions'), 'keywords' => 'plan month transactions'],
        ['label' => __('Goals'), 'href' => '/goals', 'icon' => 'target', 'description' => __('Plan · Milestones & funding'), 'keywords' => 'plan goals targets'],
        ['label' => __('Loans'), 'href' => '/loans', 'icon' => 'badge-check', 'description' => __('Protect · Debts & payoffs'), 'keywords' => 'protect debts payoff'],
        ['label' => __('Emergency fund'), 'href' => '/emergency', 'icon' => 'shield', 'description' => __('Protect · Safety net'), 'keywords' => 'protect emergency'],
        ['label' => __('Scheduled payments'), 'href' => '/scheduled', 'icon' => 'calendar-clock', 'description' => __('Plan · Automation hub'), 'keywords' => 'automation recurring schedule'],
        ['label' => __('Stocks'), 'href' => '/stocks', 'icon' => 'briefcase-business', 'description' => __('Review · Portfolio tracker'), 'keywords' => 'invest review stocks'],
        ['label' => __('Yearly timeline'), 'href' => '/years', 'icon' => 'line-chart', 'description' => __('Review · Historical net results'), 'keywords' => 'review years history'],
        ['label' => __('Settings'), 'href' => '/settings', 'icon' => 'settings-2', 'description' => __('Personalize & preferences'), 'keywords' => 'settings profile'],
        ['label' => __('Tutorial'), 'href' => '/tutorial', 'icon' => 'book-open', 'description' => __('Guided walkthrough'), 'keywords' => 'help tutorial guide'],
      ],
      __('Quick actions') => [
        ['label' => __('Add transaction'), 'href' => '/current-month#quick-add', 'icon' => 'plus-circle', 'description' => __('Capture income or spending now'), 'keywords' => 'add transaction quick'],
        ['label' => __('Create a goal'), 'href' => '/goals#create-goal', 'icon' => 'target', 'description' => __('Start a new mission'), 'keywords' => 'goal create plan'],
        ['label' => __('Log emergency deposit'), 'href' => '/emergency#ef-contribute', 'icon' => 'piggy-bank', 'description' => __('Boost the safety net'), 'keywords' => 'emergency deposit'],
        ['label' => __('Add scheduled payment'), 'href' => '/scheduled#create-schedule', 'icon' => 'calendar-plus', 'description' => __('Automate a recurring bill'), 'keywords' => 'schedule recurring add'],
      ],
      __('Support') => [
        ['label' => __('Open feedback form'), 'href' => '/feedback', 'icon' => 'message-circle', 'description' => __('Share ideas or issues'), 'keywords' => 'feedback support'],
        ['label' => __('Switch theme'), 'href' => '/settings/theme', 'icon' => 'palette', 'description' => __('Pick a new palette'), 'keywords' => 'theme appearance'],
        ['label' => __('Read tutorial'), 'href' => '/tutorial', 'icon' => 'graduation-cap', 'description' => __('Step-by-step help'), 'keywords' => 'help tutorial'],
      ],
    ];
  ?>
<?php if (is_logged_in()): ?>
  <header x-data="{ open:false }" @keydown.escape.window="open = false" class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur dark:border-slate-800/70 dark:bg-slate-950/60">
    <div class="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 py-3">
      <a href="/" class="flex items-center gap-2 text-base font-semibold text-slate-900 transition hover:opacity-90 dark:text-white">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/90 text-white shadow-sm">
          <img src="/logo.png" alt="<?= htmlspecialchars($app['app']['name']) ?>" class="h-6 w-6 object-contain" />
        </span>
        <span class="hidden sm:inline"><?= htmlspecialchars($app['app']['name']) ?></span>
      </a>

      <?php if (!$hideMenus): ?>
        <nav class="hidden flex-1 items-center gap-1 lg:flex">
          <?php foreach ($items as $it): echo nav_link($it, $currentPath); endforeach; ?>
        </nav>

        <div class="hidden items-center gap-1 lg:flex">
          <button type="button" class="icon-btn" data-command-open>
            <span class="sr-only"><?= __('Open command palette') ?></span>
            <i data-lucide="search" class="h-5 w-5"></i>
          </button>
          <button type="button" class="icon-btn" @click="toggleTheme()" x-data="{}">
            <span class="sr-only"><?= __('Toggle theme') ?></span>
            <span x-cloak x-show="theme === 'light'"><i data-lucide="sun" class="h-5 w-5"></i></span>
            <span x-cloak x-show="theme === 'dark'"><i data-lucide="moon" class="h-5 w-5"></i></span>
          </button>
          <form action="/logout" method="post" class="inline-flex">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <button class="btn btn-primary"><?= __('Logout') ?></button>
          </form>
        </div>

        <div class="ml-auto flex items-center gap-1 lg:hidden">
          <button type="button" class="icon-btn" data-command-open>
            <span class="sr-only"><?= __('Open command palette') ?></span>
            <i data-lucide="search" class="h-5 w-5"></i>
          </button>
          <button type="button" class="icon-btn" @click="open = !open" :aria-expanded="open ? 'true' : 'false'">
            <span class="sr-only"><?= __('Open menu') ?></span>
            <i data-lucide="menu" class="h-5 w-5"></i>
          </button>
        </div>
      <?php else: ?>
        <div class="ml-auto flex items-center gap-1">
          <button type="button" class="icon-btn" @click="toggleTheme()" x-data="{}">
            <span class="sr-only"><?= __('Toggle theme') ?></span>
            <span x-cloak x-show="theme === 'light'"><i data-lucide="sun" class="h-5 w-5"></i></span>
            <span x-cloak x-show="theme === 'dark'"><i data-lucide="moon" class="h-5 w-5"></i></span>
          </button>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$hideMenus): ?>
      <div x-cloak x-show="open" x-transition class="lg:hidden">
        <div class="mx-auto w-full max-w-6xl px-4 pb-3">
          <div class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_40px_-30px_rgba(15,36,29,0.35)] backdrop-blur dark:border-slate-800/70 dark:bg-slate-950/80" @click.outside="open = false">
            <div class="grid gap-2 text-sm">
              <?php foreach ($items as $it): ?>
                <?= nav_link($it, $currentPath, 'w-full justify-between') ?>
              <?php endforeach; ?>
              <button type="button" class="btn btn-muted w-full justify-center" data-command-open @click="open = false">
                <span class="flex items-center gap-2">
                  <i data-lucide="search" class="h-4 w-4"></i>
                  <span><?= __('Command palette') ?></span>
                </span>
              </button>
            </div>
            <div class="mt-4 flex items-center justify-between gap-2">
              <button type="button" class="icon-btn" @click="toggleTheme(); open = false">
                <span class="sr-only"><?= __('Toggle theme') ?></span>
                <span x-cloak x-show="theme === 'light'"><i data-lucide="sun" class="h-5 w-5"></i></span>
                <span x-cloak x-show="theme === 'dark'"><i data-lucide="moon" class="h-5 w-5"></i></span>
              </button>
              <form action="/logout" method="post" class="flex-1 text-right">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <button class="btn btn-primary w-full"><?= __('Logout') ?></button>
              </form>
            </div>
            <div class="mt-4 border-t border-slate-200/70 pt-4 text-xs text-slate-500 dark:border-slate-800/60 dark:text-slate-400">
              <div class="mb-2 font-semibold uppercase tracking-[0.18em]">
                <?= __('Workflow') ?>
              </div>
              <div class="grid gap-2">
                <?php foreach ($journeySteps as $step):
                  $label = trim((string)$step['label']);
                  if ($label === '') continue;
                  $desc = trim((string)($step['description'] ?? ''));
                  $href = $step['href'] ?? '#';
                  $active = !empty($step['match']) && preg_match($step['match'], $currentPath) === 1;
                  $icon = $step['icon'] ?? 'compass';
                  $cls = $active
                    ? 'border-slate-900 bg-slate-900 text-white dark:border-brand-500 dark:bg-brand-500 dark:text-white'
                    : 'border-slate-200/80 bg-white/85 text-slate-600 dark:border-slate-800/60 dark:bg-slate-900/55 dark:text-slate-300';
                ?>
                  <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="flex items-start justify-between gap-2 rounded-xl border px-3 py-2 <?= $cls ?>" @click="open = false">
                    <span class="flex items-center gap-2 font-semibold">
                      <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" class="h-4 w-4"></i>
                      <span><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
                    </span>
                    <?php if ($desc): ?>
                      <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400"><?= htmlspecialchars($desc, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-200/70 bg-white/70 dark:border-slate-800/70 dark:bg-transparent">
        <div class="mx-auto flex w-full max-w-6xl gap-2 overflow-x-auto px-4 py-3">
          <?php foreach ($journeySteps as $step):
            $label = trim((string)$step['label']);
            if ($label === '') continue;
            $href = $step['href'] ?? '#';
            $desc = trim((string)($step['description'] ?? ''));
            $icon = $step['icon'] ?? 'compass';
            $active = !empty($step['match']) && preg_match($step['match'], $currentPath) === 1;
            $cls = $active
              ? 'border-slate-900 bg-slate-900 text-white shadow-[0_18px_36px_-28px_rgba(15,36,29,0.45)] dark:border-brand-500 dark:bg-brand-500 dark:text-white'
              : 'border-slate-200/80 bg-white/85 text-slate-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-800/60 dark:bg-slate-900/55 dark:text-slate-300 dark:hover:border-brand-500/60 dark:hover:text-brand-200';
          ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="flex min-w-[12rem] flex-col gap-2 rounded-2xl border px-4 py-3 text-sm <?= $cls ?>">
              <div class="flex items-center gap-2 font-semibold">
                <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" class="h-4 w-4"></i>
                <span><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
              </div>
              <?php if ($desc): ?>
                <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($desc, ENT_QUOTES) ?></p>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </header>
  <?php if (!$hideMenus): ?>
    <dialog id="mm-command-palette" aria-label="<?= htmlspecialchars(__('Command palette'), ENT_QUOTES) ?>">
      <div class="modal-panel max-w-2xl">
        <div class="modal-header">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500"><?= __('Command palette') ?></div>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= __('Type to jump anywhere or trigger quick actions.') ?></p>
          </div>
          <button type="button" class="icon-btn" data-command-close>
            <span class="sr-only"><?= __('Close') ?></span>
            <i data-lucide="x" class="h-4 w-4"></i>
          </button>
        </div>
        <div class="modal-body space-y-4">
          <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="search"
                   data-command-search
                   autocomplete="off"
                   class="w-full rounded-2xl border border-slate-200/80 bg-white/90 py-2.5 pl-10 pr-3 text-sm text-slate-700 shadow-inner focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-200"
                   placeholder="<?= __('Search pages or actions…') ?>" />
          </div>
          <div data-command-empty class="hidden rounded-2xl border border-dashed border-slate-200 bg-white/60 p-4 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
            <?= __('No matches. Try different keywords.') ?>
          </div>
          <div data-command-list class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
            <?php foreach ($commandSections as $sectionLabel => $sectionItems):
              $sectionLabel = trim((string)$sectionLabel);
              if ($sectionLabel === '' || empty($sectionItems)) continue;
            ?>
              <div class="space-y-2" data-command-section>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">
                  <?= htmlspecialchars($sectionLabel, ENT_QUOTES) ?>
                </div>
                <div class="space-y-1.5">
                  <?php foreach ($sectionItems as $cmd):
                    $cmdLabel = trim((string)($cmd['label'] ?? ''));
                    if ($cmdLabel === '') continue;
                    $cmdHref = (string)($cmd['href'] ?? '#');
                    $cmdIcon = trim((string)($cmd['icon'] ?? 'arrow-up-right'));
                    $cmdDescription = trim((string)($cmd['description'] ?? ''));
                    $cmdKeywords = strtolower(trim((string)($cmd['keywords'] ?? '')));
                  ?>
                    <button type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white/85 px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-slate-800/70 dark:bg-slate-900/55 dark:text-slate-200 dark:hover:border-brand-500/40"
                            data-command-item
                            data-command-href="<?= htmlspecialchars($cmdHref, ENT_QUOTES) ?>"
                            data-command-label="<?= htmlspecialchars(strtolower($cmdLabel), ENT_QUOTES) ?>"
                            data-command-keywords="<?= htmlspecialchars($cmdKeywords, ENT_QUOTES) ?>">
                      <span class="flex items-center gap-3">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200/80 bg-white/85 dark:border-slate-800/70 dark:bg-slate-900/55">
                          <i data-lucide="<?= htmlspecialchars($cmdIcon, ENT_QUOTES) ?>" class="h-4 w-4"></i>
                        </span>
                        <span class="flex flex-col text-left">
                          <span class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($cmdLabel, ENT_QUOTES) ?></span>
                          <?php if ($cmdDescription): ?>
                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400"><?= htmlspecialchars($cmdDescription, ENT_QUOTES) ?></span>
                          <?php endif; ?>
                        </span>
                      </span>
                      <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300 dark:text-slate-600"><?= __('⌘K / Ctrl+K') ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </dialog>
  <?php endif; ?>
<?php endif; ?>
  <main class="relative z-10 mx-auto w-full max-w-6xl px-4 py-8">
