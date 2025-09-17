<?php require_login(); $u=uid(); ?>
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Net This Month</h2>
    <?php require_login(); $u=uid();
      $first = date('Y-m-01'); $last = date('Y-m-t');

      // tx sums
      $stmt=$pdo->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN kind='income'   THEN amount ELSE 0 END),0) AS income_tx,
          COALESCE(SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END),0) AS spending_tx
        FROM transactions
        WHERE user_id=? AND occurred_on BETWEEN ?::date AND ?::date
      ");
      $stmt->execute([$u,$first,$last]);
      $row=$stmt->fetch();
      $incomeTx=(float)$row['income_tx']; $spendingTx=(float)$row['spending_tx'];

      // basic incomes active this month
      $bi=$pdo->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM basic_incomes
        WHERE user_id=? AND valid_from <= ?::date AND (valid_to IS NULL OR valid_to >= ?::date)
      ");
      $bi->execute([$u,$last,$first]); $basic=(float)$bi->fetchColumn();

      $net = ($incomeTx + $basic) - $spendingTx;
    ?>
    <p class="text-2xl mt-2 font-semibold"><?= moneyfmt($net) ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Goals Progress</h2>
    <?php $g=$pdo->prepare('SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status=\'active\'' ); $g->execute([$u]); $g=$g->fetch(); $pc = $g && $g['t']>0 ? round($g['c']/$g['t']*100) : 0; ?>
    <div class="mt-3 w-full bg-gray-100 h-2 rounded">
      <div class="h-2 rounded bg-accent" style="width: <?= $pc ?>%"></div>
    </div>
    <p class="text-sm mt-2"><?= $pc ?>% of active goals</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Emergency Fund</h2>
    <?php $e=$pdo->prepare('SELECT total,target_amount FROM emergency_fund WHERE user_id=?'); $e->execute([$u]); $e=$e->fetch(); $pct = $e && $e['target_amount']>0? round($e['total']/$e['target_amount']*100):0; ?>
    <p class="text-2xl mt-2 font-semibold"><?= $e? moneyfmt($e['total']) : '—' ?></p>
    <p class="text-sm text-gray-500"><?= $pct ?>% of target</p>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Last 30 Days — Daily Flow</h3>
    <?php
      $q=$pdo->prepare("SELECT occurred_on::date d, SUM(CASE WHEN kind='income' THEN amount ELSE -amount END) v
                        FROM transactions WHERE user_id=? AND occurred_on >= CURRENT_DATE-INTERVAL '30 days'
                        GROUP BY d ORDER BY d");
      $q->execute([$u]); $labels=[]; $data=[]; foreach($q as $r){$labels[]=$r['d']; $data[]=(float)$r['v'];}
    ?>
    <canvas id="flow30" class="w-full h-64"></canvas>
    <script>renderLineChart('flow30', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Spending by Category (This Month)</h3>
    <?php
      $q=$pdo->prepare("SELECT COALESCE(c.label,'Uncategorized') lb, SUM(t.amount) s
                        FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
                        WHERE t.user_id=? AND t.kind='spending' AND date_trunc('month',t.occurred_on)=date_trunc('month',CURRENT_DATE)
                        GROUP BY lb ORDER BY s DESC");
      $q->execute([$u]); $labels=[]; $data=[]; foreach($q as $r){$labels[]=$r['lb']; $data[]=(float)$r['s'];}
    ?>
    <canvas id="spendcat" class="w-full h-64"></canvas>
    <script>renderDoughnut('spendcat', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <h3 class="font-semibold mb-3">Dave Ramsey Baby Steps</h3>
  <ol class="space-y-2 text-sm">
    <?php
      $steps = [
        1=>'Save $1,000 starter emergency fund',
        2=>'Debt snowball (all non‑mortgage debt)',
        3=>'3–6 months of expenses in savings',
        4=>'Invest 15% of household income for retirement',
        5=>'College funding for children',
        6=>'Pay off home early',
        7=>'Build wealth and give',
      ];
      $bs=$pdo->prepare('SELECT step,status FROM baby_steps WHERE user_id=? ORDER BY step');
      $bs->execute([$u]); $statuses=[]; foreach($bs as $r){$statuses[$r['step']]=$r['status'];}
      foreach($steps as $i=>$label): $st=$statuses[$i] ?? 'in_progress'; ?>
        <li class="flex items-center justify-between p-3 rounded-lg border <?php echo $st==='done'?'border-emerald-300 bg-emerald-50':'border-gray-200'; ?>">
          <span class="font-medium">Step <?= $i ?>:</span>
          <span class="flex-1 ml-2"><?= htmlspecialchars($label) ?></span>
          <span class="text-xs px-2 py-1 rounded-full <?php echo $st==='done'?'bg-emerald-200':'bg-gray-200'; ?>"><?= htmlspecialchars($st) ?></span>
        </li>
    <?php endforeach; ?>
  </ol>
</section>