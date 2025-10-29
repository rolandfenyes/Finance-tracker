<?php
$template = $template ?? [];
$renderedHtml = $renderedHtml ?? '';
$mockData = $mockData ?? [];
$placeholders = $placeholders ?? [];
$missingPlaceholders = $missingPlaceholders ?? [];
$locale = $locale ?? 'en';
?>

<div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
      <i data-lucide="shield" class="h-4 w-4"></i>
      <?= __('Administration') ?>
    </div>
    <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="space-y-2">
        <p class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Email preview') ?>
        </p>
        <h1 class="card-title text-3xl font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars($template['name'] ?? __('Email template')) ?>
        </h1>
        <p class="card-subtle max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Review how this template renders when populated with representative data. Missing placeholders are highlighted so you can update the mock payload as needed.') ?>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <a
          href="/admin/emails"
          class="btn btn-secondary inline-flex items-center gap-2"
        >
          <i data-lucide="arrow-left" class="h-4 w-4"></i>
          <?= __('Back to list') ?>
        </a>
        <div class="flex items-center gap-2 rounded-2xl border border-white/60 bg-white/70 px-4 py-2 text-xs font-medium text-slate-500 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50 dark:text-slate-300">
          <i data-lucide="languages" class="h-4 w-4"></i>
          <span><?= sprintf(__('Locale: %s'), strtoupper($locale)) ?></span>
        </div>
      </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-12">
      <div class="space-y-6 lg:col-span-4">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <?= __('Template details') ?>
          </h2>
          <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div>
              <dt class="font-medium text-slate-500 dark:text-slate-400"><?= __('Filename') ?></dt>
              <dd class="mt-1 break-all font-mono text-xs">
                <?= htmlspecialchars($template['relative_path'] ?? $template['filename'] ?? '') ?>
              </dd>
            </div>
            <div>
              <dt class="font-medium text-slate-500 dark:text-slate-400"><?= __('Placeholders') ?></dt>
              <dd class="mt-1">
                <?= number_format((int)($template['placeholder_count'] ?? count($placeholders))) ?>
              </dd>
            </div>
            <div>
              <dt class="font-medium text-slate-500 dark:text-slate-400"><?= __('Mock data status') ?></dt>
              <dd class="mt-1">
                <?php if (!empty($template['has_test_data'])): ?>
                  <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/60 bg-emerald-50/70 px-3 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/60 dark:bg-emerald-500/20 dark:text-emerald-100">
                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                    <?= __('Includes inline test data comment') ?>
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center gap-2 rounded-full border border-amber-300/60 bg-amber-50/70 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-400/60 dark:bg-amber-500/20 dark:text-amber-100">
                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                    <?= __('No inline test data comment detected') ?>
                  </span>
                <?php endif; ?>
              </dd>
            </div>
          </dl>

          <?php if ($missingPlaceholders): ?>
            <div class="mt-5 rounded-2xl border border-rose-300/60 bg-rose-50/80 p-4 text-xs text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/20 dark:text-rose-100">
              <p class="font-semibold uppercase tracking-wide">
                <?= __('Missing placeholder values') ?>
              </p>
              <ul class="mt-2 space-y-1 font-mono">
                <?php foreach ($missingPlaceholders as $missing): ?>
                  <li><?= htmlspecialchars($missing) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>

        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <?= __('Mock data payload') ?>
          </h2>
          <?php if ($mockData): ?>
            <dl class="mt-4 space-y-3 text-xs text-slate-600 dark:text-slate-300">
              <?php foreach ($mockData as $key => $value): ?>
                <?php $displayValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$value; ?>
                <div>
                  <dt class="font-semibold text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string)$key) ?></dt>
                  <dd class="mt-1 break-all font-mono">
                    <?= htmlspecialchars($displayValue) ?>
                  </dd>
                </div>
              <?php endforeach; ?>
            </dl>
          <?php else: ?>
            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
              <?= __('No mock data was applied to this template.') ?>
            </p>
          <?php endif; ?>
        </div>

        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <?= __('Available placeholders') ?>
          </h2>
          <?php if ($placeholders): ?>
            <div class="mt-4 flex flex-wrap gap-2">
              <?php foreach ($placeholders as $key): ?>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200/60 bg-slate-50/70 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">
                  <i data-lucide="variable" class="h-3.5 w-3.5"></i>
                  <?= htmlspecialchars($key) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
              <?= __('No merge tags were detected in this template.') ?>
            </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="lg:col-span-8">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <?= __('Rendered preview') ?>
          </h2>
          <span class="text-xs text-slate-500 dark:text-slate-400">
            <?= __('Interactive elements are disabled in preview mode.') ?>
          </span>
        </div>
        <div class="mt-3 overflow-hidden rounded-3xl border border-white/60 bg-slate-50/70 shadow-lg backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <iframe
            class="h-[720px] w-full bg-white"
            srcdoc="<?= htmlspecialchars($renderedHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            sandbox="allow-same-origin"
          ></iframe>
        </div>
      </div>
    </div>
  </section>
</div>
