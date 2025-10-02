<?php
/** @var array $holdings */
/** @var array $insights */
/** @var string $baseCurrency */
/** @var callable $formatQuantity */
?>
<?php if (empty($holdings)): ?>
  <tr>
    <td colspan="9" class="px-6 py-8 text-center text-gray-500">No holdings yet. Record your first trade to get started.</td>
  </tr>
<?php endif; ?>
<?php foreach ($holdings as $holding):
  $symbol = $holding['symbol'];
  $suggestions = $insights[$symbol]['suggestions'] ?? [];
  $signals = $insights[$symbol]['signals'] ?? [];
?>
  <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition" data-symbol="<?= htmlspecialchars($symbol) ?>" data-qty="<?= htmlspecialchars($holding['qty']) ?>" data-avg="<?= htmlspecialchars($holding['avg_cost']) ?>" data-currency="<?= htmlspecialchars($holding['currency']) ?>">
    <td class="px-6 py-4 font-semibold text-gray-900 dark:text-gray-100">
      <a href="/stocks/<?= urlencode($symbol) ?>" class="hover:text-emerald-500 transition"><?= htmlspecialchars($symbol) ?></a>
      <span class="block text-xs text-gray-500"><?= htmlspecialchars($holding['name'] ?? '') ?></span>
    </td>
    <td class="px-6 py-4 text-right font-mono text-xs sm:text-sm"><?= $formatQuantity($holding['qty']) ?></td>
    <td class="px-6 py-4 text-right">
      <?= moneyfmt($holding['avg_cost'], $holding['currency']) ?>
    </td>
    <td class="px-6 py-4 text-right" data-role="holding-last">
      <?= $holding['last_price'] !== null ? moneyfmt($holding['last_price'], $holding['currency']) : '<span class="text-xs text-gray-400">stale</span>' ?>
    </td>
    <td class="px-6 py-4 text-right">
      <div data-role="holding-market"><?= moneyfmt($holding['market_value_base'], $baseCurrency) ?></div>
      <div class="text-xs text-gray-400" data-role="holding-market-ccy"><?= moneyfmt($holding['market_value_ccy'], $holding['currency']) ?></div>
    </td>
    <td class="px-6 py-4 text-right <?= $holding['unrealized_base'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
      <div data-role="holding-unrealized"><?= moneyfmt($holding['unrealized_base'], $baseCurrency) ?></div>
      <div class="text-xs text-gray-400" data-role="holding-unrealized-ccy"><?= moneyfmt($holding['unrealized_ccy'], $holding['currency']) ?></div>
      <?php if ($holding['unrealized_pct'] !== null): ?>
        <div class="text-xs font-medium <?= $holding['unrealized_base'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>"><?= number_format($holding['unrealized_pct'], 2) ?>%</div>
      <?php endif; ?>
    </td>
    <td class="px-6 py-4 text-right <?= $holding['day_pl_base'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>" data-role="holding-day">
      <div><?= moneyfmt($holding['day_pl_base'], $baseCurrency) ?></div>
      <div class="text-xs text-gray-400"><?= moneyfmt($holding['day_pl_ccy'], $holding['currency']) ?></div>
    </td>
    <td class="px-6 py-4 text-right">
      <?= number_format($holding['weight_pct'], 2) ?>%
      <?php if (!empty($holding['risk_note'])): ?>
        <span class="block text-xs text-rose-500"><?= htmlspecialchars($holding['risk_note']) ?></span>
      <?php endif; ?>
    </td>
    <td class="px-6 py-4 text-left">
      <?php if (!empty($suggestions)): ?>
        <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
          <?php foreach ($suggestions as $suggestion): ?>
            <li>• <?= htmlspecialchars($suggestion) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php elseif (!empty($signals)): ?>
        <span class="text-xs text-gray-500">Balanced</span>
      <?php else: ?>
        <span class="text-xs text-gray-400">–</span>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
