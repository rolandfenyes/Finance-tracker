<section class="max-w-3xl mx-auto">
  <div class="card space-y-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Data & Privacy') ?></h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400"><?= __('Control how your personal information is stored, exported, and erased from MyMoneyMap.') ?></p>
      </div>
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
      <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/50 dark:bg-rose-500/15 dark:text-rose-100"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-500/50 dark:bg-emerald-500/15 dark:text-emerald-100"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>

    <section class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/40">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Account overview') ?></h2>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?= __('A quick snapshot of the data MyMoneyMap currently stores for you.') ?></p>

      <dl class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Full name') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($account['full_name'] ?? '') ?></dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Email address') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($account['email'] ?? '') ?></dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Joined') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
            <?php if (!empty($account['created_at'])): ?>
              <?= htmlspecialchars(date('Y-m-d', strtotime($account['created_at']))) ?>
            <?php else: ?>
              <?= __('Unknown') ?>
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Tracked transactions') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white"><?= number_format((int)($counts['transactions'] ?? 0)) ?></dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Goals & plans') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white"><?= number_format((int)($counts['goals'] ?? 0)) ?> <?= __('goals') ?> · <?= number_format((int)($counts['scheduled'] ?? 0)) ?> <?= __('schedules') ?></dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Feedback submissions') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white"><?= number_format((int)($counts['feedback'] ?? 0)) ?></dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Last data export') ?></dt>
          <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
            <?php if (!empty($lastExport)): ?>
              <?= htmlspecialchars(date('Y-m-d H:i', strtotime($lastExport))) ?>
            <?php else: ?>
              <?= __('No exports yet') ?>
            <?php endif; ?>
          </dd>
        </div>
      </dl>
    </section>

    <section class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/40">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Download your data') ?></h2>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?= __('Generate a machine-readable JSON file containing every record associated with your account.') ?></p>
      <ul class="mt-3 list-disc space-y-1 pl-6 text-sm text-slate-500 dark:text-slate-400">
        <li><?= __('Includes transactions, goals, loans, scheduled payments, feedback, and device credentials.') ?></li>
        <li><?= __('The export is generated in real time and never stored on the server after download.') ?></li>
      </ul>
      <form method="post" action="/settings/privacy/export" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <button type="submit" class="btn btn-primary flex items-center gap-2">
          <i data-lucide="download" class="h-4 w-4"></i>
          <span><?= __('Download personal data (.json)') ?></span>
        </button>
        <p class="text-xs text-slate-500 dark:text-slate-400"><?= __('Keep this file secure—anyone with access can read your financial history.') ?></p>
      </form>
    </section>

    <section class="rounded-3xl border border-rose-200/70 bg-rose-50/80 p-5 shadow-sm dark:border-rose-500/40 dark:bg-rose-500/10">
      <h2 class="text-lg font-semibold text-rose-700 dark:text-rose-200"><?= __('Delete your account') ?></h2>
      <p class="mt-2 text-sm text-rose-700/90 dark:text-rose-200/90"><?= __('Erase your profile and all associated data from MyMoneyMap. This action is permanent and cannot be undone.') ?></p>
      <form method="post" action="/settings/privacy/delete" class="mt-4 space-y-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div class="field">
          <label class="label text-rose-800 dark:text-rose-100" for="confirm-email"><?= __('Type your email to confirm') ?></label>
          <input id="confirm-email" name="confirm_email" type="email" required class="input border-rose-200/80 focus:border-rose-400 focus:ring-rose-300 dark:border-rose-500/40 dark:bg-rose-500/10" placeholder="<?= htmlspecialchars($account['email'] ?? '') ?>" />
        </div>
        <div class="field">
          <label class="label text-rose-800 dark:text-rose-100" for="confirm-password"><?= __('Current password') ?></label>
          <input id="confirm-password" name="password" type="password" required autocomplete="current-password" class="input border-rose-200/80 focus:border-rose-400 focus:ring-rose-300 dark:border-rose-500/40 dark:bg-rose-500/10" placeholder="••••••••" />
          <p class="help text-rose-700/90 dark:text-rose-200/80"><?= __('For your security we require a password check before erasing data.') ?></p>
        </div>
        <button type="submit" class="btn btn-danger w-full sm:w-auto">
          <i data-lucide="shield-off" class="h-4 w-4"></i>
          <span><?= __('Permanently delete my account') ?></span>
        </button>
      </form>
    </section>

    <section class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/40">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Need more details?') ?></h2>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?= __('Review our privacy policy to understand how we process, retain, and secure your personal information.') ?></p>
      <a href="/privacy" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-accent">
        <i data-lucide="file-text" class="h-4 w-4"></i>
        <span><?= __('Read the privacy policy') ?></span>
      </a>
    </section>
  </div>
</section>
