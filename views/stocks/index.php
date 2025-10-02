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

$marketByCurrency = $totals['total_market_value_by_currency'] ?? [];
$unrealizedByCurrency = $totals['unrealized_by_currency'] ?? [];
$realizedByCurrency = $totals['realized_by_currency'] ?? [];
$cashByCurrency = $totals['cash_by_currency'] ?? [];
$preferredCurrency = array_key_exists('USD', $marketByCurrency) ? 'USD' : (array_key_first($marketByCurrency) ?: $baseCurrency);
$preferredMarket = $marketByCurrency[$preferredCurrency] ?? null;
$preferredUnrealized = $unrealizedByCurrency[$preferredCurrency] ?? null;
$preferredRealized = $realizedByCurrency[$preferredCurrency] ?? null;
$preferredCash = $cashByCurrency[$preferredCurrency] ?? null;

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
      <form method="post" action="/stocks/refresh" class="inline-flex">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/stocks', ENT_QUOTES) ?>" />
        <button class="btn btn-ghost" type="submit">Refresh quotes</button>
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
    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start">
      <div class="flex-1 space-y-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-white/60">Total value</p>
          <div class="text-4xl font-semibold"><?= moneyfmt($totals['total_market_value'], $baseCurrency) ?></div>
          <?php if ($preferredMarket !== null): ?>
            <p class="text-sm text-white/70">≈ <?= moneyfmt($preferredMarket, $preferredCurrency) ?></p>
          <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-4 text-sm">
          <div>
            <span class="text-white/60">Change (<?= htmlspecialchars(strtoupper($chartMeta['range'])) ?>)</span>
            <div class="text-lg font-medium <?= $chartMeta['change'] >= 0 ? 'text-emerald-300' : 'text-rose-300' ?>">
              <?= ($chartMeta['change'] >= 0 ? '+' : '') . moneyfmt($chartMeta['change'], $baseCurrency) ?>
              <span class="text-sm text-white/70">(<?= number_format($chartMeta['change_pct'], 2) ?>%)</span>
            </div>
          </div>
          <div class="hidden sm:block h-10 w-px bg-white/10"></div>
          <div>
            <span class="text-white/60">Unrealized P/L</span>
            <div class="text-lg font-medium <?= $totals['unrealized_pl'] >= 0 ? 'text-emerald-300' : 'text-rose-300' ?>">
              <?= moneyfmt($totals['unrealized_pl'], $baseCurrency) ?>
              <?php if ($preferredUnrealized !== null): ?>
                <span class="text-sm text-white/70">(<?= moneyfmt($preferredUnrealized, $preferredCurrency) ?>)</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if (!empty($marketByCurrency)): ?>
          <div class="flex flex-wrap gap-3 text-xs text-white/70">
            <?php foreach ($marketByCurrency as $currency => $amount): ?>
              <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-1 font-medium">
                <?= htmlspecialchars($currency) ?> · <?= moneyfmt($amount, $currency) ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="flex w-full flex-col gap-4 lg:w-1/2">
        <div class="h-56">
          <canvas id="portfolioValueChart" height="224"></canvas>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <label class="inline-flex items-center gap-2 text-xs text-white/70">
            <input type="checkbox" class="h-4 w-4 rounded border-white/40 bg-transparent text-emerald-300 focus:ring-emerald-200" data-role="chart-contributions" />
            Show cash contributions
          </label>
          <form method="get" action="/stocks" class="flex flex-wrap gap-1" id="portfolioRangeForm">
            <input type="hidden" name="q" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" />
            <input type="hidden" name="sector" value="<?= htmlspecialchars($filters['sector'] ?? '') ?>" />
            <input type="hidden" name="currency" value="<?= htmlspecialchars($filters['currency'] ?? '') ?>" />
            <input type="hidden" name="period" value="<?= htmlspecialchars($filters['realized_period'] ?? 'YTD') ?>" />
            <?php if (!empty($filters['watchlist_only'])): ?>
              <input type="hidden" name="watchlist" value="1" />
            <?php endif; ?>
            <?php foreach ($rangeOptions as $option): ?>
              <?php $isActive = strtoupper($chartRange) === $option; ?>
              <button
                type="submit"
                name="chartRange"
                value="<?= htmlspecialchars($option) ?>"
                class="px-3 py-1 text-xs font-medium rounded-full <?= $isActive ? 'bg-white text-slate-900 shadow-sm' : 'bg-white/10 text-white/80 hover:bg-white/20' ?>"
                aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
              ><?= htmlspecialchars($option) ?></button>
            <?php endforeach; ?>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
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
  const allocationData = <?= json_encode($allocations['by_ticker']) ?>;
  const portfolioSeries = <?= json_encode($portfolioChart['series']) ?>;
  const portfolioLabels = <?= json_encode($portfolioChart['labels']) ?>;
  const holdings = <?= json_encode(array_map(static fn($h) => $h['symbol'], $holdings)) ?>;
  const watchlist = <?= json_encode(array_map(static fn($w) => $w['symbol'], $watchlist)) ?>;
  const refreshSeconds = <?= (int)$refreshSeconds ?> * 1000;
  const chartCurrency = <?= json_encode($baseCurrency) ?>;
  const cashSeries = <?= json_encode(array_fill(0, count($portfolioChart['series']), (float)$totals['cash_balance'])) ?>;

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

  const valueCanvas = document.getElementById('portfolioValueChart');
  let contributionVisible = false;
  if (valueCanvas && portfolioSeries.length) {
    const ctx = valueCanvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, valueCanvas.height);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.45)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
    const cashGradient = ctx.createLinearGradient(0, 0, 0, valueCanvas.height);
    cashGradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
    cashGradient.addColorStop(1, 'rgba(59, 130, 246, 0.05)');

    const formatCurrency = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: chartCurrency }).format(value);

    const chart = new Chart(valueCanvas, {
      type: 'line',
      data: {
        labels: portfolioLabels,
        datasets: [{
          label: 'Portfolio',
          data: portfolioSeries,
          borderColor: '#34d399',
          backgroundColor: gradient,
          tension: 0.35,
          fill: true,
          pointRadius: 0,
          spanGaps: true
        }, {
          label: 'Cash',
          data: cashSeries,
          borderColor: '#3b82f6',
          backgroundColor: cashGradient,
          tension: 0.3,
          fill: true,
          pointRadius: 0,
          spanGaps: true,
          hidden: true
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

    const contributionsToggle = document.querySelector('[data-role="chart-contributions"]');
    if (contributionsToggle) {
      contributionsToggle.addEventListener('change', () => {
        contributionVisible = !contributionVisible;
        chart.data.datasets[1].hidden = !contributionVisible;
        chart.update();
      });
    }
  }

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
