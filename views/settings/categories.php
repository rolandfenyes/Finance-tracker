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
        <input name="label" class="input sm:col-span-3" placeholder="Label (e.g., Salary / Groceries)" required />
        <input name="color" type="color" value="#6B7280" class="h-10 w-16 rounded-xl border border-gray-300 sm:col-span-1" />
        <button class="btn btn-primary sm:col-span-6">Add</button>
      </form>

      <p class="help mt-2">These categories appear in the “Quick Add” and transaction forms.</p>
    </div>

    <!-- List -->
    <div>
      <h2 class="font-medium mb-2">Your categories</h2>
      <?php
        $income = array_values(array_filter($rows, fn($r)=>$r['kind']==='income'));
        $spend  = array_values(array_filter($rows, fn($r)=>$r['kind']==='spending'));
        $renderList = function($list,$title,$usage){
      ?>
        <div class="mb-5">
          <div class="text-sm font-semibold text-gray-600 mb-2"><?= htmlspecialchars($title) ?></div>
          <ul class="divide-y rounded-xl border">
            <?php if (!count($list)): ?>
              <li class="p-3 text-sm text-gray-500">No categories yet.</li>
            <?php else: foreach($list as $c): $used = (int)($usage[$c['id']] ?? 0); ?>
              <li class="p-3">
                <details class="group">
                  <summary class="flex items-center justify-between gap-3 cursor-pointer list-none">
                    <?php $dot = $c['color'] ?: '#6B7280'; $used = (int)($usage[$c['id']] ?? 0); ?>
                    <div class="flex items-center gap-3 min-w-0 group-open:hidden">
                      <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($dot) ?>;"></span>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2">
                          <span class="font-medium truncate"><?= htmlspecialchars($c['label']) ?></span>
                          <span class="chip"><?= $c['kind']==='income' ? 'Income' : 'Spending' ?></span>
                        </div>
                        <?php if ($used): ?>
                          <div class="text-xs text-gray-500"><?= $used ?> transaction<?= $used!==1?'s':'' ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="row-btn">Edit</span>
                  </summary>

                  <div class="edit-panel">
                    <!-- edit form here -->  
                    <form class="grid gap-3 sm:grid-cols-12 items-end" method="post" action="/settings/categories/edit">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="id" value="<?= $c['id'] ?>" />

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
                        <div class="flex items-center gap-2">
                          <input name="color" type="color" value="<?= htmlspecialchars($dot) ?>" class="color-input" />
                        </div>
                      </div>

                      <div class="sm:col-span-12 flex gap-2 justify-end">
                        <button class="btn btn-primary">Save</button>
                        <form method="post" action="/settings/categories/delete"
                              onsubmit="return confirm('Delete this category? Transactions will remain without a category.');">
                          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                          <input type="hidden" name="id" value="<?= $c['id'] ?>" />
                          <button class="btn btn-danger">Remove</button>
                        </form>
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
