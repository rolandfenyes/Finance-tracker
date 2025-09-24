<?php
/** @var array $user */
/** @var string $displayName */
/** @var array $navSections */

$email = trim($user['email'] ?? '');
$firstSource = $displayName ?: $email;
$firstChar = '';
if ($firstSource !== '') {
    $firstChar = function_exists('mb_substr') ? mb_substr($firstSource, 0, 1) : substr($firstSource, 0, 1);
}
$initial = strtoupper($firstChar ?: '?');
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-10 pb-28">
  <section class="rounded-4xl border border-white/60 bg-white/80 p-6 shadow-glass backdrop-blur-xl dark:border-slate-800/70 dark:bg-slate-900/70">
    <div class="flex items-center gap-5">
      <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-brand-400/90 to-brand-600/90 text-2xl font-semibold tracking-tight text-white shadow-brand-glow">
        <?= htmlspecialchars($initial) ?>
      </div>
      <div class="flex-1">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500/80 dark:text-slate-300/60"><?= __('Signed in as') ?></p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($displayName) ?></p>
        <?php if ($email && strtolower($email) !== strtolower($displayName)): ?>
          <p class="text-sm text-slate-500 dark:text-slate-300/80"><?= htmlspecialchars($email) ?></p>
        <?php endif; ?>
      </div>
      <div class="shrink-0">
        <a href="/settings/profile" class="btn btn-muted whitespace-nowrap px-4 py-2 text-sm font-semibold">
          <?= __('Edit profile') ?>
        </a>
      </div>
    </div>
  </section>

  <section
    class="rounded-4xl border border-white/60 bg-white/80 p-6 shadow-glass backdrop-blur-xl dark:border-slate-800/70 dark:bg-slate-900/70"
    x-data="{
      mode: $root.theme,
      init() {
        this.mode = $root.theme;
        document.addEventListener('themechange', (event) => { this.mode = event.detail.theme; });
      },
      set(mode) {
        $root.applyTheme(mode);
        this.mode = mode;
      }
    }"
    role="group"
    aria-labelledby="more-theme-heading"
  >
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500/80 dark:text-slate-300/60"><?= __('Appearance') ?></p>
        <h2 id="more-theme-heading" class="text-2xl font-semibold text-slate-900 dark:text-white"><?= __('Theme') ?></h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300/80"><?= __('Choose the look that feels most comfortable for your eyes.') ?></p>
      </div>
    </div>
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
      <button
        type="button"
        class="flex items-center justify-between rounded-3xl border-2 px-4 py-3 text-left transition"
        :class="mode === 'light' ? 'border-brand-500/80 bg-brand-50/80 text-brand-900 shadow-brand-glow' : 'border-white/40 bg-white/60 text-slate-600 dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-300'"
        @click="set('light')"
      >
        <span class="flex items-center gap-3 text-base font-semibold">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500/90 text-white shadow">
            <i data-lucide="sun" class="h-5 w-5"></i>
          </span>
          <?= __('Light mode') ?>
        </span>
        <i data-lucide="check" class="h-5 w-5" x-show="mode === 'light'"></i>
      </button>
      <button
        type="button"
        class="flex items-center justify-between rounded-3xl border-2 px-4 py-3 text-left transition"
        :class="mode === 'dark' ? 'border-brand-400/80 bg-brand-600/20 text-brand-50 shadow-brand-glow' : 'border-white/40 bg-white/60 text-slate-600 dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-300'"
        @click="set('dark')"
      >
        <span class="flex items-center gap-3 text-base font-semibold">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900/90 text-white shadow dark:bg-slate-800">
            <i data-lucide="moon" class="h-5 w-5"></i>
          </span>
          <?= __('Dark mode') ?>
        </span>
        <i data-lucide="check" class="h-5 w-5" x-show="mode === 'dark'"></i>
      </button>
    </div>
  </section>

  <?php foreach ($navSections as $section):
    $title = $section['title'] ?? '';
    $items = $section['items'] ?? [];
    if (!$items) { continue; }
  ?>
    <section class="space-y-4">
      <?php if ($title): ?>
        <h2 class="px-2 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500/70 dark:text-slate-300/60">
          <?= htmlspecialchars($title) ?>
        </h2>
      <?php endif; ?>
      <div class="space-y-3" role="list">
        <?php foreach ($items as $item):
          $href = $item['href'] ?? '#';
          $label = $item['label'] ?? '';
          $description = $item['description'] ?? '';
          $icon = $item['icon'] ?? 'circle';
        ?>
          <a
            role="listitem"
            class="group flex items-center gap-4 rounded-3xl border border-white/60 bg-white/80 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:-translate-y-0.5 focus-visible:shadow-lg focus-visible:outline-none dark:border-slate-800/70 dark:bg-slate-900/70"
            href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
          >
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-100/80 text-brand-700 transition group-hover:bg-brand-200 group-hover:text-brand-900 dark:bg-brand-500/15 dark:text-brand-100 dark:group-hover:bg-brand-500/25">
              <i data-lucide="<?= htmlspecialchars($icon) ?>" class="h-5 w-5"></i>
            </span>
            <span class="flex-1 text-left">
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
    </section>
  <?php endforeach; ?>

  <form action="/logout" method="post" class="mt-4 pt-4">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <button class="btn btn-primary w-full justify-center text-base font-semibold">
      <?= __('Logout') ?>
    </button>
  </form>
</div>
