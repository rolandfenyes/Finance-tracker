<?php $ym = sprintf('%04d-%02d', $y, $m); ?>
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">This Month (<?= $ym ?>)</h2>
    <p class="mt-2 text-sm text-gray-500">Income (main <?= htmlspecialchars($main) ?>):
      <strong><?= moneyfmt($sumIn_main,$main) ?></strong>
    </p>
    <p class="mt-1 text-sm text-gray-500">Spending (main <?= htmlspecialchars($main) ?>):
      <strong><?= moneyfmt($sumOut_main,$main) ?></strong>
    </p>
    <p class="mt-1 text-sm">Net (main):
      <strong><?= moneyfmt($sumIn_main-$sumOut_main,$main) ?></strong>
    </p>
    <div class="mt-3 text-xs text-gray-500 space-y-1">
      <div>
        Native income:
        <?php if (!empty($sumIn_native_by_cur)): ?>
          <?php foreach ($sumIn_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a,$c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
      <div>
        Native spending:
        <?php if (!empty($sumOut_native_by_cur)): ?>
          <?php foreach ($sumOut_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a,$c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="text-base font-semibold mb-3">Quick Add</h3>

    <form class="grid gap-4 md:grid-cols-12 md:items-end" method="post" action="/months/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="y" value="<?= $y ?>" />
      <input type="hidden" name="m" value="<?= $m ?>" />

      <!-- Type -->
      <div class="field md:col-span-2">
        <label class="label">Type</label>
        <select name="kind" class="select">
          <option value="income">Income</option>
          <option value="spending">Spending</option>
        </select>
      </div>

      <!-- Category -->
      <div class="field md:col-span-3">
        <label class="label">Category</label>
        <select name="category_id" class="select">
          <option value="">— Category —</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= $c['id'] ?>"><?= ucfirst($c['kind']) ?> · <?= htmlspecialchars($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Amount + Currency -->
      <div class="field md:col-span-4">
        <label class="label">Amount</label>
        <div class="grid grid-cols-5 gap-2">
          <input name="amount" type="number" step="0.01" class="input col-span-3" placeholder="0.00" required />
          <select name="currency" class="select col-span-2">
            <?php foreach ($userCurrencies as $c): ?>
              <option value="<?= htmlspecialchars($c['code']) ?>" <?= $c['is_main'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['code']) ?>
              </option>
            <?php endforeach; ?>
            <?php if (!count($userCurrencies)): ?><option value="HUF">HUF</option><?php endif; ?>
          </select>
        </div>
      </div>



      <!-- Date -->
      <div class="field md:col-span-2">
        <label class="label">Date</label>
        <input name="occurred_on" type="date" value="<?= $ym ?>-01" class="input" />
      </div>

      <!-- Note -->
      <div class="field md:col-span-8">
        <label class="label">Note <span class="help">(optional)</span></label>
        <input name="note" class="input" placeholder="Add a short note…" />
      </div>

      <!-- Submit -->
      <div class="md:col-span-4 flex md:justify-end">
        <button class="btn btn-primary w-full md:w-auto">Add</button>
      </div>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <!-- Spending by Category (month) -->
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Spending by Category</h3>
    <?php
      $sp=$pdo->prepare("SELECT COALESCE(c.label,'Uncategorized') lb, SUM(t.amount) s
         FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
         WHERE t.user_id=? AND t.kind='spending' AND t.occurred_on BETWEEN ?::date AND ?::date
         GROUP BY lb ORDER BY s DESC");
      $sp->execute([uid(),$first,$last]); $labels=[]; $data=[];
      foreach($sp as $r){ $labels[]=$r['lb']; $data[]=(float)$r['s']; }
    ?>
    <canvas id="spendcat-month" class="w-full h-64"></canvas>
    <script>renderDoughnut('spendcat-month', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>

  <!-- Daily Flow -->
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Daily Flow</h3>
    <?php
      $q=$pdo->prepare("SELECT occurred_on::date d,
         SUM(CASE WHEN kind='income' THEN amount ELSE -amount END) v
         FROM transactions WHERE user_id=? AND occurred_on BETWEEN ?::date AND ?::date
         GROUP BY d ORDER BY d");
      $q->execute([uid(),$first,$last]); $labels=[]; $data=[];
      foreach($q as $r){ $labels[]=$r['d']; $data[]=(float)$r['v']; }
    ?>
    <canvas id="flow-month" class="w-full h-64"></canvas>
    <script>renderLineChart('flow-month', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h3 class="font-semibold mb-3">Transactions</h3>

  <table class="min-w-full text-sm">
    <?php if (!empty($cats)): ?>
      <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
        <?php foreach ($cats as $c): ?>
          <span class="inline-flex items-center gap-1">
            <span class="inline-block h-2.5 w-2.5 rounded-full"
                  style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>;"></span>
            <?= htmlspecialchars($c['label']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3">Date</th>
        <th class="py-2 pr-3">Kind</th>
        <th class="py-2 pr-3">Category</th>
        <th class="py-2 pr-3 text-right">Amount (native)</th>
        <th class="py-2 pr-3 text-right">Amount (<?= htmlspecialchars($main) ?>)</th>
        <th class="py-2 pr-3">Note</th>
        <th class="py-2 pr-3">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allTx as $row): ?>
        <?php
          $isVirtual = !empty($row['is_virtual']);
          $nativeCur = $row['currency'] ?: $main;

          // For virtual we already provided amount_main; for real prefer stored value
          if ($isVirtual && isset($row['amount_main'])) {
            $amtMain = (float)$row['amount_main'];
            $mainCur = $row['main_currency'] ?? $main;
          } else {
            if (isset($row['amount_main']) && $row['amount_main'] !== null && !empty($row['main_currency'])) {
              $amtMain = (float)$row['amount_main'];
              $mainCur = $row['main_currency'];
            } else {
              $amtMain = fx_convert($pdo, (float)$row['amount'], $nativeCur, $main, $row['occurred_on']);
              $mainCur = $main;
            }
          }
        ?>
        <tr class="border-b hover:bg-gray-50 <?= $isVirtual ? 'opacity-95' : '' ?>">
          <td class="py-2 pr-3">
            <?= htmlspecialchars($row['occurred_on']) ?>
            <?php if ($isVirtual): ?>
              <span class="ml-1 inline-flex items-center text-[11px] text-gray-500">
                <!-- lock icon -->
                🔒
              </span>
            <?php endif; ?>
          </td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($row['kind']) ?></td>
          <td class="py-2 pr-3">
            <span class="inline-flex items-center gap-2">
              <span class="inline-block h-2.5 w-2.5 rounded-full"
                    style="background: <?= htmlspecialchars($row['cat_color'] ?? '#6B7280') ?>;"></span>
              <?= htmlspecialchars($row['cat_label'] ?? '—') ?>
            </span>
            <?php if ($isVirtual && $row['virtual_type']==='scheduled'): ?>
              <span class="text-xs text-gray-500 ml-1">(auto)</span>
            <?php elseif ($isVirtual && $row['virtual_type']==='basic_income'): ?>
              <span class="text-xs text-gray-500 ml-1">(auto)</span>
            <?php endif; ?>
          </td>

          <!-- Native -->
          <td class="py-2 pr-3 font-medium text-right">
            <?= moneyfmt($row['amount'], $nativeCur) ?>
          </td>

          <!-- Main -->
          <td class="py-2 pr-3 text-right">
            <span class="font-medium"><?= moneyfmt($amtMain, $mainCur) ?></span>
          </td>

          <td class="py-2 pr-3 text-gray-500">
            <?= htmlspecialchars($row['note'] ?? '') ?>
          </td>

          <td class="py-2 pr-3">
            <?php if (!$isVirtual): ?>
              <details><summary class="cursor-pointer text-accent">Edit</summary>
                <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/months/tx/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= $y ?>" />
                  <input type="hidden" name="m" value="<?= $m ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <select name="kind" class="select">
                    <option <?= $row['kind']==='income'?'selected':'' ?> value="income">Income</option>
                    <option <?= $row['kind']==='spending'?'selected':'' ?> value="spending">Spending</option>
                  </select>
                  <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="input" required />
                  <select name="currency" class="select">
                    <?php foreach ($userCurrencies as $c): ?>
                      <option value="<?= htmlspecialchars($c['code']) ?>" <?= ($row['currency']===$c['code'] ? 'selected' : ($c['is_main'] && !$row['currency'] ? 'selected' : '')) ?>>
                        <?= htmlspecialchars($c['code']) ?>
                      </option>
                    <?php endforeach; ?>
                    <?php if (!count($userCurrencies)): ?><option value="HUF">HUF</option><?php endif; ?>
                  </select>
                  <input name="occurred_on" type="date" value="<?= $row['occurred_on'] ?>" class="input" />
                  <input name="note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" class="input" />
                  <button class="btn btn-primary">Save</button>
                </form>
                <form class="mt-2" method="post" action="/months/tx/delete" onsubmit="return confirm('Delete transaction?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= $y ?>" />
                  <input type="hidden" name="m" value="<?= $m ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <button class="btn btn-danger">Remove</button>
                </form>
              </details>
            <?php else: ?>
              <span class="text-xs text-gray-400">Auto-generated</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>  
  </table>
</section>

