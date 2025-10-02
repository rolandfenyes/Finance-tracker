<?php
/** @var array $transactions */
/** @var string $baseCurrency */

$formatQuantity = static function ($qty): string {
    $formatted = number_format((float)$qty, 6, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');
    return $trimmed === '' ? '0' : $trimmed;
};
?>

<section class="space-y-6">
  <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Transactions</h1>
      <p class="text-sm text-gray-500">A chronological ledger of every equity trade recorded for your account.</p>
    </div>
    <a href="/stocks" class="btn btn-ghost">Back to overview</a>
  </header>

  <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">All transactions</h2>
        <p class="text-xs text-gray-500">Newest orders first, including fractional quantities and legacy imports.</p>
      </div>
      <span class="text-xs text-gray-400">Total: <?= count($transactions) ?></span>
    </div>
    <div class="mt-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50/80 text-gray-600 uppercase text-xs tracking-wide dark:bg-gray-900/60">
          <tr>
            <th class="px-5 py-3 text-left">Date</th>
            <th class="px-5 py-3 text-left">Ticker</th>
            <th class="px-5 py-3 text-left">Side</th>
            <th class="px-5 py-3 text-right">Quantity</th>
            <th class="px-5 py-3 text-right">Price</th>
            <th class="px-5 py-3 text-right">Fee</th>
            <th class="px-5 py-3 text-right">Total</th>
            <th class="px-5 py-3 text-left">Note</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php if (empty($transactions)): ?>
            <tr>
              <td colspan="8" class="px-5 py-8 text-center text-gray-500">No trades recorded yet.</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($transactions as $trade):
            $executedAt = $trade['executed_at'] ?? null;
            $tradeOn = $trade['trade_on'] ?? null;
            $timestamp = $executedAt ?: $tradeOn;
            $displayDate = $timestamp ? date('Y-m-d H:i', strtotime($timestamp)) : '—';
            $side = strtoupper((string)($trade['side'] ?? ''));
            $quantityDisplay = $formatQuantity($trade['quantity'] ?? 0);
            $currency = $trade['currency'] ?? ($trade['stock_currency'] ?? $baseCurrency);
            $priceDisplay = moneyfmt((float)($trade['price'] ?? 0), $currency);
            $feeAmount = (float)($trade['fee'] ?? 0);
            $feeDisplay = $feeAmount !== 0.0 ? moneyfmt($feeAmount, $currency) : '—';
            $total = (float)($trade['quantity'] ?? 0) * (float)($trade['price'] ?? 0);
            $totalDisplay = moneyfmt($total, $currency);
            $note = $trade['note'] ?? '';
          ?>
            <tr>
              <td class="px-5 py-4 whitespace-nowrap text-gray-600 dark:text-gray-300"><?= htmlspecialchars($displayDate) ?></td>
              <td class="px-5 py-4 font-semibold text-gray-900 dark:text-gray-100">
                <?= htmlspecialchars($trade['symbol'] ?? '') ?>
                <?php if (!empty($trade['name'])): ?>
                  <span class="block text-xs text-gray-500"><?= htmlspecialchars($trade['name']) ?></span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $side === 'BUY' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200' ?>">
                  <?= htmlspecialchars($side) ?>
                </span>
              </td>
              <td class="px-5 py-4 text-right font-mono text-xs sm:text-sm"><?= htmlspecialchars($quantityDisplay) ?></td>
              <td class="px-5 py-4 text-right"><?= $priceDisplay ?></td>
              <td class="px-5 py-4 text-right"><?= $feeDisplay ?></td>
              <td class="px-5 py-4 text-right"><?= $totalDisplay ?></td>
              <td class="px-5 py-4 text-left text-xs text-gray-500">
                <?= $note !== '' ? htmlspecialchars($note) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>
