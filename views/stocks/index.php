<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium"><?= htmlspecialchars(__('stocks.portfolio.title')) ?></h2>
    <p class="text-2xl mt-2 font-semibold"><?= moneyfmt($portfolio_value) ?></p>
    <p class="text-xs text-gray-500"><?= htmlspecialchars(__('stocks.portfolio.subtitle')) ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="font-semibold mb-3"><?= htmlspecialchars(__('stocks.trade.buy_title')) ?></h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/stocks/buy">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="<?= htmlspecialchars(__('stocks.trade.symbol_placeholder')) ?>" required>
      <input name="quantity" type="number" step="0.000001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="<?= htmlspecialchars(__('stocks.trade.quantity_placeholder')) ?>" required>
      <input name="price" type="number" step="0.0001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="<?= htmlspecialchars(__('stocks.trade.price_placeholder')) ?>" required>
      <input name="currency" class="rounded-xl border-gray-300 sm:col-span-1" value="USD">
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1">
      <button class="bg-gray-900 text-white rounded-xl px-4"><?= htmlspecialchars(__('stocks.trade.buy_button')) ?></button>
    </form>

    <h3 class="font-semibold mt-6 mb-3"><?= htmlspecialchars(__('stocks.trade.sell_title')) ?></h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/stocks/sell">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="<?= htmlspecialchars(__('stocks.trade.symbol_placeholder')) ?>" required>
      <input name="quantity" type="number" step="0.000001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="<?= htmlspecialchars(__('stocks.trade.quantity_placeholder')) ?>" required>
      <input name="price" type="number" step="0.0001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="<?= htmlspecialchars(__('stocks.trade.price_placeholder')) ?>" required>
      <input name="currency" class="rounded-xl border-gray-300 sm:col-span-1" value="USD">
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1">
      <button class="bg-accent text-white rounded-xl px-4"><?= htmlspecialchars(__('stocks.trade.sell_button')) ?></button>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
    <h3 class="font-semibold mb-3"><?= htmlspecialchars(__('stocks.open_positions.title')) ?></h3>
    <table class="min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.symbol')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.quantity')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.avg_buy')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.cost')) ?></th></tr></thead>
      <tbody>
        <?php foreach($positions as $p): if((float)$p['qty']<=0) continue; $cost=(float)$p['qty']*(float)$p['avg_buy_price']; ?>
          <tr class="border-b">
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($p['symbol']) ?></td>
            <td class="py-2 pr-3"><?= (float)$p['qty'] ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($p['avg_buy_price']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($cost) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
    <h3 class="font-semibold mb-3"><?= htmlspecialchars(__('stocks.recent_trades.title')) ?></h3>
    <table class="min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.date')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.side')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.symbol')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.quantity')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.price')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('stocks.table.currency')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('common.actions')) ?></th></tr></thead>
      <tbody>
        <?php foreach($trades as $t): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= htmlspecialchars($t['trade_on']) ?></td>
            <?php
              $sideKey = strtolower((string)($t['side'] ?? ''));
              $sideLabel = __('stocks.side.' . $sideKey);
              if ($sideLabel === 'stocks.side.' . $sideKey) { $sideLabel = $sideKey !== '' ? ucfirst($sideKey) : ''; }
            ?>
            <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($sideLabel) ?></td>
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($t['symbol']) ?></td>
            <td class="py-2 pr-3"><?= (float)$t['quantity'] ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($t['price']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($t['currency']) ?></td>
            <td class="py-2 pr-3">
              <form method="post" action="/stocks/trade/delete" onsubmit="return confirm(<?= json_encode(__('stocks.delete_confirm')) ?>)">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $t['id'] ?>" />
                <button class="text-red-600"><?= htmlspecialchars(__('common.remove')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>