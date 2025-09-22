<section class="grid min-h-[70vh] place-items-center px-4 py-10">
  <div class="w-full max-w-md space-y-6">
    <div class="card space-y-6">
      <div class="rounded-2xl border border-brand-500/20 bg-brand-600/10 px-6 py-5 text-brand-900 shadow-inner shadow-brand-500/20 dark:border-brand-400/30 dark:bg-brand-600/20 dark:text-brand-50">
        <div class="flex items-center gap-3">
          <div class="grid h-12 w-12 place-items-center rounded-2xl bg-white/80 text-2xl shadow-sm dark:bg-slate-900/60">✨</div>
          <div>
            <h1 class="text-lg font-semibold leading-tight"><?= __('Create your account') ?></h1>
            <p class="text-xs text-brand-800/80 dark:text-brand-100/80"><?= __('Start tracking your money smarter') ?></p>
          </div>
        </div>
        <?php if (!empty($_SESSION['flash'])): ?>
          <p class="mt-3 rounded-2xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
          </p>
        <?php endif; ?>
      </div>

      <form method="post" action="/register" class="space-y-5" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="field">
          <label class="label mb-1"><?= __('Full name') ?></label>
          <div class="relative">
            <input
              name="full_name"
              class="input pl-11"
              placeholder="<?= __('Jane Doe') ?>"
              autocomplete="name"
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">👤</span>
          </div>
        </div>

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
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">✉️</span>
          </div>
        </div>

        <div class="field">
          <label class="label mb-1"><?= __('Password') ?></label>
          <div class="relative">
            <input
              id="reg-pass"
              name="password"
              type="password"
              class="input pl-11 pr-12"
              placeholder="<?= __('Min 8 characters') ?>"
              minlength="8"
              autocomplete="new-password"
              required
              oninput="document.getElementById('pw-hint').textContent = this.value.length<8 ? '<?= __('At least 8 characters') ?>' : '<?= __('Looks good!') ?>'"
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">🔑</span>
            <button type="button"
                    class="absolute inset-y-0 right-2 my-auto inline-flex h-9 items-center rounded-xl px-3 text-xs font-semibold text-brand-700 transition hover:bg-brand-50/70 dark:text-brand-100 dark:hover:bg-slate-800"
                    data-show="<?= __('Show') ?>" data-hide="<?= __('Hide') ?>"
                    onclick="const p=document.getElementById('reg-pass'); const isPassword=p.type==='password'; p.type=isPassword?'text':'password'; this.textContent=isPassword?this.dataset.hide:this.dataset.show;">
              <?= __('Show') ?>
            </button>
          </div>
          <p id="pw-hint" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"><?= __('At least 8 characters') ?></p>
        </div>

        <div class="field">
          <label class="label mb-1"><?= __('Confirm password') ?></label>
          <div class="relative">
            <input
              id="reg-pass2"
              type="password"
              class="input pl-11"
              placeholder="<?= __('Re-enter password') ?>"
              autocomplete="new-password"
              oninput="document.getElementById('pw-match').textContent = (this.value && this.value!==document.getElementById('reg-pass').value) ? '<?= __('Passwords do not match') ?>' : ''"
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">✅</span>
          </div>
          <p id="pw-match" class="mt-1 text-[11px] text-rose-500"></p>
        </div>

        <div class="flex items-center justify-between text-sm text-slate-600 dark:text-slate-300">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="agree" value="1" required class="h-4 w-4 rounded border-brand-200 text-brand-600 focus:ring-brand-400" />
            <span><?= __('I agree to the') ?> <a href="/terms" class="font-semibold text-brand-600 hover:underline dark:text-brand-200"><?= __('Terms') ?></a> & <a href="/privacy" class="font-semibold text-brand-600 hover:underline dark:text-brand-200"><?= __('Privacy') ?></a></span>
          </label>
          <span class="text-xs text-slate-400 dark:text-slate-500"><?= __('It’s free') ?></span>
        </div>

        <button class="btn btn-primary w-full text-base"><?= __('Create account') ?></button>

        <p class="text-center text-sm text-slate-600 dark:text-slate-300">
          <?= __('Already have an account?') ?>
          <a class="font-semibold text-brand-600 hover:underline dark:text-brand-200" href="/login"><?= __('Sign in') ?></a>
        </p>
      </form>
    </div>

    <p class="text-center text-[11px] text-slate-500 dark:text-slate-400">
      <?= __('We never share your email. You can delete your account anytime.') ?>
    </p>
  </div>
</section>
