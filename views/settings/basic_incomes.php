<section class="max-w-4xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Basic Incomes</h1>
    <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-3 text-sm text-emerald-700"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>

    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <!-- Add / Raise form -->
    <div class="order-2 md:order-1">
      <h2 class="font-medium mb-2">Add income / Record a raise</h2>
      <form method="post" action="/settings/basic-incomes/add" class="grid sm:grid-cols-6 gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input name="label" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="e.g., Salary" required>
        <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Amount" required>
        <select name="currency" class="rounded-xl border-gray-300 sm:col-span-1">
          <?php foreach($currencies as $c): ?>
            <option value="<?= htmlspecialchars($c['code']) ?>" <?= $c['is_main']? 'selected':'' ?>><?= htmlspecialchars($c['code']) ?></option>
          <?php endforeach; if(!count($currencies)): ?>
            <option value="HUF">HUF</option>
          <?php endif; ?>
        </select>
        <input name="valid_from" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1">
        <button class="bg-gray-900 text-white rounded-xl px-4 sm:col-span-6">Save</button>
      </form>
      <p class="text-xs text-gray-500 mt-2">If an income with the same label exists, its previous period is automatically closed the day before the new start date.</p>
    </div>

    <!-- Current income snapshot by label (latest row) -->
    <div class="order-1 md:order-2">
      <h2 class="font-medium mb-2">Current snapshot</h2>
      <?php
        $latest = [];
        foreach ($rows as $r) {
          $lab = $r['label'];
          if (!isset($latest[$lab])) $latest[$lab] = $r; // rows sorted by valid_from DESC
        }
      ?>
      <ul class="divide-y">
        <?php foreach($latest as $lab=>$r): ?>
          <li class="py-3 flex items-center justify-between">
            <div>
              <div class="font-medium"><?= htmlspecialchars($lab) ?></div>
              <div class="text-xs text-gray-500">Since <?= htmlspecialchars($r['valid_from']) ?> — <?= moneyfmt($r['amount'], $r['currency']) ?></div>
            </div>
          </li>
        <?php endforeach; if(!count($latest)): ?>
          <li class="py-3 text-sm text-gray-500">No basic incomes yet.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <!-- Full history table -->
  <div class="mt-8 overflow-x-auto">
    <h2 class="font-medium mb-2">History</h2>
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Label</th>
          <th class="py-2 pr-3">Amount</th>
          <th class="py-2 pr-3">Currency</th>
          <th class="py-2 pr-3">Valid From</th>
          <th class="py-2 pr-3">Valid To</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr class="border-b">
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($r['label']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['valid_from']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['valid_to'] ?? '—') ?></td>
            <td class="py-2 pr-3">
              <details>
                <summary class="cursor-pointer text-accent">Edit</summary>
                <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/settings/basic-incomes/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <input name="label" value="<?= htmlspecialchars($r['label']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                  <input name="amount" type="number" step="0.01" value="<?= $r['amount'] ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                  <input name="currency" value="<?= htmlspecialchars($r['currency']) ?>" class="rounded-xl border-gray-300 sm:col-span-1">
                  <input name="valid_from" type="date" value="<?= $r['valid_from'] ?>" class="rounded-xl border-gray-300 sm:col-span-1">
                  <input name="valid_to" type="date" value="<?= $r['valid_to'] ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                  <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
                </form>
                <form class="mt-2" method="post" action="/settings/basic-incomes/delete" onsubmit="return confirm('Delete this record?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <button class="text-red-600">Remove</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>