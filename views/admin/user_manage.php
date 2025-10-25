<?php
$user = $user ?? [];
$roleOptions = $roleOptions ?? [];
$statusOptions = $statusOptions ?? [];
$activity = $activity ?? [];
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
  </section>
</div>
