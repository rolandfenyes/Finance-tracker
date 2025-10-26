<?php
$role = $role ?? [];
$fields = $fields ?? [];
$mode = $mode ?? 'create';
$isEdit = $mode === 'edit';
$isSystem = !empty($role['is_system']);
$action = $isEdit ? '/admin/roles/update' : '/admin/roles';
?>
<section class="mx-auto max-w-3xl space-y-6">
  <div>
    <a href="/admin/roles" class="inline-flex items-center gap-1 text-sm font-medium text-accent">
      <span aria-hidden="true">←</span>
      <span><?= __('Back to roles') ?></span>
    </a>
    <h1 class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white">
      <?= $isEdit ? __('Edit role') : __('Create role') ?>
    </h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
      <?= __('Define the name, slug, and capabilities available to accounts assigned to this role.') ?>
    </p>
  </div>

  <form action="<?= $action ?>" method="post" class="space-y-6 rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <?php if ($isEdit): ?>
      <input type="hidden" name="role_id" value="<?= (int)($role['id'] ?? 0) ?>" />
    <?php endif; ?>

    <div class="grid gap-5 sm:grid-cols-2">
      <div class="sm:col-span-1">
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="role-name">
          <?= __('Role name') ?>
        </label>
        <input id="role-name" name="name" class="input mt-1" required value="<?= htmlspecialchars((string)($role['name'] ?? '')) ?>" />
      </div>
      <div class="sm:col-span-1">
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="role-slug">
          <?= __('Role slug') ?>
        </label>
        <input
          id="role-slug"
          name="slug"
          class="input mt-1"
          value="<?= htmlspecialchars((string)($role['slug'] ?? '')) ?>"
          <?= $isSystem ? 'disabled' : 'required pattern="[a-z0-9_-]+"' ?>
        />
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
          <?= __('Lowercase characters, numbers, hyphens, and underscores only.') ?>
        </p>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="role-description">
          <?= __('Description') ?>
        </label>
        <textarea id="role-description" name="description" rows="3" class="input mt-1" placeholder="<?= __('Optional summary shown to administrators.') ?>"><?= htmlspecialchars((string)($role['description'] ?? '')) ?></textarea>
      </div>
    </div>

    <div>
      <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
        <?= __('Role capabilities') ?>
      </h2>
      <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
        <?= __('Control access limits and feature flags assigned to this role.') ?>
      </p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <?php foreach ($fields as $key => $field): ?>
          <?php
            $type = $field['type'] ?? 'number';
            $label = $field['label'] ?? $key;
            $help = $field['help'] ?? null;
            $value = $role['capabilities'][$key] ?? null;
          ?>
          <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-4 dark:border-slate-800/70 dark:bg-slate-900/50">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="cap-<?= htmlspecialchars($key) ?>">
              <?= htmlspecialchars($label) ?>
            </label>
            <?php if ($type === 'boolean'): ?>
              <div class="mt-3 flex items-center gap-2">
                <input type="checkbox" id="cap-<?= htmlspecialchars($key) ?>" name="capabilities[<?= htmlspecialchars($key) ?>]" class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-400" <?= $value ? 'checked' : '' ?> />
                <span class="text-sm text-slate-700 dark:text-slate-200">
                  <?= __('Enabled') ?>
                </span>
              </div>
            <?php else: ?>
              <input
                type="number"
                min="0"
                id="cap-<?= htmlspecialchars($key) ?>"
                name="capabilities[<?= htmlspecialchars($key) ?>]"
                class="input mt-2"
                value="<?= $value === null ? '' : (int)$value ?>"
                placeholder="<?= __('Unlimited') ?>"
              />
            <?php endif; ?>
            <?php if ($help): ?>
              <p class="mt-2 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($help) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <button class="btn btn-primary inline-flex items-center gap-2">
        <i data-lucide="save" class="h-4 w-4"></i>
        <span><?= $isEdit ? __('Update role') : __('Create role') ?></span>
      </button>
      <a href="/admin/roles" class="btn btn-muted inline-flex items-center gap-2">
        <i data-lucide="x" class="h-4 w-4"></i>
        <span><?= __('Cancel') ?></span>
      </a>
    </div>
  </form>
</section>
