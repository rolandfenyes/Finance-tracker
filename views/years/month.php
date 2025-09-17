<?php $ym = sprintf('%04d-%02d', $year, $month); ?>
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">This Month (<?= $y ?>‑<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>)</h2>
  <p class="mt-2 text-sm text-gray-500">Income (main <?= htmlspecialchars($main) ?>): <strong><?= moneyfmt($sumIn_main,$main) ?></strong></p>
  <p class="mt-1 text-sm text-gray-500">Spending (main <?= htmlspecialchars($main) ?>): <strong><?= moneyfmt($sumOut_main,$main) ?></strong></p>
  <p class="mt-1 text-sm">Net (main): <strong><?= moneyfmt($sumIn_main - $sumOut_main,$main) ?></strong></p>
  <p class="mt-3 text-xs text-gray-400">Native totals (sum of entered amounts): Inc <?= moneyfmt($sumIn_native) ?> · Sp <?= moneyfmt($sumOut_native) ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="font-semibold mb-3">Quick Add</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/months/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="y" value="<?= $year ?>" />
      <input type="hidden" name="m" value="<?= $month ?>" />
      <select name="kind" class="rounded-xl border-gray-300 sm:col-span-1">
        <option value="income">Income</option>
        <option value="spending">Spending</option>
      </select>
      <select name="category_id" class="rounded-xl border-gray-300 sm:col-span-2">
        <option value="">— Category —</option>
        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?php echo ucfirst($c['kind']).' · '.htmlspecialchars($c['label']); ?></option><?php endforeach; ?>
      </select>
      <input name="amount" type="number" step="0.01" placeholder="Amount" class="rounded-xl border-gray-300 sm:col-span-1" required />
      <input name="occurred_on" type="date" value="<?= $ym ?>-<?= min( cal_days_in_month(CAL_GREGORIAN,$month,$year), (int)date('d') ) ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
      <input name="note" placeholder="Note" class="rounded-xl border-gray-300 sm:col-span-1" />
      <button class="bg-gray-900 text-white rounded-xl px-4">Add</button>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Spending by Category</h3>
    <?php
      $sp=$pdo->prepare("SELECT COALESCE(c.label,'Uncategorized') lb, SUM(t.amount) s
        FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
        WHERE t.user_id=? AND t.kind='spending' AND EXTRACT(YEAR FROM t.occurred_on)=? AND EXTRACT(MONTH FROM t.occurred_on)=?
        GROUP BY lb ORDER BY s DESC");
      $sp->execute([uid(),$year,$month]); $labels=[]; $data=[]; foreach($sp as $r){$labels[]=$r['lb']; $data[]=(float)$r['s'];}
    ?>
    <canvas id="spendcat-month" class="w-full h-64"></canvas>
    <script>renderDoughnut('spendcat-month', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Daily Flow</h3>
    <?php
      $q=$pdo->prepare("SELECT occurred_on::date d, SUM(CASE WHEN kind='income' THEN amount ELSE -amount END) v
                        FROM transactions WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? AND EXTRACT(MONTH FROM occurred_on)=?
                        GROUP BY d ORDER BY d");
      $q->execute([uid(),$year,$month]); $labels=[]; $data=[]; foreach($q as $r){$labels[]=$r['d']; $data[]=(float)$r['v'];}
    ?>
    <canvas id="flow-month" class="w-full h-64"></canvas>
    <script>renderLineChart('flow-month', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h3 class="font-semibold mb-3">Transactions</h3>
  <table class="min-w-full text-sm">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3">Date</th>
        <th class="py-2 pr-3">Kind</th>
        <th class="py-2 pr-3">Category</th>
        <th class="py-2 pr-3">Amount</th>
        <th class="py-2 pr-3">Note</th>
        <th class="py-2 pr-3">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($tx as $row): ?>
        <tr class="border-b hover:bg-gray-50">
          <td class="py-2 pr-3"><?= htmlspecialchars($row['occurred_on']) ?></td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($row['kind']) ?></td>
          <td class="py-2 pr-3"><?= htmlspecialchars($row['cat_label'] ?? '—') ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($row['amount']) ?></td>
          <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($row['note'] ?? '') ?></td>
          <td class="py-2 pr-3">
            <details>
              <summary class="cursor-pointer text-accent">Edit</summary>
              <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/months/tx/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="y" value="<?= $year ?>" />
                <input type="hidden" name="m" value="<?= $month ?>" />
                <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                <select name="kind" class="rounded-xl border-gray-300 sm:col-span-1">
                  <option <?=$row['kind']==='income'?'selected':''?> value="income">Income</option>
                  <option <?=$row['kind']==='spending'?'selected':''?> value="spending">Spending</option>
                </select>
                <select name="category_id" class="rounded-xl border-gray-300 sm:col-span-2">
                  <option value="">— Category —</option>
                  <?php foreach($cats as $c): ?><option <?=$row['category_id']==$c['id']?'selected':''?> value="<?= $c['id'] ?>"><?php echo ucfirst($c['kind']).' · '.htmlspecialchars($c['label']); ?></option><?php endforeach; ?>
                </select>
                <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="rounded-xl border-gray-300 sm:col-span-1" required />
                <input name="occurred_on" type="date" value="<?= $row['occurred_on'] ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
                <input name="note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
                <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
              </form>
              <form class="mt-2" method="post" action="/months/tx/delete" onsubmit="return confirm('Delete transaction?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="y" value="<?= $year ?>" />
                <input type="hidden" name="m" value="<?= $month ?>" />
                <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                <button class="text-red-600">Remove</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="mt-6 grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h3 class="font-semibold">Goals snapshot</h3>
    <?php $pc = ($g && $g['t']>0) ? round($g['c']/$g['t']*100) : 0; ?>
    <div class="mt-2 w-full bg-gray-100 h-2 rounded"><div class="h-2 rounded bg-accent" style="width: <?= $pc ?>%"></div></div>
    <p class="text-xs text-gray-500 mt-1"><?= $pc ?>% of active goals</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h3 class="font-semibold">Emergency fund</h3>
    <?php $epc = ($e && $e['target_amount']>0)? round($e['total']/$e['target_amount']*100):0; ?>
    <p class="text-sm">Total: <?= $e? moneyfmt($e['total'],$e['currency']) : '—' ?></p>
    <div class="mt-2 w-full bg-gray-100 h-2 rounded"><div class="h-2 rounded bg-accent" style="width: <?= $epc ?>%"></div></div>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h3 class="font-semibold">Scheduled payments this month</h3>
    <ul class="mt-2 text-sm">
      <?php foreach($scheduled as $s): ?>
        <li class="flex justify-between py-1"><span><?= htmlspecialchars($s['title']) ?></span><span><?= moneyfmt($s['amount'],$s['currency']) ?></span></li>
      <?php endforeach; if(!count($scheduled)): ?>
        <li class="text-gray-500 text-sm">No scheduled payments due.</li>
      <?php endif; ?>
    </ul>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h3 class="font-semibold mb-3">Loan payments this month</h3>
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Loan</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Principal</th><th class="py-2 pr-3">Interest</th></tr></thead>
    <tbody>
      <?php foreach($loanPayments as $p): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= htmlspecialchars($p['paid_on']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($p['name']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($p['amount']) ?></td>
          <td class="py-2 pr-3"><?= moneyfmt($p['principal_component']) ?></td>
          <td class="py-2 pr-3"><?= moneyfmt($p['interest_component']) ?></td>
        </tr>
      <?php endforeach; if(!count($loanPayments)): ?>
        <tr><td class="py-3 text-gray-500" colspan="5">No loan payments recorded this month.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>