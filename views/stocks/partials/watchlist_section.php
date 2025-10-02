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
