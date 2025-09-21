<section class="max-w-4xl mx-auto">
  <div class="bg-white rounded-2xl shadow-glass p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold">Step 5 · Categories</h1>
        <p class="text-sm text-gray-600 mt-1">
          Categories help you group transactions (e.g., Salary, Groceries, Rent).
          We’ve suggested some below—you can edit, add, or remove them.
        </p>
      </div>
      <a href="/onboard/next" class="text-sm text-accent">Skip for now →</a>
    </div>

    <?php if (!empty($_SESSION['flash_ok']) || !empty($_SESSION['flash_err'])): ?>
        <p class="mt-3 text-sm <?= !empty($_SESSION['flash_ok']) ? 'text-emerald-700' : 'text-red-600' ?>">
            <?= $_SESSION['flash_ok'] ?? $_SESSION['flash_err']; unset($_SESSION['flash_ok'], $_SESSION['flash_err']); ?>
        </p>
    <?php endif; ?>


    <?php
      // Split rows into income/spending safely
      $list   = $rows ?? [];
      $income = array_values(array_filter($list, fn($r) => ($r['kind'] ?? '') === 'income'));
      $spend  = array_values(array_filter($list, fn($r) => ($r['kind'] ?? '') === 'spending'));
    ?>

    <div class="mt-6 grid md:grid-cols-2 gap-6">
      <!-- Add -->
      <div>
        <h2 class="font-medium mb-2">Add category</h2>
        <form method="post" action="/onboard/categories/add" class="grid sm:grid-cols-6 gap-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <select name="kind" class="select sm:col-span-2">
            <option value="income">Income</option>
            <option value="spending">Spending</option>
          </select>
          <input name="label" class="input sm:col-span-3" placeholder="Label (e.g., Salary)" required />
          <input name="color" type="color" value="#6B7280" class="h-10 w-16 rounded-xl border border-gray-300" />
          <div class="sm:col-span-6 flex justify-end">
            <button class="btn btn-primary">Add</button>
          </div>
        </form>
      </div>

      <!-- Lists -->
      <div>
        <h2 class="font-medium mb-2">Your categories</h2>

        <!-- Income -->
        <div class="mb-5">
          <div class="text-sm font-semibold text-gray-600 mb-2">Income</div>
          <ul class="divide-y rounded-xl border">
            <?php if (empty($income)): ?>
              <li class="p-4 text-sm text-gray-500">No income categories yet.</li>
            <?php else: foreach ($income as $c): ?>
              <li class="p-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>"></span>
                  <div>
                    <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>
                    <div class="text-xs text-gray-500">Income</div>
                  </div>
                </div>
                <form method="post" action="/onboard/categories/delete"
                      onsubmit="return confirm('Delete this category?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button class="btn btn-danger !py-1.5 !px-3">Remove</button>
                </form>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>

        <!-- Spending -->
        <div>
          <div class="text-sm font-semibold text-gray-600 mb-2">Spending</div>
          <ul class="divide-y rounded-xl border">
            <?php if (empty($spend)): ?>
              <li class="p-4 text-sm text-gray-500">No spending categories yet.</li>
            <?php else: foreach ($spend as $c): ?>
              <li class="p-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>"></span>
                  <div>
                    <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>
                    <div class="text-xs text-gray-500">Spending</div>
                  </div>
                </div>
                <form method="post" action="/onboard/categories/delete"
                      onsubmit="return confirm('Delete this category?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button class="btn btn-danger !py-1.5 !px-3">Remove</button>
                </form>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>

        <div class="mt-4 flex justify-end">
          <a href="/onboard/next" class="btn">Next step</a>
        </div>
      </div>
    </div>
  </div>
</section>
