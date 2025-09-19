<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Scheduled Payments</h1>
  <p class="text-sm text-gray-500">Set up recurring payments.</p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add scheduled payment</summary>
    <form method="post" action="/scheduled/add" class="grid gap-3 sm:grid-cols-12">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <div class="sm:col-span-4">
        <label class="label">Title</label>
        <input name="title" class="input" placeholder="e.g., Rent" required />
      </div>

      <div class="sm:col-span-2">
        <label class="label">Amount</label>
        <input name="amount" type="number" step="0.01" class="input" required />
      </div>

      <div class="sm:col-span-2">
        <label class="label">Currency</label>
        <select name="currency" class="select">
          <?php if (!empty($userCurrencies)): ?>
            <?php foreach ($userCurrencies as $curRow):
              $code = htmlspecialchars($curRow['code'] ?? '');
              $isMain = !empty($curRow['is_main']);
            ?>
              <option value="<?= $code ?>" <?= $isMain ? 'selected' : '' ?>><?= $code ?></option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="HUF">HUF</option>
          <?php endif; ?>
        </select>

      </div>

      <div class="sm:col-span-2">
        <label class="label">First due</label>
        <input name="next_due" type="date" class="input" required />
      </div>

      <div class="sm:col-span-2">
        <label class="label">Category</label>
        <select name="category_id" class="select">
          <option value="">No category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Recurrence builder -->
      <div class="sm:col-span-12 rounded-xl border p-3">
        <input type="hidden" name="rrule" id="rrule-add" />
        <div class="grid md:grid-cols-12 gap-2">
          <div class="md:col-span-3">
            <label class="label">Repeats</label>
            <select class="select" id="rb-freq-add">
              <option value="">Does not repeat</option>
              <option value="DAILY">Daily</option>
              <option value="WEEKLY">Weekly</option>
              <option value="MONTHLY">Monthly</option>
              <option value="YEARLY">Yearly</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="label">Every</label>
            <input type="number" min="1" value="1" id="rb-interval-add" class="input" />
          </div>

          <!-- Weekly options -->
          <div class="md:col-span-7" id="rb-weekly-add" style="display:none">
            <label class="label">On</label>
            <div class="flex flex-wrap gap-2 text-sm">
              <?php
                $days = ['MO'=>'Mon','TU'=>'Tue','WE'=>'Wed','TH'=>'Thu','FR'=>'Fri','SA'=>'Sat','SU'=>'Sun'];
                foreach($days as $code=>$lbl): ?>
                <label class="inline-flex items-center gap-1">
                  <input type="checkbox" value="<?= $code ?>" class="rb-byday-add">
                  <span><?= $lbl ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Monthly options -->
          <div class="md:col-span-7" id="rb-monthly-add" style="display:none">
            <label class="label">Day of month</label>
            <input type="number" min="1" max="31" id="rb-bymonthday-add" class="input" placeholder="e.g., 10" />
          </div>

          <!-- Yearly options -->
          <div class="md:col-span-7" id="rb-yearly-add" style="display:none">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="label">Month</label>
                <input type="number" min="1" max="12" id="rb-bymonth-add" class="input" placeholder="1-12" />
              </div>
              <div>
                <label class="label">Day</label>
                <input type="number" min="1" max="31" id="rb-bymday-add" class="input" placeholder="1-31" />
              </div>
            </div>
          </div>

          <!-- Ends -->
          <div class="md:col-span-12 grid md:grid-cols-12 gap-2">
            <div class="md:col-span-3">
              <label class="label">Ends</label>
              <select class="select" id="rb-endtype-add">
                <option value="none">Never</option>
                <option value="count">After # times</option>
                <option value="until">On date</option>
              </select>
            </div>
            <div class="md:col-span-2" id="rb-count-wrap-add" style="display:none">
              <label class="label">Count</label>
              <input type="number" min="1" id="rb-count-add" class="input" />
            </div>
            <div class="md:col-span-3" id="rb-until-wrap-add" style="display:none">
              <label class="label">Until</label>
              <input type="date" id="rb-until-add" class="input" />
            </div>
            <div class="md:col-span-4 flex items-end justify-end">
              <div class="text-xs text-gray-500" id="rb-summary-add"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="sm:col-span-12 flex justify-end">
        <button class="btn btn-primary">Add</button>
      </div>
    </form>

  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold">Scheduled payments</h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Title</th>
          <th class="py-2 pr-3">Amount</th>
          <th class="py-2 pr-3">Currency</th>
          <th class="py-2 pr-3">Repeats</th>
          <th class="py-2 pr-3">First payment</th>
          <th class="py-2 pr-3">Category</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr class="border-b">
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($r['title']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
            <td class="py-2 pr-3 text-sm text-gray-600">
              <span class="rrule-summary" data-rrule="<?= htmlspecialchars($r['rrule'] ?? '') ?>"></span>
            </td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['next_due'] ?? '—') ?></td>
            <td class="py-2 pr-3">
              <div class="flex flex-row justify-between items-center gap-2 flex-wrap">
                <?php if (!empty($r['cat_label'])): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($r['cat_color']) ?>;"></span>
                    <?= htmlspecialchars($r['cat_label']) ?>
                  </span>
                <?php endif; ?>

                <div class="flex flex-row gap-2">
                  <button
                    type="button"
                    class="btn btn-primary !py-1 !px-3"
                    data-edit-scheduled
                    data-id="<?= (int)$r['id'] ?>"
                    data-title="<?= htmlspecialchars($r['title']) ?>"
                    data-amount="<?= htmlspecialchars($r['amount']) ?>"
                    data-currency="<?= htmlspecialchars($r['currency']) ?>"
                    data-next_due="<?= htmlspecialchars($r['next_due']) ?>"
                    data-category_id="<?= (int)($r['category_id'] ?? 0) ?>"
                    data-rrule="<?= htmlspecialchars($r['rrule'] ?? '') ?>"
                  >Edit</button>
  
                  <form method="post" action="/scheduled/delete" class="inline"
                        onsubmit="return confirm('Delete this scheduled item?');">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                    <button class="btn btn-danger !py-1 !px-3">Remove</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="md:hidden space-y-3">
    <?php foreach($rows as $r): ?>
      <div class="rounded-xl border p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($r['title']) ?></div>
            <?php if (!empty($r['cat_label'])): ?>
              <div class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[11px]">
                <span class="inline-block h-2 w-2 rounded-full" style="background-color: <?= htmlspecialchars($r['cat_color']) ?>;"></span>
                <?= htmlspecialchars($r['cat_label']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="text-right">
            <div class="font-semibold"><?= moneyfmt($r['amount']) ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($r['currency']) ?></div>
          </div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
          <div class="rounded-lg bg-gray-50 p-2">
            <div class="text-gray-500">First payment</div>
            <div class="font-medium"><?= htmlspecialchars($r['next_due'] ?? '—') ?></div>
          </div>
          <div class="rounded-lg bg-gray-50 p-2">
            <div class="text-gray-500">Repeats</div>
            <div class="font-medium">
              <span class="rrule-summary" data-rrule="<?= htmlspecialchars($r['rrule'] ?? '') ?>"></span>
            </div>
          </div>
        </div>

        <div class="mt-3 flex items-center justify-end gap-2">
          <button
            type="button"
            class="btn btn-primary !py-1.5 !px-3"
            data-edit-scheduled
            data-id="<?= (int)$r['id'] ?>"
            data-title="<?= htmlspecialchars($r['title']) ?>"
            data-amount="<?= htmlspecialchars($r['amount']) ?>"
            data-currency="<?= htmlspecialchars($r['currency']) ?>"
            data-next_due="<?= htmlspecialchars($r['next_due']) ?>"
            data-category_id="<?= (int)($r['category_id'] ?? 0) ?>"
            data-rrule="<?= htmlspecialchars($r['rrule'] ?? '') ?>"
          >Edit</button>

          <form method="post" action="/scheduled/delete" class="inline"
                onsubmit="return confirm('Delete this scheduled item?');">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
            <button class="btn btn-danger !py-1.5 !px-3">Remove</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
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

function wireRR(id){
  const $ = (x)=>document.getElementById(x);
  const freq   = $(`rb-freq-${id}`);
  const inter  = $(`rb-interval-${id}`);
  const weekly = $(`rb-weekly-${id}`);
  const monthly= $(`rb-monthly-${id}`);
  const yearly = $(`rb-yearly-${id}`);
  const bydayCbs = Array.from(document.querySelectorAll(`.rb-byday-${id}`));
  const bymonthday = $(`rb-bymonthday-${id}`);
  const bymonth = $(`rb-bymonth-${id}`);
  const bymday  = $(`rb-bymday-${id}`);

  const endtype = $(`rb-endtype-${id}`);
  const countWrap = $(`rb-count-wrap-${id}`);
  const countInp  = $(`rb-count-${id}`);
  const untilWrap = $(`rb-until-wrap-${id}`);
  const untilInp  = $(`rb-until-${id}`);

  const out     = $(`rrule-${id}`);
  const sum     = $(`rb-summary-${id}`);

  function toggleEnds(){
    const mode = endtype ? endtype.value : 'none';
    // visibility
    countWrap && (countWrap.style.display = (mode==='count') ? '' : 'none');
    untilWrap && (untilWrap.style.display = (mode==='until') ? '' : 'none');
    // required flags
    if (countInp) countInp.required = (mode==='count');
    if (untilInp) untilInp.required = (mode==='until');
    // clear the inactive field so it doesn't get serialized
    if (mode !== 'count' && countInp) countInp.value = '';
    if (mode !== 'until' && untilInp) untilInp.value = '';
  }

  function toggleFreqBlocks(){
    const f = freq ? freq.value : '';
    weekly && (weekly.style.display = (f==='WEEKLY') ? '' : 'none');
    monthly&& (monthly.style.display= (f==='MONTHLY') ? '' : 'none');
    yearly && (yearly.style.display = (f==='YEARLY') ? '' : 'none');
  }

  function build(){
    if (!out) return;
    let r = [];
    const f = freq ? freq.value : '';
    if (!f){ out.value=''; sum && (sum.textContent='One-time'); return; }

    r.push('FREQ='+f);

    const iv = Math.max(1, parseInt(inter?.value || '1', 10));
    if (iv > 1) r.push('INTERVAL='+iv);

    if (f === 'WEEKLY'){
      const days = bydayCbs.filter(cb=>cb.checked).map(cb=>cb.value);
      if (days.length) r.push('BYDAY='+days.join(','));
    } else if (f === 'MONTHLY'){
      const dom = parseInt(bymonthday?.value || '', 10);
      if (!isNaN(dom)) r.push('BYMONTHDAY='+dom);
    } else if (f === 'YEARLY'){
      const mo = parseInt(bymonth?.value || '', 10);
      const dy = parseInt(bymday?.value  || '', 10);
      if (!isNaN(mo)) r.push('BYMONTH='+mo);
      if (!isNaN(dy)) r.push('BYMONTHDAY='+dy);
    }

    // Ends
    const mode = endtype ? endtype.value : 'none';
    if (mode === 'count'){
      const c = parseInt(countInp?.value || '', 10);
      if (!isNaN(c) && c > 0) r.push('COUNT='+c);
    } else if (mode === 'until'){
      const d = (untilInp?.value || '').replaceAll('-',''); // YYYYMMDD
      if (d) r.push('UNTIL='+d);
    }

    out.value = r.join(';');
    if (typeof rrSummary === 'function') {
      sum && (sum.textContent = rrSummary(out.value));
    } else {
      sum && (sum.textContent = out.value || 'One-time');
    }
  }

  // Prefill from hidden RRULE (if present)
  if (out && out.value){
    const p = parseRR ? parseRR(out.value) : null;
    if (p){
      if (freq) freq.value = p.FREQ || '';
      if (inter) inter.value = p.INTERVAL || 1;
      if (bymonthday && p.BYMONTHDAY!=null) bymonthday.value = p.BYMONTHDAY;
      if (bymonth && p.BYMONTH!=null) bymonth.value = p.BYMONTH;
      if (bymday && p.BYMONTHDAY!=null) bymday.value = p.BYMONTHDAY;
      if (Array.isArray(p.BYDAY) && bydayCbs.length){
        const set = new Set(p.BYDAY);
        bydayCbs.forEach(cb => cb.checked = set.has(cb.value));
      }
      if (endtype){
        if (p.COUNT){ endtype.value='count'; if (countInp) countInp.value = p.COUNT; }
        else if (p.UNTIL){ endtype.value='until';
          if (untilInp && p.UNTIL.length >= 8){
            untilInp.value = `${p.UNTIL.slice(0,4)}-${p.UNTIL.slice(4,6)}-${p.UNTIL.slice(6,8)}`;
          }
        } else { endtype.value='none'; }
      }
    }
  }

  // wire events
  [freq, inter, ...bydayCbs, bymonthday, bymonth, bymday, endtype, countInp, untilInp]
    .forEach(el => el && el.addEventListener('input', build));
  freq && freq.addEventListener('change', ()=>{ toggleFreqBlocks(); build(); });
  endtype && endtype.addEventListener('change', ()=>{ toggleEnds(); build(); });

  // initial
  toggleFreqBlocks();
  toggleEnds();
  build();
}


// Wire Add builder
wireRR('add');

// Wire all edit builders (no PHP vars needed here)
document.querySelectorAll('input[id^="rrule-"]').forEach(h => {
  const id = h.id.replace('rrule-','');
  wireRR(id);
  const freq = document.getElementById(`rb-freq-${id}`);
  freq && freq.dispatchEvent(new Event('change'));
});
</script>

<!-- Modal: Scheduled Editor -->
<div id="sched-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="sched-title">
  <div class="modal-backdrop" data-close-sched></div>

  <div class="modal-panel">
    <!-- Header -->
    <div class="modal-header">
      <h3 id="sched-title" class="font-semibold">Edit Scheduled Payment</h3>
      <button class="icon-btn" aria-label="Close" data-close-sched>✕</button>
    </div>

    <!-- Scrollable body -->
    <div class="modal-body">
      <form id="sched-form" method="post" action="/scheduled/edit" class="grid gap-3 md:grid-cols-12">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="id" id="sched-id" />
        <input type="hidden" name="rrule" id="rrule-dlg" />

        <div class="md:col-span-5">
          <label class="label">Title</label>
          <input name="title" id="sched-title-input" class="input" required />
        </div>

        <div class="md:col-span-3">
          <label class="label">Amount</label>
          <input name="amount" id="sched-amount" type="number" step="0.01" class="input" required />
        </div>

        <div class="md:col-span-2">
          <label class="label">Currency</label>
          <select name="currency" id="sched-currency" class="select">
            <?php foreach ($userCurrencies as $curRow): $code = htmlspecialchars($curRow['code']??''); ?>
              <option value="<?= $code ?>"><?= $code ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="label">First payment</label>
          <input name="next_due" id="sched-nextdue" type="date" class="input" required />
        </div>

        <div class="md:col-span-12 md:col-span-4">
          <label class="label">Category</label>
          <select name="category_id" id="sched-category" class="select">
            <option value="">No category</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Recurrence builder -->
        <div class="md:col-span-12 rounded-xl border p-3">
          <div class="grid md:grid-cols-12 gap-2">
            <div class="md:col-span-3">
              <label class="label">Repeats</label>
              <select class="select" id="rb-freq-dlg">
                <option value="">Does not repeat</option>
                <option value="DAILY">Daily</option>
                <option value="WEEKLY">Weekly</option>
                <option value="MONTHLY">Monthly</option>
                <option value="YEARLY">Yearly</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="label">Every</label>
              <input type="number" min="1" value="1" id="rb-interval-dlg" class="input" />
            </div>

            <!-- Weekly -->
            <div class="md:col-span-7" id="rb-weekly-dlg" style="display:none">
              <label class="label">On</label>
              <div class="flex flex-wrap gap-2 text-sm">
                <?php foreach(['MO'=>'Mon','TU'=>'Tue','WE'=>'Wed','TH'=>'Thu','FR'=>'Fri','SA'=>'Sat','SU'=>'Sun'] as $code=>$lbl): ?>
                  <label class="inline-flex items-center gap-1">
                    <input type="checkbox" value="<?= $code ?>" class="rb-byday-dlg">
                    <span><?= $lbl ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Monthly -->
            <div class="md:col-span-7" id="rb-monthly-dlg" style="display:none">
              <label class="label">Day of month</label>
              <input type="number" min="1" max="31" id="rb-bymonthday-dlg" class="input" placeholder="e.g., 10" />
            </div>

            <!-- Yearly -->
            <div class="md:col-span-7" id="rb-yearly-dlg" style="display:none">
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="label">Month</label>
                  <input type="number" min="1" max="12" id="rb-bymonth-dlg" class="input" />
                </div>
                <div>
                  <label class="label">Day</label>
                  <input type="number" min="1" max="31" id="rb-bymday-dlg" class="input" />
                </div>
              </div>
            </div>

            <!-- Ends -->
            <div class="md:col-span-12 grid md:grid-cols-12 gap-2">
              <div class="md:col-span-3">
                <label class="label">Ends</label>
                <select class="select" id="rb-endtype-dlg">
                  <option value="none">Never</option>
                  <option value="count">After # times</option>
                  <option value="until">On date</option>
                </select>
              </div>
              <div class="md:col-span-2" id="rb-count-wrap-dlg" style="display:none">
                <label class="label">Count</label>
                <input type="number" min="1" id="rb-count-dlg" class="input" />
              </div>
              <div class="md:col-span-3" id="rb-until-wrap-dlg" style="display:none">
                <label class="label">Until</label>
                <input type="date" id="rb-until-dlg" class="input" />
              </div>
              <div class="md:col-span-4 flex items-end justify-end">
                <div class="text-xs text-gray-500" id="rb-summary-dlg"></div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Sticky footer -->
    <div class="modal-footer flex items-center justify-end gap-2">
      <button type="button" class="btn" data-close-sched>Cancel</button>
      <button type="submit" class="btn btn-primary" form="sched-form" id="sched-save">Save</button>
    </div>
  </div>
</div>


<script>
  // --- open/close helpers ---
  const modal = document.getElementById('sched-modal');
  function openSched(){ modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
  function closeSched(){ modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }

  // Close handlers
  modal.querySelectorAll('[data-close-sched]').forEach(el=>el.addEventListener('click', closeSched));
  document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) closeSched(); });

  // --- populate modal from data-* on button ---
  document.querySelectorAll('[data-edit-scheduled]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const get = (k)=>btn.getAttribute(k) || '';

      // Fill basics
      document.getElementById('sched-id').value       = get('data-id');
      document.getElementById('sched-title-input').value    = get('data-title');
      document.getElementById('sched-amount').value   = get('data-amount');
      document.getElementById('sched-nextdue').value  = get('data-next_due');

      // Currency select
      const cur = get('data-currency').toUpperCase();
      const curSel = document.getElementById('sched-currency');
      if (curSel) {
        Array.from(curSel.options).forEach(o=>o.selected = (o.value.toUpperCase()===cur));
      }

      // Category
      const catId = get('data-category_id');
      const catSel = document.getElementById('sched-category');
      if (catSel) {
        Array.from(catSel.options).forEach(o=>o.selected = (o.value === String(catId)));
      }

      // RRULE: write to hidden, then wire + prefill UI via wireRR
      document.getElementById('rrule-dlg').value = get('data-rrule') || '';

      // (Re)wire the recurrence builder for 'dlg'
      if (typeof wireRR === 'function') {
        wireRR('dlg'); // parses existing hidden rrule and updates inputs/summary
        const freqEl = document.getElementById('rb-freq-dlg');
        if (freqEl) freqEl.dispatchEvent(new Event('change')); // show correct sections
      }

      openSched();
    });
  });
</script>

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
  // Keep rrule-dlg in sync right before submit (prevents stale value edge-cases)
  const schedForm = document.getElementById('sched-form');
  if (schedForm) {
    schedForm.addEventListener('submit', () => {
      if (typeof wireRR === 'function') wireRR('dlg'); // rebuild once
      // Optional: guard against double submit
      const saveBtn = document.getElementById('sched-save');
      saveBtn && (saveBtn.disabled = true);
    });
  }
</script>
