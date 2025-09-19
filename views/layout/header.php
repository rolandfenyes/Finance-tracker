<?php $app = require __DIR__ . '/../../config/config.php';
$locale = current_locale();
$translationsForJs = translations();
$fallbackTranslations = translations(default_locale());
$availableLocales = available_locales();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$redirectTo = $_SERVER['REQUEST_URI'] ?? '/';
if (!is_string($redirectTo) || $redirectTo === '') { $redirectTo = '/'; }
$items = [
  ['href'=>'/',             'label'=>__('nav.dashboard'),      'match'=>'#^/$#'],
  ['href'=>'/current-month','label'=>__('nav.current_month'), 'match'=>'#^/current-month$#'],
  ['href'=>'/goals',        'label'=>__('nav.goals'),          'match'=>'#^/goals(?:/.*)?$#'],
  ['href'=>'/loans',        'label'=>__('nav.loans'),          'match'=>'#^/loans(?:/.*)?$#'],
  ['href'=>'/emergency',    'label'=>__('nav.emergency'),      'match'=>'#^/emergency(?:/.*)?$#'],
  ['href'=>'/scheduled',    'label'=>__('nav.scheduled'),      'match'=>'#^/scheduled(?:/.*)?$#'],
  ['href'=>'/stocks',       'label'=>__('nav.stocks'),         'match'=>'#^/stocks(?:/.*)?$#'],
  ['href'=>'/years',        'label'=>__('nav.years'),          'match'=>'#^/years(?:/.*)?$#'],
  ['href'=>'/settings',     'label'=>__('nav.settings'),       'match'=>'#^/settings$#'],
];
function nav_link(array $item, string $currentPath, string $extra=''): string {
  $active = preg_match($item['match'], $currentPath) === 1;
  $base = 'px-3 py-1.5 rounded-lg transition';
  $cls  = $active ? 'bg-gray-900 text-white' : 'hover:bg-gray-100 text-gray-800';
  return '<a class="'.$base.' '.$cls.' '.$extra.'" href="'.$item['href'].'">'.htmlspecialchars($item['label']).'</a>';
}
$jsonLocale = json_encode($locale, JSON_UNESCAPED_UNICODE);
$jsonTranslations = json_encode($translationsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$jsonFallback = json_encode($fallbackTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($app['app']['name']) ?></title>
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
  <script>
    window.APP_LOCALE = <?= $jsonLocale ?>;
    window.APP_TRANSLATIONS = <?= $jsonTranslations ?>;
    window.APP_TRANSLATIONS_FALLBACK = <?= $jsonFallback ?>;
    window.t = function(key, params) {
      params = params || {};
      const getter = (source) => {
        return key.split('.').reduce((obj, segment) => (obj && obj[segment] !== undefined) ? obj[segment] : undefined, source);
      };
      let value = getter(window.APP_TRANSLATIONS);
      if (value === undefined) {
        value = getter(window.APP_TRANSLATIONS_FALLBACK);
      }
      if (value === undefined) {
        return key;
      }
      if (typeof value === 'object') {
        return value;
      }
      for (const entry in params) {
        if (Object.prototype.hasOwnProperty.call(params, entry)) {
          value = value.replace(':' + entry, String(params[entry]));
        }
      }
      return value;
    };
  </script>
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="backdrop-blur bg-white/70 sticky top-0 z-40 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
      <a href="/" class="font-semibold tracking-tight text-lg">💎 <?= htmlspecialchars($app['app']['name']) ?></a>

      <!-- Desktop navigation -->
      <div class="hidden sm:flex items-center gap-3 text-sm">
        <nav class="flex items-center gap-2">
          <?php if (is_logged_in()): ?>
            <?php foreach ($items as $it): echo nav_link($it, $currentPath); endforeach; ?>
            <form action="/logout" method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <button class="ml-2 px-3 py-1.5 rounded-lg bg-gray-900 text-white"><?= __('nav.logout') ?></button>
            </form>
          <?php else: ?>
            <a class="px-3 py-1.5 rounded-lg hover:bg-gray-100" href="/login"><?= __('nav.login') ?></a>
            <a class="px-3 py-1.5 rounded-lg hover:bg-gray-100" href="/register"><?= __('nav.register') ?></a>
          <?php endif; ?>
        </nav>
        <form method="post" action="/language" class="flex items-center" onchange="this.submit()">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>" />
          <label for="header-locale" class="sr-only"><?= __('common.language') ?></label>
          <select id="header-locale" name="locale" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
            <?php foreach ($availableLocales as $code => $label): ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $code === $locale ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <!-- Mobile menu -->
      <div x-data="{open:false}" class="sm:hidden relative">
        <button @click="open = !open" class="px-3 py-1.5 rounded-lg border bg-white"><?= __('nav.menu') ?></button>

        <div x-cloak
            x-show="open"
            @click.outside="open=false"
            x-transition
            class="absolute right-0 mt-2 w-56 bg-white border rounded-2xl shadow-glass p-2 z-50">
          <?php if (is_logged_in()): ?>
            <div class="grid gap-1">
              <?php foreach ($items as $it): ?>
                <?= nav_link($it, $currentPath, 'block text-left px-3 py-2 rounded-lg hover:bg-gray-100') ?>
              <?php endforeach; ?>
              <form action="/logout" method="post" class="pt-1">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <button class="w-full px-3 py-2 rounded-lg bg-gray-900 text-white"><?= __('nav.logout') ?></button>
              </form>
            </div>
          <?php else: ?>
            <a class="block px-3 py-2 rounded-lg hover:bg-gray-100" href="/login"><?= __('nav.login') ?></a>
            <a class="block px-3 py-2 rounded-lg hover:bg-gray-100" href="/register"><?= __('nav.register') ?></a>
          <?php endif; ?>
          <form method="post" action="/language" class="mt-3 pt-3 border-t">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>" />
            <label for="mobile-locale" class="block text-xs text-gray-500 mb-1"><?= __('common.language') ?></label>
            <select id="mobile-locale" name="locale" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm" onchange="this.form.submit()">
              <?php foreach ($availableLocales as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $code === $locale ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>

    </div>
  </header>
  <main class="max-w-6xl mx-auto px-4 py-6">
