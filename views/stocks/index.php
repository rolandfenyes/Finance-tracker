<?php
/** @var MyMoneyMap\Stocks\DTO\PortfolioSnapshot $snapshot */
/** @var array<int, array<string, mixed>> $trades */
/** @var string $realizedRange */
/** @var string $chartRange */
/** @var array{labels: list<string>, values: list<float>} $portfolioChart */
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $settings */
$base = $snapshot->baseCurrency;
$rangeOptions = ['1W' => '1W', '1M' => '1M', '3M' => '3M', '6M' => '6M', '1Y' => '1Y', 'YTD' => 'YTD'];
$liveSymbols = [];
foreach ($snapshot->holdings as $holdingSymbol) {
    $liveSymbols[] = $holdingSymbol->symbol;
}
foreach ($snapshot->watchlist as $watch) {
    $liveSymbols[] = $watch->symbol;
}
$liveSymbols = array_values(array_unique($liveSymbols));
?>

<section class="space-y-8">
  <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900 dark:text-slate-100">Stocks &amp; ETFs</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400">Live snapshot of your equity positions converted to <?= htmlspecialchars($base) ?>.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="get" class="flex items-center gap-2 text-sm">
        <label class="font-medium text-slate-600 dark:text-slate-300" for="realized-range">Realized P/L</label>
        <select id="realized-range" name="realized" class="input w-32">
          <?php foreach ($rangeOptions as $key => $label): ?>
            <option value="<?= $key ?>" <?= $realizedRange === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary">Apply</button>
      </form>
      <button data-open-trade class="btn btn-primary">New trade</button>
    </div>
  </header>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="rounded-2xl border border-rose-200/70 bg-rose-50/80 px-4 py-3 text-sm text-rose-700 shadow-sm dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-100">
      <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-700 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100">
      <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
    </div>
  <?php endif; ?>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <article class="card bg-white/70 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Market Value</h2>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100"><?= moneyfmt($snapshot->totalMarketValue, $base) ?></p>
      <p class="text-xs text-slate-400">Converted to <?= htmlspecialchars($base) ?> using latest spot.</p>
    </article>
    <article class="card bg-white/70 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Cost</h2>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100"><?= moneyfmt($snapshot->totalCost, $base) ?></p>
      <p class="text-xs text-slate-400">Average cost per instrument currency converted on spot.</p>
    </article>
    <article class="card bg-white/70 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Unrealized P/L</h2>
      <p class="mt-2 text-2xl font-semibold <?= $snapshot->unrealized >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
        <?= moneyfmt($snapshot->unrealized, $base) ?>
        <span class="ml-2 text-sm font-medium text-slate-500 dark:text-slate-400"><?= number_format($snapshot->unrealizedPercent, 2) ?>%</span>
      </p>
      <p class="text-xs text-slate-400">Based on average cost method.</p>
    </article>
    <article class="card bg-white/70 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Realized P/L (<?= htmlspecialchars($realizedRange) ?>)</h2>
      <p class="mt-2 text-2xl font-semibold <?= $snapshot->realizedPeriod >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
        <?= moneyfmt($snapshot->realizedPeriod, $base) ?>
      </p>
      <p class="text-xs text-slate-400">FIFO cost basis for sells within period.</p>
    </article>
    <article class="card bg-white/70 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Cash Impact</h2>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100"><?= moneyfmt($snapshot->cashImpact, $base) ?></p>
      <p class="text-xs text-slate-400">Net cash flows from trades (fees included).</p>
    </article>
    <article class="card bg-white/70 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Daily P/L</h2>
      <p class="mt-2 text-2xl font-semibold <?= $snapshot->dailyPL >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
        <?= moneyfmt($snapshot->dailyPL, $base) ?>
      </p>
      <p class="text-xs text-slate-400">Change since previous close.</p>
    </article>
  </section>

  <?php if ($snapshot->insights): ?>
    <section class="grid gap-3 rounded-3xl border border-emerald-200/50 bg-emerald-50/60 p-4 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-900/20">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">Insights</h2>
      <ul class="grid gap-2 md:grid-cols-2">
        <?php foreach ($snapshot->insights as $insight): ?>
          <li class="rounded-2xl bg-white/70 p-3 text-sm shadow-sm dark:bg-emerald-950/40">
            <p class="font-medium text-slate-700 dark:text-emerald-100"><?= htmlspecialchars($insight->title) ?></p>
            <p class="text-slate-500 dark:text-emerald-200/80"><?= htmlspecialchars($insight->description) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="grid gap-6 lg:grid-cols-2">
    <div class="card relative overflow-hidden bg-white/80 p-4 shadow-glass dark:bg-slate-900/60">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Portfolio value</h3>
        <form method="get" class="flex items-center gap-2 text-xs">
          <input type="hidden" name="realized" value="<?= htmlspecialchars($realizedRange) ?>">
          <input type="hidden" name="search" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')) ?>">
          <input type="hidden" name="currency" value="<?= htmlspecialchars((string) ($filters['currency'] ?? '')) ?>">
          <input type="hidden" name="sector" value="<?= htmlspecialchars((string) ($filters['sector'] ?? '')) ?>">
          <label for="chart-range" class="font-medium text-slate-500 dark:text-slate-400">Range</label>
          <select name="chart_range" id="chart-range" class="input h-8 text-xs">
            <?php foreach (['1M','3M','6M','1Y'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $chartRange === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="mt-4 h-64">
        <canvas id="portfolio-value-chart" class="h-full w-full"></canvas>
      </div>
    </div>

    <div class="card bg-white/80 p-4 shadow-glass dark:bg-slate-900/60">
      <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Allocation</h3>
      <div class="mt-4 grid gap-6 sm:grid-cols-2">
        <div class="h-56">
          <canvas id="allocation-ticker"></canvas>
        </div>
        <div class="h-56">
          <canvas id="allocation-sector"></canvas>
        </div>
      </div>
    </div>
  </section>

  <section class="card bg-white/80 p-5 shadow-glass dark:bg-slate-900/60">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Holdings</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Filter and search across your open positions.</p>
      </div>
      <form method="get" class="flex flex-wrap gap-2 text-sm">
        <input class="input" name="search" placeholder="Symbol or name" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')) ?>">
        <input type="hidden" name="realized" value="<?= htmlspecialchars($realizedRange) ?>">
        <select class="input" name="currency">
          <option value="">Currency</option>
          <?php foreach (['USD','EUR','GBP','HUF','JPY'] as $ccy): ?>
            <option value="<?= $ccy ?>" <?= ($filters['currency'] ?? '') === $ccy ? 'selected' : '' ?>><?= $ccy ?></option>
          <?php endforeach; ?>
        </select>
        <input class="input" name="sector" placeholder="Sector" value="<?= htmlspecialchars((string) ($filters['sector'] ?? '')) ?>">
        <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
          <input type="checkbox" name="watchlist" value="1" <?= !empty($filters['watchlist']) ? 'checked' : '' ?>> Watchlist only
        </label>
        <button class="btn btn-secondary">Filter</button>
      </form>
    </header>

    <div class="mt-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <th class="py-2 pr-3">Ticker</th>
            <th class="py-2 pr-3">Qty</th>
            <th class="py-2 pr-3">Avg cost</th>
            <th class="py-2 pr-3">Last price</th>
            <th class="py-2 pr-3">Market value</th>
            <th class="py-2 pr-3">Unrealized</th>
            <th class="py-2 pr-3">Day P/L</th>
            <th class="py-2 pr-3">Weight</th>
            <th class="py-2 pr-3">Notes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
          <?php foreach ($snapshot->holdings as $holding): ?>
            <tr class="hover:bg-white/60 dark:hover:bg-slate-800/40">
              <td class="py-3 pr-3 font-semibold">
                <a class="text-brand-600 hover:underline" href="/stocks/<?= urlencode($holding->symbol) ?>"><?= htmlspecialchars($holding->symbol) ?></a>
                <div class="text-xs text-slate-400"><?= htmlspecialchars($holding->currency) ?></div>
              </td>
              <td class="py-3 pr-3 text-slate-600 dark:text-slate-300"><?= number_format($holding->quantity, 4) ?></td>
              <td class="py-3 pr-3 text-slate-600 dark:text-slate-300">
                <?= moneyfmt($holding->averageCost, $holding->currency) ?>
                <div class="text-xs text-slate-400"><?= moneyfmt($holding->averageCostBase, $base) ?></div>
              </td>
              <td class="py-3 pr-3 text-slate-600 dark:text-slate-300">
                <span data-live-last="<?= htmlspecialchars($holding->symbol) ?>" data-currency="<?= htmlspecialchars($holding->currency) ?>"><?= moneyfmt($holding->lastPrice, $holding->currency) ?></span>
                <div class="text-xs text-slate-400"><?= moneyfmt($holding->lastPriceBase, $base) ?></div>
              </td>
              <td class="py-3 pr-3 text-slate-600 dark:text-slate-300">
                <?= moneyfmt($holding->marketValue, $holding->currency) ?>
                <div class="text-xs text-slate-400"><?= moneyfmt($holding->marketValueBase, $base) ?></div>
              </td>
              <td class="py-3 pr-3 <?= $holding->unrealized >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
                <?= moneyfmt($holding->unrealized, $base) ?> (<?= number_format($holding->unrealizedPercent, 2) ?>%)
              </td>
              <td class="py-3 pr-3 <?= $holding->dayChangeBase >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
                <span data-live-change="<?= htmlspecialchars($holding->symbol) ?>" data-currency="<?= htmlspecialchars($holding->currency) ?>" data-base="<?= htmlspecialchars($base) ?>"><?= moneyfmt($holding->dayChange, $holding->currency) ?></span>
                <div class="text-xs text-slate-400"><?= moneyfmt($holding->dayChangeBase, $base) ?></div>
              </td>
              <td class="py-3 pr-3 text-slate-600 dark:text-slate-300"><?= number_format($holding->weight, 2) ?>%</td>
              <td class="py-3 pr-3 text-xs text-slate-500 dark:text-slate-400">
                <?php if ($holding->concentrationWarning): ?>
                  <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/20 dark:text-rose-200">
                    <i data-lucide="alert-triangle" class="h-3 w-3"></i> Concentrated
                  </span>
                <?php else: ?>
                  <span class="text-slate-400">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($snapshot->watchlist): ?>
    <section class="card bg-white/80 p-4 shadow-glass dark:bg-slate-900/60">
      <header class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Watchlist</h3>
      </header>
      <div class="mt-4 flex flex-wrap gap-4">
        <?php foreach ($snapshot->watchlist as $entry): ?>
          <div class="min-w-[180px] rounded-2xl border border-slate-200/60 bg-white/70 px-4 py-3 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/50">
            <div class="flex items-center justify-between">
              <a href="/stocks/<?= urlencode($entry->symbol) ?>" class="font-semibold text-slate-800 hover:text-brand-600 dark:text-slate-100"><?= htmlspecialchars($entry->symbol) ?></a>
              <form method="post" action="/stocks/watch">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="stock_id" value="<?= $entry->stockId ?>">
                <button class="icon-action" title="Toggle watch">
                  <i data-lucide="star" class="h-4 w-4 text-amber-400"></i>
                </button>
              </form>
            </div>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($entry->currency) ?></div>
            <?php if ($entry->quote): ?>
              <p class="mt-2 text-lg font-semibold text-slate-800 dark:text-slate-100"><span data-live-last="<?= htmlspecialchars($entry->symbol) ?>" data-currency="<?= htmlspecialchars($entry->currency) ?>"><?= moneyfmt($entry->quote->last, $entry->currency) ?></span></p>
              <p class="text-xs <?= $entry->quote->change() >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><span data-live-change="<?= htmlspecialchars($entry->symbol) ?>" data-currency="<?= htmlspecialchars($entry->currency) ?>" data-base="<?= htmlspecialchars($base) ?>"><?= moneyfmt($entry->quote->change(), $entry->currency) ?></span> (<?= number_format($entry->quote->percentChange(), 2) ?>%)
              </p>
            <?php else: ?>
              <p class="mt-2 text-xs text-slate-400">No live quote</p>
            <?php endif; ?>
            <a href="/stocks/<?= urlencode($entry->symbol) ?>" class="mt-2 inline-flex items-center gap-1 text-xs text-brand-600 hover:underline">Trade</a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="card bg-white/80 p-5 shadow-glass dark:bg-slate-900/60">
    <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Recent trades</h3>
    <div class="mt-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <th class="py-2 pr-3">Date</th>
            <th class="py-2 pr-3">Side</th>
            <th class="py-2 pr-3">Symbol</th>
            <th class="py-2 pr-3">Qty</th>
            <th class="py-2 pr-3">Price</th>
            <th class="py-2 pr-3">Fee</th>
            <th class="py-2 pr-3">Currency</th>
            <th class="py-2 pr-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
          <?php foreach ($trades as $trade): ?>
            <tr>
              <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($trade['executed_at']))) ?></td>
              <td class="py-2 pr-3 capitalize text-slate-600 dark:text-slate-300"><?= htmlspecialchars(strtolower($trade['side'])) ?></td>
              <td class="py-2 pr-3 font-semibold"><a class="text-brand-600 hover:underline" href="/stocks/<?= urlencode($trade['symbol']) ?>"><?= htmlspecialchars($trade['symbol']) ?></a></td>
              <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= number_format((float)$trade['qty'], 4) ?></td>
              <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($trade['price'], $trade['currency']) ?></td>
              <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($trade['fee'], $trade['currency']) ?></td>
              <td class="py-2 pr-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($trade['currency']) ?></td>
              <td class="py-2 pr-3">
                <form method="post" action="/stocks/trade/delete" onsubmit="return confirm('Delete trade?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$trade['id'] ?>">
                  <button class="icon-action icon-action--danger" title="Remove">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>

<div id="trade-drawer" class="fixed inset-0 z-40 hidden bg-slate-900/40 backdrop-blur-sm">
  <div class="absolute right-0 top-0 h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">
    <header class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Record trade</h2>
      <button data-close-trade class="icon-action"><i data-lucide="x" class="h-5 w-5"></i></button>
    </header>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Supports buy or sell tickets with fees and notes.</p>

    <form class="mt-6 space-y-4" method="post" action="/stocks/buy" data-trade-form>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="side" value="BUY">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-medium text-slate-500">Symbol
          <input name="symbol" class="input mt-1" placeholder="AAPL" required>
        </label>
        <label class="text-xs font-medium text-slate-500">Exchange
          <input name="exchange" class="input mt-1" placeholder="NASDAQ">
        </label>
      </div>
      <label class="text-xs font-medium text-slate-500">Company name
        <input name="name" class="input mt-1" placeholder="Apple Inc.">
      </label>
      <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-medium text-slate-500">Quantity
          <input name="quantity" type="number" step="0.0001" class="input mt-1" required>
        </label>
        <label class="text-xs font-medium text-slate-500">Price
          <input name="price" type="number" step="0.0001" class="input mt-1" required>
        </label>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-medium text-slate-500">Currency
          <input name="currency" class="input mt-1" value="USD">
        </label>
        <label class="text-xs font-medium text-slate-500">Fee
          <input name="fee" type="number" step="0.01" class="input mt-1" value="0">
        </label>
      </div>
      <label class="text-xs font-medium text-slate-500">Executed at
        <input name="executed_at" type="datetime-local" class="input mt-1" value="<?= date('Y-m-d\TH:i') ?>">
      </label>
      <label class="text-xs font-medium text-slate-500">Note
        <textarea name="note" class="input mt-1" rows="2"></textarea>
      </label>
      <div class="flex items-center gap-2">
        <button class="btn btn-primary">Save trade</button>
        <button type="button" data-close-trade class="btn btn-secondary">Cancel</button>
      </div>
    </form>

    <hr class="my-6 border-slate-200 dark:border-slate-800">

    <form class="space-y-4" method="post" action="/stocks/sell" data-trade-form>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="side" value="SELL">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-medium text-slate-500">Symbol
          <input name="symbol" class="input mt-1" placeholder="AAPL" required>
        </label>
        <label class="text-xs font-medium text-slate-500">Exchange
          <input name="exchange" class="input mt-1" placeholder="NASDAQ">
        </label>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-medium text-slate-500">Quantity
          <input name="quantity" type="number" step="0.0001" class="input mt-1" required>
        </label>
        <label class="text-xs font-medium text-slate-500">Price
          <input name="price" type="number" step="0.0001" class="input mt-1" required>
        </label>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-medium text-slate-500">Currency
          <input name="currency" class="input mt-1" value="USD">
        </label>
        <label class="text-xs font-medium text-slate-500">Fee
          <input name="fee" type="number" step="0.01" class="input mt-1" value="0">
        </label>
      </div>
      <label class="text-xs font-medium text-slate-500">Executed at
        <input name="executed_at" type="datetime-local" class="input mt-1" value="<?= date('Y-m-d\TH:i') ?>">
      </label>
      <label class="text-xs font-medium text-slate-500">Note
        <textarea name="note" class="input mt-1" rows="2"></textarea>
      </label>
      <div class="flex items-center gap-2">
        <button class="btn btn-danger">Record sell</button>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <button type="button" data-qty="1" class="chip">+1</button>
          <button type="button" data-qty="10" class="chip">+10</button>
          <button type="button" data-qty="100" class="chip">+100</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    const chartData = <?= json_encode($portfolioChart, JSON_UNESCAPED_SLASHES) ?>;
    window.renderLineChart && window.renderLineChart('portfolio-value-chart', chartData.labels, chartData.values);

    const allocTicker = <?= json_encode(array_map(fn($slice) => ['label' => $slice->label, 'value' => $slice->value], $snapshot->allocationsByTicker), JSON_UNESCAPED_SLASHES) ?>;
    const allocSector = <?= json_encode(array_map(fn($slice) => ['label' => $slice->label, 'value' => $slice->value], $snapshot->allocationsBySector), JSON_UNESCAPED_SLASHES) ?>;
    window.renderDoughnut && window.renderDoughnut('allocation-ticker', allocTicker.map(x => x.label), allocTicker.map(x => x.value));
    window.renderDoughnut && window.renderDoughnut('allocation-sector', allocSector.map(x => x.label), allocSector.map(x => x.value));

    const drawer = document.getElementById('trade-drawer');
    const openButtons = document.querySelectorAll('[data-open-trade]');
    const closeButtons = document.querySelectorAll('[data-close-trade]');
    openButtons.forEach(btn => btn.addEventListener('click', () => drawer?.classList.remove('hidden')));
    closeButtons.forEach(btn => btn.addEventListener('click', () => drawer?.classList.add('hidden')));

    document.querySelectorAll('form[data-trade-form] button.chip').forEach(btn => {
      btn.addEventListener('click', () => {
        const qtyInput = btn.closest('form')?.querySelector('input[name="quantity"]');
        if (!qtyInput) return;
        const base = parseFloat(qtyInput.value || '0');
        const increment = parseFloat(btn.dataset.qty || '0');
        qtyInput.value = (base + increment).toFixed(4);
      });
    });

    const liveSymbols = <?= json_encode($liveSymbols, JSON_UNESCAPED_SLASHES) ?>;
    if (liveSymbols.length) {
      const formatCurrency = (value, currency, sign = false) => {
        try {
          return new Intl.NumberFormat(undefined, { style: 'currency', currency, signDisplay: sign ? 'exceptZero' : 'auto' }).format(value);
        } catch (err) {
          return (sign && value > 0 ? '+' : '') + value.toFixed(2) + ' ' + currency;
        }
      };
      const fetchQuotes = () => {
        if (document.hidden) return;
        fetch('/api/stocks/live?symbols=' + liveSymbols.join(','))
          .then(resp => resp.json())
          .then(payload => {
            (payload.quotes || []).forEach(item => {
              const symbol = item.symbol;
              document.querySelectorAll('[data-live-last= + symbol + ]').forEach(el => {
                const currency = el.dataset.currency || 'USD';
                el.textContent = formatCurrency(item.last, currency, false);
              });
              document.querySelectorAll('[data-live-change= + symbol + ]').forEach(el => {
                const currency = el.dataset.currency || 'USD';
                el.textContent = formatCurrency(item.last - item.prev_close, currency, true);
              });
            });
          })
          .catch(() => {});
      };
      fetchQuotes();
      setInterval(fetchQuotes, 8000);
      document.addEventListener('visibilitychange', () => { if (!document.hidden) fetchQuotes(); });
    }
  })();
</script>
