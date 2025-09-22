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
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#f1f7f4',
              100: '#dcece2',
              200: '#c0ddcc',
              300: '#94c3aa',
              400: '#69a986',
              500: '#4b966e',
              600: '#3c7b5b',
              700: '#32644b',
              800: '#2b513f',
              900: '#234234',
              950: '#11241d'
            },
            jade: '#4b966e',
            "brand-muted": '#e6f1eb',
            "brand-deep": '#163428',
            accent: '#3c7b5b'
          },
          fontFamily: {
            brand: ['"IBM Plex Sans"', 'Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'sans-serif']
          },
          boxShadow: {
            glass: '0 30px 60px -25px rgba(17, 36, 29, 0.45)',
            "brand-glow": '0 20px 45px -20px rgba(75, 150, 110, 0.65)'
          },
          borderRadius: {
            '3xl': '1.75rem',
            '4xl': '2.5rem'
          },
          backdropBlur: {
            xs: '4px'
          },
          backgroundImage: {
            'mesh-light': 'radial-gradient(120% 120% at 10% 20%, rgba(75,150,110,0.18) 0%, transparent 55%), radial-gradient(90% 90% at 90% 0%, rgba(42,94,70,0.12) 0%, transparent 65%), linear-gradient(135deg, #f8fbf9 0%, #eef5f1 50%, #f6faf6 100%)',
            'mesh-dark': 'radial-gradient(120% 120% at 0% 0%, rgba(75,150,110,0.35) 0%, transparent 60%), radial-gradient(90% 90% at 100% 20%, rgba(28,66,50,0.5) 0%, transparent 70%), linear-gradient(160deg, #060d0b 0%, #0f1e18 55%, #0a1612 100%)'
          }
        }
      }
    }
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
        @apply font-brand antialiased min-h-screen bg-mesh-light text-slate-900 transition-colors duration-300 ease-out dark:bg-mesh-dark dark:text-slate-100;
      }
      body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -2;
        background: radial-gradient(30% 30% at 20% 20%, rgba(75, 150, 110, 0.35), transparent 65%), radial-gradient(45% 45% at 80% 0%, rgba(48, 104, 75, 0.25), transparent 70%);
        opacity: 0.85;
        pointer-events: none;
        transition: opacity 0.4s ease;
      }
      .dark body::before {
        opacity: 0.55;
        background: radial-gradient(40% 40% at 12% 18%, rgba(75, 150, 110, 0.45), transparent 70%), radial-gradient(40% 40% at 85% 10%, rgba(18, 36, 29, 0.55), transparent 75%);
      }
      main {
        @apply flex-1;
      }
    }

    @layer components {
      .chip {
        @apply inline-flex items-center gap-1.5 rounded-full border border-brand-100/70 bg-white/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-brand-700 shadow-sm backdrop-blur-xs dark:border-slate-700 dark:bg-slate-900/60 dark:text-brand-100;
      }
      .row-btn {
        @apply border border-brand-100/70 bg-white/70 text-sm font-medium text-brand-700 px-3 py-1.5 rounded-xl shadow-sm transition hover:bg-brand-50/60 hover:text-brand-800 dark:border-slate-700 dark:bg-slate-900/50 dark:text-brand-100 dark:hover:bg-slate-800;
      }
      .edit-panel {
        @apply mt-3 rounded-2xl border border-brand-100/70 bg-white/70 p-4 shadow-sm backdrop-blur-xs dark:border-slate-800 dark:bg-slate-900/50;
      }
      .color-input {
        @apply h-11 w-16 rounded-xl border border-brand-100/70 bg-white/80 shadow-sm dark:border-slate-700 dark:bg-slate-900/60;
      }
      .tab-btn.active {
        font-weight: 600;
        border-bottom-width: 2px;
        @apply border-brand-500 text-brand-600 dark:text-brand-200;
      }

      .card {
        @apply relative overflow-hidden rounded-3xl border border-white/40 bg-white/70 p-6 shadow-glass backdrop-blur-xl transition dark:border-slate-800/60 dark:bg-slate-900/50 dark:shadow-none;
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
        @apply relative overflow-hidden rounded-2xl border border-white/50 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40;
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
        @apply rounded-2xl border border-white/50 bg-white/70 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/50;
      }
      .panel-ghost {
        @apply rounded-2xl border border-brand-100/70 bg-brand-50/40 shadow-sm backdrop-blur-xs dark:border-slate-800 dark:bg-slate-900/40;
      }

      .card-kicker {
        @apply text-[11px] font-semibold uppercase tracking-[0.3em] text-brand-600 dark:text-brand-200;
      }
      .card-title {
        @apply text-xl font-semibold text-slate-900 dark:text-white;
      }
      .card-subtle {
        @apply text-sm text-slate-600 dark:text-slate-300;
      }
      .card-list {
        @apply divide-y divide-white/50 overflow-hidden rounded-2xl border border-white/50 bg-white/60 backdrop-blur dark:divide-slate-800/70 dark:border-slate-800/70 dark:bg-slate-900/40;
      }
      .list-row {
        @apply flex items-center justify-between px-4 py-3 text-sm text-slate-700 dark:text-slate-200;
      }

      .field { @apply grid gap-1.5; }
      .label {
        @apply text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300;
      }
      .help {
        @apply text-xs text-slate-400 dark:text-slate-500;
      }

      .input,
      .select,
      .input-select,
      .textarea {
        @apply w-full rounded-2xl border border-brand-200/80 bg-white/95 px-4 py-2.5 text-sm text-slate-900 shadow-inner backdrop-blur transition placeholder:text-slate-500 focus:border-brand-400 focus:ring-2 focus:ring-brand-200 focus:outline-none dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-brand-400 dark:focus:ring-brand-500/40;
      }
      .textarea { @apply min-h-[104px]; }

      .input-group {
        @apply relative flex items-stretch overflow-hidden rounded-2xl border border-brand-200/80 bg-white/95 shadow-sm backdrop-blur dark:border-slate-600 dark:bg-slate-900/60;
      }
      .input-group > .input {
        @apply flex-1 rounded-none border-0 bg-transparent px-3.5 py-2.5 text-sm shadow-none focus:ring-0;
      }
      .input-select {
        @apply rounded-none border-0 bg-brand-50/80 px-3.5 py-2 text-sm font-medium text-brand-700 dark:bg-slate-800/60 dark:text-brand-100;
      }
      .input-group .ig-input,
      .input-group .ig-select {
        @apply h-11 bg-transparent px-3 text-sm text-slate-900 outline-none dark:text-slate-100;
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
      .ig-input[type=number] { -moz-appearance: textfield; }

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
        background: rgba(6, 13, 11, 0.6);
        backdrop-filter: blur(12px);
      }
      .modal-panel {
        position: relative;
        margin: 0;
        max-width: 52rem;
        width: calc(100% - 2rem);
        border-radius: 1.5rem;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.25);
        background: rgba(255,255,255,0.85);
        box-shadow: 0 40px 80px -40px rgba(11,31,26,0.6);
        backdrop-filter: blur(22px);
        display: flex;
        flex-direction: column;
      }
      .dark .modal-panel {
        border-color: rgba(30,64,54,0.45);
        background: rgba(15,30,26,0.92);
      }
      .modal-header {
        @apply flex items-center justify-between border-b border-white/40 bg-white/50 px-6 py-4 text-slate-800 dark:border-slate-800/60 dark:bg-slate-900/40 dark:text-slate-100;
      }
      .modal-body {
        @apply max-h-[calc(80vh-7rem)] overflow-y-auto px-6 py-5 text-slate-700 dark:text-slate-200;
      }
      .modal-footer {
        @apply sticky bottom-0 border-t border-white/40 bg-white/60 px-6 py-4 dark:border-slate-800/60 dark:bg-slate-900/40;
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
        background: rgba(6, 13, 11, 0.55);
        backdrop-filter: blur(14px);
      }
      dialog[open] {
        position: fixed;
        inset: 0;
        margin: auto;
        border: 1px solid rgba(255,255,255,0.25);
        padding: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: rgba(255,255,255,0.92);
        z-index: 60;
      }
      .dark dialog[open] {
        border-color: rgba(30,64,54,0.55);
        background: rgba(15,30,26,0.95);
        color: #e4f0ea;
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
        @apply inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/50 bg-white/60 text-brand-600 shadow-sm transition hover:-translate-y-[1px] hover:shadow-brand-glow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-200 dark:border-slate-700 dark:bg-slate-900/50 dark:text-brand-100;
      }
      .icon-btn:hover {
        @apply bg-brand-50/70 dark:bg-slate-800/70;
      }

      table.table-glass thead tr {
        @apply text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300;
      }
      table.table-glass tbody tr {
        @apply border-b border-white/40 dark:border-slate-800/60;
      }
      table.table-glass tbody tr:last-child {
        @apply border-b-0;
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
      const registry = [];
      function cleanupAndRun() {
        for (let i = registry.length - 1; i >= 0; i--) {
          const keep = registry[i]();
          if (keep === false) registry.splice(i, 1);
        }
      }

      window.getChartPalette = function() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
          isDark,
          axis: isDark ? 'rgba(226, 244, 236, 0.9)' : 'rgba(35, 66, 52, 0.82)',
          grid: isDark ? 'rgba(226, 244, 236, 0.15)' : 'rgba(17, 36, 29, 0.08)',
          incomeBar: isDark ? 'rgba(167, 242, 204, 0.92)' : 'rgba(74, 171, 125, 0.82)',
          spendBar: isDark ? 'rgba(255, 169, 190, 0.85)' : 'rgba(236, 104, 134, 0.7)',
          netLine: isDark ? '#8ff0c4' : '#2f6e54',
          netFillTop: isDark ? 'rgba(143, 240, 196, 0.24)' : 'rgba(75,150,110,0.28)',
          netFillBottom: isDark ? 'rgba(143, 240, 196, 0.08)' : 'rgba(75,150,110,0.04)',
          tooltipBg: isDark ? 'rgba(12, 24, 20, 0.94)' : 'rgba(255, 255, 255, 0.96)',
          tooltipText: isDark ? 'rgba(224, 240, 233, 0.95)' : 'rgba(23, 45, 35, 0.88)',
          tooltipBorder: isDark ? 'rgba(134, 214, 176, 0.45)' : 'rgba(75, 150, 110, 0.35)',
          doughnutBorder: isDark ? 'rgba(143, 240, 196, 0.35)' : 'rgba(50, 112, 86, 0.22)',
          doughnutSegments: isDark
            ? ['rgba(159, 235, 194, 0.92)', 'rgba(123, 209, 166, 0.9)', 'rgba(92, 182, 140, 0.86)', 'rgba(191, 241, 215, 0.9)', 'rgba(75, 150, 110, 0.92)']
            : ['rgba(75, 150, 110, 0.9)', 'rgba(112, 181, 144, 0.85)', 'rgba(153, 206, 178, 0.82)', 'rgba(189, 223, 204, 0.8)', 'rgba(58, 125, 92, 0.88)']
        };
      };

      window.registerChartTheme = function(chart, apply) {
        if (!chart || typeof apply !== 'function') return;
        registry.push(() => {
          if (!chart || chart._destroyed || !chart.canvas || !chart.canvas.isConnected) {
            return false;
          }
          apply(chart);
          chart.update('none');
          return true;
        });
      };

      window.updateChartGlobals = function() {
        if (!window.Chart) return;
        const palette = window.getChartPalette();
        Chart.defaults.color = palette.axis;
        Chart.defaults.borderColor = palette.grid;
        Chart.defaults.font.family = getComputedStyle(document.body || document.documentElement).fontFamily || 'IBM Plex Sans, sans-serif';
        Chart.defaults.font.weight = '500';
      };

      document.addEventListener('themechange', () => {
        window.updateChartGlobals();
        cleanupAndRun();
      });

      document.addEventListener('DOMContentLoaded', () => {
        window.updateChartGlobals();
        cleanupAndRun();
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
    function nav_link(array $item, string $currentPath, string $extra=''): string {
      $active = preg_match($item['match'], $currentPath) === 1;
      $base = 'px-3 py-2 rounded-2xl transition-colors text-sm font-medium flex items-center gap-2';
      $cls  = $active
        ? 'bg-brand-600 text-white shadow-brand-glow'
        : 'text-slate-600 hover:text-brand-700 hover:bg-brand-50/70 dark:text-slate-300 dark:hover:text-brand-100 dark:hover:bg-slate-800/70';
      $label = __($item['label']);
      return '<a class="'.$base.' '.$cls.' '.$extra.'" href="'.$item['href'].'"'.($active?' aria-current="page"':'').'>'.htmlspecialchars($label).'</a>';
    }
  ?>

  <header class="sticky top-0 z-40 border-b border-white/40 bg-white/60 backdrop-blur-xl transition dark:border-slate-800/60 dark:bg-slate-900/50">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-600/90 text-white shadow-brand-glow">
          <img src="/logo_simple_light.png" alt="App logo" class="h-10 w-10 object-contain" />
        </span>
        <span><?= htmlspecialchars($app['app']['name']) ?></span>
      </a>

      <?php if (!$hideMenus): ?>
        <nav class="hidden items-center gap-3 text-sm sm:flex">
          <?php if (is_logged_in()): ?>
            <?php foreach ($items as $it): echo nav_link($it, $currentPath); endforeach; ?>
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
                <?php foreach ($items as $it): ?>
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

  <main class="relative z-10 mx-auto w-full max-w-6xl px-4 py-8">
