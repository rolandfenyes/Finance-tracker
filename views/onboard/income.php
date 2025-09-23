<?php include __DIR__ . '/_progress.php'; ?>

<?php
$flashMessage = $_SESSION['flash'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_type']);
$rows = $rows ?? [];
$userCurrencies = $userCurrencies ?? [];
$categories = $categories ?? [];
$mainCurrency = $mainCurrency ?? ($userCurrencies[0]['code'] ?? '');
$normalizedMain = strtoupper($mainCurrency);
$totalMain = 0.0;
foreach ($rows as $row) {
  $rowCur = strtoupper($row['currency'] ?? '');
  if ($rowCur === '' && $normalizedMain !== '') {
    $rowCur = $normalizedMain;
  }
  if ($normalizedMain === '' || $rowCur === $normalizedMain) {
    $totalMain += (float)($row['amount'] ?? 0);
  }
}
$formattedTotal = moneyfmt($totalMain, $normalizedMain ?: ($userCurrencies[0]['code'] ?? ''));
?>

<section class="max-w-6xl mx-auto space-y-8">
  <div class="card grid gap-6 md:grid-cols-2">
    <div>
      <div class="card-kicker"><?= __('Lock in your paydays') ?></div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
        <?= __('Add your recurring income') ?>
      </h1>
      <p class="mt-3 text-base text-gray-600 dark:text-gray-300 leading-relaxed">
        <?= __('Tell MyMoneyMap about the money you count on—salary, stipends, side hustles. We use these numbers to power cashflow rules, savings automations, and projections.') ?>
      </p>
      <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="calendar-check" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Add each recurring source once—update the amount later if it changes.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="tag" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Optional: tag a category to keep reports tidy.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i></span>
          <span><?= __('We’ll celebrate your total monthly inflow in your main currency (:currency).', ['currency' => $normalizedMain ?: __('N/A')]) ?></span>
        </li>
      </ul>
    </div>
    <div class="self-stretch rounded-3xl border border-brand-200/60 bg-brand-50/40 p-5 text-sm text-brand-700 shadow-inner dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-100">
      <h2 class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-600 dark:text-brand-200"><?= __('Monthly inflow snapshot') ?></h2>
      <p class="mt-3 text-2xl font-semibold" data-income-total><?= $formattedTotal ?></p>
      <p class="mt-2 leading-relaxed text-brand-700/80 dark:text-brand-200/90">
        <?= __('Based on incomes entered in :currency. Add more below to update this total.', ['currency' => $normalizedMain ?: __('your main currency')]) ?>
      </p>
      <p class="mt-4 text-xs text-brand-700/70 dark:text-brand-200/70">
        <?= __('Tip: If your income arrives in another currency, add it anyway—we keep track alongside your currency list.') ?>
      </p>
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
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= __('Add an income source') ?></h2>
      <form method="post" action="/onboard/income" class="grid gap-3 md:grid-cols-12" id="income-form">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div class="md:col-span-6">
          <label class="label" for="income-label"><?= __('Name') ?></label>
          <input name="label" id="income-label" class="input" placeholder="<?= __('e.g. Salary, Freelance project') ?>" required />
        </div>
        <div class="md:col-span-6">
          <label class="label" for="income-amount"><?= __('Amount per month') ?></label>
          <div class="input-group">
            <input name="amount" id="income-amount" type="number" step="0.01" min="0" class="ig-input" placeholder="0.00" required data-income-amount />
            <select name="currency" id="income-currency" class="ig-select" data-income-currency>
              <?php foreach ($userCurrencies as $uc):
                $code = htmlspecialchars($uc['code']);
                $selected = !empty($uc['is_main']) ? 'selected' : '';
              ?>
                <option value="<?= $code ?>" <?= $selected ?>><?= $code ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="help"><?= __('Choose from currencies you added earlier.') ?></p>
        </div>
        <div class="md:col-span-6">
          <label class="label" for="income-category"><?= __('Category (optional)') ?></label>
          <select name="category_id" id="income-category" class="select">
            <option value=""><?= __('No category') ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-6">
          <label class="label" for="income-valid-from"><?= __('Valid from') ?></label>
          <input name="valid_from" id="income-valid-from" type="date" class="input" value="<?= date('Y-m-d') ?>" />
        </div>
        <div class="md:col-span-12 flex items-center justify-end gap-3">
          <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
            <?= __('Add each source once—use Settings later for raises or bonuses.') ?>
          </span>
          <button class="btn btn-primary">
            <?= __('Save income') ?>
          </button>
        </div>
      </form>
    </div>

    <div class="card space-y-4 lg:col-span-2">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= __('Your incomes') ?></h2>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            <?= __(':count saved so far', ['count' => count($rows)]) ?>
          </p>
        </div>
        <span class="chip"><?= __('Main currency: :code', ['code' => $normalizedMain ?: __('N/A')]) ?></span>
      </div>
      <ul class="glass-stack">
        <?php if (!count($rows)): ?>
          <li class="glass-stack__item text-sm text-gray-500 dark:text-gray-300">
            <?= __('No incomes yet. Add your primary salary to continue.') ?>
          </li>
        <?php else: foreach ($rows as $r): ?>
          <li class="glass-stack__item flex items-center justify-between gap-3">
            <div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($r['label']) ?></div>
              <div class="text-xs text-gray-600 dark:text-gray-300">
                <?= moneyfmt($r['amount'], $r['currency']) ?>
                <?php if (!empty($r['cat_label'])): ?> · <?= htmlspecialchars($r['cat_label']) ?><?php endif; ?>
                <?php if (!empty($r['valid_from'])): ?> · <?= __('since :date', ['date' => htmlspecialchars($r['valid_from'])]) ?><?php endif; ?>
              </div>
            </div>
            <form method="post" action="/onboard/income/delete" onsubmit="return confirm('<?= addslashes(__('Remove this income?')) ?>')">
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
    </div>
  </div>

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <a href="/onboard/categories" class="btn btn-ghost">
      <?= __('Back to categories') ?>
    </a>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
        <?= __('Once you’re happy, continue to celebrate your setup!') ?>
      </span>
      <a href="/onboard/next" class="btn btn-primary">
        <?= __('Finish onboarding') ?>
      </a>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const amountInput = document.querySelector('[data-income-amount]');
    const currencySelect = document.querySelector('[data-income-currency]');
    const totalEl = document.querySelector('[data-income-total]');
    if (!amountInput || !currencySelect || !totalEl) return;

    const baseTotal = <?= json_encode($totalMain) ?>;
    const mainCode = <?= json_encode($normalizedMain) ?>;
    const formatter = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function refreshTotal() {
      const inputValue = parseFloat(amountInput.value);
      const selectedCode = (currencySelect.value || '').toUpperCase();
      let displayTotal = baseTotal;
      if (!Number.isNaN(inputValue) && selectedCode === mainCode) {
        displayTotal += inputValue;
      }
      const formatted = formatter.format(displayTotal) + (mainCode ? ` ${mainCode}` : '');
      totalEl.textContent = formatted;
    }

    amountInput.addEventListener('input', refreshTotal);
    currencySelect.addEventListener('change', refreshTotal);
  });
</script>
