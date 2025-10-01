<?php
/** @var array $stock */
/** @var array|null $position */
/** @var array|null $quote */
/** @var array $priceHistory */
/** @var array $insights */
/** @var array $positionSeries */
/** @var float $realizedYtd */
/** @var array $settings */
/** @var bool $isWatched */
/** @var string $historyRange */
/** @var string $baseCurrency */
/** @var array $userCurrencies */

$rangeOptions = ['1D', '5D', '1M', '6M', '1Y', '5Y'];

$symbol = $stock['symbol'];
$currency = $stock['currency'] ?? 'USD';
$preferredCurrency = strtoupper($currency);
$hasPreferredCurrency = false;
foreach ($userCurrencies as $c) {
  if (strtoupper($c['code']) === $preferredCurrency) {
    $hasPreferredCurrency = true;
    break;
  }
}
$selectedCurrencyCode = $preferredCurrency;
if (!$hasPreferredCurrency) {
  $selectedCurrencyCode = null;
  foreach ($userCurrencies as $c) {
    if (!empty($c['is_main'])) {
      $selectedCurrencyCode = strtoupper($c['code']);
      break;
    }
  }
  if ($selectedCurrencyCode === null && !empty($userCurrencies)) {
    $selectedCurrencyCode = strtoupper($userCurrencies[0]['code']);
  }
  if ($selectedCurrencyCode === null) {
    $selectedCurrencyCode = $preferredCurrency;
  }
}
$lastPrice = $quote['last'] ?? null;
$prevClose = $quote['prev_close'] ?? null;
$change = ($lastPrice !== null && $prevClose !== null) ? $lastPrice - $prevClose : null;
$changePct = ($change !== null && $prevClose) ? ($change / $prevClose) * 100 : null;
$suggestions = $insights['suggestions'] ?? [];
$signals = $insights['signals'] ?? [];
?>

<section class="space-y-6">
  <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
        <?= htmlspecialchars($symbol) ?>
        <?php if (!empty($stock['name'])): ?>
          <span class="text-base text-gray-500 font-normal"><?= htmlspecialchars($stock['name']) ?></span>
        <?php endif; ?>
      </h1>
      <p class="text-sm text-gray-500 flex flex-wrap gap-3">
        <?php if (!empty($stock['exchange'])): ?><span><?= htmlspecialchars($stock['exchange']) ?></span><?php endif; ?>
        <span><?= htmlspecialchars($currency) ?></span>
        <?php if (!empty($stock['sector'])): ?><span><?= htmlspecialchars($stock['sector']) ?></span><?php endif; ?>
        <?php if (!empty($stock['industry'])): ?><span><?= htmlspecialchars($stock['industry']) ?></span><?php endif; ?>
        <?php if (!empty($stock['beta'])): ?><span>β <?= number_format($stock['beta'], 2) ?></span><?php endif; ?>
      </p>
    </div>
    <form method="post" action="/stocks/<?= urlencode($symbol) ?>/watch" class="flex items-center gap-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <button class="btn <?= $isWatched ? 'btn-secondary' : 'btn-primary' ?>">
        <?= $isWatched ? 'Remove from watchlist' : 'Add to watchlist' ?>
      </button>
      <a href="/stocks" class="btn btn-ghost">Back to overview</a>
    </form>
  </header>

  <section class="grid lg:grid-cols-3 gap-6">
    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40" id="positionCard"
      data-qty="<?= $position ? htmlspecialchars($position['qty']) : 0 ?>"
      data-avg="<?= $position ? htmlspecialchars($position['avg_cost_ccy']) : 0 ?>">
      <h2 class="text-lg font-semibold mb-3">Live price</h2>
      <div class="flex items-end gap-4">
        <div>
          <div class="text-4xl font-semibold text-gray-900 dark:text-gray-100" id="quoteLast">
            <?= $lastPrice !== null ? moneyfmt($lastPrice, $currency) : '—' ?>
          </div>
          <div class="text-sm <?= ($change ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500' ?>" id="quoteChange">
            <?php if ($change !== null): ?>
              <?= ($change >= 0 ? '+' : '') . moneyfmt($change, $currency) ?> (<?= number_format($changePct, 2) ?>%)
            <?php else: ?>
              —
            <?php endif; ?>
          </div>
        </div>
        <dl class="text-xs text-gray-500 space-y-1">
          <div><dt class="uppercase tracking-wide">Prev Close</dt><dd id="quotePrev"><?= $prevClose !== null ? moneyfmt($prevClose, $currency) : '—' ?></dd></div>
          <div><dt class="uppercase tracking-wide">Day High</dt><dd id="quoteHigh"><?= $quote['day_high'] ?? null ? moneyfmt($quote['day_high'], $currency) : '—' ?></dd></div>
          <div><dt class="uppercase tracking-wide">Day Low</dt><dd id="quoteLow"><?= $quote['day_low'] ?? null ? moneyfmt($quote['day_low'], $currency) : '—' ?></dd></div>
          <div><dt class="uppercase tracking-wide">Volume</dt><dd id="quoteVol"><?= $quote['volume'] ?? null ? number_format($quote['volume']) : '—' ?></dd></div>
        </dl>
      </div>
      <p class="text-xs text-gray-400 mt-3">Refreshes every <?= (int)$settings['refresh_seconds'] ?: 10 ?>s</p>
    </article>

    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-lg font-semibold mb-3">Position</h2>
      <?php if ($position): ?>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div><dt class="text-gray-500">Quantity</dt><dd class="text-gray-900 dark:text-gray-100"><?= number_format($position['qty'], 4) ?></dd></div>
          <div><dt class="text-gray-500">Average cost</dt><dd><?= moneyfmt($position['avg_cost_ccy'], $currency) ?></dd></div>
          <div><dt class="text-gray-500">Market value</dt><dd id="positionValue" data-currency="<?= htmlspecialchars($currency) ?>"><?= moneyfmt($position['qty'] * ($lastPrice ?? $position['avg_cost_ccy']), $currency) ?></dd></div>
          <div><dt class="text-gray-500">Unrealized P/L</dt><dd id="positionUnrealized" data-currency="<?= htmlspecialchars($currency) ?>"><?= moneyfmt(($lastPrice !== null ? ($lastPrice - $position['avg_cost_ccy']) : 0) * $position['qty'], $currency) ?></dd></div>
          <div><dt class="text-gray-500">Realized P/L (YTD)</dt><dd><?= moneyfmt($realizedYtd, $baseCurrency) ?></dd></div>
          <div><dt class="text-gray-500">Cost basis method</dt><dd><?= htmlspecialchars($settings['unrealized_method'] ?? 'Average') ?></dd></div>
        </dl>
      <?php else: ?>
        <p class="text-sm text-gray-500">No open position. Use the trade form to start tracking shares.</p>
      <?php endif; ?>
    </article>

    <article class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
      <h2 class="text-lg font-semibold mb-3">Insights</h2>
      <?php if (!empty($suggestions)): ?>
        <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
          <?php foreach ($suggestions as $suggestion): ?>
            <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-emerald-400"></span><span><?= htmlspecialchars($suggestion) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-sm text-gray-500">No actionable suggestions. Position looks balanced.</p>
      <?php endif; ?>
      <?php if (!empty($signals)): ?>
        <hr class="my-4 border-gray-200/70 dark:border-gray-800">
        <ul class="text-xs text-gray-500 space-y-1">
          <?php foreach ($signals as $signal): ?>
            <li><strong><?= htmlspecialchars($signal['label']) ?>:</strong> <?= htmlspecialchars($signal['value']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </article>
  </section>

  <section class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <h2 class="text-lg font-semibold">Price history</h2>
      <form id="rangeForm" method="get" class="flex items-center gap-1" aria-label="Select price history range">
        <input type="hidden" name="range" value="<?= htmlspecialchars($historyRange) ?>" />
        <div role="radiogroup" class="flex flex-wrap gap-1">
          <?php foreach ($rangeOptions as $option): ?>
            <?php $isActive = strtoupper($historyRange) === $option; ?>
            <button
              type="submit"
              name="range"
              value="<?= htmlspecialchars($option) ?>"
              data-range-btn="<?= htmlspecialchars($option) ?>"
              class="btn <?= $isActive ? 'btn-primary' : 'btn-ghost' ?> px-3 py-1 text-sm"
              aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
            ><?= htmlspecialchars($option) ?></button>
          <?php endforeach; ?>
        </div>
      </form>
    </div>
    <canvas id="priceHistoryChart" height="240"></canvas>
  </section>

  <section class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
    <h2 class="text-lg font-semibold mb-4">Position value</h2>
    <canvas id="positionValueChart" height="220"></canvas>
  </section>

  <section class="card p-5 shadow-md bg-white/80 dark:bg-gray-900/40">
    <h2 class="text-lg font-semibold mb-3">Quick trade</h2>
    <form method="post" action="/stocks/trade" class="grid sm:grid-cols-6 gap-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="symbol" value="<?= htmlspecialchars($symbol) ?>" />
      <select name="side" class="input">
        <option value="BUY">Buy</option>
        <option value="SELL">Sell</option>
      </select>
      <input name="quantity" type="number" step="0.0001" placeholder="Qty" class="input" required />
      <input name="price" type="number" step="0.0001" value="<?= $lastPrice !== null ? htmlspecialchars($lastPrice) : '' ?>" placeholder="Price" class="input" required />
      <select name="currency" class="input">
        <?php if (!empty($userCurrencies)): ?>
          <?php foreach ($userCurrencies as $c): ?>
            <?php $code = strtoupper($c['code']); ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $selectedCurrencyCode ? 'selected' : '' ?>>
              <?= htmlspecialchars($code) ?>
            </option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="<?= htmlspecialchars($selectedCurrencyCode) ?>" selected><?= htmlspecialchars($selectedCurrencyCode) ?></option>
        <?php endif; ?>
      </select>
      <input name="fee" type="number" step="0.01" placeholder="Fee" class="input" />
      <input name="trade_date" type="date" value="<?= date('Y-m-d') ?>" class="input" />
      <input name="trade_time" type="time" value="<?= date('H:i') ?>" class="input" />
      <input name="note" placeholder="Note" class="input sm:col-span-2" />
      <button class="btn btn-primary sm:col-span-2">Submit trade</button>
    </form>
  </section>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb" crossorigin="anonymous"></script>
<script>
(function(){
  const initialPriceHistory = <?= json_encode($priceHistory) ?>;
  const initialPositionSeries = <?= json_encode($positionSeries) ?>;
  const symbol = <?= json_encode($symbol) ?>;
  const refreshMs = (<?= (int)($settings['refresh_seconds'] ?? 10) ?>) * 1000;
  const currency = <?= json_encode($currency) ?>;
  const initialRange = <?= json_encode(strtoupper($historyRange)) ?>;

  const rangeForm = document.getElementById('rangeForm');
  const rangeButtons = rangeForm ? Array.from(rangeForm.querySelectorAll('[data-range-btn]')) : [];
  const rangeHidden = rangeForm ? rangeForm.querySelector('input[name="range"]') : null;

  const priceCtx = document.getElementById('priceHistoryChart');
  let priceChart = null;
  function ensurePriceChart(data) {
    if (!priceCtx) {
      return;
    }
    const labels = (data || []).map(item => item.date);
    const closes = (data || []).map(item => item.close);
    if (!priceChart) {
      priceChart = new Chart(priceCtx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data: closes,
            borderColor: '#60a5fa',
            tension: 0.3,
            fill: true,
            pointRadius: 0,
            backgroundColor: 'rgba(96, 165, 250, 0.15)'
          }]
        },
        options: {
          plugins: { legend: { display: false } },
          scales: { x: { display: false } },
        }
      });
    } else {
      priceChart.data.labels = labels;
      priceChart.data.datasets[0].data = closes;
      priceChart.update();
    }
  }

  const positionCtx = document.getElementById('positionValueChart');
  let positionChart = null;
  function ensurePositionChart(series) {
    if (!positionCtx) {
      return;
    }
    const labels = (series && Array.isArray(series.labels)) ? series.labels : [];
    const values = (series && Array.isArray(series.series)) ? series.series : [];
    if (!positionChart) {
      positionChart = new Chart(positionCtx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data: values,
            borderColor: '#34d399',
            backgroundColor: 'rgba(52, 211, 153, 0.15)',
            fill: true,
            tension: 0.3,
            spanGaps: true,
            pointRadius: 0,
          }]
        },
        options: { plugins: { legend: { display: false } }, scales: { x: { display: false } } }
      });
    } else {
      positionChart.data.labels = labels;
      positionChart.data.datasets[0].data = values;
      positionChart.update();
    }
  }

  function setActiveRange(range) {
    const upper = (range || '').toUpperCase();
    rangeButtons.forEach(btn => {
      const active = btn.dataset.rangeBtn === upper;
      btn.classList.toggle('btn-primary', active);
      btn.classList.toggle('btn-ghost', !active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (rangeHidden) {
      rangeHidden.value = upper;
    }
  }

  ensurePriceChart(initialPriceHistory);
  ensurePositionChart(initialPositionSeries);
  setActiveRange(initialRange);

  async function loadRange(range) {
    if (!rangeForm) {
      return;
    }
    rangeForm.classList.add('opacity-60', 'pointer-events-none');
    try {
      const resp = await fetch(`/api/stocks/${encodeURIComponent(symbol)}/history?range=${encodeURIComponent(range)}`);
      if (!resp.ok) {
        throw new Error(`History request failed (${resp.status})`);
      }
      const payload = await resp.json();
      if (payload && payload.price) {
        ensurePriceChart(payload.price);
      }
      if (payload && payload.position) {
        ensurePositionChart(payload.position);
      }
    } finally {
      rangeForm.classList.remove('opacity-60', 'pointer-events-none');
    }
  }

  if (rangeForm) {
    rangeForm.addEventListener('submit', function(evt) {
      if (rangeForm.dataset.forceSubmit === '1') {
        return;
      }
      const submitter = evt.submitter;
      if (submitter && submitter.dataset.rangeBtn) {
        evt.preventDefault();
        const selected = submitter.dataset.rangeBtn;
        setActiveRange(selected);
        loadRange(selected).catch(() => {
          rangeForm.dataset.forceSubmit = '1';
          rangeForm.submit();
        });
      }
    });
  }

  async function refreshQuote(){
    try {
      const resp = await fetch(`/api/stocks/live?symbols=${symbol}`);
      if (!resp.ok) return;
      const data = await resp.json();
      if (!data || !data.quotes || !data.quotes.length) return;
      const quote = data.quotes[0];
      const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: quote.currency || currency });
      if (quote.last !== null) {
        document.getElementById('quoteLast').textContent = formatter.format(quote.last);
        const card = document.getElementById('positionCard');
        if (card) {
          const qty = parseFloat(card.dataset.qty || '0');
          const avg = parseFloat(card.dataset.avg || '0');
          const valueEl = document.getElementById('positionValue');
          const unrealizedEl = document.getElementById('positionUnrealized');
          if (valueEl) {
            valueEl.textContent = formatter.format(qty * quote.last);
          }
          if (unrealizedEl) {
            const diff = (quote.last - avg) * qty;
            unrealizedEl.textContent = formatter.format(diff);
            unrealizedEl.classList.toggle('text-emerald-500', diff >= 0);
            unrealizedEl.classList.toggle('text-rose-500', diff < 0);
          }
        }
      }
      if (quote.prev_close !== null && quote.last !== null) {
        const diff = quote.last - quote.prev_close;
        const pct = quote.prev_close ? (diff / quote.prev_close) * 100 : 0;
        const text = `${diff >= 0 ? '+' : ''}${formatter.format(diff)} (${pct.toFixed(2)}%)`;
        const changeEl = document.getElementById('quoteChange');
        changeEl.textContent = text;
        changeEl.classList.toggle('text-emerald-500', diff >= 0);
        changeEl.classList.toggle('text-rose-500', diff < 0);
      }
      if (quote.prev_close !== null) document.getElementById('quotePrev').textContent = formatter.format(quote.prev_close);
      if (quote.day_high !== null) document.getElementById('quoteHigh').textContent = formatter.format(quote.day_high);
      if (quote.day_low !== null) document.getElementById('quoteLow').textContent = formatter.format(quote.day_low);
      if (quote.volume !== null) document.getElementById('quoteVol').textContent = new Intl.NumberFormat().format(quote.volume);
    } catch (err) {
      console.warn('Quote refresh failed', err);
    }
  }

  refreshQuote();
  let timer = setInterval(refreshQuote, refreshMs);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(timer);
    } else {
      refreshQuote();
      timer = setInterval(refreshQuote, refreshMs);
    }
  });
})();
</script>
