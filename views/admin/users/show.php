<?php
/** @var array $user */
/** @var array $currencies */
/** @var array $transactionsSummary */
/** @var array $recentTransactions */
/** @var array $goals */
/** @var array $loans */
/** @var array $scheduled */
/** @var array $feedback */
/** @var array|null $emergency */
/** @var array $auditLog */
/** @var array $availableRoles */
/** @var array $availableLocales */

$userId = (int)($user['id'] ?? 0);
$isVerified = !empty($user['email_verified_at']);
$isSuspended = !empty($user['suspended_at']);
$role = $user['admin_role'] ?? '';
$desiredLanguage = $user['desired_language'] ?? '';
$isSelf = uid() === $userId;
$isImpersonatingThisUser = admin_is_impersonating() && $isSelf;

$roleOptions = ['' => __('No admin access')];
foreach ($availableRoles as $roleOption) {
    $roleOptions[$roleOption] = ucfirst($roleOption);
}

$localeOptions = ['' => __('System default')];
foreach ($availableLocales as $code => $label) {
    $localeOptions[$code] = $label;
}

?>

<div class="space-y-10 pb-16">
  <header class="card relative overflow-hidden">
    <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-brand-500/10 blur-3xl"></div>
    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
      <div class="space-y-4">
        <div class="flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
          <a href="/admin/users" class="inline-flex items-center gap-2 hover:text-brand-600 dark:hover:text-emerald-200">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <?= htmlspecialchars(__('Back to directory')) ?>
          </a>
          <span>·</span>
          <span><?= htmlspecialchars(sprintf(__('User #%d'), $userId)) ?></span>
        </div>
        <div class="flex flex-col gap-2">
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
            <?= htmlspecialchars($user['email'] ?? '') ?>
          </h1>
          <?php if (!empty($user['full_name'])): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              <?= htmlspecialchars($user['full_name']) ?>
            </p>
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
            <?= htmlspecialchars(__('Joined')) ?>
            ·
            <?= htmlspecialchars(admin_relative_time($user['created_at'] ?? null)) ?>
          </span>
          <?php if (!empty($user['last_login_at'])): ?>
            <span class="chip bg-slate-200/60 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
              <?= htmlspecialchars(__('Last login')) ?>
              ·
              <?= htmlspecialchars(admin_relative_time($user['last_login_at'])) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="flex flex-col gap-3 text-sm text-slate-600 dark:text-slate-300">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Preferred language')) ?></p>
          <p class="font-medium text-slate-900 dark:text-white">
            <?= htmlspecialchars($desiredLanguage && isset($availableLocales[$desiredLanguage]) ? $availableLocales[$desiredLanguage] : __('Uses default')) ?>
          </p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Currencies')) ?></p>
          <?php if (empty($currencies)): ?>
            <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(__('None configured')) ?></p>
          <?php else: ?>
            <p class="font-medium text-slate-900 dark:text-white">
              <?php foreach ($currencies as $idx => $currency): ?>
                <span class="<?= !empty($currency['is_main']) ? 'font-semibold text-brand-600 dark:text-emerald-200' : '' ?>">
                  <?= htmlspecialchars($currency['code'] ?? '') ?>
                </span><?= $idx < count($currencies) - 1 ? ', ' : '' ?>
              <?php endforeach; ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <article class="tile">
      <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Lifetime transactions')) ?></p>
      <p class="text-3xl font-semibold text-slate-900 dark:text-white"><?= number_format((int)($transactionsSummary['count'] ?? 0)) ?></p>
    </article>
    <article class="tile">
      <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Lifetime net')) ?></p>
      <p class="text-3xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(moneyfmt($transactionsSummary['net_total'] ?? 0.0)) ?></p>
    </article>
    <article class="tile">
      <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('30 day net')) ?></p>
      <p class="text-3xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(moneyfmt($transactionsSummary['net_30d'] ?? 0.0)) ?></p>
    </article>
    <article class="tile">
      <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Goals tracked')) ?></p>
      <p class="text-3xl font-semibold text-slate-900 dark:text-white"><?= number_format(count($goals)) ?></p>
    </article>
  </section>

  <section class="card">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
      <?= htmlspecialchars(__('Administrative controls')) ?>
    </h2>
    <p class="text-sm text-slate-500 dark:text-slate-400">
      <?= htmlspecialchars(__('Adjust access, language preferences, and suspension status. All changes are captured in the audit log.')) ?>
    </p>
    <form action="/admin/users/<?= $userId ?>" method="post" class="mt-5 grid gap-4 lg:grid-cols-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <label class="grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Admin role')) ?></span>
        <select name="admin_role" class="input">
          <?php foreach ($roleOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= (string)$role === (string)$value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Preferred language')) ?></span>
        <select name="desired_language" class="input">
          <?php foreach ($localeOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= (string)$desiredLanguage === (string)$value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="lg:col-span-2 grid gap-1 text-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Internal admin notes')) ?></span>
        <textarea name="admin_notes" rows="3" class="input" placeholder="<?= htmlspecialchars(__('Notes visible to the admin team only.')) ?>"><?= htmlspecialchars($user['admin_notes'] ?? '', ENT_QUOTES) ?></textarea>
      </label>
      <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="suspend" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= $isSuspended ? 'checked' : '' ?> />
        <?= htmlspecialchars(__('Suspend account access')) ?>
      </label>
      <div class="lg:col-span-2 flex flex-wrap items-center gap-3 pt-2">
        <button class="btn btn-primary">
          <i data-lucide="save" class="mr-2 h-4 w-4"></i>
          <?= htmlspecialchars(__('Save changes')) ?>
        </button>
        <a href="/admin/users/<?= $userId ?>" class="btn btn-muted">
          <?= htmlspecialchars(__('Cancel')) ?>
        </a>
      </div>
    </form>
    <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
      <form action="/admin/users/<?= $userId ?>/impersonate" method="post" class="flex items-center justify-between gap-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div>
          <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Impersonate user')) ?></p>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            <?= htmlspecialchars(__('Switch into this account for troubleshooting. Your admin session can be restored from the banner.')) ?>
          </p>
        </div>
        <button class="btn btn-secondary" <?= $isImpersonatingThisUser ? 'disabled' : '' ?>>
          <i data-lucide="user-switch" class="mr-2 h-4 w-4"></i>
          <?= htmlspecialchars($isImpersonatingThisUser ? __('Currently impersonating') : __('Start impersonation')) ?>
        </button>
      </form>
    </div>
  </section>

  <section class="card">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
      <?= htmlspecialchars(__('Recent transactions')) ?>
    </h2>
    <?php if (empty($recentTransactions)): ?>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= htmlspecialchars(__('No transactions recorded yet.')) ?>
      </p>
    <?php else: ?>
      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
          <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4">ID</th>
              <th class="py-2 pr-4"><?= htmlspecialchars(__('Type')) ?></th>
              <th class="py-2 pr-4"><?= htmlspecialchars(__('Amount')) ?></th>
              <th class="py-2 pr-4"><?= htmlspecialchars(__('Category')) ?></th>
              <th class="py-2 pr-4"><?= htmlspecialchars(__('Occurred on')) ?></th>
              <th class="py-2 pr-4"><?= htmlspecialchars(__('Logged')) ?></th>
              <th class="py-2 pr-4"><?= htmlspecialchars(__('Note')) ?></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <?php foreach ($recentTransactions as $tx): ?>
              <tr class="align-top">
                <td class="py-2 pr-4 font-mono text-xs text-slate-500 dark:text-slate-400">#<?= (int)$tx['id'] ?></td>
                <td class="py-2 pr-4 text-slate-700 dark:text-slate-200">
                  <?= htmlspecialchars(ucfirst((string)($tx['kind'] ?? ''))) ?>
                </td>
                <td class="py-2 pr-4 text-slate-900 dark:text-white">
                  <?= htmlspecialchars(moneyfmt((float)($tx['amount'] ?? 0), (string)($tx['currency'] ?? ''))) ?>
                </td>
                <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars($tx['category'] ?? '—') ?>
                </td>
                <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars($tx['occurred_on'] ?? '—') ?>
                </td>
                <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars(admin_relative_time($tx['created_at'] ?? null)) ?>
                </td>
                <td class="py-2 text-slate-500 dark:text-slate-400">
                  <?= $tx['note'] ? nl2br(htmlspecialchars($tx['note'])) : '—' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <div class="card">
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Goals')) ?></h2>
      <?php if (empty($goals)): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('No goals added yet.')) ?></p>
      <?php else: ?>
        <ul class="mt-4 space-y-3 text-sm">
          <?php foreach ($goals as $goal): ?>
            <li class="rounded-2xl border border-white/70 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="flex items-center justify-between">
                <p class="font-semibold text-slate-900 dark:text-white">
                  <?= htmlspecialchars($goal['title'] ?? '') ?>
                </p>
                <span class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars(ucfirst($goal['status'] ?? '')) ?>
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars(moneyfmt((float)($goal['current_amount'] ?? 0), (string)($goal['currency'] ?? ''))) ?>
                / <?= htmlspecialchars(moneyfmt((float)($goal['target_amount'] ?? 0), (string)($goal['currency'] ?? ''))) ?>
                <?php if (!empty($goal['deadline'])): ?>· <?= htmlspecialchars(__('due')) ?> <?= htmlspecialchars($goal['deadline']) ?><?php endif; ?>
              </p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Loans')) ?></h2>
      <?php if (empty($loans)): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('No loans tracked.')) ?></p>
      <?php else: ?>
        <ul class="mt-4 space-y-3 text-sm">
          <?php foreach ($loans as $loan): ?>
            <li class="rounded-2xl border border-white/70 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-900/60">
              <p class="font-semibold text-slate-900 dark:text-white">
                <?= htmlspecialchars($loan['name'] ?? '') ?>
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars(__('Balance')) ?>: <?= htmlspecialchars(moneyfmt((float)($loan['balance'] ?? 0))) ?> · <?= htmlspecialchars(__('Rate')) ?>: <?= htmlspecialchars(number_format((float)($loan['interest_rate'] ?? 0), 2)) ?>%
              </p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <div class="card">
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Scheduled payments')) ?></h2>
      <?php if (empty($scheduled)): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('No active schedules.')) ?></p>
      <?php else: ?>
        <ul class="mt-4 space-y-3 text-sm">
          <?php foreach ($scheduled as $schedule): ?>
            <li class="rounded-2xl border border-white/70 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="flex items-center justify-between">
                <p class="font-semibold text-slate-900 dark:text-white">
                  <?= htmlspecialchars($schedule['title'] ?? '') ?>
                </p>
                <span class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars($schedule['next_due'] ?? __('unscheduled')) ?>
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars(moneyfmt((float)($schedule['amount'] ?? 0), (string)($schedule['currency'] ?? ''))) ?>
              </p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Feedback')) ?></h2>
      <?php if (empty($feedback)): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('No feedback submitted.')) ?></p>
      <?php else: ?>
        <ul class="mt-4 space-y-3 text-sm">
          <?php foreach ($feedback as $item): ?>
            <li class="rounded-2xl border border-white/70 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="flex items-center justify-between">
                <p class="font-semibold text-slate-900 dark:text-white">
                  <?= htmlspecialchars($item['title'] ?? '') ?>
                </p>
                <span class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars(ucfirst($item['status'] ?? '')) ?>
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars(ucfirst($item['kind'] ?? '')) ?> · <?= htmlspecialchars(admin_relative_time($item['created_at'] ?? null)) ?>
              </p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <section class="card">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Emergency fund')) ?></h2>
    <?php if (!$emergency): ?>
      <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('No emergency fund tracked for this user.')) ?></p>
    <?php else: ?>
      <div class="mt-4 grid gap-4 sm:grid-cols-3 text-sm text-slate-600 dark:text-slate-300">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Target')) ?></p>
          <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(moneyfmt((float)($emergency['target_amount'] ?? 0), (string)($emergency['currency'] ?? ''))) ?></p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Current total')) ?></p>
          <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(moneyfmt((float)($emergency['total'] ?? 0), (string)($emergency['currency'] ?? ''))) ?></p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('Progress')) ?></p>
          <p class="font-semibold text-slate-900 dark:text-white"><?= isset($emergency['progress']) ? $emergency['progress'] . '%' : '—' ?></p>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <section id="admin-audit-log" class="card">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(__('Audit log')) ?></h2>
    <?php if (empty($auditLog)): ?>
      <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(__('No admin actions recorded yet.')) ?></p>
    <?php else: ?>
      <ul class="mt-4 space-y-3 text-sm">
        <?php foreach ($auditLog as $entry): ?>
          <li class="rounded-2xl border border-white/70 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-900/60">
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <p class="font-semibold text-slate-900 dark:text-white">
                  <?= htmlspecialchars(str_replace('_', ' ', $entry['action'] ?? '')) ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars($entry['actor_email'] ?? __('Unknown admin')) ?>
                  · <?= htmlspecialchars(admin_relative_time($entry['created_at'] ?? null)) ?>
                </p>
              </div>
              <span class="text-xs font-mono text-slate-400 dark:text-slate-500">#<?= (int)$entry['id'] ?></span>
            </div>
            <?php if (!empty($entry['meta']) && is_array($entry['meta'])): ?>
              <div class="mt-2 grid gap-1 text-xs text-slate-500 dark:text-slate-400 sm:grid-cols-2">
                <?php foreach ($entry['meta'] as $key => $value): ?>
                  <div><span class="font-semibold text-slate-600 dark:text-slate-300"><?= htmlspecialchars((string)$key) ?>:</span> <?= htmlspecialchars(is_scalar($value) ? (string)$value : json_encode($value)) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
