<?php
$settings = $settings ?? system_settings();
$integrations = $integrations ?? [];
$templates = $templates ?? [];
$notificationChannels = $notificationChannels ?? [];
$environment = $environment ?? [];
$statusOptions = [
    'active' => __('Active'),
    'inactive' => __('Inactive'),
    'revoked' => __('Revoked'),
];
$currentPath = htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/admin/system', PHP_URL_PATH) ?? '/admin/system', ENT_QUOTES);
?>
<section class="mx-auto max-w-7xl space-y-10">
  <header class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
    <div class="space-y-2">
      <h1 class="text-3xl font-semibold text-slate-900 dark:text-white"><?= __('System & configuration') ?></h1>
      <p class="max-w-2xl text-sm text-slate-600 dark:text-slate-400">
        <?= __('Centralize global platform settings, infrastructure integrations, and operational controls.') ?>
      </p>
    </div>
    <div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Environment') ?></div>
        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars((string)($environment['environment'] ?? 'production')) ?>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
          <?= ($environment['debug'] ?? false) ? __('Debug enabled') : __('Debug disabled') ?>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Version') ?></div>
        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars((string)($environment['version'] ?? '1.0.0')) ?>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
          <?= __('Last migration') ?>:
          <?= htmlspecialchars((string)($environment['last_migration'] ?? __('Not available'))) ?>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Maintenance') ?></div>
        <div class="mt-1 inline-flex items-center gap-2 text-lg font-semibold <?= !empty($environment['maintenance_mode']) ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300' ?>">
          <i data-lucide="<?= !empty($environment['maintenance_mode']) ? 'alert-triangle' : 'check-circle' ?>" class="h-4 w-4"></i>
          <span><?= !empty($environment['maintenance_mode']) ? __('Enabled') : __('Disabled') ?></span>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
          <?= __('Timezone') ?>: <?= htmlspecialchars((string)($environment['timezone'] ?? date_default_timezone_get())) ?>
        </div>
      </div>
    </div>
  </header>

  <section class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Global settings') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Configure the application identity, support contacts, and maintenance banner.') ?>
        </p>
      </div>
    </div>
    <form action="/admin/system/settings" method="post" class="mt-6 grid gap-4 lg:grid-cols-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="redirect" value="<?= $currentPath ?>">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Site name') ?></span>
        <input type="text" name="site_name" value="<?= htmlspecialchars((string)($settings['site_name'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Primary URL') ?></span>
        <input type="url" name="primary_url" value="<?= htmlspecialchars((string)($settings['primary_url'] ?? ''), ENT_QUOTES) ?>" placeholder="https://app.example.com" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Support email') ?></span>
        <input type="email" name="support_email" value="<?= htmlspecialchars((string)($settings['support_email'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Contact email') ?></span>
        <input type="email" name="contact_email" value="<?= htmlspecialchars((string)($settings['contact_email'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Logo URL') ?></span>
        <input type="url" name="logo_url" value="<?= htmlspecialchars((string)($settings['logo_url'] ?? ''), ENT_QUOTES) ?>" placeholder="https://cdn.example.com/logo.svg" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Favicon URL') ?></span>
        <input type="url" name="favicon_url" value="<?= htmlspecialchars((string)($settings['favicon_url'] ?? ''), ENT_QUOTES) ?>" placeholder="https://cdn.example.com/favicon.ico" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/70 px-4 py-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200">
        <input type="checkbox" name="maintenance_mode" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>>
        <div>
          <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Enable maintenance mode') ?></span>
          <p class="text-xs text-slate-500 dark:text-slate-400"><?= __('Display a maintenance banner across the user experience.') ?></p>
        </div>
      </label>
      <label class="block text-sm lg:col-span-2">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Maintenance message') ?></span>
        <textarea name="maintenance_message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" placeholder="<?= htmlspecialchars(__('We’ll be back shortly. Thanks for your patience!')) ?>"><?= htmlspecialchars((string)($settings['maintenance_message'] ?? ''), ENT_QUOTES) ?></textarea>
      </label>
      <div class="lg:col-span-2 flex justify-end">
        <button class="btn btn-primary inline-flex items-center gap-2">
          <i data-lucide="save" class="h-4 w-4"></i>
          <span><?= __('Save settings') ?></span>
        </button>
      </div>
    </form>
  </section>

  <section class="space-y-6" id="integrations">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('API integrations') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Manage external services, rotate API keys, and annotate metadata for operational context.') ?>
        </p>
      </div>
    </div>
    <?php if (!$integrations): ?>
      <div class="rounded-3xl border border-dashed border-slate-200/70 bg-white/60 p-6 text-sm text-slate-500 backdrop-blur dark:border-slate-700/70 dark:bg-slate-900/50 dark:text-slate-300">
        <?= __('No integrations connected yet. Create one below to begin tracking credentials.') ?>
      </div>
    <?php endif; ?>
    <div class="grid gap-6 lg:grid-cols-2">
      <?php foreach ($integrations as $integration): ?>
        <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
          <form action="/admin/system/api/save" method="post" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="redirect" value="<?= $currentPath ?>#integrations">
            <input type="hidden" name="id" value="<?= (int)($integration['id'] ?? 0) ?>">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string)($integration['name'] ?? '')) ?></h3>
                <?php if (!empty($integration['service'])): ?>
                  <p class="text-xs text-slate-500 dark:text-slate-400"><?= __('Service') ?>: <span class="font-mono text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)$integration['service']) ?></span></p>
                <?php endif; ?>
              </div>
              <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
                <i data-lucide="key" class="h-3.5 w-3.5"></i>
                <?= htmlspecialchars((string)($integration['api_key_masked'] ?? '')) ?>
              </span>
            </div>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Integration name') ?></span>
              <input type="text" name="name" value="<?= htmlspecialchars((string)($integration['name'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Service identifier') ?></span>
              <input type="text" name="service" value="<?= htmlspecialchars((string)($integration['service'] ?? ''), ENT_QUOTES) ?>" placeholder="stripe" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('API key') ?></span>
              <input type="text" name="api_key" value="<?= htmlspecialchars((string)($integration['api_key'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Status') ?></span>
              <select name="status" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= strtolower((string)($integration['status'] ?? 'active')) === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Metadata (JSON)') ?></span>
              <textarea name="metadata" rows="4" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" placeholder="<?= htmlspecialchars(json_encode(['team' => 'platform'], JSON_UNESCAPED_SLASHES)) ?>"><?= htmlspecialchars((string)($integration['metadata_raw'] ?? ''), ENT_QUOTES) ?></textarea>
            </label>
            <div class="flex items-center justify-between">
              <button class="btn btn-primary inline-flex items-center gap-2">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span><?= __('Save integration') ?></span>
              </button>
              <button type="submit" form="delete-integration-<?= (int)($integration['id'] ?? 0) ?>" class="btn btn-ghost inline-flex items-center gap-2 text-sm text-rose-600 hover:text-rose-500 dark:text-rose-300">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
                <span><?= __('Delete') ?></span>
              </button>
            </div>
          </form>
          <form id="delete-integration-<?= (int)($integration['id'] ?? 0) ?>" action="/admin/system/api/delete" method="post" class="hidden">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="redirect" value="<?= $currentPath ?>#integrations">
            <input type="hidden" name="id" value="<?= (int)($integration['id'] ?? 0) ?>">
          </form>
        </article>
      <?php endforeach; ?>
      <article class="rounded-3xl border border-dashed border-slate-200/70 bg-white/60 p-6 shadow-sm backdrop-blur dark:border-slate-700/70 dark:bg-slate-900/50">
        <form action="/admin/system/api/save" method="post" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="redirect" value="<?= $currentPath ?>#integrations">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Add integration') ?></h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            <?= __('Store credentials securely and reference usage with custom metadata.') ?>
          </p>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Integration name') ?></span>
            <input type="text" name="name" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Service identifier') ?></span>
            <input type="text" name="service" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('API key') ?></span>
            <input type="text" name="api_key" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Status') ?></span>
            <select name="status" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Metadata (JSON)') ?></span>
            <textarea name="metadata" rows="4" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" placeholder='{"team":"growth"}'></textarea>
          </label>
          <button class="btn btn-primary inline-flex items-center gap-2">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span><?= __('Create integration') ?></span>
          </button>
        </form>
      </article>
    </div>
  </section>

  <section class="space-y-6" id="email-templates">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Email templates') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Edit transactional messaging and send instant previews to verify changes.') ?>
        </p>
      </div>
    </div>
    <div class="space-y-6">
      <?php foreach ($templates as $template): ?>
        <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
          <form action="/admin/system/email/save" method="post" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="redirect" value="<?= $currentPath ?>#email-templates">
            <input type="hidden" name="id" value="<?= (int)($template['id'] ?? 0) ?>">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string)($template['name'] ?? '')) ?></h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  <?= __('Code') ?>: <span class="font-mono text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($template['code'] ?? '')) ?></span>
                  · <?= __('Locale') ?>: <span class="font-mono text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($template['locale'] ?? 'en')) ?></span>
                </p>
              </div>
              <?php if (!empty($template['last_tested_at'])): ?>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  <?= __('Last test') ?>:
                  <?= htmlspecialchars((string)$template['last_tested_at']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
              <label class="block text-sm">
                <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Template code') ?></span>
                <input type="text" name="code" value="<?= htmlspecialchars((string)($template['code'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
              </label>
              <label class="block text-sm">
                <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Display name') ?></span>
                <input type="text" name="name" value="<?= htmlspecialchars((string)($template['name'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
              </label>
              <label class="block text-sm">
                <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Locale') ?></span>
                <input type="text" name="locale" value="<?= htmlspecialchars((string)($template['locale'] ?? 'en'), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              </label>
              <label class="block text-sm">
                <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Subject line') ?></span>
                <input type="text" name="subject" value="<?= htmlspecialchars((string)($template['subject'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
              </label>
            </div>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Body') ?></span>
              <textarea name="body" rows="6" class="mt-1 w-full rounded-2xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required><?= htmlspecialchars((string)($template['body'] ?? ''), ENT_QUOTES) ?></textarea>
            </label>
            <div class="flex justify-end">
              <button class="btn btn-primary inline-flex items-center gap-2">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span><?= __('Save template') ?></span>
              </button>
            </div>
          </form>
          <form action="/admin/system/email/test" method="post" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="redirect" value="<?= $currentPath ?>#email-templates">
            <input type="hidden" name="template_id" value="<?= (int)($template['id'] ?? 0) ?>">
            <input type="email" name="test_email" placeholder="admin@example.com" class="w-48 rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            <button class="btn btn-secondary inline-flex items-center gap-2">
              <i data-lucide="send" class="h-4 w-4"></i>
              <span><?= __('Send test email') ?></span>
            </button>
          </form>
        </article>
      <?php endforeach; ?>
      <article class="rounded-3xl border border-dashed border-slate-200/70 bg-white/60 p-6 shadow-sm backdrop-blur dark:border-slate-700/70 dark:bg-slate-900/50">
        <form action="/admin/system/email/save" method="post" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="redirect" value="<?= $currentPath ?>#email-templates">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Create template') ?></h3>
          <div class="grid gap-4 lg:grid-cols-2">
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Template code') ?></span>
              <input type="text" name="code" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Display name') ?></span>
              <input type="text" name="name" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Locale') ?></span>
              <input type="text" name="locale" value="en" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
            </label>
            <label class="block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Subject line') ?></span>
              <input type="text" name="subject" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            </label>
          </div>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Body') ?></span>
            <textarea name="body" rows="6" class="mt-1 w-full rounded-2xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required></textarea>
          </label>
          <button class="btn btn-primary inline-flex items-center gap-2">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span><?= __('Save template') ?></span>
          </button>
        </form>
      </article>
    </div>
  </section>

  <section class="space-y-6" id="notifications">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Notification settings') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Tune delivery channels, toggle alerts, and configure providers per channel.') ?>
        </p>
      </div>
    </div>
    <form action="/admin/system/notifications/save" method="post" class="space-y-4">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="redirect" value="<?= $currentPath ?>#notifications">
      <div class="grid gap-4 lg:grid-cols-2">
        <?php foreach ($notificationChannels as $channel): ?>
          <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
            <input type="hidden" name="channels[<?= (int)($channel['id'] ?? 0) ?>][id]" value="<?= (int)($channel['id'] ?? 0) ?>">
            <input type="hidden" name="channels[<?= (int)($channel['id'] ?? 0) ?>][channel]" value="<?= htmlspecialchars((string)($channel['channel'] ?? ''), ENT_QUOTES) ?>">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Channel') ?></div>
                <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string)($channel['name'] ?? '')) ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  <?= __('Identifier') ?>: <span class="font-mono text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($channel['channel'] ?? '')) ?></span>
                </div>
              </div>
              <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="channels[<?= (int)($channel['id'] ?? 0) ?>][enabled]" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= !empty($channel['is_enabled']) ? 'checked' : '' ?>>
                <span><?= __('Enabled') ?></span>
              </label>
            </div>
            <label class="mt-4 block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Display name') ?></span>
              <input type="text" name="channels[<?= (int)($channel['id'] ?? 0) ?>][name]" value="<?= htmlspecialchars((string)($channel['name'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
            </label>
            <label class="mt-4 block text-sm">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Configuration (JSON)') ?></span>
              <textarea name="channels[<?= (int)($channel['id'] ?? 0) ?>][config]" rows="4" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white"><?= htmlspecialchars((string)($channel['config_raw'] ?? ''), ENT_QUOTES) ?></textarea>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="flex justify-end">
        <button class="btn btn-primary inline-flex items-center gap-2">
          <i data-lucide="save" class="h-4 w-4"></i>
          <span><?= __('Save notification settings') ?></span>
        </button>
      </div>
    </form>
    <article class="rounded-3xl border border-dashed border-slate-200/70 bg-white/60 p-6 shadow-sm backdrop-blur dark:border-slate-700/70 dark:bg-slate-900/50">
      <form action="/admin/system/notifications/add" method="post" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="redirect" value="<?= $currentPath ?>#notifications">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Add notification channel') ?></h3>
        <div class="grid gap-4 lg:grid-cols-2">
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Identifier') ?></span>
            <input type="text" name="channel" placeholder="webhook" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Display name') ?></span>
            <input type="text" name="name" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" required>
          </label>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
          <input type="checkbox" name="enabled" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" checked>
          <span><?= __('Enabled') ?></span>
        </label>
        <label class="block text-sm">
          <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Configuration (JSON)') ?></span>
          <textarea name="config" rows="4" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white" placeholder='{"provider":"custom"}'></textarea>
        </label>
        <button class="btn btn-primary inline-flex items-center gap-2">
          <i data-lucide="plus" class="h-4 w-4"></i>
          <span><?= __('Add channel') ?></span>
        </button>
      </form>
    </article>
  </section>

  <section class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Environment overview') ?></h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
      <?= __('Review runtime and infrastructure metadata for quick diagnostics.') ?>
    </p>
    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Application name') ?></dt>
        <dd class="text-sm text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($environment['app_name'] ?? 'MyMoneyMap')) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Application URL') ?></dt>
        <dd class="text-sm text-slate-700 dark:text-slate-200">
          <?php if (!empty($environment['app_url'])): ?>
            <a href="<?= htmlspecialchars((string)$environment['app_url'], ENT_QUOTES) ?>" class="text-emerald-600 hover:text-emerald-500 dark:text-emerald-300" target="_blank" rel="noopener">
              <?= htmlspecialchars((string)$environment['app_url']) ?>
            </a>
          <?php else: ?>
            <?= __('Not configured') ?>
          <?php endif; ?>
        </dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('PHP version') ?></dt>
        <dd class="text-sm text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($environment['php_version'] ?? PHP_VERSION)) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Database version') ?></dt>
        <dd class="text-sm text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($environment['database_version'] ?? __('Not available'))) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Server') ?></dt>
        <dd class="text-sm text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)($environment['server'] ?? php_uname())) ?></dd>
      </div>
    </dl>
  </section>
</section>
