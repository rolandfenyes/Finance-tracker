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
/** @var bool $historyStale */
/** @var bool $fxStale */

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

  <div
    class="rounded-2xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-100 <?= empty($fxStale) ? 'hidden' : '' ?>"
    data-role="fx-rate-notice"
    aria-live="polite"
  >
    Currency conversion rates are updating in the background. Totals may adjust shortly.
  </div>

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
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold">Trade</h2>
        <p class="text-xs text-gray-500">Open the trade ticket to record buy or sell activity for <?= htmlspecialchars($symbol) ?>.</p>
      </div>
      <button type="button" class="btn btn-primary" data-dialog-open="detailTradeDialog">Open trade ticket</button>
    </div>
  </section>
</section>

<div class="dialog hidden" id="detailTradeDialog" role="dialog" aria-modal="true" aria-labelledby="detailTradeDialogTitle">
  <div class="dialog-backdrop" data-dialog-close></div>
  <div class="dialog-panel">
    <div class="dialog-header">
      <h2 id="detailTradeDialogTitle" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Trade <?= htmlspecialchars($symbol) ?></h2>
      <button type="button" class="dialog-close" data-dialog-close>&times;</button>
    </div>
    <form method="post" action="/stocks/trade" class="grid gap-3 sm:grid-cols-6">
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
            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $selectedCurrencyCode ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="<?= htmlspecialchars($selectedCurrencyCode) ?>" selected><?= htmlspecialchars($selectedCurrencyCode) ?></option>
        <?php endif; ?>
      </select>
      <input name="fee" type="number" step="0.01" placeholder="Fee" class="input" />
      <input name="trade_date" type="date" value="<?= date('Y-m-d') ?>" class="input" />
      <input name="trade_time" type="time" value="<?= date('H:i') ?>" class="input" />
      <input name="note" placeholder="Note" class="input sm:col-span-3" />
      <div class="sm:col-span-6 flex flex-wrap gap-2 text-xs text-gray-500">
        <span class="self-center">Quick presets:</span>
        <?php foreach ([1, 10, 100] as $preset): ?>
          <button type="button" class="rounded-full border border-emerald-200 px-3 py-1 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-500/40 dark:text-emerald-200 dark:hover:bg-emerald-500/10" data-quantity-preset="<?= $preset ?>">+<?= $preset ?></button>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary sm:col-span-6">Submit trade</button>
    </form>
  </div>
</div>

<?php if (!defined('STOCKS_DIALOG_STYLE')): ?>
  <?php define('STOCKS_DIALOG_STYLE', true); ?>
  <style>
    .dialog { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; }
    .dialog.hidden { display: none; }
    .dialog-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(2px); }
    .dialog-panel { position: relative; width: min(600px, 92vw); max-height: 90vh; overflow-y: auto; border-radius: 1.25rem; background: rgba(255,255,255,0.98); padding: 1.75rem; box-shadow: 0 35px 60px -25px rgba(15, 23, 42, 0.45); }
    .dark .dialog-panel { background: rgba(15, 23, 42, 0.92); }
    .dialog-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .dialog-close { width: 2.5rem; height: 2.5rem; border-radius: 9999px; font-size: 1.5rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center; color: rgba(100,116,139,0.8); background: rgba(148,163,184,0.12); }
    .dialog-close:hover { background: rgba(148,163,184,0.2); }
  </style>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
<script>
(function(){
  const initialPriceHistory = <?= json_encode($priceHistory) ?>;
  const initialPositionSeries = <?= json_encode($positionSeries) ?>;
  const symbol = <?= json_encode($symbol) ?>;
  const refreshMs = (<?= (int)($settings['refresh_seconds'] ?? 10) ?>) * 1000;
  const currency = <?= json_encode($currency) ?>;
  const initialRange = <?= json_encode(strtoupper($historyRange)) ?>;
  const baseCurrency = <?= json_encode($baseCurrency) ?>;
  const initialHistoryStale = <?= $historyStale ? 'true' : 'false' ?>;

  const rangeForm = document.getElementById('rangeForm');
  const rangeButtons = rangeForm ? Array.from(rangeForm.querySelectorAll('[data-range-btn]')) : [];
  const rangeHidden = rangeForm ? rangeForm.querySelector('input[name="range"]') : null;
  const fxRateNotice = document.querySelector('[data-role="fx-rate-notice"]');

  let historyRetryTimer = null;
  let historyRetryAttempts = 0;
  const maxHistoryRetries = 5;

  function scheduleHistoryRetry(range, delay = 2000) {
    if (historyRetryAttempts >= maxHistoryRetries) {
      return;
    }
    historyRetryAttempts += 1;
    if (historyRetryTimer) {
      clearTimeout(historyRetryTimer);
    }
    historyRetryTimer = setTimeout(() => {
      historyRetryTimer = null;
      loadRange(range).catch(() => {});
    }, delay);
  }

  function resetHistoryRetry() {
    historyRetryAttempts = 0;
    if (historyRetryTimer) {
      clearTimeout(historyRetryTimer);
      historyRetryTimer = null;
    }
  }

  const priceCtx = document.getElementById('priceHistoryChart');
  let priceChart = null;
  function ensurePriceChart(data) {
    if (!priceCtx) {
      return;
    }
    const labels = (data || []).map(item => item.date);
    const closes = (data || []).map(item => item.close);
    const ctx = priceCtx.getContext('2d');
    if (!ctx) {
      return;
    }
    const gradient = ctx.createLinearGradient(0, 0, 0, priceCtx.height);
    gradient.addColorStop(0, 'rgba(96, 165, 250, 0.45)');
    gradient.addColorStop(1, 'rgba(96, 165, 250, 0.08)');
    const formatPrice = value => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value);
    if (!priceChart) {
      priceChart = new Chart(priceCtx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data: closes,
            borderColor: '#60a5fa',
            tension: 0.35,
            fill: true,
            pointRadius: 0,
            spanGaps: true,
            backgroundColor: gradient
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: context => formatPrice(context.parsed.y || 0)
              }
            }
          },
          scales: {
            x: { display: false },
            y: {
              grid: { color: 'rgba(148, 163, 184, 0.2)' },
              ticks: {
                color: 'rgba(100,116,139,0.8)',
                callback: value => formatPrice(value)
              }
            }
          }
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
    const ctx = positionCtx.getContext('2d');
    if (!ctx) {
      return;
    }
    const gradient = ctx.createLinearGradient(0, 0, 0, positionCtx.height);
    gradient.addColorStop(0, 'rgba(52, 211, 153, 0.45)');
    gradient.addColorStop(1, 'rgba(52, 211, 153, 0.1)');
    const formatPosition = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: baseCurrency }).format(value);
    if (!positionChart) {
      positionChart = new Chart(positionCtx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data: values,
            borderColor: '#34d399',
            backgroundColor: gradient,
            fill: true,
            tension: 0.35,
            spanGaps: true,
            pointRadius: 0,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: context => formatPosition(context.parsed.y || 0)
              }
            }
          },
          scales: {
            x: { display: false },
            y: {
              grid: { color: 'rgba(148, 163, 184, 0.2)' },
              ticks: {
                color: 'rgba(100,116,139,0.8)',
                callback: value => formatPosition(value)
              }
            }
          }
        }
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

  if (initialHistoryStale || (initialPositionSeries && initialPositionSeries.stale)) {
    scheduleHistoryRetry(initialRange, 1500);
  }

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
      if (fxRateNotice && payload && Object.prototype.hasOwnProperty.call(payload, 'fxStale')) {
        if (payload.fxStale) {
          fxRateNotice.classList.remove('hidden');
        } else {
          fxRateNotice.classList.add('hidden');
        }
      }
      const needsRetry = payload && (payload.stale || (payload.position && payload.position.stale));
      if (needsRetry) {
        scheduleHistoryRetry(range, 2000);
      } else {
        resetHistoryRetry();
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

  const openDialog = (id) => {
    const dialog = document.getElementById(id);
    if (!dialog) return;
    dialog.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    if (id === 'detailTradeDialog') {
      const quantityInput = dialog.querySelector('input[name="quantity"]');
      if (quantityInput) {
        setTimeout(() => quantityInput.focus(), 50);
      }
    }
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

  const presetButtons = document.querySelectorAll('[data-quantity-preset]');
  if (presetButtons.length) {
    const quantityInput = document.querySelector('#detailTradeDialog input[name="quantity"]');
    presetButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        if (!quantityInput) return;
        const increment = parseFloat(btn.getAttribute('data-quantity-preset') || '0');
        const current = parseFloat(quantityInput.value || '0');
        const result = (isNaN(current) ? 0 : current) + (isNaN(increment) ? 0 : increment);
        quantityInput.value = (Math.round(result * 10000) / 10000).toString();
      });
    });
  }

  let refreshInFlight = false;
  let staleQuoteTimer = null;

  async function refreshQuote(){
    if (refreshInFlight) return;
    refreshInFlight = true;
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
      if (quote.stale) {
        if (staleQuoteTimer) clearTimeout(staleQuoteTimer);
        staleQuoteTimer = setTimeout(() => {
          staleQuoteTimer = null;
          refreshQuote();
        }, Math.min(refreshMs, 3000));
      } else if (staleQuoteTimer) {
        clearTimeout(staleQuoteTimer);
        staleQuoteTimer = null;
      }
    } catch (err) {
      console.warn('Quote refresh failed', err);
    } finally {
      refreshInFlight = false;
    }
  }

  refreshQuote();
  let timer = setInterval(refreshQuote, refreshMs);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(timer);
      if (staleQuoteTimer) {
        clearTimeout(staleQuoteTimer);
        staleQuoteTimer = null;
      }
    } else {
      refreshQuote();
      timer = setInterval(refreshQuote, refreshMs);
    }
  });
})();
</script>
