<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Loans</h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add loan</summary>

    <form class="mt-4 grid gap-4 lg:grid-cols-12" method="post" action="/loans/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <!-- Loan details -->
      <div class="bg-white rounded-2xl p-5 shadow-glass lg:col-span-7">
        <h3 class="font-semibold mb-4">Loan details</h3>
        <div class="grid sm:grid-cols-12 gap-3">
          <div class="field sm:col-span-6">
            <label class="label">Name</label>
            <input name="name" class="input" placeholder="e.g., Car loan" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label">Principal</label>
            <input name="principal" type="number" step="0.01" class="input" placeholder="0.00" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label">APR %</label>
            <input name="interest_rate" type="number" step="0.001" class="input" placeholder="e.g., 8.5" required />
          </div>

          <div class="field sm:col-span-6">
            <label class="label">Start date</label>
            <input name="start_date" type="date" class="input" required />
          </div>
          <div class="field sm:col-span-6">
            <label class="label">End date (optional)</label>
            <input name="end_date" type="date" class="input" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label">Pay day (1–31)</label>
            <input name="payment_day" type="number" min="1" max="31" class="input" placeholder="e.g., 10" />
          </div>
          <div class="field sm:col-span-6">
            <label class="label">Extra monthly payment</label>
            <input name="extra_payment" type="number" step="0.01" class="input" placeholder="0.00" />
          </div>

          <!-- NEW: Loan currency -->
          <div class="field sm:col-span-6">
            <label class="label">Loan currency</label>
            <select name="loan_currency" id="loan-currency-select" class="select">
              <?php foreach ($userCurrencies as $uc): ?>
                <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                  <?= htmlspecialchars($uc['code']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="help">Used for principal, balance and payments recorded against the loan.</p>
          </div>
        </div>
        <div class="mt-3 flex flex-col gap-3">
          <div class="field sm:col-span-6">
            <label class="label">Insurance per month (excluded from progress)</label>
            <input name="insurance_monthly" type="number" step="0.01" class="input" placeholder="0.00" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label">History</label>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="history_confirmed" value="1" />
              <span>I’ve kept up with every scheduled payment since the start date</span>
            </label>
            <p class="help">When checked, progress is computed from the amortization schedule up to today.</p>
          </div>
        </div>

      </div>

      <!-- Schedule -->
      <div class="bg-white rounded-2xl p-5 shadow-glass lg:col-span-5">
        <h3 class="font-semibold mb-4">Repayment schedule</h3>

        <div class="field">
          <label class="label">Link existing schedule</label>
          <select name="scheduled_payment_id" class="select">
            <option value="">— None —</option>
            <?php foreach($scheduledList as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>">
                <?= htmlspecialchars($sp['title']) ?> (<?= moneyfmt($sp['amount'], $sp['currency']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <p class="help">Pick an already created scheduled payment to link.</p>
        </div>

        <div class="my-3 flex items-center gap-3 text-xs text-gray-400">
          <div class="h-px flex-1 bg-gray-200"></div><span>or</span><div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <div class="space-y-3">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="create_schedule" value="1" />
            <span>Create a monthly schedule from this loan</span>
          </label>

          <div class="grid sm:grid-cols-12 gap-3">
            <div class="field sm:col-span-6">
              <label class="label">First due</label>
              <input name="first_due" type="date" class="input" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label">Due day</label>
              <input name="due_day" type="number" min="1" max="31" class="input" placeholder="e.g., 10" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label">Monthly amount</label>
              <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="Auto-calc if empty" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label">Schedule currency</label>
              <select name="currency" id="schedule-currency-select" class="select">
                <?php foreach ($userCurrencies as $uc): ?>
                  <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
                    <?= htmlspecialchars($uc['code']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="help">Defaults to the loan currency.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-12 flex justify-end">
        <button class="btn btn-primary">Save</button>
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
    <h2 class="font-semibold">Loans</h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3 w-[38%]">Loan</th>
          <th class="py-2 pr-3 w-[18%]">Balance</th>
          <th class="py-2 pr-3 w-[24%]">Schedule</th>
          <th class="py-2 pr-3 w-[20%] text-right">Actions</th>
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
      ?>
        <tr class="border-b align-top">
          <td class="py-3 pr-3">
            <div class="font-medium flex items-center gap-2">
              <?= htmlspecialchars($l['name']) ?>
              <span class="text-xs text-gray-500">· APR <?= (float)$l['interest_rate'] ?>%</span>
            </div>
            <div class="text-xs text-gray-500">
              <?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date'] ?? '—') ?>
              <?php if ($months): ?> · <?= $months ?> mo<?php endif; ?>
              <?php if (!empty($l['history_confirmed'])): ?>
                <span class="ml-1 text-emerald-600">✔ history confirmed</span>
              <?php endif; ?>
            </div>

            <!-- progress -->
            <div class="mt-2">
              <div class="h-2 bg-gray-100 rounded-full">
                <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= number_format(min(100,max(0,$pct)),2,'.','') ?>%"></div>
              </div>
              <div class="mt-1 text-xs text-gray-600">
                <?= moneyfmt($paid,$cur) ?> paid of <?= moneyfmt($prin,$cur) ?> (<?= number_format($pct,1) ?>%)
                <br> Est. balance <?= moneyfmt($bal,$cur) ?>
              </div>
              <?php if (!empty($l['history_confirmed']) && $l['_interest_paid'] !== null): ?>
                <div class="text-[11px] text-gray-500">Estimated interest so far: <?= moneyfmt($l['_interest_paid'],$cur) ?></div>
              <?php endif; ?>
            </div>
          </td>

          <td class="py-3 pr-3 whitespace-nowrap align-middle">
            <div class="text-sm text-gray-500">Balance</div>
            <div class="font-semibold"><?= moneyfmt($bal, $cur) ?></div>
          </td>

          <td class="py-3 pr-3 align-middle">
            <?php if (!empty($l['scheduled_payment_id'])): ?>
              <div class="flex items-center gap-2">
                <span class="chip"> <?= htmlspecialchars($l['sched_title']) ?> </span>
              </div>
              <?php if (isset($l['sched_next_due'])): ?>
                <div class="text-xs text-gray-500 mt-1">Next: <?= htmlspecialchars($l['sched_next_due']) ?></div>
              <?php endif; ?>
              <?php if (!empty($l['sched_rrule'])): ?>
                <span class="rrule-summary text-[11px] text-gray-400 mt-1"
                      data-rrule="<?= htmlspecialchars($l['sched_rrule']) ?>"></span>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-xs text-gray-500">No schedule</div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 text-right align-middle">
            <button type="button"
                    class="btn btn-primary !px-3"
                    data-open="#loan-edit-<?= (int)$l['id'] ?>">
              Edit / Pay
            </button>
          </td>
        </tr>
      <?php endforeach; if (!count($rows)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500">No loans yet.</td></tr>
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
    ?>
      <div class="rounded-xl border p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($l['name']) ?></div>
            <div class="text-xs text-gray-500">APR <?= (float)$l['interest_rate'] ?>%</div>
          </div>
          <button type="button" class="btn btn-primary !px-3" data-open="#loan-edit-<?= (int)$l['id'] ?>">Edit</button>
        </div>

        <div class="mt-2 text-xs text-gray-500">
          <?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date'] ?? '—') ?>
          <?php if (!empty($l['history_confirmed'])): ?>
            · <span class="text-emerald-600">✔ history</span>
          <?php endif; ?>
        </div>

        <div class="mt-3">
          <div class="h-2 bg-gray-100 rounded-full">
            <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= number_format(min(100,max(0,$pct)),2,'.','') ?>%"></div>
          </div>
          <div class="mt-1 text-xs text-gray-600">
            <?= moneyfmt($paid,$cur) ?> / <?= moneyfmt($prin,$cur) ?> <br> Balance <?= moneyfmt($bal,$cur) ?>
          </div>
        </div>

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
            <div class="text-gray-500">No schedule</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php foreach($rows as $l): $curList = $userCurrencies ?? [['code'=>'HUF','is_main'=>true]]; ?>
<div id="loan-edit-<?= (int)$l['id'] ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="loan-edit-title-<?= (int)$l['id'] ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel rounded-2xl overflow-hidden">
    <!-- Header -->
    <div class="modal-header">
      <h3 id="loan-edit-title-<?= (int)$l['id'] ?>" class="font-semibold">Edit loan</h3>
      <button class="icon-btn" aria-label="Close" data-close>✕</button>
    </div>

    <!-- Body -->
    <form method="post" action="/loans/edit" class="modal-body grid gap-4 md:grid-cols-12">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>" />

      <!-- Left: details -->
      <div class="md:col-span-7 space-y-3">
        <div class="grid sm:grid-cols-12 gap-3">
          <div class="field sm:col-span-6">
            <label class="label">Name</label>
            <input name="name" class="input" value="<?= htmlspecialchars($l['name']) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label">Principal</label>
            <input name="principal" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['principal']) ?>" required />
          </div>
          <div class="field sm:col-span-3">
            <label class="label">APR %</label>
            <input name="interest_rate" type="number" step="0.001" class="input" value="<?= htmlspecialchars($l['interest_rate']) ?>" required />
          </div>

          <div class="field sm:col-span-6">
            <label class="label">Start date</label>
            <input name="start_date" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" required />
          </div>
          <div class="field sm:col-span-6">
            <label class="label">End date (optional)</label>
            <input name="end_date" type="date" class="input" value="<?= htmlspecialchars($l['end_date'] ?? '') ?>" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label">Pay day (1–31)</label>
            <input name="payment_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
          </div>
          <div class="field sm:col-span-6">
            <label class="label">Extra monthly payment</label>
            <input name="extra_payment" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['extra_payment'] ?? 0) ?>" />
          </div>

          <div class="field sm:col-span-6">
            <label class="label">Loan currency</label>
            <select name="loan_currency" class="select">
              <?php foreach ($curList as $uc): $code=$uc['code']; ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                  <?= htmlspecialchars($code) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field sm:col-span-6">
            <label class="label">Insurance / month</label>
            <input name="insurance_monthly" type="number" step="0.01" class="input" value="<?= htmlspecialchars($l['insurance_monthly'] ?? 0) ?>" />
          </div>
        </div>

        <div class="field">
          <label class="label">History</label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="history_confirmed" value="1" <?= !empty($l['history_confirmed'])?'checked':'' ?> />
            <span>I’ve kept up with every scheduled payment since the start date</span>
          </label>
        </div>
      </div>

      <!-- Right: schedule -->
      <div class="md:col-span-5 space-y-3">
        <div class="field">
          <label class="label">Link existing schedule</label>
          <select name="scheduled_payment_id" class="select">
            <option value="">— None —</option>
            <?php foreach($scheduledList as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>" <?= ((int)($l['scheduled_payment_id']??0)===(int)$sp['id'])?'selected':'' ?>>
                <?= htmlspecialchars($sp['title']) ?> (<?= moneyfmt($sp['amount'],$sp['currency']) ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <?php if (!empty($l['scheduled_payment_id'])): ?>
          <label class="mt-2 inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="unlink_schedule" value="1" />
            <span>Unlink current schedule</span>
          </label>
          <?php endif; ?>

          <p class="help">Pick an existing schedule, or create a new one below.</p>
        </div>

        <div class="my-2 flex items-center gap-3 text-xs text-gray-400">
          <div class="h-px flex-1 bg-gray-200"></div><span>or</span><div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <div class="space-y-3">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="create_schedule" value="1" />
            <span>Create a monthly schedule</span>
          </label>

          <div class="grid sm:grid-cols-12 gap-3">
            <div class="field sm:col-span-6">
              <label class="label">First due</label>
              <input name="first_due" type="date" class="input" value="<?= htmlspecialchars($l['start_date']) ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label">Due day</label>
              <input name="due_day" type="number" min="1" max="31" class="input" value="<?= htmlspecialchars($l['payment_day'] ?? '') ?>" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label">Monthly amount</label>
              <input name="monthly_amount" type="number" step="0.01" class="input" placeholder="Auto-calc if empty" />
            </div>
            <div class="field sm:col-span-6">
              <label class="label">Schedule currency</label>
              <select name="currency" class="select">
                <?php foreach ($curList as $uc): $code=$uc['code']; ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($l['currency'])===strtoupper($code)?'selected':'' ?>>
                    <?= htmlspecialchars($code) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="help">Defaults to the loan currency if unchanged.</p>
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
          <input name="amount" type="number" step="0.01" placeholder="Payment amount" class="input" required>
          <button class="btn btn-emerald md:col-span-1">Record Payment</button>
        </form>

        <div class="flex justify-end gap-2">
          <button class="btn" data-close>Cancel</button>
          <button class="btn btn-primary" onclick="this.closest('.modal').querySelector('form').submit()">Save</button>
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
  if (!rrule) return 'One-time';
  const p = parseRR(rrule);
  if (!p.FREQ) return 'One-time';

  const every = (n,unit)=> n>1 ? `Every ${n} ${unit}s` : `Every ${unit}`;
  const end = (p.COUNT ? `, ${p.COUNT} times`
             : p.UNTIL ? `, until ${p.UNTIL.slice(0,4)}-${p.UNTIL.slice(4,6)}-${p.UNTIL.slice(6,8)}`
             : '');

  if (p.FREQ==='DAILY')   return `${every(p.INTERVAL,'day')}${end}`;
  if (p.FREQ==='WEEKLY')  return `${every(p.INTERVAL,'week')}${p.BYDAY.length? ' on '+p.BYDAY.join(', '):''}${end}`;
  if (p.FREQ==='MONTHLY') return `${every(p.INTERVAL,'month')}${p.BYMONTHDAY? ' on day '+p.BYMONTHDAY:''}${end}`;
  if (p.FREQ==='YEARLY')  return `${every(p.INTERVAL,'year')}${(p.BYMONTH? ' on '+String(p.BYMONTH).padStart(2,'0')+'-'+(p.BYMONTHDAY??''): '')}${end}`;
  return 'Repeats';
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
