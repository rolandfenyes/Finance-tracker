<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= htmlspecialchars(__('loans.title')) ?></h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= htmlspecialchars(__('loans.add')) ?></summary>

    <form class="mt-4 grid gap-4 lg:grid-cols-12" method="post" action="/loans/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <!-- Loan details -->
      <div class="bg-white rounded-2xl p-5 shadow-glass lg:col-span-7">
        <h3 class="font-semibold mb-4"><?= htmlspecialchars(__('loans.sections.details')) ?></h3>
        <div class="grid sm:grid-cols-12 gap-3">
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.name')) ?></label>
            <input name="name" class="input" placeholder="<?= htmlspecialchars(__('loans.fields.name_placeholder')) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= htmlspecialchars(__('loans.fields.principal')) ?></label>
            <input name="principal" type="number" step="0.01" class="input" placeholder="0.00" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= htmlspecialchars(__('loans.fields.apr')) ?></label>
            <input name="interest_rate" type="number" step="0.001" class="input" placeholder="8.5" required />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.start_date')) ?></label>
            <input name="start_date" type="date" class="input" required />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.end_date')) ?></label>
            <input name="end_date" type="date" class="input" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.payment_day')) ?></label>
            <input name="payment_day" type="number" min="1" max="31" class="input" placeholder="10" />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.extra_payment')) ?></label>
            <input name="extra_payment" type="number" step="0.01" class="input" placeholder="0.00" />
          </div>

          <!-- NEW: Loan currency -->
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.loan_currency')) ?></label>
            <select name="loan_currency" id="loan-currency-select" class="select">
              <?php foreach ($userCurrencies as $uc): ?>
                <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                  <?= htmlspecialchars($uc['code']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="help"><?= htmlspecialchars(__('loans.fields.loan_currency_help')) ?></p>
          </div>
        </div>
        <div class="mt-3 flex flex-col gap-3">
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.insurance_monthly')) ?></label>
            <input name="insurance_monthly" type="number" step="0.01" class="input" placeholder="0.00" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.history')) ?></label>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="history_confirmed" value="1" />
              <span><?= htmlspecialchars(__('loans.fields.history_label')) ?></span>
            </label>
            <p class="help"><?= htmlspecialchars(__('loans.fields.history_help')) ?></p>
          </div>
        </div>

      </div>

      <!-- Schedule -->
      <div class="bg-white rounded-2xl p-5 shadow-glass lg:col-span-5">
        <h3 class="font-semibold mb-4"><?= htmlspecialchars(__('loans.sections.schedule')) ?></h3>

        <div class="field">
          <label class="label"><?= htmlspecialchars(__('loans.fields.schedule_link')) ?></label>
          <select name="scheduled_payment_id" class="select">
            <option value=""><?= htmlspecialchars(__('loans.fields.schedule_none')) ?></option>
            <?php foreach($scheduledList as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>">
                <?= htmlspecialchars($sp['title']) ?> (<?= moneyfmt($sp['amount'], $sp['currency']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <p class="help"><?= htmlspecialchars(__('loans.fields.schedule_help')) ?></p>
        </div>

        <div class="my-3 flex items-center gap-3 text-xs text-gray-400">
          <div class="h-px flex-1 bg-gray-200"></div><span><?= htmlspecialchars(__('loans.fields.schedule_separator')) ?></span><div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <div class="space-y-3">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="create_schedule" value="1" />
            <span><?= htmlspecialchars(__('loans.fields.create_schedule')) ?></span>
          </label>

          <div class="grid sm:grid-cols-12 gap-3">
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.first_due')) ?></label>
              <input name="first_due" type="date" class="input" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.due_day')) ?></label>
              <input name="due_day" type="number" min="1" max="31" class="input" placeholder="10" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.monthly_amount')) ?></label>
              <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="<?= htmlspecialchars(__('loans.fields.monthly_amount_placeholder')) ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.schedule_currency')) ?></label>
              <select name="currency" id="schedule-currency-select" class="select">
                <?php foreach ($userCurrencies as $uc): ?>
                  <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                    <?= htmlspecialchars($uc['code']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="help"><?= htmlspecialchars(__('loans.fields.schedule_currency_help')) ?></p>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-12 flex justify-end">
        <button class="btn btn-primary"><?= htmlspecialchars(__('common.save')) ?></button>
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

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold"><?= htmlspecialchars(__('loans.list.title')) ?></h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3 w-[38%]"><?= htmlspecialchars(__('loans.list.columns.loan')) ?></th>
          <th class="py-2 pr-3 w-[18%]"><?= htmlspecialchars(__('loans.list.columns.balance')) ?></th>
          <th class="py-2 pr-3 w-[24%]"><?= htmlspecialchars(__('loans.list.columns.schedule')) ?></th>
          <th class="py-2 pr-3 w-[20%] text-right"><?= htmlspecialchars(__('loans.list.columns.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $l):
        $cur   = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
        $prin  = (float)($l['principal'] ?? 0);
        $bal   = (float)($l['_est_balance'] ?? ($l['balance'] ?? 0));
        $paid  = (float)($l['_principal_paid'] ?? max(0, $prin - $bal));
        $pct   = (float)($l['_progress_pct'] ?? ($prin>0?($paid/$prin*100):0));
        $months = 0;
        if (!empty($l['start_date']) && !empty($l['end_date'])) {
          $a = new DateTime($l['start_date']); $b = new DateTime($l['end_date']);
          $d = $a->diff($b); $months = $d->y*12 + $d->m + ($d->d>0?1:0);
        }

        $aprRate = (float)$l['interest_rate'];
        $aprDisplay = rtrim(rtrim(number_format($aprRate, 2, '.', ''), '0'), '.');
        $aprLabel = __('loans.list.apr_badge', ['rate' => $aprDisplay !== '' ? $aprDisplay : '0']);
        $rangeLabel = __('loans.list.date_range', ['start' => $l['start_date'], 'end' => $l['end_date'] ?? '—']);
        $monthsLabel = $months ? __('loans.list.months', ['count' => $months]) : '';
        $historyConfirmed = !empty($l['history_confirmed']) ? __('loans.list.history_confirmed') : '';
        $progressWidth = number_format(min(100,max(0,$pct)),2,'.','');
        $paidLabel = __('loans.list.paid_of', [
          'paid' => moneyfmt($paid,$cur),
          'principal' => moneyfmt($prin,$cur),
          'percent' => number_format($pct,1)
        ]);
        $balanceLabel = __('loans.list.balance_est', ['amount' => moneyfmt($bal,$cur)]);
        $interestLabel = (!empty($l['history_confirmed']) && $l['_interest_paid'] !== null)
          ? __('loans.list.estimated_interest', ['amount' => moneyfmt($l['_interest_paid'],$cur)])
          : null;
        $nextDueLabel = !empty($l['sched_next_due']) ? __('loans.list.next_due', ['date' => $l['sched_next_due']]) : null;
      ?>
        <tr class="border-b align-top">
          <td class="py-3 pr-3">
            <div class="font-medium flex items-center gap-2">
              <?= htmlspecialchars($l['name']) ?>
              <span class="text-xs text-gray-500"><?= htmlspecialchars($aprLabel) ?></span>
            </div>
            <div class="text-xs text-gray-500">
              <?= htmlspecialchars($rangeLabel) ?>
              <?php if ($monthsLabel): ?> · <?= htmlspecialchars($monthsLabel) ?><?php endif; ?>
              <?php if ($historyConfirmed): ?>
                <span class="ml-1 text-emerald-600"><?= htmlspecialchars($historyConfirmed) ?></span>
              <?php endif; ?>
            </div>

            <!-- progress -->
            <div class="mt-2">
              <div class="h-2 bg-gray-100 rounded-full">
                <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= $progressWidth ?>%"></div>
              </div>
              <div class="mt-1 text-xs text-gray-600">
                <?= htmlspecialchars($paidLabel) ?>
                <br> <?= htmlspecialchars($balanceLabel) ?>
              </div>
              <?php if ($interestLabel): ?>
                <div class="text-[11px] text-gray-500"><?= htmlspecialchars($interestLabel) ?></div>
              <?php endif; ?>
            </div>
          </td>

          <td class="py-3 pr-3 whitespace-nowrap align-middle">
            <div class="text-sm text-gray-500"><?= htmlspecialchars(__('loans.list.columns.balance')) ?></div>
            <div class="font-semibold"><?= moneyfmt($bal, $cur) ?></div>
          </td>

          <td class="py-3 pr-3 align-middle">
            <?php if (!empty($l['scheduled_payment_id'])): ?>
              <div class="flex items-center gap-2">
                <span class="chip"> <?= htmlspecialchars($l['sched_title']) ?> </span>
              </div>
              <?php if ($nextDueLabel): ?>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($nextDueLabel) ?></div>
              <?php endif; ?>
              <?php if (!empty($l['sched_rrule'])): ?>
                <span class="rrule-summary text-[11px] text-gray-400 mt-1"
                      data-rrule="<?= htmlspecialchars($l['sched_rrule']) ?>"></span>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-xs text-gray-500"><?= htmlspecialchars(__('loans.list.no_schedule')) ?></div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 text-right align-middle">
            <button type="button"
                    class="btn btn-primary !px-3"
                    data-open="#loan-edit-<?= (int)$l['id'] ?>">
              <?= htmlspecialchars(__('loans.list.edit_pay')) ?>
            </button>
          </td>
        </tr>
      <?php endforeach; if (!count($rows)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500"><?= htmlspecialchars(__('loans.list.none')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="md:hidden space-y-3">
    <?php foreach($rows as $l):
      $cur   = $l['_currency'] ?: ($l['currency'] ?: 'HUF');
      $prin  = (float)($l['principal'] ?? 0);
      $bal   = (float)($l['_est_balance'] ?? ($l['balance'] ?? 0));
      $paid  = (float)($l['_principal_paid'] ?? max(0, $prin - $bal));
      $pct   = (float)($l['_progress_pct'] ?? ($prin>0?($paid/$prin*100):0));
      $aprRate = (float)$l['interest_rate'];
      $aprDisplay = rtrim(rtrim(number_format($aprRate, 2, '.', ''), '0'), '.');
      $aprLabel = __('loans.list.apr_badge', ['rate' => $aprDisplay !== '' ? $aprDisplay : '0']);
      $rangeLabel = __('loans.list.date_range', ['start' => $l['start_date'], 'end' => $l['end_date'] ?? '—']);
      $historyShort = !empty($l['history_confirmed']) ? __('loans.list.history_short') : '';
      $progressWidth = number_format(min(100,max(0,$pct)),2,'.','');
      $summaryLabel = __('loans.payment.summary', ['paid' => moneyfmt($paid,$cur), 'principal' => moneyfmt($prin,$cur)]);
      $balanceLabel = __('loans.list.columns.balance');
      $nextDueLabel = !empty($l['sched_next_due']) ? __('loans.list.next_due', ['date' => $l['sched_next_due']]) : null;
    ?>
      <div class="rounded-xl border p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($l['name']) ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($aprLabel) ?></div>
          </div>
          <button type="button" class="btn btn-primary !px-3" data-open="#loan-edit-<?= (int)$l['id'] ?>"><?= htmlspecialchars(__('loans.list.edit_pay')) ?></button>
        </div>

        <div class="mt-2 text-xs text-gray-500">
          <?= htmlspecialchars($rangeLabel) ?>
          <?php if ($historyShort): ?>
            · <span class="text-emerald-600"><?= htmlspecialchars($historyShort) ?></span>
          <?php endif; ?>
        </div>

        <div class="mt-3">
          <div class="h-2 bg-gray-100 rounded-full">
            <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= $progressWidth ?>%"></div>
          </div>
          <div class="mt-1 text-xs text-gray-600">
            <?= htmlspecialchars($summaryLabel) ?> <br> <?= htmlspecialchars($balanceLabel) ?> <?= moneyfmt($bal,$cur) ?>
          </div>
        </div>

        <div class="mt-3 text-xs text-gray-600">
          <?php if (!empty($l['scheduled_payment_id'])): ?>
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip"><?= htmlspecialchars($l['sched_title']) ?></span>
              <?php if ($nextDueLabel): ?>
                <span class="text-gray-500"><?= htmlspecialchars($nextDueLabel) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($l['sched_rrule'])): ?>
              <div class="rrule-summary text-[11px] text-gray-400 mt-1"
                   data-rrule="<?= htmlspecialchars($l['sched_rrule']) ?>"></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-gray-500"><?= htmlspecialchars(__('loans.list.no_schedule')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php foreach($rows as $l): $curList = $userCurrencies ?? [['code'=>'HUF','is_main'=>true]]; ?>
<div id="loan-edit-<?= (int)$l['id'] ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="loan-edit-title-<?= (int)$l['id'] ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel overflow-hidden">
    <!-- Header -->
    <div class="modal-header">
      <h3 id="loan-edit-title-<?= (int)$l['id'] ?>" class="font-semibold"><?= htmlspecialchars(__('loans.modal.title')) ?></h3>
      <button class="icon-btn" aria-label="<?= htmlspecialchars(__('common.close')) ?>" data-close>✕</button>
    </div>

    <!-- Body -->
    <form method="post" action="/loans/edit" class="modal-body grid gap-4 md:grid-cols-12">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />

      <!-- Left: details -->
      <div class="md:col-span-7 space-y-3">
        <div class="grid sm:grid-cols-12 gap-3">
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.name')) ?></label>
            <input name="name" class="input" value="<?= htmlspecialchars($l['name']) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= htmlspecialchars(__('loans.fields.principal')) ?></label>
            <input name="principal" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['principal']) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label"><?= htmlspecialchars(__('loans.fields.apr')) ?></label>
            <input name="interest_rate" type="number" step="0.001" class="input" value="<?= htmlspecialchars($l['interest_rate']) ?>" required />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.start_date')) ?></label>
            <input name="start_date" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" required />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.end_date')) ?></label>
            <input name="end_date" type="date" class="input" value="<?= htmlspecialchars($l['end_date'] ?? '') ?>" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.payment_day')) ?></label>
            <input name="payment_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
          </div>
          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.extra_payment')) ?></label>
            <input name="extra_payment" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['extra_payment'] ?? 0) ?>" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.loan_currency')) ?></label>
            <select name="loan_currency" class="select">
              <?php foreach ($curList as $uc): $code=$uc['code']; ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                  <?= htmlspecialchars($code) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field sm:col-span-6">
            <label class="label"><?= htmlspecialchars(__('loans.fields.insurance_monthly_short')) ?></label>
            <input name="insurance_monthly" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['insurance_monthly'] ?? 0) ?>" />
          </div>
        </div>

        <div class="field">
          <label class="label"><?= htmlspecialchars(__('loans.fields.history')) ?></label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="history_confirmed" value="1" <?= !empty($l['history_confirmed'])?'checked':'' ?> />
            <span><?= htmlspecialchars(__('loans.fields.history_label')) ?></span>
          </label>
        </div>
      </div>

      <!-- Right: schedule -->
      <div class="md:col-span-5 space-y-3">
        <div class="field">
          <label class="label"><?= htmlspecialchars(__('loans.fields.schedule_link')) ?></label>
          <select name="scheduled_payment_id" class="select">
            <option value=""><?= htmlspecialchars(__('loans.fields.schedule_none')) ?></option>
            <?php foreach($scheduledList as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>" <?= ((int)($l['scheduled_payment_id']??0)===(int)$sp['id'])?'selected':'' ?>>
                <?= htmlspecialchars($sp['title']) ?> (<?= moneyfmt($sp['amount'],$sp['currency']) ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <?php if (!empty($l['scheduled_payment_id'])): ?>
          <label class="mt-2 inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="unlink_schedule" value="1" />
            <span><?= htmlspecialchars(__('loans.fields.unlink_schedule')) ?></span>
          </label>
          <?php endif; ?>

          <p class="help"><?= htmlspecialchars(__('loans.fields.schedule_create_hint')) ?></p>
        </div>

        <div class="my-2 flex items-center gap-3 text-xs text-gray-400">
          <div class="h-px flex-1 bg-gray-200"></div><span><?= htmlspecialchars(__('loans.fields.schedule_separator')) ?></span><div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <div class="space-y-3">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="create_schedule" value="1" />
            <span><?= htmlspecialchars(__('loans.fields.create_schedule_short')) ?></span>
          </label>

          <div class="grid sm:grid-cols-12 gap-3">
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.first_due')) ?></label>
              <input name="first_due" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.due_day')) ?></label>
              <input name="due_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.monthly_amount')) ?></label>
              <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="<?= htmlspecialchars(__('loans.fields.monthly_amount_placeholder')) ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label"><?= htmlspecialchars(__('loans.fields.schedule_currency')) ?></label>
              <select name="currency" class="select">
                <?php foreach ($curList as $uc): $code=$uc['code']; ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                    <?= htmlspecialchars($code) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="help"><?= htmlspecialchars(__('loans.fields.schedule_currency_help')) ?></p>
            </div>
          </div>
        </div>
      </div>
    </form>

    <!-- Sticky footer -->
    <div class="modal-footer bg-gray-50">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 w-full">
        <!-- Quick payment -->
        <form class="grid grid-cols-2 md:grid-cols-4 gap-2 w-full md:w-auto"
              method="post" action="/loans/payment/add">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>" />
          <input name="paid_on" type="date" value="<?= date('Y-m-d') ?>" class="input">
          <input name="amount" type="number" step="0.01" placeholder="<?= htmlspecialchars(__('loans.modal.payment_placeholder')) ?>" class="input" required>
          <button class="btn btn-emerald md:col-span-1"><?= htmlspecialchars(__('loans.modal.record_payment')) ?></button>
        </form>

        <div class="flex justify-end gap-2">
          <button class="btn" data-close><?= htmlspecialchars(__('common.cancel')) ?></button>
          <button class="btn btn-primary" onclick="this.closest('.modal').querySelector('form').submit()"><?= htmlspecialchars(__('common.save')) ?></button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>



<script>
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
  const hasT = typeof t === 'function';
  const oneTime = hasT ? t('recurrence.summary.one_time') : 'One-time';
  if (!rrule) return oneTime;
  const p = parseRR(rrule);
  if (!p.FREQ) return oneTime;

  const unitMap = { DAILY: 'day', WEEKLY: 'week', MONTHLY: 'month', YEARLY: 'year' };
  const every = (n, unitKey) => {
    if (!unitKey) return '';
    if (!hasT) { return n > 1 ? `Every ${n} ${unitKey}s` : `Every ${unitKey}`; }
    const key = n > 1 ? 'recurrence.summary.every_interval' : 'recurrence.summary.every_single';
    const params = n > 1
      ? { interval: n, unit: t(`recurrence.units.${unitKey}.plural`) }
      : { unit: t(`recurrence.units.${unitKey}.singular`) };
    return t(key, params);
  };

  const parts = [];
  const unitKey = unitMap[p.FREQ] || '';
  const base = every(p.INTERVAL || 1, unitKey);
  if (base) parts.push(base);

  let details = '';
  if (p.FREQ === 'WEEKLY') {
    const days = Array.isArray(p.BYDAY) ? p.BYDAY.map(code => {
      const key = `dates.weekdays.${code.toLowerCase()}.short`;
      if (!hasT) return code;
      const label = t(key);
      return label === key ? code : label;
    }).filter(Boolean) : [];
    if (days.length) {
      details = hasT ? t('recurrence.summary.on_days', { days: days.join(', ') }) : `on ${days.join(', ')}`;
    }
  } else if (p.FREQ === 'MONTHLY' && p.BYMONTHDAY != null) {
    details = hasT ? t('recurrence.summary.on_day_of_month', { day: p.BYMONTHDAY }) : `on day ${p.BYMONTHDAY}`;
  } else if (p.FREQ === 'YEARLY') {
    const month = p.BYMONTH != null ? p.BYMONTH : null;
    const day = p.BYMONTHDAY != null ? p.BYMONTHDAY : null;
    if (month !== null || day !== null) {
      let label = '';
      if (month !== null) {
        if (hasT) {
          const key = `dates.months.${month}.short`;
          const translated = t(key);
          label = translated === key ? String(month).padStart(2, '0') : translated;
        } else {
          label = String(month).padStart(2, '0');
        }
      }
      if (day !== null) {
        const dayStr = String(day).padStart(2, '0');
        label = label ? `${label} ${dayStr}` : dayStr;
      }
      details = hasT ? t('recurrence.summary.on_date', { date: label }) : `on ${label}`;
    }
  }
  if (details) parts.push(details);

  let ending = '';
  if (p.COUNT) {
    ending = hasT ? t('recurrence.summary.count', { count: p.COUNT }) : `after ${p.COUNT} times`;
  } else if (p.UNTIL) {
    const formatted = p.UNTIL.length >= 8
      ? `${p.UNTIL.slice(0,4)}-${p.UNTIL.slice(4,6)}-${p.UNTIL.slice(6,8)}`
      : p.UNTIL;
    ending = hasT ? t('recurrence.summary.until', { date: formatted }) : `until ${formatted}`;
  }
  if (ending) parts.push(ending);

  if (!parts.length) {
    return hasT ? t('recurrence.summary.repeats') : 'Repeats';
  }
  return parts.join(' ');
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
    if (m) m.classList.remove('hidden');
    return;
  }
  const closeBtn = e.target.closest('[data-close]');
  if (closeBtn) {
    closeBtn.closest('.modal')?.classList.add('hidden');
  }
});
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape') document.querySelectorAll('.modal')?.forEach(m=>m.classList.add('hidden'));
});
</script>
