<section class="min-h-[80vh] grid place-items-center px-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-glass p-6">
    <h1 class="text-lg font-semibold">Create your account</h1>
    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-2 text-sm text-red-600"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <form method="post" action="/register" class="mt-4 space-y-4" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div>
        <label class="label">Full name</label>
        <input class="input w-full" name="full_name" placeholder="Jane Doe" autocomplete="name" required>
      </div>
      <div>
        <label class="label">Date of birth</label>
        <input class="input w-full" name="date_of_birth" type="date">
      </div>
      <div>
        <label class="label">Email</label>
        <input class="input w-full" name="email" type="email" autocomplete="email" required>
      </div>
      <div>
        <label class="label">Password (min 8)</label>
        <input class="input w-full" name="password" type="password" minlength="8" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary w-full">Continue</button>
    </form>
    <p class="text-xs text-gray-500 mt-3">Already registered? <a class="text-accent" href="/login">Sign in</a></p>
  </div>
</section>
