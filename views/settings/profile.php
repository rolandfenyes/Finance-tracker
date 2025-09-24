<section class="max-w-2xl mx-auto">
  <div class="card">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-xl font-semibold">Profile</h1>
      <div class="flex items-center gap-2">
        <a href="/settings" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to Settings') ?></span>
        </a>
        <a href="/more" class="inline-flex sm:hidden items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to More') ?></span>
        </a>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-3 text-red-600 text-sm"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <p class="mt-3 text-brand-600 text-sm"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/profile" class="grid gap-4 mt-5">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <div class="field">
        <label class="label">Email</label>
        <input class="input bg-gray-50" value="<?= htmlspecialchars($user['email']) ?>" disabled />
        <p class="help">Email changes are disabled here.</p>
      </div>

      <div class="field">
        <label class="label">Full name</label>
        <input name="full_name" class="input" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" />
      </div>

      <div class="field">
        <label class="label">Date of birth</label>
        <input name="date_of_birth" type="date" class="input" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>" />
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div class="field">
          <label class="label">New password</label>
          <input name="password" type="password" class="input" placeholder="Leave blank to keep current" />
        </div>
        <div class="field">
          <label class="label">Confirm password</label>
          <input name="password2" type="password" class="input" placeholder="Confirm new password" />
        </div>
      </div>

      <div class="flex justify-end">
        <button class="btn btn-primary">Save changes</button>
      </div>
    </form>
  </div>
</section>
