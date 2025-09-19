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

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Loan</th><th class="py-2 pr-3">APR</th><th class="py-2 pr-3">Balance</th><th class="py-2 pr-3">Period</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $l):
        $cur   = $l['currency'] ?: 'HUF';
        $prin  = (float)($l['principal'] ?? 0);
        $bal   = (float)($l['balance'] ?? 0);
        $paid  = max(0, $prin - $bal);
        $pct   = $prin > 0 ? max(0,min(100, ($paid / $prin) * 100)) : 0;
        $months = 0;
        if (!empty($l['start_date']) && !empty($l['end_date'])) {
          $a = new DateTime($l['start_date']); $b = new DateTime($l['end_date']);
          $d = $a->diff($b); $months = $d->y*12 + $d->m + ($d->d>0?1:0);
        }
      ?>
        <tr class="border-b align-top">
          <td class="py-3 pr-3 w-[38%]">
            <div class="font-medium"><?= htmlspecialchars($l['name']) ?></div>
            <div class="text-xs text-gray-500">APR <?= (float)$l['interest_rate'] ?>% · <?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date'] ?? '—') ?></div>

            <!-- progress -->
            <div class="mt-2">
              <div class="h-2 bg-gray-100 rounded-full">
                <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
              </div>
              <div class="mt-1 text-xs text-gray-600">
                <?= moneyfmt($paid, $cur) ?> paid of <?= moneyfmt($prin, $cur) ?> (<?= number_format($pct,1) ?>%)
              </div>
            </div>
          </td>

          <td class="py-3 pr-3 w-[18%]">
            <div class="text-sm">Balance</div>
            <div class="font-semibold"><?= moneyfmt($bal, $cur) ?></div>
            <?php if ($months): ?>
              <div class="text-xs text-gray-500 mt-1"><?= $months ?>-month term</div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 w-[24%]">
            <?php if (!empty($l['scheduled_payment_id'])): ?>
              <div class="text-sm">Schedule</div>
              <div class="flex items-center gap-2 mt-1">
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

          <td class="py-3 pr-3 w-[20%]">
            <details>
              <summary class="cursor-pointer text-accent">Edit / Pay</summary>
              <!-- keep your existing edit / payment forms here, but
                  include <select name="loan_currency"> matching this loan, and show amounts with $cur -->
            </details>
          </td>
        </tr>
      <?php endforeach; if (!count($rows)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500">No loans yet.</td></tr>
      <?php endif; ?>
      </tbody>

  </table>
</section>

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