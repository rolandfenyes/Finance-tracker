<section class="max-w-2xl mx-auto card">
  <h1 class="text-lg font-semibold"><?= __('Cashflow rules') ?></h1>
  <p class="text-sm text-gray-600 mt-1">
    <?= __('Decide how your income is split automatically (e.g., 50/30/20).') ?>
    <?= __('You can change this later.') ?>
  </p>

  <div class="mt-3 grid gap-2 sm:grid-cols-3">
    <div class="rounded-xl border p-3">
      <div class="font-medium"><?= __('Needs') ?></div>
      <p class="text-xs text-gray-500"><?= __('Rent, utilities, groceries') ?></p>
      <span class="chip"><?= __('Suggestion: :percent%', ['percent' => 50]) ?></span>
    </div>
    <div class="rounded-xl border p-3">
      <div class="font-medium"><?= __('Wants') ?></div>
      <p class="text-xs text-gray-500"><?= __('Eating out, travel') ?></p>
      <span class="chip"><?= __('Suggestion: :percent%', ['percent' => 30]) ?></span>
    </div>
    <div class="rounded-xl border p-3">
      <div class="font-medium"><?= __('Savings/Debt') ?></div>
      <p class="text-xs text-gray-500"><?= __('Emergency fund & loans') ?></p>
      <span class="chip"><?= __('Suggestion: :percent%', ['percent' => 20]) ?></span>
    </div>
  </div>

  <form method="post" action="/onboard/rules" class="mt-4 space-y-3">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <!-- Minimal 3 rows, editable -->
    <?php foreach ([[__('Needs'),50],[__('Wants'),30],[__('Savings/Debt'),20]] as $i=>$s): ?>
      <div class="grid grid-cols-12 gap-2 items-end">
        <div class="col-span-8">
          <label class="label"><?= __('Label') ?></label>
          <input class="input w-full" name="rules[<?= $i ?>][label]" value="<?= htmlspecialchars($s[0]) ?>" />
        </div>
        <div class="col-span-4">
          <label class="label"><?= __('% of income') ?></label>
          <input class="input w-full" type="number" step="0.1" name="rules[<?= $i ?>][percent]" value="<?= (float)$s[1] ?>">
        </div>
      </div>
    <?php endforeach; ?>

    <div class="flex justify-end gap-2">
      <a class="btn" href="/login"><?= __('Cancel') ?></a>
      <button class="btn btn-primary"><?= __('Continue') ?></button>
    </div>
  </form>
</section>
