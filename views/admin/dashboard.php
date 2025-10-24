<?php
$stats = $stats ?? [];
$recentUsers = $recentUsers ?? [];
$roleOptions = $roleOptions ?? [];
$pendingMigrations = $pendingMigrations ?? null;
$totalMigrations = $totalMigrations ?? null;
$statDefaults = [
    'users' => 0,
    'transactions' => 0,
    'goals' => 0,
    'loans' => 0,
];
$stats = array_merge($statDefaults, $stats);
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
          <?= __('Administrator dashboard') ?>
        </h1>
        <p class="card-subtle mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Monitor key system metrics, manage privileged roles, and keep your workspace healthy.') ?>
        </p>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <form action="/admin/migrations" method="post" class="sm:flex-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <button class="btn btn-primary w-full justify-center gap-2">
            <i data-lucide="database" class="h-4 w-4"></i>
            <?= __('Run database migrations') ?>
          </button>
        </form>
      </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <?php
        $statCards = [
          [
            'label' => __('Registered users'),
            'value' => number_format((int)$stats['users']),
            'icon' => 'users',
          ],
          [
            'label' => __('Transactions recorded'),
            'value' => number_format((int)$stats['transactions']),
            'icon' => 'receipt',
          ],
          [
            'label' => __('Active goals'),
            'value' => number_format((int)$stats['goals']),
            'icon' => 'goal',
          ],
          [
            'label' => __('Loans tracked'),
            'value' => number_format((int)$stats['loans']),
            'icon' => 'landmark',
          ],
        ];
      ?>
      <?php foreach ($statCards as $card): ?>
        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= htmlspecialchars($card['label']) ?></span>
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-brand-500/15 text-brand-700 dark:bg-brand-500/25 dark:text-brand-200">
              <i data-lucide="<?= htmlspecialchars($card['icon']) ?>" class="h-4 w-4"></i>
            </span>
          </div>
          <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-white">
            <?= htmlspecialchars($card['value']) ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>

    <?php
      $pendingNote = null;
      if ($pendingMigrations !== null) {
          $formattedPending = number_format((int)$pendingMigrations);
          $totalText = $totalMigrations !== null ? number_format((int)$totalMigrations) : null;
          $pendingNote = $pendingMigrations > 0
              ? __(':count migration(s) pending.', ['count' => $formattedPending])
              : __('All migrations are applied.');
          if ($totalText !== null) {
              $pendingNote .= ' ' . __('Total files: :count', ['count' => $totalText]);
          }
      }
    ?>
    <?php if ($pendingNote): ?>
      <div class="mt-4 rounded-3xl border border-amber-200/70 bg-amber-50/80 p-4 text-sm text-amber-800 dark:border-amber-400/40 dark:bg-amber-500/15 dark:text-amber-100">
        <?= htmlspecialchars($pendingNote) ?>
      </div>
    <?php endif; ?>

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
  </section>

  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
      <i data-lucide="users" class="h-4 w-4"></i>
      <?= __('Recent accounts') ?>
    </div>
    <h2 class="card-title mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
      <?= __('Manage roles & access') ?>
    </h2>
    <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
      <?= __('Promote trusted teammates to administrators or return access to standard user privileges.') ?>
    </p>

    <div class="mt-6 overflow-hidden rounded-3xl border border-white/60 bg-white/60 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
          <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Name') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Email') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Role') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold text-right"><?= __('Actions') ?></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php if ($recentUsers): ?>
              <?php foreach ($recentUsers as $user):
                $displayName = trim($user['full_name'] ?? '') ?: ($user['email'] ?? __('Unknown'));
                $email = $user['email'] ?? '';
                $role = $user['role'] ?? 'user';
                $createdAt = $user['created_at'] ?? null;
                $createdLabel = $createdAt ? date('Y-m-d H:i', strtotime((string)$createdAt)) : __('Unknown');
              ?>
                <tr class="transition hover:bg-brand-50/40 dark:hover:bg-slate-800/30">
                  <td class="px-4 py-4 align-middle text-sm font-medium text-slate-900 dark:text-slate-100">
                    <div class="flex flex-col">
                      <span><?= htmlspecialchars($displayName) ?></span>
                      <span class="text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Joined :date', ['date' => $createdLabel]) ?>
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <span class="break-all font-mono text-xs sm:text-sm"><?= htmlspecialchars($email) ?></span>
                  </td>
                  <td class="px-4 py-4 align-middle text-sm text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= $role === 'admin' ? 'border-brand-500/70 bg-brand-500/10 text-brand-700 dark:border-brand-400/50 dark:bg-brand-500/15 dark:text-brand-100' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200' ?>">
                      <i data-lucide="<?= $role === 'admin' ? 'shield' : 'user' ?>" class="h-3.5 w-3.5"></i>
                      <?= htmlspecialchars($roleOptions[$role] ?? ucfirst($role)) ?>
                    </span>
                  </td>
                  <td class="px-4 py-4 align-middle text-right text-sm">
                    <form action="/admin/users/role" method="post" class="inline-flex items-center justify-end gap-2">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>" />
                      <label class="sr-only" for="role-<?= (int)$user['id'] ?>"><?= __('Select role') ?></label>
                      <select
                        id="role-<?= (int)$user['id'] ?>"
                        name="role"
                        class="rounded-2xl border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-600 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
                      >
                        <?php foreach ($roleOptions as $value => $label): ?>
                          <option value="<?= htmlspecialchars($value) ?>" <?= $role === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-muted whitespace-nowrap">
                        <?= __('Update') ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                  <?= __('No user accounts found yet.') ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
