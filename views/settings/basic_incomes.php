<section class="max-w-5xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Basic Incomes</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-3 text-sm text-emerald-700"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
  <?php endif; ?>

  <div class="mt-6 grid md:grid-cols-12 gap-6">
    <!-- Left: Add / Raise -->
    <div class="md:col-span-7">
      <h2 class="font-medium mb-3">Add income / Record a raise</h2>

      <form method="post" action="/settings/basic-incomes/add"
            class="grid gap-3 md:grid-cols-12 items-end">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="md:col-span-4">
          <label class="label">Label</label>
          <input name="label" class="input" placeholder="e.g., Salary" required />
        </div>

        <div class="md:col-span-2">
          <label class="label">Amount</label>
          <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
        </div>

        <div class="md:col-span-2">
          <label class="label">Currency</label>
          <select name="currency" class="select">
            <?php foreach ($currencies as $uc): ?>
              <option value="<?= htmlspecialchars($uc['code']) ?>" <?= $uc['is_main'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($uc['code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="label">Valid from</label>
          <input name="valid_from" type="date" class="input" required />
        </div>

        <div class="md:col-span-2">
          <label class="label">Category</label>
          <select name="category_id" class="select">
            <option value="">No category</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-12 flex justify-end">
          <button class="btn btn-primary">Add</button>
        </div>
      </form>

      <p class="text-xs text-gray-500 mt-2">
        If an income with the same label exists, its previous period is automatically closed the day before the new start date.
      </p>
    </div>

    <!-- Right: Snapshot -->
    <aside class="md:col-span-5">
      <h2 class="font-medium mb-3">Current snapshot</h2>
      <?php
        $latest = [];
        foreach ($rows as $r) { $lab = $r['label']; if (!isset($latest[$lab])) $latest[$lab] = $r; }
      ?>
      <div class="space-y-3">
        <?php if ($latest): foreach ($latest as $lab=>$r): ?>
          <div class="rounded-xl border p-4 flex items-start justify-between">
            <div class="min-w-0">
              <div class="font-medium truncate"><?= htmlspecialchars($lab) ?></div>
              <div class="text-xs text-gray-500 mt-0.5">
                Since <?= htmlspecialchars($r['valid_from']) ?> — <?= moneyfmt($r['amount'], $r['currency']) ?>
              </div>
            </div>
            <?php if (!empty($r['category_id'])):
              // quick category chip
              $catMap = $catMap ?? (function($categories){ $m=[]; foreach($categories as $cx){ $m[(int)$cx['id']]=$cx; } return $m; })($categories);
              $cx = $catMap[(int)$r['category_id']] ?? null;
              if ($cx): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs shrink-0">
                  <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($cx['color']) ?>;"></span>
                  <?= htmlspecialchars($cx['label']) ?>
                </span>
            <?php endif; endif; ?>
          </div>
        <?php endforeach; else: ?>
          <div class="rounded-xl border p-4 text-sm text-gray-500">No basic incomes yet.</div>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <!-- History -->
  <div class="mt-8 overflow-x-auto">
    <h2 class="font-medium mb-2">History</h2>
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Label</th>
          <th class="py-2 pr-3">Category</th>
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
            <td class="py-2 pr-3 font-medium">
              <?php if (!empty($r['category_id'])):
                // build a small map once
                $catMap = $catMap ?? (function($categories){ $m=[]; foreach($categories as $cx){ $m[(int)$cx['id']]=$cx; } return $m; })($categories);
                $cx = $catMap[(int)$r['category_id']] ?? null;
                if ($cx): ?>
                  <div class="mt-1 text-xs">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border">
                      <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($cx['color']) ?>;"></span>
                      <?= htmlspecialchars($cx['label']) ?>
                    </span>
                  </div>
              <?php endif; endif; ?>
            </td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount'], $r['currency']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['valid_from']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['valid_to'] ?? '—') ?></td>
            <td class="py-2 pr-3">
              <details>
                <summary class="cursor-pointer text-accent">Edit</summary>
                <form class="mt-2 grid sm:grid-cols-12 gap-2" method="post" action="/settings/basic-incomes/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <input name="label"      value="<?= htmlspecialchars($r['label']) ?>" class="input sm:col-span-4" />
                  <input name="amount"     type="number" step="0.01" value="<?= htmlspecialchars($r['amount']) ?>" class="input sm:col-span-2" />
                  <input name="currency"   value="<?= htmlspecialchars($r['currency']) ?>" class="input sm:col-span-2" />
                  <input name="valid_from" type="date"  value="<?= htmlspecialchars($r['valid_from']) ?>" class="input sm:col-span-2" />
                  <input name="valid_to"   type="date"  value="<?= htmlspecialchars($r['valid_to'] ?? '') ?>" class="input sm:col-span-2" />
                  <select name="category_id" class="select sm:col-span-4">
                    <option value="">No category</option>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['category_id'] ?? 0)===(int)$c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="sm:col-span-8 flex justify-end">
                    <button class="btn btn-primary">Save</button>
                  </div>
                </form>
                <form class="mt-2" method="post" action="/settings/basic-incomes/delete"
                      onsubmit="return confirm('Delete this record?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <button class="btn btn-danger">Remove</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
