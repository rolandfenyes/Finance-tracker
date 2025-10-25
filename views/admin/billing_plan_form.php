<?php
$plan = $plan ?? [
    'id' => null,
    'code' => '',
    'name' => '',
    'description' => '',
    'price' => 0,
    'currency' => 'USD',
    'billing_interval' => 'monthly',
    'interval_count' => 1,
    'role_slug' => '',
    'trial_days' => null,
    'is_active' => true,
    'stripe_product_id' => '',
    'stripe_price_id' => '',
];
$roleOptions = $roleOptions ?? [];
$intervalLabels = $intervalLabels ?? [];
$mode = $mode ?? 'create';
$action = $mode === 'edit' ? '/admin/billing/plans/update' : '/admin/billing/plans';
?>
<section class="mx-auto max-w-4xl space-y-6">
  <header>
    <a href="/admin/billing" class="inline-flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-500 dark:text-emerald-300">
      <i data-lucide="arrow-left" class="h-4 w-4"></i>
      <span><?= __('Back to billing') ?></span>
    </a>
    <h1 class="mt-4 text-3xl font-semibold text-slate-900 dark:text-white">
      <?= $mode === 'edit' ? __('Edit pricing plan') : __('Create pricing plan') ?>
    </h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
      <?= __('Define subscription terms, assign the target role, and connect Stripe product IDs.') ?>
    </p>
  </header>

  <form action="<?= $action ?>" method="post" class="grid gap-4 rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <?php if (!empty($plan['id'])): ?>
      <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
    <?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Name') ?></span>
        <input type="text" name="name" value="<?= htmlspecialchars((string)$plan['name'], ENT_QUOTES) ?>" required class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Code') ?></span>
        <input type="text" name="code" value="<?= htmlspecialchars((string)$plan['code'], ENT_QUOTES) ?>" required class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <label class="block text-sm">
      <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Description') ?></span>
      <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white"><?= htmlspecialchars((string)($plan['description'] ?? ''), ENT_QUOTES) ?></textarea>
    </label>
    <div class="grid gap-4 sm:grid-cols-3">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Price') ?></span>
        <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars((string)$plan['price'], ENT_QUOTES) ?>" required class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Currency') ?></span>
        <input type="text" name="currency" maxlength="3" value="<?= htmlspecialchars((string)$plan['currency'], ENT_QUOTES) ?>" required class="mt-1 w-full uppercase rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Billing interval') ?></span>
        <select name="billing_interval" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
          <?php foreach ($intervalLabels as $key => $label): ?>
            <option value="<?= htmlspecialchars((string)$key, ENT_QUOTES) ?>" <?= ($plan['billing_interval'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Interval count') ?></span>
        <input type="number" name="interval_count" min="1" value="<?= (int)($plan['interval_count'] ?? 1) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Role assignment') ?></span>
        <select name="role_slug" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
          <?php foreach ($roleOptions as $slug => $label): ?>
            <option value="<?= htmlspecialchars((string)$slug, ENT_QUOTES) ?>" <?= ($plan['role_slug'] ?? '') === $slug ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Trial days') ?></span>
        <input type="number" name="trial_days" min="0" value="<?= $plan['trial_days'] !== null ? (int)$plan['trial_days'] : '' ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
      <input type="checkbox" name="is_active" value="1" <?= !empty($plan['is_active']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
      <span><?= __('Plan is active') ?></span>
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Stripe product ID') ?></span>
        <input type="text" name="stripe_product_id" value="<?= htmlspecialchars((string)($plan['stripe_product_id'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Stripe price ID') ?></span>
        <input type="text" name="stripe_price_id" value="<?= htmlspecialchars((string)($plan['stripe_price_id'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <div class="flex justify-end gap-3">
      <a href="/admin/billing" class="btn btn-muted inline-flex items-center gap-2">
        <i data-lucide="x" class="h-4 w-4"></i>
        <span><?= __('Cancel') ?></span>
      </a>
      <button class="btn btn-primary inline-flex items-center gap-2">
        <i data-lucide="save" class="h-4 w-4"></i>
        <span><?= $mode === 'edit' ? __('Update plan') : __('Create plan') ?></span>
      </button>
    </div>
  </form>
</section>
