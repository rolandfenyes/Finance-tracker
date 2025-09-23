<?php
require_once __DIR__.'/../layout/page_header.php';
require_once __DIR__.'/../layout/focus_panel.php';

$totalIncome = 0;
$totalSpending = 0;
foreach ($byMonth as $row) {
  $totalIncome += $row['income'] ?? 0;
  $totalSpending += $row['spending'] ?? 0;
}
$netYear = $totalIncome - $totalSpending;

render_page_header([
  'kicker' => __('History'),
  'title' => __(':year performance', ['year' => $year]),
  'subtitle' => __('Drill into any month to revisit budgets, transactions, and progress.'),
  'meta' => [
    ['icon' => 'trending-up', 'label' => __('Income: :amount', ['amount' => moneyfmt($totalIncome)])],
    ['icon' => 'trending-down', 'label' => __('Spending: :amount', ['amount' => moneyfmt($totalSpending)])],
  ],
  'insight' => [
    'label' => __('Net result'),
    'value' => moneyfmt($netYear),
    'subline' => __('Average per month: :amount', ['amount' => moneyfmt($netYear / 12)]),
  ],
  'actions' => [
    ['label' => __('Back to years'), 'href' => '/years', 'icon' => 'arrow-left', 'style' => 'primary'],
    ['label' => __('Open current month'), 'href' => '/current-month', 'icon' => 'calendar-range', 'style' => 'muted'],
  ],
  'tabs' => [
    ['label' => __('Months'), 'href' => '#year-months', 'active' => true],
  ],
]);

$positiveMonths = 0;
$negativeMonths = 0;
$bestMonth = null;
$bestValue = null;
$worstMonth = null;
$worstValue = null;

for ($m = 1; $m <= 12; $m++) {
  $row = $byMonth[$m];
  $net = ($row['income'] ?? 0) - ($row['spending'] ?? 0);
  if ($net >= 0) {
    $positiveMonths++;
  } else {
    $negativeMonths++;
  }
  if ($bestValue === null || $net > $bestValue) {
    $bestValue = $net;
    $bestMonth = $m;
  }
  if ($worstValue === null || $net < $worstValue) {
    $worstValue = $net;
    $worstMonth = $m;
  }
}

render_focus_panel([
  'id' => 'year-focus',
  'title' => __('Replay this year’s story'),
  'description' => __('Pinpoint the standout months, learn from the slow ones, and feed those lessons into your plan.'),
  'items' => [
    [
      'icon' => 'sparkles',
      'label' => __('Visit the strongest month'),
      'description' => __('Open the month with the best net to remember what worked.'),
      'href' => $bestMonth ? ('/years/'.$year.'/'.$bestMonth) : '#year-months',
      'state' => $bestMonth ? 'success' : 'info',
      'state_label' => $bestMonth ? __('Best: :month', ['month' => date('M', mktime(0, 0, 0, $bestMonth, 1, $year))]) : __('No data'),
    ],
    [
      'icon' => 'flame',
      'label' => __('Review the toughest month'),
      'description' => __('Check categories and transactions for the month that ran hottest.'),
      'href' => $worstMonth ? ('/years/'.$year.'/'.$worstMonth) : '#year-months',
      'state' => $worstMonth ? 'warning' : 'info',
      'state_label' => $worstMonth ? __('Needs attention: :month', ['month' => date('M', mktime(0, 0, 0, $worstMonth, 1, $year))]) : __('No data'),
    ],
    [
      'icon' => 'list',
      'label' => __('Check category allocations'),
      'description' => __('From the month detail, jump to budgets or transactions to see the “why”.'),
      'href' => '#year-months',
      'state' => 'active',
      'state_label' => __('Drill in'),
    ],
    [
      'icon' => 'share-2',
      'label' => __('Share highlights with your partner'),
      'description' => __('Use the month view exports to recap wins and adjustments together.'),
      'href' => '#year-months',
      'state' => 'info',
      'state_label' => __('Collaborate'),
    ],
  ],
  'side' => [
    'label' => __('Net result'),
    'value' => moneyfmt($netYear),
    'subline' => __('Positive months: :pos · Negative: :neg', ['pos' => $positiveMonths, 'neg' => $negativeMonths]),
    'footnote' => __('Average per month: :amount', ['amount' => moneyfmt($netYear / 12)]),
    'actions' => [
      ['label' => __('Open current year overview'), 'href' => '/years', 'icon' => 'calendar'],
      ['label' => __('Jump to month view'), 'href' => '/current-month#month-summary', 'icon' => 'calendar-range'],
    ],
  ],
  'tips' => [
    __('Apply takeaways from top-performing months to your current cashflow rules.'),
    __('Use command palette shortcuts to move from this history straight into budgeting screens.'),
  ],
]);
?>

<section id="year-months" class="card">
  <h1 class="text-xl font-semibold"><?= $year ?></h1>
  <div class="mt-4 grid sm:grid-cols-3 md:grid-cols-4 gap-3">
    <?php for($m=1;$m<=12;$m++): $r=$byMonth[$m]; $net=$r['income']-$r['spending']; ?>
      <a href="/years/<?= $year ?>/<?= $m ?>" class="panel p-4 hover:shadow block">
        <div class="font-medium"><?= date('M', mktime(0,0,0,$m,1,$year)) ?></div>
        <div class="text-xs text-gray-500 mt-1">Inc: <?= moneyfmt($r['income']) ?> · Sp: <?= moneyfmt($r['spending']) ?></div>
        <div class="text-sm font-medium mt-1 <?= $net>=0?'text-brand-600':'text-red-600' ?>"><?= moneyfmt($net) ?></div>
      </a>
    <?php endfor; ?>
  </div>
</section>