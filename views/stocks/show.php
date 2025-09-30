<?php
/** @var array<string, mixed> $stock */
/** @var ?MyMoneyMap\Stocks\DTO\Holding $holding */
/** @var ?MyMoneyMap\Stocks\DTO\LiveQuote $quote */
/** @var array{labels: list<string>, values: list<float>} $priceSeries */
/** @var array{labels: list<string>, values: list<float>} $positionSeries */
/** @var float $realizedYtd */
/** @var list<MyMoneyMap\Stocks\DTO\Insight> $insights */
/** @var array<string, mixed> $settings */
/** @var bool $isWatched */
$base = fx_user_main($pdo, uid());
?>

<section class="space-y-8">
  <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-3xl font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($stock['symbol']) ?></h1>
        <?php if (!empty($stock['exchange'])): ?>
          <span class="rounded-full border border-slate-200 px-2 py-1 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-300"><?= htmlspecialchars($stock['exchange']) ?></span>
        <?php endif; ?>
      </div>
      <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($stock['name'] ?? '') ?></p>
      <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-400 dark:text-slate-500">
        <?php if (!empty($stock['sector'])): ?><span><?= htmlspecialchars($stock['sector']) ?></span><?php endif; ?>
        <?php if (!empty($stock['industry'])): ?><span><?= htmlspecialchars($stock['industry']) ?></span><?php endif; ?>
        <?php if (!empty($stock['currency'])): ?><span>Currency: <?= htmlspecialchars($stock['currency']) ?></span><?php endif; ?>
      </div>
    </div>
    <form method="post" action="/stocks/watch" class="flex items-center gap-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="stock_id" value="<?= (int) $stock['id'] ?>">
      <button class="btn <?= $isWatched ? 'btn-secondary' : 'btn-primary' ?>" type="submit">
        <i data-lucide="star" class="mr-1 h-4 w-4"></i>
        <?= $isWatched ? 'Unwatch' : 'Add to watchlist' ?>
      </button>
      <button data-open-trade class="btn btn-primary">Record trade</button>
    </form>
  </header>

  <section class="grid gap-6 lg:grid-cols-3">
    <article class="card bg-white/80 p-5 shadow-glass dark:bg-slate-900/60 lg:col-span-2">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 class="text-sm font-medium text-slate-500 dark:text-slate-300">Live price</h2>
          <p class="text-3xl font-semibold text-slate-900 dark:text-slate-100"><?= $quote ? moneyfmt($quote->last, $stock['currency']) : '—' ?></p>
          <?php if ($quote): ?>
            <p class="text-sm <?= $quote->change() >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
              <?= moneyfmt($quote->change(), $stock['currency']) ?> (<?= number_format($quote->percentChange(), 2) ?>%)
              <span class="ml-2 text-xs text-slate-400">As of <?= $quote->asOf->format('H:i') ?></span>
            </p>
          <?php else: ?>
            <p class="text-xs text-slate-400">No live quote available.</p>
          <?php endif; ?>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs text-slate-500 dark:text-slate-400">
          <div><dt class="font-medium text-slate-600 dark:text-slate-300">Day high</dt><dd><?= $quote ? moneyfmt($quote->dayHigh, $stock['currency']) : '—' ?></dd></div>
          <div><dt class="font-medium text-slate-600 dark:text-slate-300">Day low</dt><dd><?= $quote ? moneyfmt($quote->dayLow, $stock['currency']) : '—' ?></dd></div>
          <div><dt class="font-medium text-slate-600 dark:text-slate-300">Prev close</dt><dd><?= $quote ? moneyfmt($quote->previousClose, $stock['currency']) : '—' ?></dd></div>
          <div><dt class="font-medium text-slate-600 dark:text-slate-300">Volume</dt><dd><?= $quote ? number_format($quote->volume) : '—' ?></dd></div>
        </dl>
      </header>
      <div class="mt-6">
        <div class="flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
          <?php foreach (['1M','3M','6M','1Y','5Y'] as $range): ?>
            <button data-range="<?= $range ?>" class="chip <?= $range === '6M' ? 'bg-brand-500/20 text-brand-700 dark:bg-emerald-500/20 dark:text-emerald-100' : '' ?>"><?= $range ?></button>
          <?php endforeach; ?>
        </div>
        <div class="mt-4 h-72">
          <canvas id="price-chart"></canvas>
        </div>
      </div>
    </article>
    <article class="card bg-white/80 p-5 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-medium text-slate-500 dark:text-slate-300">Position</h2>
      <?php if ($holding): ?>
        <dl class="mt-3 space-y-3 text-sm text-slate-600 dark:text-slate-300">
          <div class="flex items-center justify-between"><dt>Quantity</dt><dd><?= number_format($holding->quantity, 4) ?></dd></div>
          <div class="flex items-center justify-between"><dt>Average cost</dt><dd><?= moneyfmt($holding->averageCost, $stock['currency']) ?> <span class="text-xs text-slate-400">(<?= moneyfmt($holding->averageCostBase, $base) ?>)</span></dd></div>
          <div class="flex items-center justify-between"><dt>Market value</dt><dd><?= moneyfmt($holding->marketValueBase, $base) ?></dd></div>
          <div class="flex items-center justify-between <?= $holding->unrealized >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>"><dt>Unrealized</dt><dd><?= moneyfmt($holding->unrealized, $base) ?> (<?= number_format($holding->unrealizedPercent, 2) ?>%)</dd></div>
          <div class="flex items-center justify-between"><dt>Weight</dt><dd><?= number_format($holding->weight, 2) ?>%</dd></div>
          <div class="flex items-center justify-between"><dt>Realized P/L (YTD)</dt><dd><?= moneyfmt($realizedYtd, $base) ?></dd></div>
        </dl>
      <?php else: ?>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No open position. Use the trade form to start tracking.</p>
      <?php endif; ?>
      <div class="mt-6">
        <h3 class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Position value</h3>
        <div class="mt-3 h-40"><canvas id="position-chart"></canvas></div>
      </div>
    </article>
  </section>

  <?php if ($insights): ?>
    <section class="card bg-white/80 p-4 shadow-glass dark:bg-slate-900/60">
      <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Insights</h2>
      <ul class="mt-3 grid gap-3 md:grid-cols-2">
        <?php foreach ($insights as $insight): ?>
          <li class="rounded-2xl bg-slate-50/80 p-3 text-sm shadow-sm dark:bg-slate-800/60">
            <p class="font-semibold text-slate-700 dark:text-slate-100"><?= htmlspecialchars($insight->title) ?></p>
            <p class="text-slate-500 dark:text-slate-300"><?= htmlspecialchars($insight->description) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</section>

<div id="trade-drawer" class="fixed inset-0 z-40 hidden bg-slate-900/40 backdrop-blur-sm">
  <div class="absolute right-0 top-0 h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">
    <header class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Record trade</h2>
      <button data-close-trade class="icon-action"><i data-lucide="x" class="h-5 w-5"></i></button>
    </header>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ticket for <?= htmlspecialchars($stock['symbol']) ?>.</p>

    <form class="mt-6 space-y-4" method="post" action="/stocks/buy" data-trade-form>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="symbol" value="<?= htmlspecialchars($stock['symbol']) ?>">
      <input type="hidden" name="exchange" value="<?= htmlspecialchars($stock['exchange'] ?? '') ?>">
      <input type="hidden" name="name" value="<?= htmlspecialchars($stock['name'] ?? '') ?>">
      <input type="hidden" name="currency" value="<?= htmlspecialchars($stock['currency'] ?? 'USD') ?>">
      <label class="text-xs font-medium text-slate-500">Quantity
        <input name="quantity" type="number" step="0.0001" class="input mt-1" required>
      </label>
      <label class="text-xs font-medium text-slate-500">Price
        <input name="price" type="number" step="0.0001" class="input mt-1" required>
      </label>
      <label class="text-xs font-medium text-slate-500">Fee
        <input name="fee" type="number" step="0.01" class="input mt-1" value="0">
      </label>
      <label class="text-xs font-medium text-slate-500">Executed at
        <input name="executed_at" type="datetime-local" class="input mt-1" value="<?= date('Y-m-d\TH:i') ?>">
      </label>
      <label class="text-xs font-medium text-slate-500">Note
        <textarea name="note" class="input mt-1" rows="2"></textarea>
      </label>
      <div class="flex items-center gap-2">
        <button class="btn btn-primary">Save buy</button>
        <button type="button" data-close-trade class="btn btn-secondary">Cancel</button>
      </div>
    </form>

    <hr class="my-6 border-slate-200 dark:border-slate-800">

    <form class="space-y-4" method="post" action="/stocks/sell" data-trade-form>
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="symbol" value="<?= htmlspecialchars($stock['symbol']) ?>">
      <input type="hidden" name="exchange" value="<?= htmlspecialchars($stock['exchange'] ?? '') ?>">
      <input type="hidden" name="currency" value="<?= htmlspecialchars($stock['currency'] ?? 'USD') ?>">
      <label class="text-xs font-medium text-slate-500">Quantity
        <input name="quantity" type="number" step="0.0001" class="input mt-1" required>
      </label>
      <label class="text-xs font-medium text-slate-500">Price
        <input name="price" type="number" step="0.0001" class="input mt-1" required>
      </label>
      <label class="text-xs font-medium text-slate-500">Fee
        <input name="fee" type="number" step="0.01" class="input mt-1" value="0">
      </label>
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
    const priceSeries = <?= json_encode($priceSeries, JSON_UNESCAPED_SLASHES) ?>;
    window.renderLineChart && window.renderLineChart('price-chart', priceSeries.labels, priceSeries.values);
    const positionSeries = <?= json_encode($positionSeries, JSON_UNESCAPED_SLASHES) ?>;
    window.renderLineChart && window.renderLineChart('position-chart', positionSeries.labels, positionSeries.values);

    const drawer = document.getElementById('trade-drawer');
    document.querySelectorAll('[data-open-trade]').forEach(btn => btn.addEventListener('click', () => drawer?.classList.remove('hidden')));
    document.querySelectorAll('[data-close-trade]').forEach(btn => btn.addEventListener('click', () => drawer?.classList.add('hidden')));
    document.querySelectorAll('form[data-trade-form] button.chip').forEach(btn => {
      btn.addEventListener('click', () => {
        const qty = btn.closest('form')?.querySelector('input[name="quantity"]');
        if (!qty) return;
        qty.value = (parseFloat(qty.value || '0') + parseFloat(btn.dataset.qty || '0')).toFixed(4);
      });
    });

    document.querySelectorAll('[data-range]').forEach(btn => {
      btn.addEventListener('click', () => {
        const range = btn.dataset.range;
        fetch(`/api/stocks/<?= urlencode($stock['symbol']) ?>/history?range=${range}`)
          .then(resp => resp.json())
          .then(data => {
            if (!data.candles) return;
            const labels = data.candles.map(item => item.date);
            const values = data.candles.map(item => item.close);
            window.renderLineChart && window.renderLineChart('price-chart', labels, values);
          });
      });
    });
  })();
</script>
