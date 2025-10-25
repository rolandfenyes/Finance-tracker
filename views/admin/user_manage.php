<?php
$user = $user ?? [];
$roleOptions = $roleOptions ?? [];
$statusOptions = $statusOptions ?? [];
$activity = $activity ?? [];
$subscriptions = $subscriptions ?? [];
$invoices = $invoices ?? [];
$payments = $payments ?? [];
$invoiceStatusOptions = $invoiceStatusOptions ?? [];
$paymentStatusOptions = $paymentStatusOptions ?? [];
$paymentTypeOptions = $paymentTypeOptions ?? [];
$feedbackEntries = $feedbackEntries ?? [];
$feedbackResponses = $feedbackResponses ?? [];
$feedbackKindOptions = $feedbackKindOptions ?? [];
$feedbackSeverityOptions = $feedbackSeverityOptions ?? [];
$feedbackStatusOptions = $feedbackStatusOptions ?? [];
$currentUrl = $currentUrl ?? '/admin/users/manage';
$returnUrl = $returnUrl ?? '/admin/users';

$userId = (int)($user['id'] ?? 0);
$displayName = trim($user['full_name'] ?? '') ?: ($user['email'] ?? __('Unknown'));
$email = $user['email'] ?? '';
$role = normalize_user_role($user['role'] ?? null);
$status = normalize_user_status($user['status'] ?? null);
$verified = !empty($user['email_verified_at']);
$roleLabel = match ($role) {
    ROLE_ADMIN => __('Administrator'),
    ROLE_PREMIUM => __('Premium user'),
    default => __('Free user'),
};
$statusLabel = $status === USER_STATUS_ACTIVE ? __('Active') : __('Inactive');
$verificationLabel = $verified ? __('Verified') : __('Unverified');
$createdAt = $user['created_at'] ?? null;
$updatedAt = $user['updated_at'] ?? null;
$deactivatedAt = $user['deactivated_at'] ?? null;
$lastLoginAt = $user['last_login_at'] ?? null;
$lastLoginIp = $user['last_login_ip'] ?? null;
$lastLoginAgent = $user['last_login_user_agent'] ?? null;
$language = $user['desired_language'] ?? null;

$subscriptionStatusLabels = [
    'active' => __('Active'),
    'trialing' => __('Trialing'),
    'past_due' => __('Past due'),
    'canceled' => __('Canceled'),
    'expired' => __('Expired'),
];

$subscriptionStatusClasses = [
    'active' => 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100',
    'trialing' => 'border-sky-300/70 bg-sky-100/60 text-sky-700 dark:border-sky-400/60 dark:bg-sky-500/20 dark:text-sky-100',
    'past_due' => 'border-amber-300/70 bg-amber-100/60 text-amber-700 dark:border-amber-400/50 dark:bg-amber-500/20 dark:text-amber-100',
    'canceled' => 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200',
    'expired' => 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100',
];

$billingIntervalLabels = [
    'weekly' => __('Weekly'),
    'monthly' => __('Monthly'),
    'yearly' => __('Yearly'),
    'lifetime' => __('Lifetime'),
];

$invoiceStatusClasses = [
    'draft' => 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200',
    'open' => 'border-sky-300/70 bg-sky-100/60 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/20 dark:text-sky-100',
    'past_due' => 'border-amber-300/70 bg-amber-100/60 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/20 dark:text-amber-100',
    'paid' => 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100',
    'failed' => 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100',
    'refunded' => 'border-purple-300/70 bg-purple-100/60 text-purple-700 dark:border-purple-400/60 dark:bg-purple-500/20 dark:text-purple-100',
    'void' => 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200',
];

$paymentStatusClasses = [
    'pending' => 'border-amber-300/70 bg-amber-100/60 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/20 dark:text-amber-100',
    'succeeded' => 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100',
    'failed' => 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100',
    'canceled' => 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200',
];

$subscriptionLookup = [];
foreach ($subscriptions as $sub) {
    $subscriptionLookup[(int)$sub['id']] = trim((string)$sub['plan_name']);
}

$invoiceLookup = [];
foreach ($invoices as $invoice) {
    $invoiceLookup[(int)$invoice['id']] = $invoice;
}
?>

<div class="mx-auto w-full max-w-5xl space-y-6 pb-12">
  <section class="card">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
          <i data-lucide="shield-check" class="h-4 w-4"></i>
          <?= __('Administration') ?>
        </div>
        <h1 class="card-title mt-4 text-3xl font-semibold text-slate-900 dark:text-white">
          <?= __('Manage user') ?>
        </h1>
        <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Review account details, manage permissions, and take action on this user.') ?>
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <a class="btn btn-muted inline-flex items-center gap-2" href="<?= htmlspecialchars($returnUrl) ?>">
          <i data-lucide="arrow-left" class="h-4 w-4"></i>
          <span><?= __('Back to users') ?></span>
        </a>
        <a class="btn btn-muted inline-flex items-center gap-2" href="/admin/users">
          <i data-lucide="list" class="h-4 w-4"></i>
          <span><?= __('All users') ?></span>
        </a>
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

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_3fr]">
      <div class="space-y-6">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">
            <?= __('Profile summary') ?>
          </h2>
          <dl class="mt-4 grid gap-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <?= __('Name') ?>
              </dt>
              <dd class="mt-1 text-base font-medium text-slate-900 dark:text-slate-100">
                <?= htmlspecialchars($displayName) ?>
              </dd>
            </div>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <?= __('Email') ?>
              </dt>
              <dd class="mt-1 font-mono text-sm">
                <?= htmlspecialchars($email) ?>
              </dd>
            </div>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200">
                <i data-lucide="user" class="h-3.5 w-3.5"></i>
                <?= htmlspecialchars($roleLabel) ?>
              </span>
              <span class="inline-flex items-center gap-2 rounded-full border <?= $status === USER_STATUS_ACTIVE ? 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100' : 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100' ?> px-3 py-1 text-xs font-semibold">
                <i data-lucide="<?= $status === USER_STATUS_ACTIVE ? 'check-circle' : 'slash' ?>" class="h-3.5 w-3.5"></i>
                <?= htmlspecialchars($statusLabel) ?>
              </span>
              <span class="inline-flex items-center gap-2 rounded-full border <?= $verified ? 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100' : 'border-amber-300/60 bg-amber-100/50 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/20 dark:text-amber-100' ?> px-3 py-1 text-xs font-semibold">
                <i data-lucide="<?= $verified ? 'badge-check' : 'hourglass' ?>" class="h-3.5 w-3.5"></i>
                <?= htmlspecialchars($verificationLabel) ?>
              </span>
            </div>
            <?php if ($language): ?>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  <?= __('Preferred language') ?>
                </dt>
                <dd class="mt-1">
                  <?= htmlspecialchars(strtoupper((string)$language)) ?>
                </dd>
              </div>
            <?php endif; ?>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <?= __('Created at') ?>
              </dt>
              <dd class="mt-1">
                <?= $createdAt ? date('Y-m-d H:i', strtotime((string)$createdAt)) : __('Unknown') ?>
              </dd>
            </div>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <?= __('Last updated') ?>
              </dt>
              <dd class="mt-1">
                <?= $updatedAt ? date('Y-m-d H:i', strtotime((string)$updatedAt)) : __('Unknown') ?>
              </dd>
            </div>
            <?php if ($deactivatedAt): ?>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  <?= __('Deactivated at') ?>
                </dt>
                <dd class="mt-1">
                  <?= date('Y-m-d H:i', strtotime((string)$deactivatedAt)) ?>
                </dd>
              </div>
            <?php endif; ?>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <?= __('Last successful login') ?>
              </dt>
              <dd class="mt-1 space-y-1">
                <div>
                  <?= $lastLoginAt ? date('Y-m-d H:i', strtotime((string)$lastLoginAt)) : __('Never') ?>
                </div>
                <?php if ($lastLoginIp): ?>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    <?= __('IP address') ?>: <?= htmlspecialchars($lastLoginIp) ?>
                  </div>
                <?php endif; ?>
                <?php if ($lastLoginAgent): ?>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    <?= __('User agent') ?>: <?= htmlspecialchars($lastLoginAgent) ?>
                  </div>
                <?php endif; ?>
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="space-y-6">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">
            <?= __('Account actions') ?>
          </h2>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <form action="/admin/users/role" method="post" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                <?= __('Role') ?>
              </div>
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="user_id" value="<?= $userId ?>" />
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
              <select name="role" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                <?php foreach ($roleOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value) ?>" <?= $role === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary justify-center">
                <?= __('Update role') ?>
              </button>
            </form>

            <form action="/admin/users/status" method="post" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                <?= __('Account status') ?>
              </div>
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="user_id" value="<?= $userId ?>" />
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
              <select name="status" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn <?= $status === USER_STATUS_ACTIVE ? 'btn-danger' : 'btn-primary' ?> justify-center">
                <?= __('Update status') ?>
              </button>
            </form>

            <form action="/admin/users/reset-password" method="post" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                <?= __('Reset password') ?>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= __('Generate a new temporary password and sign the user out of remembered sessions.') ?>
              </p>
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="user_id" value="<?= $userId ?>" />
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
              <button class="btn btn-muted justify-center">
                <i data-lucide="key-round" class="h-4 w-4"></i>
                <span><?= __('Reset password') ?></span>
              </button>
            </form>

            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                <?= __('Email verification') ?>
              </div>
              <?php if ($verified): ?>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  <?= __('The email address is already verified.') ?>
                </p>
              <?php else: ?>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  <?= __('Send another verification email to confirm ownership of the address.') ?>
                </p>
                <form action="/admin/users/resend-verification" method="post" class="mt-2 flex flex-col gap-3">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="user_id" value="<?= $userId ?>" />
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
                  <button class="btn btn-muted justify-center">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    <span><?= __('Resend verification email') ?></span>
                  </button>
                </form>
              <?php endif; ?>
            </div>

            <form action="/admin/users/reset-email" method="post" class="md:col-span-2 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/60">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                <?= __('Reset email address') ?>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= __('Change the sign-in email and send a verification request to the new address.') ?>
              </p>
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="user_id" value="<?= $userId ?>" />
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                  type="email"
                  name="new_email"
                  placeholder="<?= htmlspecialchars($email) ?>"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
                  required
                />
                <button class="btn btn-muted justify-center sm:w-auto">
                  <?= __('Update email') ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">
            <?= __('Recent login activity') ?>
          </h2>
          <?php if ($activity): ?>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
              <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                  <tr>
                    <th class="px-4 py-3 font-semibold"><?= __('Date') ?></th>
                    <th class="px-4 py-3 font-semibold"><?= __('Method') ?></th>
                    <th class="px-4 py-3 font-semibold"><?= __('Result') ?></th>
                    <th class="px-4 py-3 font-semibold"><?= __('IP address') ?></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <?php foreach ($activity as $event):
                    $eventTime = $event['created_at'] ?? null;
                    $success = (bool)($event['success'] ?? false);
                    $method = $event['method'] ?? '';
                    $ip = $event['ip_address'] ?? '';
                    ?>
                    <tr>
                      <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                        <?= $eventTime ? date('Y-m-d H:i', strtotime((string)$eventTime)) : __('Unknown') ?>
                      </td>
                      <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                        <?= htmlspecialchars(ucfirst((string)$method)) ?>
                      </td>
                      <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-2 rounded-full border <?= $success ? 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-300/50 dark:bg-emerald-400/20 dark:text-emerald-100' : 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100' ?> px-3 py-1 text-xs font-semibold">
                          <i data-lucide="<?= $success ? 'check' : 'x' ?>" class="h-3.5 w-3.5"></i>
                          <?= $success ? __('Success') : __('Failed') ?>
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                        <?= $ip ? htmlspecialchars($ip) : '—' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
              <?= __('No recent login attempts recorded.') ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mt-10 space-y-8">
      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
              <i data-lucide="credit-card" class="h-4 w-4"></i>
              <?= __('Billing & subscriptions') ?>
            </div>
            <h2 class="card-title mt-2 text-xl font-semibold text-slate-900 dark:text-white">
              <?= __('Active plans') ?>
            </h2>
            <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
              <?= __('Review subscription terms, upcoming renewals, and plan history.') ?>
            </p>
          </div>
        </div>

        <?php if ($subscriptions): ?>
          <div class="mt-4 space-y-4">
            <?php foreach ($subscriptions as $sub):
              $subStatus = $sub['status'] ?? '';
              $statusLabel = $subscriptionStatusLabels[$subStatus] ?? ucfirst((string)$subStatus);
              $statusClass = $subscriptionStatusClasses[$subStatus] ?? 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200';
              $intervalKey = $sub['billing_interval'] ?? '';
              $intervalLabel = $billingIntervalLabels[$intervalKey] ?? $intervalKey;
              $intervalCount = max(1, (int)($sub['interval_count'] ?? 1));
              if ($intervalKey === 'lifetime') {
                  $cycleLabel = __('Lifetime access');
              } elseif ($intervalCount === 1) {
                  $cycleLabel = __('Every :interval', ['interval' => strtolower((string)$intervalLabel)]);
              } else {
                  $cycleLabel = __('Every :count :intervals', ['count' => $intervalCount, 'intervals' => strtolower((string)$intervalLabel)]);
              }
              $amountLabel = moneyfmt($sub['amount'], strtoupper((string)$sub['currency']));
              $periodStart = $sub['current_period_start'] ? date('Y-m-d', strtotime((string)$sub['current_period_start'])) : '—';
              $periodEnd = $sub['current_period_end'] ? date('Y-m-d', strtotime((string)$sub['current_period_end'])) : '—';
              $cancelAt = $sub['cancel_at'] ? date('Y-m-d', strtotime((string)$sub['cancel_at'])) : null;
              $canceledAt = $sub['canceled_at'] ? date('Y-m-d', strtotime((string)$sub['canceled_at'])) : null;
              $trialEndsAt = $sub['trial_ends_at'] ? date('Y-m-d', strtotime((string)$sub['trial_ends_at'])) : null;
            ?>
              <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div class="text-lg font-semibold text-slate-900 dark:text-white">
                      <?= htmlspecialchars($sub['plan_name'] ?? __('Unnamed plan')) ?>
                    </div>
                    <div class="text-xs font-mono text-slate-500 dark:text-slate-400">
                      <?= __('Plan code') ?>: <?= htmlspecialchars($sub['plan_code'] ?? '-') ?>
                    </div>
                  </div>
                  <span class="inline-flex items-center gap-2 rounded-full border <?= $statusClass ?> px-3 py-1 text-xs font-semibold">
                    <i data-lucide="badge-check" class="h-3.5 w-3.5"></i>
                    <?= htmlspecialchars($statusLabel) ?>
                  </span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm text-slate-600 dark:text-slate-300 md:grid-cols-2">
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <?= __('Billing cycle') ?>
                    </dt>
                    <dd class="mt-1"><?= htmlspecialchars($cycleLabel) ?></dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <?= __('Price per cycle') ?>
                    </dt>
                    <dd class="mt-1 font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($amountLabel) ?></dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <?= __('Current period') ?>
                    </dt>
                    <dd class="mt-1"><?= $periodStart ?> → <?= $periodEnd ?></dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <?= __('Started on') ?>
                    </dt>
                    <dd class="mt-1"><?= $sub['started_at'] ? date('Y-m-d', strtotime((string)$sub['started_at'])) : '—' ?></dd>
                  </div>
                  <?php if ($trialEndsAt): ?>
                    <div>
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <?= __('Trial ends') ?>
                      </dt>
                      <dd class="mt-1"><?= $trialEndsAt ?></dd>
                    </div>
                  <?php endif; ?>
                  <?php if ($cancelAt || $canceledAt): ?>
                    <div>
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <?= $canceledAt ? __('Canceled at') : __('Scheduled cancellation') ?>
                      </dt>
                      <dd class="mt-1"><?= $canceledAt ?: $cancelAt ?></dd>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($sub['notes'])): ?>
                    <div class="md:col-span-2">
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <?= __('Notes') ?>
                      </dt>
                      <dd class="mt-1 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string)$sub['notes'])) ?></dd>
                    </div>
                  <?php endif; ?>
                </dl>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            <?= __('No subscriptions recorded for this account.') ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
              <i data-lucide="file-text" class="h-4 w-4"></i>
              <?= __('Invoices & billing history') ?>
            </div>
            <h2 class="card-title mt-2 text-xl font-semibold text-slate-900 dark:text-white">
              <?= __('Manage invoices') ?>
            </h2>
            <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
              <?= __('Review invoice status, capture notes, and update outcomes for failed or refunded payments.') ?>
            </p>
          </div>
        </div>

        <?php if ($invoices): ?>
          <div class="mt-4 space-y-4">
            <?php foreach ($invoices as $invoice):
              $invoiceId = (int)($invoice['id'] ?? 0);
              $invStatus = $invoice['status'] ?? '';
              $invStatusLabel = $invoiceStatusOptions[$invStatus] ?? ucfirst((string)$invStatus);
              $invStatusClass = $invoiceStatusClasses[$invStatus] ?? 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200';
              $invoiceAmount = moneyfmt($invoice['total_amount'], strtoupper((string)$invoice['currency']));
              $issuedAt = $invoice['issued_at'] ? date('Y-m-d', strtotime((string)$invoice['issued_at'])) : '—';
              $dueAt = $invoice['due_at'] ? date('Y-m-d', strtotime((string)$invoice['due_at'])) : '—';
              $paidAt = $invoice['paid_at'] ? date('Y-m-d', strtotime((string)$invoice['paid_at'])) : '—';
              $reasonPreset = $invoice['failure_reason'] ?? $invoice['refund_reason'] ?? '';
              $subscriptionName = $invoice['subscription_id'] && isset($subscriptionLookup[$invoice['subscription_id']])
                ? $subscriptionLookup[$invoice['subscription_id']]
                : null;
            ?>
              <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div class="text-lg font-semibold text-slate-900 dark:text-white">
                      <?= __('Invoice #:number', ['number' => htmlspecialchars($invoice['invoice_number'] ?? (string)$invoiceId)]) ?>
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      <?= __('Issued :date · Due :due', ['date' => $issuedAt, 'due' => $dueAt]) ?>
                    </div>
                    <?php if ($subscriptionName): ?>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Subscription') ?>: <?= htmlspecialchars($subscriptionName) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="flex flex-col items-end gap-2 text-right">
                    <span class="inline-flex items-center gap-2 rounded-full border <?= $invStatusClass ?> px-3 py-1 text-xs font-semibold">
                      <i data-lucide="receipt" class="h-3.5 w-3.5"></i>
                      <?= htmlspecialchars($invStatusLabel) ?>
                    </span>
                    <div class="text-base font-semibold text-slate-900 dark:text-white">
                      <?= htmlspecialchars($invoiceAmount) ?>
                    </div>
                  </div>
                </div>

                <dl class="mt-4 grid gap-3 text-sm text-slate-600 dark:text-slate-300 md:grid-cols-2">
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <?= __('Paid at') ?>
                    </dt>
                    <dd class="mt-1"><?= $paidAt ?></dd>
                  </div>
                  <?php if (!empty($invoice['failure_reason'])): ?>
                    <div>
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <?= __('Failure reason') ?>
                      </dt>
                      <dd class="mt-1 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string)$invoice['failure_reason'])) ?></dd>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($invoice['refund_reason'])): ?>
                    <div>
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <?= __('Refund reason') ?>
                      </dt>
                      <dd class="mt-1 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string)$invoice['refund_reason'])) ?></dd>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($invoice['notes'])): ?>
                    <div class="md:col-span-2">
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <?= __('Notes') ?>
                      </dt>
                      <dd class="mt-1 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string)$invoice['notes'])) ?></dd>
                    </div>
                  <?php endif; ?>
                </dl>

                <form method="post" action="/admin/users/invoices/update" class="mt-4 grid gap-3 md:grid-cols-4">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="user_id" value="<?= $userId ?>" />
                  <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="invoice-status-<?= $invoiceId ?>">
                      <?= __('Status') ?>
                    </label>
                    <select id="invoice-status-<?= $invoiceId ?>" name="status" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                      <?php foreach ($invoiceStatusOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $key === $invStatus ? 'selected' : '' ?>>
                          <?= htmlspecialchars($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="invoice-reason-<?= $invoiceId ?>">
                      <?= __('Reason (fail/refund)') ?>
                    </label>
                    <input id="invoice-reason-<?= $invoiceId ?>" name="reason" value="<?= htmlspecialchars($reasonPreset) ?>" placeholder="<?= __('Optional explanation') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="invoice-note-<?= $invoiceId ?>">
                      <?= __('Internal notes') ?>
                    </label>
                    <textarea id="invoice-note-<?= $invoiceId ?>" name="note" rows="2" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" placeholder="<?= __('Add context for teammates…') ?>"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
                  </div>
                  <div class="md:col-span-4 flex justify-end">
                    <button class="btn btn-primary">
                      <?= __('Update invoice') ?>
                    </button>
                  </div>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            <?= __('No invoices have been generated for this user yet.') ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
              <i data-lucide="wallet" class="h-4 w-4"></i>
              <?= __('Payments & adjustments') ?>
            </div>
            <h2 class="card-title mt-2 text-xl font-semibold text-slate-900 dark:text-white">
              <?= __('Record payment events') ?>
            </h2>
            <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
              <?= __('Log manual charges, refunds, or corrections and update the status of individual transactions.') ?>
            </p>
          </div>
        </div>

        <form method="post" action="/admin/users/payments/create" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-white/80 p-5 dark:border-slate-800 dark:bg-slate-900/60 md:grid-cols-4">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input type="hidden" name="user_id" value="<?= $userId ?>" />
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-invoice">
              <?= __('Invoice (optional)') ?>
            </label>
            <select id="payment-invoice" name="invoice_id" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
              <option value=""><?= __('Unlinked payment') ?></option>
              <?php foreach ($invoices as $invoice): ?>
                <option value="<?= (int)$invoice['id'] ?>">
                  <?= htmlspecialchars($invoice['invoice_number'] ?? ('#' . (int)$invoice['id'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-type">
              <?= __('Type') ?>
            </label>
            <select id="payment-type" name="type" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
              <?php foreach ($paymentTypeOptions as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-status">
              <?= __('Status') ?>
            </label>
            <select id="payment-status" name="status" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
              <?php foreach ($paymentStatusOptions as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex gap-2">
            <div class="w-1/2">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-amount">
                <?= __('Amount') ?>
              </label>
              <input id="payment-amount" name="amount" type="number" step="0.01" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
            </div>
            <div class="w-1/2">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-currency">
                <?= __('Currency') ?>
              </label>
              <input id="payment-currency" name="currency" value="<?= htmlspecialchars(strtoupper((string)($invoices[0]['currency'] ?? 'HUF'))) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm uppercase tracking-wide text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
            </div>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-gateway">
              <?= __('Gateway') ?>
            </label>
            <input id="payment-gateway" name="gateway" placeholder="<?= __('Stripe, PayPal…') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-reference">
              <?= __('Reference') ?>
            </label>
            <input id="payment-reference" name="reference" placeholder="<?= __('Transaction ID') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-failure">
              <?= __('Failure reason') ?>
            </label>
            <input id="payment-failure" name="failure_reason" placeholder="<?= __('Optional when status is failed') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-processed">
              <?= __('Processed at') ?>
            </label>
            <input id="payment-processed" name="processed_at" type="datetime-local" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-note">
              <?= __('Internal notes') ?>
            </label>
            <textarea id="payment-note" name="note" rows="2" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" placeholder="<?= __('Add context for this payment…') ?>"></textarea>
          </div>
          <div class="md:col-span-4 flex justify-end">
            <button class="btn btn-primary">
              <?= __('Record payment') ?>
            </button>
          </div>
        </form>

        <?php if ($payments): ?>
          <div class="mt-6 space-y-4">
            <?php foreach ($payments as $payment):
              $paymentId = (int)($payment['id'] ?? 0);
              $paymentStatus = $payment['status'] ?? '';
              $paymentStatusLabel = $paymentStatusOptions[$paymentStatus] ?? ucfirst((string)$paymentStatus);
              $paymentStatusClass = $paymentStatusClasses[$paymentStatus] ?? 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200';
              $paymentTypeLabel = $paymentTypeOptions[$payment['type'] ?? ''] ?? ucfirst((string)($payment['type'] ?? ''));
              $paymentAmount = moneyfmt($payment['amount'], strtoupper((string)$payment['currency']));
              $processedAt = $payment['processed_at'] ? date('Y-m-d H:i', strtotime((string)$payment['processed_at'])) : __('Pending');
              $processedValue = $payment['processed_at'] ? date('Y-m-d\TH:i', strtotime((string)$payment['processed_at'])) : '';
              $linkedInvoice = null;
              if (!empty($payment['invoice_id']) && isset($invoiceLookup[$payment['invoice_id']])) {
                  $linkedInvoice = $invoiceLookup[$payment['invoice_id']];
              }
            ?>
              <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200">
                        <i data-lucide="coins" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($paymentTypeLabel) ?>
                      </span>
                      <span class="inline-flex items-center gap-2 rounded-full border <?= $paymentStatusClass ?> px-3 py-1 text-xs font-semibold">
                        <i data-lucide="activity" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($paymentStatusLabel) ?>
                      </span>
                    </div>
                    <div class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                      <?= htmlspecialchars($paymentAmount) ?>
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      <?= __('Processed at :date', ['date' => $processedAt]) ?>
                    </div>
                    <?php if ($linkedInvoice): ?>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Linked invoice') ?>: <?= htmlspecialchars($linkedInvoice['invoice_number'] ?? ('#' . (int)$linkedInvoice['id'])) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($payment['gateway'])): ?>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Gateway') ?>: <?= htmlspecialchars((string)$payment['gateway']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($payment['transaction_reference'])): ?>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Reference') ?>: <?= htmlspecialchars((string)$payment['transaction_reference']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($payment['failure_reason'])): ?>
                      <div class="mt-1 text-xs text-rose-500 dark:text-rose-300">
                        <?= __('Failure reason') ?>: <?= htmlspecialchars((string)$payment['failure_reason']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($payment['notes'])): ?>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-300">
                        <?= __('Notes') ?>: <?= nl2br(htmlspecialchars((string)$payment['notes'])) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <form method="post" action="/admin/users/payments/update" class="mt-4 grid gap-3 md:grid-cols-4">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="user_id" value="<?= $userId ?>" />
                  <input type="hidden" name="payment_id" value="<?= $paymentId ?>" />
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-status-<?= $paymentId ?>">
                      <?= __('Status') ?>
                    </label>
                    <select id="payment-status-<?= $paymentId ?>" name="status" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                      <?php foreach ($paymentStatusOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $key === $paymentStatus ? 'selected' : '' ?>>
                          <?= htmlspecialchars($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-processed-<?= $paymentId ?>">
                      <?= __('Processed at') ?>
                    </label>
                    <input id="payment-processed-<?= $paymentId ?>" name="processed_at" type="datetime-local" value="<?= htmlspecialchars($processedValue) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-gateway-<?= $paymentId ?>">
                      <?= __('Gateway') ?>
                    </label>
                    <input id="payment-gateway-<?= $paymentId ?>" name="gateway" value="<?= htmlspecialchars((string)$payment['gateway']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-reference-<?= $paymentId ?>">
                      <?= __('Reference') ?>
                    </label>
                    <input id="payment-reference-<?= $paymentId ?>" name="reference" value="<?= htmlspecialchars((string)$payment['transaction_reference']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-failure-<?= $paymentId ?>">
                      <?= __('Failure reason') ?>
                    </label>
                    <input id="payment-failure-<?= $paymentId ?>" name="failure_reason" value="<?= htmlspecialchars((string)$payment['failure_reason']) ?>" placeholder="<?= __('Only for failed payments') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="payment-note-<?= $paymentId ?>">
                      <?= __('Internal notes') ?>
                    </label>
                    <textarea id="payment-note-<?= $paymentId ?>" name="note" rows="2" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" placeholder="<?= __('Share context with the team…') ?>"><?= htmlspecialchars((string)$payment['notes']) ?></textarea>
                  </div>
                  <div class="md:col-span-4 flex justify-end">
                    <button class="btn btn-muted">
                      <?= __('Update payment') ?>
                    </button>
                  </div>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">
            <?= __('No payment history recorded yet.') ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
              <i data-lucide="messages-square" class="h-4 w-4"></i>
              <?= __('User feedback') ?>
            </div>
            <h2 class="card-title mt-2 text-xl font-semibold text-slate-900 dark:text-white">
              <?= __('Feedback & responses') ?>
            </h2>
            <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
              <?= __('Review submissions, adjust severity or status, and reply directly to the user.') ?>
            </p>
          </div>
        </div>

        <?php if ($feedbackEntries): ?>
          <div class="mt-4 space-y-4">
            <?php foreach ($feedbackEntries as $entry):
              $feedbackId = (int)($entry['id'] ?? 0);
              $kind = $entry['kind'] ?? '';
              $statusKey = $entry['status'] ?? '';
              $severityKey = $entry['severity'] ?? '';
              $kindLabel = $feedbackKindOptions[$kind] ?? ucfirst((string)$kind);
              $statusLabel = $feedbackStatusOptions[$statusKey] ?? ucfirst((string)$statusKey);
              $severityLabel = $severityKey ? ($feedbackSeverityOptions[$severityKey] ?? ucfirst((string)$severityKey)) : __('Not set');
              $createdAt = $entry['created_at'] ? date('Y-m-d H:i', strtotime((string)$entry['created_at'])) : '—';
              $updatedAt = $entry['updated_at'] ? date('Y-m-d H:i', strtotime((string)$entry['updated_at'])) : '—';
              $responsesForEntry = $feedbackResponses[$feedbackId] ?? [];
            ?>
              <div class="space-y-4 rounded-2xl border border-slate-200 bg-white/80 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="inline-flex items-center gap-2 rounded-full border border-amber-300/70 bg-amber-100/60 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/20 dark:text-amber-100">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($kindLabel) ?>
                      </span>
                      <span class="inline-flex items-center gap-2 rounded-full border border-slate-300/60 bg-slate-200/60 px-3 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
                        <i data-lucide="gauge" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($severityLabel) ?>
                      </span>
                      <span class="inline-flex items-center gap-2 rounded-full border border-brand-300/70 bg-brand-100/60 px-3 py-1 text-xs font-semibold text-brand-700 dark:border-brand-400/60 dark:bg-brand-500/20 dark:text-brand-100">
                        <i data-lucide="workflow" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($statusLabel) ?>
                      </span>
                    </div>
                    <div class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">
                      <?= htmlspecialchars($entry['title'] ?? __('Untitled feedback')) ?>
                    </div>
                    <div class="mt-2 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">
                      <?= nl2br(htmlspecialchars((string)$entry['message'])) ?>
                    </div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                      <?= __('Created :created · Updated :updated', ['created' => $createdAt, 'updated' => $updatedAt]) ?>
                    </div>
                  </div>
                </div>

                <form method="post" action="/admin/users/feedback/update" class="grid gap-3 rounded-2xl border border-slate-200 bg-white/70 p-4 dark:border-slate-700 dark:bg-slate-900/50 md:grid-cols-2">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="user_id" value="<?= $userId ?>" />
                  <input type="hidden" name="feedback_id" value="<?= $feedbackId ?>" />
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="feedback-kind-<?= $feedbackId ?>">
                      <?= __('Type') ?>
                    </label>
                    <select id="feedback-kind-<?= $feedbackId ?>" name="kind" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                      <?php foreach ($feedbackKindOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $key === $kind ? 'selected' : '' ?>>
                          <?= htmlspecialchars($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="feedback-severity-<?= $feedbackId ?>">
                      <?= __('Severity') ?>
                    </label>
                    <select id="feedback-severity-<?= $feedbackId ?>" name="severity" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                      <option value=""><?= __('Not set') ?></option>
                      <?php foreach ($feedbackSeverityOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $key === $severityKey ? 'selected' : '' ?>>
                          <?= htmlspecialchars($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="feedback-status-<?= $feedbackId ?>">
                      <?= __('Status') ?>
                    </label>
                    <select id="feedback-status-<?= $feedbackId ?>" name="status" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                      <?php foreach ($feedbackStatusOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $key === $statusKey ? 'selected' : '' ?>>
                          <?= htmlspecialchars($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="feedback-title-<?= $feedbackId ?>">
                      <?= __('Title') ?>
                    </label>
                    <input id="feedback-title-<?= $feedbackId ?>" name="title" value="<?= htmlspecialchars((string)$entry['title']) ?>" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="feedback-message-<?= $feedbackId ?>">
                      <?= __('Message') ?>
                    </label>
                    <textarea id="feedback-message-<?= $feedbackId ?>" name="message" rows="3" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"><?= htmlspecialchars((string)$entry['message']) ?></textarea>
                  </div>
                  <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-muted">
                      <?= __('Save changes') ?>
                    </button>
                  </div>
                </form>

                <form method="post" action="/admin/users/feedback/respond" class="grid gap-3 rounded-2xl border border-slate-200 bg-white/70 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="user_id" value="<?= $userId ?>" />
                  <input type="hidden" name="feedback_id" value="<?= $feedbackId ?>" />
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>" />
                  <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="feedback-response-<?= $feedbackId ?>">
                      <?= __('Respond to user') ?>
                    </label>
                    <textarea id="feedback-response-<?= $feedbackId ?>" name="message" rows="2" placeholder="<?= __('Share an update or follow-up…') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200" required></textarea>
                  </div>
                  <div class="flex justify-end">
                    <button class="btn btn-primary">
                      <?= __('Send response') ?>
                    </button>
                  </div>
                </form>

                <?php if ($responsesForEntry): ?>
                  <div class="space-y-3">
                    <?php foreach ($responsesForEntry as $response):
                      $resCreated = $response['created_at'] ? date('Y-m-d H:i', strtotime((string)$response['created_at'])) : '—';
                      $resName = trim((string)($response['admin_name'] ?? '')) ?: ($response['admin_email'] ?? __('Administrator'));
                    ?>
                      <div class="rounded-2xl border border-slate-200 bg-white/60 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                          <div class="font-semibold text-slate-900 dark:text-white">
                            <?= htmlspecialchars($resName) ?>
                          </div>
                          <div class="text-xs text-slate-500 dark:text-slate-400">
                            <?= $resCreated ?>
                          </div>
                        </div>
                        <div class="mt-2 whitespace-pre-wrap">
                          <?= nl2br(htmlspecialchars((string)$response['message'])) ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            <?= __('No feedback submissions from this user yet.') ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
