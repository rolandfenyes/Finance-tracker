<?php
$users = $users ?? [];
$filters = $filters ?? [];
$roleOptions = $roleOptions ?? [];
$statusOptions = $statusOptions ?? [];
$verifiedOptions = $verifiedOptions ?? [];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0];
$currentUrl = $currentUrl ?? '/admin/users';
?>

<div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
      <i data-lucide="shield-check" class="h-4 w-4"></i>
      <?= __('Administration') ?>
    </div>
    <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="card-title text-3xl font-semibold text-slate-900 dark:text-white">
          <?= __('User management') ?>
        </h1>
        <p class="card-subtle mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Search, filter, and take action on accounts across the platform.') ?>
        </p>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash']) || !empty($_SESSION['flash_success'])): ?>
      <div class="mt-6 space-y-3">
        <?php if (!empty($_SESSION['flash'])): ?>
          <div class="rounded-3xl border border-rose-300/70 bg-rose-50/80 p-4 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-100">
            <?= htmlspecialchars($_SESSION['flash']) ?>
          </div>
          <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
          <div class="rounded-3xl border border-emerald-300/70 bg-emerald-50/80 p-4 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
          </div>
          <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="get" action="/admin/users" class="mt-6 grid gap-4 rounded-3xl border border-white/60 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40 sm:grid-cols-2 lg:grid-cols-5">
      <div class="sm:col-span-2 lg:col-span-2">
        <label for="filter-search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Search') ?>
        </label>
        <input
          id="filter-search"
          name="q"
          type="text"
          value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
          placeholder="<?= __('Search name or email') ?>"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        />
      </div>
      <div>
        <label for="filter-role" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Role') ?>
        </label>
        <select
          id="filter-role"
          name="role"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        >
          <option value=""><?= __('All') ?></option>
          <?php foreach ($roleOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['role'] ?? '') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Status') ?>
        </label>
        <select
          id="filter-status"
          name="status"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        >
          <option value=""><?= __('All') ?></option>
          <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="filter-verified" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Verified') ?>
        </label>
        <select
          id="filter-verified"
          name="verified"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        >
          <option value=""><?= __('All') ?></option>
          <?php foreach ($verifiedOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['verified'] ?? '') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-1">
        <button type="submit" class="btn btn-primary w-full justify-center">
          <?= __('Apply') ?>
        </button>
        <a class="btn btn-muted justify-center" href="/admin/users">
          <?= __('Clear') ?>
        </a>
      </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-3xl border border-white/60 bg-white/60 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
          <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Name') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Email') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Role') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Status') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Verified') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Last login') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold text-right"><?= __('Actions') ?></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php if ($users): ?>
              <?php foreach ($users as $user):
                $userId = (int)($user['id'] ?? 0);
                $displayName = trim($user['full_name'] ?? '') ?: ($user['email'] ?? __('Unknown'));
                $email = $user['email'] ?? '';
                $role = normalize_user_role($user['role'] ?? null);
                $status = normalize_user_status($user['status'] ?? null);
                $roleBadgeClass = match ($role) {
                  ROLE_ADMIN => 'border-brand-500/70 bg-brand-500/10 text-brand-700 dark:border-brand-400/50 dark:bg-brand-500/15 dark:text-brand-100',
                  ROLE_PREMIUM => 'border-amber-400/70 bg-amber-200/30 text-amber-800 dark:border-amber-300/60 dark:bg-amber-300/15 dark:text-amber-200',
                  default => 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
                };
                $statusBadgeClass = $status === USER_STATUS_ACTIVE
                  ? 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100'
                  : 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100';
                $verified = !empty($user['email_verified_at']);
                $lastLoginAt = $user['last_login_at'] ?? null;
                $lastLoginLabel = $lastLoginAt ? date('Y-m-d H:i', strtotime((string)$lastLoginAt)) : __('Never');
                $statusLabel = $status === USER_STATUS_ACTIVE ? __('Active') : __('Inactive');
                $roleLabel = match ($role) {
                  ROLE_ADMIN => __('Administrator'),
                  ROLE_PREMIUM => __('Premium user'),
                  default => __('Free user'),
                };
                $manageUrl = '/admin/users/manage?id=' . $userId;
                if ($currentUrl !== '') {
                  $manageUrl .= '&return=' . rawurlencode($currentUrl);
                }
              ?>
                <tr class="transition hover:bg-brand-50/40 dark:hover:bg-slate-800/30">
                  <td class="px-4 py-4 align-middle text-sm font-medium text-slate-900 dark:text-slate-100">
                    <div class="flex flex-col">
                      <span><?= htmlspecialchars($displayName) ?></span>
                      <span class="text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Joined :date', ['date' => $user['created_at'] ? date('Y-m-d H:i', strtotime((string)$user['created_at'])) : __('Unknown')]) ?>
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <span class="break-all font-mono text-xs sm:text-sm"><?= htmlspecialchars($email) ?></span>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= $roleBadgeClass ?>">
                      <i data-lucide="<?= $role === ROLE_ADMIN ? 'shield-check' : ($role === ROLE_PREMIUM ? 'crown' : 'user') ?>" class="h-3.5 w-3.5"></i>
                      <?= htmlspecialchars($roleLabel) ?>
                    </span>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= $statusBadgeClass ?>">
                      <i data-lucide="<?= $status === USER_STATUS_ACTIVE ? 'check-circle' : 'slash' ?>" class="h-3.5 w-3.5"></i>
                      <?= htmlspecialchars($statusLabel) ?>
                    </span>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <?php if ($verified): ?>
                      <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/60 bg-emerald-100/50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/20 dark:text-emerald-100">
                        <i data-lucide="badge-check" class="h-3.5 w-3.5"></i>
                        <?= __('Verified') ?>
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-2 rounded-full border border-amber-300/60 bg-amber-100/50 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/20 dark:text-amber-100">
                        <i data-lucide="hourglass" class="h-3.5 w-3.5"></i>
                        <?= __('Unverified') ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <div class="flex flex-col items-start gap-1">
                      <span class="font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($lastLoginLabel) ?></span>
                      <?php if (!empty($user['last_login_ip'])): ?>
                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= __('IP address') ?>: <?= htmlspecialchars($user['last_login_ip']) ?></span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="px-4 py-4 align-middle text-right text-sm">
                    <a class="btn btn-primary inline-flex items-center justify-center gap-2" href="<?= htmlspecialchars($manageUrl) ?>">
                      <i data-lucide="settings" class="h-4 w-4"></i>
                      <span><?= __('Manage') ?></span>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                  <?= __('No user accounts found yet.') ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
      <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="text-sm text-slate-600 dark:text-slate-300">
          <?= __('Showing :count user(s)', ['count' => number_format((int)$pagination['total'])]) ?>
        </div>
        <div class="flex items-center gap-2">
          <?php if (!empty($pagination['prev'])): ?>
            <a class="btn btn-muted" href="<?= htmlspecialchars($pagination['prev']) ?>">
              <?= __('Previous') ?>
            </a>
          <?php endif; ?>
          <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            <?= __('Page :current of :total', ['current' => (int)$pagination['page'], 'total' => (int)$pagination['pages']]) ?>
          </span>
          <?php if (!empty($pagination['next'])): ?>
            <a class="btn btn-muted" href="<?= htmlspecialchars($pagination['next']) ?>">
              <?= __('Next') ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>

  </div>
