<?php
$themes = $themes ?? available_themes();
$currentTheme = $currentTheme ?? current_theme_slug();
$currentMeta = $currentMeta ?? theme_meta($currentTheme) ?? [];
?>
<section class="max-w-4xl mx-auto">
  <div class="card">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="card-kicker"><?= __('Appearance') ?></div>
        <h1 class="text-2xl font-semibold mt-1"><?= __('Theme library') ?></h1>
        <?php if (!empty($currentMeta['name'])): ?>
          <p class="card-subtle mt-1">
            <?= __('Current theme: :name', ['name' => htmlspecialchars($currentMeta['name'])]) ?>
          </p>
        <?php endif; ?>
        <p class="card-subtle mt-3 text-sm text-gray-500 dark:text-gray-300">
          <?= __('Pick any palette to apply and save it instantly — no extra clicks needed.') ?>
        </p>
        <noscript>
          <p class="mt-3 text-sm text-red-600">
            <?= __('JavaScript is required for instant saving. Select a theme and use the save button below.') ?>
          </p>
        </noscript>
      </div>
      <a href="/settings" class="inline-flex items-center gap-1 text-sm font-medium text-accent">
        <span aria-hidden="true">←</span>
        <span class="hidden sm:inline"><?= __('Back to Settings') ?></span>
        <span class="sm:hidden"><?= __('Back to More') ?></span>
      </a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-4 text-sm text-red-600"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <p class="mt-4 text-sm text-brand-600"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/theme" class="mt-6 space-y-6" id="settings-theme-form">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="grid gap-4 md:grid-cols-2">
          <?php foreach ($themes as $slug => $meta):
            $isActive = $slug === $currentTheme;
            $preview = $meta['preview'] ?? [];
            $lightSwatch = $preview['light'] ?? ($meta['muted'] ?? '#f8fafc');
            $darkSwatch = $preview['dark'] ?? ($meta['deep'] ?? '#111827');
            $primary = $meta['base'] ?? '#4b966e';
            $accent = $meta['accent'] ?? $primary;
            $description = trim($meta['description'] ?? '');
          ?>
          <label class="group relative block cursor-pointer rounded-3xl border transition-all duration-200 p-5 backdrop-blur hover:-translate-y-1 focus-within:outline focus-within:outline-2 focus-within:outline-brand-300 <?= $isActive ? 'border-brand-500 bg-white/85 shadow-brand-glow dark:bg-slate-900/70' : 'border-white/60 bg-white/60 hover:border-brand-200 hover:bg-brand-50/60 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:bg-slate-800/80' ?>">
          <input type="radio" name="theme" value="<?= htmlspecialchars($slug) ?>" class="sr-only" <?= $isActive ? 'checked' : '' ?> data-theme-choice>
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
            <div class="min-w-0 space-y-2">
              <div class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-700 dark:text-brand-200">#<?= str_pad(strtoupper(str_replace('-', '', $slug)), 6, '•') ?></div>
              <div class="text-lg font-semibold text-gray-800 dark:text-gray-100 break-words"><?= htmlspecialchars($meta['name'] ?? $slug) ?></div>
              <?php if ($description !== ''): ?>
                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed"><?= htmlspecialchars($description) ?></p>
              <?php endif; ?>
            </div>
            <div class="flex flex-wrap items-center gap-3 sm:flex-nowrap sm:gap-2">
              <span class="flex h-10 w-10 items-center justify-center rounded-2xl border border-white/50 shadow-sm" style="background: <?= htmlspecialchars($lightSwatch) ?>"></span>
              <span class="flex h-10 w-10 items-center justify-center rounded-2xl border border-white/40 shadow-sm" style="background: linear-gradient(135deg, <?= htmlspecialchars($primary) ?>, <?= htmlspecialchars($accent) ?>);"></span>
              <span class="flex h-10 w-10 items-center justify-center rounded-2xl border border-white/30 shadow-sm" style="background: <?= htmlspecialchars($darkSwatch) ?>"></span>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
            <div class="rounded-2xl bg-white/80 p-3 shadow-sm">
              <div class="h-2 rounded-full mb-2" style="background: <?= htmlspecialchars($primary) ?>"></div>
              <div class="h-2 rounded-full" style="background: <?= htmlspecialchars($accent) ?>"></div>
            </div>
            <div class="rounded-2xl bg-slate-900/80 p-3 shadow-inner text-white">
              <div class="h-2 rounded-full mb-2" style="background: <?= htmlspecialchars($accent) ?>"></div>
              <div class="h-2 rounded-full" style="background: <?= htmlspecialchars($primary) ?>"></div>
            </div>
          </div>
          <div class="absolute top-4 right-4">
            <span class="chip transition <?= $isActive ? '' : 'opacity-0 group-hover:opacity-100' ?>"><?= __('Selected') ?></span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <div class="flex items-center justify-end" data-theme-submit-row>
        <button class="btn btn-primary px-6"><?= __('Save theme') ?></button>
      </div>
    </form>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('settings-theme-form');
    if (!form) {
      return;
    }

    const manualRow = form.querySelector('[data-theme-submit-row]');
    if (manualRow) {
      manualRow.setAttribute('hidden', 'hidden');
      manualRow.classList.add('hidden');
      manualRow.setAttribute('aria-hidden', 'true');
    }

    const radios = form.querySelectorAll('[data-theme-choice]');
    let submitting = false;

    radios.forEach((radio) => {
      radio.addEventListener('change', () => {
        if (!radio.checked) {
          return;
        }

        if (window.MyMoneyMapApplyTheme) {
          window.MyMoneyMapApplyTheme(radio.value);
        }

        if (submitting) {
          return;
        }

        submitting = true;

        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    });
  });
</script>
