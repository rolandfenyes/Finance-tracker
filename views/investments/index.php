<?php
/** @var array $investments */
/** @var array $availableSchedules */
/** @var array $userCurrencies */
/** @var string $mainCurrency */
/** @var array $transactionsByInvestment */

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

?>

<div class="mx-auto w-full max-w-5xl space-y-6 pb-6 pt-6 sm:px-6 lg:px-0">
  <section class="card">
    <div class="card-kicker"><?= __('Investments') ?></div>
    <h1 class="card-title mt-1"><?= __('Manage your investment accounts') ?></h1>
    <p class="card-subtle mt-2 text-sm">
      <?= __('Keep track of savings accounts, ETFs, and individual stocks. Link scheduled payments to stay on autopilot.') ?>
    </p>

    <form method="post" action="/investments/add" class="mt-6 space-y-4">
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

      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <label class="label" for="investment-identifier"><?= __('Account number / Ticker') ?></label>
          <input id="investment-identifier" name="identifier" class="input" placeholder="<?= __('e.g., HU12 3456 7890 1234 or VOO') ?>" />
        </div>
        <div>
          <label class="label" for="investment-interest"><?= __('Annual rate (EBKM)') ?></label>
          <div class="relative">
            <input id="investment-interest" name="interest_rate" type="number" step="0.01" min="0" class="input pr-12" placeholder="<?= __('Optional') ?>" />
            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">%</span>
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
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
        <div>
          <label class="label" for="investment-initial-amount"><?= __('Starting balance') ?></label>
          <div class="relative">
            <input id="investment-initial-amount" name="initial_amount" type="number" step="0.01" class="input pr-16" placeholder="<?= __('Optional') ?>" />
            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">
              <?= htmlspecialchars(strtoupper($mainCurrency)) ?>
            </span>
          </div>
        </div>
      </div>

      <div>
        <label class="label" for="investment-notes"><?= __('Notes') ?></label>
        <textarea id="investment-notes" name="notes" rows="3" class="textarea" placeholder="<?= __('Add details like goals, strategy, or reminders.') ?>"></textarea>
      </div>

      <div>
        <label class="label" for="investment-schedule"><?= __('Linked scheduled payment') ?></label>
        <select id="investment-schedule" name="scheduled_payment_id" class="select">
          <option value=""><?= __('No linked schedule') ?></option>
          <?php foreach ($availableSchedules as $schedule): ?>
            <option value="<?= (int)$schedule['id'] ?>">
              <?= htmlspecialchars($schedule['title']) ?> · <?= moneyfmt($schedule['amount']) ?> <?= htmlspecialchars($schedule['currency']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-300/80">
          <?= __('Link a scheduled payment to automate contributions or transfers.') ?>
        </p>
      </div>

      <div class="flex justify-end">
        <button class="btn btn-primary">
          <i data-lucide="plus" class="mr-2 h-4 w-4"></i>
          <?= __('Add investment') ?>
        </button>
      </div>
    </form>
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

                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label class="label"><?= __('Annual rate (EBKM)') ?></label>
                    <div class="relative">
                      <input name="interest_rate" type="number" step="0.01" min="0" class="input pr-12" value="<?= htmlspecialchars($investment['interest_rate'] ?? '') ?>" />
                      <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">%</span>
                    </div>
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
