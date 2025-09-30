<?php $hasPositions = !empty($positions); ?>
<?php $displayCurrency = strtoupper($base_currency); ?>
<?php $currencyOptions = array_values(array_filter($currencies ?? [], fn($c) => !empty($c['code']))); ?>
<?php if (empty($currencyOptions)) { $currencyOptions = [['code' => $displayCurrency]]; } ?>

<?php if (!empty($currencyOptions)): ?>
  <form method="get" class="mb-4 flex flex-wrap items-center justify-end gap-2">
    <?php foreach ($_GET as $paramKey => $paramValue): ?>
      <?php if ($paramKey === 'currency' || !is_scalar($paramValue)) continue; ?>
      <input type="hidden" name="<?= htmlspecialchars($paramKey) ?>" value="<?= htmlspecialchars($paramValue) ?>">
    <?php endforeach; ?>
    <label for="stocks-display-currency" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">
      <?= __('Display currency') ?>
    </label>
    <select id="stocks-display-currency" name="currency" class="select" onchange="this.form.submit()">
      <?php foreach ($currencyOptions as $option): ?>
        <?php $code = strtoupper($option['code']); ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= $code === $displayCurrency ? 'selected' : '' ?>>
          <?= htmlspecialchars($code) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>

<section class="grid gap-4 lg:grid-cols-4">
  <div class="card">
    <div class="card-kicker"><?= __('Portfolio') ?></div>
    <h2 class="card-title mt-1"><?= __('Market Value') ?></h2>
    <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-white" data-portfolio-value>
      <?= $hasPositions ? '—' : moneyfmt(0, $base_currency) ?>
    </p>
    <p class="card-subtle mt-2 flex items-center gap-2 text-xs">
      <span><?= __('Real-time quotes from Yahoo Finance.') ?></span>
      <span class="hidden items-center gap-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400" data-portfolio-updated>
        <i data-lucide="clock-3" class="h-3.5 w-3.5"></i>
        <span data-portfolio-updated-text>—</span>
      </span>
    </p>
  </div>

  <div class="card">
    <div class="card-kicker"><?= __('Cost Basis') ?></div>
    <h3 class="card-title mt-1"><?= __('Invested Capital') ?></h3>
    <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white" data-portfolio-cost>
      <?= moneyfmt($portfolio_cost_basis_main, $base_currency) ?>
    </p>
    <p class="card-subtle mt-2 text-xs"><?= __('Converted to :currency using today’s FX rates.', ['currency' => strtoupper($base_currency)]) ?></p>
  </div>

  <div class="card">
    <div class="card-kicker"><?= __('Performance') ?></div>
    <h3 class="card-title mt-1"><?= __('Unrealized P/L') ?></h3>
    <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white" data-portfolio-gain>—</p>
    <p class="card-subtle mt-2 text-xs" data-portfolio-gain-pct>—</p>
  </div>

  <div class="card">
    <div class="card-kicker"><?= __('Today') ?></div>
    <h3 class="card-title mt-1"><?= __('Day Change') ?></h3>
    <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white" data-portfolio-day>—</p>
    <p class="card-subtle mt-2 text-xs" data-portfolio-day-pct>—</p>
  </div>
</section>

<section class="mt-6 grid gap-6 lg:grid-cols-3">
  <div class="card lg:col-span-2">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Portfolio Value (30 days)') ?></h3>
        <p class="card-subtle text-xs"><?= __('Approximate value based on latest FX conversion.') ?></p>
      </div>
      <span class="chip" data-portfolio-history-loading><?= __('Loading…') ?></span>
    </div>
    <div class="mt-5 min-h-[18rem]">
      <?php if ($hasPositions): ?>
        <canvas id="portfolio-history-chart" class="h-72 w-full"></canvas>
      <?php else: ?>
        <div class="flex h-72 items-center justify-center text-sm text-slate-500 dark:text-slate-300">
          <?= __('Add a trade to start building your performance history.') ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Allocation') ?></h3>
      <span class="chip" data-allocation-total>—</span>
    </div>
    <div class="mt-5 min-h-[12rem]">
      <?php if ($hasPositions): ?>
        <canvas id="portfolio-allocation-chart" class="h-48 w-full"></canvas>
        <ul class="mt-5 space-y-2 text-sm" data-allocation-list></ul>
      <?php else: ?>
        <div class="flex h-48 items-center justify-center text-sm text-slate-500 dark:text-slate-300">
          <?= __('Your positions will appear here once you record trades.') ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="mt-6 grid gap-6 lg:grid-cols-3">
  <div class="card lg:col-span-2 overflow-x-auto">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Open Positions') ?></h3>
      <div class="text-xs text-slate-500 dark:text-slate-300">
        <?= __('Totals shown in :currency.', ['currency' => strtoupper($base_currency)]) ?>
      </div>
    </div>
    <?php if ($hasPositions): ?>
      <table class="table-glass mt-4 min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-300">
            <th class="py-2 pr-3"><?= __('Symbol') ?></th>
            <th class="py-2 pr-3"><?= __('Quantity') ?></th>
            <th class="py-2 pr-3"><?= __('Avg Buy') ?></th>
            <th class="py-2 pr-3"><?= __('Cost Basis') ?></th>
            <th class="py-2 pr-3"><?= __('Market Price') ?></th>
            <th class="py-2 pr-3"><?= __('Market Value') ?></th>
            <th class="py-2 pr-3"><?= __('Unrealized P/L') ?></th>
            <th class="py-2 pr-3"><?= __('Day Change') ?></th>
            <th class="py-2 pr-3"><?= __('Last trade') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($positions as $position): ?>
            <tr class="border-b border-white/40 last:border-0 dark:border-slate-800/60" data-stock-row data-symbol="<?= htmlspecialchars($position['symbol']) ?>">
              <td class="py-2 pr-3 font-semibold text-slate-900 dark:text-white">
                <div><?= htmlspecialchars($position['symbol']) ?></div>
              </td>
              <td class="py-2 pr-3">
                <?= number_format((float)$position['qty'], 6, '.', '') ?>
              </td>
              <td class="py-2 pr-3">
                <?= moneyfmt($position['avg_buy_price'], $position['currency']) ?>
              </td>
              <td class="py-2 pr-3 font-medium text-slate-900 dark:text-white">
                <?= moneyfmt($position['cost_main'], $base_currency) ?>
              </td>
              <td class="py-2 pr-3">
                <span data-stock-price>—</span>
              </td>
              <td class="py-2 pr-3 font-medium" data-stock-value>—</td>
              <td class="py-2 pr-3" data-stock-gain>
                <div class="flex flex-col">
                  <span data-stock-gain-amount>—</span>
                  <span class="text-xs" data-stock-gain-pct>—</span>
                </div>
              </td>
              <td class="py-2 pr-3" data-stock-day>
                <div class="flex flex-col">
                  <span data-stock-day-amount>—</span>
                  <span class="text-xs" data-stock-day-pct>—</span>
                </div>
              </td>
              <td class="py-2 pr-3 text-xs text-slate-500 dark:text-slate-300">
                <?= $position['last_trade_on'] ? htmlspecialchars($position['last_trade_on']) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="mt-6 flex flex-col items-center justify-center gap-3 rounded-3xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-300">
        <i data-lucide="line-chart" class="h-8 w-8 text-brand-500"></i>
        <p><?= __('No open positions yet. Add your trades below to start tracking performance.') ?></p>
      </div>
    <?php endif; ?>
    <p class="mt-3 hidden text-xs text-rose-500" data-stocks-error></p>
  </div>

  <div class="card">
    <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Record a trade') ?></h3>
    <p class="card-subtle mt-2 text-sm"><?= __('Keep your holdings up-to-date by logging buys and sells as they happen.') ?></p>

    <div class="mt-4 space-y-6">
      <div>
        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Buy') ?></h4>
        <form class="mt-3 grid gap-2 sm:grid-cols-6" method="post" action="/stocks/buy" data-trade-form data-trade-side="buy">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input name="symbol" class="input sm:col-span-2" placeholder="AAPL" required>
          <input name="price" type="number" step="0.0001" min="0" class="input sm:col-span-2" placeholder="<?= __('Price') ?>" required data-trade-price>
          <input name="amount" type="number" step="0.0001" min="0" class="input sm:col-span-2" placeholder="<?= __('Amount') ?>" required data-trade-amount>
          <input name="fee" type="number" step="0.0001" min="0" class="input sm:col-span-2" placeholder="<?= __('Fee') ?>" data-trade-fee>
          <select name="currency" class="select sm:col-span-2">
            <?php foreach ($currencyOptions as $option): ?>
              <?php $code = strtoupper($option['code']); ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $code === $displayCurrency ? 'selected' : '' ?>>
                <?= htmlspecialchars($code) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="input sm:col-span-2">
          <input type="hidden" name="quantity" value="" data-trade-quantity-input>
          <div class="sm:col-span-6 text-xs text-slate-500 dark:text-slate-300">
            <?= __('Calculated quantity:') ?> <span data-trade-quantity-display>—</span>
          </div>
          <button class="btn btn-primary sm:col-span-2"><?= __('Buy') ?></button>
        </form>
      </div>

      <div>
        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Sell') ?></h4>
        <form class="mt-3 grid gap-2 sm:grid-cols-6" method="post" action="/stocks/sell" data-trade-form data-trade-side="sell">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input name="symbol" class="input sm:col-span-2" placeholder="AAPL" required>
          <input name="price" type="number" step="0.0001" min="0" class="input sm:col-span-2" placeholder="<?= __('Price') ?>" required data-trade-price>
          <input name="amount" type="number" step="0.0001" min="0" class="input sm:col-span-2" placeholder="<?= __('Amount') ?>" required data-trade-amount>
          <input name="fee" type="number" step="0.0001" min="0" class="input sm:col-span-2" placeholder="<?= __('Fee') ?>" data-trade-fee>
          <select name="currency" class="select sm:col-span-2">
            <?php foreach ($currencyOptions as $option): ?>
              <?php $code = strtoupper($option['code']); ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $code === $displayCurrency ? 'selected' : '' ?>>
                <?= htmlspecialchars($code) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="input sm:col-span-2">
          <input type="hidden" name="quantity" value="" data-trade-quantity-input>
          <div class="sm:col-span-6 text-xs text-slate-500 dark:text-slate-300">
            <?= __('Calculated quantity:') ?> <span data-trade-quantity-display>—</span>
          </div>
          <button class="btn btn-danger sm:col-span-2"><?= __('Sell') ?></button>
        </form>
      </div>
    </div>
  </div>
</section>

<section class="mt-6 card overflow-x-auto">
  <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Recent Trades') ?></h3>
  <?php if (!empty($trades)): ?>
    <table class="table-glass mt-4 min-w-full text-sm">
      <thead>
        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-300">
          <th class="py-2 pr-3"><?= __('Date') ?></th>
          <th class="py-2 pr-3"><?= __('Side') ?></th>
          <th class="py-2 pr-3"><?= __('Symbol') ?></th>
          <th class="py-2 pr-3"><?= __('Quantity') ?></th>
          <th class="py-2 pr-3"><?= __('Price') ?></th>
          <th class="py-2 pr-3"><?= __('Amount') ?></th>
          <th class="py-2 pr-3"><?= __('Fee') ?></th>
          <th class="py-2 pr-3"><?= __('Currency') ?></th>
          <th class="py-2 pr-3"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($trades as $trade): ?>
          <tr class="border-b border-white/40 last:border-0 dark:border-slate-800/60">
            <td class="py-2 pr-3"><?= htmlspecialchars($trade['trade_on']) ?></td>
            <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($trade['side']) ?></td>
            <td class="py-2 pr-3 font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($trade['symbol']) ?></td>
            <td class="py-2 pr-3"><?= number_format((float)$trade['quantity'], 6, '.', '') ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($trade['price'], $trade['currency']) ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($trade['amount'], $trade['currency']) ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($trade['fee'], $trade['currency']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($trade['currency']) ?></td>
            <td class="py-2 pr-3">
              <form method="post" action="/stocks/trade/delete" onsubmit="return confirm('<?= __('Delete trade?') ?>')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= (int)$trade['id'] ?>" />
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
  <?php else: ?>
    <div class="mt-6 text-sm text-slate-500 dark:text-slate-300">
      <?= __('No trades logged yet.') ?>
    </div>
  <?php endif; ?>
</section>

<script>
  (function () {
    const forms = document.querySelectorAll('[data-trade-form]');
    if (!forms.length) return;

    const formatQuantity = (qty) => {
      if (!Number.isFinite(qty) || qty <= 0) {
        return '—';
      }
      const fixed = qty.toFixed(6);
      return fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
    };

    forms.forEach((form) => {
      const priceInput = form.querySelector('[data-trade-price]');
      const amountInput = form.querySelector('[data-trade-amount]');
      const feeInput = form.querySelector('[data-trade-fee]');
      const quantityInput = form.querySelector('[data-trade-quantity-input]');
      const quantityDisplay = form.querySelector('[data-trade-quantity-display]');

      const update = () => {
        const price = parseFloat(priceInput ? priceInput.value : '');
        const amount = parseFloat(amountInput ? amountInput.value : '');
        const fee = parseFloat(feeInput ? feeInput.value : '');
        let quantity = 0;

        const netAmount = (Number.isFinite(amount) ? amount : NaN) - (Number.isFinite(fee) ? Math.max(fee, 0) : 0);

        if (Number.isFinite(price) && price > 0 && Number.isFinite(netAmount) && netAmount > 0) {
          quantity = netAmount / price;
        }

        if (quantityInput) {
          quantityInput.value = quantity > 0 ? quantity.toFixed(6) : '';
        }
        if (quantityDisplay) {
          quantityDisplay.textContent = formatQuantity(quantity);
        }
      };

      [priceInput, amountInput, feeInput].forEach((input) => {
        if (input) {
          input.addEventListener('input', update);
          input.addEventListener('blur', update);
        }
      });

      form.addEventListener('submit', update);

      update();
    });
  })();
</script>

<script>
  window.MyMoneyMapStocksPage = {
    baseCurrency: <?= json_encode($base_currency, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    asOfDate: <?= json_encode($as_of, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    costBasis: <?= json_encode($portfolio_cost_basis_main, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    positions: <?= $positions_payload ?? '[]' ?>,
    currencyRates: <?= $currency_rates_payload ?? '{}' ?>
  };
</script>

<script>
  (function () {
    const data = window.MyMoneyMapStocksPage || {};

    const boot = () => {
      const toolkit = window.MyMoneyMapStocksToolkit || {};
      if (!toolkit || typeof toolkit.fetchQuotes !== 'function') {
        return;
      }

      const positions = Array.isArray(data.positions) ? data.positions : [];
      const baseCurrency = data.baseCurrency || 'USD';
      const currencyRates = data.currencyRates || {};
      const costBasis = Number(data.costBasis || 0);

      const valueEl = document.querySelector('[data-portfolio-value]');
      const costEl = document.querySelector('[data-portfolio-cost]');
      const gainEl = document.querySelector('[data-portfolio-gain]');
      const gainPctEl = document.querySelector('[data-portfolio-gain-pct]');
      const dayEl = document.querySelector('[data-portfolio-day]');
      const dayPctEl = document.querySelector('[data-portfolio-day-pct]');
      const updatedWrap = document.querySelector('[data-portfolio-updated]');
      const updatedText = document.querySelector('[data-portfolio-updated-text]');
      const errorEl = document.querySelector('[data-stocks-error]');
      const allocationList = document.querySelector('[data-allocation-list]');
      const allocationTotal = document.querySelector('[data-allocation-total]');
      const historyChip = document.querySelector('[data-portfolio-history-loading]');

      if (costEl) {
        costEl.textContent = toolkit.formatCurrency(costBasis, baseCurrency);
      }

      if (!positions.length) {
        if (valueEl) {
          valueEl.textContent = toolkit.formatCurrency(costBasis, baseCurrency);
        }
        if (gainEl) gainEl.textContent = '—';
        if (gainPctEl) gainPctEl.textContent = '—';
        if (dayEl) dayEl.textContent = '—';
        if (dayPctEl) dayPctEl.textContent = '—';
        if (allocationTotal) allocationTotal.textContent = '—';
        if (historyChip) historyChip.classList.add('hidden');
        return;
      }

      const rateFor = (currency, fallback) => {
        const key = currency ? String(currency).toUpperCase() : '';
        if (key && typeof currencyRates[key] !== 'undefined') {
          return Number(currencyRates[key]);
        }
        if (fallback && typeof currencyRates[fallback] !== 'undefined') {
          return Number(currencyRates[fallback]);
        }
        return 1;
      };

      const updateRow = (symbol, snapshot) => {
        const row = document.querySelector(`[data-stock-row][data-symbol="${CSS.escape(symbol)}"]`);
        if (!row) return;

        const priceEl = row.querySelector('[data-stock-price]');
        const valueCell = row.querySelector('[data-stock-value]');
      const gainAmountEl = row.querySelector('[data-stock-gain-amount]');
      const gainPctCell = row.querySelector('[data-stock-gain-pct]');
      const dayAmountEl = row.querySelector('[data-stock-day-amount]');
      const dayPctEl = row.querySelector('[data-stock-day-pct]');

      if (priceEl) {
        priceEl.textContent = snapshot.price !== null
          ? toolkit.formatCurrency(snapshot.price, snapshot.quoteCurrency, { showCode: true })
          : '—';
      }

      if (valueCell) {
        valueCell.textContent = toolkit.formatCurrency(snapshot.value, baseCurrency);
        valueCell.classList.toggle('text-emerald-600', snapshot.value >= snapshot.cost);
      }

      if (gainAmountEl && gainPctCell) {
        gainAmountEl.textContent = toolkit.formatCurrency(snapshot.gain, baseCurrency);
        gainPctCell.textContent = toolkit.formatPercent(snapshot.gainPct);
        const positive = snapshot.gain > 0;
        const negative = snapshot.gain < 0;
        gainAmountEl.classList.toggle('text-emerald-600', positive);
        gainAmountEl.classList.toggle('text-rose-600', negative);
        gainPctCell.classList.toggle('text-emerald-600', positive);
        gainPctCell.classList.toggle('text-rose-600', negative);
      }

      if (dayAmountEl && dayPctEl) {
        dayAmountEl.textContent = toolkit.formatCurrency(snapshot.dayChange, baseCurrency);
        dayPctEl.textContent = toolkit.formatPercent(snapshot.dayChangePct);
        const positive = snapshot.dayChange > 0;
        const negative = snapshot.dayChange < 0;
        dayAmountEl.classList.toggle('text-emerald-600', positive);
        dayAmountEl.classList.toggle('text-rose-600', negative);
        dayPctEl.classList.toggle('text-emerald-600', positive);
        dayPctEl.classList.toggle('text-rose-600', negative);
      }
      };

      const computeSnapshots = (quotes) => {
        let totalValue = 0;
        let totalCost = 0;
        let totalDayChange = 0;
        let latestTs = 0;
        const holdings = [];

      positions.forEach((pos) => {
        const symbol = String(pos.symbol || '').toUpperCase();
        const qty = Number(pos.qty || 0);
        const cost = Number(pos.cost_main || 0);
        const rateFallback = pos.currency ? String(pos.currency).toUpperCase() : undefined;
        totalCost += cost;

        const quote = quotes[symbol] || null;
        const quoteCurrency = quote && quote.currency ? quote.currency : (quote && quote.financialCurrency ? quote.financialCurrency : rateFallback);
        const rate = rateFor(quoteCurrency, rateFallback);
        const price = quote && typeof quote.regularMarketPrice === 'number' ? quote.regularMarketPrice : null;
        const change = quote && typeof quote.regularMarketChange === 'number' ? quote.regularMarketChange : 0;
        const priceValue = price !== null ? price * qty : null;
        const value = priceValue !== null ? priceValue * rate : cost;
        const dayChange = price !== null ? change * qty * rate : 0;
        const prevValue = value - dayChange;
        const dayChangePct = prevValue > 0 ? (dayChange / prevValue) * 100 : 0;
        const gain = value - cost;
        const gainPct = cost > 0 ? (gain / cost) * 100 : 0;

        if (quote && quote.regularMarketTime) {
          latestTs = Math.max(latestTs, Number(quote.regularMarketTime) * 1000);
        }

        totalValue += value;
        totalDayChange += dayChange;

        holdings.push({
          symbol,
          qty,
          name: quote && (quote.shortName || quote.longName) ? (quote.shortName || quote.longName) : symbol,
          quoteCurrency: quoteCurrency || rateFallback || baseCurrency,
          price,
          cost,
          value,
          gain,
          gainPct,
          dayChange,
          dayChangePct,
        });
      });

      const totalGain = totalValue - totalCost;
      const previousTotal = totalValue - totalDayChange;

      holdings.sort((a, b) => b.value - a.value);
      holdings.forEach((h) => {
        h.allocation = totalValue > 0 ? (h.value / totalValue) * 100 : 0;
      });

      return {
        holdings,
        totalValue,
        totalCost,
        totalGain,
        totalGainPct: totalCost > 0 ? (totalGain / totalCost) * 100 : 0,
        totalDayChange,
        totalDayPct: previousTotal > 0 ? (totalDayChange / previousTotal) * 100 : 0,
        updatedAt: latestTs || null,
      };
      };

      const loadQuotes = async () => {
        try {
          const quotes = await toolkit.fetchQuotes(positions.map((p) => p.symbol));
          return quotes || {};
        } catch (err) {
          if (errorEl) {
          errorEl.textContent = '<?= __('Unable to fetch live quotes right now. Please try again later.') ?>';
          errorEl.classList.remove('hidden');
        }
        console.error(err);
        return {};
      }
      };

      const renderAllocation = (holdings) => {
        if (!allocationList) return;
        allocationList.innerHTML = '';
        holdings.slice(0, 6).forEach((h) => {
          const li = document.createElement('li');
          li.className = 'flex items-center justify-between rounded-2xl border border-white/50 px-3 py-2 shadow-sm backdrop-blur dark:border-slate-800/60';
        const left = document.createElement('div');
        left.className = 'flex flex-col';
        const sym = document.createElement('span');
        sym.className = 'font-semibold text-slate-900 dark:text-white';
        sym.textContent = h.symbol;
        const name = document.createElement('span');
        name.className = 'text-xs text-slate-500 dark:text-slate-300';
        name.textContent = h.name;
        left.appendChild(sym);
        left.appendChild(name);

        const right = document.createElement('div');
        right.className = 'text-right';
        const val = document.createElement('div');
        val.className = 'text-sm font-semibold text-brand-700 dark:text-brand-200';
        val.textContent = toolkit.formatCurrency(h.value, baseCurrency);
        const pct = document.createElement('div');
        pct.className = 'text-xs text-slate-500 dark:text-slate-300';
        pct.textContent = toolkit.formatPercent(h.allocation);
        right.appendChild(val);
        right.appendChild(pct);

        li.appendChild(left);
        li.appendChild(right);
        allocationList.appendChild(li);
      });

      if (allocationTotal) {
        allocationTotal.textContent = toolkit.formatCurrency(
          holdings.reduce((acc, h) => acc + h.value, 0),
          baseCurrency
        );
      }
      };

      const renderHistory = async (holdings) => {
        if (!document.getElementById('portfolio-history-chart')) {
          if (historyChip) historyChip.classList.add('hidden');
          return;
        }

      try {
        const histories = await Promise.all(positions.map((pos) => toolkit.fetchHistory(pos.symbol)));
        const aggregate = toolkit.buildPortfolioHistory(positions, histories, currencyRates, baseCurrency);
        if (aggregate.labels.length && typeof window.renderLineChart === 'function') {
          window.renderLineChart('portfolio-history-chart', aggregate.labels, aggregate.values.map((v) => Number(v.toFixed(2))));
        }
      } catch (err) {
        console.error(err);
      } finally {
        if (historyChip) historyChip.classList.add('hidden');
      }
      };

      const init = async () => {
        const quotes = await loadQuotes();
        const portfolio = computeSnapshots(quotes);

        if (valueEl) {
          valueEl.textContent = toolkit.formatCurrency(portfolio.totalValue, baseCurrency);
      }

      if (gainEl) {
        gainEl.textContent = toolkit.formatCurrency(portfolio.totalGain, baseCurrency);
        gainEl.classList.toggle('text-emerald-600', portfolio.totalGain > 0);
        gainEl.classList.toggle('text-rose-600', portfolio.totalGain < 0);
      }

      if (gainPctEl) {
        gainPctEl.textContent = toolkit.formatPercent(portfolio.totalGainPct);
        gainPctEl.classList.toggle('text-emerald-600', portfolio.totalGain > 0);
        gainPctEl.classList.toggle('text-rose-600', portfolio.totalGain < 0);
      }

      if (dayEl) {
        dayEl.textContent = toolkit.formatCurrency(portfolio.totalDayChange, baseCurrency);
        dayEl.classList.toggle('text-emerald-600', portfolio.totalDayChange > 0);
        dayEl.classList.toggle('text-rose-600', portfolio.totalDayChange < 0);
      }

      if (dayPctEl) {
        dayPctEl.textContent = toolkit.formatPercent(portfolio.totalDayPct);
        dayPctEl.classList.toggle('text-emerald-600', portfolio.totalDayChange > 0);
        dayPctEl.classList.toggle('text-rose-600', portfolio.totalDayChange < 0);
      }

      if (portfolio.updatedAt && updatedWrap && updatedText) {
        updatedWrap.classList.remove('hidden');
        updatedText.textContent = new Date(portfolio.updatedAt).toLocaleString();
      }

      portfolio.holdings.forEach((h) => updateRow(h.symbol, h));

      if (typeof window.renderDoughnut === 'function' && document.getElementById('portfolio-allocation-chart')) {
        const labels = portfolio.holdings.map((h) => h.symbol);
        const values = portfolio.holdings.map((h) => Number(h.value.toFixed(2)));
        window.renderDoughnut('portfolio-allocation-chart', labels, values);
      }

      renderAllocation(portfolio.holdings);
      renderHistory(portfolio.holdings);
      };

      init();
    };

    if (window.MyMoneyMapStocksToolkit && typeof window.MyMoneyMapStocksToolkit.fetchQuotes === 'function') {
      boot();
    } else {
      window.addEventListener('stocks-toolkit-ready', boot, { once: true });
    }
  })();
</script>
