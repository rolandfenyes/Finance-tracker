<?php $app = require __DIR__ . '/../../config/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($app['app']['name']) ?></title>
  <!-- Favicons -->

  <!-- Classic ICO -->
  <link rel="icon" type="image/x-icon" href="/favicon.ico">

  <!-- PNG -->
  <link rel="icon" type="image/png" href="/favicon.png">

  <!-- Apple Touch Icon (for iOS) -->
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">

  <!-- Tailwind CDN (JIT) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { DEFAULT: '#111827' },
            accent: '#B81730'
          },
          boxShadow: { glass: '0 10px 30px rgba(0,0,0,0.08)'}
        }
      }
    }
  </script>
  <style type="text/tailwindcss">
    @layer components {
      .chip{ @apply inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700 border; }
      .row-btn{ @apply border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl px-3 py-1.5 text-sm; }
      .edit-panel{ @apply mt-3 bg-gray-50 rounded-xl p-4 border; }
      .color-input{ @apply h-10 w-14 rounded-lg border border-gray-300; }
      .tab-btn.active { background:#0f172a0d; font-weight:600; border-bottom:2px solid #111827; }

      details[open] ~ .header-line,
      details[open] .header-line {
        @apply hidden;
      }

      .card { @apply bg-white rounded-2xl p-5 shadow-glass; }
      .field { @apply grid gap-1; }
      .label { @apply text-sm font-medium text-gray-700; }
      .help  { @apply text-xs text-gray-400; }

      .input, .select, .input-select, .textarea {
        @apply w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
              focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900;
      }
      .textarea { @apply min-h-[92px]; }

      .input-group { @apply flex rounded-xl border border-gray-300 bg-white shadow-sm overflow-hidden; }
      .input-group > .input { @apply border-0 rounded-none flex-1 shadow-none; }
      .input-select { @apply border-0 rounded-none bg-gray-50 px-2; }

      .input-group { @apply relative flex items-stretch rounded-xl border border-gray-300 bg-white shadow-sm overflow-hidden; }
      .input-group .ig-input  { @apply flex-1 px-3 py-2 text-sm bg-white outline-none; }
      .input-group .ig-select { @apply px-3 pr-8 text-sm bg-white outline-none; }

      /* unify heights & remove browser styles */
      .input-group .ig-input,
      .input-group .ig-select { @apply h-10; }
      .input-group .ig-select { appearance:none; -webkit-appearance:none; -moz-appearance:none; }

      /* hide number spinners */
      .ig-input[type=number]::-webkit-outer-spin-button,
      .ig-input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
      .ig-input[type=number] { -moz-appearance: textfield; }

      .btn { @apply inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium transition; }
      .btn-primary { @apply bg-gray-900 text-white hover:opacity-90 active:opacity-80; }
      .btn-ghost   { @apply border border-gray-300 text-gray-700 hover:bg-gray-50; }
      .btn-danger  { @apply border border-red-300 text-red-600 hover:bg-red-50; }
      .btn-emerald { background:#059669; color:#fff; border-radius:.75rem; padding:.5rem .9rem; }

      /* Base containers */
      .card { @apply bg-white rounded-2xl p-5 shadow-glass; }
      .tile { @apply card transition hover:shadow-md; } /* clickable card */

      /* Headers & text */
      .card-kicker { @apply text-xs uppercase tracking-wide text-gray-500; }
      .card-title  { @apply text-lg font-semibold; }
      .card-subtle { @apply text-sm text-gray-500; }

      /* Lists inside cards */
      .card-list  { @apply divide-y rounded-xl border; }
      .list-row   { @apply flex items-center justify-between p-3; }

      /* Modal base */
      .modal { position: fixed; inset: 0; z-index: 50; }
      .modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.45); }
      .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; }
      .modal-body   { padding: 1.25rem; max-height: calc(80vh - 7rem); overflow:auto; }
      .modal-footer { padding: .75rem 1.25rem; border-top: 1px solid #e5e7eb; position: sticky; bottom: 0; }
      .icon-btn { padding: .25rem .5rem; border-radius: .5rem; }
      .icon-btn:hover { background: #f3f4f6; }

      /* Make the panel rounded and clip children so header/footer match */
      .modal-panel {
        border-radius: 1rem;          /* rounded-xl */
        overflow: hidden;             /* ensures header/footer corners are rounded too */
      }

      /* Default (desktop/tablet) */
      .modal-panel {
        position: relative;
        margin: 2.5rem auto;
        max-width: 52rem;       /* ~832px */
        width: calc(100% - 2rem);
        background: #fff;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,.25);
        border-radius: 1rem;
        overflow: hidden;
      }

      /* Mobile fullscreen modal */
      @media (max-width: 767px) {
        .modal-panel {
          margin: 0;             /* remove outer margin */
          width: 100%;
          max-width: 100%;
          height: 100%;          /* take up entire height */
          max-height: 100%;
          border-radius: 0;      /* no rounded corners in fullscreen */
        }

        .modal-body {
          max-height: calc(100vh - 6rem); /* leave room for header + footer */
          overflow-y: auto;
        }

        .modal-header,
        .modal-footer {
          border-radius: 0;
        }
      }


    }
  </style>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial}</style>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gray-50 text-gray-900">
  <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $onboarding  = str_starts_with($currentPath, '/onboard');
    $hideMenus   = ($onboarding && $currentPath !== '/onboard/done');

    // nav items
    $items = [
      ['href'=>'/',              'label'=>'Dashboard',      'match'=>'#^/$#'],
      ['href'=>'/current-month', 'label'=>'Current Month',  'match'=>'#^/current-month$#'],
      ['href'=>'/goals',         'label'=>'Goals',          'match'=>'#^/goals(?:/.*)?$#'],
      ['href'=>'/loans',         'label'=>'Loans',          'match'=>'#^/loans(?:/.*)?$#'],
      ['href'=>'/emergency',     'label'=>'Emergency',      'match'=>'#^/emergency(?:/.*)?$#'],
      ['href'=>'/scheduled',     'label'=>'Scheduled',      'match'=>'#^/scheduled(?:/.*)?$#'],
      // ['href'=>'/stocks',        'label'=>'Stocks',         'match'=>'#^/stocks(?:/.*)?$#'],
      // ['href'=>'/years',         'label'=>'Years',          'match'=>'#^/years(?:/.*)?$#'],
      ['href'=>'/feedback', 'label'=>'Feedback', 'match'=>'#^/feedback$#'],
      ['href'=>'/settings',      'label'=>'Settings',       'match'=>'#^/settings$#'],
    ];
    function nav_link(array $item, string $currentPath, string $extra=''): string {
      $active = preg_match($item['match'], $currentPath) === 1;
      $base = 'px-3 py-1.5 rounded-lg transition';
      $cls  = $active ? 'bg-gray-900 text-white' : 'hover:bg-gray-100 text-gray-800';
      return '<a class="'.$base.' '.$cls.' '.$extra.'" href="'.$item['href'].'">'.htmlspecialchars($item['label']).'</a>';
    }
  ?>

  <header class="backdrop-blur bg-white/70 sticky top-0 z-40 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="/" class="font-semibold tracking-tight text-lg flex items-center gap-2">
        <img src="/logo_simple_light.png" alt="App logo" class="h-14 w-14">
        <?= htmlspecialchars($app['app']['name']) ?>
      </a>


      <!-- Desktop nav (hidden during onboarding, except /onboard/done) -->
      <?php if (!$hideMenus): ?>
        <nav class="hidden sm:flex items-center gap-2 text-sm">
          <?php if (is_logged_in()): ?>
            <?php foreach ($items as $it): echo nav_link($it, $currentPath); endforeach; ?>
            <form action="/logout" method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <button class="ml-2 px-3 py-1.5 rounded-lg bg-gray-900 text-white">Logout</button>
            </form>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

      <!-- Mobile menu (also hidden during onboarding, except /onboard/done) -->
      <?php if (!$hideMenus): ?>
        <div x-data="{open:false}" class="sm:hidden relative">
          <button @click="open = !open" class="px-3 py-1.5 rounded-lg border bg-white">Menu</button>
          <div x-cloak x-show="open" @click.outside="open=false" x-transition
               class="absolute right-0 mt-2 w-56 bg-white border rounded-2xl shadow-glass p-2 z-50">
            <?php if (is_logged_in()): ?>
              <div class="grid gap-1">
                <?php foreach ($items as $it): ?>
                  <?= nav_link($it, $currentPath, 'block text-left') ?>
                <?php endforeach; ?>
                <form action="/logout" method="post" class="pt-1">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <button class="w-full px-3 py-2 rounded-lg bg-gray-900 text-white">Logout</button>
                </form>
              </div>
            <?php else: ?>
              <a class="block px-3 py-2 rounded-lg hover:bg-gray-100" href="/login">Login</a>
              <a class="block px-3 py-2 rounded-lg hover:bg-gray-100" href="/register">Register</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-6">