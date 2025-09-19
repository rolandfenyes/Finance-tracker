<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Goals</h1>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-2 text-sm text-emerald-700"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
  <?php endif; ?>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add goal</summary>
    <form class="mt-3 grid sm:grid-cols-12 gap-3" method="post" action="/goals/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div class="sm:col-span-5">
        <label class="label">Title</label>
        <input name="title" class="input" placeholder="e.g., New laptop" required />
      </div>
      <div class="sm:col-span-3">
        <label class="label">Target</label>
        <input name="target_amount" type="number" step="0.01" class="input" placeholder="0.00" required />
      </div>
      <div class="sm:col-span-2">
        <label class="label">Currency</label>
        <select name="currency" class="select">
          <?php foreach ($userCurrencies as $uc): ?>
            <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
              <?= htmlspecialchars($uc['code']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sm:col-span-2">
        <label class="label">Current (optional)</label>
        <input name="current_amount" type="number" step="0.01" class="input" placeholder="0.00" />
      </div>
      <div class="sm:col-span-12">
        <label class="label">Status</label>
        <select name="status" class="select w-full max-w-xs">
          <option value="active">Active</option>
          <option value="paused">Paused</option>
          <option value="done">Done</option>
        </select>
      </div>
      <div class="sm:col-span-12 flex justify-end">
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold">Your goals</h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3 w-[38%]">Goal</th>
        <th class="py-2 pr-3 w-[22%]">Progress</th>
        <th class="py-2 pr-3 w-[20%]">Schedule</th>
        <th class="py-2 pr-3 w-[20%]" style="text-align:right;">Actions</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $g):
        $cur = $g['currency'] ?: 'HUF';
        $target = (float)($g['target_amount'] ?? 0);
        $current= (float)($g['current_amount'] ?? 0);
        $pct = $target>0 ? min(100, max(0, $current/$target*100)) : 0;
      ?>
        <tr class="border-b align-top">
          <td class="py-3 pr-3">
            <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
            <div class="text-xs text-gray-500">
              <?= htmlspecialchars(ucfirst($g['status'] ?? 'active')) ?> · <?= htmlspecialchars($cur) ?>
            </div>
            <div class="mt-2">
              <div class="h-2 bg-gray-100 rounded-full">
                <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
              </div>
              <div class="mt-1 text-xs text-gray-600">
                <?= moneyfmt($current,$cur) ?> / <?= moneyfmt($target,$cur) ?> (<?= number_format($pct,1) ?>%)
              </div>
            </div>
          </td>

          <td class="py-3 pr-3 align-middle">
            <?php if (!empty($g['sched_id'])): ?>
              <div class="font-medium"><?= htmlspecialchars($g['sched_title']) ?></div>
              <div class="text-xs text-gray-500"><?= moneyfmt($g['sched_amount'], $g['sched_currency']) ?></div>
              <?php if (!empty($g['sched_rrule'])): ?>
                <div class="rrule-summary text-[11px] text-gray-400 mt-1"
                     data-rrule="<?= htmlspecialchars($g['sched_rrule']) ?>"></div>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-xs text-gray-500">No schedule</div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 align-middle">
            <div class="text-sm"><?= moneyfmt($target - $current, $cur) ?> to go</div>
          </td>

          <td class="py-3 pr-0 align-middle" style="text-align:right;">
            <button class="btn btn-primary !px-3"
                    data-open="#goal-edit-<?= (int)$g['id'] ?>">Edit / Add money</button>
          </td>
        </tr>
      <?php endforeach; if(!count($rows)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500">No goals yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="md:hidden space-y-3">
    <?php foreach ($rows as $g):
      $cur = $g['currency'] ?: 'HUF';
      $target = (float)($g['target_amount'] ?? 0);
      $current= (float)($g['current_amount'] ?? 0);
      $pct = $target>0 ? min(100, max(0, $current/$target*100)) : 0;
    ?>
      <div class="rounded-xl border p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
            <div class="text-xs text-gray-500"><?= ucfirst($g['status']) ?> · <?= htmlspecialchars($cur) ?></div>
          </div>
          <button class="btn btn-primary !px-3" data-open="#goal-edit-<?= (int)$g['id'] ?>">Edit</button>
        </div>
        <div class="mt-3">
          <div class="h-2 bg-gray-100 rounded-full">
            <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
          </div>
          <div class="mt-1 text-xs text-gray-600">
            <?= moneyfmt($current,$cur) ?> / <?= moneyfmt($target,$cur) ?>
          </div>
          <?php if (!empty($g['sched_id'])): ?>
            <div class="mt-2 text-xs">
              <span class="chip"><?= htmlspecialchars($g['sched_title']) ?></span>
              <span class="text-gray-500"> · <?= moneyfmt($g['sched_amount'], $g['sched_currency']) ?></span>
              <?php if (!empty($g['sched_rrule'])): ?>
                <div class="rrule-summary text-[11px] text-gray-400 mt-1"
                     data-rrule="<?= htmlspecialchars($g['sched_rrule']) ?>"></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php foreach ($rows as $g): $goalId=(int)$g['id']; $cur=$g['currency'] ?: 'HUF'; ?>
<div id="goal-edit-<?= $goalId ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="goal-edit-title-<?= $goalId ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel">
    <!-- Header -->
    <div class="modal-header">
      <h3 id="goal-edit-title-<?= $goalId ?>" class="font-semibold">Edit goal</h3>
      <button class="icon-btn" aria-label="Close" data-close>✕</button>
    </div>

    <!-- Body -->
    <div class="modal-body">
      <form id="goal-form-<?= $goalId ?>" method="post" action="/goals/edit" class="grid gap-4 md:grid-cols-12">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="id" value="<?= $goalId ?>" />

        <!-- Left column: Goal details (like Loans left column) -->
        <div class="md:col-span-7">
          <div class="grid sm:grid-cols-12 gap-3">
            <div class="sm:col-span-7">
              <label class="label">Name</label>
              <input name="title" class="input" value="<?= htmlspecialchars($g['title']) ?>" required />
            </div>
            <div class="sm:col-span-5">
              <label class="label">Target</label>
              <input name="target_amount" type="number" step="0.01" class="input" value="<?= htmlspecialchars($g['target_amount']) ?>" required />
            </div>

            <div class="sm:col-span-6">
              <label class="label">Currency</label>
              <select name="currency" class="select">
                <?php foreach ($userCurrencies as $uc): $code=$uc['code']; ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($code)===strtoupper($cur)?'selected':'' ?>><?= htmlspecialchars($code) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="sm:col-span-6">
              <label class="label">Status</label>
              <select name="status" class="select">
                <option value="active" <?= $g['status']==='active'?'selected':'' ?>>Active</option>
                <option value="paused" <?= $g['status']==='paused'?'selected':'' ?>>Paused</option>
                <option value="done"   <?= $g['status']==='done'  ?'selected':'' ?>>Done</option>
              </select>
            </div>

            <div class="sm:col-span-12">
              <label class="label">Note (optional)</label>
              <input name="note" class="input" value="<?= htmlspecialchars($g['note'] ?? '') ?>" />
            </div>
          </div>
        </div>

        <!-- Right column: Scheduled OR Manual (like Loans right column) -->
        <div class="md:col-span-5">
          <h4 class="font-semibold mb-2">Add money</h4>

          <!-- Link existing schedule -->
          <div class="field">
            <label class="label">Link existing schedule</label>
            <form method="post" action="/goals/link-schedule" class="grid gap-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
              <select name="scheduled_payment_id" class="select">
                <option value="">— None —</option>
                <?php foreach ($scheduledList as $sp): ?>
                  <option value="<?= (int)$sp['id'] ?>" <?= ((int)($g['scheduled_payment_id'] ?? 0)===(int)$sp['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($sp['title']) ?> (<?= moneyfmt($sp['amount'],$sp['currency']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (!empty($g['scheduled_payment_id'])): ?>
                <label class="inline-flex items-center gap-2 text-sm mt-1">
                  <input type="checkbox" name="unlink" value="1">
                  <span>Unlink current schedule</span>
                </label>
              <?php endif; ?>
              <div class="flex justify-end">
                <button class="btn">Apply</button>
              </div>
            </form>
          </div>

          <!-- Divider (same as Loans) -->
          <div class="my-3 flex items-center gap-3 text-xs text-gray-400">
            <div class="h-px flex-1 bg-gray-200"></div><span>or</span><div class="h-px flex-1 bg-gray-200"></div>
          </div>

          <!-- Create new monthly schedule (mirrors Loans create section) -->
          <div class="space-y-3">
            <form method="post" action="/goals/create-schedule" class="grid sm:grid-cols-12 gap-3">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
              <div class="sm:col-span-12">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" name="create_schedule" value="1">
                  <span>Create a monthly schedule</span>
                </label>
              </div>
              <div class="sm:col-span-12">
                <label class="label">Schedule title</label>
                <input name="title" class="input" placeholder="Goal: <?= htmlspecialchars($goalName ?? 'Savings') ?>">
              </div>

              <div class="sm:col-span-6">
                <label class="label">Category</label>
                <select name="category_id" class="select">
                  <option value="">No category</option>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="sm:col-span-6">
                <label class="label">First due</label>
                <input name="next_due" type="date" class="input" />
              </div>
              <div class="sm:col-span-6">
                <label class="label">Due day</label>
                <input name="due_day" type="number" min="1" max="31" class="input" placeholder="e.g., 10" />
              </div>
              <div class="sm:col-span-6">
                <label class="label">Monthly amount</label>
                <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" />
              </div>
              <div class="sm:col-span-6">
                <label class="label">Currency</label>
                <select name="currency" class="select">
                  <?php foreach ($userCurrencies as $uc): ?>
                    <option value="<?= htmlspecialchars($uc['code']) ?>" <?= strtoupper($uc['code'])===strtoupper($cur)?'selected':'' ?>>
                      <?= htmlspecialchars($uc['code']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="help">Defaults to the goal currency.</p>
              </div>
              <!-- Implicit RRULE: FREQ=MONTHLY;BYMONTHDAY=due_day; (COUNT/UNTIL optional on server) -->
              <div class="sm:col-span-12 flex justify-end">
                <button class="btn btn-primary">Create schedule</button>
              </div>
            </form>
          </div>
        </div>
      </form>
    </div>

    <!-- Footer (identical to Loans) -->
    <div class="modal-footer bg-gray-50">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 w-full">
        <!-- Quick “Add money now” bar (matches Loans quick payment bar layout & spacing) -->
        <div class="flex flex-row items-center gap-2">
          <form method="post" action="/goals/tx/add" class="flex flex-row items-center gap-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
            <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="input">
            <input name="amount" type="number" step="0.01" placeholder="Amount" class="input" required>
            <button class="w-80 btn btn-emerald">Add money</button>
          </form>

          
        </div>
        <!-- Danger: Delete goal (right aligned on larger screens) -->
          <form method="post" action="/goals/delete" onsubmit="return confirm('Delete this goal?')" class="">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= $goalId ?>" />
            <button class="btn btn-danger">Delete</button>
          </form>
        <button class="btn" data-close>Cancel</button>
        <button class="btn btn-primary" onclick="document.getElementById('goal-form-<?= $goalId ?>').submit()">Save</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
// modal open/close
document.addEventListener('click', (e)=>{
  const open = e.target.closest('[data-open]');
  if (open){ document.querySelector(open.dataset.open)?.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); return; }
  const close = e.target.closest('[data-close]');
  if (close){ close.closest('.modal')?.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }
});
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') document.querySelectorAll('.modal').forEach(m=>m.classList.add('hidden')); });

// tabs
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const wrap = btn.closest('.rounded-xl.border');
    wrap.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    wrap.querySelectorAll('.tab-panel').forEach(p=>p.classList.add('hidden'));
    const target = wrap.querySelector(btn.dataset.tab);
    target && target.classList.remove('hidden');
  });
});

// simple goal RRULE builder (monthly/weekly)
<?php foreach($rows as $g): $id=(int)$g['id']; ?>
(function(){
  const out = document.getElementById('goal-rrule-<?= $id ?>');
  const freq= document.getElementById('g-freq-<?= $id ?>');
  const interval=document.getElementById('g-interval-<?= $id ?>');
  const bymd = document.getElementById('g-bymd-<?= $id ?>');
  const endtype=document.getElementById('g-endtype-<?= $id ?>');
  const countW=document.getElementById('g-count-wrap-<?= $id ?>');
  const countI=document.getElementById('g-count-<?= $id ?>');
  const untilW=document.getElementById('g-until-wrap-<?= $id ?>');
  const untilI=document.getElementById('g-until-<?= $id ?>');
  const monthlyWrap=document.getElementById('g-monthly-wrap-<?= $id ?>');
  const summary=document.getElementById('g-summary-<?= $id ?>');

  function vis(){
    monthlyWrap.style.display = (freq.value==='MONTHLY') ? '' : 'none';
    countW.style.display = (endtype.value==='count') ? '' : 'none';
    untilW.style.display = (endtype.value==='until') ? '' : 'none';
  }
  function build(){
    let r = ['FREQ='+freq.value];
    const iv = Math.max(1, parseInt(interval.value||'1',10));
    if (iv>1) r.push('INTERVAL='+iv);
    if (freq.value==='MONTHLY'){
      const d = parseInt(bymd.value||'',10);
      if (!isNaN(d)) r.push('BYMONTHDAY='+d);
    }
    if (endtype.value==='count'){
      const c = parseInt(countI.value||'',10); if(!isNaN(c) && c>0) r.push('COUNT='+c);
    } else if (endtype.value==='until' && untilI.value){
      r.push('UNTIL='+untilI.value.replaceAll('-',''));
    }
    out.value = r.join(';');
    // tiny summary
    let s = (freq.value==='WEEKLY'?'Every '+iv+' week(s)':
             'Every '+iv+' month(s)'+(bymd.value?(' on day '+bymd.value):''));
    if (endtype.value==='count' && countI.value) s += ', '+countI.value+' times';
    if (endtype.value==='until' && untilI.value) s += ', until '+untilI.value;
    summary.textContent = s;
  }
  [freq, interval, bymd, endtype, countI, untilI].forEach(el=>el && el.addEventListener('input', ()=>{ vis(); build(); }));
  vis(); build();
})();
<?php endforeach; ?>

// RRULE summaries already handled elsewhere on page
document.querySelectorAll('.rrule-summary[data-rrule]').forEach(el=>{
  const r = el.getAttribute('data-rrule') || '';
  el.textContent = (typeof rrSummary==='function') ? rrSummary(r) : r;
});
</script>
