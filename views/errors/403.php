<div class="mx-auto flex min-h-[60vh] w-full max-w-3xl flex-col items-center justify-center gap-6 text-center">
  <div class="space-y-2">
    <span class="inline-flex items-center gap-2 rounded-full bg-rose-100/80 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-rose-700 dark:bg-rose-500/10 dark:text-rose-200">
      <i data-lucide="shield-alert" class="h-4 w-4"></i>
      <?= htmlspecialchars(__('Access denied')) ?>
    </span>
    <h1 class="text-3xl font-semibold text-slate-900 dark:text-white">
      <?= htmlspecialchars($message ?? __('You do not have permission to view this page.')) ?>
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
      <?= htmlspecialchars(__('Please contact an administrator if you believe this is an error.')) ?>
    </p>
  </div>
  <a href="/" class="btn btn-primary">
    <i data-lucide="home" class="mr-2 h-4 w-4"></i>
    <?= htmlspecialchars(__('Return home')) ?>
  </a>
</div>
