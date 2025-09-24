<section class="max-w-5xl mx-auto">
  <div class="card">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-xl font-semibold"><?= __('Manage Basic Incomes') ?></h1>
      <div class="flex items-center gap-2">
        <a href="/settings" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to Settings') ?></span>
        </a>
        <a href="/more" class="inline-flex sm:hidden items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to More') ?></span>
        </a>
      </div>
    </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-3 text-sm text-brand-600"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
  <?php endif; ?>
  <?php
    $categoryMap = [];
    foreach ($categories as $catRow) {
      $categoryMap[(int)($catRow['id'] ?? 0)] = $catRow;
    }
  ?>

    <div class="mt-6 grid md:grid-cols-12 gap-6">
    <!-- Left: Add / Raise -->
    <div class="md:col-span-7">
      <h2 class="font-medium mb-3"><?= __('Add income / Record a raise') ?></h2>

      <form method="post" action="/settings/basic-incomes/add"
            class="grid gap-3 md:grid-cols-12 items-end">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="md:col-span-4">
          <label class="label"><?= __('Label') ?></label>
          <input name="label" class="input" placeholder="<?= __('e.g., Salary') ?>" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= __('Amount') ?></label>
          <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= __('Currency') ?></label>
          <select name="currency" class="select">
            <?php foreach ($currencies as $uc): ?>
              <option value="<?= htmlspecialchars($uc['code']) ?>" <?= $uc['is_main'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($uc['code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= __('Valid from') ?></label>
          <input name="valid_from" type="date" class="input" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= __('Category') ?></label>
          <select name="category_id" class="select">
            <option value=""><?= __('No category') ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-12 flex justify-end">
          <button class="btn btn-primary"><?= __('Add') ?></button>
        </div>
      </form>

      <p class="text-xs text-gray-500 mt-2">
        <?= __('If an income with the same label exists, its previous period is automatically closed the day before the new start date.') ?>
      </p>
    </div>

    <!-- Right: Snapshot -->
    <aside class="md:col-span-5">
      <h2 class="font-medium mb-3"><?= __('Current snapshot') ?></h2>
      <?php
        $latest = [];
        foreach ($rows as $r) { $lab = $r['label']; if (!isset($latest[$lab])) $latest[$lab] = $r; }
      ?>
      <div class="space-y-3">
        <?php if ($latest): foreach ($latest as $lab=>$r): ?>
          <div class="panel p-4 flex items-start justify-between">
            <div class="min-w-0">
              <div class="font-medium truncate"><?= htmlspecialchars($lab) ?></div>
              <div class="text-xs text-gray-500 mt-0.5">
                <?= __('Since :date — :amount', ['date' => htmlspecialchars($r['valid_from']), 'amount' => moneyfmt($r['amount'], $r['currency'])]) ?>
              </div>
            </div>
            <?php if (!empty($r['category_id'])):
              $cx = $categoryMap[(int)$r['category_id']] ?? null;
              if ($cx): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs shrink-0">
                  <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($cx['color']) ?>;"></span>
                  <?= htmlspecialchars($cx['label']) ?>
                </span>
            <?php endif; endif; ?>
          </div>
        <?php endforeach; else: ?>
          <div class="panel p-4 text-sm text-gray-500"><?= __('No basic incomes yet.') ?></div>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <!-- History -->
  <div class="mt-8">
    <h2 class="font-medium mb-3"><?= __('History') ?></h2>
    <div class="space-y-4">
      <?php if ($rows): foreach ($rows as $r): ?>
        <article class="panel p-5 space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-2">
              <div class="text-lg font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($r['label']) ?></div>
              <?php if (!empty($r['category_id'])):
                $cx = $categoryMap[(int)$r['category_id']] ?? null;
                if ($cx): ?>
                  <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/60 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-gray-200">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($cx['color']) ?>;"></span>
                    <?= htmlspecialchars($cx['label']) ?>
                  </span>
              <?php endif; endif; ?>
            </div>
            <div class="text-right leading-tight">
              <div class="text-xl font-semibold text-gray-900 dark:text-gray-100"><?= moneyfmt($r['amount'], $r['currency']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($r['currency']) ?></div>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/60 px-3 py-1 dark:border-slate-700 dark:bg-slate-900/40">
              <?= __('Valid from') ?>
              <span class="font-medium text-gray-700 dark:text-gray-200"><?= htmlspecialchars($r['valid_from']) ?></span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/60 px-3 py-1 dark:border-slate-700 dark:bg-slate-900/40">
              <?= __('Valid to') ?>
              <span class="font-medium text-gray-700 dark:text-gray-200">
                <?= $r['valid_to'] ? htmlspecialchars($r['valid_to']) : __('Ongoing') ?>
              </span>
            </span>
          </div>
          <details class="group rounded-2xl border border-dashed border-white/60 bg-white/60 p-4 transition dark:border-slate-700/70 dark:bg-slate-900/40">
            <summary class="flex cursor-pointer items-center justify-between gap-2 text-sm font-semibold text-brand-600 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-300">
              <span class="flex items-center gap-2">
                <span class="icon-action icon-action--primary" aria-hidden="true">
                  <i data-lucide="pencil" class="h-4 w-4"></i>
                </span>
                <?= __('Edit') ?>
              </span>
              <i data-lucide="chevron-down" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180"></i>
            </summary>
            <div class="mt-3 space-y-3">
              <form class="grid gap-2 sm:grid-cols-12" method="post" action="/settings/basic-incomes/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <input name="label" value="<?= htmlspecialchars($r['label']) ?>" class="input sm:col-span-4" />
                <input name="amount" type="number" step="0.01" value="<?= htmlspecialchars($r['amount']) ?>" class="input sm:col-span-2" />
                <input name="currency" value="<?= htmlspecialchars($r['currency']) ?>" class="input sm:col-span-2" />
                <input name="valid_from" type="date" value="<?= htmlspecialchars($r['valid_from']) ?>" class="input sm:col-span-2" />
                <input name="valid_to" type="date" value="<?= htmlspecialchars($r['valid_to'] ?? '') ?>" class="input sm:col-span-2" />
                <select name="category_id" class="select sm:col-span-4">
                  <option value=""><?= __('No category') ?></option>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['category_id'] ?? 0)===(int)$c['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="sm:col-span-12 flex justify-end">
                  <button class="btn btn-primary"><?= __('Save') ?></button>
                </div>
              </form>
              <form class="flex justify-end" method="post" action="/settings/basic-incomes/delete"
                    onsubmit="return confirm('<?= addslashes(__('Delete this record?')) ?>')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                  <i data-lucide="trash-2" class="h-4 w-4"></i>
                  <span class="sr-only"><?= __('Remove') ?></span>
                </button>
              </form>
            </div>
          </details>
        </article>
      <?php endforeach; else: ?>
        <div class="panel p-5 text-sm text-gray-500 dark:text-gray-400"><?= __('No basic incomes yet.') ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>
