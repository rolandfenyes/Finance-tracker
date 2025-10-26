<?php
$roles = $roles ?? [];
$fields = $fields ?? [];
?>
<section class="mx-auto max-w-6xl space-y-6">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-white"><?= __('Role management') ?></h1>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
        <?= __('Review each role, adjust capabilities, and keep your plans organised.') ?>
      </p>
    </div>
    <a href="/admin/roles/create" class="btn btn-primary inline-flex items-center gap-2">
      <i data-lucide="plus" class="h-4 w-4"></i>
      <span><?= __('Add role') ?></span>
    </a>
  </div>

  <?php if (!$roles): ?>
    <div class="rounded-3xl border border-slate-200/70 bg-white/70 p-6 text-sm text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
      <?= __('No roles have been configured yet.') ?>
    </div>
  <?php endif; ?>

  <div class="grid gap-6 lg:grid-cols-2">
    <?php foreach ($roles as $role): ?>
      <?php
        $isSystem = !empty($role['is_system']);
        $capabilities = $role['capabilities'] ?? [];
      ?>
      <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                <?= htmlspecialchars((string)$role['name']) ?>
              </h2>
              <?php if ($isSystem): ?>
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                  <?= __('System role') ?>
                </span>
              <?php else: ?>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700/60 dark:text-slate-200">
                  <?= __('Custom role') ?>
                </span>
              <?php endif; ?>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              <?= __('Slug') ?>: <span class="font-mono text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)$role['slug']) ?></span>
            </p>
            <?php if (!empty($role['description'])): ?>
              <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                <?= nl2br(htmlspecialchars((string)$role['description'])) ?>
              </p>
            <?php endif; ?>
          </div>
          <div class="text-right text-sm text-slate-500 dark:text-slate-400">
            <div><?= __('Assigned users') ?>: <strong class="text-slate-700 dark:text-slate-200"><?= (int)$role['user_count'] ?></strong></div>
            <?php if (!empty($role['updated_at'])): ?>
              <div><?= __('Last updated') ?>: <?= date('Y-m-d', strtotime((string)$role['updated_at'])) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
          <?php foreach ($fields as $key => $field): ?>
            <?php
              $type = $field['type'] ?? 'number';
              $value = $capabilities[$key] ?? null;
              if ($type === 'boolean') {
                $display = $value ? __('Yes') : __('No');
              } elseif ($value === null) {
                $display = __('Unlimited');
              } else {
                $display = (string)(int)$value;
              }
            ?>
            <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 dark:border-slate-800/70 dark:bg-slate-900/50">
              <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars((string)($field['label'] ?? $key)) ?>
              </dt>
              <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                <?= htmlspecialchars($display) ?>
              </dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <div class="mt-6 flex flex-wrap items-center gap-3">
          <a href="/admin/roles/edit?id=<?= (int)$role['id'] ?>" class="btn btn-muted inline-flex items-center gap-2">
            <i data-lucide="pencil" class="h-4 w-4"></i>
            <span><?= __('Edit role') ?></span>
          </a>
          <?php if (!$isSystem): ?>
            <form action="/admin/roles/delete" method="post" class="inline-flex" onsubmit="return confirm('<?= __('Are you sure you want to delete this role?') ?>');">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>" />
              <button class="btn btn-danger inline-flex items-center gap-2">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
                <span><?= __('Delete role') ?></span>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
