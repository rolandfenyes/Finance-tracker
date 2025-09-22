<?php
$localeOptions = available_locales();
$currentLocale = app_locale();
$localeFlags = [
  'en' => '🇺🇸',
  'hu' => '🇭🇺',
  'es' => '🇪🇸',
];
?>
<section class="grid place-items-center px-4">
  <div class="w-full max-w-md">
    <?php if ($localeOptions): ?>
      <div class="mb-6">
        <div class="bg-white/80 border border-white/40 backdrop-blur-sm rounded-3xl shadow-glass p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 flex items-center gap-2">
            <span>🌐</span>
            <span><?= __('Choose your language') ?></span>
          </p>
          <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
            <?php foreach ($localeOptions as $code => $label): ?>
              <?php
              $isActive = $code === $currentLocale;
              $flag = $localeFlags[$code] ?? '🏳️';
              ?>
              <a
                href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
                class="flex items-center justify-between gap-2 rounded-2xl border px-3 py-2 text-sm font-medium transition-all duration-200 <?= $isActive ? 'bg-gray-900 text-white border-gray-900 shadow-lg shadow-gray-900/20' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-50 hover:shadow-md' ?>"
                title="<?= htmlspecialchars($label) ?>"
                aria-current="<?= $isActive ? 'true' : 'false' ?>"
              >
                <span class="text-xl leading-none"><?= $flag ?></span>
                <span class="flex-1 text-right"><?= htmlspecialchars($label) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-glass overflow-hidden">
      <!-- Header / brand -->
      <div class="p-6 border-b bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl grid place-items-center bg-gray-900 text-white">🔒</div>
          <div>
            <h1 class="text-lg font-semibold leading-tight"><?= __('Welcome back') ?></h1>
            <p class="text-xs text-gray-500"><?= __('Sign in to continue') ?></p>
          </div>
        </div>
        <?php if (!empty($_SESSION['flash'])): ?>
          <p class="mt-3 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Form -->
      <form method="post" action="/login" class="p-6 space-y-4" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <!-- Email -->
        <div>
          <label class="label mb-1"><?= __('Email') ?></label>
          <div class="relative">
            <input
              name="email"
              type="email"
              class="input pl-10 w-full"
              placeholder="<?= __('you@example.com') ?>"
              autocomplete="email"
              inputmode="email"
              required
              autofocus
            />
            <span class="absolute inset-y-0 left-3 grid place-items-center text-gray-400 pointer-events-none">✉️</span>
          </div>
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="label"><?= __('Password') ?></label>
            <a href="/forgot" class="text-xs text-accent hover:underline"><?= __('Forgot?') ?></a>
          </div>
          <div class="relative">
            <input
              id="login-password"
              name="password"
              type="password"
              class="input pl-10 pr-10 w-full"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            />
            <span class="absolute inset-y-0 left-3 grid place-items-center text-gray-400 pointer-events-none">🔑</span>
            <button type="button" class="absolute inset-y-0 right-2 my-auto h-9 px-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100"
                    data-show="<?= __('Show') ?>" data-hide="<?= __('Hide') ?>"
                    onclick="const p=document.getElementById('login-password'); const isPassword=p.type==='password'; p.type=isPassword?'text':'password'; this.textContent=isPassword?this.dataset.hide:this.dataset.show;">
              <?= __('Show') ?>
            </button>
          </div>
        </div>

        <!-- Extras -->
        <div class="flex items-center justify-between">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
            <span><?= __('Remember me') ?></span>
          </label>
          <span class="text-xs text-gray-400"><?= __('Secure by design') ?></span>
        </div>

        <button class="btn btn-primary w-full"><?= __('Sign in') ?></button>

        <p class="text-sm text-gray-500 text-center">
          <?= __('No account?') ?>
          <a class="text-accent hover:underline" href="/register"><?= __('Create one') ?></a>
        </p>
      </form>
    </div>

    <!-- Footer note -->
    <p class="mt-4 text-[11px] text-center text-gray-400">
      <?= __('By continuing you agree to the Terms & Privacy Policy.') ?>
    </p>
  </div>
</section>
