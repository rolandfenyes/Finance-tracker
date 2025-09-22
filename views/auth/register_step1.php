<section class="min-h-[80vh] grid place-items-center px-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-glass p-6">
    <h1 class="text-lg font-semibold"><?= __('Create your account') ?></h1>
    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-2 text-sm text-red-600"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <form method="post" action="/register" class="mt-4 space-y-4" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div>
        <label class="label"><?= __('Full name') ?></label>
        <input class="input w-full" name="full_name" placeholder="<?= __('Jane Doe') ?>" autocomplete="name" required>
      </div>
      <div>
        <label class="label"><?= __('Date of birth') ?></label>
        <input class="input w-full" name="date_of_birth" type="date">
      </div>
      <div>
        <label class="label"><?= __('Email') ?></label>
        <input class="input w-full" name="email" type="email" autocomplete="email" required>
      </div>
      <div>
        <label class="label"><?= __('Password (min 8)') ?></label>
        <input class="input w-full" name="password" type="password" minlength="8" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary w-full"><?= __('Continue') ?></button>
    </form>
    <p class="text-xs text-gray-500 mt-3"><?= __('Already registered?') ?> <a class="text-accent" href="/login"><?= __('Sign in') ?></a></p>
  </div>
</section>
