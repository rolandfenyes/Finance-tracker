<?php
$themes = $themes ?? available_themes();
$currentTheme = $currentTheme ?? current_theme_slug();
?>

<?php include __DIR__ . '/_progress.php'; ?>

<section class="max-w-6xl mx-auto space-y-8">
  <div class="card flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
    <div class="max-w-xl">
      <div class="card-kicker"><?= __('Make it yours') ?></div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
        <?= __('Choose your vibe') ?>
      </h1>
      <p class="mt-3 text-base text-gray-600 dark:text-gray-300 leading-relaxed">
        <?= __('Preview a few gorgeous palettes and pick the one that matches your money personality. You can switch themes anytime from Settings—this is just your day-one look.') ?>
      </p>
      <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Live preview instantly updates the UI as you select options.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="palette" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Each theme ships with curated accents for charts, chips, and cards.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="repeat" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Not feeling it later? Change themes with one click in Settings → Appearance.') ?></span>
        </li>
      </ul>
    </div>
    <div class="self-stretch rounded-3xl border border-dashed border-brand-200/60 bg-brand-50/40 p-4 text-sm text-brand-700 shadow-inner dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
      <p class="font-semibold uppercase tracking-[0.22em] text-xs text-brand-500 dark:text-brand-200"><?= __('Quick tip') ?></p>
      <p class="mt-2 leading-relaxed">
        <?= __('Themes also update your charts, icons, and gradients so everything feels cohesive. Pick the palette that motivates you to stay consistent!') ?>
      </p>
      <a href="/logout" class="mt-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-700 hover:text-brand-800 dark:text-brand-200">
        <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
        <?= __('Exit setup') ?>
      </a>
    </div>
  </div>

  <form method="post" action="/onboard/theme" class="card space-y-6" id="onboard-theme-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

    <div class="flex items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= __('Pick a color story') ?></h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          <?= __('Tap a card to preview it across the interface, then hit continue.') ?>
        </p>
      </div>
      <a href="/onboard/next" class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white">
        <?= __('I’ll keep the current theme') ?>
      </a>
    </div>

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

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
      <a href="/onboard/next" class="btn btn-ghost order-2 sm:order-1">
        <?= __('Skip for now') ?>
      </a>
      <button class="btn btn-primary px-6 order-1 sm:order-2">
        <?= __('Continue →') ?>
      </button>
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
