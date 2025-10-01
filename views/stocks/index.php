<?php
/** @var array $overview */
/** @var array $insights */
/** @var array $portfolioChart */
/** @var array $filters */
/** @var int $refreshSeconds */
/** @var array $userCurrencies */

$totals = $overview['totals'];
$holdings = $overview['holdings'];
$allocations = $overview['allocations'];
$watchlist = $overview['watchlist'];
$baseCurrency = $totals['base_currency'];
?>

<section class="space-y-6">
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

  <section class="grid md:grid-cols-3 gap-4">
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Total Market Value</h2>
      <p class="text-3xl font-semibold mt-2"><?= moneyfmt($totals['total_market_value'], $baseCurrency) ?></p>
      <p class="text-xs text-gray-500">Cash impact: <?= moneyfmt($totals['cash_impact'], $baseCurrency) ?></p>
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
      <button class="btn btn-primary sm:col-span-2">Submit trade</button>
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
})();
</script>
