<?php
$loanPayments = $loanPayments ?? [];
$activeLoans = $activeLoans ?? [];
$finishedLoans = $finishedLoans ?? [];
$allLoans = $allLoans ?? array_merge($activeLoans, $finishedLoans);
?>

<section class="card">
  <h1 class="text-xl font-semibold"><?= __('Loans') ?></h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= __('Add loan') ?></summary>

    <form class="mt-4 grid gap-4 lg:grid-cols-12" method="post" action="/loans/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <!-- Loan details -->
      <div class="card lg:col-span-7">
        <h3 class="font-semibold mb-4"><?= __('Loan details') ?></h3>
        <div class="grid sm:grid-cols-12 gap-3">
          <div class="field sm:col-span-6">
            <label class="label"><?= __('Name') ?></label>
            <input name="name" class="input" placeholder="<?= __('e.g., Car loan') ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= __('Principal') ?></label>
            <input name="principal" type="number" step="0.01" class="input" placeholder="0.00" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= __('APR %') ?></label>
            <input name="interest_rate" type="number" step="0.001" class="input" placeholder="e.g., 8.5" required />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('Start date') ?></label>
            <input name="start_date" type="date" class="input" required />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= __('End date (optional)') ?></label>
            <input name="end_date" type="date" class="input" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('Pay day (1–31)') ?></label>
            <input name="payment_day" type="number" min="1" max="31" class="input" placeholder="<?= __('e.g., 10') ?>" />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= __('Extra monthly payment') ?></label>
            <input name="extra_payment" type="number" step="0.01" class="input" placeholder="0.00" />
          </div>

          <!-- NEW: Loan currency -->
          <div class="field sm:col-span-6">
            <label class="label"><?= __('Loan currency') ?></label>
            <select name="loan_currency" id="loan-currency-select" class="select">
              <?php foreach ($userCurrencies as $uc): ?>
                <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                  <?= htmlspecialchars($uc['code']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="help"><?= __('Used for principal, balance and payments recorded against the loan.') ?></p>
          </div>
        </div>
        <div class="mt-3 flex flex-col gap-3">
          <div class="field sm:col-span-6">
            <label class="label"><?= __('Insurance per month (excluded from progress)') ?></label>
            <input name="insurance_monthly" type="number" step="0.01" class="input" placeholder="0.00" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('History') ?></label>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="history_confirmed" value="1" />
              <span><?= __('I’ve kept up with every scheduled payment since the start date') ?></span>
            </label>
            <p class="help"><?= __('When checked, progress is computed from the amortization schedule up to today.') ?></p>
          </div>
        </div>

      </div>

      <!-- Schedule -->
      <div class="card lg:col-span-5">
        <h3 class="font-semibold mb-4"><?= __('Repayment schedule') ?></h3>

        <div class="field">
          <label class="label"><?= __('Link existing schedule') ?></label>
          <select name="scheduled_payment_id" class="select">
            <option value=""><?= __('— None —') ?></option>
            <?php foreach($scheduledList as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>">
                <?= htmlspecialchars($sp['title']) ?> (<?= moneyfmt($sp['amount'], $sp['currency']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <p class="help"><?= __('Pick an already created scheduled payment to link.') ?></p>
        </div>

        <div class="my-3 flex items-center gap-3 text-xs text-gray-400">
          <div class="h-px flex-1 bg-gray-200"></div><span><?= __('or') ?></span><div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <div class="space-y-3">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="create_schedule" value="1" />
            <span><?= __('Create a monthly schedule from this loan') ?></span>
          </label>

          <div class="grid sm:grid-cols-12 gap-3">
            <div class="field sm:col-span-6">
              <label class="label"><?= __('First due') ?></label>
              <input name="first_due" type="date" class="input" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= __('Due day') ?></label>
              <input name="due_day" type="number" min="1" max="31" class="input" placeholder="<?= __('e.g., 10') ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= __('Monthly amount') ?></label>
              <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="<?= __('Auto-calc if empty') ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= __('Schedule currency') ?></label>
              <select name="currency" id="schedule-currency-select" class="select">
                <?php foreach ($userCurrencies as $uc): ?>
                  <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                    <?= htmlspecialchars($uc['code']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="help"><?= __('Defaults to the loan currency.') ?></p>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-12 flex justify-end">
        <button class="btn btn-primary"><?= __('Save') ?></button>
      </div>
    </form>
  </details>

  <script>
    // Keep schedule currency in sync when the loan currency changes (only if user hasn't touched it)
    (function(){
      const loanCur = document.getElementById('loan-currency-select');
      const schedCur = document.getElementById('schedule-currency-select');
      if (!loanCur || !schedCur) return;
      let userTouchedSched = false;
      schedCur.addEventListener('change', () => userTouchedSched = true);
      loanCur.addEventListener('change', () => {
        if (!userTouchedSched) schedCur.value = loanCur.value;
      });
    })();
  </script>


</section>

<section class="mt-6 card">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold"><?= __('Loans') ?></h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="table-glass min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3 w-[38%]"><?= __('Loan') ?></th>
          <th class="py-2 pr-3 w-[18%]"><?= __('Balance') ?></th>
          <th class="py-2 pr-3 w-[24%]"><?= __('Schedule') ?></th>
          <th class="py-2 pr-3 w-[20%] text-right"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($activeLoans as $l):
        $cur   = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
        $prin  = (float)($l['principal'] ?? 0);
        $bal   = (float)($l['_est_balance'] ?? ($l['balance'] ?? 0));
        $paid  = (float)($l['_principal_paid'] ?? max(0, $prin - $bal));
        $pct   = (float)($l['_progress_pct'] ?? ($prin>0?($paid/$prin*100):0));
        $isPaidOff = !empty($l['_is_paid_off']);
        $months = 0;
        if (!empty($l['start_date']) && !empty($l['end_date'])) {
          $a = new DateTime($l['start_date']); $b = new DateTime($l['end_date']);
          $d = $a->diff($b); $months = $d->y*12 + $d->m + ($d->d>0?1:0);
        }
      ?>
        <tr class="border-b align-top <?= $isPaidOff ? 'bg-emerald-50/60 dark:bg-emerald-500/5' : '' ?>">
          <td class="py-3 pr-3">
            <div class="font-medium flex items-center gap-2">
              <?= htmlspecialchars($l['name']) ?>
              <span class="text-xs text-gray-500"><?= __('· APR :rate%', ['rate' => (float)$l['interest_rate']]) ?></span>
              <?php if ($isPaidOff): ?>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                  <span aria-hidden="true">🎉</span>
                  <?= __('Paid off') ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="text-xs text-gray-500">
              <?= __(':start → :end', [
                'start' => htmlspecialchars($l['start_date']),
                'end' => htmlspecialchars($l['end_date'] ?? '—'),
              ]) ?>
              <?php if ($months): ?> · <?= __(':months mo', ['months' => $months]) ?><?php endif; ?>
              <?php if (!empty($l['history_confirmed'])): ?>
                <span class="ml-1 text-brand-600">✔ <?= __('history confirmed') ?></span>
              <?php endif; ?>
            </div>

            <!-- progress -->
            <div class="mt-2">
              <div class="h-2 bg-brand-100/60 rounded-full">
                <div class="h-2 bg-brand-500 rounded-full" style="width: <?= number_format(min(100,max(0,$pct)),2,'.','') ?>%"></div>
              </div>
              <div class="mt-1 text-xs text-gray-600">
                <?= __(':paid paid of :total (:percent%)', [
                  'paid' => moneyfmt($paid,$cur),
                  'total' => moneyfmt($prin,$cur),
                  'percent' => number_format($pct,1),
                ]) ?>
                <br> <?= __('Est. balance :amount', ['amount' => moneyfmt($bal,$cur)]) ?>
              </div>
              <?php if (!empty($l['history_confirmed']) && $l['_interest_paid'] !== null): ?>
                <div class="text-[11px] text-gray-500"><?= __('Estimated interest so far: :amount', ['amount' => moneyfmt($l['_interest_paid'],$cur)]) ?></div>
              <?php endif; ?>
              <?php if ($isPaidOff): ?>
                <div class="mt-3 flex items-start gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-xs text-emerald-700 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                  <span aria-hidden="true" class="text-base leading-none">✅</span>
                  <div class="space-y-0.5">
                    <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-100"><?= __('Loan complete!') ?></div>
                    <div><?= __('Congrats on clearing this debt.') ?></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </td>

          <td class="py-3 pr-3 whitespace-nowrap align-middle">
            <div class="text-sm text-gray-500"><?= __('Balance') ?></div>
            <div class="font-semibold"><?= moneyfmt($bal, $cur) ?></div>
          </td>

          <td class="py-3 pr-3 align-middle">
            <?php if (!empty($l['scheduled_payment_id'])): ?>
              <div class="flex items-center gap-2">
                <span class="chip"> <?= htmlspecialchars($l['sched_title']) ?> </span>
              </div>
              <?php if (isset($l['sched_next_due'])): ?>
                <div class="text-xs text-gray-500 mt-1"><?= __('Next: :date', ['date' => htmlspecialchars($l['sched_next_due'])]) ?></div>
              <?php endif; ?>
              <?php if (!empty($l['sched_rrule'])): ?>
                <span class="rrule-summary text-[11px] text-gray-400 mt-1"
                      data-rrule="<?= htmlspecialchars($l['sched_rrule']) ?>"></span>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-xs text-gray-500"><?= __('No schedule') ?></div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 text-right align-middle">
            <div class="flex justify-end gap-2">
              <button class="icon-action" data-open="#loan-history-<?= (int)$l['id'] ?>" title="<?= __('View history') ?>">
                <i data-lucide="history" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('View history') ?></span>
              </button>
              <?php if ($isPaidOff): ?>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                  <span aria-hidden="true">🌟</span>
                  <?= __('Finished') ?>
                </span>
              <?php else: ?>
                <button type="button"
                        class="btn btn-primary !px-3"
                        data-open="#loan-pay-<?= (int)$l['id'] ?>">
                  <?= __('Record Payment') ?>
                </button>
              <?php endif; ?>
              <button type="button"
                      class="btn !px-3"
                      data-open="#loan-edit-<?= (int)$l['id'] ?>">
                <?= __('Edit') ?>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; if (!count($activeLoans)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500"><?= __('No loans yet.') ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="md:hidden space-y-3">
    <?php foreach($activeLoans as $l):
      $cur   = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
      $prin  = (float)($l['principal'] ?? 0);
      $bal   = (float)($l['_est_balance'] ?? ($l['balance'] ?? 0));
      $paid  = (float)($l['_principal_paid'] ?? max(0, $prin - $bal));
      $pct   = (float)($l['_progress_pct'] ?? ($prin>0?($paid/$prin*100):0));
      $isPaidOff = !empty($l['_is_paid_off']);
    ?>
      <div class="panel p-4 <?= $isPaidOff ? 'border-emerald-300/60 bg-emerald-50/60 dark:border-emerald-500/40 dark:bg-emerald-500/10' : '' ?>">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($l['name']) ?></div>
            <div class="text-xs text-gray-500"><?= __('APR :rate%', ['rate' => (float)$l['interest_rate']]) ?></div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="icon-action" data-open="#loan-history-<?= (int)$l['id'] ?>" title="<?= __('View history') ?>">
              <i data-lucide="history" class="h-4 w-4"></i>
              <span class="sr-only"><?= __('View history') ?></span>
            </button>
            <button type="button" class="icon-action icon-action--primary" data-open="#loan-edit-<?= (int)$l['id'] ?>" title="<?= __('Edit') ?>">
              <i data-lucide="pencil" class="h-4 w-4"></i>
              <span class="sr-only"><?= __('Edit') ?></span>
            </button>
          </div>
        </div>

        <div class="mt-2 text-xs text-gray-500">
          <?= __(':start → :end', [
            'start' => htmlspecialchars($l['start_date']),
            'end' => htmlspecialchars($l['end_date'] ?? '—'),
          ]) ?>
          <?php if (!empty($l['history_confirmed'])): ?>
            · <span class="text-brand-600">✔ <?= __('history') ?></span>
          <?php endif; ?>
        </div>

        <div class="mt-3">
          <div class="h-2 bg-brand-100/60 rounded-full">
            <div class="h-2 bg-brand-500 rounded-full" style="width: <?= number_format(min(100,max(0,$pct)),2,'.','') ?>%"></div>
          </div>
          <div class="mt-1 text-xs text-gray-600">
            <?= moneyfmt($paid,$cur) ?> / <?= moneyfmt($prin,$cur) ?>
            <br> <?= __('Balance :amount', ['amount' => moneyfmt($bal,$cur)]) ?>
          </div>
        </div>

        <?php if ($isPaidOff): ?>
          <div class="mt-3 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-3 py-2 text-xs text-emerald-700 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
            <div class="font-semibold text-sm text-emerald-700 dark:text-emerald-100"><?= __('Loan complete!') ?></div>
            <div><?= __('Congrats on clearing this debt.') ?></div>
          </div>
        <?php endif; ?>

        <div class="mt-3 text-xs text-gray-600">
          <?php if (!empty($l['scheduled_payment_id'])): ?>
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip"><?= htmlspecialchars($l['sched_title']) ?></span>
              <?php if (isset($l['sched_next_due'])): ?>
                <span class="text-gray-500">Next: <?= htmlspecialchars($l['sched_next_due']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($l['sched_rrule'])): ?>
              <div class="rrule-summary text-[11px] text-gray-400 mt-1"
                   data-rrule="<?= htmlspecialchars($l['sched_rrule']) ?>"></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-gray-500"><?= __('No schedule') ?></div>
          <?php endif; ?>
        </div>

        <div class="mt-3 flex flex-col gap-2">
          <?php if ($isPaidOff): ?>
            <span class="inline-flex items-center justify-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
              <span aria-hidden="true">🌟</span>
              <?= __('Finished') ?>
            </span>
          <?php else: ?>
            <button type="button" class="btn btn-primary" data-open="#loan-pay-<?= (int)$l['id'] ?>"><?= __('Record Payment') ?></button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (count($finishedLoans)): ?>
<section class="mt-6 card">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold"><?= __('Finished loans') ?></h2>
  </div>
  <p class="text-sm text-gray-500"><?= __('These loans have been paid off and are kept for history. Payments and loan details are locked.') ?></p>

  <div class="hidden md:block overflow-x-auto mt-4">
    <table class="table-glass min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3 w-[38%]"><?= __('Loan') ?></th>
          <th class="py-2 pr-3 w-[18%]"><?= __('Balance') ?></th>
          <th class="py-2 pr-3 w-[24%]"><?= __('Schedule') ?></th>
          <th class="py-2 pr-3 w-[20%] text-right"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($finishedLoans as $l):
        $cur   = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
        $prin  = (float)($l['principal'] ?? 0);
        $bal   = 0.0;
        $paid  = (float)($l['_principal_paid'] ?? $prin);
        $pct   = 100.0;
        $finishedAt = $l['finished_at'] ?? null;
      ?>
        <tr class="border-b align-top bg-emerald-50/50 dark:bg-emerald-500/5">
          <td class="py-3 pr-3">
            <div class="font-medium flex items-center gap-2">
              <?= htmlspecialchars($l['name']) ?>
              <span class="text-xs text-gray-500"><?= __('· APR :rate%', ['rate' => (float)$l['interest_rate']]) ?></span>
              <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                <span aria-hidden="true">🎉</span>
                <?= __('Paid off') ?>
              </span>
            </div>
            <div class="text-xs text-gray-500">
              <?= __(':start → :end', [
                'start' => htmlspecialchars($l['start_date']),
                'end' => htmlspecialchars($l['end_date'] ?? '—'),
              ]) ?>
              <?php if ($finishedAt): ?>
                · <?= __('Finished on :date', ['date' => htmlspecialchars(date('Y-m-d', strtotime($finishedAt)))]) ?>
              <?php endif; ?>
            </div>

            <div class="mt-2">
              <div class="h-2 bg-brand-100/60 rounded-full">
                <div class="h-2 bg-brand-500 rounded-full" style="width: 100%"></div>
              </div>
              <div class="mt-1 text-xs text-gray-600">
                <?= __(':paid paid of :total (:percent%)', [
                  'paid' => moneyfmt($paid,$cur),
                  'total' => moneyfmt($prin,$cur),
                  'percent' => number_format($pct,1),
                ]) ?>
                <br> <?= __('Est. balance :amount', ['amount' => moneyfmt($bal,$cur)]) ?>
              </div>
              <div class="mt-3 flex items-start gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-xs text-emerald-700 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                <span aria-hidden="true" class="text-base leading-none">✅</span>
                <div class="space-y-0.5">
                  <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-100"><?= __('Loan complete!') ?></div>
                  <div><?= __('Congrats on clearing this debt.') ?></div>
                </div>
              </div>
            </div>
          </td>

          <td class="py-3 pr-3 whitespace-nowrap align-middle">
            <div class="text-sm text-gray-500"><?= __('Balance') ?></div>
            <div class="font-semibold"><?= moneyfmt($bal, $cur) ?></div>
          </td>

          <td class="py-3 pr-3 align-middle">
            <?php if (!empty($l['scheduled_payment_id'])): ?>
              <div class="flex items-center gap-2">
                <span class="chip"> <?= htmlspecialchars($l['sched_title']) ?> </span>
              </div>
              <div class="text-xs text-gray-500 mt-1"><?= __('Finished') ?></div>
            <?php else: ?>
              <div class="text-xs text-gray-500"><?= __('No schedule') ?></div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 text-right align-middle">
            <div class="flex justify-end gap-2">
              <button class="icon-action" data-open="#loan-history-<?= (int)$l['id'] ?>" title="<?= __('View history') ?>">
                <i data-lucide="history" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('View history') ?></span>
              </button>
              <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                <span aria-hidden="true">🌟</span>
                <?= __('Finished') ?>
              </span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="md:hidden space-y-3 mt-4">
    <?php foreach($finishedLoans as $l):
      $cur   = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
      $prin  = (float)($l['principal'] ?? 0);
      $paid  = (float)($l['_principal_paid'] ?? $prin);
      $finishedAt = $l['finished_at'] ?? null;
    ?>
      <div class="panel p-4 border-emerald-300/60 bg-emerald-50/60 dark:border-emerald-500/40 dark:bg-emerald-500/10">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($l['name']) ?></div>
            <div class="text-xs text-gray-500"><?= __('APR :rate%', ['rate' => (float)$l['interest_rate']]) ?></div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="icon-action" data-open="#loan-history-<?= (int)$l['id'] ?>" title="<?= __('View history') ?>">
              <i data-lucide="history" class="h-4 w-4"></i>
              <span class="sr-only"><?= __('View history') ?></span>
            </button>
          </div>
        </div>

        <div class="mt-2 text-xs text-gray-500">
          <?= __(':start → :end', [
            'start' => htmlspecialchars($l['start_date']),
            'end' => htmlspecialchars($l['end_date'] ?? '—'),
          ]) ?>
          <?php if ($finishedAt): ?>
            · <?= __('Finished :date', ['date' => htmlspecialchars(date('Y-m-d', strtotime($finishedAt)))]) ?>
          <?php endif; ?>
        </div>

        <div class="mt-3">
          <div class="h-2 bg-brand-100/60 rounded-full">
            <div class="h-2 bg-brand-500 rounded-full" style="width: 100%"></div>
          </div>
          <div class="mt-1 text-xs text-gray-600">
            <?= moneyfmt($paid,$cur) ?> / <?= moneyfmt($prin,$cur) ?>
            <br> <?= __('Balance :amount', ['amount' => moneyfmt(0,$cur)]) ?>
          </div>
        </div>

        <div class="mt-3 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-3 py-2 text-xs text-emerald-700 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
          <div class="font-semibold text-sm text-emerald-700 dark:text-emerald-100"><?= __('Loan complete!') ?></div>
          <div><?= __('Congrats on clearing this debt.') ?></div>
        </div>

        <div class="mt-3 text-xs text-gray-600">
          <?php if (!empty($l['scheduled_payment_id'])): ?>
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip"><?= htmlspecialchars($l['sched_title']) ?></span>
              <span class="text-gray-500"><?= __('Finished') ?></span>
            </div>
          <?php else: ?>
            <div class="text-gray-500"><?= __('No schedule') ?></div>
          <?php endif; ?>
        </div>

        <div class="mt-3 flex flex-col gap-2">
          <span class="inline-flex items-center justify-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
            <span aria-hidden="true">🌟</span>
            <?= __('Finished') ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php foreach($allLoans as $l):
  $curList = $userCurrencies ?? [['code'=>'HUF','is_main'=>true]];
  $loanCurrency = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
  $loanTxList = $loanPayments[(int)$l['id']] ?? [];
  $loanLocked = !empty($l['_is_locked']);
?>
<?php if (!$loanLocked): ?>
<div id="loan-edit-<?= (int)$l['id'] ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="loan-edit-title-<?= (int)$l['id'] ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel overflow-hidden">
    <div class="modal-header">
      <h3 id="loan-edit-title-<?= (int)$l['id'] ?>" class="font-semibold"><?= __('Edit loan') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="modal-body flex flex-col gap-6">
      <form method="post" action="/loans/edit" id="loan-form-<?= (int)$l['id'] ?>" class="grid gap-4 md:grid-cols-12">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />

      <div class="md:col-span-7 space-y-3">
        <div class="grid sm:grid-cols-12 gap-3">
          <div class="field sm:col-span-6">
            <label class="label"><?= __('Name') ?></label>
            <input name="name" class="input" value="<?= htmlspecialchars($l['name']) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= __('Principal') ?></label>
            <input name="principal" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['principal']) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= __('APR %') ?></label>
            <input name="interest_rate" type="number" step="0.001" class="input" value="<?= htmlspecialchars($l['interest_rate']) ?>" required />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('Start date') ?></label>
            <input name="start_date" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" required />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= __('End date (optional)') ?></label>
            <input name="end_date" type="date" class="input" value="<?= htmlspecialchars($l['end_date'] ?? '') ?>" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('Pay day (1–31)') ?></label>
            <input name="payment_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= __('Extra monthly payment') ?></label>
            <input name="extra_payment" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['extra_payment'] ?? 0) ?>" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('Loan currency') ?></label>
            <select name="loan_currency" class="select">
              <?php foreach ($curList as $uc): $code=$uc['code']; ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                  <?= htmlspecialchars($code) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= __('Insurance / month') ?></label>
            <input name="insurance_monthly" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['insurance_monthly'] ?? 0) ?>" />
          </div>
        </div>

        <div class="field">
          <label class="label"><?= __('History') ?></label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="history_confirmed" value="1" <?= !empty($l['history_confirmed'])?'checked':'' ?> />
            <span><?= __('I’ve kept up with every scheduled payment since the start date') ?></span>
          </label>
        </div>
      </div>

      <div class="md:col-span-5 grid gap-4">

        <h4 class="font-semibold"><?= __('Repayment schedule') ?></h4>

        <?php if (!empty($l['scheduled_payment_id'])): ?>
          <div class="rounded-xl border p-3 bg-gray-50" id="loan-linked-card-<?= (int)$l['id'] ?>">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-medium"><?= htmlspecialchars($l['sched_title'] ?? __('Linked schedule')) ?></div>
                <div class="text-xs text-gray-600">
                  <?php if (isset($l['sched_amount'], $l['sched_currency'])): ?>
                    <?= moneyfmt((float)$l['sched_amount'], $l['sched_currency']) ?>
                  <?php endif; ?>
                  <?php if (!empty($l['sched_rrule'])): ?>
                    · <span class="rrule-summary" data-rrule="<?= htmlspecialchars($l['sched_rrule']) ?>"></span>
                  <?php endif; ?>
                  <?php if (!empty($l['sched_next_due'])): ?>
                    · <?= __('next :date', ['date' => htmlspecialchars($l['sched_next_due'])]) ?>
                  <?php endif; ?>
                </div>
              </div>
              <form method="post" action="/loans/edit" class="shrink-0" id="loan-unlink-form-<?= (int)$l['id'] ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />
                <input type="hidden" name="unlink_schedule" value="1" />
                <button class="btn btn-danger !py-1 !px-3" data-unlink-loan="<?= (int)$l['id'] ?>"><?= __('Unlink') ?></button>
              </form>
            </div>
          </div>

          <div class="hidden" id="loan-link-wrap-<?= (int)$l['id'] ?>">
            <form method="post" action="/loans/edit" class="grid gap-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />
              <label class="label"><?= __('Link existing schedule') ?></label>
              <select name="scheduled_payment_id" class="select">
                <option value=""><?= __('— None —') ?></option>
                <?php foreach($scheduledList as $sp): ?>
                  <option value="<?= (int)$sp['id'] ?>">
                    <?= htmlspecialchars($sp['title']) ?>
                    (<?= moneyfmt($sp['amount'], $sp['currency']) ?><?= $sp['next_due'] ? ', next '.$sp['next_due'] : '' ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="flex justify-end"><button class="btn"><?= __('Apply') ?></button></div>
            </form>

            <div class="my-2 flex items-center gap-3 text-xs text-gray-400">
              <div class="h-px flex-1 bg-gray-200"></div><span><?= __('or') ?></span><div class="h-px flex-1 bg-gray-200"></div>
            </div>

            <form method="post" action="/loans/edit" class="grid sm:grid-cols-12 gap-3">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />
              <input type="hidden" name="create_schedule" value="1" />
              <div class="sm:col-span-6">
                <label class="label"><?= __('First due') ?></label>
                <input name="first_due" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" />
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Due day') ?></label>
                <input name="due_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Monthly amount') ?></label>
                <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="<?= __('Auto-calc if empty') ?>" />
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Currency') ?></label>
                <select name="currency" class="select">
                  <?php foreach (($userCurrencies ?? [['code'=>'HUF','is_main'=>true]]) as $uc): $code=$uc['code']; ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                      <?= htmlspecialchars($code) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="help"><?= __('Defaults to the loan currency.') ?></p>
              </div>
              <div class="sm:col-span-12 flex justify-end">
                <button class="btn btn-primary"><?= __('Create schedule') ?></button>
              </div>
            </form>
          </div>

        <?php else: ?>
          <form method="post" action="/loans/edit" class="grid gap-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />
            <label class="label"><?= __('Link existing schedule') ?></label>
            <select name="scheduled_payment_id" class="select">
              <option value=""><?= __('— None —') ?></option>
              <?php foreach($scheduledList as $sp): ?>
                <option value="<?= (int)$sp['id'] ?>">
                  <?= htmlspecialchars($sp['title']) ?>
                  (<?= moneyfmt($sp['amount'], $sp['currency']) ?><?= $sp['next_due'] ? ', next '.$sp['next_due'] : '' ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="flex justify-end"><button class="btn"><?= __('Apply') ?></button></div>
          </form>

          <div class="my-2 flex items-center gap-3 text-xs text-gray-400">
            <div class="h-px flex-1 bg-gray-200"></div><span><?= __('or') ?></span><div class="h-px flex-1 bg-gray-200"></div>
          </div>

          <form method="post" action="/loans/edit" class="grid sm:grid-cols-12 gap-3">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />
            <input type="hidden" name="create_schedule" value="1" />
            <div class="sm:col-span-6">
              <label class="label"><?= __('First due') ?></label>
              <input name="first_due" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" />
            </div>
            <div class="sm:col-span-6">
              <label class="label"><?= __('Due day') ?></label>
              <input name="due_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
            </div>
            <div class="sm:col-span-6">
              <label class="label"><?= __('Monthly amount') ?></label>
              <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="<?= __('Auto-calc if empty') ?>" />
            </div>
            <div class="sm:col-span-6">
              <label class="label"><?= __('Currency') ?></label>
              <select name="currency" class="select">
                <?php foreach (($userCurrencies ?? [['code'=>'HUF','is_main'=>true]]) as $uc): $code=$uc['code']; ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                    <?= htmlspecialchars($code) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="help"><?= __('Defaults to the loan currency.') ?></p>
            </div>
            <div class="sm:col-span-12 flex justify-end">
              <button class="btn btn-primary"><?= __('Create schedule') ?></button>
            </div>
          </form>
        <?php endif; ?>

      </div>

      </form>

      <section class="rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 text-sm text-rose-600 dark:border-rose-500/50 dark:bg-rose-500/10 dark:text-rose-200">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="space-y-1">
            <h4 class="text-base font-semibold"><?= __('Danger zone') ?></h4>
            <p class="text-sm text-rose-500 dark:text-rose-200/80">
              <?= __('Deleting this loan will remove its payment history.') ?>
            </p>
          </div>
          <form
            method="post"
            action="/loans/delete"
            onsubmit="return confirm('<?= __('Delete this loan?') ?>')"
            class="sm:shrink-0"
          >
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />
            <button class="btn btn-danger w-full sm:w-auto">
              <?= __('Delete loan') ?>
            </button>
          </form>
        </div>
      </section>
    </div>

    <div class="modal-footer">
      <div class="flex flex-row flex-wrap gap-2 justify-end">
        <button class="btn" data-close><?= __('Cancel') ?></button>
        <button class="btn btn-primary" form="loan-form-<?= (int)$l['id'] ?>"><?= __('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<div id="loan-pay-<?= (int)$l['id'] ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="loan-pay-title-<?= (int)$l['id'] ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel">
    <div class="modal-header">
      <h3 id="loan-pay-title-<?= (int)$l['id'] ?>" class="font-semibold"><?= __('Record Payment') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="modal-body">
      <form
        id="loan-pay-form-<?= (int)$l['id'] ?>"
        class="grid gap-3 sm:grid-cols-12"
        method="post"
        action="/loans/payment/add"
      >
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>" />
        <input type="hidden" name="currency" value="<?= htmlspecialchars($loanCurrency) ?>" />
        <div class="sm:col-span-5">
          <label class="label"><?= __('Date') ?></label>
          <input
            name="paid_on"
            type="date"
            value="<?= date('Y-m-d') ?>"
            class="input"
          >
        </div>
        <div class="sm:col-span-5">
          <label class="label"><?= __('Amount (:currency)', ['currency' => htmlspecialchars($loanCurrency)]) ?></label>
          <input
            name="amount"
            type="number"
            step="0.01"
            placeholder="<?= __('Payment amount') ?>"
            class="input"
            required
          >
        </div>
        <div class="sm:col-span-5">
          <label class="label"><?= __('Interest portion (optional)') ?></label>
          <input
            name="interest_component"
            type="number"
            step="0.01"
            placeholder="0.00"
            class="input"
          >
        </div>
      </form>
    </div>

    <div class="modal-footer">
      <div class="flex flex-row flex-wrap gap-2 justify-end">
        <button type="button" class="btn" data-close><?= __('Cancel') ?></button>
        <button class="btn btn-primary" form="loan-pay-form-<?= (int)$l['id'] ?>"><?= __('Record Payment') ?></button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<div id="loan-history-<?= (int)$l['id'] ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="loan-history-title-<?= (int)$l['id'] ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel">
    <div class="modal-header">
      <h3 id="loan-history-title-<?= (int)$l['id'] ?>" class="font-semibold"><?= __('Payment history') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="modal-body">
      <?php if ($loanTxList): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                <th class="py-2 pr-3"><?= __('Date') ?></th>
                <th class="py-2 pr-3"><?= __('Amount') ?></th>
                <th class="py-2 pr-3"><?= __('Breakdown') ?></th>
                <th class="py-2 pr-0 text-right"><?= $loanLocked ? __('Status') : __('Actions') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($loanTxList as $tx): $txId=(int)$tx['id']; $txCur = $tx['currency'] ?: $loanCurrency; ?>
              <tr class="border-t">
                <td class="py-2 pr-3 align-middle text-sm"><?= htmlspecialchars($tx['paid_on']) ?></td>
                <td class="py-2 pr-3 align-middle text-sm">
                  <?= moneyfmt((float)$tx['amount'], $txCur) ?>
                  <?php if ($txCur && strtoupper($txCur) !== strtoupper($loanCurrency)): ?>
                    <span class="text-xs text-gray-500">(<?= htmlspecialchars($txCur) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 pr-3 align-middle text-xs text-gray-600">
                  <?= __('Principal: :amount', ['amount' => moneyfmt((float)$tx['principal_component'], $txCur)]) ?> ·
                  <?= __('Interest: :amount', ['amount' => moneyfmt((float)$tx['interest_component'], $txCur)]) ?>
                </td>
                <td class="py-2 pr-0 align-middle text-right">
                  <?php if ($loanLocked): ?>
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                      <span aria-hidden="true">🔒</span>
                      <?= __('Locked') ?>
                    </span>
                  <?php else: ?>
                    <div class="flex justify-end gap-2">
                      <button type="button" class="btn !px-3" data-open="#loan-payment-edit-<?= $txId ?>"><?= __('Edit') ?></button>
                      <form method="post" action="/loans/payment/delete" onsubmit="return confirm('<?= __('Delete this payment?') ?>');">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                        <input type="hidden" name="id" value="<?= $txId ?>" />
                        <button class="btn btn-danger !px-3" type="submit"><?= __('Delete') ?></button>
                      </form>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-500"><?= __('No payments recorded yet.') ?></p>
      <?php endif; ?>
    </div>

    <div class="modal-footer">
      <div class="flex justify-end">
        <button type="button" class="btn" data-close><?= __('Close') ?></button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php foreach($allLoans as $l):
  $loanCurrency = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
  $loanTxList = $loanPayments[(int)$l['id']] ?? [];
  if (!empty($l['_is_locked'])) { continue; }
?>
  <?php foreach ($loanTxList as $tx): $txId=(int)$tx['id']; $txCur = $tx['currency'] ?: $loanCurrency; ?>
    <div id="loan-payment-edit-<?= $txId ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="loan-payment-edit-title-<?= $txId ?>">
      <div class="modal-backdrop" data-close></div>
      <div class="modal-panel">
        <div class="modal-header">
          <h3 id="loan-payment-edit-title-<?= $txId ?>" class="font-semibold"><?= __('Edit payment') ?></h3>
          <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>
        <div class="modal-body">
          <form method="post" action="/loans/payment/update" id="loan-payment-form-<?= $txId ?>" class="grid gap-3 sm:grid-cols-12">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= $txId ?>" />
            <div class="sm:col-span-5">
              <label class="label"><?= __('Date') ?></label>
              <input name="paid_on" type="date" class="input" value="<?= htmlspecialchars($tx['paid_on']) ?>" required />
            </div>
            <div class="sm:col-span-5">
              <label class="label"><?= __('Amount') ?></label>
              <input name="amount" type="number" step="0.01" class="input" value="<?= htmlspecialchars($tx['amount']) ?>" required />
            </div>
            <div class="sm:col-span-5">
              <label class="label"><?= __('Interest portion') ?></label>
              <input name="interest_component" type="number" step="0.01" class="input" value="<?= htmlspecialchars($tx['interest_component']) ?>" />
            </div>
            <div class="sm:col-span-4">
              <label class="label"><?= __('Currency') ?></label>
              <select name="currency" class="select">
                <option value=""><?= __('Default') ?></option>
                <?php foreach (($userCurrencies ?? [['code'=>'HUF','is_main'=>true]]) as $uc): $code=$uc['code']; ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($code)===strtoupper($txCur)?'selected':'' ?>>
                    <?= htmlspecialchars($code) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <div class="flex flex-row flex-wrap gap-2 justify-end">
            <button type="button" class="btn" data-close><?= __('Cancel') ?></button>
            <button class="btn btn-primary" form="loan-payment-form-<?= $txId ?>"><?= __('Save changes') ?></button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>



<script>
const loanRruleText = {
  everyWeeks: <?= json_encode(__('Every :count week(s)')) ?>,
  everyMonths: <?= json_encode(__('Every :count month(s)')) ?>,
  everyDays: <?= json_encode(__('Every :count day(s)')) ?>,
  everyYears: <?= json_encode(__('Every :count year(s)')) ?>,
  onDay: <?= json_encode(__(' on day :day')) ?>,
  onDays: <?= json_encode(__(' on :days')) ?>,
  onDate: <?= json_encode(__(' on :date')) ?>,
  times: <?= json_encode(__(', :count times')) ?>,
  until: <?= json_encode(__(', until :date')) ?>,
  oneTime: <?= json_encode(__('One-time')) ?>,
  repeats: <?= json_encode(__('Repeats')) ?>,
};
const loanDayNames = <?= json_encode([
  'MO' => __('Mon'),
  'TU' => __('Tue'),
  'WE' => __('Wed'),
  'TH' => __('Thu'),
  'FR' => __('Fri'),
  'SA' => __('Sat'),
  'SU' => __('Sun'),
]) ?>;
const loanReplace = (tpl, replacements) => {
  if (!tpl) return '';
  let out = tpl;
  for (const [key, value] of Object.entries(replacements || {})) {
    out = out.replace(new RegExp(':'+key, 'g'), String(value ?? ''));
  }
  return out;
};

function parseRR(rule){
  const out = { FREQ:'', INTERVAL:1, BYDAY:[], BYMONTHDAY:null, BYMONTH:null, COUNT:null, UNTIL:null };
  if (!rule) return out;
  rule.split(';').forEach(part=>{
    const [k,v] = part.split('=');
    if (!k || !v) return;
    if (k==='FREQ') out.FREQ = v;
    else if (k==='INTERVAL') out.INTERVAL = Math.max(1, parseInt(v||'1',10));
    else if (k==='BYDAY') out.BYDAY = v.split(',').filter(Boolean);
    else if (k==='BYMONTHDAY') out.BYMONTHDAY = parseInt(v,10);
    else if (k==='BYMONTH') out.BYMONTH = parseInt(v,10);
    else if (k==='COUNT') out.COUNT = parseInt(v,10);
    else if (k==='UNTIL') out.UNTIL = v;
  });
  return out;
}

function rrSummary(rrule){
  if (!rrule) return loanRruleText.oneTime;
  const p = parseRR(rrule);
  if (!p.FREQ) return loanRruleText.oneTime;

  const intervalStr = String(p.INTERVAL ?? 1);
  const endText = (p.COUNT ? loanReplace(loanRruleText.times, {count: p.COUNT})
                  : p.UNTIL ? loanReplace(loanRruleText.until, {
                      date: `${p.UNTIL.slice(0,4)}-${p.UNTIL.slice(4,6)}-${p.UNTIL.slice(6,8)}`
                    })
                  : '');

  if (p.FREQ==='DAILY') {
    return loanReplace(loanRruleText.everyDays, {count: intervalStr}) + endText;
  }
  if (p.FREQ==='WEEKLY') {
    const days = Array.isArray(p.BYDAY) ? p.BYDAY.map(code => loanDayNames[code] || code).join(', ') : '';
    return loanReplace(loanRruleText.everyWeeks, {count: intervalStr}) + (days ? loanReplace(loanRruleText.onDays, {days}) : '') + endText;
  }
  if (p.FREQ==='MONTHLY') {
    const base = loanReplace(loanRruleText.everyMonths, {count: intervalStr});
    return base + (p.BYMONTHDAY ? loanReplace(loanRruleText.onDay, {day: p.BYMONTHDAY}) : '') + endText;
  }
  if (p.FREQ==='YEARLY') {
    const date = (p.BYMONTH ? String(p.BYMONTH).padStart(2,'0') : '--') + '-' + (p.BYMONTHDAY ?? '--');
    return loanReplace(loanRruleText.everyYears, {count: intervalStr}) + loanReplace(loanRruleText.onDate, {date}) + endText;
  }
  return loanRruleText.repeats;
}

  // Render summaries in the table (replace raw text)
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.rrule-summary[data-rrule]').forEach(el=>{
      const r = el.getAttribute('data-rrule') || '';
      el.textContent = rrSummary(r);
    });
  });
  </script>

  <script>
  document.addEventListener('click', (e)=>{
    const openSel = e.target.closest('[data-open]');
    if (openSel) {
      const id = openSel.getAttribute('data-open');
      const m = document.querySelector(id);
      if (m && m.classList.contains('hidden')) {
        m.classList.remove('hidden');
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.open();
      }
      return;
    }
    const closeBtn = e.target.closest('[data-close]');
    if (closeBtn) {
      const modal = closeBtn.closest('.modal');
      if (modal && !modal.classList.contains('hidden')) {
        modal.classList.add('hidden');
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.close();
      }
    }
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal:not(.hidden)')?.forEach(m=>{
        m.classList.add('hidden');
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.close();
      });
    }
  });
</script>
