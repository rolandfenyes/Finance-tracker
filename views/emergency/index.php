<?php
  $targetAmount = $fund['target_amount'] ?? 0;
  $pct = ($fund && $targetAmount > 0) ? round($fund['total'] / $targetAmount * 100) : 0;
  $totalDisplay = $fund ? moneyfmt($fund['total'], $fund['currency']) : '—';
  $targetDisplay = $fund ? moneyfmt($fund['target_amount'], $fund['currency']) : '—';
?>

<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= htmlspecialchars(__('emergency.title')) ?></h1>
  <p class="text-sm mt-1"><?= htmlspecialchars(__('emergency.total', ['amount' => $totalDisplay])) ?></p>
  <div class="mt-2 bg-gray-100 h-2 rounded"><div class="h-2 bg-accent rounded" style="width: <?= $pct ?>%"></div></div>
  <p class="text-xs text-gray-500 mt-1">
    <?= htmlspecialchars(__('emergency.target', ['amount' => $targetDisplay])) ?> (<?= $pct ?>%)
  </p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= htmlspecialchars(__('emergency.set_target')) ?></summary>
    <form class="mt-3 grid sm:grid-cols-4 gap-2" method="post" action="/emergency/set">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="target_amount" type="number" step="0.01" value="<?= htmlspecialchars($fund['target_amount'] ?? '') ?>" class="rounded-xl border-gray-300" placeholder="<?= htmlspecialchars(__('emergency.target_amount_placeholder')) ?>" required>
      <input name="currency" value="<?= htmlspecialchars($fund['currency'] ?? 'HUF') ?>" class="rounded-xl border-gray-300" placeholder="<?= htmlspecialchars(__('common.currency')) ?>">
      <button class="bg-gray-900 text-white rounded-xl px-4"><?= htmlspecialchars(__('common.save')) ?></button>
    </form>
  </details>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= htmlspecialchars(__('emergency.add_transaction')) ?></summary>
    <form class="mt-3 grid sm:grid-cols-5 gap-2" method="post" action="/emergency/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <select name="kind" class="rounded-xl border-gray-300">
        <option value="deposit"><?= htmlspecialchars(__('emergency.kind.deposit')) ?></option>
        <option value="withdraw"><?= htmlspecialchars(__('emergency.kind.withdraw')) ?></option>
      </select>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="<?= htmlspecialchars(__('common.amount')) ?>" required>
      <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
      <input name="note" class="rounded-xl border-gray-300" placeholder="<?= htmlspecialchars(__('common.note')) ?>">
      <button class="bg-gray-900 text-white rounded-xl px-4"><?= htmlspecialchars(__('common.save')) ?></button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h2 class="font-semibold mb-3"><?= htmlspecialchars(__('emergency.table.title')) ?></h2>
  <table class="min-w-full text-sm">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3"><?= htmlspecialchars(__('emergency.table.date')) ?></th>
        <th class="py-2 pr-3"><?= htmlspecialchars(__('emergency.table.kind')) ?></th>
        <th class="py-2 pr-3"><?= htmlspecialchars(__('emergency.table.amount')) ?></th>
        <th class="py-2 pr-3"><?= htmlspecialchars(__('emergency.table.note')) ?></th>
        <th class="py-2 pr-3"><?= htmlspecialchars(__('common.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($tx as $t): ?>
        <?php
          $kindKey = 'emergency.kind.' . ($t['kind'] ?? '');
          $kindLabel = __($kindKey);
          if ($kindLabel === $kindKey) { $kindLabel = $t['kind']; }
        ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= htmlspecialchars($t['occurred_on']) ?></td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($kindLabel) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($t['amount']) ?></td>
          <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($t['note'] ?? '') ?></td>
          <td class="py-2 pr-3">
            <form method="post" action="/emergency/tx/delete" onsubmit="return confirm(<?= json_encode(__('emergency.delete_confirm')) ?>)">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>" />
              <button class="text-red-600"><?= htmlspecialchars(__('common.remove')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>