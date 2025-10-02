<?php
/** @var array $totals */
/** @var string $baseCurrency */
/** @var array $marketByCurrency */
/** @var array $unrealizedByCurrency */
/** @var array $realizedByCurrency */
/** @var array $cashEntries */
/** @var array $cashByCurrency */
/** @var string $preferredCurrency */
/** @var float|null $preferredMarket */
/** @var float|null $preferredUnrealized */
/** @var float|null $preferredRealized */
/** @var float|null $preferredCash */
?>
<article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
  <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Total Market Value</h2>
  <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100"><?= moneyfmt($totals['total_market_value'], $baseCurrency) ?></p>
  <?php if ($preferredMarket !== null): ?>
    <p class="text-sm text-gray-500">≈ <?= moneyfmt($preferredMarket, $preferredCurrency) ?></p>
  <?php endif; ?>
  <?php if (!empty($marketByCurrency)): ?>
    <ul class="mt-3 space-y-1 text-xs text-gray-500">
      <?php foreach ($marketByCurrency as $currency => $amount): ?>
        <li><?= moneyfmt($amount, $currency) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</article>
<article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
  <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Unrealized P/L</h2>
  <p class="mt-3 text-2xl font-semibold <?= $totals['unrealized_pl'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
    <?= moneyfmt($totals['unrealized_pl'], $baseCurrency) ?>
  </p>
  <?php if ($preferredUnrealized !== null): ?>
    <p class="text-sm text-gray-500">≈ <?= moneyfmt($preferredUnrealized, $preferredCurrency) ?></p>
  <?php endif; ?>
  <p class="text-xs text-gray-500 mt-1"><?= number_format($totals['unrealized_pct'], 2) ?>%</p>
  <?php if (!empty($unrealizedByCurrency)): ?>
    <ul class="mt-3 space-y-1 text-xs <?= $totals['unrealized_pl'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
      <?php foreach ($unrealizedByCurrency as $currency => $amount): ?>
        <li><?= moneyfmt($amount, $currency) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</article>
<article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
  <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Realized P/L (<?= htmlspecialchars($totals['realized_period']) ?>)</h2>
  <p class="mt-3 text-2xl font-semibold <?= $totals['realized_pl'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
    <?= moneyfmt($totals['realized_pl'], $baseCurrency) ?>
  </p>
  <?php if ($preferredRealized !== null): ?>
    <p class="text-sm text-gray-500">≈ <?= moneyfmt($preferredRealized, $preferredCurrency) ?></p>
  <?php endif; ?>
  <p class="text-xs text-gray-500 mt-1">Daily P/L: <?= moneyfmt($totals['daily_pl'], $baseCurrency) ?></p>
  <?php if (!empty($realizedByCurrency)): ?>
    <ul class="mt-3 space-y-1 text-xs <?= $totals['realized_pl'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
      <?php foreach ($realizedByCurrency as $currency => $amount): ?>
        <li><?= moneyfmt($amount, $currency) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</article>
<article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
  <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Cash Balance</h2>
  <p class="mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">
    <?= moneyfmt($totals['cash_balance'] ?? 0, $baseCurrency) ?>
  </p>
  <?php if ($preferredCash !== null): ?>
    <p class="text-sm text-gray-500">≈ <?= moneyfmt($preferredCash, $preferredCurrency) ?></p>
  <?php endif; ?>
  <?php if (!empty($cashEntries)): ?>
    <ul class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
      <?php foreach ($cashEntries as $entry): ?>
        <li><?= moneyfmt($entry['amount'], $entry['currency']) ?> <span class="text-[11px] text-gray-400">(<?= moneyfmt($entry['amount_base'], $baseCurrency) ?>)</span></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="text-xs text-gray-500 mt-3">No cash entries yet.</p>
  <?php endif; ?>
</article>
