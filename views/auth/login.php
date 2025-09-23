<?php
$localeOptions = available_locales();
$currentLocale = app_locale();
$localeFlags = [
  'en' => '🇺🇸',
  'hu' => '🇭🇺',
  'es' => '🇪🇸',
];
$app = require __DIR__ . '/../../config/config.php';

$activeFlag = $localeFlags[$currentLocale] ?? '🏳️';
$activeLabel = $localeOptions[$currentLocale] ?? strtoupper($currentLocale);
?>
<section class="mt-10 min-h-full grid place-items-center">
  <div class="relative w-full max-w-md" x-data="{ open:false }">
    <!-- Language: floating flag button (always on top) -->
    <div x-data="{ open:false }" class="fixed top-4 right-4 z-[60]">
      <button
        @click="open=!open" @keydown.escape.window="open=false"
        class="inline-flex items-center gap-2 rounded-2xl border border-white/70 bg-white/80 px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-white/95 dark:border-slate-700 dark:bg-slate-900/70"
        aria-label="Change language">
        <span class="text-xl leading-none"><?= $activeFlag ?></span>
        <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 11.188l3.71-3.958a.75.75 0 111.1 1.02l-4.25 4.53a.75.75 0 01-1.1 0l-4.25-4.53a.75.75 0 01.02-1.06z"/></svg>
      </button>

      <!-- Dropdown (absolute so it won't push the button) -->
      <div
        x-cloak x-show="open" @click.outside="open=false"
        x-transition.origin.top.right
        class="absolute right-0 mt-2 w-56 rounded-2xl border border-white/70 bg-white/95 p-2 shadow-2xl backdrop-blur z-[70] dark:border-slate-700 dark:bg-slate-900/95">
        <div class="grid gap-1">
          <?php foreach ($localeOptions as $code => $label): ?>
            <?php $flag = $localeFlags[$code] ?? '🏳️'; $isActive = $code === $currentLocale; ?>
            <a href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
              class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm <?= $isActive ? 'bg-brand-600 text-white' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200' ?>"
              aria-current="<?= $isActive ? 'true' : 'false' ?>">
              <span class="text-xl leading-none"><?= $flag ?></span>
              <span class="flex-1"><?= htmlspecialchars($label) ?></span>
              <?php if ($isActive): ?><span class="text-xs opacity-80">●</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>



    <!-- Card -->
    <div class="card space-y-6">
      <!-- Brand -->
      <div class="flex flex-col items-center text-center">
         <div class="h-14 w-14 p-2 rounded-xl bg-brand-500 flex flex-col items-center justify-center shadow-sm">
          <img src="/logo.png" alt="App logo" class="h-12 w-12 object-contain" />
        </div>
        <div class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
          <?= htmlspecialchars($app['app']['name']) ?>
        </div>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= __('Personal finance, made simple') ?></p>
      </div>

      <!-- Welcome / Flash -->
      <div class="rounded-2xl border border-brand-500/20 bg-brand-600/10 px-5 py-4 text-brand-900 shadow-inner shadow-brand-500/20 dark:border-brand-400/30 dark:bg-brand-600/20 dark:text-brand-50">
        <div class="flex items-center gap-3">
          <div class="grid h-10 w-10 place-items-center rounded-xl bg-white/80 text-xl shadow-sm dark:bg-slate-900/60">🔒</div>
          <div>
            <h1 class="text-base font-semibold leading-tight"><?= __('Welcome back') ?></h1>
            <p class="text-[11px] text-brand-800/80 dark:text-brand-100/80"><?= __('Sign in to continue') ?></p>
          </div>
        </div>
        <?php if (!empty($_SESSION['flash'])): ?>
          <p class="mt-3 rounded-xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Form -->
      <form method="post" action="/login" class="space-y-4" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="field">
          <label class="label mb-1"><?= __('Email') ?></label>
          <div class="relative">
            <input
              name="email"
              type="email"
              class="input pl-11"
              placeholder="<?= __('you@example.com') ?>"
              autocomplete="email"
              inputmode="email"
              required
              autofocus
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">✉️</span>
          </div>
        </div>

        <div class="field">
          <div class="mb-1 flex items-center justify-between">
            <label class="label"><?= __('Password') ?></label>
            <a href="/forgot" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-200"><?= __('Forgot?') ?></a>
          </div>
          <div class="relative">
            <input
              id="login-password"
              name="password"
              type="password"
              class="input pl-11 pr-12"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">🔑</span>
            <button type="button"
              class="absolute inset-y-0 right-2 my-auto inline-flex h-9 items-center rounded-xl px-3 text-xs font-semibold text-brand-700 transition hover:bg-brand-50/70 dark:text-brand-100 dark:hover:bg-slate-800"
              data-show="<?= __('Show') ?>" data-hide="<?= __('Hide') ?>"
              onclick="const p=document.getElementById('login-password'); const isPwd=p.type==='password'; p.type=isPwd?'text':'password'; this.textContent=isPwd?this.dataset.hide:this.dataset.show;">
              <?= __('Show') ?>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-sm text-slate-600 dark:text-slate-300">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-brand-200 text-brand-600 focus:ring-brand-400" />
            <span><?= __('Remember me') ?></span>
          </label>
          <span class="text-xs text-slate-400 dark:text-slate-500"><?= __('Secure by design') ?></span>
        </div>

        <button class="btn btn-primary w-full text-base"><?= __('Sign in') ?></button>

        <p class="text-center text-sm text-slate-600 dark:text-slate-300">
          <?= __('No account?') ?>
          <a class="font-semibold text-brand-600 hover:underline dark:text-brand-200" href="/register"><?= __('Create one') ?></a>
        </p>
      </form>

      <p class="text-center text-[11px] text-slate-500 dark:text-slate-400">
        <?= __('By continuing you agree to the Terms & Privacy Policy.') ?>
      </p>
    </div>
  </div>
</section>
