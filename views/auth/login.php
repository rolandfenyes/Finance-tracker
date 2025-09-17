<section class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-glass p-6">
    <h1 class="text-xl font-semibold mb-4">Welcome back</h1>
    <?php if (!empty($_SESSION['flash'])): ?><p class="text-red-600 mb-3"><?=$_SESSION['flash']; unset($_SESSION['flash']);?></p><?php endif; ?>
    <form method="post" action="/login" class="space-y-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input name="email" type="email" class="w-full rounded-xl border-gray-300" required />
      </div>
      <div>
        <label class="block text-sm mb-1">Password</label>
        <input name="password" type="password" class="w-full rounded-xl border-gray-300" required />
      </div>
      <button class="w-full bg-gray-900 text-white rounded-xl py-2.5">Login</button>
    </form>
    <p class="text-sm text-gray-500 mt-3">No account? <a class="text-accent" href="/register">Register</a></p>
  </div>
</section>