<section class="max-w-3xl mx-auto">
  <div class="card">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold"><?= __('Step :step · Basic income', ['step' => 4]) ?></h1>
        <p class="text-sm text-gray-600 mt-1">
          <?= __('Add your recurring income (e.g., salary, stipend).') ?>
          <?= __('You can add more than one, choose currencies, and optionally tag a category for reporting.') ?>
        </p>
      </div>
      <a href="/onboard/next" class="text-sm text-accent"><?= __('Skip for now →') ?></a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-3 text-red-600 text-sm"><?=$_SESSION['flash']; unset($_SESSION['flash']);?></p>
    <?php endif; ?>

    <div class="mt-6 grid md:grid-cols-2 gap-6">
      <!-- Add income -->
      <div>
        <h2 class="font-medium mb-2"><?= __('Add an income') ?></h2>
        <form method="post" action="/onboard/income" class="grid sm:grid-cols-12 gap-3">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <div class="sm:col-span-7">
            <label class="label"><?= __('Name') ?></label>
            <input name="label" class="input" placeholder="<?= __('e.g., Salary') ?>" required />
          </div>
          <div class="sm:col-span-5">
            <label class="label"><?= __('Amount / month') ?></label>
            <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
          </div>

          <div class="sm:col-span-6">
            <label class="label"><?= __('Currency') ?></label>
            <!-- same selector pattern you use elsewhere -->
            <div class="relative">
              <input type="text" class="input pr-10" placeholder="<?= __('Filter currency…') ?>" oninput="
                const q=this.value.toUpperCase();
                this.nextElementSibling.querySelectorAll('option').forEach(o=>{
                  o.hidden = q && !o.value.toUpperCase().includes(q);
                });
              ">
              <select name="currency" class="select mt-2">
                <?php foreach ($userCurrencies as $uc): ?>
                  <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                    <?= htmlspecialchars($uc['code']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="help"><?= __('Don’t see your currency? Add it in the previous step.') ?></p>
          </div>

          <div class="sm:col-span-6">
            <label class="label"><?= __('Category (optional)') ?></label>
            <select name="category_id" class="select">
              <option value=""><?= __('No category') ?></option>
              <?php foreach($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="sm:col-span-6">
            <label class="label"><?= __('Valid from') ?></label>
            <input name="valid_from" type="date" class="input" value="<?= date('Y-m-d') ?>" />
          </div>

          <div class="sm:col-span-12 flex justify-end">
            <button class="btn btn-primary"><?= __('Save & Continue') ?></button>
          </div>
        </form>
      </div>

      <!-- Existing incomes -->
      <div>
        <h2 class="font-medium mb-2"><?= __('Your incomes') ?></h2>
        <ul class="divide-y rounded-xl border">
          <?php if (!count($rows)): ?>
            <li class="p-4 text-sm text-gray-500"><?= __('None yet.') ?></li>
          <?php else: foreach ($rows as $r): ?>
            <li class="p-4 flex items-center justify-between gap-3">
              <div>
                <div class="font-medium"><?= htmlspecialchars($r['label']) ?></div>
                <div class="text-xs text-gray-600">
                  <?= moneyfmt($r['amount'], $r['currency']) ?>
                  <?php if (!empty($r['cat_label'])): ?> · <?= htmlspecialchars($r['cat_label']) ?><?php endif; ?>
                  <?php if (!empty($r['valid_from'])): ?> · <?= __('since :date', ['date' => htmlspecialchars($r['valid_from'])]) ?><?php endif; ?>
                </div>
              </div>
              <form method="post" action="/onboard/income/delete"
                    onsubmit="return confirm('<?= addslashes(__('Remove this income?')) ?>')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                  <i data-lucide="trash-2" class="h-4 w-4"></i>
                  <span class="sr-only"><?= __('Remove') ?></span>
                </button>
              </form>
            </li>
          <?php endforeach; endif; ?>
        </ul>

        <div class="mt-4 flex justify-end">
          <a href="/onboard/next" class="btn"><?= __('Next step') ?></a>
        </div>
      </div>
    </div>
  </div>
</section>
