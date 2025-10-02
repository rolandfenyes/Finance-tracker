<?php
/** @var array $overview */
/** @var array $insights */
/** @var array $portfolioChart */
/** @var array $chartMeta */
/** @var string $chartRange */
/** @var array $filters */
/** @var int $refreshSeconds */
/** @var array $userCurrencies */
/** @var null|string $error */

$totals = $overview['totals'];
$holdings = $overview['holdings'];
$allocations = $overview['allocations'];
$watchlist = $overview['watchlist'];
$cashEntries = $overview['cash'] ?? [];
$baseCurrency = $totals['base_currency'];
$rangeOptions = ['1M', '3M', '6M', '1Y', '5Y'];

$currencyContext = $currencyContext ?? [
    'marketByCurrency' => [],
    'unrealizedByCurrency' => [],
    'realizedByCurrency' => [],
    'cashByCurrency' => [],
    'preferredCurrency' => $baseCurrency,
    'preferredMarket' => null,
    'preferredUnrealized' => null,
    'preferredRealized' => null,
    'preferredCash' => null,
];
$marketByCurrency = $currencyContext['marketByCurrency'];
$unrealizedByCurrency = $currencyContext['unrealizedByCurrency'];
$realizedByCurrency = $currencyContext['realizedByCurrency'];
$cashByCurrency = $currencyContext['cashByCurrency'];
$preferredCurrency = $currencyContext['preferredCurrency'];
$preferredMarket = $currencyContext['preferredMarket'];
$preferredUnrealized = $currencyContext['preferredUnrealized'];
$preferredRealized = $currencyContext['preferredRealized'];
$preferredCash = $currencyContext['preferredCash'];

$formatQuantity = static function ($qty): string {
    $formatted = number_format((float)$qty, 6, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');
    return $trimmed === '' ? '0' : $trimmed;
};
?>

<section class="space-y-8">
  <?php if (!empty($error) && $error === 'trade'): ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
      Unable to record the trade. Please ensure the latest database migrations have been run and try again.
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100">
      <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-100">
      <?= htmlspecialchars($_SESSION['flash']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>

  <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
    <div class="space-y-2">
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Stocks</h1>
      <p class="text-sm text-gray-500">A live look at your equity exposure, performance, and cash firepower.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="post" action="/stocks/refresh" class="inline-flex items-center gap-2" data-role="refresh-form">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/stocks', ENT_QUOTES) ?>" />
        <input type="hidden" name="q" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" />
        <input type="hidden" name="sector" value="<?= htmlspecialchars($filters['sector'] ?? '') ?>" />
        <input type="hidden" name="currency" value="<?= htmlspecialchars($filters['currency'] ?? '') ?>" />
        <input type="hidden" name="period" value="<?= htmlspecialchars($filters['realized_period'] ?? 'YTD') ?>" />
        <input type="hidden" name="format" value="json" />
        <?php if (!empty($filters['watchlist_only'])): ?>
          <input type="hidden" name="watchlist" value="1" />
        <?php endif; ?>
        <input type="hidden" name="chartRange" value="<?= htmlspecialchars($chartRange) ?>" />
        <button class="btn btn-ghost" type="submit" data-role="refresh-submit">Refresh quotes</button>
        <span class="text-xs text-gray-500" data-role="refresh-status" aria-live="polite"></span>
      </form>
      <a href="/stocks/transactions" class="btn btn-ghost">Transactions</a>
      <button type="button" class="btn btn-secondary" data-dialog-open="cashDialog">Record cash</button>
      <button type="button" class="btn btn-primary" data-dialog-open="tradeDialog">New trade</button>
    </div>
  </header>

  <form method="get" action="/stocks" class="grid grid-cols-1 gap-3 rounded-2xl border border-gray-200/70 bg-white/80 p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/40 sm:grid-cols-6">
    <input type="search" name="q" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Search ticker" class="input sm:col-span-2" />
    <input type="text" name="sector" value="<?= htmlspecialchars($filters['sector'] ?? '') ?>" placeholder="Sector" class="input sm:col-span-1" />
    <input type="text" name="currency" value="<?= htmlspecialchars($filters['currency'] ?? '') ?>" placeholder="Currency" class="input sm:col-span-1" />
    <select name="period" class="input">
      <?php $period = strtoupper($filters['realized_period'] ?? 'YTD'); ?>
      <?php foreach(['YTD','1M','3M','1Y','ALL'] as $opt): ?>
        <option value="<?= $opt ?>" <?= $period === $opt ? 'selected' : '' ?>>Realized <?= $opt ?></option>
      <?php endforeach; ?>
    </select>
    <label class="inline-flex items-center justify-center rounded-lg bg-white/70 px-3 text-sm font-medium text-gray-600 shadow-sm dark:bg-gray-900/60 dark:text-gray-300">
      <input type="checkbox" name="watchlist" value="1" <?= !empty($filters['watchlist_only']) ? 'checked' : '' ?> class="mr-2" />Watchlist only
    </label>
    <button class="btn btn-primary sm:col-span-1">Apply</button>
    <input type="hidden" name="chartRange" value="<?= htmlspecialchars($chartRange) ?>" />
  </form>

  <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-800 p-6 text-white shadow-xl">
    <div class="absolute -top-20 -right-20 h-60 w-60 rounded-full bg-emerald-500/20 blur-3xl"></div>
    <div class="absolute bottom-0 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-emerald-400/20 blur-3xl"></div>
    <div id="overviewHeroContent">
      <?php include __DIR__ . '/partials/overview_hero.php'; ?>
    </div>
  </section>

  <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" id="overviewCards">
    <?php include __DIR__ . '/partials/overview_cards.php'; ?>
  </section>

  <section class="grid gap-6 xl:grid-cols-3">
    <article class="xl:col-span-2 rounded-2xl border border-gray-200/70 bg-white/80 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
        <div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Current holdings</h3>
          <p class="text-xs text-gray-500">Base currency: <?= htmlspecialchars($baseCurrency) ?></p>
        </div>
        <div class="text-xs text-gray-400">Updated live</div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50/80 text-gray-600 uppercase text-xs tracking-wide dark:bg-gray-900/60">
            <tr>
              <th class="px-6 py-3 text-left">Ticker</th>
              <th class="px-6 py-3 text-right">Qty</th>
              <th class="px-6 py-3 text-right">Avg cost</th>
              <th class="px-6 py-3 text-right">Last</th>
              <th class="px-6 py-3 text-right">Market value</th>
              <th class="px-6 py-3 text-right">Unrealized</th>
              <th class="px-6 py-3 text-right">Day P/L</th>
              <th class="px-6 py-3 text-right">Weight</th>
              <th class="px-6 py-3 text-left">Insights</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="holdingsTableBody">
            <?php include __DIR__ . '/partials/holdings_rows.php'; ?>
          </tbody>
        </table>
      </div>
    </article>
    <div class="space-y-6">
      <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
        <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">Allocation by ticker</h3>
        <canvas id="allocationTickerChart" height="220"></canvas>
      </article>
      <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
        <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">Quick actions</h3>
        <p class="text-sm text-gray-500">Need to adjust cash or rebalance? Use the buttons below.</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary" data-dialog-open="tradeDialog">Record trade</button>
          <button type="button" class="btn btn-secondary" data-dialog-open="cashDialog">Record cash movement</button>
          <a href="/stocks/transactions" class="btn btn-ghost">View transactions</a>
        </div>
      </article>
    </div>
  </section>

  <?php if (!empty($watchlist)): ?>
    <section class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Watchlist</h3>
        <span class="text-xs text-gray-400">Auto-refreshing</span>
      </div>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" id="watchlistStrip">
        <?php foreach ($watchlist as $item): ?>
          <article class="flex flex-col gap-1 rounded-2xl border border-gray-100 bg-white/70 p-4 shadow-sm transition dark:border-gray-800 dark:bg-gray-900/40" data-symbol="<?= htmlspecialchars($item['symbol']) ?>">
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

  <section class="rounded-2xl border border-gray-200/70 bg-white/80 p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Import trades from CSV</h3>
        <p class="text-xs text-gray-500">Upload broker exports to backfill BUY/SELL activity. Cash top-ups, withdrawals, dividends, and fees are reconciled automatically.</p>
      </div>
      <form method="post" action="/stocks/clear" onsubmit="return confirm('Clear all recorded stock trades? This cannot be undone.');" class="shrink-0">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <button class="btn btn-danger">Clear history</button>
      </form>
    </div>
    <form method="post" action="/stocks/import" enctype="multipart/form-data" class="mt-4 space-y-3" id="stocksCsvForm">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="file" name="csv" accept=".csv,text/csv" class="input" required />
      <p class="text-xs text-gray-500">We match columns named Date, Ticker/Symbol, Type, Quantity, Price per share, Total Amount, Currency, and Fee when available.</p>
      <button class="btn btn-secondary">Upload CSV</button>
      <div class="hidden rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100" data-role="csv-progress">
        <div class="flex items-center justify-between mb-2">
          <span data-role="csv-progress-text">Preparing upload…</span>
          <span data-role="csv-progress-value">0%</span>
        </div>
        <div class="h-2 rounded-full bg-emerald-200/60 dark:bg-emerald-500/30">
          <div class="h-full w-0 rounded-full bg-emerald-500 transition-all duration-300" data-role="csv-progress-bar"></div>
        </div>
      </div>
      <div class="hidden rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100" data-role="csv-server-progress">
        <div class="flex items-center justify-between mb-2">
          <span data-role="csv-server-text">Reconciling portfolio…</span>
          <span data-role="csv-server-value">0%</span>
        </div>
        <div class="h-2 rounded-full bg-emerald-200/60 dark:bg-emerald-500/30">
          <div class="h-full w-0 rounded-full bg-emerald-500 transition-all duration-300" data-role="csv-server-bar"></div>
        </div>
      </div>
    </form>
  </section>
</section>

<div class="dialog hidden" id="tradeDialog" role="dialog" aria-modal="true" aria-labelledby="tradeDialogTitle">
  <div class="dialog-backdrop" data-dialog-close></div>
  <div class="dialog-panel">
    <div class="dialog-header">
      <h2 id="tradeDialogTitle" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Record trade</h2>
      <button type="button" class="dialog-close" data-dialog-close>&times;</button>
    </div>
    <form method="post" action="/stocks/trade" class="grid gap-3 sm:grid-cols-6">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" placeholder="AAPL" class="input sm:col-span-2" required />
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
            <option value="<?= htmlspecialchars($code) ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="USD" selected>USD</option>
        <?php endif; ?>
      </select>
      <input name="fee" type="number" step="0.01" placeholder="Fee" class="input" />
      <input name="trade_date" type="date" value="<?= date('Y-m-d') ?>" class="input" />
      <input name="trade_time" type="time" value="<?= date('H:i') ?>" class="input" />
      <input name="note" placeholder="Note" class="input sm:col-span-3" />
      <button class="btn btn-primary sm:col-span-6">Save trade</button>
    </form>
  </div>
</div>

<div class="dialog hidden" id="cashDialog" role="dialog" aria-modal="true" aria-labelledby="cashDialogTitle">
  <div class="dialog-backdrop" data-dialog-close></div>
  <div class="dialog-panel">
    <div class="dialog-header">
      <h2 id="cashDialogTitle" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Record cash movement</h2>
      <button type="button" class="dialog-close" data-dialog-close>&times;</button>
    </div>
    <form method="post" action="/stocks/cash" class="grid gap-3 sm:grid-cols-6">
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
            <option value="<?= htmlspecialchars($code) ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
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
  </div>
</div>

<style>
  .dialog { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; }
  .dialog.hidden { display: none; }
  .dialog-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(2px); }
  .dialog-panel { position: relative; width: min(600px, 92vw); max-height: 90vh; overflow-y: auto; border-radius: 1.25rem; background: var(--color-surface, rgba(255,255,255,0.98)); padding: 1.75rem; box-shadow: 0 35px 60px -25px rgba(15, 23, 42, 0.45); }
  .dark .dialog-panel { background: rgba(15, 23, 42, 0.92); }
  .dialog-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
  .dialog-close { width: 2.5rem; height: 2.5rem; border-radius: 9999px; font-size: 1.5rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center; color: rgba(100,116,139,0.8); background: rgba(148,163,184,0.12); }
  .dialog-close:hover { background: rgba(148,163,184,0.2); }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
<script>
(function(){
  let allocationData = <?= json_encode($allocations['by_ticker']) ?>;
  let portfolioLabels = <?= json_encode($portfolioChart['labels']) ?>;
  let portfolioSeries = <?= json_encode($portfolioChart['series']) ?>;
  let holdingsSymbols = <?= json_encode(array_map(static fn($h) => $h['symbol'], $holdings)) ?>;
  let watchlistSymbols = <?= json_encode(array_map(static fn($w) => $w['symbol'], $watchlist)) ?>;
  const refreshSeconds = <?= (int)$refreshSeconds ?> * 1000;
  let chartCurrency = <?= json_encode($baseCurrency) ?>;
  let baseCurrencyCode = <?= json_encode($baseCurrency) ?>;
  let baseFormatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: baseCurrencyCode });
  let cashSeries = <?= json_encode(array_fill(0, count($portfolioChart['series']), (float)$totals['cash_balance'])) ?>;
  let allocationChart = null;
  let portfolioChartInstance = null;
  let contributionVisible = false;
  let contributionsToggle = null;
  let contributionListener = null;

  const parseNumber = (value) => {
    if (value === null || value === undefined || value === '') return 0;
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  };

  const applyGainClass = (element, amount) => {
    if (!element) return;
    element.classList.remove('text-emerald-500', 'text-rose-500');
    if (!Number.isFinite(amount)) return;
    element.classList.add(amount >= 0 ? 'text-emerald-500' : 'text-rose-500');
  };

  const updateBaseFormatter = (currency) => {
    if (!currency) return;
    baseCurrencyCode = currency;
    baseFormatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: baseCurrencyCode });
  };

  const allocationCtx = document.getElementById('allocationTickerChart');
  if (allocationCtx && allocationData.length) {
    const labels = allocationData.map(item => item.label);
    const data = allocationData.map(item => item.weight_pct);
    allocationChart = new Chart(allocationCtx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: labels.map((_, idx) => `rgba(94, 234, 212, ${0.85 - idx * 0.05})`),
          borderWidth: 0,
          hoverOffset: 6
        }]
      },
      options: {
        plugins: { legend: { position: 'bottom', labels: { color: '#4b5563' } } }
      }
    });
  }

  function bindContributionsToggle() {
    const toggle = document.querySelector('[data-role="chart-contributions"]');
    if (contributionsToggle && contributionListener) {
      contributionsToggle.removeEventListener('change', contributionListener);
    }
    contributionsToggle = toggle;
    if (!contributionsToggle) {
      contributionListener = null;
      return;
    }
    contributionsToggle.checked = contributionVisible;
    contributionListener = () => {
      contributionVisible = contributionsToggle.checked;
      if (portfolioChartInstance && portfolioChartInstance.data.datasets[1]) {
        portfolioChartInstance.data.datasets[1].hidden = !contributionVisible;
        portfolioChartInstance.update();
      }
    };
    contributionsToggle.addEventListener('change', contributionListener);
  }

  function renderPortfolioChart(labels, series, cash) {
    const canvas = document.getElementById('portfolioValueChart');
    if (!canvas) {
      bindContributionsToggle();
      return;
    }
    if (portfolioChartInstance) {
      portfolioChartInstance.destroy();
      portfolioChartInstance = null;
    }
    if (!series || !series.length) {
      bindContributionsToggle();
      return;
    }
    const ctx = canvas.getContext('2d');
    const height = canvas.height || canvas.clientHeight || 200;
    const gradient = ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.45)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
    const cashGradient = ctx.createLinearGradient(0, 0, 0, height);
    cashGradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
    cashGradient.addColorStop(1, 'rgba(59, 130, 246, 0.05)');
    const formatCurrency = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: chartCurrency }).format(value);

    portfolioChartInstance = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Portfolio',
          data: series,
          borderColor: '#34d399',
          backgroundColor: gradient,
          tension: 0.35,
          fill: true,
          pointRadius: 0,
          spanGaps: true
        }, {
          label: 'Cash',
          data: cash,
          borderColor: '#3b82f6',
          backgroundColor: cashGradient,
          tension: 0.3,
          fill: true,
          pointRadius: 0,
          spanGaps: true,
          hidden: !contributionVisible
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: { display: false },
          y: {
            grid: { color: 'rgba(255,255,255,0.08)' },
            ticks: {
              color: 'rgba(255,255,255,0.6)',
              callback: value => formatCurrency(value)
            }
          }
        },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: context => `${context.dataset.label}: ${formatCurrency(context.parsed.y || 0)}`
            }
          }
        }
      }
    });
    bindContributionsToggle();
  }

  function updateAllocation(labels, weights) {
    if (!allocationChart) return;
    allocationChart.data.labels = labels;
    allocationChart.data.datasets[0].data = weights;
    allocationChart.update();
  }

  renderPortfolioChart(portfolioLabels, portfolioSeries, cashSeries);

  const openDialog = (id) => {
    const dialog = document.getElementById(id);
    if (!dialog) return;
    dialog.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  };

  const closeDialog = (dialog) => {
    dialog.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  };

  document.querySelectorAll('[data-dialog-open]').forEach(trigger => {
    trigger.addEventListener('click', () => openDialog(trigger.getAttribute('data-dialog-open')));
  });

  document.querySelectorAll('[data-dialog-close]').forEach(closeBtn => {
    closeBtn.addEventListener('click', () => closeDialog(closeBtn.closest('.dialog')));
  });

  document.querySelectorAll('.dialog').forEach(dialog => {
    dialog.addEventListener('click', (evt) => {
      if (evt.target === dialog.querySelector('.dialog-backdrop')) {
        closeDialog(dialog);
      }
    });
  });

  const buildSymbols = () => Array.from(new Set([...holdingsSymbols, ...watchlistSymbols]));
  let symbols = buildSymbols();

  const updatePanels = (quotes) => {
    document.querySelectorAll('#watchlistStrip [data-symbol]').forEach(panel => {
      const symbol = panel.getAttribute('data-symbol');
      const quote = quotes[symbol];
      if (!quote) return;
      const currency = quote.currency || chartCurrency;
      const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency });
      const lastEl = panel.querySelector('[data-role="last"]');
      const changeEl = panel.querySelector('[data-role="change"]');
      if (lastEl && quote.last !== null) {
        lastEl.textContent = formatter.format(quote.last);
      }
      if (changeEl && quote.prev_close !== null && quote.last !== null) {
        const diff = quote.last - quote.prev_close;
        const pct = quote.prev_close ? (diff / quote.prev_close) * 100 : 0;
        changeEl.textContent = `${diff >= 0 ? '+' : ''}${diff.toFixed(2)} (${pct.toFixed(2)}%)`;
        changeEl.classList.toggle('text-emerald-500', diff >= 0);
        changeEl.classList.toggle('text-rose-500', diff < 0);
      }
    });

    const weightRows = [];
    document.querySelectorAll('#holdingsTableBody tr[data-symbol]').forEach(row => {
      const symbol = row.getAttribute('data-symbol');
      const quote = quotes[symbol];
      const currency = row.getAttribute('data-currency') || (quote && quote.currency) || chartCurrency;
      const qty = parseNumber(row.getAttribute('data-qty'));
      const avg = parseNumber(row.getAttribute('data-avg'));
      let fxRate = parseNumber(row.getAttribute('data-fx'));
      const costBase = parseNumber(row.getAttribute('data-cost-base'));
      const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency });

      if (quote && quote.last !== null) {
        row.setAttribute('data-last', quote.last);
      }
      if (quote && quote.prev_close !== null) {
        row.setAttribute('data-prev', quote.prev_close);
      }

      const lastPrice = parseNumber(row.getAttribute('data-last'));
      const prevClose = parseNumber(row.getAttribute('data-prev'));

      if (!Number.isFinite(fxRate) || fxRate <= 0) {
        const storedMarketCcy = parseNumber(row.getAttribute('data-market-ccy'));
        const storedMarketBase = parseNumber(row.getAttribute('data-market-base'));
        fxRate = storedMarketCcy !== 0 ? storedMarketBase / storedMarketCcy : 1;
      }
      if (!Number.isFinite(fxRate) || fxRate <= 0) {
        fxRate = 1;
      }
      if (currency === baseCurrencyCode) {
        fxRate = 1;
      }

      const lastCell = row.querySelector('[data-role="holding-last"]');
      if (lastCell && Number.isFinite(lastPrice)) {
        lastCell.textContent = formatter.format(lastPrice);
      }

      let marketCcyValue = parseNumber(row.getAttribute('data-market-ccy'));
      if (quote && quote.last !== null) {
        marketCcyValue = qty * quote.last;
      } else if (Number.isFinite(lastPrice)) {
        marketCcyValue = qty * lastPrice;
      }

      const marketCcy = row.querySelector('[data-role="holding-market-ccy"]');
      if (marketCcy && Number.isFinite(marketCcyValue)) {
        marketCcy.textContent = formatter.format(marketCcyValue);
      }

      const marketBaseValue = Number.isFinite(marketCcyValue) ? marketCcyValue * fxRate : parseNumber(row.getAttribute('data-market-base'));
      const marketBase = row.querySelector('[data-role="holding-market-base"]');
      if (marketBase && Number.isFinite(marketBaseValue)) {
        marketBase.textContent = baseFormatter.format(marketBaseValue);
      }
      row.setAttribute('data-market-ccy', marketCcyValue);
      row.setAttribute('data-market-base', marketBaseValue);
      row.setAttribute('data-fx', fxRate);

      const diffCcy = Number.isFinite(lastPrice) ? (lastPrice - avg) * qty : parseNumber(row.getAttribute('data-unrealized-ccy'));
      const diffBase = Number.isFinite(diffCcy) ? diffCcy * fxRate : parseNumber(row.getAttribute('data-unrealized-base'));
      const unrealizedCcy = row.querySelector('[data-role="holding-unrealized-ccy"]');
      if (unrealizedCcy && Number.isFinite(diffCcy)) {
        unrealizedCcy.textContent = formatter.format(diffCcy);
      }
      const unrealizedBase = row.querySelector('[data-role="holding-unrealized-base"]');
      if (unrealizedBase && Number.isFinite(diffBase)) {
        unrealizedBase.textContent = baseFormatter.format(diffBase);
      }
      const unrealizedCell = row.querySelector('[data-role="holding-unrealized-cell"]');
      applyGainClass(unrealizedCell, diffBase);
      const pctEl = row.querySelector('[data-role="holding-unrealized-pct"]');
      if (pctEl) {
        const pctValue = costBase > 0 && Number.isFinite(diffBase) ? (diffBase / costBase) * 100 : 0;
        pctEl.textContent = `${pctValue.toFixed(2)}%`;
        applyGainClass(pctEl, diffBase);
      }
      row.setAttribute('data-unrealized-ccy', diffCcy);
      row.setAttribute('data-unrealized-base', diffBase);

      let dayDiffCcy = parseNumber(row.getAttribute('data-day-ccy'));
      if (quote && quote.last !== null && quote.prev_close !== null) {
        dayDiffCcy = (quote.last - quote.prev_close) * qty;
      } else if (Number.isFinite(lastPrice) && Number.isFinite(prevClose)) {
        dayDiffCcy = (lastPrice - prevClose) * qty;
      }
      const dayBaseValue = Number.isFinite(dayDiffCcy) ? dayDiffCcy * fxRate : parseNumber(row.getAttribute('data-day-base'));
      const dayCcy = row.querySelector('[data-role="holding-day-ccy"]');
      if (dayCcy && Number.isFinite(dayDiffCcy)) {
        dayCcy.textContent = formatter.format(dayDiffCcy);
      }
      const dayBase = row.querySelector('[data-role="holding-day-base"]');
      if (dayBase && Number.isFinite(dayBaseValue)) {
        dayBase.textContent = baseFormatter.format(dayBaseValue);
      }
      const dayCell = row.querySelector('[data-role="holding-day-cell"]');
      applyGainClass(dayCell, dayBaseValue);
      row.setAttribute('data-day-ccy', dayDiffCcy);
      row.setAttribute('data-day-base', dayBaseValue);

      weightRows.push({ row, marketBase: Number.isFinite(marketBaseValue) ? marketBaseValue : 0 });
    });

    const totalMarketBase = weightRows.reduce((sum, item) => sum + (Number.isFinite(item.marketBase) ? item.marketBase : 0), 0);
    weightRows.forEach(({ row, marketBase }) => {
      const weightEl = row.querySelector('[data-role="holding-weight"]');
      const pct = totalMarketBase > 0 ? (marketBase / totalMarketBase) * 100 : 0;
      if (weightEl) {
        weightEl.textContent = pct.toFixed(2);
      }
      const riskEl = row.querySelector('[data-role="holding-risk"]');
      if (riskEl) {
        if (pct > 15) {
          riskEl.textContent = 'High concentration';
          riskEl.classList.remove('hidden');
        } else {
          riskEl.textContent = '';
          riskEl.classList.add('hidden');
        }
      }
    });
  };

  let pollInFlight = false;
  let stalePollTimer = null;

  async function pollQuotes(){
    if (!symbols.length || pollInFlight) return;
    pollInFlight = true;
    try {
      const resp = await fetch(`/api/stocks/live?symbols=${symbols.join(',')}`, { headers: { 'Accept': 'application/json' } });
      if (!resp.ok) return;
      const data = await resp.json();
      if (data && data.quotes) {
        const lookup = {};
        data.quotes.forEach(q => { lookup[q.symbol] = q; });
        updatePanels(lookup);
        const hasStale = data.quotes.some(q => q.stale);
        if (hasStale) {
          if (stalePollTimer) clearTimeout(stalePollTimer);
          stalePollTimer = setTimeout(() => {
            stalePollTimer = null;
            pollQuotes();
          }, Math.min(refreshSeconds, 3000));
        } else if (stalePollTimer) {
          clearTimeout(stalePollTimer);
          stalePollTimer = null;
        }
      }
    } catch (err) {
      console.warn('Live quotes polling failed', err);
    } finally {
      pollInFlight = false;
    }
  }

  pollQuotes();
  let pollTimer = setInterval(pollQuotes, refreshSeconds);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(pollTimer);
      if (stalePollTimer) {
        clearTimeout(stalePollTimer);
        stalePollTimer = null;
      }
    } else {
      pollQuotes();
      pollTimer = setInterval(pollQuotes, refreshSeconds);
    }
  });

  const refreshForm = document.querySelector('[data-role="refresh-form"]');
  if (refreshForm) {
    refreshForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitBtn = refreshForm.querySelector('[data-role="refresh-submit"]');
      const statusEl = refreshForm.querySelector('[data-role="refresh-status"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Refreshing…';
      }
      if (statusEl) {
        statusEl.textContent = 'Refreshing…';
      }
      try {
        const resp = await fetch(refreshForm.action, {
          method: 'POST',
          body: new FormData(refreshForm),
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const contentType = resp.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
          throw new Error('Unexpected response from server.');
        }
        const payload = await resp.json();
        if (!resp.ok || !payload.success) {
          throw new Error(payload && payload.message ? payload.message : 'Unable to refresh quotes');
        }
        if (payload.hero) {
          const hero = document.getElementById('overviewHeroContent');
          if (hero) hero.innerHTML = payload.hero;
        }
        if (payload.cards) {
          const cards = document.getElementById('overviewCards');
          if (cards) cards.innerHTML = payload.cards;
        }
        if (payload.holdings) {
          const tbody = document.getElementById('holdingsTableBody');
          if (tbody) tbody.innerHTML = payload.holdings;
        }
        if (payload.portfolioChart) {
          portfolioLabels = payload.portfolioChart.labels || [];
          portfolioSeries = payload.portfolioChart.series || [];
          cashSeries = payload.portfolioChart.cashSeries || [];
          renderPortfolioChart(portfolioLabels, portfolioSeries, cashSeries);
        } else {
          bindContributionsToggle();
        }
        if (payload.allocations && allocationChart) {
          updateAllocation(payload.allocations.labels || [], payload.allocations.weights || []);
        }
        if (payload.symbols) {
          holdingsSymbols = payload.symbols.holdings || holdingsSymbols;
          watchlistSymbols = payload.symbols.watchlist || watchlistSymbols;
          symbols = buildSymbols();
        }
        if (payload.baseCurrency) {
          chartCurrency = payload.baseCurrency;
          updateBaseFormatter(payload.baseCurrency);
        }
        if (statusEl) {
          statusEl.textContent = payload.message || 'Quotes refreshed.';
        }
        pollQuotes();
      } catch (error) {
        console.error('Quote refresh failed', error);
        if (statusEl) {
          statusEl.textContent = error.message || 'Unable to refresh quotes';
        }
      } finally {
        const submitBtnFinal = refreshForm.querySelector('[data-role="refresh-submit"]');
        if (submitBtnFinal) {
          submitBtnFinal.disabled = false;
          submitBtnFinal.textContent = 'Refresh quotes';
        }
        setTimeout(() => {
          const status = refreshForm.querySelector('[data-role="refresh-status"]');
          if (status) status.textContent = '';
        }, 4000);
      }
    });
  }

  const csvForm = document.getElementById('stocksCsvForm');
  if (csvForm) {
    const uploadBox = csvForm.querySelector('[data-role="csv-progress"]');
    const uploadBar = csvForm.querySelector('[data-role="csv-progress-bar"]');
    const uploadValue = csvForm.querySelector('[data-role="csv-progress-value"]');
    const uploadText = csvForm.querySelector('[data-role="csv-progress-text"]');
    const serverBox = csvForm.querySelector('[data-role="csv-server-progress"]');
    const serverBar = csvForm.querySelector('[data-role="csv-server-bar"]');
    const serverValue = csvForm.querySelector('[data-role="csv-server-value"]');
    const serverText = csvForm.querySelector('[data-role="csv-server-text"]');

    let serverTimer = null;
    let serverStarted = false;
    let serverGauge = 0;

    const resetServerProgress = () => {
      if (serverTimer) {
        clearInterval(serverTimer);
        serverTimer = null;
      }
      serverStarted = false;
      serverGauge = 0;
      if (serverBox) serverBox.classList.add('hidden');
      if (serverBar) {
        serverBar.style.width = '0%';
        serverBar.classList.remove('bg-rose-500');
        serverBar.classList.add('bg-emerald-500');
      }
      if (serverValue) serverValue.textContent = '0%';
      if (serverText) serverText.textContent = 'Reconciling portfolio…';
    };

    const beginServerProgress = (message) => {
      if (serverTimer) clearInterval(serverTimer);
      serverStarted = true;
      serverGauge = 10;
      if (serverBox) serverBox.classList.remove('hidden');
      if (serverBar) {
        serverBar.style.width = serverGauge + '%';
        serverBar.classList.remove('bg-rose-500');
        serverBar.classList.add('bg-emerald-500');
      }
      if (serverValue) serverValue.textContent = serverGauge + '%';
      if (serverText && message) serverText.textContent = message;
      serverTimer = setInterval(() => {
        if (serverGauge < 90) {
          serverGauge += 3;
          if (serverBar) serverBar.style.width = serverGauge + '%';
          if (serverValue) serverValue.textContent = serverGauge + '%';
        }
      }, 500);
    };

    csvForm.addEventListener('submit', async (evt) => {
      evt.preventDefault();
      const formData = new FormData(csvForm);
      if (uploadBox) uploadBox.classList.remove('hidden');
      if (uploadBar) uploadBar.style.width = '10%';
      if (uploadValue) uploadValue.textContent = '10%';
      if (uploadText) uploadText.textContent = 'Uploading…';

      try {
        beginServerProgress('Reconciling portfolio…');
        const resp = await fetch(csvForm.action, {
          method: 'POST',
          body: formData,
          headers: { 'Accept': 'application/json' }
        });
        const payload = await resp.json();
        if (uploadBar) uploadBar.style.width = '100%';
        if (uploadValue) uploadValue.textContent = '100%';
        if (serverTimer) {
          clearInterval(serverTimer);
          serverTimer = null;
        }
        if (serverBar) serverBar.style.width = '100%';
        if (serverValue) serverValue.textContent = '100%';
        if (serverText) serverText.textContent = 'Portfolio updated';
        if (!resp.ok) {
          if (serverBar) {
            serverBar.classList.remove('bg-emerald-500');
            serverBar.classList.add('bg-rose-500');
          }
          alert(payload && payload.message ? payload.message : 'Import failed');
        } else {
          alert(payload && payload.message ? payload.message : 'Import completed');
          window.location.reload();
        }
      } catch (error) {
        console.error('Import failed', error);
        if (serverTimer) {
          clearInterval(serverTimer);
          serverTimer = null;
        }
        if (serverBar) {
          serverBar.style.width = '100%';
          serverBar.classList.remove('bg-emerald-500');
          serverBar.classList.add('bg-rose-500');
        }
        if (serverText) serverText.textContent = 'Upload failed';
        alert('Import failed. Please try again.');
      } finally {
        setTimeout(() => {
          if (uploadBox) uploadBox.classList.add('hidden');
          resetServerProgress();
        }, 1500);
      }
    });
  }
})();
</script>
