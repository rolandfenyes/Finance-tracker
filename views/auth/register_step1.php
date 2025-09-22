<section class="grid min-h-[70vh] place-items-center px-4 py-10">
  <div class="w-full max-w-md">
    <div class="card space-y-6">
      <div>
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Create your account') ?></h1>
        <?php if (!empty($_SESSION['flash'])): ?>
          <p class="mt-2 rounded-2xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200">
            <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
          </p>
        <?php endif; ?>
      </div>
      <form method="post" action="/register" class="space-y-5" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div class="field">
          <label class="label"><?= __('Full name') ?></label>
          <input class="input" name="full_name" placeholder="<?= __('Jane Doe') ?>" autocomplete="name" required />
        </div>
        <div class="field">
          <label class="label"><?= __('Date of birth') ?></label>
          <input class="input" name="date_of_birth" type="date" />
        </div>
        <div class="field">
          <label class="label"><?= __('Email') ?></label>
          <input class="input" name="email" type="email" autocomplete="email" required />
        </div>
        <div class="field">
          <label class="label"><?= __('Password (min 8)') ?></label>
          <input class="input" name="password" type="password" minlength="8" autocomplete="new-password" required />
        </div>
        <button class="btn btn-primary w-full text-base"><?= __('Continue') ?></button>
      </form>
      <p class="text-center text-xs text-slate-500 dark:text-slate-400">
        <?= __('Already registered?') ?> <a class="font-semibold text-brand-600 hover:underline dark:text-brand-200" href="/login"><?= __('Sign in') ?></a>
      </p>
    </div>
  </div>
</section>
