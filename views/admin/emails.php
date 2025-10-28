<?php
$templates = $templates ?? [];
$locale = $locale ?? 'en';
?>

<div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
      <i data-lucide="shield" class="h-4 w-4"></i>
      <?= __('Administration') ?>
    </div>
    <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="card-title text-3xl font-semibold text-slate-900 dark:text-white">
          <?= __('Email templates') ?>
        </h1>
        <p class="card-subtle mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Preview the transactional emails available for administrators and ensure their merge fields render with sensible mock data.') ?>
        </p>
      </div>
      <div class="flex items-center gap-3 rounded-2xl border border-white/60 bg-white/70 px-4 py-3 text-xs font-medium text-slate-500 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50 dark:text-slate-300">
        <i data-lucide="languages" class="h-4 w-4"></i>
        <span><?= sprintf(__('Locale: %s'), strtoupper($locale)) ?></span>
      </div>
    </div>

    <?php if ($templates): ?>
      <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <?php foreach ($templates as $template):
          $slug = $template['slug'] ?? '';
          $href = '/admin/emails/preview?template=' . urlencode((string)$slug);
          $placeholderCount = (int)($template['placeholder_count'] ?? 0);
          $hasTestData = !empty($template['has_test_data']);
        ?>
          <a
            href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
            class="group flex flex-col justify-between rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm transition hover:border-brand-200/80 hover:bg-brand-50/60 hover:shadow-brand-glow backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40 dark:hover:border-brand-400/40 dark:hover:bg-slate-900/60"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-slate-900 transition group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-200">
                  <?= htmlspecialchars($template['name'] ?? $slug) ?>
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars($template['relative_path'] ?? $template['filename'] ?? '') ?>
                </p>
              </div>
              <span class="inline-flex items-center gap-1 rounded-full border border-brand-500/20 bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-700 transition group-hover:border-brand-500/40 group-hover:bg-brand-500/20 group-hover:text-brand-800 dark:border-brand-400/30 dark:bg-brand-500/20 dark:text-brand-200 dark:group-hover:border-brand-400/50 dark:group-hover:bg-brand-500/30">
                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                <?= __('Preview') ?>
              </span>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
              <span class="inline-flex items-center gap-1 rounded-full border border-slate-200/60 bg-slate-50/70 px-3 py-1 font-medium dark:border-slate-700 dark:bg-slate-800/70">
                <i data-lucide="code" class="h-3.5 w-3.5"></i>
                <?= sprintf(__('Placeholders: %d'), $placeholderCount) ?>
              </span>
              <span class="inline-flex items-center gap-1 rounded-full border px-3 py-1 font-medium <?= $hasTestData
                ? 'border-emerald-300/60 bg-emerald-50/70 text-emerald-700 dark:border-emerald-400/60 dark:bg-emerald-500/20 dark:text-emerald-100'
                : 'border-amber-300/60 bg-amber-50/70 text-amber-700 dark:border-amber-400/60 dark:bg-amber-500/20 dark:text-amber-100' ?>">
                <i data-lucide="database" class="h-3.5 w-3.5"></i>
                <?= $hasTestData ? __('Mock data ready') : __('Add test data comment') ?>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="mt-8 rounded-3xl border border-amber-200/70 bg-amber-50/80 p-5 text-sm text-amber-800 dark:border-amber-400/50 dark:bg-amber-500/15 dark:text-amber-100">
        <?= __('No email templates were found for this locale. Ensure the docs/email_templates directory is available.') ?>
      </div>
    <?php endif; ?>
  </section>
</div>
