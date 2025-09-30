<?php
/** @var array $plans */
/** @var array|null $currentPlan */
/** @var array $planItems */
/** @var array $planCategoryLimits */
/** @var float $planCategoryTotal */
/** @var float $planFreeAfterLimits */
/** @var float $planReservedFree */
/** @var array $planMonthlyBreakdown */
/** @var string $mainCurrency */
/** @var string $startSuggestion */
/** @var string $startParam */
/** @var int $defaultHorizon */
/** @var int $averageMonths */
/** @var array $averageMonthsOptions */
/** @var int|null $selectedPlanId */
/** @var array $incomeData */
/** @var array $spendingCategories */
/** @var array $averages */
/** @var array $resources */
/** @var array $difficultyOptions */
/** @var array $difficultyConfig */
/** @var array $cashflowRules */
/** @var string $defaultDifficulty */
/** @var array $initialCategorySuggestions */
/** @var float $reservedFreePreview */
?>

<section class="card">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-white"><?= __('Advanced planner') ?></h1>
      <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
        <?= __('Design a focused financial roadmap for the next quarter, half-year, or full year. Capture milestones, estimate monthly allocations, and tune your category budgets before making the plan live.') ?>
      </p>
    </div>
    <?php if (!empty($plans)): ?>
      <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 text-sm shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/60">
        <div class="font-semibold text-slate-800 dark:text-slate-100"><?= __('Saved plans') ?></div>
        <div class="mt-1 text-slate-500 dark:text-slate-400">
          <?= __(':count plans total', ['count' => count($plans)]) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-4 rounded-2xl bg-brand-50/70 px-4 py-3 text-sm text-brand-700 shadow-sm dark:bg-brand-500/15 dark:text-brand-100">
      <?= htmlspecialchars($_SESSION['flash'], ENT_QUOTES) ?>
      <?php unset($_SESSION['flash']); ?>
    </p>
  <?php endif; ?>
</section>

<section class="mt-6 card">
  <div class="card-kicker"><?= __('Baseline insights') ?></div>
  <h2 class="card-title">
    <?= __('Starting point for :month', ['month' => date('F Y', strtotime($incomeData['month'] ?? ($startSuggestion . '-01')))]) ?>
  </h2>
  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur-sm dark:border-slate-800/60 dark:bg-slate-900/60">
      <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Reliable income') ?></h3>
      <div class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white">
        <?= moneyfmt($incomeData['total'] ?? 0, $mainCurrency) ?>
      </div>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        <?= __('Active basic incomes converted to your main currency.') ?>
      </p>
      <ul class="mt-4 space-y-2 text-sm">
        <?php if (!empty($incomeData['sources'])): ?>
          <?php foreach ($incomeData['sources'] as $source): ?>
            <li class="flex items-start justify-between gap-3 rounded-2xl border border-white/50 bg-white/60 px-3 py-2 text-slate-600 shadow-sm backdrop-blur-sm dark:border-slate-800/70 dark:bg-slate-900/50 dark:text-slate-300">
              <span class="font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($source['label'], ENT_QUOTES) ?></span>
              <span class="text-right">
                <span class="block text-xs text-slate-400 dark:text-slate-500"><?= moneyfmt($source['amount'], $source['currency']) ?></span>
                <span class="block font-semibold text-slate-700 dark:text-slate-200"><?= moneyfmt($source['converted'], $mainCurrency) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="rounded-2xl border border-dashed border-slate-300/60 px-3 py-3 text-slate-500 dark:border-slate-700 dark:text-slate-400">
            <?= __('No recurring incomes captured yet. Add them under Settings → Basic incomes for more precise planning.') ?>
          </li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-sm backdrop-blur-sm dark:border-slate-800/60 dark:bg-slate-900/60">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Recent spending averages') ?></h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= __('Based on :months months of history (:from → :to).', [
              'months' => $averages['months'] ?? 0,
              'from' => date('M Y', strtotime($averages['window_start'] ?? $startSuggestion . '-01')),
              'to' => date('M Y', strtotime($averages['window_end'] ?? $startSuggestion . '-01')),
            ]) ?>
          </p>
        </div>
        <form method="get" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300" data-average-form>
          <?php if (!empty($selectedPlanId)): ?>
            <input type="hidden" name="plan" value="<?= (int)$selectedPlanId ?>" />
          <?php elseif (!empty($_GET['plan'])): ?>
            <input type="hidden" name="plan" value="<?= (int)$_GET['plan'] ?>" />
          <?php endif; ?>
          <?php if (!empty($startParam)): ?>
            <input type="hidden" name="start" value="<?= htmlspecialchars($startParam, ENT_QUOTES) ?>" />
          <?php endif; ?>
          <label for="avg-months" class="whitespace-nowrap"><?= __('Look back') ?></label>
          <select id="avg-months" name="avg_months" class="select select-sm" data-average-select>
            <?php foreach ($averageMonthsOptions as $option): ?>
              <option value="<?= (int)$option ?>" <?= (int)$option === (int)$averageMonths ? 'selected' : '' ?>>
                <?= (int)$option === 1 ? __('1 month') : __(':count months', ['count' => (int)$option]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="mt-4 grid gap-2 sm:grid-cols-2">
        <?php if (!empty($averages['categories'])): ?>
          <?php foreach ($averages['categories'] as $cat): ?>
            <div class="rounded-2xl border border-white/50 bg-white/60 px-3 py-2 shadow-sm backdrop-blur-sm dark:border-slate-800/70 dark:bg-slate-900/50">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($cat['label'], ENT_QUOTES) ?></span>
                <span class="inline-flex h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($cat['color'], ENT_QUOTES) ?>"></span>
              </div>
              <div class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= __('Average') ?></div>
              <div class="text-base font-semibold text-slate-900 dark:text-white"><?= moneyfmt($cat['average'], $mainCurrency) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="rounded-2xl border border-dashed border-slate-300/60 px-3 py-3 text-slate-500 dark:border-slate-700 dark:text-slate-400">
            <?= __('No spending history yet. Once you record transactions we will surface category averages here.') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="mt-6 card">
  <div class="card-kicker"><?= __('Draft a new plan') ?></div>
  <h2 class="card-title"><?= __('Build your timeline') ?></h2>
  <form class="mt-6 space-y-6" method="post" action="/advanced-planner">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <input type="hidden" name="avg_months" value="<?= (int)$averageMonths ?>" />
    <div class="grid gap-4 md:grid-cols-2">
      <label class="block">
        <span class="label"><?= __('Plan title') ?></span>
        <input class="input" name="title" placeholder="<?= __('Quarterly wealth sprint') ?>" />
      </label>
      <label class="block">
        <span class="label"><?= __('Start month') ?></span>
        <input class="input" type="month" name="start_month" value="<?= htmlspecialchars($startSuggestion, ENT_QUOTES) ?>" required />
      </label>
      <label class="block">
        <span class="label"><?= __('Time horizon') ?></span>
        <select class="select" name="horizon_months" data-horizon-selector>
          <option value="3" <?= $defaultHorizon === 3 ? 'selected' : '' ?>><?= __('Next 3 months (quarter)') ?></option>
          <option value="6"><?= __('Next 6 months (half-year)') ?></option>
          <option value="12"><?= __('Next 12 months (full year)') ?></option>
        </select>
      </label>
      <label class="block">
        <span class="label"><?= __('Budget intensity') ?></span>
        <select class="select" name="difficulty_level" data-difficulty-selector>
          <?php foreach ($difficultyOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $key === $defaultDifficulty ? 'selected' : '' ?>>
              <?= htmlspecialchars($option['label'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" data-difficulty-description>
          <?= htmlspecialchars($difficultyOptions[$defaultDifficulty]['description'] ?? '', ENT_QUOTES) ?>
        </p>
      </label>
      <label class="block">
        <span class="label"><?= __('Notes (optional)') ?></span>
        <textarea class="textarea" name="notes" rows="3" placeholder="<?= __('Add context, assumptions, or reminders for future you.') ?>"></textarea>
      </label>
    </div>

    <div>
      <div class="flex items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Milestones & funding targets') ?></h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= __('Each milestone represents a goal, loan payoff, investment, or custom initiative to fund within the horizon.') ?>
          </p>
        </div>
        <button type="button" class="btn btn-muted" data-add-item>
          <i data-lucide="plus" class="h-4 w-4"></i>
          <span><?= __('Add milestone') ?></span>
        </button>
      </div>
      <div class="mt-4 space-y-3" data-items-container>
        <p class="rounded-2xl border border-dashed border-slate-300/70 px-4 py-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400" data-empty-state>
          <?= __('No milestones yet. Add them manually or pull in suggestions below.') ?>
        </p>
      </div>
    </div>

    <?php if (!empty($resources['emergency']) || !empty($resources['goals']) || !empty($resources['loans'])): ?>
      <div class="rounded-3xl border border-white/60 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Quick adds from your data') ?></h3>
        <div class="mt-3 grid gap-3 lg:grid-cols-3">
          <?php if (!empty($resources['emergency'])): $ef = $resources['emergency']; ?>
            <div class="panel p-4">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-100"><?= __('Emergency fund gap') ?></div>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                <?= __('Need :amount to reach the target.', ['amount' => moneyfmt($ef['remaining'], $ef['currency'])]) ?>
              </p>
              <button
                type="button"
                class="btn btn-primary mt-3 w-full"
                data-quick-add
                data-kind="emergency"
                data-label="<?= htmlspecialchars(__('Collect full emergency fund'), ENT_QUOTES) ?>"
                data-target="<?= htmlspecialchars($ef['remaining_main'], ENT_QUOTES) ?>"
                data-current="0"
                data-reference=""
                data-notes="<?= htmlspecialchars(__('Target gap: :amount', ['amount' => moneyfmt($ef['remaining'], $ef['currency'])]), ENT_QUOTES) ?>"
              >
                <?= __('Add to plan') ?>
              </button>
            </div>
          <?php endif; ?>

          <?php if (!empty($resources['goals'])): ?>
            <div class="panel p-4">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-100"><?= __('Goals nearing completion') ?></div>
              <ul class="mt-2 space-y-2 text-sm">
                <?php foreach ($resources['goals'] as $goal): ?>
                  <li class="rounded-2xl border border-white/60 bg-white/70 p-3 shadow-sm backdrop-blur-sm dark:border-slate-800/60 dark:bg-slate-900/60">
                    <div class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($goal['label'], ENT_QUOTES) ?></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                      <?= __('Need :amount', ['amount' => moneyfmt($goal['remaining'], $goal['currency'])]) ?>
                    </div>
                    <button
                      type="button"
                      class="btn btn-muted mt-2 w-full"
                      data-quick-add
                      data-kind="goal"
                      data-label="<?= htmlspecialchars(__('Finish goal: :name', ['name' => $goal['label']]), ENT_QUOTES) ?>"
                      data-target="<?= htmlspecialchars($goal['remaining_main'], ENT_QUOTES) ?>"
                      data-current="0"
                      data-reference="<?= (int)$goal['id'] ?>"
                      data-notes="<?= htmlspecialchars(__('Remaining native amount: :amount', ['amount' => moneyfmt($goal['remaining'], $goal['currency'])]), ENT_QUOTES) ?>"
                    >
                      <?= __('Add') ?>
                    </button>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($resources['loans'])): ?>
            <div class="panel p-4">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-100"><?= __('Loans to eliminate') ?></div>
              <ul class="mt-2 space-y-2 text-sm">
                <?php foreach ($resources['loans'] as $loan): ?>
                  <li class="rounded-2xl border border-white/60 bg-white/70 p-3 shadow-sm backdrop-blur-sm dark:border-slate-800/60 dark:bg-slate-900/60">
                    <div class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($loan['label'], ENT_QUOTES) ?></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                      <?= __('Balance :amount', ['amount' => moneyfmt($loan['balance'], $loan['currency'])]) ?>
                    </div>
                    <button
                      type="button"
                      class="btn btn-muted mt-2 w-full"
                      data-quick-add
                      data-kind="loan"
                      data-label="<?= htmlspecialchars(__('Pay off loan: :name', ['name' => $loan['label']]), ENT_QUOTES) ?>"
                      data-target="<?= htmlspecialchars($loan['balance_main'], ENT_QUOTES) ?>"
                      data-current="0"
                      data-reference="<?= (int)$loan['id'] ?>"
                      data-notes="<?= htmlspecialchars(__('Outstanding native balance: :amount', ['amount' => moneyfmt($loan['balance'], $loan['currency'])]), ENT_QUOTES) ?>"
                    >
                      <?= __('Add') ?>
                    </button>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="rounded-3xl border border-white/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/50">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Category budget suggestions') ?></h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= __('We anchor suggestions to your Cashflow Rules, scaling them to the leftover cash and chosen intensity.') ?>
          </p>
        </div>
        <div
          class="flex w-full max-w-xs flex-col items-end gap-1 text-right text-sm"
          data-budget-progress-wrapper
        >
          <span class="font-medium" data-budget-progress-label>
            <?= __('Allocated :spent of :available', [
              'spent' => moneyfmt(0, $mainCurrency),
              'available' => moneyfmt(max(0, ($incomeData['total'] ?? 0) - $reservedFreePreview), $mainCurrency),
            ]) ?>
          </span>
          <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
            <div
              class="h-full w-0 rounded-full bg-brand-500 transition-all duration-300 dark:bg-brand-400"
              data-budget-progress-bar
            ></div>
          </div>
          <span class="text-xs" data-leftover-preview>
            <?= __('Free to allocate after cushion: :amount/month', [
              'amount' => moneyfmt(max(0, ($incomeData['total'] ?? 0) - $reservedFreePreview), $mainCurrency),
            ]) ?>
          </span>
          <span class="text-xs text-slate-500 dark:text-slate-400" data-reserved-preview>
            <?= __('Reserved safety cushion (10%): :amount/month', [
              'amount' => moneyfmt($reservedFreePreview, $mainCurrency),
            ]) ?>
          </span>
        </div>
      </div>
      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-white/60 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800/60 dark:text-slate-400">
              <th class="py-2 pr-3"><?= __('Category') ?></th>
              <th class="py-2 pr-3"><?= __('Avg / month') ?></th>
              <th class="py-2 pr-3"><?= __('Suggested limit') ?></th>
            </tr>
          </thead>
          <tbody data-category-suggestions>
          <?php if (!empty($averages['categories'])): ?>
            <?php foreach ($averages['categories'] as $idx => $cat): ?>
              <?php
                $ruleId = $cat['cashflow_rule_id'] ?? null;
                $initialSuggestion = $initialCategorySuggestions[$idx]['suggested'] ?? ($cat['average'] ?? 0);
                $scheduledAmount = $initialCategorySuggestions[$idx]['scheduled'] ?? ($cat['scheduled_average'] ?? 0);
                $lockedMin = $initialCategorySuggestions[$idx]['locked_min'] ?? 0;
                $isLocked = !empty($initialCategorySuggestions[$idx]['locked']);
              ?>
              <tr
                class="border-b border-white/50 dark:border-slate-800/50"
                data-category-row
                data-rule-id="<?= $ruleId ? (int)$ruleId : '' ?>"
                data-locked-min="<?= htmlspecialchars(number_format((float)$lockedMin, 2, '.', ''), ENT_QUOTES) ?>"
                data-scheduled="<?= htmlspecialchars(number_format((float)$scheduledAmount, 2, '.', ''), ENT_QUOTES) ?>"
              >
                  <td class="py-2 pr-3 align-top">
                    <div class="flex items-start gap-2">
                      <span class="mt-1 inline-flex h-2.5 w-2.5 flex-none rounded-full" style="background-color: <?= htmlspecialchars($cat['color'], ENT_QUOTES) ?>"></span>
                      <div>
                        <div class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($cat['label'], ENT_QUOTES) ?></div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                          <?php if ($isLocked && $scheduledAmount > 0): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-200/70 px-2 py-0.5 font-medium text-slate-600 dark:bg-slate-800/80 dark:text-slate-200">
                              <i data-lucide="shield-check" class="h-3 w-3"></i>
                              <?= __('Scheduled') ?>
                            </span>
                          <?php elseif ($isLocked): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-200/70 px-2 py-0.5 font-medium text-slate-600 dark:bg-slate-800/80 dark:text-slate-200">
                              <i data-lucide="shield-check" class="h-3 w-3"></i>
                              <?= __('Protected') ?>
                            </span>
                          <?php endif; ?>
                          <span class="hidden inline-flex items-center gap-1 rounded-full bg-brand-100/70 px-2 py-0.5 font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-200" data-category-manual-tag>
                            <i data-lucide="pencil" class="h-3 w-3"></i>
                            <?= __('Adjusted') ?>
                          </span>
                        </div>
                      </div>
                    </div>
                    <input type="hidden" name="category_id[]" value="<?= $cat['id'] !== null ? (int)$cat['id'] : '' ?>" />
                    <input type="hidden" name="category_label[]" value="<?= htmlspecialchars($cat['label'], ENT_QUOTES) ?>" />
                    <input type="hidden" name="category_average[]" value="<?= htmlspecialchars($cat['average'], ENT_QUOTES) ?>" data-category-average />
                    <input type="hidden" name="category_locked_min[]" value="<?= htmlspecialchars(number_format((float)$lockedMin, 2, '.', ''), ENT_QUOTES) ?>" data-category-locked />
                  </td>
                  <td class="py-2 pr-3 align-top text-slate-600 dark:text-slate-300" data-category-average-display>
                    <div><?= moneyfmt($cat['average'], $mainCurrency) ?></div>
                    <?php if ($scheduledAmount > 0): ?>
                      <div class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                        <?= __('Protected minimum: :amount', ['amount' => moneyfmt($lockedMin > 0 ? $lockedMin : $scheduledAmount, $mainCurrency)]) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 pr-3 align-top text-slate-900 dark:text-white">
                    <div class="flex flex-wrap items-center gap-2">
                      <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 dark:text-slate-500"><?= htmlspecialchars($mainCurrency, ENT_QUOTES) ?></span>
                        <input
                          type="number"
                          step="0.01"
                          min="<?= htmlspecialchars(number_format((float)$lockedMin, 2, '.', ''), ENT_QUOTES) ?>"
                          class="input input-sm w-32 pl-14"
                          name="category_suggested[]"
                          value="<?= htmlspecialchars(number_format((float)$initialSuggestion, 2, '.', ''), ENT_QUOTES) ?>"
                          data-category-suggested-input
                        />
                      </div>
                      <button type="button" class="btn btn-xs btn-muted hidden" data-reset-suggestion>
                        <i data-lucide="rotate-ccw" class="h-3 w-3"></i>
                        <span><?= __('Reset') ?></span>
                      </button>
                    </div>
                    <div class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300" data-category-suggested-display>
                      <?= moneyfmt($initialSuggestion, $mainCurrency) ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" class="py-4 text-center text-slate-500 dark:text-slate-400">
                  <?= __('We will auto-fill suggestions after you log some spending categories.') ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rounded-3xl border border-white/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/60">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Monthly spending preview') ?></h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= __('We always keep at least 10% of income free. Flexible milestones slide later automatically to stay within the liveable budget.') ?>
          </p>
        </div>
      </div>
      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-white/60 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800/60 dark:text-slate-400">
              <th class="py-2 pr-3"><?= __('Month') ?></th>
              <th class="py-2 pr-3"><?= __('Milestones') ?></th>
              <th class="py-2 pr-3"><?= __('Categories') ?></th>
              <th class="py-2 pr-3"><?= __('Reserved 10%') ?></th>
              <th class="py-2 pr-3"><?= __('Unallocated') ?></th>
              <th class="py-2 pr-3"><?= __('Total planned') ?></th>
            </tr>
          </thead>
          <tbody data-monthly-preview-body>
          </tbody>
        </table>
      </div>
      <p class="mt-3 text-xs text-slate-500 dark:text-slate-400" data-monthly-preview-empty>
        <?= __('Add milestones or adjust category limits to see a month-by-month breakdown.') ?>
      </p>
    </div>

    <div class="flex flex-col gap-3 rounded-3xl border border-white/70 bg-white/80 px-4 py-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/60 sm:flex-row sm:items-center sm:justify-between">
      <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
        <input type="checkbox" name="activate" value="1" class="checkbox" />
        <span><?= __('Make this plan live immediately') ?></span>
      </label>
      <button type="submit" class="btn btn-primary">
        <i data-lucide="sparkles" class="h-4 w-4"></i>
        <span><?= __('Generate plan') ?></span>
      </button>
    </div>
  </form>
</section>

<?php if ($currentPlan): ?>
  <section class="mt-6 card">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="card-kicker"><?= __('Current plan overview') ?></div>
        <h2 class="card-title">
          <?= htmlspecialchars($currentPlan['title'] ?? __('Advanced plan'), ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          <?= __('Status: :status · Horizon: :months months', [
            'status' => ucfirst($currentPlan['status']),
            'months' => (int)$currentPlan['horizon_months'],
          ]) ?>
          <?php if (!empty($currentPlan['difficulty_level'])):
            $difficultyKey = $currentPlan['difficulty_level'];
            $difficultyLabel = $difficultyOptions[$difficultyKey]['label'] ?? ucfirst($difficultyKey);
          ?>
            · <?= __('Approach: :level', ['level' => htmlspecialchars($difficultyLabel, ENT_QUOTES)]) ?>
          <?php endif; ?>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="rounded-full border border-white/70 bg-white/80 px-4 py-1 text-sm text-slate-600 dark:border-slate-800/60 dark:bg-slate-900/60 dark:text-slate-300">
          <?= __('Start :start', ['start' => date('M Y', strtotime($currentPlan['plan_start']))]) ?>
        </span>
        <span class="rounded-full border border-white/70 bg-white/80 px-4 py-1 text-sm text-slate-600 dark:border-slate-800/60 dark:bg-slate-900/60 dark:text-slate-300">
          <?= __('End :end', ['end' => date('M Y', strtotime($currentPlan['plan_end']))]) ?>
        </span>
      </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Monthly income') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($currentPlan['monthly_income'], $currentPlan['main_currency']) ?></div>
      </div>
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Reserved safety cushion (10%)') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($planReservedFree, $currentPlan['main_currency']) ?></div>
      </div>
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Avg. milestone funding') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($currentPlan['monthly_commitments'], $currentPlan['main_currency']) ?></div>
      </div>
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Available for categories') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($currentPlan['monthly_discretionary'], $currentPlan['main_currency']) ?></div>
      </div>
    </div>

    <?php if ($currentPlan['status'] !== 'active'): ?>
      <div class="mt-4 rounded-3xl border border-white/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/60">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= __('Monthly cushion after planned spendings (excludes 10% reserve)') ?></div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              <?= __('This is the flex left after milestones and suggested limits are funded. A 10% income cushion stays untouched each month.') ?>
            </p>
          </div>
          <div class="text-xl font-semibold text-slate-900 dark:text-white">
            <?= moneyfmt($planFreeAfterLimits, $currentPlan['main_currency']) ?>
          </div>
        </div>
        <?php if ($planCategoryTotal > 0): ?>
          <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            <?= __('Planned category spending totals :amount/month.', ['amount' => moneyfmt($planCategoryTotal, $currentPlan['main_currency'])]) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($planMonthlyBreakdown['months'])): ?>
      <div class="mt-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Monthly spending outlook') ?></h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          <?= __('Milestones without deadlines start later automatically so each month stays within the 90% spending capacity.') ?>
        </p>
        <div class="mt-3 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-white/60 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800/60 dark:text-slate-400">
                <th class="py-2 pr-3"><?= __('Month') ?></th>
                <th class="py-2 pr-3"><?= __('Milestones') ?></th>
                <th class="py-2 pr-3"><?= __('Categories') ?></th>
                <th class="py-2 pr-3"><?= __('Reserved 10%') ?></th>
                <th class="py-2 pr-3"><?= __('Unallocated') ?></th>
                <th class="py-2 pr-3"><?= __('Total planned') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($planMonthlyBreakdown['months'] as $month): ?>
                <?php
                  $free = (float)($month['free'] ?? 0);
                  $freeClass = $free < -0.01 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300';
                ?>
                <tr class="border-b border-white/50 dark:border-slate-800/50">
                  <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= date('M Y', strtotime($month['date'])) ?></td>
                  <td class="py-2 pr-3 font-medium text-slate-700 dark:text-slate-100"><?= moneyfmt($month['milestones'], $currentPlan['main_currency']) ?></td>
                  <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($month['categories'], $currentPlan['main_currency']) ?></td>
                  <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($month['reserved'], $currentPlan['main_currency']) ?></td>
                  <td class="py-2 pr-3 font-medium <?= $freeClass ?>"><?= moneyfmt($free, $currentPlan['main_currency']) ?></td>
                  <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($month['total_planned'], $currentPlan['main_currency']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="mt-6">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Milestone order') ?></h3>
      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-white/60 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800/60 dark:text-slate-400">
              <th class="py-2 pr-3"><?= __('Priority') ?></th>
              <th class="py-2 pr-3"><?= __('Milestone') ?></th>
              <th class="py-2 pr-3"><?= __('Due by') ?></th>
              <th class="py-2 pr-3"><?= __('Target') ?></th>
              <th class="py-2 pr-3"><?= __('Monthly allocation') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($planItems as $item): ?>
              <tr class="border-b border-white/50 dark:border-slate-800/50">
                <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">#<?= (int)$item['priority'] ?></td>
                <td class="py-2 pr-3">
                  <div class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($item['reference_label'], ENT_QUOTES) ?></div>
                  <?php if (!empty($item['notes'])): ?>
                    <div class="text-xs text-slate-500 dark:text-slate-400"><?= nl2br(htmlspecialchars($item['notes'], ENT_QUOTES)) ?></div>
                  <?php endif; ?>
                </td>
                <td class="py-2 pr-3 text-slate-600 dark:text-slate-300">
                  <?php if (!empty($item['target_due_date'])): ?>
                    <?= date('M Y', strtotime($item['target_due_date'])) ?>
                  <?php else: ?>
                    <span class="text-slate-400 dark:text-slate-500"><?= __('Not set') ?></span>
                  <?php endif; ?>
                </td>
                <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($item['required_amount'], $currentPlan['main_currency']) ?></td>
                <td class="py-2 pr-3 font-semibold text-slate-900 dark:text-white"><?= moneyfmt($item['monthly_allocation'], $currentPlan['main_currency']) ?></td>
              </tr>
            <?php endforeach; if (!$planItems): ?>
              <tr>
                <td colspan="5" class="py-4 text-center text-slate-500 dark:text-slate-400"><?= __('No milestones were captured for this plan.') ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-6">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Suggested category limits') ?></h3>
      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-white/60 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800/60 dark:text-slate-400">
              <th class="py-2 pr-3"><?= __('Category') ?></th>
              <th class="py-2 pr-3"><?= __('Avg / month') ?></th>
              <th class="py-2 pr-3"><?= __('Suggested limit') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($planCategoryLimits as $cat): ?>
              <tr class="border-b border-white/50 dark:border-slate-800/50">
                <td class="py-2 pr-3 font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($cat['category_label'], ENT_QUOTES) ?></td>
                <td class="py-2 pr-3 text-slate-600 dark:text-slate-300"><?= moneyfmt($cat['average_spent'], $currentPlan['main_currency']) ?></td>
                <td class="py-2 pr-3 font-semibold text-slate-900 dark:text-white"><?= moneyfmt($cat['suggested_limit'], $currentPlan['main_currency']) ?></td>
              </tr>
            <?php endforeach; if (!$planCategoryLimits): ?>
              <tr>
                <td colspan="3" class="py-4 text-center text-slate-500 dark:text-slate-400"><?= __('No category limits were recorded for this plan.') ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-sm text-slate-500 dark:text-slate-400">
        <?= __('Switch between saved plans using the selector below or delete ones you no longer need.') ?>
      </div>
      <div class="flex flex-wrap gap-2">
        <?php if ($currentPlan['status'] !== 'active'): ?>
          <form method="post" action="/advanced-planner/activate" class="inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="plan_id" value="<?= (int)$currentPlan['id'] ?>" />
            <input type="hidden" name="avg_months" value="<?= (int)$averageMonths ?>" />
            <button type="submit" class="btn btn-primary">
              <i data-lucide="rocket" class="h-4 w-4"></i>
              <span><?= __('Make live') ?></span>
            </button>
          </form>
        <?php endif; ?>
        <form method="post" action="/advanced-planner/delete" onsubmit="return confirm('<?= __('Delete this plan?') ?>');" class="inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input type="hidden" name="plan_id" value="<?= (int)$currentPlan['id'] ?>" />
          <input type="hidden" name="avg_months" value="<?= (int)$averageMonths ?>" />
          <button type="submit" class="btn btn-muted">
            <i data-lucide="trash" class="h-4 w-4"></i>
            <span><?= __('Delete') ?></span>
          </button>
        </form>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($plans)): ?>
  <section class="mt-6 card">
    <div class="card-kicker"><?= __('Saved plans') ?></div>
    <h2 class="card-title"><?= __('Review or jump to an older plan') ?></h2>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
      <?php foreach ($plans as $plan): ?>
        <a href="<?= '/advanced-planner?plan=' . (int)$plan['id'] . '&avg_months=' . (int)$averageMonths ?>" class="panel block p-4 transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:-translate-y-0.5 focus-visible:shadow-lg">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($plan['title'], ENT_QUOTES) ?></div>
              <div class="text-xs text-slate-500 dark:text-slate-400">
                <?= __('Horizon: :months months', ['months' => (int)$plan['horizon_months']]) ?> · <?= __('Status: :status', ['status' => ucfirst($plan['status'])]) ?>
                <?php if (!empty($plan['difficulty_level'])):
                  $planDifficultyKey = $plan['difficulty_level'];
                  $planDifficultyLabel = $difficultyOptions[$planDifficultyKey]['label'] ?? ucfirst($planDifficultyKey);
                ?>
                  · <?= __('Approach: :level', ['level' => htmlspecialchars($planDifficultyLabel, ENT_QUOTES)]) ?>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($plan['status'] === 'active'): ?>
              <span class="rounded-full bg-brand-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 dark:bg-brand-500/30 dark:text-brand-100">
                <?= __('Live') ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            <?= __('Start :start · End :end', [
              'start' => date('M Y', strtotime($plan['plan_start'])),
              'end' => date('M Y', strtotime($plan['plan_end'])),
            ]) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<template id="planner-item-template">
  <div class="panel p-4" data-item>
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div class="grid flex-1 gap-3 md:grid-cols-12">
        <label class="md:col-span-5">
          <span class="label"><?= __('Label') ?></span>
          <input class="input" name="item_label[]" placeholder="<?= __('Build EF to 3 months') ?>" required />
        </label>
        <label class="md:col-span-3">
          <span class="label"><?= __('Type') ?></span>
          <select class="select" name="item_type[]">
            <option value="emergency"><?= __('Emergency fund') ?></option>
            <option value="investment"><?= __('Investment') ?></option>
            <option value="loan"><?= __('Loan payoff') ?></option>
            <option value="goal"><?= __('Goal') ?></option>
            <option value="custom" selected><?= __('Custom') ?></option>
          </select>
        </label>
        <label class="md:col-span-3">
          <span class="label"><?= __('Due by (optional)') ?></span>
          <input class="input" type="month" name="item_due[]" data-due-input />
        </label>
        <label class="md:col-span-2">
          <span class="label"><?= __('Target (main currency)') ?></span>
          <input class="input" type="number" step="0.01" min="0" name="item_target[]" value="0" data-target-input />
        </label>
        <label class="md:col-span-2">
          <span class="label"><?= __('Already set aside') ?></span>
          <input class="input" type="number" step="0.01" min="0" name="item_current[]" value="0" data-current-input />
        </label>
        <label class="md:col-span-2">
          <span class="label"><?= __('Priority order') ?></span>
          <input class="input" type="number" min="1" step="1" name="item_priority[]" value="1" />
        </label>
        <label class="md:col-span-12">
          <span class="label"><?= __('Notes (optional)') ?></span>
          <textarea class="textarea" name="item_notes[]" rows="2" placeholder="<?= __('Add colour, dates, or reference links.') ?>"></textarea>
        </label>
        <input type="hidden" name="item_reference[]" value="" />
      </div>
      <div class="flex flex-col items-end gap-2 md:w-40">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Monthly estimate') ?></div>
        <div class="text-lg font-semibold text-slate-900 dark:text-white" data-monthly-output><?= moneyfmt(0, $mainCurrency) ?></div>
        <button type="button" class="btn btn-muted" data-remove-item>
          <i data-lucide="trash-2" class="h-4 w-4"></i>
          <span><?= __('Remove') ?></span>
        </button>
      </div>
    </div>
  </div>
</template>

<?php
$ruleDataForJs = array_map(
  static function (array $rule): array {
    return [
      'id' => (int)($rule['id'] ?? 0),
      'percent' => (float)($rule['percent'] ?? 0),
    ];
  },
  $cashflowRules
);
?>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('[data-items-container]');
    const template = document.getElementById('planner-item-template');
    const addBtn = document.querySelector('[data-add-item]');
    const emptyState = container ? container.querySelector('[data-empty-state]') : null;
    const horizonSelect = document.querySelector('[data-horizon-selector]');
    const startInput = document.querySelector('input[name="start_month"]');
    const leftoverPreview = document.querySelector('[data-leftover-preview]');
    const monthlyPreviewBody = document.querySelector('[data-monthly-preview-body]');
    const monthlyPreviewEmpty = document.querySelector('[data-monthly-preview-empty]');
    const progressWrapper = document.querySelector('[data-budget-progress-wrapper]');
    const progressBar = document.querySelector('[data-budget-progress-bar]');
    const progressLabel = document.querySelector('[data-budget-progress-label]');
    const averageForm = document.querySelector('[data-average-form]');
    const averageSelect = document.querySelector('[data-average-select]');
    const difficultySelect = document.querySelector('[data-difficulty-selector]');
    const difficultyDescription = document.querySelector('[data-difficulty-description]');
    const difficultyConfig = <?= json_encode($difficultyConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const ruleData = <?= json_encode($ruleDataForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const monthlyIncome = <?= json_encode((float)($incomeData['total'] ?? 0)) ?>;
    const reservedFree = <?= json_encode($reservedFreePreview) ?>;
    const planningCapacity = Math.max(0, monthlyIncome - reservedFree);
    const defaultDifficulty = <?= json_encode($defaultDifficulty) ?>;
    const progressLabelTemplate = <?= json_encode(__('Allocated :spent of :available')) ?>;
    const leftoverFreeTemplate = <?= json_encode(__('Free to allocate after cushion: :amount/month')) ?>;
    const leftoverOverTemplate = <?= json_encode(__('Overallocated past cushion by :amount/month')) ?>;
    const leftoverFullText = <?= json_encode(__('Fully allocated for the month')) ?>;

    averageSelect?.addEventListener('change', () => {
      if (averageForm) {
        averageForm.submit();
      }
    });

    const fmt = (value) => {
      const num = Number.isFinite(value) ? value : 0;
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: <?= json_encode($mainCurrency) ?> }).format(num);
    };

    const parseMonthValue = (value) => {
      if (!value) return null;
      const [yStr, mStr] = value.split('-');
      const year = Number.parseInt(yStr, 10);
      const month = Number.parseInt(mStr, 10);
      if (!Number.isFinite(year) || !Number.isFinite(month)) return null;
      return new Date(Date.UTC(year, month - 1, 1));
    };

    const formatMonthValue = (date) => {
      if (!date) return '';
      const year = date.getUTCFullYear();
      const month = String(date.getUTCMonth() + 1).padStart(2, '0');
      return `${year}-${month}`;
    };

    const getPlanEnd = (startDate, horizon) => {
      if (!startDate) return null;
      const months = Math.max(0, Math.floor(horizon) - 1);
      return new Date(Date.UTC(startDate.getUTCFullYear(), startDate.getUTCMonth() + months, 1));
    };

    const clampDueDate = (startDate, horizon, dueDate) => {
      if (!startDate || !dueDate) return dueDate;
      const planEnd = getPlanEnd(startDate, horizon);
      if (!planEnd) return dueDate;
      if (dueDate < startDate) return new Date(startDate.getTime());
      if (dueDate > planEnd) return new Date(planEnd.getTime());
      return dueDate;
    };

    const monthsBetweenInclusive = (startDate, endDate) => {
      if (!startDate || !endDate) return null;
      const years = endDate.getUTCFullYear() - startDate.getUTCFullYear();
      const months = endDate.getUTCMonth() - startDate.getUTCMonth();
      return Math.max(1, years * 12 + months + 1);
    };

    const updateEmptyState = () => {
      if (!container) return;
      const hasItems = container.querySelectorAll('[data-item]').length > 0;
      if (emptyState) {
        emptyState.style.display = hasItems ? 'none' : '';
      }
    };

    const normalizeDueInputForPanel = (panel) => {
      const dueInput = panel.querySelector('[data-due-input]');
      if (!dueInput) return;
      const dueValue = dueInput.value;
      if (!dueValue) return;
      const startMonthValue = startInput?.value || '';
      const startDate = parseMonthValue(startMonthValue);
      if (!startDate) return;
      const dueDate = parseMonthValue(dueValue);
      if (!dueDate) return;
      const horizon = Math.max(1, parseFloat(horizonSelect?.value || '3') || 3);
      const clamped = clampDueDate(startDate, horizon, dueDate);
      if (!clamped) return;
      const formatted = formatMonthValue(clamped);
      if (formatted !== dueValue) {
        dueInput.value = formatted;
      }
    };

    const computeMonthly = (panel) => {
      const horizon = Math.max(1, parseFloat(horizonSelect?.value || '3') || 3);
      const target = parseFloat(panel.querySelector('[data-target-input]')?.value || '0');
      const current = parseFloat(panel.querySelector('[data-current-input]')?.value || '0');
      const required = Math.max(0, target - current);
      let monthsToFund = horizon;
      const startDate = parseMonthValue(startInput?.value || '');
      const dueInput = panel.querySelector('[data-due-input]');
      const dueDateRaw = dueInput ? parseMonthValue(dueInput.value) : null;
      if (startDate) {
        const planEnd = getPlanEnd(startDate, horizon) || startDate;
        const clampedDue = clampDueDate(startDate, horizon, dueDateRaw);
        const effectiveEnd = clampedDue || planEnd;
        const span = monthsBetweenInclusive(startDate, effectiveEnd);
        if (span) {
          monthsToFund = span;
        }
      }
      const monthly = monthsToFund > 0 ? required / monthsToFund : 0;
      return monthly;
    };

    const recalcPanel = (panel) => {
      const monthly = computeMonthly(panel);
      const output = panel.querySelector('[data-monthly-output]');
      if (output) {
        output.textContent = fmt(monthly);
      }
      return monthly;
    };

    const categoryRows = Array.from(document.querySelectorAll('[data-category-row]')).map((row) => {
      const averageInput = row.querySelector('[data-category-average]');
      const lockedInput = row.querySelector('[data-category-locked]');
      const suggestedInput = row.querySelector('[data-category-suggested-input]');
      const lockedFromDataset = Number.parseFloat(row.dataset.lockedMin || '0');
      return {
        row,
        ruleId: row.dataset.ruleId ? Number.parseInt(row.dataset.ruleId, 10) : null,
        averageInput,
        average: Number.parseFloat(averageInput?.value || '0') || 0,
        suggestedInput,
        displayCell: row.querySelector('[data-category-suggested-display]'),
        lockedInput,
        manualTag: row.querySelector('[data-category-manual-tag]'),
        resetButton: row.querySelector('[data-reset-suggestion]'),
        lockedMin: Number.isFinite(lockedFromDataset) && lockedFromDataset > 0
          ? lockedFromDataset
          : Number.parseFloat(lockedInput?.value || '0') || 0,
        scheduled: Number.parseFloat(row.dataset.scheduled || '0') || 0,
        manual: false,
      };
    });

    let lastEditedCategory = null;
    let isRecalculating = false;

    const updateDisplay = (cat, value) => {
      if (cat.displayCell) {
        cat.displayCell.textContent = fmt(value);
      }
    };

    const setManualState = (cat, manual) => {
      cat.manual = Boolean(manual);
      if (cat.resetButton) {
        cat.resetButton.classList.toggle('hidden', !cat.manual);
      }
      if (cat.manualTag) {
        cat.manualTag.classList.toggle('hidden', !cat.manual);
      }
    };

    categoryRows.forEach((cat) => setManualState(cat, false));

    const ruleMap = new Map(
      (ruleData || []).map((rule) => [Number.parseInt(rule.id, 10), { percent: Number.parseFloat(rule.percent) || 0 }])
    );

    const parseNumeric = (value, fallback = 0) => {
      const num = Number.parseFloat(value);
      return Number.isFinite(num) ? num : fallback;
    };

    const buildMilestoneSchedule = () => {
      const horizonValue = Number.parseInt(horizonSelect?.value || '3', 10);
      const horizon = Number.isFinite(horizonValue) && horizonValue > 0 ? horizonValue : 1;
      const months = Array.from({ length: horizon }, (_, index) => ({ index, load: 0, available: planningCapacity }));
      const startDate = parseMonthValue(startInput?.value || '');

      const dueEntries = [];
      const flexEntries = [];

      const panels = container?.querySelectorAll('[data-item]') || [];
      panels.forEach((panel, idx) => {
        const targetInput = panel.querySelector('[data-target-input]');
        const currentInput = panel.querySelector('[data-current-input]');
        const dueInput = panel.querySelector('[data-due-input]');
        const target = parseNumeric(targetInput?.value || '0', 0);
        const current = parseNumeric(currentInput?.value || '0', 0);
        const required = Math.max(0, target - current);
        let dueIndex = Math.max(0, horizon - 1);
        let hasDue = false;
        if (dueInput?.value) {
          const dueDateRaw = parseMonthValue(dueInput.value);
          if (dueDateRaw) {
            hasDue = true;
            if (startDate) {
              const clampedDue = clampDueDate(startDate, horizon, dueDateRaw);
              const span = monthsBetweenInclusive(startDate, clampedDue) || horizon;
              dueIndex = Math.min(horizon - 1, Math.max(0, span - 1));
            } else {
              dueIndex = Math.max(0, horizon - 1);
            }
          }
        }

        const entry = { key: idx, required, dueIndex, hasDue };
        if (hasDue) {
          dueEntries.push(entry);
        } else {
          flexEntries.push(entry);
        }
      });

      dueEntries.sort((a, b) => {
        if (a.dueIndex !== b.dueIndex) {
          return a.dueIndex - b.dueIndex;
        }
        return b.required - a.required;
      });

      flexEntries.sort((a, b) => {
        if (b.required !== a.required) {
          return b.required - a.required;
        }
        return a.key - b.key;
      });

      const orderedEntries = [...dueEntries, ...flexEntries];

      orderedEntries.forEach((entry) => {
        const { key, required } = entry;
        let dueIndex = Math.min(Math.max(entry.dueIndex, 0), months.length - 1);
        if (required <= 0) {
          return;
        }

        let bestStart = 0;
        let bestMonthly = required / Math.max(1, dueIndex + 1);
        let bestLength = Math.max(1, dueIndex + 1);
        let bestOverflow = Number.POSITIVE_INFINITY;

        for (let start = dueIndex; start >= 0; start -= 1) {
          const length = dueIndex - start + 1;
          if (length <= 0) continue;
          const share = required / length;
          let fits = true;
          let overflow = 0;
          for (let i = start; i <= dueIndex; i += 1) {
            const projected = months[i].load + share;
            if (projected - planningCapacity > 1e-6) {
              fits = false;
              overflow = Math.max(overflow, projected - planningCapacity);
            }
          }
          if (fits) {
            bestStart = start;
            bestMonthly = share;
            bestLength = length;
            bestOverflow = -1;
            break;
          }
          if (overflow < bestOverflow) {
            bestOverflow = overflow;
            bestStart = start;
            bestMonthly = share;
            bestLength = length;
          }
        }

        for (let i = bestStart; i <= dueIndex; i += 1) {
          months[i].load += bestMonthly;
        }
      });

      let minAvailable = planningCapacity;
      months.forEach((month) => {
        month.available = Math.max(0, planningCapacity - month.load);
        minAvailable = Math.min(minAvailable, month.available);
      });

      return {
        months,
        minAvailable,
        horizon,
        startDate,
      };
    };

    const updateMonthlyPreview = (schedule, categoriesTotal) => {
      if (!monthlyPreviewBody) {
        return;
      }

      const months = schedule?.months || [];
      monthlyPreviewBody.innerHTML = '';

      if (!months.length) {
        if (monthlyPreviewEmpty) {
          monthlyPreviewEmpty.classList.remove('hidden');
        }
        return;
      }

      const startDate = schedule.startDate;

      months.forEach((month) => {
        const offsetDate = startDate
          ? new Date(Date.UTC(startDate.getUTCFullYear(), startDate.getUTCMonth() + month.index, 1))
          : null;
        const label = offsetDate
          ? offsetDate.toLocaleString(undefined, { month: 'short', year: 'numeric' })
          : `M${month.index + 1}`;
        const milestones = month.load;
        const free = monthlyIncome - reservedFree - categoriesTotal - milestones;
        const totalPlanned = milestones + categoriesTotal + reservedFree;

        const row = document.createElement('tr');
        row.className = 'border-b border-white/50 dark:border-slate-800/50';

        const cells = [
          { text: label, className: 'py-2 pr-3 text-slate-600 dark:text-slate-300' },
          { text: fmt(milestones), className: 'py-2 pr-3 font-medium text-slate-700 dark:text-slate-100' },
          { text: fmt(categoriesTotal), className: 'py-2 pr-3 text-slate-600 dark:text-slate-300' },
          { text: fmt(reservedFree), className: 'py-2 pr-3 text-slate-600 dark:text-slate-300' },
          {
            text: fmt(free),
            className: `py-2 pr-3 font-medium ${free < -0.01 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300'}`,
          },
          { text: fmt(totalPlanned), className: 'py-2 pr-3 text-slate-600 dark:text-slate-300' },
        ];

        cells.forEach((cell) => {
          const td = document.createElement('td');
          td.className = cell.className;
          td.textContent = cell.text;
          row.appendChild(td);
        });

        monthlyPreviewBody.appendChild(row);
      });

      if (monthlyPreviewEmpty) {
        monthlyPreviewEmpty.classList.toggle('hidden', months.length > 0);
      }
    };

    const enforceManualLimits = (discretionary) => {
      const manualCats = categoryRows.filter((cat) => cat.manual && cat.suggestedInput);
      if (!manualCats.length) {
        return;
      }

      let nonManualLocked = 0;
      categoryRows.forEach((cat) => {
        if (cat.manual) {
          return;
        }
        nonManualLocked += Math.max(0, cat.lockedMin || 0);
      });

      const allowance = discretionary - nonManualLocked;
      const manualLockedSum = manualCats.reduce((sum, cat) => sum + Math.max(0, cat.lockedMin || 0), 0);
      if (allowance <= manualLockedSum + 0.0001) {
        manualCats.forEach((cat) => {
          const minValue = Math.max(0, cat.lockedMin || 0);
          if (cat.suggestedInput) {
            cat.suggestedInput.value = minValue.toFixed(2);
          }
          updateDisplay(cat, minValue);
        });
        return;
      }

      const availableExtras = Math.max(0, allowance - manualLockedSum);
      const manualData = manualCats.map((cat) => {
        const minValue = Math.max(0, cat.lockedMin || 0);
        const currentValue = Math.max(minValue, parseNumeric(cat.suggestedInput?.value || '0', minValue));
        const extra = Math.max(0, currentValue - minValue);
        return { cat, minValue, extra, currentValue };
      });

      let totalExtras = 0;
      manualData.forEach((item) => {
        totalExtras += item.extra;
      });

      if (totalExtras <= availableExtras + 0.0001) {
        manualData.forEach((item) => {
          if (item.cat.suggestedInput) {
            item.cat.suggestedInput.value = item.currentValue.toFixed(2);
          }
          updateDisplay(item.cat, item.currentValue);
        });
        return;
      }

      const ordered = [...manualData];
      if (lastEditedCategory) {
        ordered.sort((a, b) => {
          if (a.cat === lastEditedCategory) return 1;
          if (b.cat === lastEditedCategory) return -1;
          return 0;
        });
      }

      let remainingExtras = availableExtras;
      ordered.forEach((item) => {
        const allowedExtra = Math.max(0, Math.min(item.extra, remainingExtras));
        const finalValue = item.minValue + allowedExtra;
        if (item.cat.suggestedInput) {
          item.cat.suggestedInput.value = finalValue.toFixed(2);
        }
        updateDisplay(item.cat, finalValue);
        remainingExtras = Math.max(0, remainingExtras - allowedExtra);
      });
    };

    const calculateCategoryTotals = () => {
      let total = 0;
      let lockedTotal = 0;
      categoryRows.forEach((cat) => {
        const minValue = Math.max(0, cat.lockedMin || 0);
        const raw = cat.suggestedInput ? parseNumeric(cat.suggestedInput.value || '0', minValue) : minValue;
        const effective = Math.max(minValue, raw);
        total += effective;
        lockedTotal += minValue;
      });
      return { total, lockedTotal };
    };

    const updateProgress = (discretionary, allocated, freeAmount) => {
      const overspent = freeAmount < -0.01;
      const hasCapacity = discretionary > 0;
      if (progressLabel) {
        const label = progressLabelTemplate
          .replace(':spent', fmt(Math.max(0, allocated)))
          .replace(':available', fmt(Math.max(0, discretionary)));
        progressLabel.textContent = label;
      }
      if (progressWrapper) {
        progressWrapper.classList.toggle('text-rose-600', overspent);
        progressWrapper.classList.toggle('dark:text-rose-400', overspent);
        progressWrapper.classList.toggle('text-slate-600', !overspent);
        progressWrapper.classList.toggle('dark:text-slate-300', !overspent);
      }
      if (progressBar) {
        let ratio = 0;
        if (hasCapacity) {
          ratio = Math.min(1, allocated / discretionary);
        } else {
          ratio = allocated > 0 ? 1 : 0;
        }
        const percent = Math.max(0, Math.min(100, ratio * 100));
        progressBar.style.width = `${percent}%`;
        progressBar.classList.toggle('bg-rose-500', overspent);
        progressBar.classList.toggle('dark:bg-rose-400', overspent);
        progressBar.classList.toggle('bg-brand-500', !overspent);
        progressBar.classList.toggle('dark:bg-brand-400', !overspent);
      }
      if (leftoverPreview) {
        let text;
        if (freeAmount > 0.01) {
          text = leftoverFreeTemplate.replace(':amount', fmt(freeAmount));
        } else if (freeAmount < -0.01) {
          text = leftoverOverTemplate.replace(':amount', fmt(Math.abs(freeAmount)));
        } else {
          text = leftoverFullText;
        }
        leftoverPreview.textContent = text;
        leftoverPreview.classList.toggle('text-rose-600', overspent);
        leftoverPreview.classList.toggle('dark:text-rose-400', overspent);
        leftoverPreview.classList.toggle('text-slate-500', !overspent);
        leftoverPreview.classList.toggle('dark:text-slate-400', !overspent);
      }
    };

    const getDifficultyKey = () => {
      const key = difficultySelect?.value || defaultDifficulty || 'medium';
      return Object.prototype.hasOwnProperty.call(difficultyConfig, key) ? key : 'medium';
    };

    const updateDifficultyDescription = () => {
      if (!difficultyDescription) return;
      const key = getDifficultyKey();
      const info = difficultyConfig[key];
      if (info && info.description) {
        difficultyDescription.textContent = info.description;
      }
    };

    const computeCategorySuggestions = (income, discretionary, difficultyKey) => {
      const multiplier = difficultyConfig[difficultyKey]?.multiplier ?? 1;
      const lockedAmounts = new Map();
      const adjustableBases = new Map();
      let totalLocked = 0;

      categoryRows.forEach((cat, idx) => {
        const minValue = Number.isFinite(cat.lockedMin) ? Math.max(0, cat.lockedMin) : 0;
        const averageValue = Number.isFinite(cat.average) ? cat.average : Number.parseFloat(cat.averageInput?.value || '0') || 0;
        const manualValue = Number.parseFloat(cat.suggestedInput?.value || '0');
        const effectiveManual = Number.isFinite(manualValue) ? manualValue : 0;
        let lockedBase = 0;
        let adjustableBase = 0;
        if (cat.manual) {
          lockedBase = Math.max(minValue, effectiveManual);
          adjustableBase = 0;
        } else {
          lockedBase = minValue > 0 ? minValue : 0;
          adjustableBase = Math.max(0, averageValue - lockedBase);
        }
        if (lockedBase > 0) {
          lockedAmounts.set(idx, lockedBase);
          totalLocked += lockedBase;
        }
        adjustableBases.set(idx, adjustableBase);
      });

      const availableDiscretionary = Math.max(0, discretionary - totalLocked);
      const groups = new Map();

      categoryRows.forEach((cat, idx) => {
        const ruleId = cat.ruleId;
        if (!ruleId) return;
        const rule = ruleMap.get(ruleId);
        if (!rule || !(rule.percent > 0)) return;
        if (!groups.has(ruleId)) {
          groups.set(ruleId, { percent: rule.percent, locked: 0, adjustable: [] });
        }
        const group = groups.get(ruleId);
        if (lockedAmounts.has(idx)) {
          group.locked += lockedAmounts.get(idx);
        }
        const adjustableBase = adjustableBases.get(idx) || 0;
        if (adjustableBase > 0) {
          group.adjustable.push(idx);
        }
      });

      let totalRuleBudget = 0;
      groups.forEach((group) => {
        const base = Math.max(0, (group.percent / 100) * income);
        group.base = base;
        group.adjustableBudget = Math.max(0, base - group.locked);
        totalRuleBudget += group.adjustableBudget;
      });

      let allocatedToRules = totalRuleBudget;
      let scale = 1;
      if (totalRuleBudget > 0 && availableDiscretionary < totalRuleBudget) {
        scale = availableDiscretionary / totalRuleBudget;
        allocatedToRules = availableDiscretionary;
      }

      const adjustableValues = new Array(categoryRows.length).fill(0);

      groups.forEach((group) => {
        if (!group.adjustable.length) {
          return;
        }
        const ruleBudget = group.adjustableBudget * scale;
        if (ruleBudget <= 0) {
          return;
        }
        let totalAvg = 0;
        group.adjustable.forEach((idx) => {
          const base = adjustableBases.get(idx) || 0;
          totalAvg += Math.max(0, base);
        });
        group.adjustable.forEach((idx) => {
          const base = adjustableBases.get(idx) || 0;
          if (totalAvg > 0) {
            adjustableValues[idx] = ruleBudget * (Math.max(0, base) / totalAvg);
          } else {
            adjustableValues[idx] = ruleBudget / group.adjustable.length;
          }
        });
      });

      const unruledAdjustable = [];
      categoryRows.forEach((cat, idx) => {
        if (cat.ruleId) {
          return;
        }
        const base = adjustableBases.get(idx) || 0;
        if (base <= 0) {
          return;
        }
        unruledAdjustable.push(idx);
      });

      let leftoverForUnruled = Math.max(0, availableDiscretionary - allocatedToRules);
      if (totalRuleBudget <= 0) {
        leftoverForUnruled = availableDiscretionary;
      }

      if (unruledAdjustable.length > 0) {
        let totalAvg = 0;
        unruledAdjustable.forEach((idx) => {
          const base = adjustableBases.get(idx) || 0;
          totalAvg += Math.max(0, base);
        });
        unruledAdjustable.forEach((idx) => {
          const base = adjustableBases.get(idx) || 0;
          if (leftoverForUnruled <= 0) {
            adjustableValues[idx] = 0;
          } else if (totalAvg > 0) {
            adjustableValues[idx] = leftoverForUnruled * (Math.max(0, base) / totalAvg);
          } else {
            adjustableValues[idx] = leftoverForUnruled / unruledAdjustable.length;
          }
        });
      }

      const multiplierValue = Number.isFinite(multiplier) ? multiplier : 1;
      adjustableValues.forEach((value, idx) => {
        const base = adjustableBases.get(idx) || 0;
        if (base <= 0) {
          return;
        }
        adjustableValues[idx] = Math.max(0, value * multiplierValue);
      });

      let totalAdjustable = 0;
      adjustableValues.forEach((value, idx) => {
        const base = adjustableBases.get(idx) || 0;
        if (base > 0) {
          totalAdjustable += value;
        }
      });
      if (totalAdjustable > availableDiscretionary && availableDiscretionary > 0) {
        const ratio = availableDiscretionary / totalAdjustable;
        adjustableValues.forEach((value, idx) => {
          const base = adjustableBases.get(idx) || 0;
          if (base > 0) {
            adjustableValues[idx] = value * ratio;
          }
        });
      }

      const finalValues = new Array(categoryRows.length).fill(0);
      categoryRows.forEach((cat, idx) => {
        const lockedBase = lockedAmounts.get(idx) || 0;
        const base = adjustableBases.get(idx) || 0;
        const adjustableValue = base > 0 ? Math.max(0, adjustableValues[idx]) : 0;
        finalValues[idx] = lockedBase + adjustableValue;
      });

      return finalValues;
    };

    const applyCategorySuggestions = (values) => {
      categoryRows.forEach((cat, idx) => {
        if (!cat.suggestedInput) {
          return;
        }
        if (cat.manual) {
          const raw = Number.parseFloat(cat.suggestedInput.value || '0');
          const effective = Math.max(cat.lockedMin || 0, Number.isFinite(raw) ? raw : 0);
          updateDisplay(cat, effective);
          return;
        }
        const value = Number.isFinite(values[idx]) ? values[idx] : 0;
        const minValue = cat.lockedMin || 0;
        const finalValue = Math.max(minValue, value);
        cat.suggestedInput.value = finalValue.toFixed(2);
        updateDisplay(cat, finalValue);
      });
    };

    categoryRows.forEach((cat) => {
      const input = cat.suggestedInput;
      if (!input) return;

      input.addEventListener('input', () => {
        const raw = Number.parseFloat(input.value || '0');
        const effective = Math.max(cat.lockedMin || 0, Number.isFinite(raw) ? raw : 0);
        setManualState(cat, true);
        lastEditedCategory = cat;
        updateDisplay(cat, effective);
        recalcPlanEstimates();
      });

      input.addEventListener('blur', () => {
        const raw = Number.parseFloat(input.value || '0');
        const effective = Math.max(cat.lockedMin || 0, Number.isFinite(raw) ? raw : 0);
        input.value = effective.toFixed(2);
        updateDisplay(cat, effective);
      });

      cat.resetButton?.addEventListener('click', () => {
        setManualState(cat, false);
        const baseValue = Math.max(cat.lockedMin || 0, 0);
        input.value = baseValue > 0 ? baseValue.toFixed(2) : '';
        updateDisplay(cat, baseValue);
        lastEditedCategory = cat;
        recalcPlanEstimates();
      });
    });

    const recalcPlanEstimates = () => {
      if (isRecalculating) {
        return;
      }
      isRecalculating = true;
      const schedule = buildMilestoneSchedule();
      const rawAvailable = schedule && Number.isFinite(schedule.minAvailable)
        ? schedule.minAvailable
        : planningCapacity;
      const discretionary = Math.max(0, rawAvailable);
      enforceManualLimits(discretionary);
      const difficultyKey = getDifficultyKey();
      const suggestionValues = computeCategorySuggestions(monthlyIncome, discretionary, difficultyKey);
      applyCategorySuggestions(suggestionValues);
      const totals = calculateCategoryTotals();
      const categoryTotal = Number.isFinite(totals.total) ? totals.total : 0;
      const freeAmount = rawAvailable - categoryTotal;
      updateProgress(discretionary, categoryTotal, freeAmount);
      updateMonthlyPreview(schedule, categoryTotal);
      isRecalculating = false;
    };

    const attachListeners = (panel) => {
      const inputs = panel.querySelectorAll('[data-target-input], [data-current-input]');
      inputs.forEach((input) => {
        input.addEventListener('input', () => {
          recalcPanel(panel);
          recalcPlanEstimates();
        });
      });
      const dueInput = panel.querySelector('[data-due-input]');
      dueInput?.addEventListener('change', () => {
        normalizeDueInputForPanel(panel);
        recalcPanel(panel);
        recalcPlanEstimates();
      });
      dueInput?.addEventListener('input', () => {
        recalcPanel(panel);
        recalcPlanEstimates();
      });
      const removeBtn = panel.querySelector('[data-remove-item]');
      removeBtn?.addEventListener('click', () => {
        panel.remove();
        updateEmptyState();
        recalcPlanEstimates();
      });
    };

    const addItem = (prefill = {}) => {
      if (!template || !container) return;
      const node = template.content.firstElementChild.cloneNode(true);
      const label = node.querySelector('input[name="item_label[]"]');
      const type = node.querySelector('select[name="item_type[]"]');
      const due = node.querySelector('input[name="item_due[]"]');
      const target = node.querySelector('input[name="item_target[]"]');
      const current = node.querySelector('input[name="item_current[]"]');
      const priority = node.querySelector('input[name="item_priority[]"]');
      const reference = node.querySelector('input[name="item_reference[]"]');
      const notes = node.querySelector('textarea[name="item_notes[]"]');
      if (prefill.label) label.value = prefill.label;
      if (prefill.kind) type.value = prefill.kind;
      if (prefill.due) due.value = prefill.due;
      if (prefill.target !== undefined) target.value = prefill.target;
      if (prefill.current !== undefined) current.value = prefill.current;
      if (prefill.priority) priority.value = prefill.priority;
      if (prefill.reference) reference.value = prefill.reference;
      if (prefill.notes) notes.value = prefill.notes;
      container.appendChild(node);
      const panel = container.lastElementChild;
      attachListeners(panel);
      normalizeDueInputForPanel(panel);
      recalcPanel(panel);
      recalcPlanEstimates();
      updateEmptyState();
    };

    addBtn?.addEventListener('click', () => addItem());
    horizonSelect?.addEventListener('change', () => {
      container?.querySelectorAll('[data-item]').forEach((panel) => {
        normalizeDueInputForPanel(panel);
        recalcPanel(panel);
      });
      recalcPlanEstimates();
    });
    startInput?.addEventListener('change', () => {
      container?.querySelectorAll('[data-item]').forEach((panel) => {
        normalizeDueInputForPanel(panel);
        recalcPanel(panel);
      });
      recalcPlanEstimates();
    });

    document.querySelectorAll('[data-quick-add]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const prefill = {
          label: btn.dataset.label || '',
          kind: btn.dataset.kind || 'custom',
          target: parseFloat(btn.dataset.target || '0') || 0,
          current: parseFloat(btn.dataset.current || '0') || 0,
          priority: (container?.querySelectorAll('[data-item]').length || 0) + 1,
          reference: btn.dataset.reference || '',
          notes: btn.dataset.notes || '',
          due: btn.dataset.due || ''
        };
        addItem(prefill);
      });
    });

    difficultySelect?.addEventListener('change', () => {
      updateDifficultyDescription();
      recalcPlanEstimates();
    });

    updateEmptyState();
    updateDifficultyDescription();
    recalcPlanEstimates();
  });
</script>
