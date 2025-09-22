<section class="grid place-items-center px-4">
  <div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-glass overflow-hidden">
      <!-- Header -->
      <div class="p-6 border-b bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl grid place-items-center bg-gray-900 text-white">✨</div>
          <div>
            <h1 class="text-lg font-semibold leading-tight"><?= __('Create your account') ?></h1>
            <p class="text-xs text-gray-500"><?= __('Start tracking your money smarter') ?></p>
          </div>
        </div>
        <?php if (!empty($_SESSION['flash'])): ?>
          <p class="mt-3 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Form -->
      <form method="post" action="/register" class="p-6 space-y-4" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div>
          <label class="label mb-1"><?= __('Full name') ?></label>
          <div class="relative">
            <input
              name="full_name"
              class="input pl-10 w-full"
              placeholder="<?= __('Jane Doe') ?>"
              autocomplete="name"
            />
            <span class="absolute inset-y-0 left-3 grid place-items-center text-gray-400 pointer-events-none">👤</span>
          </div>
        </div>

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
            />
            <span class="absolute inset-y-0 left-3 grid place-items-center text-gray-400 pointer-events-none">✉️</span>
          </div>
        </div>

        <div>
          <label class="label mb-1"><?= __('Password') ?></label>
          <div class="relative">
            <input
              id="reg-pass"
              name="password"
              type="password"
              class="input pl-10 pr-10 w-full"
              placeholder="<?= __('Min 8 characters') ?>"
              minlength="8"
              autocomplete="new-password"
              required
              oninput="document.getElementById('pw-hint').textContent = this.value.length<8 ? '<?= __('At least 8 characters') ?>' : '<?= __('Looks good!') ?>'"
            />
            <span class="absolute inset-y-0 left-3 grid place-items-center text-gray-400 pointer-events-none">🔑</span>
            <button type="button"
                    class="absolute inset-y-0 right-2 my-auto h-9 px-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100"
                    data-show="<?= __('Show') ?>" data-hide="<?= __('Hide') ?>"
                    onclick="const p=document.getElementById('reg-pass'); const isPassword=p.type==='password'; p.type=isPassword?'text':'password'; this.textContent=isPassword?this.dataset.hide:this.dataset.show;">
              <?= __('Show') ?>
            </button>
          </div>
          <p id="pw-hint" class="mt-1 text-[11px] text-gray-500"><?= __('At least 8 characters') ?></p>
        </div>

        <!-- Optional confirm (safe to keep; backend can ignore if not used) -->
        <div>
          <label class="label mb-1"><?= __('Confirm password') ?></label>
          <div class="relative">
            <input
              id="reg-pass2"
              type="password"
              class="input pl-10 w-full"
              placeholder="<?= __('Re-enter password') ?>"
              autocomplete="new-password"
              oninput="document.getElementById('pw-match').textContent = (this.value && this.value!==document.getElementById('reg-pass').value) ? '<?= __('Passwords do not match') ?>' : ''"
            />
            <span class="absolute inset-y-0 left-3 grid place-items-center text-gray-400 pointer-events-none">✅</span>
          </div>
          <p id="pw-match" class="mt-1 text-[11px] text-red-600"></p>
        </div>

        <div class="flex items-center justify-between">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="agree" value="1" required class="rounded border-gray-300">
            <span><?= __('I agree to the') ?> <a href="/terms" class="text-accent hover:underline"><?= __('Terms') ?></a> & <a href="/privacy" class="text-accent hover:underline"><?= __('Privacy') ?></a></span>
          </label>
          <span class="text-xs text-gray-400"><?= __('It’s free') ?></span>
        </div>

        <button class="btn btn-primary w-full"><?= __('Create account') ?></button>

        <p class="text-sm text-gray-500 text-center">
          <?= __('Already have an account?') ?>
          <a class="text-accent hover:underline" href="/login"><?= __('Sign in') ?></a>
        </p>
      </form>
    </div>

    <p class="mt-4 text-[11px] text-center text-gray-400">
      <?= __('We never share your email. You can delete your account anytime.') ?>
    </p>
  </div>
</section>
