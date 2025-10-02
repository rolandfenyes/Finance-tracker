<?php
/** @var array $overview */
/** @var array $insights */
/** @var array $portfolioChart */
/** @var array $filters */
/** @var int $refreshSeconds */
/** @var array $userCurrencies */
/** @var null|string $error */

$totals = $overview['totals'];
$holdings = $overview['holdings'];
$allocations = $overview['allocations'];
$watchlist = $overview['watchlist'];
$cashEntries = $overview['cash'] ?? [];
$transactions = $overview['trades'] ?? [];
$baseCurrency = $totals['base_currency'];
$formatQuantity = static function ($qty): string {
  $formatted = number_format((float)$qty, 6, '.', '');
  $trimmed = rtrim(rtrim($formatted, '0'), '.');
  return $trimmed === '' ? '0' : $trimmed;
};
?>

<section class="space-y-6">
  <?php if (!empty($error) && $error === 'trade'): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
      Unable to record the trade. Please ensure the latest database migrations have been run and try again.
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100">
      <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-100">
      <?= htmlspecialchars($_SESSION['flash']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
  <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Stocks</h1>
      <p class="text-sm text-gray-500">Live overview of your equity portfolio across currencies.</p>
    </div>
    <form method="get" action="/stocks" class="grid grid-cols-1 sm:grid-cols-5 gap-2">
      <input type="search" name="q" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Search ticker" class="input sm:col-span-2" />
      <input type="text" name="sector" value="<?= htmlspecialchars($filters['sector'] ?? '') ?>" placeholder="Sector" class="input" />
      <input type="text" name="currency" value="<?= htmlspecialchars($filters['currency'] ?? '') ?>" placeholder="Currency" class="input" />
      <select name="period" class="input">
        <?php $period = strtoupper($filters['realized_period'] ?? 'YTD'); ?>
        <?php foreach(['YTD','1M','3M','1Y','ALL'] as $opt): ?>
          <option value="<?= $opt ?>" <?= $period === $opt ? 'selected' : '' ?>>Realized <?= $opt ?></option>
        <?php endforeach; ?>
      </select>
      <label class="inline-flex items-center justify-center rounded-lg bg-white/70 dark:bg-gray-900/40 border border-gray-200/70 px-3 text-sm font-medium text-gray-600 shadow-sm">
        <input type="checkbox" name="watchlist" value="1" <?= !empty($filters['watchlist_only']) ? 'checked' : '' ?> class="mr-2" />Watchlist only
      </label>
      <button class="btn btn-primary sm:col-span-1">Apply</button>
    </form>
  </header>

  <section class="grid md:grid-cols-4 gap-4">
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Total Market Value</h2>
      <p class="text-3xl font-semibold mt-2"><?= moneyfmt($totals['total_market_value'], $baseCurrency) ?></p>
      <p class="text-xs text-gray-500">Trade cash flow: <?= moneyfmt($totals['cash_impact'], $baseCurrency) ?></p>
    </article>
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Unrealized P/L</h2>
      <p class="text-3xl font-semibold mt-2 <?= $totals['unrealized_pl'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
        <?= moneyfmt($totals['unrealized_pl'], $baseCurrency) ?>
      </p>
      <p class="text-xs text-gray-500"><?= number_format($totals['unrealized_pct'], 2) ?>%</p>
    </article>
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Realized P/L (<?= htmlspecialchars($totals['realized_period']) ?>)</h2>
      <p class="text-3xl font-semibold mt-2 <?= $totals['realized_pl'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
        <?= moneyfmt($totals['realized_pl'], $baseCurrency) ?>
      </p>
      <p class="text-xs text-gray-500">Daily P/L: <?= moneyfmt($totals['daily_pl'], $baseCurrency) ?></p>
    </article>
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Cash Balance</h2>
      <p class="text-3xl font-semibold mt-2 text-slate-700 dark:text-slate-100">
        <?= moneyfmt($totals['cash_balance'] ?? 0, $baseCurrency) ?>
      </p>
      <?php if (!empty($cashEntries)): ?>
        <ul class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
          <?php foreach ($cashEntries as $entry): ?>
            <li>
              <?= moneyfmt($entry['amount'], $entry['currency']) ?>
              <span class="text-[11px] text-gray-400">(<?= moneyfmt($entry['amount_base'], $baseCurrency) ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-xs text-gray-500 mt-3">No cash entries yet.</p>
      <?php endif; ?>
    </article>
  </section>

  <section class="grid lg:grid-cols-5 gap-6">
    <div class="lg:col-span-3 card overflow-hidden shadow-md bg-white/80 dark:bg-gray-900/40">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200/70 dark:border-gray-800">
        <h3 class="text-lg font-semibold">Current holdings</h3>
        <span class="text-xs text-gray-500">Base currency: <?= htmlspecialchars($baseCurrency) ?></span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50/70 dark:bg-gray-900/60 text-gray-600 uppercase text-xs tracking-wide">
            <tr>
              <th class="px-5 py-3 text-left">Ticker</th>
              <th class="px-5 py-3 text-right">Qty</th>
              <th class="px-5 py-3 text-right">Avg Cost</th>
              <th class="px-5 py-3 text-right">Last</th>
              <th class="px-5 py-3 text-right">Market Value</th>
              <th class="px-5 py-3 text-right">Unrealized</th>
              <th class="px-5 py-3 text-right">Day P/L</th>
              <th class="px-5 py-3 text-right">Weight</th>
              <th class="px-5 py-3 text-left">Insights</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($holdings)): ?>
              <tr>
                <td colspan="9" class="px-5 py-6 text-center text-gray-500">No holdings yet. Record your first trade below.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($holdings as $holding):
              $symbol = $holding['symbol'];
              $suggestions = $insights[$symbol]['suggestions'] ?? [];
              $signals = $insights[$symbol]['signals'] ?? [];
            ?>
              <tr class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50/70 dark:hover:bg-gray-900/40" data-symbol="<?= htmlspecialchars($symbol) ?>" data-qty="<?= htmlspecialchars($holding['qty']) ?>" data-avg="<?= htmlspecialchars($holding['avg_cost']) ?>" data-currency="<?= htmlspecialchars($holding['currency']) ?>">
                <td class="px-5 py-4 font-semibold text-gray-900 dark:text-gray-100">
                  <a href="/stocks/<?= urlencode($symbol) ?>" class="hover:text-emerald-500 transition"><?= htmlspecialchars($symbol) ?></a>
                  <span class="block text-xs text-gray-500"><?= htmlspecialchars($holding['name'] ?? '') ?></span>
                </td>
                <td class="px-5 py-4 text-right"><?= number_format($holding['qty'], 4) ?></td>
                <td class="px-5 py-4 text-right">
                  <?= moneyfmt($holding['avg_cost'], $holding['currency']) ?>
                </td>
                <td class="px-5 py-4 text-right" data-role="holding-last">
                  <?= $holding['last_price'] !== null ? moneyfmt($holding['last_price'], $holding['currency']) : '<span class="text-xs text-gray-400">stale</span>' ?>
                </td>
                <td class="px-5 py-4 text-right">
                  <div data-role="holding-market"><?= moneyfmt($holding['market_value_base'], $baseCurrency) ?></div>
                  <div class="text-xs text-gray-400" data-role="holding-market-ccy"><?= moneyfmt($holding['market_value_ccy'], $holding['currency']) ?></div>
                </td>
                <td class="px-5 py-4 text-right <?= $holding['unrealized_base'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
                  <div data-role="holding-unrealized"><?= moneyfmt($holding['unrealized_base'], $baseCurrency) ?></div>
                  <div class="text-xs text-gray-400" data-role="holding-unrealized-ccy"><?= moneyfmt($holding['unrealized_ccy'], $holding['currency']) ?></div>
                </td>
                <td class="px-5 py-4 text-right <?= $holding['day_pl_base'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>" data-role="holding-day">
                  <?= moneyfmt($holding['day_pl_base'], $baseCurrency) ?>
                </td>
                <td class="px-5 py-4 text-right">
                  <?= number_format($holding['weight_pct'], 2) ?>%
                  <?php if (!empty($holding['risk_note'])): ?>
                    <span class="block text-xs text-rose-500"><?= htmlspecialchars($holding['risk_note']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="px-5 py-4 text-left">
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
          </tbody>
        </table>
      </div>
    </div>
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
        <h3 class="text-lg font-semibold mb-3">Allocation by ticker</h3>
        <canvas id="allocationTickerChart" height="220"></canvas>
      </div>
      <div class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
        <h3 class="text-lg font-semibold mb-3">Portfolio value</h3>
        <canvas id="portfolioValueChart" height="220"></canvas>
      </div>
    </div>
  </section>

  <section class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-lg font-semibold">All transactions</h3>
        <p class="text-xs text-gray-500">Newest orders first, including fractional quantities.</p>
      </div>
      <span class="text-xs text-gray-400">Total: <?= count($transactions) ?></span>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50/70 dark:bg-gray-900/60 text-gray-600 uppercase text-xs tracking-wide">
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
        <tbody>
          <?php if (empty($transactions)): ?>
            <tr>
              <td colspan="8" class="px-5 py-6 text-center text-gray-500">No trades recorded yet.</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($transactions as $trade):
            $executedAt = $trade['executed_at'] ?? null;
            $tradeOn = $trade['trade_on'] ?? null;
            $timestamp = $executedAt ?: $tradeOn;
            $displayDate = $timestamp ? date('Y-m-d H:i', strtotime($timestamp)) : '—';
            $side = strtoupper((string)($trade['side'] ?? ''));
            $quantityDisplay = $formatQuantity($trade['quantity'] ?? 0);
            $priceDisplay = moneyfmt((float)($trade['price'] ?? 0), $trade['currency'] ?? 'USD');
            $feeAmount = (float)($trade['fee'] ?? 0);
            $feeDisplay = $feeAmount !== 0.0 ? moneyfmt($feeAmount, $trade['currency'] ?? 'USD') : '—';
            $total = (float)($trade['quantity'] ?? 0) * (float)($trade['price'] ?? 0);
            $totalDisplay = moneyfmt($total, $trade['currency'] ?? 'USD');
            $note = $trade['note'] ?? '';
          ?>
            <tr class="border-t border-gray-100 dark:border-gray-800">
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
  </section>

  <section class="grid lg:grid-cols-2 gap-6">
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h3 class="text-lg font-semibold mb-3">Record trade</h3>
      <form method="post" action="/stocks/trade" class="grid sm:grid-cols-6 gap-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input name="symbol" placeholder="AAPL" class="input" required />
        <select name="side" class="input">
          <option value="BUY">Buy</option>
          <option value="SELL">Sell</option>
        </select>
        <input name="quantity" type="number" step="0.0001" placeholder="Qty" class="input" required />
        <input name="price" type="number" step="0.0001" placeholder="Price" class="input" required />
        <select name="currency" class="input">
          <?php if (!empty($userCurrencies)): ?>
            <?php $hasSelected = false; ?>
            <?php foreach ($userCurrencies as $index => $c): ?>
              <?php
                $code = strtoupper($c['code']);
                $isSelected = !empty($c['is_main']) || (!$hasSelected && $index === 0);
                if ($isSelected) { $hasSelected = true; }
              ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= htmlspecialchars($code) ?>
              </option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="USD" selected>USD</option>
          <?php endif; ?>
        </select>
        <input name="fee" type="number" step="0.01" placeholder="Fee" class="input" />
        <input name="trade_date" type="date" value="<?= date('Y-m-d') ?>" class="input" />
        <input name="trade_time" type="time" value="<?= date('H:i') ?>" class="input" />
        <input name="note" placeholder="Note" class="input sm:col-span-2" />
        <button class="btn btn-primary sm:col-span-6">Save trade</button>
      </form>
    </article>
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h3 class="text-lg font-semibold mb-3">Record cash movement</h3>
      <form method="post" action="/stocks/cash" class="grid sm:grid-cols-6 gap-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <select name="cash_action" class="input">
          <option value="deposit">Add cash</option>
          <option value="withdraw">Withdraw cash</option>
        </select>
        <input name="cash_amount" type="number" step="0.01" placeholder="Amount" class="input sm:col-span-2" required />
        <select name="cash_currency" class="input">
          <?php if (!empty($userCurrencies)): ?>
            <?php $hasCashSelected = false; ?>
            <?php foreach ($userCurrencies as $index => $c): ?>
              <?php
                $code = strtoupper($c['code']);
                $isSelected = !empty($c['is_main']) || (!$hasCashSelected && $index === 0);
                if ($isSelected) { $hasCashSelected = true; }
              ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= htmlspecialchars($code) ?>
              </option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="USD" selected>USD</option>
          <?php endif; ?>
        </select>
        <input name="cash_date" type="date" value="<?= date('Y-m-d') ?>" class="input" />
        <input name="cash_time" type="time" value="<?= date('H:i') ?>" class="input" />
        <input name="cash_note" placeholder="Note" class="input sm:col-span-3" />
        <button class="btn btn-secondary sm:col-span-6">Save cash entry</button>
      </form>
    </article>
  </section>

  <section class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <h3 class="text-lg font-semibold">Import trades from CSV</h3>
        <p class="text-xs text-gray-500">Upload broker exports to backfill BUY/SELL activity. Cash top-ups, withdrawals, and dividends are kept out of positions automatically.</p>
      </div>
      <form method="post" action="/stocks/clear" onsubmit="return confirm('Clear all recorded stock trades? This cannot be undone.');" class="shrink-0">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <button class="btn btn-danger">Clear history</button>
      </form>
    </div>
    <form method="post" action="/stocks/import" enctype="multipart/form-data" class="space-y-3" id="stocksCsvForm">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="file" name="csv" accept=".csv,text/csv" class="input" required />
      <p class="text-xs text-gray-500">We match columns named Date, Ticker/Symbol, Type, Quantity, Price per share, Total Amount, Currency, and Fee when available.</p>
      <button class="btn btn-secondary">Upload CSV</button>
      <div class="hidden rounded-lg border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100" data-role="csv-progress">
        <div class="flex items-center justify-between mb-2">
          <span data-role="csv-progress-text">Preparing upload…</span>
          <span data-role="csv-progress-value">0%</span>
        </div>
        <div class="h-2 rounded-full bg-emerald-200/60 dark:bg-emerald-500/30">
          <div class="h-full w-0 rounded-full bg-emerald-500 transition-all duration-300" data-role="csv-progress-bar"></div>
        </div>
      </div>
    </form>
  </section>

  <?php if (!empty($watchlist)): ?>
  <section class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
    <h3 class="text-lg font-semibold mb-4">Watchlist</h3>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4" id="watchlistStrip">
      <?php foreach ($watchlist as $item): ?>
        <article class="p-4 rounded-2xl border border-gray-100 dark:border-gray-800 bg-white/60 dark:bg-gray-900/40 shadow-sm flex flex-col gap-1" data-symbol="<?= htmlspecialchars($item['symbol']) ?>">
          <div class="flex items-center justify-between">
            <a href="/stocks/<?= urlencode($item['symbol']) ?>" class="font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($item['symbol']) ?></a>
            <span class="text-xs text-gray-500"><?= htmlspecialchars($item['currency']) ?></span>
          </div>
          <div class="text-xl font-semibold text-gray-800 dark:text-gray-100" data-role="last">
            <?= $item['last_price'] !== null ? moneyfmt($item['last_price'], $item['currency']) : '—' ?>
          </div>
          <div class="text-xs text-gray-500" data-role="change">Prev: <?= $item['prev_close'] !== null ? moneyfmt($item['prev_close'], $item['currency']) : '—' ?></div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb" crossorigin="anonymous"></script>
<script>
(function(){
  const allocationData = <?= json_encode($allocations['by_ticker']) ?>;
  const portfolioSeries = <?= json_encode($portfolioChart['series']) ?>;
  const portfolioLabels = <?= json_encode($portfolioChart['labels']) ?>;
  const holdings = <?= json_encode(array_map(fn($h)=>$h['symbol'], $holdings)) ?>;
  const watchlist = <?= json_encode(array_map(fn($w)=>$w['symbol'], $watchlist)) ?>;
  const refreshSeconds = <?= (int)$refreshSeconds ?> * 1000;

  const allocationCtx = document.getElementById('allocationTickerChart');
  if (allocationCtx && allocationData.length) {
    const labels = allocationData.map(item => item.label);
    const data = allocationData.map(item => item.weight_pct);
    new Chart(allocationCtx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: labels.map((_, idx) => `rgba(76, 175, 80, ${0.8 - idx * 0.05})`)
        }]
      },
      options: {
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }

  const valueCtx = document.getElementById('portfolioValueChart');
  if (valueCtx && portfolioSeries.length) {
    new Chart(valueCtx, {
      type: 'line',
      data: {
        labels: portfolioLabels,
        datasets: [{
          data: portfolioSeries,
          borderColor: '#34d399',
          tension: 0.3,
          fill: true,
          backgroundColor: 'rgba(52, 211, 153, 0.12)'
        }]
      },
      options: {
        scales: {
          x: { display: false },
          y: { ticks: { callback: value => new Intl.NumberFormat(undefined, { style: 'currency', currency: '<?= $baseCurrency ?>' }).format(value) } }
        },
        plugins: { legend: { display: false } }
      }
    });
  }

  const symbols = Array.from(new Set([...holdings, ...watchlist]));
  const panels = document.querySelectorAll('#watchlistStrip [data-symbol]');
  const updatePanels = (quotes) => {
    panels.forEach(panel => {
      const symbol = panel.getAttribute('data-symbol');
      const quote = quotes[symbol];
      if (!quote) return;
      const lastEl = panel.querySelector('[data-role="last"]');
      const changeEl = panel.querySelector('[data-role="change"]');
      if (lastEl && quote.last !== null) {
        lastEl.textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency: quote.currency || '<?= $baseCurrency ?>' }).format(quote.last);
      }
      if (changeEl && quote.prev_close !== null && quote.last !== null) {
        const diff = quote.last - quote.prev_close;
        const pct = quote.prev_close ? (diff / quote.prev_close) * 100 : 0;
        changeEl.textContent = `${diff >= 0 ? '+' : ''}${diff.toFixed(2)} (${pct.toFixed(2)}%)`;
        changeEl.classList.toggle('text-emerald-500', diff >= 0);
        changeEl.classList.toggle('text-rose-500', diff < 0);
      }
    });
    document.querySelectorAll('tr[data-symbol]').forEach(row => {
      const symbol = row.getAttribute('data-symbol');
      const quote = quotes[symbol];
      if (!quote) return;
      const currency = row.getAttribute('data-currency') || quote.currency || '<?= $baseCurrency ?>';
      const qty = parseFloat(row.getAttribute('data-qty') || '0');
      const avg = parseFloat(row.getAttribute('data-avg') || '0');
      const lastCell = row.querySelector('[data-role="holding-last"]');
      if (lastCell && quote.last !== null) {
        lastCell.textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(quote.last);
      }
      const marketCcy = row.querySelector('[data-role="holding-market-ccy"]');
      if (marketCcy && quote.last !== null) {
        marketCcy.textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(qty * quote.last);
      }
      const unrealizedCcy = row.querySelector('[data-role="holding-unrealized-ccy"]');
      if (quote.last !== null && unrealizedCcy) {
        const diffCcy = (quote.last - avg) * qty;
        unrealizedCcy.textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(diffCcy);
      }
    });
  };

  async function pollQuotes(){
    if (!symbols.length) return;
    try {
      const resp = await fetch(`/api/stocks/live?symbols=${symbols.join(',')}`, { headers: { 'Accept': 'application/json' } });
      if (!resp.ok) return;
      const data = await resp.json();
      if (data && data.quotes) {
        const lookup = {};
        data.quotes.forEach(q => { lookup[q.symbol] = q; });
        updatePanels(lookup);
      }
    } catch (err) {
      console.warn('Live quotes polling failed', err);
    }
  }

  pollQuotes();
  let pollTimer = setInterval(pollQuotes, refreshSeconds);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(pollTimer);
    } else {
      pollQuotes();
      pollTimer = setInterval(pollQuotes, refreshSeconds);
    }
  });

  const csvForm = document.getElementById('stocksCsvForm');
  if (csvForm) {
    const progressBox = csvForm.querySelector('[data-role="csv-progress"]');
    const progressBar = csvForm.querySelector('[data-role="csv-progress-bar"]');
    const progressValue = csvForm.querySelector('[data-role="csv-progress-value"]');
    const progressText = csvForm.querySelector('[data-role="csv-progress-text"]');

    csvForm.addEventListener('submit', (event) => {
      if (csvForm.dataset.uploading === '1') {
        event.preventDefault();
        return;
      }

      event.preventDefault();
      const formData = new FormData(csvForm);
      const xhr = new XMLHttpRequest();
      csvForm.dataset.uploading = '1';

      if (progressBox) {
        progressBox.classList.remove('hidden');
      }
      if (progressBar) {
        progressBar.style.width = '5%';
      }
      if (progressValue) {
        progressValue.textContent = '0%';
      }
      if (progressText) {
        progressText.textContent = 'Uploading CSV…';
      }

      xhr.open('POST', '/stocks/import');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', (e) => {
        if (!e.lengthComputable) {
          return;
        }
        const percent = Math.min(95, Math.round((e.loaded / e.total) * 90));
        if (progressBar) {
          progressBar.style.width = percent + '%';
        }
        if (progressValue) {
          progressValue.textContent = percent + '%';
        }
        if (progressText) {
          progressText.textContent = 'Processing trades…';
        }
      });

      const handleFailure = (message) => {
        if (progressBar) {
          progressBar.style.width = '100%';
          progressBar.classList.remove('bg-emerald-500');
          progressBar.classList.add('bg-rose-500');
        }
        if (progressValue) {
          progressValue.textContent = '100%';
        }
        if (progressText) {
          progressText.textContent = message || 'Upload failed. Please try again.';
        }
        csvForm.dataset.uploading = '0';
      };

      xhr.addEventListener('error', () => handleFailure('Network error while uploading.'));

      xhr.addEventListener('load', () => {
        let response = null;
        try {
          response = xhr.responseText ? JSON.parse(xhr.responseText) : null;
        } catch (err) {
          response = null;
        }

        if (xhr.status < 200 || xhr.status >= 300 || !response || response.success === false) {
          handleFailure(response && response.message ? response.message : 'Import failed.');
          return;
        }

        if (progressBar) {
          progressBar.style.width = '100%';
          progressBar.classList.remove('bg-rose-500');
          progressBar.classList.add('bg-emerald-500');
        }
        if (progressValue) {
          progressValue.textContent = '100%';
        }
        if (progressText) {
          progressText.textContent = response.message || 'Import complete. Refreshing…';
        }

        setTimeout(() => {
          window.location.reload();
        }, 600);
      });

      xhr.send(formData);
    });
  }
})();
</script>
