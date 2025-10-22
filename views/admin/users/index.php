<?php
/** @var array $users */
/** @var array $filters */
/** @var int $page */
/** @var int $pages */
/** @var array $summary */
/** @var int $total */

$statusOptions = [
    'all' => __('All statuses'),
    'active' => __('Active'),
    'suspended' => __('Suspended'),
    'unverified' => __('Unverified'),
];

$roleOptions = ['all' => __('All roles')];
foreach (ADMIN_ALLOWED_ROLES as $role) {
    $roleOptions[$role] = ucfirst($role);
}

$sortOptions = [
    'recent' => __('Newest first'),
    'oldest' => __('Oldest first'),
    'last-login' => __('Last login'),
];

$buildQuery = function (array $overrides = []) use ($filters): string {
    $query = array_merge($filters, $overrides);
    $clean = [];
    foreach ($query as $key => $value) {
        if ($key === 'page') {
            $clean[$key] = max(1, (int)$value);
            continue;
        }

        if ($value === '' || $value === 'all') {
            continue;
        }

        $clean[$key] = $value;
    }

    return http_build_query($clean);
};

?>

<div class="space-y-8 pb-16">
  <header class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
    <div class="space-y-3">
      <span class="chip inline-flex items-center gap-2 bg-white/70 text-brand-deep/80 dark:bg-slate-900/70 dark:text-emerald-200">
        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/20 text-brand-700 dark:bg-emerald-500/10 dark:text-emerald-200">
          <i data-lucide="users" class="h-3.5 w-3.5"></i>
        </span>
        <?= htmlspecialchars(__('User directory')) ?>
      </span>
      <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
        <?= htmlspecialchars(__('Accounts & activity overview')) ?>
      </h1>
      <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
        <?= htmlspecialchars(__('Search, filter, and export every customer account. Review verification status, admin roles, and recent product usage at a glance.')) ?>
      </p>
    </div>
    <div class="grid gap-3 text-sm">
      <div class="rounded-3xl border border-white/70 bg-white/70 px-5 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
        <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Total accounts')) ?></p>
        <p class="text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format($summary['total'] ?? 0) ?></p>
      </div>
      <div class="rounded-3xl border border-white/70 bg-white/70 px-5 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
        <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Pending verification')) ?></p>
        <p class="text-lg font-semibold text-amber-600 dark:text-amber-300"><?= number_format($summary['unverified'] ?? 0) ?></p>
      </div>
    </div>
  </header>

  <section class="card">
    <form method="get" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <label class="grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Search by email')) ?></span>
        <input
          type="search"
          name="q"
          value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES) ?>"
          class="input"
          placeholder="<?= htmlspecialchars(__('user@example.com')) ?>"
        />
      </label>

      <label class="grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Status')) ?></span>
        <select name="status" class="input">
          <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= ($filters['status'] ?? 'all') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Admin role')) ?></span>
        <select name="role" class="input">
          <?php foreach ($roleOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= ($filters['role'] ?? 'all') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Sort')) ?></span>
        <select name="sort" class="input">
          <?php foreach ($sortOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= ($filters['sort'] ?? 'recent') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="md:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-3 pt-2">
        <button class="btn btn-primary">
          <i data-lucide="filter" class="mr-2 h-4 w-4"></i>
          <?= htmlspecialchars(__('Apply filters')) ?>
        </button>
        <a href="/admin/users" class="btn btn-muted">
          <?= htmlspecialchars(__('Reset')) ?>
        </a>
        <a href="/admin/users/export" class="btn btn-secondary ml-auto">
          <i data-lucide="download" class="mr-2 h-4 w-4"></i>
          <?= htmlspecialchars(__('Export CSV')) ?>
        </a>
      </div>
    </form>
  </section>

  <section class="space-y-4">
    <?php if (empty($users)): ?>
      <div class="card text-center text-sm text-slate-500 dark:text-slate-400">
        <?= htmlspecialchars(__('No users matched your filters. Try broadening the search.')) ?>
      </div>
    <?php else: ?>
      <div class="grid gap-4">
        <?php foreach ($users as $user): ?>
          <?php
            $isVerified = !empty($user['email_verified_at']);
            $isSuspended = !empty($user['suspended_at']);
            $role = $user['admin_role'] ?? null;
            $lastLogin = $user['last_login_at'] ?? null;
            $lastActivity = $user['last_transaction_at'] ?? null;
          ?>
          <article class="card flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
            <div class="space-y-3">
              <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                  <a href="/admin/users/<?= (int)$user['id'] ?>" class="hover:underline">
                    <?= htmlspecialchars($user['email'] ?? '') ?>
                  </a>
                </h2>
                <?php if ($user['full_name']): ?>
                  <span class="text-sm text-slate-500 dark:text-slate-400">· <?= htmlspecialchars($user['full_name']) ?></span>
                <?php endif; ?>
              </div>
              <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider">
                <span class="chip <?= $isVerified ? 'bg-emerald-100/80 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200' : 'bg-amber-100/80 text-amber-700 dark:bg-amber-500/10 dark:text-amber-200' ?>">
                  <?= $isVerified ? htmlspecialchars(__('Verified')) : htmlspecialchars(__('Unverified')) ?>
                </span>
                <?php if ($isSuspended): ?>
                  <span class="chip bg-rose-100/80 text-rose-700 dark:bg-rose-500/10 dark:text-rose-200">
                    <?= htmlspecialchars(__('Suspended')) ?>
                  </span>
                <?php endif; ?>
                <?php if ($role): ?>
                  <span class="chip bg-brand-500/10 text-brand-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <?= htmlspecialchars(ucfirst($role)) ?>
                  </span>
                <?php endif; ?>
                <span class="chip bg-slate-200/60 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  <?= htmlspecialchars(__('Created')) ?>
                  ·
                  <?= htmlspecialchars(admin_relative_time($user['created_at'] ?? null)) ?>
                </span>
              </div>
              <?php if (!empty($user['admin_notes'])): ?>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                  <span class="font-medium text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Admin notes')) ?>:</span>
                  <?= nl2br(htmlspecialchars($user['admin_notes'])) ?>
                </p>
              <?php endif; ?>
            </div>
            <div class="grid gap-3 text-sm text-slate-600 dark:text-slate-300 md:text-right">
              <div>
                <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Last login')) ?></p>
                <p class="font-medium text-slate-900 dark:text-white">
                  <?= htmlspecialchars($lastLogin ? admin_relative_time($lastLogin) : __('Never')) ?>
                </p>
              </div>
              <div>
                <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Recent activity')) ?></p>
                <p class="font-medium text-slate-900 dark:text-white">
                  <?= htmlspecialchars($lastActivity ? admin_relative_time($lastActivity) : __('No transactions yet')) ?>
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-4 md:justify-end">
                <span><?= number_format((int)($user['transactions'] ?? 0)) ?> <?= htmlspecialchars(__('transactions')) ?></span>
                <span><?= number_format((int)($user['goals_count'] ?? 0)) ?> <?= htmlspecialchars(__('goals')) ?></span>
                <span><?= number_format((int)($user['scheduled_count'] ?? 0)) ?> <?= htmlspecialchars(__('schedules')) ?></span>
                <?php if (!empty($user['feedback_open'])): ?>
                  <span class="text-amber-600 dark:text-amber-300"><?= number_format((int)$user['feedback_open']) ?> <?= htmlspecialchars(__('open feedback')) ?></span>
                <?php endif; ?>
              </div>
              <div>
                <a href="/admin/users/<?= (int)$user['id'] ?>" class="btn btn-secondary w-full md:w-auto">
                  <i data-lucide="arrow-up-right" class="mr-2 h-4 w-4"></i>
                  <?= htmlspecialchars(__('View details')) ?>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="flex items-center justify-between pt-4" aria-label="<?= htmlspecialchars(__('Pagination')) ?>">
          <?php $prevPage = max(1, $page - 1); ?>
          <?php $nextPage = min($pages, $page + 1); ?>
          <a
            class="btn btn-muted <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>"
            href="?<?= htmlspecialchars($buildQuery(['page' => $prevPage]), ENT_QUOTES) ?>"
          >
            <i data-lucide="arrow-left" class="mr-2 h-4 w-4"></i>
            <?= htmlspecialchars(__('Previous')) ?>
          </a>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= htmlspecialchars(sprintf(__('Page %d of %d'), $page, $pages)) ?>
          </p>
          <a
            class="btn btn-muted <?= $page >= $pages ? 'opacity-50 pointer-events-none' : '' ?>"
            href="?<?= htmlspecialchars($buildQuery(['page' => $nextPage]), ENT_QUOTES) ?>"
          >
            <?= htmlspecialchars(__('Next')) ?>
            <i data-lucide="arrow-right" class="ml-2 h-4 w-4"></i>
          </a>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
