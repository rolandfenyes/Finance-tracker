<?php
require_login(); $u = uid();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../src/fx.php';

$main = fx_user_main($pdo, $u) ?: 'HUF';
$today = date('Y-m-d');
$firstThis = date('Y-m-01');
$lastThis  = date('Y-m-t');

/* ---------------- NET THIS MONTH (incl. basic incomes) ---------------- */
$sumIn = 0.0; $sumOut = 0.0;

// Transactions this month → main
$q = $pdo->prepare("
  SELECT kind, amount, COALESCE(currency, ?) AS currency, occurred_on
  FROM transactions
  WHERE user_id=? AND occurred_on BETWEEN ?::date AND ?::date
");
$q->execute([$main, $u, $firstThis, $lastThis]);
foreach ($q as $r) {
  $amt = fx_convert($pdo, (float)$r['amount'], $r['currency'], $main, $r['occurred_on']);
  if ($r['kind']==='income') $sumIn += $amt; else $sumOut += $amt;
}

// Basic incomes active this month
$y=(int)date('Y'); $m=(int)date('n');
$bi = $pdo->prepare("
  SELECT amount, COALESCE(currency, ?) AS currency
  FROM basic_incomes
  WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)
");
$bi->execute([$main, $u, $lastThis, $firstThis]);
foreach ($bi as $b) {
  $sumIn += fx_convert_basic_income($pdo, (float)$b['amount'], $b['currency'], $main, $y, $m);
}
$netThisMonth = $sumIn - $sumOut;

/* ---------------- LEFTOVER FROM PREVIOUS MONTHS (net to last month) ---------------- */
$lastPrev = date('Y-m-d', strtotime($firstThis.' -1 day'));
$sumInPrev = 0.0; $sumOutPrev = 0.0;

// Transactions before this month
$q = $pdo->prepare("
  SELECT kind, amount, COALESCE(currency, ?) AS currency, occurred_on
  FROM transactions
  WHERE user_id=? AND occurred_on <= ?::date
");
$q->execute([$main, $u, $lastPrev]);
foreach ($q as $r) {
  $amt = fx_convert($pdo, (float)$r['amount'], $r['currency'], $main, $r['occurred_on']);
  if ($r['kind']==='income') $sumInPrev += $amt; else $sumOutPrev += $amt;
}
$leftoverPrev = $sumInPrev - $sumOutPrev;

// (Optional) Add historical basic incomes to leftoverPrev (from their valid_from up to lastPrev)
$minRow = $pdo->prepare("SELECT MIN(valid_from) FROM basic_incomes WHERE user_id=?");
$minRow->execute([$u]); $minStart = $minRow->fetchColumn();
if ($minStart) {
  $startYm = new DateTime(date('Y-m-01', strtotime($minStart)));
  $endYm   = new DateTime(date('Y-m-01', strtotime($lastPrev)));
  // iterate month by month
  while ($startYm <= $endYm) {
    $Y = (int)$startYm->format('Y'); $M = (int)$startYm->format('n');
    $ymFirst = $startYm->format('Y-m-01'); $ymLast = $startYm->format('Y-m-t');
    $biM = $pdo->prepare("
      SELECT amount, COALESCE(currency, ?) AS currency
      FROM basic_incomes
      WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)
    ");
    $biM->execute([$main, $u, $ymLast, $ymFirst]);
    foreach ($biM as $b) {
      $leftoverPrev += fx_convert_basic_income($pdo, (float)$b['amount'], $b['currency'], $main, $Y, $M);
    }
    $startYm->modify('+1 month');
  }
}

/* ---------------- EMERGENCY FUND (main) ---------------- */
$efRow = $pdo->prepare("SELECT total, target_amount, currency FROM emergency_fund WHERE user_id=?");
$efRow->execute([$u]); $ef = $efRow->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'target_amount'=>0,'currency'=>$main];
$efCur = strtoupper($ef['currency'] ?: $main);
$efTotal = (float)($ef['total'] ?? 0);
$efTarget= (float)($ef['target_amount'] ?? 0);
$efTotalMain = ($efCur === $main) ? $efTotal : fx_convert($pdo, $efTotal, $efCur, $main, $today);
$efPct = $efTarget>0 ? round(min(100,max(0,$efTotal/$efTarget*100))) : 0;

/* ---------------- GOALS SUMMARY (current amounts → main) ---------------- */
$goals = $pdo->prepare("SELECT current_amount, COALESCE(currency, ?) AS currency, status FROM goals WHERE user_id=?");
$goals->execute([$main, $u]);
$goalsCurrentMain = 0.0; $goalsTarget = 0.0; $goalsCurrent = 0.0;
$goalsActiveCount=0; $goalsDoneCount=0;

$gs2 = $pdo->prepare("SELECT target_amount, COALESCE(currency, ?) AS currency, status, current_amount FROM goals WHERE user_id=?");
$gs2->execute([$main, $u]);
foreach ($gs2 as $g) {
  $curM = fx_convert($pdo, (float)($g['current_amount'] ?? 0), $g['currency'], $main, $today);
  $tgtM = fx_convert($pdo, (float)($g['target_amount'] ?? 0),  $g['currency'], $main, $today);
  $goalsCurrentMain += $curM;
  $goalsTarget      += $tgtM;
  $goalsCurrent     += (float)($g['current_amount'] ?? 0);
  if (($g['status'] ?? 'active')==='done') $goalsDoneCount++; else $goalsActiveCount++;
}
$goalsPct = $goalsTarget>0 ? round(min(100,max(0,$goalsCurrentMain/$goalsTarget*100))) : 0;

/* ---------------- LOANS SUMMARY ---------------- */
if (!function_exists('months_between')) {
  function months_between(string $start, ?string $end): int {
    if (!$end) return 0;
    $a = new DateTime($start); $b = new DateTime($end);
    $d = $a->diff($b);
    $m = $d->y * 12 + $d->m;
    if ($d->d > 0) $m += 1;
    return max(0, $m);
  }
}
if (!function_exists('loan_monthly_payment')) {
  function loan_monthly_payment(float $principal, float $annualRatePct, int $months): float {
    $r = ($annualRatePct/100.0) / 12.0;
    if ($months <= 0) return 0.0;
    if (abs($r) < 1e-10) return $principal / $months;
    return $principal * ($r * pow(1+$r, $months)) / (pow(1+$r, $months) - 1);
  }
}
if (!function_exists('amortization_to_date_precise')) {
  /**
   * Same rules you used on /loans:
   * - First due = next cycle after start_date, aligned to payment_day (clamped to month length)
   * - payment_total = (P&I + insurance); we carve insurance out when allocating
   * - extra_payment reduces principal each month
   */
  function amortization_to_date_precise(
    float $principal, float $annualRatePct,
    string $start_date, ?string $end_date, ?int $payment_day,
    float $payment_total, float $insurance_monthly = 0.0, float $extra_payment = 0.0,
    ?string $asOfDate = null
  ): array {
    $asOf = new DateTime($asOfDate ?: date('Y-m-d'));
    $start = new DateTime($start_date);
    $maturity = $end_date ? new DateTime($end_date) : null;

    $day = $payment_day ?: (int)$start->format('j');
    $firstMonth = (clone $start);
    $firstMonth->setDate((int)$firstMonth->format('Y'), (int)$firstMonth->format('n'), min($day, (int)$firstMonth->format('t')));
    if ($firstMonth <= $start) {
      $firstMonth->modify('first day of next month');
      $firstMonth->setDate((int)$firstMonth->format('Y'), (int)$firstMonth->format('n'), min($day, (int)$firstMonth->format('t')));
    } else {
      $firstMonth->modify('first day of next month');
      $firstMonth->setDate((int)$firstMonth->format('Y'), (int)$firstMonth->format('n'), min($day, (int)$firstMonth->format('t')));
    }

    $bal = $principal;
    $r = ($annualRatePct / 100.0) / 12.0;
    $ppaid = 0.0; $ipaid = 0.0; $n = 0;
    $pmt_PI = max(0.0, $payment_total - $insurance_monthly);

    $due = $firstMonth;
    while ($bal > 1e-6 && $due <= $asOf) {
      if ($maturity && $due > $maturity) break;

      $interest = $r > 0 ? $bal * $r : 0.0;
      $toward_principal = max(0.0, $pmt_PI + $extra_payment - $interest);
      if ($toward_principal <= 0 && $r > 0) break;

      $principal_part = min($bal, $toward_principal);
      $bal -= $principal_part;

      $ipaid += min($interest, $pmt_PI + $extra_payment);
      $ppaid += $principal_part; $n++;

      $due = (clone $due)->modify('first day of next month');
      $due->setDate((int)$due->format('Y'), (int)$due->format('n'), min($day, (int)$due->format('t')));
    }

    return [
      'principal_paid' => $ppaid,
      'interest_paid'  => $ipaid,
      'balance'        => max(0.0, $bal),
      'months_counted' => $n,
      'first_due'      => $firstMonth->format('Y-m-d'),
      'last_counted'   => $n ? (clone $due)->modify('-1 month')->format('Y-m-d') : null,
    ];
  }
}

/* ---------------- LOANS SUMMARY (accurate) ---------------- */
$loanPrinMain = 0.0; $loanBalMain = 0.0; $loanCount = 0;
$loanConfirmedCount = 0;

$ln = $pdo->prepare("
  SELECT
    l.id, l.principal, COALESCE(l.currency, ?) AS currency, l.interest_rate,
    l.start_date, l.end_date, l.payment_day, l.extra_payment, l.insurance_monthly, l.history_confirmed,
    l.scheduled_payment_id,
    sp.amount   AS sched_amount,
    sp.currency AS sched_currency
  FROM loans l
  LEFT JOIN scheduled_payments sp
    ON sp.id = l.scheduled_payment_id AND sp.user_id = l.user_id
  WHERE l.user_id=?
");
$ln->execute([$main, $u]);

$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(principal_component),0) FROM loan_payments WHERE loan_id = ?");

foreach ($ln as $L) {
  $loanCount++;
  $loanCur   = strtoupper($L['currency'] ?: $main);
  $principal = (float)$L['principal'];
  $ratePct   = (float)$L['interest_rate'];
  $start     = $L['start_date'] ?: date('Y-m-d');
  $end       = $L['end_date'] ?: null;
  $pday      = !empty($L['payment_day']) ? (int)$L['payment_day'] : null;
  $extra     = (float)($L['extra_payment'] ?? 0.0);
  $ins       = (float)($L['insurance_monthly'] ?? 0.0);
  $histConf  = !empty($L['history_confirmed']);

  // Monthly total to use in amortization:
  // If linked schedule exists, use its TOTAL (convert to loan currency if needed).
  // Else derive annuity P&I and add insurance.
  $monthly_total = 0.0;
  if (!empty($L['scheduled_payment_id']) && (float)($L['sched_amount'] ?? 0) > 0) {
    $spAmt = (float)$L['sched_amount'];
    $spCur = strtoupper($L['sched_currency'] ?: $loanCur);
    $monthly_total = ($spCur === $loanCur) ? $spAmt : fx_convert($pdo, $spAmt, $spCur, $loanCur, $today);
  } else {
    $months = months_between($start, $end);
    $PI = $months > 0 ? loan_monthly_payment($principal, $ratePct, $months) : 0.0;
    $monthly_total = $PI + $ins;
  }

  $principalMain = fx_convert($pdo, $principal, $loanCur, $main, $today);

  if ($histConf) {
    $loanConfirmedCount++;
    $am = amortization_to_date_precise(
      $principal, $ratePct, $start, $end, $pday,
      $monthly_total, $ins, $extra, $today
    );
    $balMain = fx_convert($pdo, (float)$am['balance'], $loanCur, $main, $today);
  } else {
    $sumStmt->execute([(int)$L['id']]);
    $actualP = (float)$sumStmt->fetchColumn();
    $balMain = fx_convert($pdo, max(0.0, $principal - $actualP), $loanCur, $main, $today);
  }

  $loanPrinMain += $principalMain;
  $loanBalMain  += max(0.0, $balMain);
}

$loanPaidMain = max(0.0, $loanPrinMain - $loanBalMain);
$loansPct     = $loanPrinMain > 0 ? round(min(100, max(0, $loanPaidMain / $loanPrinMain * 100))) : 0;


/* ---------------- CURRENT MONTH STATS (top categories) ---------------- */
$cat = $pdo->prepare("
  SELECT COALESCE(c.label,'Uncategorized') AS label, t.amount, COALESCE(t.currency, ?) AS currency, t.occurred_on
  FROM transactions t
  LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
  WHERE t.user_id=? AND t.kind='spending' AND t.occurred_on BETWEEN ?::date AND ?::date
");
$cat->execute([$main, $u, $firstThis, $lastThis]);
$byCat = [];
foreach ($cat as $r) {
  $amt = fx_convert($pdo, (float)$r['amount'], $r['currency'], $main, $r['occurred_on']);
  $byCat[$r['label']] = ($byCat[$r['label']] ?? 0) + $amt;
}
arsort($byCat);
$topCats = array_slice($byCat, 0, 6, true);

/* ---------------- BABY STEPS ---------------- */
$steps = [
  1=>'Save $1,000 starter emergency fund',
  2=>'Debt snowball (all non-mortgage debt)',
  3=>'3–6 months of expenses in savings',
  4=>'Invest 15% of household income for retirement',
  5=>'College funding for children',
  6=>'Pay off home early',
  7=>'Build wealth and give',
];
$bs = $pdo->prepare('SELECT step,status FROM baby_steps WHERE user_id=? ORDER BY step');
$bs->execute([$u]); $statuses = [];
foreach($bs as $r){ $statuses[(int)$r['step']] = $r['status']; }
$doneCount = count(array_filter($statuses, fn($s)=>$s==='done'));
$babyPct   = round($doneCount / count($steps) * 100);

/* ---------------- TOTAL NET LIQUID WORTH ---------------- */
$totalNetLiquid = $efTotalMain + $goalsCurrentMain + $netThisMonth + $leftoverPrev;

require_once __DIR__.'/layout/page_header.php';
require_once __DIR__.'/layout/focus_panel.php';

$nextSteps = [];

if ($efTarget > 0 && $efTotal < $efTarget) {
  $nextSteps[] = [
    'icon' => 'piggy-bank',
    'title' => __('Boost your emergency fund'),
    'body' => __('You are :percent% of the way to your safety net.', ['percent' => $efPct]),
    'cta_label' => __('Log a deposit'),
    'cta_href' => '/emergency#ef-contribute',
  ];
}

if ($netThisMonth < -0.01) {
  $nextSteps[] = [
    'icon' => 'trending-down',
    'title' => __('Review this month'),
    'body' => __('Spending has exceeded income by :amount so far.', ['amount' => moneyfmt(abs($netThisMonth), $main)]),
    'cta_label' => __('Open month analysis'),
    'cta_href' => '/current-month#cashflow-guidance',
  ];
} elseif ($netThisMonth > 0.01) {
  $nextSteps[] = [
    'icon' => 'thumbs-up',
    'title' => __('Great job staying ahead'),
    'body' => __('You still have :amount unassigned this month. Point it to a goal before it disappears.', ['amount' => moneyfmt($netThisMonth, $main)]),
    'cta_label' => __('Allocate to a goal'),
    'cta_href' => '/goals#create-goal',
  ];
} else {
  $nextSteps[] = [
    'icon' => 'scale',
    'title' => __('You are perfectly balanced'),
    'body' => __('Income and spending are tied. Keep an eye on new transactions so you stay on target.'),
    'cta_label' => __('Monitor transactions'),
    'cta_href' => '/current-month#transaction-list',
  ];
}

if ($loanBalMain > 0) {
  $nextSteps[] = [
    'icon' => 'badge-check',
    'title' => __('Record your latest loan payment'),
    'body' => __('You have :amount remaining across active loans. Logging payments keeps balances precise.', ['amount' => moneyfmt($loanBalMain, $main)]),
    'cta_label' => __('Update loans'),
    'cta_href' => '/loans#loan-progress',
  ];
}

if (count($nextSteps) > 3) {
  $nextSteps = array_slice($nextSteps, 0, 3);
}

render_page_header([
  'kicker' => __('Overview'),
  'title' => __('Your money mission control'),
  'subtitle' => __('Stay oriented with liquidity, budgets, and obligations. Every card points to the next best move.'),
  'meta' => [
    ['icon' => 'calendar', 'label' => __('Current month: :month', ['month' => format_month_year()])],
    ['icon' => 'wallet', 'label' => __('Main currency: :code', ['code' => $main])],
  ],
  'insight' => [
    'label' => __('Net liquid worth'),
    'value' => moneyfmt($totalNetLiquid, $main),
    'subline' => __('Goals, emergency fund, and leftover income combined.'),
  ],
  'actions' => [
    ['label' => __('Add transaction'), 'href' => '/current-month#quick-add', 'icon' => 'plus', 'style' => 'primary'],
    ['label' => __('Create a goal'), 'href' => '/goals#create-goal', 'icon' => 'target', 'style' => 'muted'],
    ['label' => __('Review budgets'), 'href' => '/settings/cashflow', 'icon' => 'sliders-horizontal', 'style' => 'link'],
  ],
]);

$hasMonthActivity = ($sumIn > 0.0 || $sumOut > 0.0);
$cashflowState = 'success';
$cashflowLabel = __('Balanced');
if ($netThisMonth > 0.01) {
  $cashflowState = 'active';
  $cashflowLabel = __('Money to assign');
} elseif ($netThisMonth < -0.01) {
  $cashflowState = 'warning';
  $cashflowLabel = __('Overspending');
}

$efState = 'info';
$efStateLabel = __('Set a target');
if ($efTarget > 0) {
  if ($efPct >= 100) {
    $efState = 'success';
    $efStateLabel = __('Target reached');
  } elseif ($efPct >= 80) {
    $efState = 'active';
    $efStateLabel = __('Almost there');
  } else {
    $efState = 'warning';
    $efStateLabel = __('Needs attention');
  }
}

$loanState = 'info';
$loanStateLabel = __('Track debts');
if ($loanCount > 0) {
  if ($loanBalMain <= 1) {
    $loanState = 'success';
    $loanStateLabel = __('Paid off');
  } elseif ($loanConfirmedCount === $loanCount) {
    $loanState = 'active';
    $loanStateLabel = __('History confirmed');
  } else {
    $loanState = 'warning';
    $loanStateLabel = __('Confirm history');
  }
}

render_focus_panel([
  'id' => 'dashboard-focus',
  'title' => __('Stay on course today'),
  'description' => __('Move through these checkpoints to keep cashflow, savings, and debt progress aligned.'),
  'items' => [
    [
      'icon' => 'sparkles',
      'label' => __('Capture today’s money moves'),
      'description' => __('Log any new income or spending so the current month snapshot stays accurate.'),
      'href' => '/current-month#quick-add',
      'state' => $hasMonthActivity ? 'success' : 'warning',
      'state_label' => $hasMonthActivity ? __('Updated') : __('Needs log'),
      'meta' => __('Current month'),
    ],
    [
      'icon' => 'scale',
      'label' => __('Balance this month’s plan'),
      'description' => __('Compare net income vs. spending and redirect extra cash toward a mission.'),
      'href' => '/current-month#month-summary',
      'state' => $cashflowState,
      'state_label' => $cashflowLabel,
      'meta' => __('Net: :amount', ['amount' => moneyfmt($netThisMonth, $main)]),
    ],
    [
      'icon' => 'shield',
      'label' => __('Boost your safety net'),
      'description' => __('Add a deposit or adjust the target so your emergency fund keeps pace with real life.'),
      'href' => '/emergency#ef-contribute',
      'state' => $efState,
      'state_label' => $efStateLabel,
      'progress' => $efTarget > 0 ? $efPct : null,
      'progress_label' => $efTarget > 0 ? __(':percent% funded', ['percent' => $efPct]) : '',
      'meta' => __('Target: :amount', ['amount' => moneyfmt($efTarget, $efCur)]),
    ],
    [
      'icon' => 'badge-check',
      'label' => __('Keep loan tracking precise'),
      'description' => $loanCount > 0
        ? __('Confirm amortization history or record the latest payment so balances stay trustworthy.')
        : __('Add every active loan to see your full payoff picture in one place.'),
      'href' => $loanCount > 0 ? '/loans#loan-progress' : '/loans#create-loan',
      'state' => $loanState,
      'state_label' => $loanStateLabel,
      'meta' => $loanCount > 0 ? __('Loans tracked: :count', ['count' => $loanCount]) : __('No loans yet'),
    ],
  ],
  'side' => [
    'label' => __('Net liquid worth'),
    'value' => moneyfmt($totalNetLiquid, $main),
    'subline' => __('Emergency fund, goal balances, and cashflow surplus combined.'),
    'footnote' => __('Figures update instantly when you capture transactions or goal contributions.'),
    'actions' => [
      ['label' => __('Open month analysis'), 'href' => '/current-month#cashflow-guidance', 'icon' => 'line-chart'],
      ['label' => __('Visit goals workspace'), 'href' => '/goals#goal-list', 'icon' => 'target'],
    ],
  ],
  'tips' => [
    __('Use the workflow strip in the header to move between Orient, Plan, Protect, and Review.'),
    __('Anchor links in each step drop you directly into the right section of every page.'),
  ],
]);
?>

<?php if ($nextSteps): ?>
  <section id="next-steps" class="grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
      <div class="flex items-center justify-between gap-3">
        <div>
          <div class="card-kicker"><?= __('Momentum') ?></div>
          <h2 class="card-title mt-1"><?= __('Next best steps') ?></h2>
        </div>
        <span class="chip"><?= __('Workflow') ?></span>
      </div>
      <div class="mt-5 grid gap-4 md:grid-cols-3">
        <?php foreach ($nextSteps as $step): ?>
          <article class="panel h-full space-y-3">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
              <i data-lucide="<?= htmlspecialchars($step['icon'], ENT_QUOTES) ?>" class="h-4 w-4"></i>
              <span><?= htmlspecialchars($step['title']) ?></span>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-300"><?= htmlspecialchars($step['body']) ?></p>
            <a href="<?= htmlspecialchars($step['cta_href'], ENT_QUOTES) ?>"
               class="inline-flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-200 dark:hover:text-brand-100">
              <?= htmlspecialchars($step['cta_label']) ?>
              <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card" id="momentum-glance">
      <div class="card-kicker"><?= __('Highlights') ?></div>
      <h3 class="card-title mt-1"><?= __('Quick stats') ?></h3>
      <ul class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
        <li class="flex items-center justify-between">
          <span><?= __('Goals active') ?></span>
          <span class="font-semibold text-slate-900 dark:text-white"><?= (int)$goalsActiveCount ?></span>
        </li>
        <li class="flex items-center justify-between">
          <span><?= __('Loans tracked') ?></span>
          <span class="font-semibold text-slate-900 dark:text-white"><?= (int)$loanCount ?></span>
        </li>
        <li class="flex items-center justify-between">
          <span><?= __('Emergency fund progress') ?></span>
          <span class="font-semibold text-brand-600 dark:text-brand-200"><?= $efTarget > 0 ? ($efPct.'%') : __('Not set') ?></span>
        </li>
      </ul>
    </div>
  </section>
<?php endif; ?>

<section id="snapshot" class="grid gap-6 lg:grid-cols-4">
  <!-- Total net liquid -->
  <div class="card lg:col-span-2">
    <div class="card-kicker"><?= __('Overview') ?></div>
    <h2 class="card-title mt-1"><?= __('Total Net Liquid Worth') ?></h2>
    <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($totalNetLiquid, $main) ?></p>
    <div class="mt-3 grid gap-2 text-[11px] text-slate-600 sm:grid-cols-2 dark:text-slate-300">
      <div class="chip"><?= __('EF: :amount', ['amount' => moneyfmt($efTotalMain, $main)]) ?></div>
      <div class="chip"><?= __('Goals (now): :amount', ['amount' => moneyfmt($goalsCurrentMain, $main)]) ?></div>
      <div class="chip"><?= __('This month: :amount', ['amount' => moneyfmt($netThisMonth, $main)]) ?></div>
      <div class="chip"><?= __('Leftover prev.: :amount', ['amount' => moneyfmt($leftoverPrev, $main)]) ?></div>
    </div>
  </div>

  <!-- Goals summary -->
  <div class="card">
    <div class="card-kicker"><?= __('Goals') ?></div>
    <h3 class="card-title mt-1"><?= __('Progress') ?></h3>
    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-brand-100/60 dark:bg-slate-800/60">
      <div class="h-2 rounded-full bg-brand-500" style="width: <?= $goalsPct ?>%"></div>
    </div>
    <p class="card-subtle mt-2"><?= __(':percent% across active goals', ['percent' => $goalsPct]) ?></p>
    <p class="card-subtle mt-1"><?= __(':active active · :done done', ['active' => (int)$goalsActiveCount, 'done' => (int)$goalsDoneCount]) ?></p>
  </div>

  <!-- EF summary -->
  <div class="card">
    <div class="card-kicker"><?= __('Safety') ?></div>
    <h3 class="card-title mt-1"><?= __('Emergency Fund') ?></h3>
    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-brand-100/60 dark:bg-slate-800/60">
      <div class="h-2 rounded-full bg-brand-600" style="width: <?= $efPct ?>%"></div>
    </div>
    <p class="card-subtle mt-2"><?= __(':percent% of target', ['percent' => $efPct]) ?><?= $efTarget>0 ? ' ('.moneyfmt($efTarget, $efCur).')':'' ?></p>
    <p class="card-subtle mt-1"><?= __('Current: :amount', ['amount' => moneyfmt($efTotal, $efCur)]) ?><?= strtoupper($efCur)!==strtoupper($main)?' · ≈ '.moneyfmt($efTotalMain,$main):'' ?></p>
  </div>
</section>

<section id="monthly-performance" class="mt-8 grid gap-6 lg:grid-cols-3">
  <!-- Current month stats -->
  <div class="card lg:col-span-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Current Month') ?></h3>
      <span class="chip"><?= format_month_year() ?></span>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-3">
      <div class="tile">
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Income') ?></div>
        <div class="mt-2 text-xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($sumIn, $main) ?></div>
      </div>
      <div class="tile">
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Spending') ?></div>
        <div class="mt-2 text-xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($sumOut, $main) ?></div>
      </div>
      <div class="tile">
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Net') ?></div>
        <div class="mt-2 text-xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($netThisMonth, $main) ?></div>
      </div>
    </div>
    <?php if ($topCats): ?>
      <div class="mt-4">
        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Top spending categories') ?></div>
        <ul class="grid gap-2 text-sm sm:grid-cols-2">
          <?php foreach($topCats as $lbl=>$amt): ?>
            <li class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/70 px-3 py-2 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/50 dark:text-slate-200">
              <span class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($lbl) ?></span>
              <span class="font-semibold text-brand-700 dark:text-brand-200"><?= moneyfmt($amt, $main) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <!-- Loans summary -->
  <div class="card">
    <div class="card-kicker"><?= __('Loans') ?></div>
    <h3 class="card-title mt-1"><?= __('Progress') ?></h3>
    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-brand-100/60 dark:bg-slate-800/60">
      <div class="h-2 rounded-full bg-brand-500" style="width: <?= $loansPct ?>%"></div>
    </div>
    <p class="card-subtle mt-2"><?= __(':percent% paid', ['percent' => $loansPct]) ?></p>
    <p class="card-subtle mt-1">
      <?= __('Paid: :amount', ['amount' => moneyfmt($loanPaidMain, $main)]) ?>
      ·
      <?= __('Balance: :amount', ['amount' => moneyfmt($loanBalMain, $main)]) ?>
    </p>
  </div>

</section>

<!-- <section class="mt-6 card">
  <h3 class="font-semibold mb-3">Dave Ramsey Baby Steps</h3>
  <div class="mb-3 w-full bg-brand-100/60 h-2 rounded">
    <div class="h-2 rounded bg-sky-600" style="width: <?= $babyPct ?>%"></div>
  </div>
  <ol class="space-y-2 text-sm">
    <?php foreach($steps as $i=>$label): $st=$statuses[$i] ?? 'in_progress'; ?>
  <li class="flex items-center justify-between p-3 rounded-lg border <?= $st==='done'?'border-brand-300 bg-brand-50/80':'border-gray-200'; ?>">
        <span class="font-medium">Step <?= $i ?>:</span>
        <span class="flex-1 ml-2"><?= htmlspecialchars($label) ?></span>
        <span class="text-xs px-2 py-1 rounded-full <?= $st==='done'?'bg-brand-500/20 text-brand-700':'bg-gray-200'; ?>">
          <?= htmlspecialchars($st) ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ol>
</section> -->
