<?php
/** @var array $plans */
/** @var array|null $currentPlan */
/** @var array $planItems */
/** @var array $planCategoryLimits */
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
        <span class="rounded-full border border-white/70 bg-white/80 px-4 py-1 text-sm font-medium text-slate-600 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60 dark:text-slate-300" data-leftover-preview>
          <?= __('Leftover estimate: :amount/month', ['amount' => moneyfmt(0, $mainCurrency)]) ?>
        </span>
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
              ?>
              <tr
                class="border-b border-white/50 dark:border-slate-800/50"
                data-category-row
                data-rule-id="<?= $ruleId ? (int)$ruleId : '' ?>"
              >
                  <td class="py-2 pr-3">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($cat['color'], ENT_QUOTES) ?>"></span>
                      <span class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($cat['label'], ENT_QUOTES) ?></span>
                    </div>
                    <input type="hidden" name="category_id[]" value="<?= $cat['id'] !== null ? (int)$cat['id'] : '' ?>" />
                    <input type="hidden" name="category_label[]" value="<?= htmlspecialchars($cat['label'], ENT_QUOTES) ?>" />
                    <input type="hidden" name="category_average[]" value="<?= htmlspecialchars($cat['average'], ENT_QUOTES) ?>" data-category-average />
                    <input type="hidden" name="category_suggested[]" value="<?= htmlspecialchars($initialSuggestion, ENT_QUOTES) ?>" data-category-suggested />
                  </td>
                  <td class="py-2 pr-3 text-slate-600 dark:text-slate-300" data-category-average-display>
                    <?= moneyfmt($cat['average'], $mainCurrency) ?>
                  </td>
                  <td class="py-2 pr-3 font-semibold text-slate-900 dark:text-white" data-category-suggested-display>
                    <?= moneyfmt($initialSuggestion, $mainCurrency) ?>
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

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Monthly income') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($currentPlan['monthly_income'], $currentPlan['main_currency']) ?></div>
      </div>
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Milestone funding') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($currentPlan['monthly_commitments'], $currentPlan['main_currency']) ?></div>
      </div>
      <div class="panel p-4 text-sm">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Leftover for spending') ?></div>
        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= moneyfmt($currentPlan['monthly_discretionary'], $currentPlan['main_currency']) ?></div>
      </div>
    </div>

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
    const averageForm = document.querySelector('[data-average-form]');
    const averageSelect = document.querySelector('[data-average-select]');
    const difficultySelect = document.querySelector('[data-difficulty-selector]');
    const difficultyDescription = document.querySelector('[data-difficulty-description]');
    const difficultyConfig = <?= json_encode($difficultyConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const ruleData = <?= json_encode($ruleDataForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const monthlyIncome = <?= json_encode((float)($incomeData['total'] ?? 0)) ?>;
    const defaultDifficulty = <?= json_encode($defaultDifficulty) ?>;

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

    const categoryRows = Array.from(document.querySelectorAll('[data-category-row]')).map((row) => ({
      row,
      ruleId: row.dataset.ruleId ? Number.parseInt(row.dataset.ruleId, 10) : null,
      averageInput: row.querySelector('[data-category-average]'),
      suggestedInput: row.querySelector('[data-category-suggested]'),
      displayCell: row.querySelector('[data-category-suggested-display]'),
    }));

    const ruleMap = new Map(
      (ruleData || []).map((rule) => [Number.parseInt(rule.id, 10), { percent: Number.parseFloat(rule.percent) || 0 }])
    );

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
      const suggestions = new Array(categoryRows.length).fill(0);
      const groups = new Map();

      categoryRows.forEach((cat, idx) => {
        const ruleId = cat.ruleId;
        const rule = ruleId ? ruleMap.get(ruleId) : null;
        if (rule && rule.percent > 0) {
          if (!groups.has(ruleId)) {
            groups.set(ruleId, { indexes: [], percent: rule.percent });
          }
          groups.get(ruleId).indexes.push(idx);
        }
      });

      let totalRuleBudget = 0;
      groups.forEach((group) => {
        const base = Math.max(0, (group.percent / 100) * income);
        group.base = base;
        totalRuleBudget += base;
      });

      let allocatedToRules = totalRuleBudget;
      let scale = 1;
      if (totalRuleBudget > 0 && discretionary < totalRuleBudget) {
        scale = discretionary / totalRuleBudget;
        allocatedToRules = discretionary;
      }

      groups.forEach((group) => {
        const ruleBudget = group.base * scale;
        const totalAvg = group.indexes.reduce((sum, idx) => {
          const avg = Number.parseFloat(categoryRows[idx].averageInput?.value || '0') || 0;
          return sum + Math.max(0, avg);
        }, 0);
        group.indexes.forEach((idx) => {
          let allocation = 0;
          if (ruleBudget > 0) {
            const avg = Number.parseFloat(categoryRows[idx].averageInput?.value || '0') || 0;
            if (totalAvg > 0) {
              allocation = ruleBudget * (Math.max(0, avg) / totalAvg);
            } else {
              allocation = ruleBudget / group.indexes.length;
            }
          }
          suggestions[idx] = allocation;
        });
      });

      const unruledIndexes = [];
      categoryRows.forEach((cat, idx) => {
        const ruleId = cat.ruleId;
        const rule = ruleId ? ruleMap.get(ruleId) : null;
        if (!rule || !(rule.percent > 0)) {
          unruledIndexes.push(idx);
        }
        if (!Number.isFinite(suggestions[idx])) {
          suggestions[idx] = 0;
        }
      });

      let leftoverForUnruled = Math.max(0, discretionary - allocatedToRules);
      if (totalRuleBudget <= 0) {
        leftoverForUnruled = discretionary;
      }

      if (unruledIndexes.length > 0) {
        const totalAvg = unruledIndexes.reduce((sum, idx) => {
          const avg = Number.parseFloat(categoryRows[idx].averageInput?.value || '0') || 0;
          return sum + Math.max(0, avg);
        }, 0);
        unruledIndexes.forEach((idx) => {
          let allocation = 0;
          if (leftoverForUnruled > 0) {
            const avg = Number.parseFloat(categoryRows[idx].averageInput?.value || '0') || 0;
            if (totalAvg > 0) {
              allocation = leftoverForUnruled * (Math.max(0, avg) / totalAvg);
            } else {
              allocation = leftoverForUnruled / unruledIndexes.length;
            }
          }
          suggestions[idx] = allocation;
        });
      }

      const scaled = suggestions.map((value) => Math.max(0, value * multiplier));
      const totalSuggested = scaled.reduce((sum, value) => sum + value, 0);
      if (totalSuggested > discretionary && discretionary > 0) {
        const ratio = discretionary / totalSuggested;
        return scaled.map((value) => value * ratio);
      }
      return scaled;
    };

    const applyCategorySuggestions = (values) => {
      categoryRows.forEach((cat, idx) => {
        const value = Number.isFinite(values[idx]) ? values[idx] : 0;
        if (cat.suggestedInput) {
          cat.suggestedInput.value = value.toFixed(2);
        }
        if (cat.displayCell) {
          cat.displayCell.textContent = fmt(value);
        }
      });
    };

    const recalcPlanEstimates = () => {
      let totalMonthly = 0;
      container?.querySelectorAll('[data-item]').forEach((panel) => {
        totalMonthly += computeMonthly(panel);
      });
      const discretionary = Math.max(0, monthlyIncome - totalMonthly);
      if (leftoverPreview) {
        leftoverPreview.textContent = <?= json_encode(__('Leftover estimate: :amount/month')) ?>.replace(':amount', fmt(discretionary));
      }
      const difficultyKey = getDifficultyKey();
      const suggestionValues = computeCategorySuggestions(monthlyIncome, discretionary, difficultyKey);
      applyCategorySuggestions(suggestionValues);
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
