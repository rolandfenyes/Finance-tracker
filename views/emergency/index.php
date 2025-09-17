<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Emergency Fund</h1>
  <?php $pct = ($fund && $fund['target_amount']>0)? round($fund['total']/$fund['target_amount']*100):0; ?>
  <p class="text-sm mt-1">Total: <?= $fund? moneyfmt($fund['total'],$fund['currency']) : '—' ?></p>
  <div class="mt-2 bg-gray-100 h-2 rounded"><div class="h-2 bg-accent rounded" style="width: <?=$pct?>%"></div></div>
  <p class="text-xs text-gray-500 mt-1">Target: <?= $fund? moneyfmt($fund['target_amount'],$fund['currency']) : '—' ?> (<?=$pct?>%)</p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Set target</summary>
    <form class="mt-3 grid sm:grid-cols-4 gap-2" method="post" action="/emergency/set">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="target_amount" type="number" step="0.01" value="<?= $fund['target_amount']??'' ?>" class="rounded-xl border-gray-300" placeholder="Target amount" required>
      <input name="currency" value="<?= $fund['currency']??'HUF' ?>" class="rounded-xl border-gray-300">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add transaction</summary>
    <form class="mt-3 grid sm:grid-cols-5 gap-2" method="post" action="/emergency/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <select name="kind" class="rounded-xl border-gray-300"><option value="deposit">Deposit</option><option value="withdraw">Withdraw</option></select>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Amount" required>
      <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
      <input name="note" class="rounded-xl border-gray-300" placeholder="Note">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h2 class="font-semibold mb-3">Transactions</h2>
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Kind</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Note</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
      <?php foreach($tx as $t): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= htmlspecialchars($t['occurred_on']) ?></td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($t['kind']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($t['amount']) ?></td>
          <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($t['note']??'') ?></td>
          <td class="py-2 pr-3">
            <form method="post" action="/emergency/tx/delete" onsubmit="return confirm('Delete transaction?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $t['id'] ?>" />
              <button class="text-red-600">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>