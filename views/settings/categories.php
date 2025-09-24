<section class="max-w-4xl mx-auto">
  <div class="card space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold"><?= __('Manage Categories') ?></h1>
      <div class="flex items-center gap-2">
        <a href="/settings" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to Settings') ?></span>
        </a>
        <a href="/more" class="inline-flex sm:hidden items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to More') ?></span>
        </a>
      </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <div>
        <h2 class="font-medium mb-2"><?= __('Add category') ?></h2>
        <form id="cat-add-form" method="post" action="/settings/categories/add" class="grid gap-2 sm:grid-cols-6">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <div class="sm:col-span-2">
            <label class="label"><?= __('Type') ?></label>
            <select id="add-kind" name="kind" class="select">
              <option value="income"><?= __('Income') ?></option>
              <option value="spending"><?= __('Spending') ?></option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="label"><?= __('Label') ?></label>
            <input name="label" class="input" placeholder="<?= __('Label (e.g., Salary / Groceries)') ?>" required />
          </div>
          <div id="add-rule-wrap" class="sm:col-span-2">
            <label class="label"><?= __('Cashflow rule') ?></label>
            <select name="cashflow_rule_id" class="select">
              <option value=""><?= __('No rule') ?></option>
              <?php foreach ($rules as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm:col-span-1">
            <label class="label"><?= __('Color') ?></label>
            <input name="color" type="color" value="#6B7280" class="color-input" />
          </div>
          <div class="sm:col-span-5 flex items-end justify-end">
            <button class="btn btn-primary"><?= __('Add') ?></button>
          </div>
        </form>
        <p class="help mt-2"><?= __('These categories appear in the “Quick Add” and transaction forms.') ?></p>
      </div>

      <div>
        <h2 class="font-medium mb-2"><?= __('Your categories') ?></h2>
        <?php
          $income = array_values(array_filter($rows, fn($r)=>$r['kind']==='income'));
          $spend  = array_values(array_filter($rows, fn($r)=>$r['kind']==='spending'));

          $renderList = function($list,$title,$usage,$isSpending) use ($rules){
        ?>
          <div class="mb-6">
            <div class="mb-2 text-sm font-semibold text-gray-600"><?= htmlspecialchars($title) ?></div>
            <ul class="glass-stack">
              <?php if (!count($list)): ?>
                <li class="glass-stack__item text-sm text-gray-500"><?= __('No categories yet.') ?></li>
              <?php else: foreach($list as $c):
                $used   = (int)($usage[$c['id']] ?? 0);
                $isEF   = in_array($c['system_key'] ?? '', ['ef_add','ef_withdraw'], true);
                $ruleName = null;
                if (!empty($rules) && !empty($c['cashflow_rule_id'])) {
                  foreach ($rules as $rul) {
                    if ((int)$rul['id'] === (int)$c['cashflow_rule_id']) { $ruleName = $rul['label']; break; }
                  }
                }
                $showWarn = $isSpending && empty($c['cashflow_rule_id']);
              ?>
                <li class="glass-stack__item">
                  <details class="group">
                    <summary class="flex cursor-pointer flex-wrap gap-3 sm:flex-nowrap sm:items-center sm:justify-between">
                      <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 sm:flex-nowrap">
                        <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>;"></span>
                        <span class="font-medium break-words leading-snug">
                          <?= htmlspecialchars($c['label']) ?>
                        </span>
                        <?php if ($isEF): ?>
                          <span class="chip"><?= __('Protected') ?></span>
                        <?php endif; ?>
                        <?php if ($ruleName): ?>
                          <span class="chip"><?= htmlspecialchars($ruleName) ?></span>
                        <?php elseif ($showWarn): ?>
                          <span class="chip" title="<?= __('No cashflow rule set') ?>">⚠️ <?= __('No rule') ?></span>
                        <?php endif; ?>
                      </div>
                      <span class="flex flex-shrink-0 items-center gap-2 text-sm text-gray-500">
                        <span class="icon-action icon-action--primary" aria-hidden="true">
                          <i data-lucide="pencil" class="h-4 w-4"></i>
                        </span>
                        <span class="sr-only"><?= __('Edit') ?></span>
                      </span>
                    </summary>

                    <div class="edit-panel">
                      <form class="grid items-end gap-3 sm:grid-cols-12" method="post" action="/settings/categories/edit">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                        <div class="field sm:col-span-3">
                          <label class="label"><?= __('Type') ?></label>
                          <select name="kind" class="select" id="kind-<?= (int)$c['id'] ?>">
                            <option value="income"   <?= $c['kind']==='income'?'selected':'' ?>><?= __('Income') ?></option>
                            <option value="spending" <?= $c['kind']==='spending'?'selected':'' ?>><?= __('Spending') ?></option>
                          </select>
                        </div>
                        <div class="field sm:col-span-6">
                          <label class="label"><?= __('Label') ?></label>
                          <input name="label" value="<?= htmlspecialchars($c['label']) ?>" class="input" />
                        </div>
                        <div class="field sm:col-span-3">
                          <label class="label"><?= __('Color') ?></label>
                          <input name="color" type="color" value="<?= htmlspecialchars($c['color'] ?? '#6B7280') ?>" class="color-input" />
                        </div>
                        <div class="field sm:col-span-6" id="rule-wrap-<?= (int)$c['id'] ?>">
                          <label class="label"><?= __('Cashflow rule') ?></label>
                          <?php if ($c['kind'] === 'income'): ?>
                            <input type="hidden" name="cashflow_rule_id" value="">
                            <div class="text-xs text-gray-500"><?= __('Income categories can’t have cashflow rules.') ?></div>
                          <?php else: ?>
                            <select name="cashflow_rule_id" class="select">
                              <option value=""><?= __('No rule') ?></option>
                              <?php foreach ($rules as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" <?= ((int)($c['cashflow_rule_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($r['label']) ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                            <?php if (empty($c['cashflow_rule_id'])): ?>
                              <div class="text-xs text-amber-600 mt-1">⚠️ <?= __('No cashflow rule set — won’t appear in guidance.') ?></div>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                        <div class="sm:col-span-12 flex justify-end">
                          <button class="btn btn-primary"><?= __('Save') ?></button>
                        </div>
                      </form>
                      <?php if (!$isEF): ?>
                        <form method="post" action="/settings/categories/delete"
                              onsubmit="return confirm('<?= addslashes(__('Delete this category? Transactions will remain without a category.')) ?>');"
                              class="mt-2 flex justify-end">
                          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                          <button class="btn btn-danger" type="submit" title="<?= __('Remove') ?>">
                            <?= __('Remove') ?>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </details>
                </li>
              <?php endforeach; endif; ?>
            </ul>
          </div>
        <?php }; $renderList($income, __('Income'), $usage, false); $renderList($spend, __('Spending'), $usage, true); ?>
      </div>
    </div>
  </div>
</section>

<script>
(function(){
  const kind = document.getElementById('add-kind');
  const wrap = document.getElementById('add-rule-wrap');
  function sync(){ if(!wrap || !kind) return; wrap.style.display = (kind.value === 'spending') ? '' : 'none'; }
  if (kind && wrap){ sync(); kind.addEventListener('change', sync); }
})();
</script>
