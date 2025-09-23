<?php include __DIR__ . '/_progress.php'; ?>

<?php
$list   = $rows ?? [];
$income = array_values(array_filter($list, fn($r) => ($r['kind'] ?? '') === 'income'));
$spend  = array_values(array_filter($list, fn($r) => ($r['kind'] ?? '') === 'spending'));
$existingLabels = array_map(fn($r) => strtolower(trim($r['label'] ?? '')), $list);
$flashMessage = $_SESSION['flash'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_type']);
$csrfToken = csrf_token();
?>

<section class="max-w-6xl mx-auto space-y-8">
  <div class="card grid gap-6 md:grid-cols-2">
    <div>
      <div class="card-kicker"><?= __('Design your budget') ?></div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
        <?= __('Create your categories') ?>
      </h1>
      <p class="mt-3 text-base text-gray-600 dark:text-gray-300 leading-relaxed">
        <?= __('Categories are the folders that keep your spending and income organised. Start with a few essentials—add more later as you learn how money flows through your life.') ?>
      </p>
      <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="bookmark-plus" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Create both income and spending categories for clear reports.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="rainbow" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Pick colours to make charts easy to scan at a glance.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Need inspiration? Use the quick-add suggestions to jump-start your list.') ?></span>
        </li>
      </ul>
    </div>
    <div class="self-stretch rounded-3xl border border-dashed border-brand-200/60 bg-brand-50/40 p-5 text-sm text-brand-700 shadow-inner dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
      <h2 class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-600 dark:text-brand-200"><?= __('Suggested starter set') ?></h2>
      <p class="mt-3 leading-relaxed">
        <?= __('Use the buttons below to add popular categories instantly. We’ll colour code them so your dashboard looks polished from day one.') ?>
      </p>
      <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
        <div>
          <div class="font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-200 mb-2"><?= __('Income') ?></div>
          <div class="flex flex-col gap-2">
            <?php foreach ($suggestedIncome ?? [] as $suggest):
              $label = $suggest['label'];
              $exists = in_array(strtolower($label), $existingLabels, true);
            ?>
              <button type="button" class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/70 px-3 py-2 text-left text-sm font-medium text-gray-700 transition <?= $exists ? 'opacity-40 cursor-not-allowed' : 'hover:-translate-y-0.5 hover:shadow-md' ?> dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-gray-200" data-category-template data-kind="income" data-label="<?= htmlspecialchars($label) ?>" data-color="<?= htmlspecialchars($suggest['color']) ?>" <?= $exists ? 'disabled' : '' ?>>
                <span><?= htmlspecialchars($label) ?></span>
                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <div class="font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-200 mb-2"><?= __('Spending') ?></div>
          <div class="flex flex-col gap-2">
            <?php foreach ($suggestedSpending ?? [] as $suggest):
              $label = $suggest['label'];
              $exists = in_array(strtolower($label), $existingLabels, true);
            ?>
              <button type="button" class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/70 px-3 py-2 text-left text-sm font-medium text-gray-700 transition <?= $exists ? 'opacity-40 cursor-not-allowed' : 'hover:-translate-y-0.5 hover:shadow-md' ?> dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-gray-200" data-category-template data-kind="spending" data-label="<?= htmlspecialchars($label) ?>" data-color="<?= htmlspecialchars($suggest['color']) ?>" <?= $exists ? 'disabled' : '' ?>>
                <span><?= htmlspecialchars($label) ?></span>
                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($flashMessage): ?>
    <div class="rounded-3xl border <?= $flashType === 'success' ? 'border-emerald-300/70 bg-emerald-50/70 text-emerald-700' : 'border-rose-300/70 bg-rose-50/70 text-rose-700' ?> px-4 py-3 text-sm shadow-sm">
      <div class="flex items-start gap-3">
        <i data-lucide="<?= $flashType === 'success' ? 'check-circle2' : 'alert-triangle' ?>" class="mt-0.5 h-4 w-4"></i>
        <span><?= htmlspecialchars($flashMessage) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid gap-6 lg:grid-cols-5">
    <div class="card space-y-6 lg:col-span-3">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= __('Add a custom category') ?></h2>
      <form method="post" action="/onboard/categories/add" class="grid gap-3 md:grid-cols-12" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $csrfToken ?>" />
        <div class="md:col-span-4">
          <label class="label" for="cat-kind"><?= __('Type') ?></label>
          <select name="kind" id="cat-kind" class="select">
            <option value="income"><?= __('Income') ?></option>
            <option value="spending"><?= __('Spending') ?></option>
          </select>
        </div>
        <div class="md:col-span-6">
          <label class="label" for="cat-label"><?= __('Name') ?></label>
          <input name="label" id="cat-label" class="input" placeholder="<?= __('e.g. Groceries or Salary') ?>" required />
        </div>
        <div class="md:col-span-2">
          <label class="label" for="cat-color"><?= __('Colour') ?></label>
          <input name="color" id="cat-color" type="color" value="#6B7280" class="color-input" />
        </div>
        <div class="md:col-span-12 flex items-center justify-end gap-3">
          <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
            <?= __('Keep it focused—3-6 categories per group is a great start.') ?>
          </span>
          <button class="btn btn-primary">
            <?= __('Add category') ?>
          </button>
        </div>
      </form>

      <form id="quick-category-form" method="post" action="/onboard/categories/add" class="hidden">
        <input type="hidden" name="csrf" value="<?= $csrfToken ?>" />
        <input type="hidden" name="kind" value="" />
        <input type="hidden" name="label" value="" />
        <input type="hidden" name="color" value="" />
      </form>
    </div>

    <div class="card space-y-5 lg:col-span-2">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= __('Your categories') ?></h2>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            <?= __(':income income · :spending spending', ['income' => count($income), 'spending' => count($spend)]) ?>
          </p>
        </div>
        <span class="chip"><?= __(':total total', ['total' => count($list)]) ?></span>
      </div>

      <div class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300 mb-2"><?= __('Income') ?></h3>
          <ul class="glass-stack">
            <?php if (!count($income)): ?>
              <li class="glass-stack__item text-sm text-gray-500 dark:text-gray-300">
                <?= __('No income categories yet. Add at least one so you can tag salaries or freelance work.') ?>
              </li>
            <?php else: foreach ($income as $c): ?>
              <li class="glass-stack__item flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>"></span>
                  <span class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($c['label']) ?></span>
                </div>
                <form method="post" action="/onboard/categories/delete" onsubmit="return confirm('<?= addslashes(__('Delete this category?')) ?>');">
                  <input type="hidden" name="csrf" value="<?= $csrfToken ?>" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                    <span class="sr-only"><?= __('Remove') ?></span>
                  </button>
                </form>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300 mb-2"><?= __('Spending') ?></h3>
          <ul class="glass-stack">
            <?php if (!count($spend)): ?>
              <li class="glass-stack__item text-sm text-gray-500 dark:text-gray-300">
                <?= __('No spending categories yet. Add a few to start tracking where money goes.') ?>
              </li>
            <?php else: foreach ($spend as $c): ?>
              <li class="glass-stack__item flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <span class="inline-block h-3 w-3 rounded-full" style="background: <?= htmlspecialchars($c['color'] ?? '#6B7280') ?>"></span>
                  <span class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($c['label']) ?></span>
                </div>
                <form method="post" action="/onboard/categories/delete" onsubmit="return confirm('<?= addslashes(__('Delete this category?')) ?>');">
                  <input type="hidden" name="csrf" value="<?= $csrfToken ?>" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                    <span class="sr-only"><?= __('Remove') ?></span>
                  </button>
                </form>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <a href="/onboard/currencies" class="btn btn-ghost">
      <?= __('Back to currencies') ?>
    </a>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
        <?= __('You can fine-tune categories anytime from Settings → Categories.') ?>
      </span>
      <a href="/onboard/next" class="btn btn-primary">
        <?= __('Continue to income') ?>
      </a>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const quickForm = document.getElementById('quick-category-form');
    if (!quickForm) return;
    const kindField = quickForm.querySelector('[name="kind"]');
    const labelField = quickForm.querySelector('[name="label"]');
    const colorField = quickForm.querySelector('[name="color"]');

    document.querySelectorAll('[data-category-template]').forEach(button => {
      button.addEventListener('click', () => {
        if (button.disabled) return;
        const kind = button.getAttribute('data-kind');
        const label = button.getAttribute('data-label');
        const color = button.getAttribute('data-color');
        if (!kind || !label) return;
        kindField.value = kind;
        labelField.value = label;
        colorField.value = color || '#6B7280';
        quickForm.submit();
      });
    });
  });
</script>
