<section class="max-w-4xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Categories</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <!-- Add -->
    <div>
      <h2 class="font-medium mb-2">Add category</h2>
      <form method="post" action="/settings/categories/add" class="grid sm:grid-cols-6 gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <select name="kind" class="select sm:col-span-2">
          <option value="income">Income</option>
          <option value="spending">Spending</option>
        </select>
        <input name="label" class="input sm:col-span-2" placeholder="Label (e.g., Salary / Groceries)" required />
        <select name="cashflow_rule_id" class="select sm:col-span-2">
          <option value="">No rule</option>
          <?php foreach ($rules as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="color" type="color" value="#6B7280" class="h-10 w-16 rounded-xl border border-gray-300 sm:col-span-1" />
        <button class="btn btn-primary sm:col-span-5">Add</button>
      </form>


      <p class="help mt-2">These categories appear in the “Quick Add” and transaction forms.</p>
    </div>

    <!-- List -->
    <div>
      <h2 class="font-medium mb-2">Your categories</h2>
      <?php
        $income = array_values(array_filter($rows, fn($r)=>$r['kind']==='income'));
        $spend  = array_values(array_filter($rows, fn($r)=>$r['kind']==='spending'));
        $renderList = function($list,$title,$usage) use ($rules){
      ?>
        <div class="mb-5">
          <div class="text-sm font-semibold text-gray-600 mb-2"><?= htmlspecialchars($title) ?></div>
          <ul class="divide-y rounded-xl border">
            <?php if (!count($list)): ?>
              <li class="p-3 text-sm text-gray-500">No categories yet.</li>
            <?php else: foreach($list as $c): 
              $used   = (int)($usage[$c['id']] ?? 0);
              $isEF   = in_array($c['system_key'] ?? '', ['ef_add','ef_withdraw'], true);
              // derive rule name for this category (if any)
              $ruleName = null;
              if (!empty($rules)) {
                foreach ($rules as $rul) {
                  if ((int)$rul['id'] === (int)($c['cashflow_rule_id'] ?? 0)) { $ruleName = $rul['label']; break; }
                }
              }
            ?>
              <li class="p-3">
                <details class="group">
                  <summary class="flex items-center justify-between gap-3 cursor-pointer list-none">
                    <div class="flex items-center gap-2">
                      <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>;"></span>
                      <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>
                      <?php if ($isEF): ?><span class="chip">Protected</span><?php endif; ?>
                      <?php if ($ruleName): ?><span class="chip"><?= htmlspecialchars($ruleName) ?></span><?php endif; ?>
                    </div>
                    <span class="row-btn">Edit</span>
                  </summary>

                  <div class="edit-panel">
                    <!-- EDIT form -->
                    <form class="grid gap-3 sm:grid-cols-12 items-end" method="post" action="/settings/categories/edit">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />

                      <div class="field sm:col-span-3">
                        <label class="label">Type</label>
                        <select name="kind" class="select">
                          <option value="income"   <?= $c['kind']==='income'?'selected':'' ?>>Income</option>
                          <option value="spending" <?= $c['kind']==='spending'?'selected':'' ?>>Spending</option>
                        </select>
                      </div>

                      <div class="field sm:col-span-6">
                        <label class="label">Label</label>
                        <input name="label" value="<?= htmlspecialchars($c['label']) ?>" class="input" />
                      </div>

                      <div class="field sm:col-span-3">
                        <label class="label">Color</label>
                        <input name="color" type="color"
                              value="<?= htmlspecialchars($c['color'] ?? '#6B7280') ?>"
                              class="h-10 w-16 rounded-xl border border-gray-300" />
                      </div>

                      <div class="field sm:col-span-6">
                        <label class="label">Cashflow rule</label>
                        <select name="cashflow_rule_id" class="select">
                          <option value="">No rule</option>
                          <?php foreach ($rules as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"
                              <?= ((int)($c['cashflow_rule_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($r['label']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="sm:col-span-12 flex gap-2 justify-end">
                        <button class="btn btn-primary">Save</button>
                        <?php if (!$isEF): ?>
                          <!-- DELETE form as a separate sibling form (no nesting) -->
                          <form method="post" action="/settings/categories/delete"
                                onsubmit="return confirm('Delete this category? Transactions will remain without a category.');" class="inline">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                            <button class="btn btn-danger">Remove</button>
                          </form>
                        <?php else: ?>
                        <?php endif; ?>
                      </div>
                    </form>
                  </div>
                </details>
              </li>
            <?php endforeach; endif; ?>

          </ul>
        </div>
      <?php }; $renderList($income,'Income',$usage); $renderList($spend,'Spending',$usage); ?>
    </div>
  </div>
</section>
