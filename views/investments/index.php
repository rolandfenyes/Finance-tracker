<?php
/** @var array $investments */
/** @var array $availableSchedules */
/** @var array $userCurrencies */
/** @var string $mainCurrency */
/** @var array $transactionsByInvestment */
/** @var array $performanceByInvestment */
/** @var array $categories */

$types = [
  'savings' => [
    'label' => __('Savings account'),
    'description' => __('Track bank deposits and high-yield savings balances.'),
    'icon' => 'piggy-bank',
  ],
  'etf' => [
    'label' => __('ETF'),
    'description' => __('Monitor diversified funds like the S&P 500.'),
    'icon' => 'line-chart',
  ],
  'stock' => [
    'label' => __('Individual stock'),
    'description' => __('Follow single-company positions you hold.'),
    'icon' => 'trending-up',
  ],
];

$frequencyOptions = [
  'daily' => __('Daily'),
  'weekly' => __('Weekly'),
  'monthly' => __('Monthly'),
  'annual' => __('Annual'),
];

$addPanelId = 'investment-add-panel';

?>

<div class="mx-auto w-full max-w-5xl space-y-6 pb-6 pt-6 sm:px-6 lg:px-0">
  <section class="card">
    <div class="card-kicker"><?= __('Investments') ?></div>
    <h1 class="card-title mt-1"><?= __('Manage your investment accounts') ?></h1>
    <p class="card-subtle mt-2 text-sm">
      <?= __('Keep track of savings accounts, ETFs, and individual stocks. Link scheduled payments to stay on autopilot.') ?>
    </p>

    <div class="mt-6">
      <button
        type="button"
        class="btn btn-primary inline-flex items-center gap-2"
        data-investment-add-toggle
        data-target="#<?= $addPanelId ?>"
        aria-expanded="false"
        aria-controls="<?= $addPanelId ?>"
      >
        <span data-add-state="closed" class="inline-flex items-center gap-2">
          <i data-lucide="plus" class="h-4 w-4"></i>
          <?= __('Add new investment') ?>
        </span>
        <span data-add-state="open" class="hidden items-center gap-2">
          <i data-lucide="minus" class="h-4 w-4"></i>
          <?= __('Hide add investment form') ?>
        </span>
      </button>
    </div>

    <details id="<?= $addPanelId ?>" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-5 shadow-sm transition dark:border-slate-700 dark:bg-slate-900/40">
      <summary class="sr-only"><?= __('Add new investment') ?></summary>

      <div class="mt-5 space-y-4">
        <form method="post" action="/investments/add" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

          <div class="grid gap-3 md:grid-cols-3">
            <?php foreach ($types as $value => $meta): ?>
              <label class="group relative flex cursor-pointer flex-col gap-2 rounded-2xl border border-slate-200 p-4 transition hover:border-brand-300 hover:bg-brand-50/70 dark:border-slate-700 dark:hover:border-brand-500/70 dark:hover:bg-slate-900/60">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2 text-sm font-semibold">
                    <input
                      type="radio"
                      name="type"
                      value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"
                      class="h-4 w-4 text-brand-600 focus:ring-brand-500"
                      <?= $value === 'savings' ? 'checked' : '' ?>
                    />
                    <span><?= htmlspecialchars($meta['label']) ?></span>
                  </div>
                  <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 group-hover:bg-brand-200 dark:bg-brand-500/15 dark:text-brand-50">
                    <i data-lucide="<?= htmlspecialchars($meta['icon']) ?>" class="h-4 w-4"></i>
                  </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-300/80">
                  <?= htmlspecialchars($meta['description']) ?>
                </p>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label" for="investment-name"><?= __('Name') ?></label>
              <input id="investment-name" name="name" class="input" placeholder="<?= __('e.g., Smart Savings 2.5%') ?>" required />
            </div>
            <div>
              <label class="label" for="investment-provider"><?= __('Institution or broker') ?></label>
              <input id="investment-provider" name="provider" class="input" placeholder="<?= __('e.g., MyBank') ?>" />
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
              <label class="label" for="investment-identifier"><?= __('Account number / Ticker') ?></label>
              <input id="investment-identifier" name="identifier" class="input" placeholder="<?= __('e.g., HU12 3456 7890 1234 or VOO') ?>" />
            </div>
            <div>
              <label class="label" for="investment-frequency"><?= __('Interest payment frequency') ?></label>
              <select id="investment-frequency" name="interest_frequency" class="select">
                <?php foreach ($frequencyOptions as $freqValue => $freqLabel): ?>
                  <option value="<?= htmlspecialchars($freqValue) ?>" <?= $freqValue === 'monthly' ? 'selected' : '' ?>>
                    <?= htmlspecialchars($freqLabel) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label" for="investment-interest"><?= __('Annual rate (EBKM)') ?></label>
              <div class="relative">
                <input id="investment-interest" name="interest_rate" type="number" step="0.01" min="0" class="input pr-12" placeholder="<?= __('Optional') ?>" />
                <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">%</span>
              </div>
            </div>
            <div>
              <label class="label" for="investment-currency"><?= __('Currency') ?></label>
              <select id="investment-currency" name="currency" class="select">
                <?php foreach ($userCurrencies as $currency):
                  $code = strtoupper($currency['code'] ?? '');
                  if ($code === '') { continue; }
                  $selected = !empty($currency['is_main']);
                ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= $selected ? 'selected' : '' ?>>
                    <?= htmlspecialchars($code) ?><?= !empty($currency['is_main']) ? ' · ' . __('Main') : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label" for="investment-initial-amount"><?= __('Starting balance') ?></label>
              <div class="relative">
                <input id="investment-initial-amount" name="initial_amount" type="number" step="0.01" class="input pr-16" placeholder="<?= __('Optional') ?>" />
                <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">
                  <?= htmlspecialchars(strtoupper($mainCurrency)) ?>
                </span>
              </div>
            </div>
            <div>
              <label class="label"><?= __('Linked scheduled payment') ?></label>
              <div class="rounded-xl border border-slate-200 dark:border-slate-700" data-schedule-tabs>
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2 text-sm font-medium dark:border-slate-700">
                  <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-xs btn-outline" data-schedule-tab="existing" aria-selected="true">
                      <?= __('Link existing schedule') ?>
                    </button>
                    <button type="button" class="btn btn-xs btn-muted" data-schedule-tab="new" aria-selected="false">
                      <?= __('Create new schedule') ?>
                    </button>
                  </div>
                </div>
                <input type="hidden" name="scheduled_mode" value="existing" data-schedule-mode />
                <div class="space-y-3 px-3 py-3" data-schedule-panel="existing">
                  <select id="investment-schedule" name="scheduled_payment_id" class="select">
                    <option value=""><?= __('No linked schedule') ?></option>
                    <?php foreach ($availableSchedules as $schedule): ?>
                      <option value="<?= (int)$schedule['id'] ?>">
                        <?= htmlspecialchars($schedule['title']) ?> · <?= moneyfmt($schedule['amount']) ?> <?= htmlspecialchars($schedule['currency']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <p class="text-xs text-slate-500 dark:text-slate-300/80">
                    <?= __('Link a scheduled payment to automate contributions or transfers.') ?>
                  </p>
                </div>
                <div class="hidden space-y-3 border-t border-slate-200 px-3 py-3 dark:border-slate-700" data-schedule-panel="new">
                  <p class="text-xs text-slate-500 dark:text-slate-300/80">
                    <?= __('Automate new contributions in one step.') ?>
                  </p>
                  <div class="grid gap-3 md:grid-cols-2">
                    <div>
                      <label class="label" for="scheduled-new-title"><?= __('Title') ?></label>
                      <input id="scheduled-new-title" name="scheduled_new_title" class="input" placeholder="<?= __('e.g., Monthly top-up') ?>" data-schedule-required="new" />
                    </div>
                    <div>
                      <label class="label" for="scheduled-new-amount"><?= __('Amount') ?></label>
                      <input id="scheduled-new-amount" name="scheduled_new_amount" type="number" step="0.01" min="0" class="input" data-schedule-required="new" />
                    </div>
                  </div>
                  <div class="grid gap-3 md:grid-cols-2">
                    <div>
                      <label class="label" for="scheduled-new-currency"><?= __('Currency') ?></label>
                      <select id="scheduled-new-currency" name="scheduled_new_currency" class="select">
                        <option value="<?= htmlspecialchars(strtoupper($mainCurrency)) ?>" selected><?= htmlspecialchars(strtoupper($mainCurrency)) ?></option>
                        <?php foreach ($userCurrencies as $currency):
                          $code = strtoupper($currency['code'] ?? '');
                          if ($code === '' || $code === strtoupper($mainCurrency)) { continue; }
                        ?>
                          <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="label" for="scheduled-new-next"><?= __('First due') ?></label>
                      <input id="scheduled-new-next" name="scheduled_new_next_due" type="date" class="input" data-schedule-required="new" />
                    </div>
                  </div>
                  <div>
                    <label class="label" for="scheduled-new-category"><?= __('Category') ?></label>
                    <select id="scheduled-new-category" name="scheduled_new_category_id" class="select">
                      <option value=""><?= __('No category') ?></option>
                      <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['label']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-rrbuilder="invadd">
                    <input type="hidden" name="scheduled_new_rrule" id="rrule-invadd" />
                    <div class="grid gap-2 md:grid-cols-12">
                      <div class="md:col-span-3">
                        <label class="label" for="rb-freq-invadd"><?= __('Repeats') ?></label>
                        <select class="select" id="rb-freq-invadd">
                          <option value=""><?= __('Does not repeat') ?></option>
                          <option value="DAILY"><?= __('Daily') ?></option>
                          <option value="WEEKLY"><?= __('Weekly') ?></option>
                          <option value="MONTHLY"><?= __('Monthly') ?></option>
                          <option value="YEARLY"><?= __('Yearly') ?></option>
                        </select>
                      </div>
                      <div class="md:col-span-2">
                        <label class="label" for="rb-interval-invadd"><?= __('Every') ?></label>
                        <input type="number" min="1" value="1" id="rb-interval-invadd" class="input" />
                      </div>
                      <div class="md:col-span-7" id="rb-weekly-invadd" style="display:none">
                        <label class="label"><?= __('On') ?></label>
                        <div class="flex flex-wrap gap-2 text-sm">
                          <?php
                            $days = ['MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun'];
                            foreach ($days as $code => $lbl): ?>
                            <label class="inline-flex items-center gap-1">
                              <input type="checkbox" value="<?= $code ?>" class="rb-byday-invadd">
                              <span><?= __($lbl) ?></span>
                            </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <div class="md:col-span-7" id="rb-monthly-invadd" style="display:none">
                        <label class="label" for="rb-bymonthday-invadd"><?= __('Day of month') ?></label>
                        <input type="number" min="1" max="31" id="rb-bymonthday-invadd" class="input" placeholder="<?= __('e.g., 10') ?>" />
                      </div>
                      <div class="md:col-span-7" id="rb-yearly-invadd" style="display:none">
                        <div class="grid grid-cols-2 gap-2">
                          <div>
                            <label class="label" for="rb-bymonth-invadd"><?= __('Month') ?></label>
                            <input type="number" min="1" max="12" id="rb-bymonth-invadd" class="input" placeholder="1-12" />
                          </div>
                          <div>
                            <label class="label" for="rb-bymday-invadd"><?= __('Day') ?></label>
                            <input type="number" min="1" max="31" id="rb-bymday-invadd" class="input" placeholder="1-31" />
                          </div>
                        </div>
                      </div>
                      <div class="md:col-span-12 grid gap-2 md:grid-cols-12">
                        <div class="md:col-span-3">
                          <label class="label" for="rb-endtype-invadd"><?= __('Ends') ?></label>
                          <select class="select" id="rb-endtype-invadd">
                            <option value="none"><?= __('Never') ?></option>
                            <option value="count"><?= __('After # times') ?></option>
                            <option value="until"><?= __('On date') ?></option>
                          </select>
                        </div>
                        <div class="md:col-span-2" id="rb-count-wrap-invadd" style="display:none">
                          <label class="label" for="rb-count-invadd"><?= __('Count') ?></label>
                          <input type="number" min="1" id="rb-count-invadd" class="input" />
                        </div>
                        <div class="md:col-span-3" id="rb-until-wrap-invadd" style="display:none">
                          <label class="label" for="rb-until-invadd"><?= __('Until') ?></label>
                          <input type="date" id="rb-until-invadd" class="input" />
                        </div>
                        <div class="md:col-span-4 flex items-end justify-end">
                          <div class="text-xs text-slate-500" id="rb-summary-invadd"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label class="label" for="investment-notes"><?= __('Notes') ?></label>
            <textarea id="investment-notes" name="notes" rows="3" class="textarea" placeholder="<?= __('Add details like goals, strategy, or reminders.') ?>"></textarea>
          </div>

          <div class="flex justify-end">
            <button class="btn btn-primary">
              <i data-lucide="check" class="mr-2 h-4 w-4"></i>
              <?= __('Save investment') ?>
            </button>
          </div>
        </form>
      </div>
    </details>
  </section>

  <section class="card">
    <h2 class="card-title flex items-center gap-2">
      <i data-lucide="briefcase" class="h-5 w-5"></i>
      <?= __('Your investments') ?>
    </h2>
    <?php if (!$investments): ?>
      <p class="card-subtle mt-2 text-sm">
        <?= __('No investments yet. Add your first one above to start tracking performance and linked transfers.') ?>
      </p>
    <?php else: ?>
      <div class="mt-4 space-y-4">
        <?php foreach ($investments as $investment):
          $currentType = $investment['type'] ?? 'savings';
          $meta = $types[$currentType] ?? $types['savings'];
          $scheduleId = (int)($investment['sched_id'] ?? 0);
          $investmentId = (int)($investment['id'] ?? 0);
          $transactions = $transactionsByInvestment[$investmentId] ?? [];
          $currencyCode = strtoupper((string)($investment['currency'] ?? $mainCurrency));
          if ($currencyCode === '') {
            $currencyCode = strtoupper($mainCurrency);
          }
          $balance = (float)($investment['balance'] ?? 0);
          $performance = $performanceByInvestment[$investmentId] ?? null;
          $frequencyKey = strtolower((string)($investment['interest_frequency'] ?? 'monthly'));
          $frequencyLabel = $frequencyOptions[$frequencyKey] ?? ucfirst($frequencyKey);
          $estimatedInterest = (float)($performance['estimated_interest'] ?? 0);
          $milestones = $performance['milestones'] ?? [];
          $milestoneCount = is_array($milestones) ? count($milestones) : 0;
          $hasRate = !empty($performance['has_rate']);
        ?>
          <details class="panel overflow-hidden" data-investment="<?= $investmentId ?>">
            <summary class="flex cursor-pointer flex-col gap-4 p-5 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500">
              <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                  <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-50">
                    <i data-lucide="<?= htmlspecialchars($meta['icon']) ?>" class="h-5 w-5"></i>
                  </span>
                  <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <?= htmlspecialchars($meta['label']) ?>
                    </div>
                    <div class="text-lg font-semibold text-slate-900 dark:text-white">
                      <?= htmlspecialchars($investment['name'] ?? '') ?>
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <?= __('Current balance') ?>
                  </div>
                  <div class="text-xl font-semibold text-slate-900 dark:text-white">
                    <?= moneyfmt($balance, $currencyCode) ?>
                  </div>
                </div>
              </div>
          <?php if (!empty($investment['provider']) || !empty($investment['identifier'])): ?>
            <div class="text-sm text-slate-500 dark:text-slate-400">
              <?= htmlspecialchars($investment['provider'] ?? '') ?><?= (!empty($investment['provider']) && !empty($investment['identifier'])) ? ' · ' : '' ?><?= htmlspecialchars($investment['identifier'] ?? '') ?>
            </div>
          <?php endif; ?>
          <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span><?= __('Payout frequency: :frequency', ['frequency' => htmlspecialchars($frequencyLabel)]) ?></span>
            <?php if ($investment['interest_rate'] !== null && $investment['interest_rate'] !== ''): ?>
              <span class="hidden text-slate-400 dark:text-slate-500 sm:inline">•</span>
              <span><?= __('Rate: :rate%', ['rate' => number_format((float)$investment['interest_rate'], 2)]) ?></span>
            <?php endif; ?>
          </div>
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span><?= __('Open editor') ?></span>
            <span><?= __('Updated :date', ['date' => htmlspecialchars(substr((string)($investment['updated_at'] ?? ''), 0, 16))]) ?></span>
          </div>
        </summary>

            <div class="space-y-6 border-t border-slate-200 bg-white px-5 pb-6 pt-5 dark:border-slate-700 dark:bg-slate-900">
              <div class="grid gap-4 md:grid-cols-2">
                <form method="post" action="/investments/adjust" class="space-y-3 rounded-xl border border-slate-200 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $investmentId ?>" />
                  <input type="hidden" name="direction" value="deposit" />
                  <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                      <?= __('Add money') ?>
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($currencyCode) ?></span>
                  </div>
                  <div>
                    <label class="label text-xs uppercase tracking-wide" for="deposit-amount-<?= $investmentId ?>"><?= __('Amount') ?></label>
                    <input id="deposit-amount-<?= $investmentId ?>" name="amount" type="number" step="0.01" min="0" class="input" required />
                  </div>
                  <div>
                    <label class="label text-xs uppercase tracking-wide" for="deposit-note-<?= $investmentId ?>"><?= __('Note') ?> <span class="lowercase text-slate-400">(<?= __('Optional') ?>)</span></label>
                    <input id="deposit-note-<?= $investmentId ?>" name="note" class="input" placeholder="<?= __('e.g., Manual top-up') ?>" />
                  </div>
                  <button class="btn btn-primary w-full">
                    <i data-lucide="arrow-down-circle" class="mr-2 h-4 w-4"></i>
                    <?= __('Add money') ?>
                  </button>
                </form>

                <form method="post" action="/investments/adjust" class="space-y-3 rounded-xl border border-slate-200 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $investmentId ?>" />
                  <input type="hidden" name="direction" value="withdraw" />
                  <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                      <?= __('Withdraw') ?>
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($currencyCode) ?></span>
                  </div>
                  <div>
                    <label class="label text-xs uppercase tracking-wide" for="withdraw-amount-<?= $investmentId ?>"><?= __('Amount') ?></label>
                    <input id="withdraw-amount-<?= $investmentId ?>" name="amount" type="number" step="0.01" min="0" class="input" required />
                  </div>
                  <div>
                    <label class="label text-xs uppercase tracking-wide" for="withdraw-note-<?= $investmentId ?>"><?= __('Note') ?> <span class="lowercase text-slate-400">(<?= __('Optional') ?>)</span></label>
                    <input id="withdraw-note-<?= $investmentId ?>" name="note" class="input" placeholder="<?= __('e.g., Transfer to checking') ?>" />
                  </div>
                  <button class="btn btn-muted w-full text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/30">
                    <i data-lucide="arrow-up-circle" class="mr-2 h-4 w-4"></i>
                    <?= __('Withdraw') ?>
                  </button>
                </form>
            </div>

            <?php if ($performance): ?>
              <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">
                  <span><?= __('Performance insights') ?></span>
                  <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($frequencyLabel) ?></span>
                </div>
                <div class="grid gap-4 px-4 py-4 lg:grid-cols-5">
                  <div class="space-y-3 lg:col-span-2">
                    <div class="rounded-lg border border-slate-200/70 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300/80">
                        <?= __('Estimated interest earned') ?>
                      </div>
                      <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                        <?= moneyfmt($estimatedInterest, $currencyCode) ?>
                      </div>
                      <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <?= __('Based on your balance changes and interest rate to date.') ?>
                      </p>
                    </div>
                    <?php if ($milestoneCount > 0): ?>
                      <div class="grid gap-3 sm:grid-cols-<?= $milestoneCount > 1 ? 2 : 1 ?>">
                        <?php foreach ($milestones as $milestone):
                          if (!is_array($milestone)) { continue; }
                          $value = (float)($milestone['value'] ?? 0);
                          $gain = (float)($milestone['gain'] ?? 0);
                          $contribTotal = (float)($milestone['contribution_total'] ?? 0);
                        ?>
                          <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/60">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300/80">
                              <?= htmlspecialchars((string)($milestone['label'] ?? '')) ?>
                            </div>
                            <div class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                              <?= moneyfmt($value, $currencyCode) ?>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                              <?= __('Projected interest: :amount', ['amount' => moneyfmt($gain, $currencyCode)]) ?>
                            </p>
                            <?php if ($contribTotal > 0): ?>
                              <p class="text-xs text-slate-500 dark:text-slate-400">
                                <?= __('Includes :amount in contributions', ['amount' => moneyfmt($contribTotal, $currencyCode)]) ?>
                              </p>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        <?= __('Add an annual rate to unlock growth projections.') ?>
                      </p>
                    <?php endif; ?>
                  </div>
                  <div class="lg:col-span-3">
                    <?php if ($hasRate && !empty($performance['chart_values'])): ?>
                      <div class="h-64">
                        <canvas id="investment-chart-<?= $investmentId ?>" aria-label="<?= __('Expected balance over time') ?>" role="img"></canvas>
                      </div>
                    <?php else: ?>
                      <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        <?= __('Add an annual rate to unlock growth projections.') ?>
                      </p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <form method="post" action="/investments/update" class="space-y-4" id="investment-update-<?= $investmentId ?>">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $investmentId ?>" />

                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label class="label"><?= __('Investment type') ?></label>
                    <select name="type" class="select">
                      <?php foreach ($types as $value => $metaOption): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $value === $currentType ? 'selected' : '' ?>>
                          <?= htmlspecialchars($metaOption['label']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="label"><?= __('Name') ?></label>
                    <input name="name" class="input" value="<?= htmlspecialchars($investment['name'] ?? '') ?>" required />
                  </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label class="label"><?= __('Institution or broker') ?></label>
                    <input name="provider" class="input" value="<?= htmlspecialchars($investment['provider'] ?? '') ?>" />
                  </div>
                  <div>
                    <label class="label"><?= __('Account number / Ticker') ?></label>
                    <input name="identifier" class="input" value="<?= htmlspecialchars($investment['identifier'] ?? '') ?>" />
                  </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                  <div>
                    <label class="label"><?= __('Annual rate (EBKM)') ?></label>
                    <div class="relative">
                      <input name="interest_rate" type="number" step="0.01" min="0" class="input pr-12" value="<?= htmlspecialchars($investment['interest_rate'] ?? '') ?>" />
                      <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">%</span>
                    </div>
                  </div>
                  <div>
                    <label class="label"><?= __('Interest payment frequency') ?></label>
                    <select name="interest_frequency" class="select">
                      <?php foreach ($frequencyOptions as $freqValue => $freqLabel): ?>
                        <option value="<?= htmlspecialchars($freqValue) ?>" <?= $freqValue === $frequencyKey ? 'selected' : '' ?>>
                          <?= htmlspecialchars($freqLabel) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="label"><?= __('Currency') ?></label>
                    <select name="currency" class="select">
                      <option value="<?= htmlspecialchars($currencyCode) ?>" selected>
                        <?= htmlspecialchars($currencyCode) ?><?= strtoupper($currencyCode) === strtoupper($mainCurrency) ? ' · ' . __('Main') : '' ?>
                      </option>
                      <?php foreach ($userCurrencies as $currency):
                        $code = strtoupper($currency['code'] ?? '');
                        if ($code === '' || $code === $currencyCode) {
                          continue;
                        }
                      ?>
                        <option value="<?= htmlspecialchars($code) ?>">
                          <?= htmlspecialchars($code) ?><?= !empty($currency['is_main']) ? ' · ' . __('Main') : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label class="label"><?= __('Linked scheduled payment') ?></label>
                    <select name="scheduled_payment_id" class="select">
                      <option value=""><?= __('No linked schedule') ?></option>
                      <?php if ($scheduleId): ?>
                        <option value="<?= $scheduleId ?>" selected>
                          <?= htmlspecialchars($investment['sched_title'] ?? '') ?>
                        </option>
                      <?php endif; ?>
                      <?php foreach ($availableSchedules as $schedule): ?>
                        <option value="<?= (int)$schedule['id'] ?>">
                          <?= htmlspecialchars($schedule['title']) ?> · <?= moneyfmt($schedule['amount']) ?> <?= htmlspecialchars($schedule['currency']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ($scheduleId): ?>
                      <p class="mt-1 text-xs text-slate-500 dark:text-slate-300/80">
                        <?= __('Next payment on :date', ['date' => htmlspecialchars($investment['sched_next_due'] ?? '—')]) ?>
                      </p>
                      <?php if (!empty($investment['sched_summary'])): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-300/80">
                          <?= __('Repeats: :summary', ['summary' => htmlspecialchars($investment['sched_summary'])]) ?>
                        </p>
                      <?php endif; ?>
                      <?php if (!empty($investment['sched_amount'])): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-300/80">
                          <?= __('Scheduled amount: :amount', ['amount' => moneyfmt((float)$investment['sched_amount'], (string)($investment['sched_currency'] ?? $currencyCode))]) ?>
                        </p>
                      <?php endif; ?>
                    <?php else: ?>
                      <p class="mt-1 text-xs text-slate-500 dark:text-slate-300/80">
                        <?= __('No schedule linked yet.') ?>
                      </p>
                    <?php endif; ?>
                  </div>
                  <div>
                    <label class="label"><?= __('Notes') ?></label>
                    <textarea name="notes" rows="3" class="textarea"><?= htmlspecialchars($investment['notes'] ?? '') ?></textarea>
                  </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    <?= __('Created on :date', ['date' => htmlspecialchars(substr((string)($investment['created_at'] ?? ''), 0, 10))]) ?>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button class="btn btn-primary">
                      <i data-lucide="save" class="mr-2 h-4 w-4"></i>
                      <?= __('Save changes') ?>
                    </button>
                    <button
                      type="submit"
                      form="investment-delete-<?= $investmentId ?>"
                      class="btn btn-muted text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/30"
                    >
                      <i data-lucide="trash-2" class="mr-2 h-4 w-4"></i>
                      <?= __('Delete investment') ?>
                    </button>
                  </div>
                </div>
            </form>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
              <details>
                <summary class="flex cursor-pointer items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white">
                  <span class="inline-flex items-center gap-2">
                    <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                    <?= __('Create and link scheduled payment') ?>
                  </span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">
                    <?= __('Automate new contributions in one step.') ?>
                  </span>
                </summary>
                <div class="border-t border-slate-200 px-4 py-4 dark:border-slate-700">
                  <?php $builderId = 'inv' . $investmentId; ?>
                  <form method="post" action="/investments/scheduled/create" class="space-y-3">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                    <input type="hidden" name="investment_id" value="<?= $investmentId ?>" />
                    <div class="grid gap-3 md:grid-cols-2">
                      <div>
                        <label class="label" for="schedule-title-<?= $investmentId ?>"><?= __('Title') ?></label>
                        <input id="schedule-title-<?= $investmentId ?>" name="title" class="input" placeholder="<?= __('e.g., Monthly top-up') ?>" required />
                      </div>
                      <div>
                        <label class="label" for="schedule-amount-<?= $investmentId ?>"><?= __('Amount') ?></label>
                        <input id="schedule-amount-<?= $investmentId ?>" name="amount" type="number" step="0.01" min="0" class="input" required />
                      </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                      <div>
                        <label class="label" for="schedule-currency-<?= $investmentId ?>"><?= __('Currency') ?></label>
                        <select id="schedule-currency-<?= $investmentId ?>" name="currency" class="select">
                          <option value="<?= htmlspecialchars($currencyCode) ?>" selected><?= htmlspecialchars($currencyCode) ?></option>
                          <?php foreach ($userCurrencies as $currency):
                            $code = strtoupper($currency['code'] ?? '');
                            if ($code === '' || $code === $currencyCode) { continue; }
                          ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div>
                        <label class="label" for="schedule-next-<?= $investmentId ?>"><?= __('First due') ?></label>
                        <input id="schedule-next-<?= $investmentId ?>" name="next_due" type="date" class="input" required />
                      </div>
                    </div>
                    <div>
                      <label class="label" for="schedule-category-<?= $investmentId ?>"><?= __('Category') ?></label>
                      <select id="schedule-category-<?= $investmentId ?>" name="category_id" class="select">
                        <option value=""><?= __('No category') ?></option>
                        <?php foreach ($categories as $category): ?>
                          <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['label']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-rrbuilder="<?= htmlspecialchars($builderId) ?>">
                      <input type="hidden" name="rrule" id="rrule-<?= htmlspecialchars($builderId) ?>" />
                      <div class="grid gap-2 md:grid-cols-12">
                        <div class="md:col-span-3">
                          <label class="label" for="rb-freq-<?= htmlspecialchars($builderId) ?>"><?= __('Repeats') ?></label>
                          <select class="select" id="rb-freq-<?= htmlspecialchars($builderId) ?>">
                            <option value=""><?= __('Does not repeat') ?></option>
                            <option value="DAILY"><?= __('Daily') ?></option>
                            <option value="WEEKLY"><?= __('Weekly') ?></option>
                            <option value="MONTHLY"><?= __('Monthly') ?></option>
                            <option value="YEARLY"><?= __('Yearly') ?></option>
                          </select>
                        </div>
                        <div class="md:col-span-2">
                          <label class="label" for="rb-interval-<?= htmlspecialchars($builderId) ?>"><?= __('Every') ?></label>
                          <input type="number" min="1" value="1" id="rb-interval-<?= htmlspecialchars($builderId) ?>" class="input" />
                        </div>
                        <div class="md:col-span-7" id="rb-weekly-<?= htmlspecialchars($builderId) ?>" style="display:none">
                          <label class="label"><?= __('On') ?></label>
                          <div class="flex flex-wrap gap-2 text-sm">
                            <?php
                              $days = ['MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun'];
                              foreach ($days as $code => $lbl): ?>
                              <label class="inline-flex items-center gap-1">
                                <input type="checkbox" value="<?= $code ?>" class="rb-byday-<?= htmlspecialchars($builderId) ?>">
                                <span><?= __($lbl) ?></span>
                              </label>
                            <?php endforeach; ?>
                          </div>
                        </div>
                        <div class="md:col-span-7" id="rb-monthly-<?= htmlspecialchars($builderId) ?>" style="display:none">
                          <label class="label" for="rb-bymonthday-<?= htmlspecialchars($builderId) ?>"><?= __('Day of month') ?></label>
                          <input type="number" min="1" max="31" id="rb-bymonthday-<?= htmlspecialchars($builderId) ?>" class="input" placeholder="<?= __('e.g., 10') ?>" />
                        </div>
                        <div class="md:col-span-7" id="rb-yearly-<?= htmlspecialchars($builderId) ?>" style="display:none">
                          <div class="grid grid-cols-2 gap-2">
                            <div>
                              <label class="label" for="rb-bymonth-<?= htmlspecialchars($builderId) ?>"><?= __('Month') ?></label>
                              <input type="number" min="1" max="12" id="rb-bymonth-<?= htmlspecialchars($builderId) ?>" class="input" placeholder="1-12" />
                            </div>
                            <div>
                              <label class="label" for="rb-bymday-<?= htmlspecialchars($builderId) ?>"><?= __('Day') ?></label>
                              <input type="number" min="1" max="31" id="rb-bymday-<?= htmlspecialchars($builderId) ?>" class="input" placeholder="1-31" />
                            </div>
                          </div>
                        </div>
                        <div class="md:col-span-12 grid gap-2 md:grid-cols-12">
                          <div class="md:col-span-3">
                            <label class="label" for="rb-endtype-<?= htmlspecialchars($builderId) ?>"><?= __('Ends') ?></label>
                            <select class="select" id="rb-endtype-<?= htmlspecialchars($builderId) ?>">
                              <option value="none"><?= __('Never') ?></option>
                              <option value="count"><?= __('After # times') ?></option>
                              <option value="until"><?= __('On date') ?></option>
                            </select>
                          </div>
                          <div class="md:col-span-2" id="rb-count-wrap-<?= htmlspecialchars($builderId) ?>" style="display:none">
                            <label class="label" for="rb-count-<?= htmlspecialchars($builderId) ?>"><?= __('Count') ?></label>
                            <input type="number" min="1" id="rb-count-<?= htmlspecialchars($builderId) ?>" class="input" />
                          </div>
                          <div class="md:col-span-3" id="rb-until-wrap-<?= htmlspecialchars($builderId) ?>" style="display:none">
                            <label class="label" for="rb-until-<?= htmlspecialchars($builderId) ?>"><?= __('Until') ?></label>
                            <input type="date" id="rb-until-<?= htmlspecialchars($builderId) ?>" class="input" />
                          </div>
                          <div class="md:col-span-4 flex items-end justify-end">
                            <div class="text-xs text-slate-500" id="rb-summary-<?= htmlspecialchars($builderId) ?>"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="flex justify-end">
                      <button class="btn btn-primary">
                        <i data-lucide="calendar-plus" class="mr-2 h-4 w-4"></i>
                        <?= __('Create schedule') ?>
                      </button>
                    </div>
                  </form>
                </div>
              </details>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
              <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">
                <span><?= __('Recent activity') ?></span>
                  <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($currencyCode) ?></span>
                </div>
                <?php $recentTransactions = array_slice($transactions, 0, 5); ?>
                <?php if ($recentTransactions): ?>
                  <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php foreach ($recentTransactions as $tx):
                      $amountValue = (float)($tx['amount'] ?? 0);
                      $isDeposit = $amountValue >= 0;
                      $note = trim((string)($tx['note'] ?? ''));
                      $created = substr((string)($tx['created_at'] ?? ''), 0, 16);
                    ?>
                      <li class="flex flex-col gap-1 px-4 py-3 text-sm">
                        <div class="flex items-center justify-between">
                          <span class="font-medium <?= $isDeposit ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' ?>">
                            <?= $isDeposit ? __('Deposit') : __('Withdrawal') ?>
                          </span>
                          <span class="font-semibold text-slate-900 dark:text-white">
                            <?= moneyfmt(abs($amountValue), $currencyCode) ?>
                          </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500 dark:text-slate-400">
                          <span><?= htmlspecialchars($created) ?></span>
                          <?php if ($note !== ''): ?>
                            <span class="hidden text-slate-400 dark:text-slate-500 sm:inline">•</span>
                            <span><?= htmlspecialchars($note) ?></span>
                          <?php endif; ?>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p class="px-4 py-4 text-sm text-slate-500 dark:text-slate-400">
                    <?= __('No activity yet.') ?>
                  </p>
                <?php endif; ?>
              </div>

              <form
                method="post"
                action="/investments/delete"
                id="investment-delete-<?= $investmentId ?>"
                onsubmit="return confirm('<?= __('Delete this investment?') ?>');"
              >
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $investmentId ?>" />
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php
$chartPayloads = [];
foreach ($performanceByInvestment as $id => $payload) {
    if (empty($payload['has_rate']) || empty($payload['chart_values']) || empty($payload['chart_labels'])) {
        continue;
    }
    $chartPayloads[$id] = [
        'labels' => array_values(array_map('strval', $payload['chart_labels'])),
        'values' => array_values(array_map('floatval', $payload['chart_values'])),
    ];
}
$builderIds = ['invadd'];
foreach ($investments as $investment) {
    $builderIds[] = 'inv' . (int)($investment['id'] ?? 0);
}
$builderIds = array_values(array_unique(array_filter($builderIds)));
?>
<script>
  const rrI18n = {
    oneTime: <?= json_encode(__('One-time')) ?>,
    everyDay: <?= json_encode(__('Every :count day(s)')) ?>,
    everyWeek: <?= json_encode(__('Every :count week(s)')) ?>,
    everyMonth: <?= json_encode(__('Every :count month(s)')) ?>,
    everyYear: <?= json_encode(__('Every :count year(s)')) ?>,
    onDays: <?= json_encode(__('on :days')) ?>,
    onDayOfMonth: <?= json_encode(__('on day :day')) ?>,
    onDate: <?= json_encode(__('on :date')) ?>,
    times: <?= json_encode(__(', :count times')) ?>,
    until: <?= json_encode(__(', until :date')) ?>,
    repeats: <?= json_encode(__('Repeats')) ?>,
    dayNames: {
      MO: <?= json_encode(__('Mon')) ?>,
      TU: <?= json_encode(__('Tue')) ?>,
      WE: <?= json_encode(__('Wed')) ?>,
      TH: <?= json_encode(__('Thu')) ?>,
      FR: <?= json_encode(__('Fri')) ?>,
      SA: <?= json_encode(__('Sat')) ?>,
      SU: <?= json_encode(__('Sun')) ?>,
    },
  };

  function rrFormat(str, params = {}) {
    return (str || '').replace(/:([a-z_]+)/gi, function (_, key) {
      return params[key] ?? '';
    });
  }

  function parseRR(rule) {
    const out = { FREQ: '', INTERVAL: 1, BYDAY: [], BYMONTHDAY: null, BYMONTH: null, COUNT: null, UNTIL: null };
    if (!rule) return out;
    rule.split(';').forEach(function (part) {
      const [k, v] = part.split('=');
      if (!k || !v) return;
      if (k === 'FREQ') out.FREQ = v;
      else if (k === 'INTERVAL') out.INTERVAL = Math.max(1, parseInt(v || '1', 10));
      else if (k === 'BYDAY') out.BYDAY = v.split(',').filter(Boolean);
      else if (k === 'BYMONTHDAY') out.BYMONTHDAY = parseInt(v, 10);
      else if (k === 'BYMONTH') out.BYMONTH = parseInt(v, 10);
      else if (k === 'COUNT') out.COUNT = parseInt(v, 10);
      else if (k === 'UNTIL') out.UNTIL = v;
    });
    return out;
  }

  function rrSummary(rrule) {
    if (!rrule) return rrI18n.oneTime;
    const p = parseRR(rrule);
    if (!p.FREQ) return rrI18n.oneTime;

    const freqTemplates = {
      DAILY: rrI18n.everyDay,
      WEEKLY: rrI18n.everyWeek,
      MONTHLY: rrI18n.everyMonth,
      YEARLY: rrI18n.everyYear,
    };

    const interval = Math.max(1, parseInt(p.INTERVAL || 1, 10));
    const everyText = rrFormat(freqTemplates[p.FREQ] || '', { count: interval });

    let extras = '';
    if (p.FREQ === 'WEEKLY') {
      const days = Array.isArray(p.BYDAY) ? p.BYDAY.map(code => rrI18n.dayNames[code] || code).filter(Boolean).join(', ') : '';
      if (days) {
        extras += ' ' + rrFormat(rrI18n.onDays, { days });
      }
    }
    if (p.FREQ === 'MONTHLY' && p.BYMONTHDAY) {
      extras += ' ' + rrFormat(rrI18n.onDayOfMonth, { day: p.BYMONTHDAY });
    }
    if (p.FREQ === 'YEARLY' && p.BYMONTH) {
      const month = String(p.BYMONTH).padStart(2, '0');
      const day = p.BYMONTHDAY != null ? String(p.BYMONTHDAY).padStart(2, '0') : '';
      extras += ' ' + rrFormat(rrI18n.onDate, { date: `${month}-${day}`.replace(/-$/, '') });
    }

    let end = '';
    if (p.COUNT) {
      end = rrFormat(rrI18n.times, { count: p.COUNT });
    } else if (p.UNTIL) {
      const until = `${p.UNTIL.slice(0, 4)}-${p.UNTIL.slice(4, 6)}-${p.UNTIL.slice(6, 8)}`;
      end = rrFormat(rrI18n.until, { date: until });
    }

    const summary = `${everyText}${extras}${end}`.trim();
    return summary || rrI18n.repeats;
  }

  function wireRR(id) {
    const $ = (x) => document.getElementById(`${x}-${id}`);
    const freq = $(`rb-freq`);
    const inter = $(`rb-interval`);
    const weekly = document.getElementById(`rb-weekly-${id}`);
    const monthly = document.getElementById(`rb-monthly-${id}`);
    const yearly = document.getElementById(`rb-yearly-${id}`);
    const bydayCbs = Array.from(document.querySelectorAll(`.rb-byday-${id}`));
    const bymonthday = $(`rb-bymonthday`);
    const bymonth = $(`rb-bymonth`);
    const bymday = $(`rb-bymday`);
    const endtype = $(`rb-endtype`);
    const countWrap = document.getElementById(`rb-count-wrap-${id}`);
    const countInp = $(`rb-count`);
    const untilWrap = document.getElementById(`rb-until-wrap-${id}`);
    const untilInp = $(`rb-until`);
    const out = document.getElementById(`rrule-${id}`);
    const sum = document.getElementById(`rb-summary-${id}`);

    function toggleEnds() {
      const mode = endtype ? endtype.value : 'none';
      if (countWrap) countWrap.style.display = mode === 'count' ? '' : 'none';
      if (untilWrap) untilWrap.style.display = mode === 'until' ? '' : 'none';
      if (countInp) countInp.required = mode === 'count';
      if (untilInp) untilInp.required = mode === 'until';
      if (mode !== 'count' && countInp) countInp.value = '';
      if (mode !== 'until' && untilInp) untilInp.value = '';
    }

    function toggleFreqBlocks() {
      const f = freq ? freq.value : '';
      if (weekly) weekly.style.display = f === 'WEEKLY' ? '' : 'none';
      if (monthly) monthly.style.display = f === 'MONTHLY' ? '' : 'none';
      if (yearly) yearly.style.display = f === 'YEARLY' ? '' : 'none';
    }

    function build() {
      if (!out) return;
      let r = [];
      const f = freq ? freq.value : '';
      if (!f) {
        out.value = '';
        if (sum) sum.textContent = rrI18n.oneTime;
        return;
      }

      r.push('FREQ=' + f);
      const iv = Math.max(1, parseInt(inter?.value || '1', 10));
      if (iv > 1) r.push('INTERVAL=' + iv);

      if (f === 'WEEKLY') {
        const days = bydayCbs.filter(cb => cb.checked).map(cb => cb.value);
        if (days.length) r.push('BYDAY=' + days.join(','));
      } else if (f === 'MONTHLY') {
        const dom = parseInt(bymonthday?.value || '', 10);
        if (!isNaN(dom)) r.push('BYMONTHDAY=' + dom);
      } else if (f === 'YEARLY') {
        const mo = parseInt(bymonth?.value || '', 10);
        const dy = parseInt(bymday?.value || '', 10);
        if (!isNaN(mo)) r.push('BYMONTH=' + mo);
        if (!isNaN(dy)) r.push('BYMONTHDAY=' + dy);
      }

      const mode = endtype ? endtype.value : 'none';
      if (mode === 'count') {
        const c = parseInt(countInp?.value || '', 10);
        if (!isNaN(c) && c > 0) r.push('COUNT=' + c);
      } else if (mode === 'until') {
        const d = (untilInp?.value || '').replaceAll('-', '');
        if (d) r.push('UNTIL=' + d);
      }

      out.value = r.join(';');
      if (sum) sum.textContent = rrSummary(out.value);
    }

    if (out && out.value) {
      const p = parseRR(out.value);
      if (freq) freq.value = p.FREQ || '';
      if (inter) inter.value = p.INTERVAL || 1;
      if (bymonthday && p.BYMONTHDAY != null) bymonthday.value = p.BYMONTHDAY;
      if (bymonth && p.BYMONTH != null) bymonth.value = p.BYMONTH;
      if (bymday && p.BYMONTHDAY != null) bymday.value = p.BYMONTHDAY;
      if (Array.isArray(p.BYDAY) && bydayCbs.length) {
        const set = new Set(p.BYDAY);
        bydayCbs.forEach(cb => { cb.checked = set.has(cb.value); });
      }
      if (endtype) {
        if (p.COUNT) {
          endtype.value = 'count';
          if (countInp) countInp.value = p.COUNT;
        } else if (p.UNTIL) {
          endtype.value = 'until';
          if (untilInp && p.UNTIL.length >= 8) {
            untilInp.value = `${p.UNTIL.slice(0, 4)}-${p.UNTIL.slice(4, 6)}-${p.UNTIL.slice(6, 8)}`;
          }
        } else {
          endtype.value = 'none';
        }
      }
    }

    [freq, inter, ...bydayCbs, bymonthday, bymonth, bymday, endtype, countInp, untilInp]
      .forEach(el => el && el.addEventListener('input', build));
    if (freq) freq.addEventListener('change', () => { toggleFreqBlocks(); build(); });
    if (endtype) endtype.addEventListener('change', () => { toggleEnds(); build(); });

    toggleFreqBlocks();
    toggleEnds();
    build();
  }

  function initScheduleTabs(container) {
    const modeInput = container.querySelector('[data-schedule-mode]');
    const triggers = Array.from(container.querySelectorAll('[data-schedule-tab]'));
    const panels = Array.from(container.querySelectorAll('[data-schedule-panel]'));
    const requiredFields = Array.from(container.querySelectorAll('[data-schedule-required]'));

    const setMode = function (mode) {
      if (modeInput) modeInput.value = mode;
      triggers.forEach(function (btn) {
        const isActive = btn.getAttribute('data-schedule-tab') === mode;
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        btn.classList.toggle('btn-outline', isActive);
        btn.classList.toggle('btn-muted', !isActive);
      });
      panels.forEach(function (panel) {
        const isActive = panel.getAttribute('data-schedule-panel') === mode;
        panel.classList.toggle('hidden', !isActive);
        if (!isActive) {
          panel.setAttribute('aria-hidden', 'true');
        } else {
          panel.removeAttribute('aria-hidden');
        }
      });
      requiredFields.forEach(function (field) {
        const appliesTo = field.getAttribute('data-schedule-required');
        if (!appliesTo) return;
        field.required = appliesTo === mode;
      });
    };

    triggers.forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        const targetMode = btn.getAttribute('data-schedule-tab') || 'existing';
        setMode(targetMode);
      });
    });

    const initialMode = (modeInput && modeInput.value === 'new') ? 'new' : 'existing';
    setMode(initialMode);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const charts = <?= json_encode((object)$chartPayloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (charts && typeof charts === 'object') {
      Object.keys(charts).forEach(function (id) {
        const data = charts[id];
        if (!data || !Array.isArray(data.labels) || !Array.isArray(data.values)) {
          return;
        }
        if (typeof window.renderLineChart === 'function') {
          window.renderLineChart('investment-chart-' + id, data.labels, data.values);
        }
      });
    }

    const builders = <?= json_encode($builderIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (Array.isArray(builders)) {
      builders.forEach(function (id) {
        if (id) {
          wireRR(id);
        }
      });
    }

    document.querySelectorAll('[data-schedule-tabs]').forEach(function (container) {
      initScheduleTabs(container);
    });

    const toggleButton = document.querySelector('[data-investment-add-toggle]');
    if (toggleButton) {
      const targetSelector = toggleButton.getAttribute('data-target');
      const panel = targetSelector ? document.querySelector(targetSelector) : null;
      if (panel) {
        const closedLabel = toggleButton.querySelector('[data-add-state="closed"]');
        const openLabel = toggleButton.querySelector('[data-add-state="open"]');
        const firstField = panel.querySelector('input, select, textarea');

        const updateState = function () {
          const isOpen = panel.hasAttribute('open');
          toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
          if (closedLabel) closedLabel.classList.toggle('hidden', isOpen);
          if (openLabel) openLabel.classList.toggle('hidden', !isOpen);
        };

        toggleButton.addEventListener('click', function (event) {
          event.preventDefault();
          const isOpen = panel.hasAttribute('open');
          if (isOpen) {
            panel.removeAttribute('open');
          } else {
            panel.setAttribute('open', '');
            if (typeof panel.scrollIntoView === 'function') {
              panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (firstField) {
              window.setTimeout(function () {
                if (typeof firstField.focus === 'function') {
                  firstField.focus();
                }
              }, 150);
            }
          }
          updateState();
        });

        panel.addEventListener('toggle', updateState);
        updateState();
      }
    }
  });
</script>
