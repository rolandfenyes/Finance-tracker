<section class="max-w-5xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold"><?= htmlspecialchars(__('settings.basic_incomes.title')) ?></h1>
    <a href="/settings" class="text-sm text-accent"><?= htmlspecialchars(__('settings.common.back')) ?></a>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-3 text-sm text-emerald-700"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
  <?php endif; ?>

  <div class="mt-6 grid md:grid-cols-12 gap-6">
    <!-- Left: Add / Raise -->
    <div class="md:col-span-7">
      <h2 class="font-medium mb-3"><?= htmlspecialchars(__('settings.basic_incomes.add_heading')) ?></h2>

      <form method="post" action="/settings/basic-incomes/add"
            class="grid gap-3 md:grid-cols-12 items-end">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="md:col-span-4">
          <label class="label"><?= htmlspecialchars(__('settings.basic_incomes.label')) ?></label>
          <input name="label" class="input" placeholder="<?= htmlspecialchars(__('settings.basic_incomes.label_placeholder')) ?>" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= htmlspecialchars(__('settings.basic_incomes.amount')) ?></label>
          <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= htmlspecialchars(__('settings.basic_incomes.currency')) ?></label>
          <select name="currency" class="select">
            <?php foreach ($currencies as $uc): ?>
              <option value="<?= htmlspecialchars($uc['code']) ?>" <?= $uc['is_main'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($uc['code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= htmlspecialchars(__('settings.basic_incomes.valid_from')) ?></label>
          <input name="valid_from" type="date" class="input" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= htmlspecialchars(__('settings.basic_incomes.category')) ?></label>
          <select name="category_id" class="select">
            <option value=""><?= htmlspecialchars(__('common.no_category')) ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-12 flex justify-end">
          <button class="btn btn-primary"><?= htmlspecialchars(__('common.add')) ?></button>
        </div>
      </form>

      <p class="text-xs text-gray-500 mt-2">
        <?= htmlspecialchars(__('settings.basic_incomes.helper')) ?>
      </p>
    </div>

    <!-- Right: Snapshot -->
    <aside class="md:col-span-5">
      <h2 class="font-medium mb-3"><?= htmlspecialchars(__('settings.basic_incomes.snapshot')) ?></h2>
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
                <?= htmlspecialchars(__('settings.basic_incomes.since', ['date' => $r['valid_from'], 'amount' => moneyfmt($r['amount'], $r['currency'])])) ?>
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
          <div class="rounded-xl border p-4 text-sm text-gray-500"><?= htmlspecialchars(__('settings.basic_incomes.none')) ?></div>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <!-- History -->
  <div class="mt-8 overflow-x-auto">
    <h2 class="font-medium mb-2"><?= htmlspecialchars(__('settings.basic_incomes.history')) ?></h2>
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.label')) ?></th>
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.category')) ?></th>
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.amount')) ?></th>
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.currency')) ?></th>
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.valid_from')) ?></th>
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.valid_to')) ?></th>
          <th class="py-2 pr-3"><?= htmlspecialchars(__('settings.basic_incomes.table.actions')) ?></th>
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
                <summary class="cursor-pointer text-accent"><?= htmlspecialchars(__('common.edit')) ?></summary>
                <form class="mt-2 grid sm:grid-cols-12 gap-2" method="post" action="/settings/basic-incomes/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <input name="label"      value="<?= htmlspecialchars($r['label']) ?>" class="input sm:col-span-4" />
                  <input name="amount"     type="number" step="0.01" value="<?= htmlspecialchars($r['amount']) ?>" class="input sm:col-span-2" />
                  <input name="currency"   value="<?= htmlspecialchars($r['currency']) ?>" class="input sm:col-span-2" />
                  <input name="valid_from" type="date"  value="<?= htmlspecialchars($r['valid_from']) ?>" class="input sm:col-span-2" />
                  <input name="valid_to"   type="date"  value="<?= htmlspecialchars($r['valid_to'] ?? '') ?>" class="input sm:col-span-2" />
                  <select name="category_id" class="select sm:col-span-4">
                    <option value=""><?= htmlspecialchars(__('common.no_category')) ?></option>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['category_id'] ?? 0)===(int)$c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="sm:col-span-8 flex justify-end">
                    <button class="btn btn-primary"><?= htmlspecialchars(__('common.save')) ?></button>
                  </div>
                </form>
                <form class="mt-2" method="post" action="/settings/basic-incomes/delete"
                      onsubmit="return confirm(<?= json_encode(__('settings.basic_incomes.delete_confirm')) ?>)">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <button class="btn btn-danger"><?= htmlspecialchars(__('common.remove')) ?></button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
