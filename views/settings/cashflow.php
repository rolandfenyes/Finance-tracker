<section class="max-w-3xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Cashflow Rules</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <!-- Progress -->
  <?php
    $pct = max(0, min(100, round($total,2)));
    $barColor = $pct < 100 ? 'bg-green-500' : ($pct == 100 ? 'bg-indigo-600' : 'bg-red-500');
  ?>
  <div class="mt-4">
    <div class="flex justify-between text-xs text-gray-600">
      <span>Total allocation</span>
      <span><?= number_format($pct,2) ?>%</span>
    </div>
    <div class="mt-1 h-3 w-full bg-gray-100 rounded-full overflow-hidden">
      <div class="h-3 <?= $barColor ?>" style="width: <?= $pct ?>%"></div>
    </div>
    <?php if ($total > 100): ?>
      <p class="mt-2 text-xs text-red-600">You’re over 100%. Reduce some rules.</p>
    <?php elseif ($total < 100): ?>
      <p class="mt-2 text-xs text-gray-500"><?= number_format(100-$total,2) ?>% unallocated.</p>
    <?php else: ?>
      <p class="mt-2 text-xs text-gray-500">Perfect! Fully allocated.</p>
    <?php endif; ?>
  </div>

  <!-- Add rule -->
  <div class="mt-6">
    <h2 class="font-medium mb-2">Add rule</h2>
    <form method="post" action="/settings/cashflow/add" class="grid sm:grid-cols-6 gap-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="label" class="input sm:col-span-4" placeholder="e.g. Needs / Investments / Fun" required />
      <div class="sm:col-span-1">
        <div class="flex items-center gap-2">
          <input name="percent" type="number" step="0.01" min="0" class="input" placeholder="%" required />
          <span class="text-sm text-gray-500">%</span>
        </div>
      </div>
      <button class="btn btn-primary sm:col-span-1">Add</button>
    </form>
  </div>

  <!-- List -->
  <div class="mt-6">
    <h2 class="font-medium mb-2">Your rules</h2>
    <ul class="divide-y rounded-xl border">
      <?php if (!count($rules)): ?>
        <li class="p-3 text-sm text-gray-500">No rules yet.</li>
      <?php else: foreach($rules as $r): ?>
        <li class="p-3">
          <details class="group">
            <summary class="flex items-center justify-between cursor-pointer list-none">
              <div class="flex items-center gap-3">
                <span class="font-medium"><?= htmlspecialchars($r['label']) ?></span>
              </div>
              <div class="text-right">
                <span class="text-sm font-semibold"><?= number_format((float)$r['percent'],2) ?>%</span>
              </div>
            </summary>
            <div class="mt-3 bg-gray-50 rounded-xl p-3 border">
              <form class="grid gap-2 sm:grid-cols-6 items-end" method="post" action="/settings/cashflow/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <input name="label" class="input sm:col-span-4" value="<?= htmlspecialchars($r['label']) ?>" />
                <div class="sm:col-span-1">
                  <div class="flex items-center gap-2">
                    <input name="percent" type="number" step="0.01" min="0" class="input"
                           value="<?= number_format((float)$r['percent'],2,'.','') ?>" />
                    <span class="text-sm text-gray-500">%</span>
                  </div>
                </div>
                <button class="btn btn-primary sm:col-span-1">Save</button>
              </form>
              <form class="mt-2 flex justify-end" method="post" action="/settings/cashflow/delete"
                    onsubmit="return confirm('Delete this rule?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <button class="btn btn-danger">Remove</button>
              </form>
            </div>
          </details>
        </li>
      <?php endforeach; endif; ?>
    </ul>
  </div>
</section>
