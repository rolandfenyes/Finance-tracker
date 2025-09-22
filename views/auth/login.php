<?php
$localeOptions = available_locales();
$currentLocale = app_locale();
$localeFlags = [
  'en' => '🇺🇸',
  'hu' => '🇭🇺',
  'es' => '🇪🇸',
];
?>
<section class="grid min-h-[70vh] place-items-center px-4 py-10">
  <div class="w-full max-w-md space-y-6">
    <?php if ($localeOptions): ?>
      <div class="panel backdrop-blur-xl p-4">
        <p class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">
          <span>🌐</span>
          <span><?= __('Choose your language') ?></span>
        </p>
        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
          <?php foreach ($localeOptions as $code => $label): ?>
            <?php
            $isActive = $code === $currentLocale;
            $flag = $localeFlags[$code] ?? '🏳️';
            ?>
            <a
              href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
              class="flex items-center justify-between gap-2 rounded-2xl border px-3 py-2 text-sm font-medium transition-all duration-200 <?= $isActive ? 'border-brand-500 bg-brand-600 text-white shadow-brand-glow' : 'border-white/60 bg-white/60 text-slate-600 hover:border-brand-200 hover:bg-brand-50/70 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:hover:bg-slate-800/70' ?>"
              title="<?= htmlspecialchars($label) ?>"
              aria-current="<?= $isActive ? 'true' : 'false' ?>"
            >
              <span class="text-xl leading-none"><?= $flag ?></span>
              <span class="flex-1 text-right"><?= htmlspecialchars($label) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card space-y-6">
      <div class="rounded-2xl border border-brand-500/20 bg-brand-600/10 px-6 py-5 text-brand-900 shadow-inner shadow-brand-500/20 dark:border-brand-400/30 dark:bg-brand-600/20 dark:text-brand-50">
        <div class="flex items-center gap-3">
          <div class="grid h-12 w-12 place-items-center rounded-2xl bg-white/80 text-2xl shadow-sm dark:bg-slate-900/60">🔒</div>
          <div>
            <h1 class="text-lg font-semibold leading-tight"><?= __('Welcome back') ?></h1>
            <p class="text-xs text-brand-800/80 dark:text-brand-100/80"><?= __('Sign in to continue') ?></p>
          </div>
        </div>
        <?php if (!empty($_SESSION['flash'])): ?>
          <p class="mt-3 rounded-2xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
          </p>
        <?php endif; ?>
      </div>

      <form method="post" action="/login" class="space-y-5" novalidate>
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
            <button type="button" class="absolute inset-y-0 right-2 my-auto inline-flex h-9 items-center rounded-xl px-3 text-xs font-semibold text-brand-700 transition hover:bg-brand-50/70 dark:text-brand-100 dark:hover:bg-slate-800"
                    data-show="<?= __('Show') ?>" data-hide="<?= __('Hide') ?>"
                    onclick="const p=document.getElementById('login-password'); const isPassword=p.type==='password'; p.type=isPassword?'text':'password'; this.textContent=isPassword?this.dataset.hide:this.dataset.show;">
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
    </div>

    <p class="text-center text-[11px] text-slate-500 dark:text-slate-400">
      <?= __('By continuing you agree to the Terms & Privacy Policy.') ?>
    </p>
  </div>
</section>
