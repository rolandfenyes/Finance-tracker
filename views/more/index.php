<?php
/** @var array $user */
/** @var string $displayName */
/** @var array $navSections */
/** @var array $localeOptions */
/** @var string $currentLocale */
/** @var array $localeFlags */

$email = trim($user['email'] ?? '');
$firstSource = $displayName ?: $email;
$firstChar = '';
if ($firstSource !== '') {
    $firstChar = function_exists('mb_substr') ? mb_substr($firstSource, 0, 1) : substr($firstSource, 0, 1);
}
$initial = strtoupper($firstChar ?: '?');
$localeOptions = $localeOptions ?? [];
$currentLocale = $currentLocale ?? app_locale();
$localeFlags = $localeFlags ?? [];
?>

<div class="mx-auto w-full max-w-3xl space-y-6 px-4 pb-28 pt-6 sm:px-6 lg:px-0">
  <div class="flex justify-center">
    <span class="inline-flex h-16 w-16 items-center justify-center rounded-3xl border border-white/60 bg-white/80 p-3 shadow-glass backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
      <img src="/logo.png" alt="App logo" class="h-full w-full object-contain" />
    </span>
  </div>

  <section class="card">
    <div class="card-kicker text-center sm:text-left"><?= __('Signed in as') ?></div>
    <div class="mt-4 flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:text-left">
      <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-brand-400/90 to-brand-600/90 text-2xl font-semibold tracking-tight text-white shadow-brand-glow">
        <?= htmlspecialchars($initial) ?>
      </div>
      <div class="sm:flex-1">
        <p class="text-xl font-semibold text-slate-900 dark:text-white sm:text-2xl"><?= htmlspecialchars($displayName) ?></p>
        <?php if ($email && strtolower($email) !== strtolower($displayName)): ?>
          <p class="mt-1 break-all text-sm text-slate-500 dark:text-slate-300/80"><?= htmlspecialchars($email) ?></p>
        <?php endif; ?>
      </div>
      <div class="w-full sm:w-auto">
        <a href="/settings/profile" class="btn btn-muted flex w-full items-center justify-center gap-2 whitespace-nowrap px-4 py-2 text-sm font-semibold sm:w-auto">
          <i data-lucide="pencil" class="h-4 w-4"></i>
          <?= __('Edit profile') ?>
        </a>
      </div>
    </div>
  </section>

  <section
    class="card"
    x-data="{
      mode: document.documentElement.dataset.theme || (document.documentElement.classList.contains('dark') ? 'dark' : 'light'),
      init() {
        const controller = window.MyMoneyMapThemeController;
        if (controller && typeof controller.current === 'function') {
          this.mode = controller.current();
        }
        document.addEventListener('themechange', (event) => {
          if (event && event.detail && event.detail.theme) {
            this.mode = event.detail.theme;
          }
        });
      },
      set(mode) {
        const controller = window.MyMoneyMapThemeController;
        if (controller && typeof controller.apply === 'function') {
          controller.apply(mode);
        } else {
          document.dispatchEvent(new CustomEvent('mymoneymap:set-theme', { detail: { theme: mode } }));
        }
        this.mode = mode;
      }
    }"
    role="group"
    aria-labelledby="more-theme-heading"
  >
    <div class="card-kicker"><?= __('Appearance') ?></div>
    <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 id="more-theme-heading" class="card-title"><?= __('Theme') ?></h2>
        <p class="card-subtle mt-1 text-sm"><?= __('Choose the look that feels most comfortable for your eyes.') ?></p>
      </div>
    </div>
    <div class="mt-6 grid gap-3 sm:grid-cols-2">
      <button
        type="button"
        class="flex items-center justify-between rounded-full border px-4 py-3 text-left text-sm font-semibold transition sm:text-base"
        :class="mode === 'light' ? 'border-brand-500/80 bg-brand-50/80 text-brand-900 shadow-brand-glow' : 'border-white/50 bg-white/70 text-slate-600 dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-300'"
        @click="set('light')"
        :aria-pressed="mode === 'light'"
      >
        <span class="flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500/90 text-white shadow">
            <i data-lucide="sun" class="h-5 w-5"></i>
          </span>
          <?= __('Light mode') ?>
        </span>
        <i data-lucide="check" class="h-5 w-5" x-cloak x-show="mode === 'light'"></i>
      </button>
      <button
        type="button"
        class="flex items-center justify-between rounded-full border px-4 py-3 text-left text-sm font-semibold transition sm:text-base"
        :class="mode === 'dark' ? 'border-brand-400/80 bg-brand-600/20 text-brand-50 shadow-brand-glow' : 'border-white/50 bg-white/70 text-slate-600 dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-300'"
        @click="set('dark')"
        :aria-pressed="mode === 'dark'"
      >
        <span class="flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900/90 text-white shadow dark:bg-slate-800">
            <i data-lucide="moon" class="h-5 w-5"></i>
          </span>
          <?= __('Dark mode') ?>
        </span>
        <i data-lucide="check" class="h-5 w-5" x-cloak x-show="mode === 'dark'"></i>
      </button>
    </div>
  </section>

  <?php if ($localeOptions): ?>
    <section class="card" id="language">
      <div class="card-kicker"><?= __('Preferences') ?></div>
      <h2 class="card-title mt-1"><?= __('Language') ?></h2>
      <p class="card-subtle mt-2 text-sm"><?= __('Choose your preferred interface language.') ?></p>
      <div class="mt-4 grid gap-2 sm:grid-cols-2">
        <?php foreach ($localeOptions as $code => $label): ?>
          <?php
          $isActive = $code === $currentLocale;
          $flag = $localeFlags[$code] ?? '🏳️';
          ?>
          <a
            href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
            class="flex items-center gap-3 rounded-2xl border px-3 py-2 text-sm font-semibold transition <?= $isActive ? 'border-brand-500 bg-brand-600 text-white shadow-brand-glow' : 'border-white/60 bg-white/70 text-slate-600 hover:border-brand-200 hover:bg-brand-50/70 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:bg-slate-800/70' ?>"
            title="<?= htmlspecialchars($label) ?>"
            aria-current="<?= $isActive ? 'true' : 'false' ?>"
          >
            <span class="text-lg leading-none"><?= $flag ?></span>
            <span><?= htmlspecialchars($label) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php foreach ($navSections as $index => $section):
    $title = trim($section['title'] ?? '');
    $items = $section['items'] ?? [];
    if (!$items) { continue; }
    $sectionId = 'more-section-' . $index;
  ?>
    <section class="card" <?= $title ? 'aria-labelledby="' . htmlspecialchars($sectionId, ENT_QUOTES) . '"' : '' ?>>
      <?php if ($title): ?>
        <h2 id="<?= htmlspecialchars($sectionId, ENT_QUOTES) ?>" class="card-title">
          <?= htmlspecialchars($title) ?>
        </h2>
      <?php endif; ?>
      <div class="mt-4" role="list">
        <div class="glass-stack" role="presentation">
          <?php foreach ($items as $item):
            $href = $item['href'] ?? '#';
            $label = $item['label'] ?? '';
            $description = $item['description'] ?? '';
            $icon = $item['icon'] ?? 'circle';
          ?>
            <a
              role="listitem"
              class="glass-stack__item group flex items-center gap-4 text-left transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:-translate-y-0.5 focus-visible:shadow-lg focus-visible:outline-none"
              href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
            >
              <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-100/80 text-brand-700 transition group-hover:bg-brand-200 group-hover:text-brand-900 dark:bg-brand-500/15 dark:text-brand-100 dark:group-hover:bg-brand-500/25">
                <i data-lucide="<?= htmlspecialchars($icon) ?>" class="h-5 w-5"></i>
              </span>
              <span class="flex-1">
                <span class="block text-base font-semibold text-slate-900 transition group-hover:text-brand-900 dark:text-white dark:group-hover:text-brand-100">
                  <?= htmlspecialchars($label) ?>
                </span>
                <?php if ($description): ?>
                  <span class="mt-1 block text-sm text-slate-500 transition group-hover:text-slate-700 dark:text-slate-300/80 dark:group-hover:text-slate-200/90">
                    <?= htmlspecialchars($description) ?>
                  </span>
                <?php endif; ?>
              </span>
              <i data-lucide="chevron-right" class="h-5 w-5 text-slate-400 transition group-hover:translate-x-1 group-hover:text-brand-600 dark:text-slate-500 dark:group-hover:text-brand-200"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="card">
    <form action="/logout" method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <button class="btn btn-primary w-full justify-center text-base font-semibold">
        <?= __('Logout') ?>
      </button>
    </form>
  </section>
</div>
