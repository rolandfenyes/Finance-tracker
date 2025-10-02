<?php
/** @var array $totals */
/** @var array $chartMeta */
/** @var array $portfolioChart */
/** @var string $baseCurrency */
/** @var array $marketByCurrency */
/** @var string $preferredCurrency */
/** @var float|null $preferredMarket */
/** @var float|null $preferredUnrealized */
?>
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
        <?php foreach (['1M','3M','6M','1Y','5Y'] as $option): ?>
          <?php $isActive = strtoupper($chartMeta['range']) === $option; ?>
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
