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
$loanPrinMain = 0.0; $loanBalMain = 0.0;

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

$stockPositions = stocks_positions_summary($pdo, $u, $main, $today);
$stockCostBasis = array_sum(array_map(fn($p) => $p['cost_main'], $stockPositions));
$stockCurrencyRates = [];
foreach ($stockPositions as $sp) {
  $stockCurrencyRates[$sp['currency']] = $sp['rate_to_main'];
}
$stockPositionsPayload = json_encode($stockPositions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$stockCurrencyRatesPayload = json_encode($stockCurrencyRates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<section class="grid gap-6 lg:grid-cols-4">
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

<section class="mt-8 grid gap-6 lg:grid-cols-3">
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

  <!-- Stocks snapshot -->
  <div class="card">
    <div class="card-kicker"><?= __('Investments') ?></div>
    <h3 class="card-title mt-1"><?= __('Stocks Snapshot') ?></h3>
    <?php if ($stockPositions): ?>
      <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white" data-dashboard-stocks-value><?= __('Loading…') ?></p>
      <p class="card-subtle mt-2 text-xs">
        <?= __('Cost basis: :amount', ['amount' => moneyfmt($stockCostBasis, $main)]) ?>
      </p>
      <p class="card-subtle mt-2 text-xs" data-dashboard-stocks-total><?= __('Loading…') ?></p>
      <p class="card-subtle mt-1 text-xs" data-dashboard-stocks-change><?= __('Loading…') ?></p>
      <span class="chip mt-2 inline-flex" data-dashboard-stocks-loading><?= __('Loading quotes…') ?></span>
      <ul class="mt-4 space-y-2 text-xs" data-dashboard-stocks-holdings></ul>
      <p class="mt-3 hidden text-xs text-rose-500" data-dashboard-stocks-error></p>
      <a href="/stocks" class="mt-4 inline-flex items-center text-sm font-semibold text-brand-700 hover:text-brand-600 dark:text-brand-200">
        <?= __('View portfolio') ?>
        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </a>
    <?php else: ?>
      <p class="mt-3 text-sm text-slate-500 dark:text-slate-300">
        <?= __('Log your stock trades to see live portfolio metrics right on the dashboard.') ?>
      </p>
      <a href="/stocks" class="mt-4 inline-flex items-center text-sm font-semibold text-brand-700 hover:text-brand-600 dark:text-brand-200">
        <?= __('Start tracking') ?>
        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </a>
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

<?php if ($stockPositions): ?>
  <script>
    (function () {
      const dataset = {
        positions: <?= $stockPositionsPayload ?>,
        baseCurrency: <?= json_encode($main, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        currencyRates: <?= $stockCurrencyRatesPayload ?>,
        costBasis: <?= json_encode($stockCostBasis, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      };

      const boot = () => {
        const toolkit = window.MyMoneyMapStocksToolkit || {};
        if (!toolkit || typeof toolkit.fetchQuotes !== 'function') {
          return;
        }

        const valueEl = document.querySelector('[data-dashboard-stocks-value]');
        const totalEl = document.querySelector('[data-dashboard-stocks-total]');
        const changeEl = document.querySelector('[data-dashboard-stocks-change]');
        const listEl = document.querySelector('[data-dashboard-stocks-holdings]');
        const loadingBadge = document.querySelector('[data-dashboard-stocks-loading]');
        const errorEl = document.querySelector('[data-dashboard-stocks-error]');
        if (!valueEl || !totalEl || !changeEl || !listEl) {
          return;
        }

        const setLoading = (isLoading) => {
          if (!loadingBadge) return;
          loadingBadge.classList.toggle('hidden', !isLoading);
        };

        const positions = Array.isArray(dataset.positions) ? dataset.positions : [];
        const currencyRates = dataset.currencyRates || {};
        const baseCurrency = dataset.baseCurrency || 'USD';
        const costBasis = Number(dataset.costBasis || 0);

        valueEl.textContent = toolkit.formatCurrency(costBasis, baseCurrency);
        totalEl.textContent = "<?= __('Loading…') ?>";
        changeEl.textContent = "<?= __('Loading…') ?>";
        listEl.innerHTML = '';

        if (errorEl) {
          errorEl.classList.add('hidden');
          errorEl.textContent = '';
        }

        setLoading(true);

        if (!positions.length) {
          setLoading(false);
          totalEl.textContent = '—';
          changeEl.textContent = '—';
          return;
        }

        const rateFor = (currency, fallback) => {
          const key = currency ? String(currency).toUpperCase() : '';
          if (key && typeof currencyRates[key] !== 'undefined') {
            return Number(currencyRates[key]);
          }
          if (typeof fallback === 'number' && !Number.isNaN(fallback)) {
            return fallback;
          }
          return 1;
        };

        toolkit.fetchQuotes(positions.map((p) => p.symbol)).then((quotes) => {
          const meta = quotes && quotes.__meta ? quotes.__meta : { stale: false, messages: [] };
          if (errorEl) {
            if (meta && Array.isArray(meta.messages) && meta.messages.length) {
              errorEl.textContent = meta.messages[0];
              errorEl.classList.remove('hidden');
            } else {
              errorEl.classList.add('hidden');
              errorEl.textContent = '';
            }
          }

          const holdings = [];
          let totalValue = 0;
          let totalDay = 0;

          positions.forEach((pos) => {
            const symbol = String(pos.symbol || '').toUpperCase();
            const qty = Number(pos.qty || 0);
            const cost = Number(pos.cost_main || 0);
            const quote = quotes && quotes[symbol] ? quotes[symbol] : null;
            const currency = quote && (quote.currency || quote.financialCurrency)
              ? (quote.currency || quote.financialCurrency)
              : (pos.currency || baseCurrency);
            const rate = rateFor(currency, Number(pos.rate_to_main || 1));
            const price = quote && typeof quote.regularMarketPrice === 'number' ? quote.regularMarketPrice : null;
            const change = quote && typeof quote.regularMarketChange === 'number' ? quote.regularMarketChange : 0;
            const value = price !== null ? price * qty * rate : cost;
            const day = price !== null ? change * qty * rate : 0;
            totalValue += value;
            totalDay += day;
            holdings.push({
              symbol,
              name: quote && (quote.shortName || quote.longName) ? (quote.shortName || quote.longName) : symbol,
              value,
              allocation: 0,
              day,
            });
          });

          const totalGain = totalValue - costBasis;
          const previousValue = totalValue - totalDay;
          const dayPct = previousValue > 0 ? (totalDay / previousValue) * 100 : 0;
          const totalPct = costBasis > 0 ? (totalGain / costBasis) * 100 : 0;

          valueEl.textContent = toolkit.formatCurrency(totalValue, baseCurrency);
          totalEl.textContent = "<?= __('Unrealized P/L:') ?> " + toolkit.formatCurrency(totalGain, baseCurrency) + ' (' + toolkit.formatPercent(totalPct) + ')';
          changeEl.textContent = "<?= __('Today:') ?> " + toolkit.formatCurrency(totalDay, baseCurrency) + ' (' + toolkit.formatPercent(dayPct) + ')';

          totalEl.classList.toggle('text-emerald-600', totalGain > 0);
          totalEl.classList.toggle('text-rose-600', totalGain < 0);
          changeEl.classList.toggle('text-emerald-600', totalDay > 0);
          changeEl.classList.toggle('text-rose-600', totalDay < 0);

          holdings.sort((a, b) => b.value - a.value);
          holdings.forEach((h) => {
            h.allocation = totalValue > 0 ? (h.value / totalValue) * 100 : 0;
          });

          listEl.innerHTML = '';
          holdings.slice(0, 3).forEach((h) => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between rounded-2xl border border-white/60 px-3 py-2 shadow-sm backdrop-blur dark:border-slate-800/60';
            const left = document.createElement('div');
            left.className = 'flex flex-col';
            const sym = document.createElement('span');
            sym.className = 'font-semibold text-slate-900 dark:text-white';
            sym.textContent = h.symbol;
            const name = document.createElement('span');
            name.className = 'text-[11px] text-slate-500 dark:text-slate-300';
            name.textContent = h.name;
            left.appendChild(sym);
            left.appendChild(name);

            const right = document.createElement('div');
            right.className = 'text-right';
            const val = document.createElement('div');
            val.className = 'text-sm font-semibold text-brand-700 dark:text-brand-200';
            val.textContent = toolkit.formatCurrency(h.value, baseCurrency);
            const pct = document.createElement('div');
            pct.className = 'text-[11px] text-slate-500 dark:text-slate-300';
            pct.textContent = toolkit.formatPercent(h.allocation);
            right.appendChild(val);
            right.appendChild(pct);

            li.appendChild(left);
            li.appendChild(right);
            listEl.appendChild(li);
          });
        }).catch((err) => {
          console.error('Dashboard stocks widget error', err);
          if (errorEl) {
            errorEl.textContent = "<?= __('Unable to fetch live quotes right now. Please try again later.') ?>";
            errorEl.classList.remove('hidden');
          }
          totalEl.textContent = '—';
          changeEl.textContent = '—';
        }).finally(() => {
          setLoading(false);
        });
      };

      if (window.MyMoneyMapStocksToolkit && typeof window.MyMoneyMapStocksToolkit.fetchQuotes === 'function') {
        boot();
      } else {
        window.addEventListener('stocks-toolkit-ready', boot, { once: true });
      }
    })();
  </script>
<?php endif; ?>
