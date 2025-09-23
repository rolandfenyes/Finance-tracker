<?php
if (!function_exists('render_focus_panel')) {
    /**
     * Render a two-column focus helper with actionable steps.
     *
     * @param array $config
     */
    function render_focus_panel(array $config): void
    {
        $id          = $config['id'] ?? null;
        $title       = trim((string)($config['title'] ?? ''));
        $description = trim((string)($config['description'] ?? ''));
        $tone        = $config['tone'] ?? 'brand';

        $rawItems = is_array($config['items'] ?? null) ? $config['items'] : [];
        $items    = array_values(array_filter($rawItems, static function ($item) {
            return is_array($item) && trim((string)($item['label'] ?? '')) !== '';
        }));

        $side = is_array($config['side'] ?? null) ? $config['side'] : null;
        $tips = is_array($config['tips'] ?? null) ? array_values(array_filter($config['tips'], static function ($tip) {
            return trim((string)$tip) !== '';
        })) : [];

        if (!$title && !$description && !$items) {
            return;
        }

        $toneClasses = [
            'brand'   => 'border-brand-300/70 bg-brand-500/10 dark:border-brand-500/50 dark:bg-brand-600/20',
            'emerald' => 'border-emerald-300/70 bg-emerald-500/10 dark:border-emerald-500/40 dark:bg-emerald-500/20',
            'amber'   => 'border-amber-300/70 bg-amber-400/10 dark:border-amber-500/40 dark:bg-amber-500/20',
            'rose'    => 'border-rose-300/70 bg-rose-500/10 dark:border-rose-500/40 dark:bg-rose-500/20',
            'neutral' => 'border-slate-200/80 bg-white/85 dark:border-slate-700 dark:bg-slate-900/55',
        ];
        $toneClass = $toneClasses[$tone] ?? $toneClasses['brand'];

        $stateMeta = [
            'success' => [
                'label' => __('Complete'),
                'icon'  => 'check',
                'class' => 'border-emerald-300/60 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-600/20 dark:text-emerald-100',
            ],
            'active' => [
                'label' => __('In progress'),
                'icon'  => 'refresh-cw',
                'class' => 'border-brand-300/60 bg-brand-500/10 text-brand-700 dark:border-brand-500/40 dark:bg-brand-600/20 dark:text-brand-100',
            ],
            'warning' => [
                'label' => __('Action needed'),
                'icon'  => 'alert-triangle',
                'class' => 'border-amber-300/60 bg-amber-400/10 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-100',
            ],
            'info' => [
                'label' => __('Helpful tip'),
                'icon'  => 'sparkles',
                'class' => 'border-slate-200/80 bg-white/85 text-slate-700 dark:border-slate-700 dark:bg-slate-900/55 dark:text-slate-200',
            ],
        ];
        ?>
        <section<?= $id ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '' ?> class="mb-10">
          <div class="card px-6 py-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
              <div class="flex-1 space-y-5">
                <?php if ($title): ?>
                  <div class="flex items-center gap-2">
                    <span class="chip"><?= __('Focus') ?></span>
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
                  </div>
                <?php endif; ?>
                <?php if ($description): ?>
                  <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"><?= htmlspecialchars($description, ENT_QUOTES) ?></p>
                <?php endif; ?>

                <?php if ($items): ?>
                  <ul class="space-y-4">
                    <?php foreach ($items as $item):
                      $label = trim((string)($item['label'] ?? ''));
                      $desc  = trim((string)($item['description'] ?? ''));
                      $href  = trim((string)($item['href'] ?? ''));
                      $icon  = trim((string)($item['icon'] ?? 'sparkles'));
                      $state = $item['state'] ?? null;
                      $stateInfo = $stateMeta[$state] ?? null;
                      $stateLabel = trim((string)($item['state_label'] ?? ($stateInfo['label'] ?? '')));
                      $badgeClass = $stateInfo['class'] ?? $stateMeta['info']['class'];
                      $badgeIcon  = $stateInfo['icon'] ?? $stateMeta['info']['icon'];
                      $metaLine   = trim((string)($item['meta'] ?? ''));
                      $progress   = isset($item['progress']) && $item['progress'] !== '' ? max(0, min(100, (float)$item['progress'])) : null;
                      $progressLabel = trim((string)($item['progress_label'] ?? ($progress !== null ? ($progress . '%') : '')));
                      ?>
                      <li class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/85 p-4 transition hover:border-brand-200 hover:shadow-lg dark:border-slate-800/60 dark:bg-slate-900/55 dark:hover:border-brand-500/40">
                        <div class="flex items-start justify-between gap-4">
                          <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-2xl border border-slate-200/80 bg-white/85 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60">
                              <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" class="h-5 w-5 text-brand-600 dark:text-brand-200"></i>
                            </span>
                            <div class="min-w-0 space-y-2">
                              <?php if ($href): ?>
                                <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="block text-base font-semibold text-slate-900 transition hover:text-brand-700 dark:text-white dark:hover:text-brand-200">
                                  <?= htmlspecialchars($label, ENT_QUOTES) ?>
                                </a>
                              <?php else: ?>
                                <span class="block text-base font-semibold text-slate-900 dark:text-white">
                                  <?= htmlspecialchars($label, ENT_QUOTES) ?>
                                </span>
                              <?php endif; ?>
                              <?php if ($desc): ?>
                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                  <?= htmlspecialchars($desc, ENT_QUOTES) ?>
                                </p>
                              <?php endif; ?>
                              <?php if ($metaLine): ?>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">
                                  <?= htmlspecialchars($metaLine, ENT_QUOTES) ?>
                                </div>
                              <?php endif; ?>
                            </div>
                          </div>
                          <?php if ($stateLabel): ?>
                            <span class="inline-flex flex-none items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] <?= $badgeClass ?>">
                              <i data-lucide="<?= htmlspecialchars($badgeIcon, ENT_QUOTES) ?>" class="h-3.5 w-3.5"></i>
                              <span><?= htmlspecialchars($stateLabel, ENT_QUOTES) ?></span>
                            </span>
                          <?php endif; ?>
                        </div>
                        <?php if ($progress !== null): ?>
                          <div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-brand-100/60 dark:bg-slate-800/60">
                              <div class="h-2 rounded-full bg-brand-600 transition-all" style="width: <?= number_format($progress, 2, '.', '') ?>%"></div>
                            </div>
                            <?php if ($progressLabel): ?>
                              <div class="mt-1 text-xs font-semibold text-brand-700 dark:text-brand-200">
                                <?= htmlspecialchars($progressLabel, ENT_QUOTES) ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if ($tips): ?>
                  <div class="rounded-2xl border border-slate-200/80 bg-white/85 p-4 text-sm text-slate-600 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/55 dark:text-slate-300">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                      <i data-lucide="lightbulb" class="h-4 w-4"></i>
                      <span><?= __('Tips') ?></span>
                    </div>
                    <ul class="space-y-1.5">
                      <?php foreach ($tips as $tip): ?>
                        <li class="flex items-start gap-2">
                          <span class="mt-1 inline-flex h-1.5 w-1.5 flex-none rounded-full bg-brand-500"></span>
                          <span class="flex-1 leading-relaxed"><?= htmlspecialchars((string)$tip, ENT_QUOTES) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>

              <?php if ($side && (trim((string)($side['label'] ?? '')) !== '' || trim((string)($side['value'] ?? '')) !== '' || !empty($side['actions']))):
                $sideLabel   = trim((string)($side['label'] ?? ''));
                $sideValue   = trim((string)($side['value'] ?? ''));
                $sideSubline = trim((string)($side['subline'] ?? ''));
                $sideFoot    = trim((string)($side['footnote'] ?? ''));
                $sideActions = array_values(array_filter(is_array($side['actions'] ?? null) ? $side['actions'] : [], static function ($action) {
                    return trim((string)($action['label'] ?? '')) !== '' && isset($action['href']);
                }));
                ?>
                <aside class="w-full max-w-sm rounded-3xl border px-5 py-6 shadow-sm <?= $toneClass ?>">
                  <?php if ($sideLabel): ?>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] opacity-80">
                      <?= htmlspecialchars($sideLabel, ENT_QUOTES) ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($sideValue): ?>
                    <div class="mt-3 text-3xl font-semibold leading-tight">
                      <?= htmlspecialchars($sideValue, ENT_QUOTES) ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($sideSubline): ?>
                    <p class="mt-2 text-sm leading-relaxed opacity-80">
                      <?= htmlspecialchars($sideSubline, ENT_QUOTES) ?>
                    </p>
                  <?php endif; ?>
                  <?php if ($sideActions): ?>
                    <div class="mt-5 space-y-2">
                      <?php foreach ($sideActions as $action):
                        $aLabel = trim((string)$action['label']);
                        $aHref  = (string)$action['href'];
                        $aIcon  = trim((string)($action['icon'] ?? 'arrow-up-right'));
                        ?>
                        <a href="<?= htmlspecialchars($aHref, ENT_QUOTES) ?>" class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white/85 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:border-brand-300 hover:text-brand-800 dark:border-slate-800/60 dark:bg-slate-900/55 dark:text-brand-100 dark:hover:border-brand-400/60">
                          <span><?= htmlspecialchars($aLabel, ENT_QUOTES) ?></span>
                          <i data-lucide="<?= htmlspecialchars($aIcon, ENT_QUOTES) ?>" class="h-4 w-4"></i>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($sideFoot): ?>
                    <p class="mt-5 text-xs leading-relaxed opacity-70">
                      <?= htmlspecialchars($sideFoot, ENT_QUOTES) ?>
                    </p>
                  <?php endif; ?>
                </aside>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <?php
    }
}
