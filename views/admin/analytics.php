<?php
$currency = strtoupper(trim((string)($currency ?? billing_default_currency())));
$kpis = $kpis ?? [];
$growthSeries = $growthSeries ?? ['daily' => [], 'weekly' => [], 'monthly' => []];
$conversionSeries = $conversionSeries ?? [];
$revenueSeries = $revenueSeries ?? [];
$churnSeries = $churnSeries ?? [];
$errorMetrics = $errorMetrics ?? [];

$formatPercent = static function (?float $value, int $decimals = 1): string {
    if ($value === null) {
        return '—';
    }

    return __(':value%', ['value' => number_format($value, $decimals)]);
};

$formatHours = static function (?float $value): string {
    if ($value === null) {
        return '—';
    }

    return __(':value hrs', ['value' => number_format($value, 2)]);
};

$tz = new DateTimeZone('UTC');
$formatDate = static function (?string $value, string $format) use ($tz): string {
    if (!$value) {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, $tz);
        return $dt->format($format);
    } catch (Throwable $e) {
        return '';
    }
};

$kpiCards = [
    [
        'label' => __('Active users'),
        'value' => number_format((int)($kpis['active_users'] ?? 0)),
        'icon' => 'users',
    ],
    [
        'label' => __('Premium users'),
        'value' => number_format((int)($kpis['premium_users'] ?? 0)),
        'icon' => 'star',
    ],
    [
        'label' => __('Active subscriptions'),
        'value' => number_format((int)($kpis['active_subscriptions'] ?? 0)),
        'icon' => 'repeat',
    ],
    [
        'label' => __('Monthly recurring revenue'),
        'value' => moneyfmt((float)($kpis['mrr'] ?? 0), $currency),
        'icon' => 'bar-chart-3',
    ],
    [
        'label' => __('Annual recurring revenue'),
        'value' => moneyfmt((float)($kpis['arr'] ?? 0), $currency),
        'icon' => 'line-chart',
    ],
    [
        'label' => __('Revenue (last 30 days)'),
        'value' => moneyfmt((float)($kpis['revenue_30d'] ?? 0), $currency),
        'icon' => 'wallet',
    ],
    [
        'label' => __('Churn rate (30 days)'),
        'value' => $formatPercent(isset($kpis['churn_rate']) ? (float)$kpis['churn_rate'] : null),
        'icon' => 'trending-down',
    ],
    [
        'label' => __('Conversion rate'),
        'value' => $formatPercent(isset($kpis['conversion_rate']) ? (float)$kpis['conversion_rate'] : null),
        'icon' => 'sparkles',
    ],
];
?>

<div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
      <i data-lucide="line-chart" class="h-4 w-4"></i>
      <?= __('Administration') ?>
    </div>
    <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="card-title text-3xl font-semibold text-slate-900 dark:text-white">
          <?= __('Analytics & insights') ?>
        </h1>
        <p class="card-subtle mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Data stories to guide strategic growth across the platform.') ?>
        </p>
      </div>
      <div class="rounded-3xl border border-emerald-200/60 bg-emerald-50/70 px-4 py-3 text-xs text-emerald-700 shadow-sm dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-100">
        <?= __('Default billing currency: :currency', ['currency' => $currency]) ?>
      </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <?php foreach ($kpiCards as $card): ?>
        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= htmlspecialchars($card['label']) ?></span>
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-brand-500/15 text-brand-700 dark:bg-brand-500/25 dark:text-brand-200">
              <i data-lucide="<?= htmlspecialchars($card['icon']) ?>" class="h-4 w-4"></i>
            </span>
          </div>
          <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">
            <?= htmlspecialchars($card['value']) ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
      <i data-lucide="trending-up" class="h-4 w-4"></i>
      <?= __('User growth') ?>
    </div>
    <h2 class="card-title mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
      <?= __('Daily, weekly, and monthly momentum at a glance') ?>
    </h2>
    <p class="card-subtle mt-2 text-sm text-slate-600 dark:text-slate-300/80">
      <?= __('Spot adoption trends and plan onboarding experiments with confidence.') ?>
    </p>

    <?php
      $growthBlocks = [
        'daily' => [
          'title' => __('Daily growth'),
          'subtitle' => __('New users — last :period', ['period' => __(':count days', ['count' => 14])]),
        ],
        'weekly' => [
          'title' => __('Weekly growth'),
          'subtitle' => __('New users — last :period', ['period' => __(':count weeks', ['count' => 12])]),
        ],
        'monthly' => [
          'title' => __('Monthly growth'),
          'subtitle' => __('New users — last :period', ['period' => __(':count months', ['count' => 12])]),
        ],
      ];
    ?>
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <?php foreach ($growthBlocks as $key => $meta):
        $series = $growthSeries[$key] ?? [];
        $maxValue = 0;
        foreach ($series as $point) {
            $maxValue = max($maxValue, (int)($point['value'] ?? 0));
        }
      ?>
        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($meta['title']) ?></h3>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($meta['subtitle']) ?></p>
            </div>
          </div>

          <?php if ($series): ?>
            <div class="mt-5 flex items-end gap-3 overflow-x-auto pb-2">
              <?php foreach ($series as $point):
                $value = (int)($point['value'] ?? 0);
                $barHeight = 0;
                if ($maxValue > 0 && $value > 0) {
                    $barHeight = (int)round(($value / $maxValue) * 100);
                    if ($barHeight < 6) {
                        $barHeight = 6;
                    }
                }
                $label = '';
                if ($key === 'daily') {
                    $label = $formatDate($point['date'] ?? null, 'M j');
                } elseif ($key === 'weekly') {
                    $start = $formatDate($point['date'] ?? null, 'M j');
                    $end = $formatDate($point['end'] ?? null, 'M j');
                    if ($start && $end) {
                        $label = __('Week of :date', ['date' => $start]) . ' — ' . $end;
                    } else {
                        $label = __('Week of :date', ['date' => $start]);
                    }
                } else {
                    $label = $formatDate($point['date'] ?? null, 'M Y');
                }
              ?>
                <div class="flex min-w-[3.5rem] flex-col items-center gap-2">
                  <div class="flex h-32 w-10 items-end justify-center rounded-2xl bg-slate-100 dark:bg-slate-800/60">
                    <div class="w-7 rounded-t-2xl bg-gradient-to-t from-brand-500/80 to-brand-300/90 dark:from-brand-400/80 dark:to-brand-200/80" style="height: <?= (int)$barHeight ?>px"></div>
                  </div>
                  <span class="text-xs font-semibold text-slate-700 dark:text-slate-200"><?= number_format($value) ?></span>
                  <span class="text-[10px] text-center uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <?= htmlspecialchars($label) ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white/60 p-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-400">
              <?= __('No user growth data yet.') ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
      <i data-lucide="rocket" class="h-4 w-4"></i>
      <?= __('Conversion rate tracking') ?>
    </div>
    <h2 class="card-title mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
      <?= __('Monitor premium adoption and monthly revenue cadence') ?>
    </h2>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
          <?= __('Premium share') ?>
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          <?= __('Percentage of signups upgrading each month.') ?>
        </p>
        <?php if ($conversionSeries):
          $maxRate = 0;
          foreach ($conversionSeries as $row) {
              $maxRate = max($maxRate, (float)($row['value'] ?? 0));
          }
          $maxRate = $maxRate > 0 ? $maxRate : 100;
        ?>
          <div class="mt-5 space-y-3">
            <?php foreach ($conversionSeries as $row):
              $rate = isset($row['value']) ? (float)$row['value'] : null;
              $barWidth = 0;
              if ($rate !== null && $maxRate > 0) {
                  $barWidth = max(4, (int)round(($rate / $maxRate) * 100));
              }
              $monthLabel = $formatDate($row['start'] ?? null, 'M Y');
            ?>
              <div>
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                  <span><?= htmlspecialchars($monthLabel) ?></span>
                  <span class="font-semibold text-slate-700 dark:text-slate-200">
                    <?= $rate === null ? '—' : htmlspecialchars($formatPercent($rate)) ?>
                  </span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-200 dark:bg-slate-800">
                  <?php if ($rate !== null): ?>
                    <div class="h-2 rounded-full bg-gradient-to-r from-brand-500 to-brand-300 dark:from-brand-400 dark:to-brand-200" style="width: <?= $barWidth ?>%"></div>
                  <?php endif; ?>
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                  <?= __(':premium premium of :total total signups', [
                    'premium' => number_format((int)($row['premium'] ?? 0)),
                    'total' => number_format((int)($row['total'] ?? 0)),
                  ]) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white/60 p-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-400">
            <?= __('No conversion data yet.') ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
          <?= __('Revenue momentum') ?>
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          <?= __('Track collected payments and churned subscriptions by month.') ?>
        </p>
        <?php if ($revenueSeries || $churnSeries):
          $churnMap = [];
          foreach ($churnSeries as $row) {
              $churnMap[$row['date'] ?? ''] = (int)($row['value'] ?? 0);
          }
        ?>
          <div class="mt-5 overflow-hidden rounded-2xl border border-slate-100 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800">
              <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                <tr>
                  <th class="px-4 py-3 font-semibold">
                    <?= __('Month') ?>
                  </th>
                  <th class="px-4 py-3 font-semibold text-right">
                    <?= __('Monthly revenue') ?>
                  </th>
                  <th class="px-4 py-3 font-semibold text-right">
                    <?= __('Churned subscriptions') ?>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($revenueSeries as $row):
                  $monthKey = $row['date'] ?? '';
                  $revenue = (float)($row['value'] ?? 0);
                  $churn = $churnMap[$monthKey] ?? 0;
                  $monthLabel = $formatDate($row['start'] ?? null, 'M Y');
                ?>
                  <tr class="transition hover:bg-brand-50/40 dark:hover:bg-slate-800/30">
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                      <?= htmlspecialchars($monthLabel) ?>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-200">
                      <?= htmlspecialchars(moneyfmt($revenue, $currency)) ?>
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-slate-600 dark:text-slate-300">
                      <?= number_format($churn) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white/60 p-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-400">
            <?= __('No revenue data yet.') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
      <i data-lucide="heart-pulse" class="h-4 w-4"></i>
      <?= __('System health') ?>
    </div>
    <h2 class="card-title mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
      <?= __('Operational signals across authentication and billing') ?>
    </h2>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= __('Login error rate') ?></span>
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-rose-500/15 text-rose-600 dark:bg-rose-500/20 dark:text-rose-200">
            <i data-lucide="shield-alert" class="h-4 w-4"></i>
          </span>
        </div>
        <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars($formatPercent(isset($errorMetrics['login_error_rate']) ? (float)$errorMetrics['login_error_rate'] : null, 2)) ?>
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
          <?= __(':count login attempts past 7 days', ['count' => number_format((int)($errorMetrics['login_total'] ?? 0))]) ?>
        </p>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= __('Authentication success rate') ?></span>
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-200">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i>
          </span>
        </div>
        <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars($formatPercent(isset($errorMetrics['auth_success_rate']) ? (float)$errorMetrics['auth_success_rate'] : null, 2)) ?>
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
          <?= __('7-day rolling view of successful sign-ins.') ?>
        </p>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= __('Payment failure rate') ?></span>
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600 dark:bg-amber-500/20 dark:text-amber-200">
            <i data-lucide="credit-card" class="h-4 w-4"></i>
          </span>
        </div>
        <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars($formatPercent(isset($errorMetrics['payment_failure_rate']) ? (float)$errorMetrics['payment_failure_rate'] : null, 2)) ?>
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
          <?= __(':count processed charges past 30 days', ['count' => number_format((int)($errorMetrics['payment_total'] ?? 0))]) ?>
        </p>
      </div>

      <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= __('Average payment processing time') ?></span>
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-sky-500/15 text-sky-600 dark:bg-sky-500/20 dark:text-sky-200">
            <i data-lucide="timer" class="h-4 w-4"></i>
          </span>
        </div>
        <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">
          <?= htmlspecialchars($formatHours(isset($errorMetrics['avg_payment_latency_hours']) ? (float)$errorMetrics['avg_payment_latency_hours'] : null)) ?>
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
          <?= __('Average hours between charge creation and settlement (30 days).') ?>
        </p>
      </div>
    </div>
  </section>
</div>
