<?php
require_once __DIR__.'/../layout/page_header.php';
require_once __DIR__.'/../layout/focus_panel.php';

render_page_header([
  'kicker' => __('Invest'),
  'title' => __('Track your stock positions'),
  'subtitle' => __('Log buys and sells, monitor open positions, and keep your cost basis tidy.'),
  'meta' => [
    ['icon' => 'pie-chart', 'label' => __('Open positions: :count', ['count' => count(array_filter($positions, fn($p) => (float)($p['qty'] ?? 0) > 0))])],
  ],
  'insight' => [
    'label' => __('Cost basis'),
    'value' => moneyfmt($portfolio_value),
    'subline' => __('Sum of quantity × average buy price for open lots.'),
  ],
  'actions' => [
    ['label' => __('Record a trade'), 'href' => '#trade-forms', 'icon' => 'plus', 'style' => 'primary'],
    ['label' => __('Review positions'), 'href' => '#open-positions', 'icon' => 'bar-chart-3', 'style' => 'muted'],
    ['label' => __('Download history'), 'href' => '#recent-trades', 'icon' => 'download', 'style' => 'link'],
  ],
  'tabs' => [
    ['label' => __('Trade'), 'href' => '#trade-forms', 'active' => true],
    ['label' => __('Positions'), 'href' => '#open-positions'],
    ['label' => __('History'), 'href' => '#recent-trades'],
  ],
]);

$openPositionsCount = count(array_filter($positions, static fn ($p) => (float)($p['qty'] ?? 0) > 0));
$tradeCount = is_array($trades ?? null) ? count($trades) : 0;

render_focus_panel([
  'id' => 'stocks-focus',
  'title' => __('Keep your investing journal tight'),
  'description' => __('Capture every trade, reconcile positions, and keep a paper trail for taxes.'),
  'items' => [
    [
      'icon' => 'plus-circle',
      'label' => __('Record the latest trade'),
      'description' => __('Log buys and sells with quantity, price, and currency the moment they happen.'),
      'href' => '#trade-forms',
      'state' => $tradeCount > 0 ? 'active' : 'warning',
      'state_label' => $tradeCount > 0 ? __('Keep logging') : __('No trades yet'),
    ],
    [
      'icon' => 'bar-chart-3',
      'label' => __('Review open positions'),
      'description' => __('Confirm quantity and average buy price so cost basis remains accurate.'),
      'href' => '#open-positions',
      'state' => $openPositionsCount > 0 ? 'success' : 'info',
      'state_label' => $openPositionsCount > 0 ? __('Positions tracked') : __('No holdings'),
      'meta' => __('Open: :count', ['count' => $openPositionsCount]),
    ],
    [
      'icon' => 'file-down',
      'label' => __('Download trade history'),
      'description' => __('Export activity before tax season or when sharing updates with your advisor.'),
      'href' => '#recent-trades',
      'state' => $tradeCount > 0 ? 'active' : 'info',
      'state_label' => $tradeCount > 0 ? __('Ready to export') : __('Log trades first'),
    ],
    [
      'icon' => 'notebook-pen',
      'label' => __('Jot notes about your thesis'),
      'description' => __('Add context to each trade by keeping a separate note or tagging entries via the memo field.'),
      'href' => '#recent-trades',
      'state' => 'info',
      'state_label' => __('Optional'),
    ],
  ],
  'side' => [
    'label' => __('Portfolio cost basis'),
    'value' => moneyfmt($portfolio_value),
    'subline' => __('Open positions: :count', ['count' => $openPositionsCount]),
    'footnote' => __('Trades recorded here feed yearly insights and help reconcile capital gains elsewhere.'),
    'actions' => [
      ['label' => __('See yearly performance'), 'href' => '/years', 'icon' => 'line-chart'],
      ['label' => __('Jump to dashboard'), 'href' => '/', 'icon' => 'layout-dashboard'],
    ],
  ],
  'tips' => [
    __('Use consistent symbol casing (e.g., AAPL) so duplicates don’t appear in the table.'),
    __('Record trades in native currency—conversions happen automatically in yearly summaries.'),
  ],
]);
?>

<section id="trade-forms" class="grid md:grid-cols-3 gap-4">
  <div class="card">
    <h2 class="font-medium">Portfolio (cost basis)</h2>
    <p class="text-2xl mt-2 font-semibold"><?= moneyfmt($portfolio_value) ?></p>
    <p class="text-xs text-gray-500">Sum of qty × avg buy price for open positions.</p>
  </div>
  <div class="card md:col-span-2">
    <h3 class="font-semibold mb-3">Trade — Buy</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/stocks/buy">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="input sm:col-span-1" placeholder="AAPL" required>
      <input name="quantity" type="number" step="0.000001" class="input sm:col-span-1" placeholder="Qty" required>
      <input name="price" type="number" step="0.0001" class="input sm:col-span-1" placeholder="Price" required>
      <input name="currency" class="input sm:col-span-1" value="USD">
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="input sm:col-span-1">
      <button class="btn btn-primary sm:col-span-1"><?= __('Buy') ?></button>
    </form>

    <h3 class="font-semibold mt-6 mb-3">Trade — Sell</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/stocks/sell">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="input sm:col-span-1" placeholder="AAPL" required>
      <input name="quantity" type="number" step="0.000001" class="input sm:col-span-1" placeholder="Qty" required>
      <input name="price" type="number" step="0.0001" class="input sm:col-span-1" placeholder="Price" required>
      <input name="currency" class="input sm:col-span-1" value="USD">
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="input sm:col-span-1">
      <button class="btn btn-danger sm:col-span-1"><?= __('Sell') ?></button>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div id="open-positions" class="card overflow-x-auto">
    <h3 class="font-semibold mb-3">Open Positions</h3>
    <table class="table-glass min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3">Symbol</th><th class="py-2 pr-3">Qty</th><th class="py-2 pr-3">Avg Buy</th><th class="py-2 pr-3">Cost</th></tr></thead>
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

  <div id="recent-trades" class="card overflow-x-auto">
    <h3 class="font-semibold mb-3">Recent Trades</h3>
    <table class="table-glass min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Side</th><th class="py-2 pr-3">Symbol</th><th class="py-2 pr-3">Qty</th><th class="py-2 pr-3">Price</th><th class="py-2 pr-3">Currency</th><th class="py-2 pr-3">Actions</th></tr></thead>
      <tbody>
        <?php foreach($trades as $t): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= htmlspecialchars($t['trade_on']) ?></td>
            <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($t['side']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($t['symbol']) ?></td>
            <td class="py-2 pr-3"><?= (float)$t['quantity'] ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($t['price']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($t['currency']) ?></td>
            <td class="py-2 pr-3">
              <form method="post" action="/stocks/trade/delete" onsubmit="return confirm('Delete trade?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $t['id'] ?>" />
                <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                  <i data-lucide="trash-2" class="h-4 w-4"></i>
                  <span class="sr-only"><?= __('Remove') ?></span>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>