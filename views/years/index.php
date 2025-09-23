<?php
require_once __DIR__.'/../layout/page_header.php';
require_once __DIR__.'/../layout/focus_panel.php';

$yearsCount = $ymax - $ymin + 1;
$latest = $byYear[$ymax] ?? ['income' => 0, 'spending' => 0];
$latestNet = ($latest['income'] ?? 0) - ($latest['spending'] ?? 0);

render_page_header([
  'kicker' => __('History'),
  'title' => __('Yearly performance timeline'),
  'subtitle' => __('Compare income, spending, and net results across every year you’ve tracked.'),
  'meta' => [
    ['icon' => 'calendar', 'label' => __('Years tracked: :count', ['count' => $yearsCount])],
  ],
  'insight' => [
    'label' => __('Latest year net'),
    'value' => moneyfmt($latestNet),
    'subline' => __('Income :income · Spending :spending', [
      'income' => moneyfmt($latest['income'] ?? 0),
      'spending' => moneyfmt($latest['spending'] ?? 0),
    ]),
  ],
  'actions' => [
    ['label' => __('Open current year'), 'href' => '/years/'.date('Y'), 'icon' => 'calendar-days', 'style' => 'primary'],
    ['label' => __('Jump to this month'), 'href' => '/current-month', 'icon' => 'calendar-range', 'style' => 'muted'],
  ],
]);

$positiveYears = 0;
$negativeYears = 0;
foreach ($byYear as $row) {
  $net = ($row['income'] ?? 0) - ($row['spending'] ?? 0);
  if ($net >= 0) {
    $positiveYears++;
  } else {
    $negativeYears++;
  }
}

render_focus_panel([
  'id' => 'years-focus',
  'title' => __('Turn hindsight into smarter plans'),
  'description' => __('Spot trends, revisit the strongest months, and capture lessons before the next season starts.'),
  'items' => [
    [
      'icon' => 'calendar-search',
      'label' => __('Jump into the current year'),
      'description' => __('Drill into monthly breakdowns to compare against your present momentum.'),
      'href' => '/years/'.date('Y').'#year-months',
      'state' => 'active',
      'state_label' => __('Explore now'),
    ],
    [
      'icon' => 'trending-up',
      'label' => __('Identify winning years'),
      'description' => __('Look for income spikes or expense dips to repeat what worked well.'),
      'href' => '#years-grid',
      'state' => $positiveYears > 0 ? 'success' : 'info',
      'state_label' => $positiveYears > 0 ? __('Positive years: :count', ['count' => $positiveYears]) : __('No data yet'),
    ],
    [
      'icon' => 'trending-down',
      'label' => __('Study tougher seasons'),
      'description' => __('Open low-performing years to investigate which categories ran hot.'),
      'href' => '#years-grid',
      'state' => $negativeYears > 0 ? 'warning' : 'info',
      'state_label' => $negativeYears > 0 ? __('Years to review: :count', ['count' => $negativeYears]) : __('All positive'),
    ],
    [
      'icon' => 'download',
      'label' => __('Archive yearly summaries'),
      'description' => __('Export or screenshot highlights for annual reviews or tax records.'),
      'href' => '#years-grid',
      'state' => 'info',
      'state_label' => __('Optional'),
    ],
  ],
  'side' => [
    'label' => __('Latest net result'),
    'value' => moneyfmt($latestNet),
    'subline' => __('Income :income · Spending :spending', [
      'income' => moneyfmt($latest['income'] ?? 0),
      'spending' => moneyfmt($latest['spending'] ?? 0),
    ]),
    'footnote' => __('Use the workflow strip to hop from Review back to Orient when you spot a new insight.'),
    'actions' => [
      ['label' => __('Open month analytics'), 'href' => '/current-month#trends', 'icon' => 'chart-area'],
      ['label' => __('Check goals progress'), 'href' => '/goals#goal-list', 'icon' => 'target'],
    ],
  ],
  'tips' => [
    __('Combine this page with your command palette (⌘/Ctrl+K) to jump straight into any year or page.'),
    __('Notice when emergency fund contributions spiked—mirror that cadence in the months ahead.'),
  ],
]);
?>

<section id="years-grid" class="card">
  <h1 class="text-xl font-semibold">Years</h1>
  <ul class="mt-4 grid sm:grid-cols-3 gap-3">
    <?php for($y=$ymax; $y>=$ymin; $y--): $row=$byYear[$y] ?? ['income'=>0,'spending'=>0]; $net=$row['income']-$row['spending']; ?>
      <li class="panel p-4 hover:shadow">
        <a class="flex items-center justify-between" href="/years/<?= $y ?>">
          <div>
            <div class="text-lg font-semibold"><?= $y ?></div>
            <div class="text-xs text-gray-500">Income: <?= moneyfmt($row['income']) ?> · Spending: <?= moneyfmt($row['spending']) ?></div>
          </div>
          <div class="text-sm font-medium <?= $net>=0?'text-brand-600':'text-red-600' ?>"><?= moneyfmt($net) ?></div>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</section>