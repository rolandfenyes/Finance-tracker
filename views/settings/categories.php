<section class="max-w-4xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Categories</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <!-- Add -->
    <div>
      <h2 class="font-medium mb-2">Add category</h2>

      <form id="cat-add-form" method="post" action="/settings/categories/add" class="grid sm:grid-cols-6 gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

        <div class="sm:col-span-2">
          <label class="label">Type</label>
          <select id="add-kind" name="kind" class="select">
            <option value="income">Income</option>
            <option value="spending">Spending</option>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label class="label">Label</label>
          <input name="label" class="input" placeholder="Label (e.g., Salary / Groceries)" required />
        </div>

        <!-- Cashflow rule (only for Spending) -->
        <div id="add-rule-wrap" class="sm:col-span-2">
          <label class="label">Cashflow rule</label>
          <select name="cashflow_rule_id" class="select">
            <option value="">No rule</option>
            <?php foreach ($rules as $r): ?>
              <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="sm:col-span-1">
          <label class="label">Color</label>
          <input name="color" type="color" value="#6B7280" class="h-10 w-16 rounded-xl border border-gray-300" />
        </div>

        <div class="sm:col-span-5 flex items-end justify-end">
          <button class="btn btn-primary">Add</button>
        </div>
      </form>

      <p class="help mt-2">These categories appear in the “Quick Add” and transaction forms.</p>
    </div>

    <!-- List -->
    <div>
      <h2 class="font-medium mb-2">Your categories</h2>
      <?php
        $income = array_values(array_filter($rows, fn($r)=>$r['kind']==='income'));
        $spend  = array_values(array_filter($rows, fn($r)=>$r['kind']==='spending'));

        $renderList = function($list,$title,$usage,$isSpending) use ($rules){
      ?>
        <div class="mb-6">
          <div class="text-sm font-semibold text-gray-600 mb-2"><?= htmlspecialchars($title) ?></div>
          <ul class="divide-y rounded-xl border">
            <?php if (!count($list)): ?>
              <li class="p-3 text-sm text-gray-500">No categories yet.</li>
            <?php else: foreach($list as $c):
              $used   = (int)($usage[$c['id']] ?? 0);
              $isEF   = in_array($c['system_key'] ?? '', ['ef_add','ef_withdraw'], true);

              // rule name
              $ruleName = null;
              if (!empty($rules) && !empty($c['cashflow_rule_id'])) {
                foreach ($rules as $rul) {
                  if ((int)$rul['id'] === (int)$c['cashflow_rule_id']) { $ruleName = $rul['label']; break; }
                }
              }

              $showWarn = $isSpending && empty($c['cashflow_rule_id']);
            ?>
              <li class="p-3">
                <details class="group">
                  <summary class="flex items-center justify-between gap-3 cursor-pointer list-none">
                    <div class="flex items-center gap-2">
                      <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>;"></span>
                      <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>

                      <?php if ($isEF): ?>
                        <span class="chip">Protected</span>
                      <?php endif; ?>

                      <?php if ($ruleName): ?>
                        <span class="chip"><?= htmlspecialchars($ruleName) ?></span>
                      <?php elseif ($showWarn): ?>
                        <span class="chip" title="No cashflow rule set">⚠️ No rule</span>
                      <?php endif; ?>
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
                        <select name="kind" class="select" id="kind-<?= (int)$c['id'] ?>">
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

                      <!-- Rule: visible only for Spending; hidden/disabled for Income -->
                      <div class="field sm:col-span-6" id="rule-wrap-<?= (int)$c['id'] ?>">
                        <label class="label">Cashflow rule</label>
                        <?php if ($c['kind'] === 'income'): ?>
                          <input type="hidden" name="cashflow_rule_id" value="">
                          <div class="text-xs text-gray-500">Income categories can’t have cashflow rules.</div>
                        <?php else: ?>
                          <select name="cashflow_rule_id" class="select">
                            <option value="">No rule</option>
                            <?php foreach ($rules as $r): ?>
                              <option value="<?= (int)$r['id'] ?>"
                                <?= ((int)($c['cashflow_rule_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['label']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <?php if (empty($c['cashflow_rule_id'])): ?>
                            <div class="text-xs text-amber-600 mt-1">⚠️ No cashflow rule set — won’t appear in guidance.</div>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>

                      <div class="sm:col-span-12 flex gap-2 justify-end">
                        <button class="btn btn-primary">Save</button>
                        <?php if (!$isEF): ?>
                          <form method="post" action="/settings/categories/delete"
                                onsubmit="return confirm('Delete this category? Transactions will remain without a category.');" class="inline">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                            <button class="btn btn-danger">Remove</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </form>
                  </div>
                </details>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      <?php }; $renderList($income,'Income',$usage,false); $renderList($spend,'Spending',$usage,true); ?>
    </div>
  </div>
</section>

<script>
// Add form: toggle rule selector based on kind
(function(){
  const kind = document.getElementById('add-kind');
  const wrap = document.getElementById('add-rule-wrap');
  function sync(){ wrap.style.display = (kind.value === 'spending') ? '' : 'none'; }
  if (kind && wrap){ sync(); kind.addEventListener('change', sync); }
})();
</script>
