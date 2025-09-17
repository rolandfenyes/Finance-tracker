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
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="backdrop-blur bg-white/70 sticky top-0 z-40 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="/" class="font-semibold tracking-tight text-lg">💎 <?= htmlspecialchars($app['app']['name']) ?></a>
      <nav class="flex items-center gap-4 text-sm">
        <?php if (is_logged_in()): ?>
          <a class="hover:text-accent" href="/current-month">Current Month</a>
          <a class="hover:text-accent" href="/settings">Settings</a>
          <form action="/logout" method="post" class="inline"><button class="px-3 py-1.5 rounded-lg bg-gray-900 text-white">Logout</button></form>
        <?php else: ?>
          <a class="hover:text-accent" href="/login">Login</a>
          <a class="hover:text-accent" href="/register">Register</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="max-w-6xl mx-auto px-4 py-6">