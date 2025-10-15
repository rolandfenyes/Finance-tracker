<section class="mx-auto max-w-xl rounded-lg bg-white p-8 shadow dark:bg-slate-800">
  <?php if (($status ?? '') === 'success'): ?>
    <h1 class="text-2xl font-semibold text-emerald-600 dark:text-emerald-300">Email verified</h1>
    <p class="mt-4 text-slate-600 dark:text-slate-200">Thank you! Your email address has been confirmed. You can close this window or continue using MyMoneyMap.</p>
  <?php elseif (($status ?? '') === 'already'): ?>
    <h1 class="text-2xl font-semibold text-indigo-600 dark:text-indigo-300">Already verified</h1>
    <p class="mt-4 text-slate-600 dark:text-slate-200">This email address was already verified. You can continue using MyMoneyMap.</p>
  <?php elseif (($status ?? '') === 'missing'): ?>
    <h1 class="text-2xl font-semibold text-amber-600 dark:text-amber-300">Verification link missing</h1>
    <p class="mt-4 text-slate-600 dark:text-slate-200">We couldn&rsquo;t find a verification token in your request. Please use the link from your email.</p>
  <?php else: ?>
    <h1 class="text-2xl font-semibold text-rose-600 dark:text-rose-300">Verification failed</h1>
    <p class="mt-4 text-slate-600 dark:text-slate-200">The verification link is invalid or has expired. Please request a new verification email.</p>
  <?php endif; ?>
  <div class="mt-6">
    <a class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white shadow hover:bg-indigo-500" href="/">Return to MyMoneyMap</a>
  </div>
</section>
