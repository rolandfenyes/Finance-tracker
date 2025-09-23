<?php
if (!function_exists('render_page_header')) {
  /**
   * Render a consistent hero/header section for logged-in views.
   *
   * @param array $config {
   *   @var string|null $id         Optional anchor id on the wrapper section.
   *   @var string|null $kicker     Small label rendered above the title.
   *   @var string      $title      Main heading.
   *   @var string|null $subtitle   Supporting copy under the title.
   *   @var array[]     $actions    Call-to-action buttons/links.
   *   @var array[]     $meta       Small meta chips under the intro copy.
   *   @var array|null  $insight    Highlight panel on the right side.
   *   @var array[]     $tabs       Optional secondary navigation links.
   *   @var array[]     $breadcrumbs Optional breadcrumb trail.
   * }
   */
  function render_page_header(array $config): void
  {
    $title = trim($config['title'] ?? '');
    if ($title === '') {
      return;
    }

    $sectionId   = $config['id'] ?? null;
    $kicker      = $config['kicker'] ?? null;
    $subtitle    = $config['subtitle'] ?? null;
    $actions     = is_array($config['actions'] ?? null) ? $config['actions'] : [];
    $meta        = is_array($config['meta'] ?? null) ? $config['meta'] : [];
    $insight     = is_array($config['insight'] ?? null) ? $config['insight'] : null;
    $tabs        = is_array($config['tabs'] ?? null) ? $config['tabs'] : [];
    $breadcrumbs = is_array($config['breadcrumbs'] ?? null) ? $config['breadcrumbs'] : [];

    $renderAttrs = static function(array $attrs): string {
      $parts = [];
      foreach ($attrs as $key => $value) {
        if ($value === null || $value === false) {
          continue;
        }
        if ($value === true) {
          $parts[] = sprintf(' %s', htmlspecialchars($key, ENT_QUOTES));
          continue;
        }
        $parts[] = sprintf(' %s="%s"', htmlspecialchars($key, ENT_QUOTES), htmlspecialchars((string)$value, ENT_QUOTES));
      }
      return implode('', $parts);
    };

    $toneClasses = [
      'brand'   => 'border-brand-300/70 bg-brand-500/10 text-brand-700 dark:border-brand-500/50 dark:bg-brand-600/20 dark:text-brand-100',
      'positive'=> 'border-emerald-300/70 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-100',
      'warning' => 'border-amber-300/70 bg-amber-400/10 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-100',
      'danger'  => 'border-rose-300/70 bg-rose-500/10 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200',
      'neutral' => 'border-slate-200/80 bg-white/80 text-slate-700 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200',
    ];

    $styleMap = [
      'primary' => 'btn btn-primary',
      'emerald' => 'btn btn-emerald',
      'muted'   => 'btn btn-muted',
      'ghost'   => 'btn btn-ghost',
      'danger'  => 'btn btn-danger',
      'link'    => 'text-sm font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-200 dark:hover:text-brand-100',
    ];
    ?>
    <section<?= $sectionId ? ' id="'.htmlspecialchars($sectionId, ENT_QUOTES).'"' : '' ?> class="mb-10">
      <div class="card px-6 py-7">
        <?php if ($breadcrumbs): ?>
          <nav aria-label="<?= htmlspecialchars(__('Breadcrumb'), ENT_QUOTES) ?>" class="mb-4">
            <ol class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
              <?php foreach ($breadcrumbs as $idx => $crumb):
                $label = htmlspecialchars($crumb['label'] ?? '', ENT_QUOTES);
                if ($label === '') continue;
                $isLast = $idx === array_key_last($breadcrumbs);
              ?>
                <li class="flex items-center gap-2">
                  <?php if (!$isLast && !empty($crumb['href'])): ?>
                    <a class="hover:text-brand-600 dark:hover:text-brand-200" href="<?= htmlspecialchars($crumb['href'], ENT_QUOTES) ?>"><?= $label ?></a>
                  <?php else: ?>
                    <span><?= $label ?></span>
                  <?php endif; ?>
                  <?php if (!$isLast): ?><span class="opacity-60">/</span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          </nav>
        <?php endif; ?>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
          <div class="max-w-2xl space-y-4">
            <?php if ($kicker): ?>
              <span class="chip uppercase tracking-[0.3em] text-[11px]"><?= htmlspecialchars($kicker, ENT_QUOTES) ?></span>
            <?php endif; ?>
            <div>
              <h1 class="text-3xl font-semibold leading-tight text-slate-900 dark:text-white"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
              <?php if ($subtitle): ?>
                <p class="mt-3 text-base text-slate-600 dark:text-slate-300"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p>
              <?php endif; ?>
            </div>

            <?php if ($meta): ?>
              <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                <?php foreach ($meta as $item):
                  $label = trim((string)($item['label'] ?? ''));
                  if ($label === '') continue;
                  $icon = $item['icon'] ?? null;
                ?>
                  <span class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-3 py-1 dark:border-slate-700 dark:bg-slate-900/60">
                    <?php if ($icon): ?>
                      <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" class="h-3.5 w-3.5"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($actions): ?>
              <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:flex-wrap">
                <?php foreach ($actions as $action):
                  $label = trim((string)($action['label'] ?? ''));
                  if ($label === '') continue;
                  $icon = $action['icon'] ?? null;
                  $styleKey = $action['style'] ?? 'primary';
                  $tag = strtolower($action['tag'] ?? 'a');
                  $classBase = $styleMap[$styleKey] ?? $styleMap['muted'];
                  $extraClass = trim((string)($action['class'] ?? ''));

                  if ($styleKey !== 'link') {
                    $class = trim($classBase.' '.$extraClass.' w-full sm:w-auto');
                  } else {
                    $class = trim($classBase.' '.$extraClass);
                  }

                  $attrs = $renderAttrs($action['attributes'] ?? []);
                  if ($tag === 'button') {
                    $type = $action['type'] ?? 'button';
                    $attrs = sprintf(' type="%s"%s', htmlspecialchars($type, ENT_QUOTES), $attrs);
                    echo '<button class="'.$class.'">';
                  } else {
                    $href = $action['href'] ?? '#';
                    $attrs = sprintf(' href="%s"%s', htmlspecialchars($href, ENT_QUOTES), $attrs);
                    echo '<a class="'.$class.'"'.$attrs.'>';
                  }

                  if ($icon) {
                    echo '<i data-lucide="'.htmlspecialchars($icon, ENT_QUOTES).'" class="h-4 w-4"></i>';
                  }
                  echo htmlspecialchars($label, ENT_QUOTES);
                  echo $tag === 'button' ? '</button>' : '</a>';
                endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($insight):
            $insightLabel = trim((string)($insight['label'] ?? ''));
            $insightValue = trim((string)($insight['value'] ?? ''));
            $insightSub = trim((string)($insight['subline'] ?? ''));
            $toneKey = $insight['tone'] ?? 'brand';
            $toneClass = $toneClasses[$toneKey] ?? $toneClasses['brand'];
            if ($insightLabel !== '' || $insightValue !== ''):
          ?>
            <div class="w-full max-w-sm rounded-3xl border px-5 py-5 text-left shadow-sm <?= $toneClass ?>">
              <?php if ($insightLabel): ?>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] opacity-80">
                  <?= htmlspecialchars($insightLabel, ENT_QUOTES) ?>
                </div>
              <?php endif; ?>
              <?php if ($insightValue): ?>
                <div class="mt-2 text-3xl font-semibold leading-tight">
                  <?= htmlspecialchars($insightValue, ENT_QUOTES) ?>
                </div>
              <?php endif; ?>
              <?php if ($insightSub): ?>
                <p class="mt-2 text-sm opacity-80"><?= htmlspecialchars($insightSub, ENT_QUOTES) ?></p>
              <?php endif; ?>
            </div>
          <?php endif; endif; ?>
        </div>

        <?php if ($tabs): ?>
          <nav class="mt-8 flex flex-wrap gap-2 border-t border-slate-200/70 pt-4 text-sm dark:border-slate-800/70" aria-label="<?= htmlspecialchars(__('Page sections'), ENT_QUOTES) ?>">
            <?php foreach ($tabs as $tab):
              $label = trim((string)($tab['label'] ?? ''));
              if ($label === '') continue;
              $href = $tab['href'] ?? '#';
              $isActive = !empty($tab['active']);
              $tabClass = 'tab-btn' . ($isActive ? ' active' : '');
            ?>
              <a class="<?= $tabClass ?>" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>">
                <?= htmlspecialchars($label, ENT_QUOTES) ?>
              </a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
      </div>
    </section>
    <?php
  }
}
