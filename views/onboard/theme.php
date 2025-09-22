<?php
$themes = $themes ?? available_themes();
$currentTheme = $currentTheme ?? current_theme_slug();
?>
<section class="max-w-3xl mx-auto card">
  <div class="flex items-center justify-between gap-4 mb-4">
    <div>
      <div class="card-kicker"><?= __('Appearance') ?></div>
      <h1 class="text-2xl font-semibold mt-1"><?= __('Choose your vibe') ?></h1>
      <p class="card-subtle mt-2">
        <?= __('Pick a theme palette to personalize your dashboard. You can switch themes anytime in Settings.') ?>
      </p>
    </div>
    <a href="/logout" class="text-sm text-gray-400 hover:text-gray-500"><?= __('Exit') ?></a>
  </div>

  <form method="post" action="/onboard/theme" class="mt-6 space-y-6" id="onboard-theme-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($themes as $slug => $meta):
          $isActive = $slug === $currentTheme;
          $preview = $meta['preview'] ?? [];
          $lightSwatch = $preview['light'] ?? ($meta['muted'] ?? '#f8fafc');
          $darkSwatch = $preview['dark'] ?? ($meta['deep'] ?? '#111827');
          $primary = $meta['base'] ?? '#4b966e';
          $accent = $meta['accent'] ?? $primary;
          $description = trim($meta['description'] ?? '');
        ?>
        <label class="group relative block cursor-pointer rounded-3xl border transition-all duration-200 p-5 backdrop-blur hover:-translate-y-1 <?= $isActive ? 'border-brand-500 bg-white/85 shadow-brand-glow dark:bg-slate-900/70' : 'border-white/60 bg-white/60 hover:border-brand-200 hover:bg-brand-50/60 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:bg-slate-800/80' ?>">
        <input type="radio" name="theme" value="<?= htmlspecialchars($slug) ?>" class="sr-only" <?= $isActive ? 'checked' : '' ?> data-theme-choice>
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-700 dark:text-brand-200">#<?= str_pad(strtoupper(str_replace('-', '', $slug)), 6, '•') ?></div>
            <div class="mt-2 text-lg font-semibold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($meta['name'] ?? $slug) ?></div>
            <?php if ($description !== ''): ?>
              <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed"><?= htmlspecialchars($description) ?></p>
            <?php endif; ?>
          </div>
          <div class="flex-shrink-0">
            <div class="flex items-center gap-2">
              <span class="h-10 w-10 rounded-2xl border border-white/50 shadow-sm" style="background: <?= htmlspecialchars($lightSwatch) ?>"></span>
              <span class="h-10 w-10 rounded-2xl border border-white/40 shadow-sm" style="background: linear-gradient(135deg, <?= htmlspecialchars($primary) ?>, <?= htmlspecialchars($accent) ?>);"></span>
              <span class="h-10 w-10 rounded-2xl border border-white/30 shadow-sm" style="background: <?= htmlspecialchars($darkSwatch) ?>"></span>
            </div>
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

    <div class="flex items-center justify-end">
      <button class="btn btn-primary px-6"><?= __('Continue →') ?></button>
    </div>
  </form>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const radios = document.querySelectorAll('[data-theme-choice]');
    radios.forEach((radio) => {
      radio.addEventListener('change', () => {
        if (radio.checked && window.MyMoneyMapApplyTheme) {
          window.MyMoneyMapApplyTheme(radio.value);
        }
      });
    });
  });
</script>
