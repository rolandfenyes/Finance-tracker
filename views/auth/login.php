<section class="grid place-items-center px-4">
  <div class="w-full max-w-md">
    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-glass overflow-hidden">
      <!-- Header / brand -->
      <div class="p-6 border-b bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl grid place-items-center bg-gray-900 text-white">🔒</div>
          <div>
            <h1 class="text-lg font-semibold leading-tight">Welcome back</h1>
            <p class="text-xs text-gray-500">Sign in to continue</p>
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
          <label class="label mb-1">Email</label>
          <div class="relative">
            <input
              name="email"
              type="email"
              class="input pl-10 w-full"
              placeholder="you@example.com"
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
            <label class="label">Password</label>
            <a href="/forgot" class="text-xs text-accent hover:underline">Forgot?</a>
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
                    onclick="const p=document.getElementById('login-password'); p.type=(p.type==='password'?'text':'password'); this.textContent=(p.type==='password'?'Show':'Hide');">
              Show
            </button>
          </div>
        </div>

        <!-- Extras -->
        <div class="flex items-center justify-between">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
            <span>Remember me</span>
          </label>
          <span class="text-xs text-gray-400">Secure by design</span>
        </div>

        <button class="btn btn-primary w-full">Sign in</button>

        <p class="text-sm text-gray-500 text-center">
          No account?
          <a class="text-accent hover:underline" href="/register">Create one</a>
        </p>
      </form>
    </div>

    <!-- Footer note -->
    <p class="mt-4 text-[11px] text-center text-gray-400">
      By continuing you agree to the Terms & Privacy Policy.
    </p>
  </div>
</section>
