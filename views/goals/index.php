<?php require_login(); $pct = ($tot['t']>0)? round($tot['c']/$tot['t']*100):0; ?>
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= htmlspecialchars(__('goals.title')) ?></h1>
  <div class="mt-3 bg-gray-100 h-2 rounded"><div class="h-2 bg-accent rounded" style="width: <?=$pct?>%"></div></div>
  <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars(__('goals.overall_progress', ['percent' => $pct])) ?></p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= htmlspecialchars(__('goals.add_goal')) ?></summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/goals/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="title" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="<?= htmlspecialchars(__('goals.form.title_placeholder')) ?>" required>
      <input name="target_amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="<?= htmlspecialchars(__('goals.form.target_amount')) ?>" required>
      <input name="current_amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="<?= htmlspecialchars(__('goals.form.current_amount')) ?>">
      <input name="currency" class="rounded-xl border-gray-300" value="HUF">
      <input name="deadline" type="date" class="rounded-xl border-gray-300">
      <select name="priority" class="rounded-xl border-gray-300"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
      <select name="status" class="rounded-xl border-gray-300">
        <option value="active" selected><?= htmlspecialchars(__('goals.form.status_options.active')) ?></option>
        <option value="paused"><?= htmlspecialchars(__('goals.form.status_options.paused')) ?></option>
        <option value="done"><?= htmlspecialchars(__('goals.form.status_options.done')) ?></option>
      </select>
      <button class="bg-gray-900 text-white rounded-xl px-4"><?= htmlspecialchars(__('common.save')) ?></button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3"><?= htmlspecialchars(__('goals.table.title')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('goals.table.progress')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('goals.table.deadline')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('goals.table.status')) ?></th><th class="py-2 pr-3"><?= htmlspecialchars(__('common.actions')) ?></th></tr></thead>
    <tbody>
    <?php foreach($rows as $g): $p = $g['target_amount']>0? round($g['current_amount']/$g['target_amount']*100):0; ?>
      <tr class="border-b">
        <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($g['title']) ?></td>
        <td class="py-2 pr-3"><div class="w-40 bg-gray-100 h-2 rounded inline-block align-middle"><div class="h-2 bg-accent rounded" style="width: <?=$p?>%"></div></div> <span class="text-xs ml-2"><?=$p?>%</span></td>
        <td class="py-2 pr-3"><?= htmlspecialchars($g['deadline']??'—') ?></td>
        <?php
          $statusKey = (string)($g['status'] ?? '');
          $statusLabel = __('goals.form.status_options.' . $statusKey);
          if ($statusLabel === 'goals.form.status_options.' . $statusKey) {
              $statusLabel = $statusKey !== '' ? ucfirst($statusKey) : '';
          }
        ?>
        <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($statusLabel) ?></td>
        <td class="py-2 pr-3">
          <details>
            <summary class="cursor-pointer text-accent"><?= htmlspecialchars(__('common.edit')) ?></summary>
            <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/goals/edit">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $g['id'] ?>" />
              <input name="title" value="<?= htmlspecialchars($g['title']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
              <input name="target_amount" type="number" step="0.01" value="<?= $g['target_amount'] ?>" class="rounded-xl border-gray-300">
              <input name="current_amount" type="number" step="0.01" value="<?= $g['current_amount'] ?>" class="rounded-xl border-gray-300">
              <input name="currency" value="<?= htmlspecialchars($g['currency']) ?>" class="rounded-xl border-gray-300">
              <input name="deadline" type="date" value="<?= $g['deadline'] ?>" class="rounded-xl border-gray-300">
              <select name="priority" class="rounded-xl border-gray-300">
                <?php for($i=1;$i<=5;$i++): ?><option <?=$g['priority']==$i?'selected':''?>><?=$i?></option><?php endfor; ?>
              </select>
              <select name="status" class="rounded-xl border-gray-300">
                <option <?=$g['status']==='active'?'selected':''?> value="active"><?= htmlspecialchars(__('goals.form.status_options.active')) ?></option>
                <option <?=$g['status']==='paused'?'selected':''?> value="paused"><?= htmlspecialchars(__('goals.form.status_options.paused')) ?></option>
                <option <?=$g['status']==='done'?'selected':''?> value="done"><?= htmlspecialchars(__('goals.form.status_options.done')) ?></option>
              </select>
              <button class="bg-gray-900 text-white rounded-xl px-4"><?= htmlspecialchars(__('common.save')) ?></button>
            </form>
            <form class="mt-2" method="post" action="/goals/delete" onsubmit="return confirm(<?= json_encode(__('goals.delete_confirm')) ?>)">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $g['id'] ?>" />
              <button class="text-red-600"><?= htmlspecialchars(__('common.remove')) ?></button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>