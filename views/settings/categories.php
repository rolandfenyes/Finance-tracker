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
        <button class="btn btn-primary sm:col-span-1">Add</button>
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
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>
                    <?php if ($used): ?>
                      <div class="text-xs text-gray-500"><?= $used ?> transaction<?= $used!==1?'s':'' ?></div>
                    <?php endif; ?>
                  </div>
                  <details class="w-64">
                    <summary class="cursor-pointer text-accent">Edit</summary>
                    <form class="mt-2 grid grid-cols-6 gap-2" method="post" action="/settings/categories/edit">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="id" value="<?= $c['id'] ?>" />
                      <select name="kind" class="select col-span-3">
                        <option value="income"   <?= $c['kind']==='income'?'selected':'' ?>>Income</option>
                        <option value="spending" <?= $c['kind']==='spending'?'selected':'' ?>>Spending</option>
                      </select>
                      <input name="label" value="<?= htmlspecialchars($c['label']) ?>" class="input col-span-3" />
                      <button class="btn btn-primary col-span-6">Save</button>
                    </form>
                    <form class="mt-2" method="post" action="/settings/categories/delete"
                          onsubmit="return confirm('Delete this category? Transactions will remain without a category.');">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="id" value="<?= $c['id'] ?>" />
                      <button class="btn btn-danger">Remove</button>
                    </form>
                  </details>
                </div>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      <?php }; $renderList($income,'Income',$usage); $renderList($spend,'Spending',$usage); ?>
    </div>
  </div>
</section>
