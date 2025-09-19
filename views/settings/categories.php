<section class="max-w-4xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold"><?= htmlspecialchars(__('settings.categories.title')) ?></h1>
    <a href="/settings" class="text-sm text-accent"><?= htmlspecialchars(__('settings.common.back')) ?></a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <!-- Add -->
    <div>
      <h2 class="font-medium mb-2"><?= htmlspecialchars(__('settings.categories.add_heading')) ?></h2>
      <form method="post" action="/settings/categories/add" class="grid sm:grid-cols-6 gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <select name="kind" class="select sm:col-span-2">
          <option value="income"><?= htmlspecialchars(__('settings.categories.section_income')) ?></option>
          <option value="spending"><?= htmlspecialchars(__('settings.categories.section_spending')) ?></option>
        </select>
        <input name="label" class="input sm:col-span-2" placeholder="<?= htmlspecialchars(__('settings.categories.label_placeholder')) ?>" required />
        <select name="cashflow_rule_id" class="select sm:col-span-2">
          <option value=""><?= htmlspecialchars(__('settings.categories.no_rule')) ?></option>
          <?php foreach ($rules as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="color" type="color" value="#6B7280" class="h-10 w-16 rounded-xl border border-gray-300 sm:col-span-1" />
        <button class="btn btn-primary sm:col-span-5"><?= htmlspecialchars(__('common.add')) ?></button>
      </form>


      <p class="help mt-2"><?= htmlspecialchars(__('settings.categories.help')) ?></p>
    </div>

    <!-- List -->
    <div>
      <h2 class="font-medium mb-2"><?= htmlspecialchars(__('settings.categories.list_title')) ?></h2>
      <?php
        $income = array_values(array_filter($rows, fn($r)=>$r['kind']==='income'));
        $spend  = array_values(array_filter($rows, fn($r)=>$r['kind']==='spending'));
        $renderList = function($list,$title,$usage) use ($rules){
      ?>
        <div class="mb-5">
          <div class="text-sm font-semibold text-gray-600 mb-2"><?= htmlspecialchars($title) ?></div>
          <ul class="divide-y rounded-xl border">
            <?php if (!count($list)): ?>
              <li class="p-3 text-sm text-gray-500"><?= htmlspecialchars(__('settings.categories.empty')) ?></li>
            <?php else: foreach($list as $c): $used = (int)($usage[$c['id']] ?? 0); ?>
              <li class="p-3">
                <details class="group">
                  <summary class="flex items-center justify-between gap-3 cursor-pointer list-none">
                    <?php
                      $ruleName = null;
                      if (!empty($rules)) {
                        foreach ($rules as $rul) { if ((int)$rul['id'] === (int)($c['cashflow_rule_id'] ?? 0)) { $ruleName = $rul['label']; break; } }
                      }
                    ?>
                    <div class="flex items-center gap-2">
                      <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>;"></span>
                      <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>
                      <?php if ($ruleName): ?>
                        <span class="chip"><?= htmlspecialchars($ruleName) ?></span>
                      <?php endif; ?>
                    </div>

                    <span class="row-btn"><?= htmlspecialchars(__('common.edit')) ?></span>
                  </summary>

                  <div class="edit-panel">
                    <!-- edit form here -->
                    <form class="grid gap-3 sm:grid-cols-12 items-end" method="post" action="/settings/categories/edit">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="id" value="<?= $c['id'] ?>" />

                      <div class="field sm:col-span-3">
                        <label class="label"><?= htmlspecialchars(__('settings.categories.type')) ?></label>
                        <select name="kind" class="select">
                          <option value="income"   <?= $c['kind']==='income'?'selected':'' ?>><?= htmlspecialchars(__('settings.categories.section_income')) ?></option>
                          <option value="spending" <?= $c['kind']==='spending'?'selected':'' ?>><?= htmlspecialchars(__('settings.categories.section_spending')) ?></option>
                        </select>
                      </div>

                      <div class="field sm:col-span-6">
                        <label class="label"><?= htmlspecialchars(__('settings.categories.label')) ?></label>
                        <input name="label" value="<?= htmlspecialchars($c['label']) ?>" class="input" />
                      </div>

                      <div class="field sm:col-span-3">
                        <label class="label"><?= htmlspecialchars(__('settings.categories.color')) ?></label>
                        <div class="flex items-center gap-2">
                          <input name="color" type="color"
                                value="<?= htmlspecialchars($dot) ?>"
                                class="h-10 w-16 rounded-xl border border-gray-300 col-span-1" />

                        </div>
                      </div>

                      <select name="cashflow_rule_id" class="select col-span-6">
                        <option value=""><?= htmlspecialchars(__('settings.categories.no_rule')) ?></option>
                        <?php foreach ($rules as $r): ?>
                          <option value="<?= (int)$r['id'] ?>"
                            <?= ((int)($c['cashflow_rule_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['label']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>


                      <div class="sm:col-span-12 flex gap-2 justify-end">
                        <button class="btn btn-primary"><?= htmlspecialchars(__('common.save')) ?></button>
                        <form method="post" action="/settings/categories/delete"
                              onsubmit="return confirm(<?= json_encode(__('settings.categories.delete_confirm')) ?>);">
                          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                          <input type="hidden" name="id" value="<?= $c['id'] ?>" />
                          <button class="btn btn-danger"><?= htmlspecialchars(__('common.remove')) ?></button>
                        </form>
                      </div>
                    </form>
                  </div>
                </details>
              </li>



            <?php endforeach; endif; ?>
          </ul>
        </div>
      <?php };
      $renderList($income, __('settings.categories.section_income'), $usage);
      $renderList($spend, __('settings.categories.section_spending'), $usage);
      ?>
    </div>
  </div>
</section>
