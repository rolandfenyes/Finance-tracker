<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">This Month (<?= $y ?>-<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>)</h2>

    <p class="mt-2 text-sm text-gray-500">Income (main <?= htmlspecialchars($main) ?>):
      <strong><?= moneyfmt($sumIn_main, $main) ?></strong>
    </p>
    <p class="mt-1 text-sm text-gray-500">Spending (main <?= htmlspecialchars($main) ?>):
      <strong><?= moneyfmt($sumOut_main, $main) ?></strong>
    </p>
    <p class="mt-1 text-sm">Net (main):
      <strong><?= moneyfmt($sumIn_main - $sumOut_main, $main) ?></strong>
    </p>

    <div class="mt-3 text-xs text-gray-500 space-y-1">
      <div>
        Native income:
        <?php if (!empty($sumIn_native_by_cur)): ?>
          <?php foreach ($sumIn_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a, $c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
      <div>
        Native spending:
        <?php if (!empty($sumOut_native_by_cur)): ?>
          <?php foreach ($sumOut_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a, $c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
    </div>
  </div>


  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="font-semibold mb-3">Quick Add</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/transactions/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <select name="kind" class="rounded-xl border-gray-300 sm:col-span-1">
        <option value="income">Income</option>
        <option value="spending">Spending</option>
      </select>
      <select name="category_id" class="rounded-xl border-gray-300 sm:col-span-2">
        <option value="">— Category —</option>
        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?php echo ucfirst($c['kind']).' · '.htmlspecialchars($c['label']); ?></option><?php endforeach; ?>
      </select>
      <input name="amount" type="number" step="0.01" placeholder="Amount" class="rounded-xl border-gray-300 sm:col-span-1" required />
      <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
      <input name="note" placeholder="Note" class="rounded-xl border-gray-300 sm:col-span-1" />
      <button class="bg-gray-900 text-white rounded-xl px-4">Add</button>
    </form>
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
              <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/transactions/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
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
              <form class="mt-2" method="post" action="/transactions/delete" onsubmit="return confirm('Delete transaction?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
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