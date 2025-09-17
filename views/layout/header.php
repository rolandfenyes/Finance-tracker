<?php $app = require __DIR__ . '/../../config/config.php'; ?>
<!doctype html>
<html lang="en">
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial}</style>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="backdrop-blur bg-white/70 sticky top-0 z-40 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="/" class="font-semibold tracking-tight text-lg">💎 <?= htmlspecialchars($app['app']['name']) ?></a>
      <?php
        // current path for active-state matching
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // define nav items with regex matchers for subpages
        $items = [
          ['href'=>'/',            'label'=>'Dashboard',      'match'=>'#^/$#'],
          ['href'=>'/current-month','label'=>'Current Month', 'match'=>'#^/current-month$#'],
          ['href'=>'/years',       'label'=>'Years',          'match'=>'#^/years(?:/.*)?$#'],
          ['href'=>'/goals',       'label'=>'Goals',          'match'=>'#^/goals(?:/.*)?$#'],
          ['href'=>'/loans',       'label'=>'Loans',          'match'=>'#^/loans(?:/.*)?$#'],
          ['href'=>'/stocks',      'label'=>'Stocks',         'match'=>'#^/stocks(?:/.*)?$#'],
          ['href'=>'/scheduled',   'label'=>'Scheduled',      'match'=>'#^/scheduled(?:/.*)?$#'],
          ['href'=>'/emergency',   'label'=>'Emergency',      'match'=>'#^/emergency(?:/.*)?$#'],
          ['href'=>'/settings',    'label'=>'Settings',       'match'=>'#^/settings$#'],
        ];

        function nav_link(array $item, string $currentPath, string $extra=''): string {
          $active = preg_match($item['match'], $currentPath) === 1;
          $base = 'px-3 py-1.5 rounded-lg transition';
          $cls  = $active ? 'bg-gray-900 text-white' : 'hover:bg-gray-100 text-gray-800';
          return '<a class="'.$base.' '.$cls.' '.$extra.'" href="'.$item['href'].'">'.htmlspecialchars($item['label']).'</a>';
        }
      ?>

      <!-- Desktop nav -->
      <nav class="hidden sm:flex items-center gap-2 text-sm">
        <?php if (is_logged_in()): ?>
          <?php foreach ($items as $it): echo nav_link($it, $currentPath); endforeach; ?>
          <form action="/logout" method="post" class="inline">
            <button class="ml-2 px-3 py-1.5 rounded-lg bg-gray-900 text-white">Logout</button>
          </form>
        <?php else: ?>
          <a class="px-3 py-1.5 rounded-lg hover:bg-gray-100" href="/login">Login</a>
          <a class="px-3 py-1.5 rounded-lg hover:bg-gray-100" href="/register">Register</a>
        <?php endif; ?>
      </nav>

      <!-- Mobile: compact dropdown panel -->
      <div x-data="{open:false}" class="sm:hidden relative">
        <button @click="open = !open" class="px-3 py-1.5 rounded-lg border bg-white">Menu</button>

        <div x-cloak
            x-show="open"
            @click.outside="open=false"
            x-transition
            class="absolute right-0 mt-2 w-56 bg-white border rounded-2xl shadow-glass p-2 z-50">
          <?php if (is_logged_in()): ?>
            <div class="grid gap-1">
              <?php foreach ($items as $it): ?>
                <?= nav_link($it, $currentPath, 'block text-left') ?>
              <?php endforeach; ?>
              <form action="/logout" method="post" class="pt-1">
                <button class="w-full px-3 py-2 rounded-lg bg-gray-900 text-white">Logout</button>
              </form>
            </div>
          <?php else: ?>
            <a class="block px-3 py-2 rounded-lg hover:bg-gray-100" href="/login">Login</a>
            <a class="block px-3 py-2 rounded-lg hover:bg-gray-100" href="/register">Register</a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </header>
  <main class="max-w-6xl mx-auto px-4 py-6">