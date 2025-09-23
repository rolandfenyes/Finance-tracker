<?php
require_once __DIR__.'/../layout/page_header.php';

$allocationPct = max(0, min(100, round($total, 2)));
$allocationStatus = $total > 100
  ? __('You’re over by :percent%. Rebalance your rules.', ['percent' => number_format($total - 100, 2)])
  : ($total < 100
    ? __(':percent% is still unallocated.', ['percent' => number_format(100 - $total, 2)])
    : __('Perfect! Fully allocated.'));

render_page_header([
  'kicker' => __('Settings'),
  'title' => __('Give every dollar a job'),
  'subtitle' => __('Define how incoming money should flow between savings, debt, and spending buckets.'),
  'insight' => [
    'label' => __('Allocated'),
    'value' => number_format($allocationPct, 2).'%',
    'subline' => $allocationStatus,
  ],
  'actions' => [
    ['label' => __('Add rule'), 'href' => '#cashflow-form', 'icon' => 'plus-circle', 'style' => 'primary'],
    ['label' => __('Back to settings'), 'href' => '/settings', 'icon' => 'arrow-left', 'style' => 'muted'],
  ],
  'tabs' => [
    ['label' => __('Rules'), 'href' => '#cashflow-form', 'active' => true],
    ['label' => __('All allocations'), 'href' => '#cashflow-list'],
  ],
]);
?>

<section class="max-w-3xl mx-auto">
  <div class="card space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold"><?= __('Cashflow Rules') ?></h1>
      <a href="/settings" class="text-sm text-accent"><?= __('← Back to Settings') ?></a>
    </div>

    <?php
      $pct = max(0, min(100, round($total,2)));
      $barColor = $pct < 100 ? 'bg-brand-500' : ($pct == 100 ? 'bg-brand-600' : 'bg-red-500');
    ?>
    <div id="cashflow-form">
      <div class="flex justify-between text-xs text-gray-600">
        <span><?= __('Total allocation') ?></span>
        <span><?= number_format($pct,2) ?>%</span>
      </div>
      <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-brand-100/60">
        <div class="h-3 <?= $barColor ?>" style="width: <?= $pct ?>%"></div>
      </div>
      <?php if ($total > 100): ?>
        <p class="mt-2 text-xs text-red-600"><?= __('You’re over 100%. Reduce some rules.') ?></p>
      <?php elseif ($total < 100): ?>
        <p class="mt-2 text-xs text-gray-500"><?= __(':percent% unallocated.', ['percent' => number_format(100-$total,2)]) ?></p>
      <?php else: ?>
        <p class="mt-2 text-xs text-gray-500"><?= __('Perfect! Fully allocated.') ?></p>
      <?php endif; ?>
    </div>

    <div id="cashflow-list">
      <h2 class="font-medium mb-2"><?= __('Add rule') ?></h2>
      <form method="post" action="/settings/cashflow/add" class="grid gap-2 sm:grid-cols-6">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input name="label" class="input sm:col-span-4" placeholder="<?= __('e.g. Needs / Investments / Fun') ?>" required />
        <div class="sm:col-span-1">
          <div class="flex items-center gap-2">
            <input name="percent" type="number" step="0.01" min="0" class="input" placeholder="%" required />
            <span class="text-sm text-gray-500">%</span>
          </div>
        </div>
        <button class="btn btn-primary sm:col-span-1"><?= __('Add') ?></button>
      </form>
    </div>

    <div>
      <h2 class="font-medium mb-2"><?= __('Your rules') ?></h2>
      <ul class="glass-stack">
        <?php if (!count($rules)): ?>
          <li class="glass-stack__item text-sm text-gray-500"><?= __('No rules yet.') ?></li>
        <?php else: foreach($rules as $r): ?>
          <li class="glass-stack__item">
            <details class="group">
              <summary class="flex cursor-pointer items-center justify-between">
                <span class="font-medium"><?= htmlspecialchars($r['label']) ?></span>
                <span class="text-sm font-semibold text-brand-600 dark:text-brand-200"><?= number_format((float)$r['percent'],2) ?>%</span>
              </summary>
              <div class="mt-3 rounded-2xl border border-white/50 bg-white/60 p-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/50">
                <form class="grid gap-2 sm:grid-cols-6 items-end" method="post" action="/settings/cashflow/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <input name="label" class="input sm:col-span-4" value="<?= htmlspecialchars($r['label']) ?>" />
                  <div class="sm:col-span-1">
                    <div class="flex items-center gap-2">
                      <input name="percent" type="number" step="0.01" min="0" class="input"
                             value="<?= number_format((float)$r['percent'],2,'.','') ?>" />
                      <span class="text-sm text-gray-500">%</span>
                    </div>
                  </div>
                  <button class="btn btn-primary sm:col-span-1"><?= __('Save') ?></button>
                </form>
                <form class="mt-2 flex justify-end" method="post" action="/settings/cashflow/delete"
                      onsubmit="return confirm('<?= addslashes(__('Delete this rule?')) ?>');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <button class="icon-action icon-action--danger" type="submit" title="<?= __('Remove') ?>">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                    <span class="sr-only"><?= __('Remove') ?></span>
                  </button>
                </form>
              </div>
            </details>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
  </div>
</section>
