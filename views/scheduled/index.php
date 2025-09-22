<section class="card">
  <h1 class="text-xl font-semibold"><?= __('Scheduled Payments') ?></h1>
  <p class="text-sm text-gray-500"><?= __('Set up recurring payments.') ?></p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= __('Add scheduled payment') ?></summary>
    <form method="post" action="/scheduled/add" class="grid gap-3 sm:grid-cols-12">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <div class="sm:col-span-4">
        <label class="label"><?= __('Title') ?></label>
        <input name="title" class="input" placeholder="<?= __('e.g., Rent') ?>" required />
      </div>

      <div class="sm:col-span-2">
        <label class="label"><?= __('Amount') ?></label>
        <input name="amount" type="number" step="0.01" class="input" required />
      </div>

      <div class="sm:col-span-2">
        <label class="label"><?= __('Currency') ?></label>
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
        <label class="label"><?= __('First due') ?></label>
        <input name="next_due" type="date" class="input" required />
      </div>

      <div class="sm:col-span-2">
        <label class="label"><?= __('Category') ?></label>
        <select name="category_id" class="select">
          <option value=""><?= __('No category') ?></option>
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
            <label class="label"><?= __('Repeats') ?></label>
            <select class="select" id="rb-freq-add">
              <option value=""><?= __('Does not repeat') ?></option>
              <option value="DAILY"><?= __('Daily') ?></option>
              <option value="WEEKLY"><?= __('Weekly') ?></option>
              <option value="MONTHLY"><?= __('Monthly') ?></option>
              <option value="YEARLY"><?= __('Yearly') ?></option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="label"><?= __('Every') ?></label>
            <input type="number" min="1" value="1" id="rb-interval-add" class="input" />
          </div>

          <!-- Weekly options -->
          <div class="md:col-span-7" id="rb-weekly-add" style="display:none">
            <label class="label"><?= __('On') ?></label>
            <div class="flex flex-wrap gap-2 text-sm">
              <?php
                $days = ['MO'=>'Mon','TU'=>'Tue','WE'=>'Wed','TH'=>'Thu','FR'=>'Fri','SA'=>'Sat','SU'=>'Sun'];
                foreach($days as $code=>$lbl): ?>
                <label class="inline-flex items-center gap-1">
                  <input type="checkbox" value="<?= $code ?>" class="rb-byday-add">
                  <span><?= __($lbl) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Monthly options -->
          <div class="md:col-span-7" id="rb-monthly-add" style="display:none">
            <label class="label"><?= __('Day of month') ?></label>
            <input type="number" min="1" max="31" id="rb-bymonthday-add" class="input" placeholder="<?= __('e.g., 10') ?>" />
          </div>

          <!-- Yearly options -->
          <div class="md:col-span-7" id="rb-yearly-add" style="display:none">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="label"><?= __('Month') ?></label>
                <input type="number" min="1" max="12" id="rb-bymonth-add" class="input" placeholder="1-12" />
              </div>
              <div>
                <label class="label"><?= __('Day') ?></label>
                <input type="number" min="1" max="31" id="rb-bymday-add" class="input" placeholder="1-31" />
              </div>
            </div>
          </div>

          <!-- Ends -->
          <div class="md:col-span-12 grid md:grid-cols-12 gap-2">
            <div class="md:col-span-3">
              <label class="label"><?= __('Ends') ?></label>
              <select class="select" id="rb-endtype-add">
                <option value="none"><?= __('Never') ?></option>
                <option value="count"><?= __('After # times') ?></option>
                <option value="until"><?= __('On date') ?></option>
              </select>
            </div>
            <div class="md:col-span-2" id="rb-count-wrap-add" style="display:none">
              <label class="label"><?= __('Count') ?></label>
              <input type="number" min="1" id="rb-count-add" class="input" />
            </div>
            <div class="md:col-span-3" id="rb-until-wrap-add" style="display:none">
              <label class="label"><?= __('Until') ?></label>
              <input type="date" id="rb-until-add" class="input" />
            </div>
            <div class="md:col-span-4 flex items-end justify-end">
              <div class="text-xs text-gray-500" id="rb-summary-add"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="sm:col-span-12 flex justify-end">
        <button class="btn btn-primary"><?= __('Add') ?></button>
      </div>
    </form>

  </details>
</section>

<section class="mt-6 card">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold"><?= __('Scheduled payments') ?></h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="table-glass min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3"><?= __('Title') ?></th>
          <th class="py-2 pr-3"><?= __('Amount') ?></th>
          <th class="py-2 pr-3"><?= __('Currency') ?></th>
          <th class="py-2 pr-3"><?= __('Repeats') ?></th>
          <th class="py-2 pr-3"><?= __('First payment') ?></th>
          <th class="py-2 pr-3"><?= __('Category') ?></th>
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
                  ><?= __('Edit') ?></button>

                  <form method="post" action="/scheduled/delete" class="inline"
                        onsubmit="return confirm('<?= __('Delete this scheduled item?') ?>');">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                    <button class="btn btn-danger !py-1 !px-3"><?= __('Remove') ?></button>
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
      <div class="panel p-4">
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
            <div class="text-gray-500"><?= __('First payment') ?></div>
            <div class="font-medium"><?= htmlspecialchars($r['next_due'] ?? '—') ?></div>
          </div>
          <div class="rounded-lg bg-gray-50 p-2">
            <div class="text-gray-500"><?= __('Repeats') ?></div>
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
          ><?= __('Edit') ?></button>

          <form method="post" action="/scheduled/delete" class="inline"
                onsubmit="return confirm('<?= __('Delete this scheduled item?') ?>');">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
            <button class="btn btn-danger !py-1.5 !px-3"><?= __('Remove') ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

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
  return (str || '').replace(/:([a-z_]+)/gi, (_, key) => (params[key] ?? ''));
}

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
    if (!f){ out.value=''; sum && (sum.textContent=rrI18n.oneTime); return; }

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
      sum && (sum.textContent = out.value || rrI18n.oneTime);
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
      <h3 id="sched-title" class="font-semibold"><?= __('Edit Scheduled Payment') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close-sched>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <!-- Scrollable body -->
    <div class="modal-body">
      <form id="sched-form" method="post" action="/scheduled/edit" class="grid gap-3 md:grid-cols-12">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="id" id="sched-id" />
        <input type="hidden" name="rrule" id="rrule-dlg" />

        <div class="md:col-span-5">
          <label class="label"><?= __('Title') ?></label>
          <input name="title" id="sched-title-input" class="input" required />
        </div>

        <div class="md:col-span-3">
          <label class="label"><?= __('Amount') ?></label>
          <input name="amount" id="sched-amount" type="number" step="0.01" class="input" required />
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= __('Currency') ?></label>
          <select name="currency" id="sched-currency" class="select">
            <?php foreach ($userCurrencies as $curRow): $code = htmlspecialchars($curRow['code']??''); ?>
              <option value="<?= $code ?>"><?= $code ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="label"><?= __('First payment') ?></label>
          <input name="next_due" id="sched-nextdue" type="date" class="input" required />
        </div>

        <div class="md:col-span-12 md:col-span-4">
          <label class="label"><?= __('Category') ?></label>
          <select name="category_id" id="sched-category" class="select">
            <option value=""><?= __('No category') ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Recurrence builder -->
        <div class="md:col-span-12 rounded-xl border p-3">
          <div class="grid md:grid-cols-12 gap-2">
            <div class="md:col-span-3">
              <label class="label"><?= __('Repeats') ?></label>
              <select class="select" id="rb-freq-dlg">
                <option value=""><?= __('Does not repeat') ?></option>
                <option value="DAILY"><?= __('Daily') ?></option>
                <option value="WEEKLY"><?= __('Weekly') ?></option>
                <option value="MONTHLY"><?= __('Monthly') ?></option>
                <option value="YEARLY"><?= __('Yearly') ?></option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="label"><?= __('Every') ?></label>
              <input type="number" min="1" value="1" id="rb-interval-dlg" class="input" />
            </div>

            <!-- Weekly -->
            <div class="md:col-span-7" id="rb-weekly-dlg" style="display:none">
              <label class="label"><?= __('On') ?></label>
              <div class="flex flex-wrap gap-2 text-sm">
                <?php foreach(['MO'=>'Mon','TU'=>'Tue','WE'=>'Wed','TH'=>'Thu','FR'=>'Fri','SA'=>'Sat','SU'=>'Sun'] as $code=>$lbl): ?>
                  <label class="inline-flex items-center gap-1">
                    <input type="checkbox" value="<?= $code ?>" class="rb-byday-dlg">
                    <span><?= __($lbl) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Monthly -->
            <div class="md:col-span-7" id="rb-monthly-dlg" style="display:none">
              <label class="label"><?= __('Day of month') ?></label>
              <input type="number" min="1" max="31" id="rb-bymonthday-dlg" class="input" placeholder="<?= __('e.g., 10') ?>" />
            </div>

            <!-- Yearly -->
            <div class="md:col-span-7" id="rb-yearly-dlg" style="display:none">
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="label"><?= __('Month') ?></label>
                  <input type="number" min="1" max="12" id="rb-bymonth-dlg" class="input" />
                </div>
                <div>
                  <label class="label"><?= __('Day') ?></label>
                  <input type="number" min="1" max="31" id="rb-bymday-dlg" class="input" />
                </div>
              </div>
            </div>

            <!-- Ends -->
            <div class="md:col-span-12 grid md:grid-cols-12 gap-2">
              <div class="md:col-span-3">
              <label class="label"><?= __('Ends') ?></label>
                <select class="select" id="rb-endtype-dlg">
                  <option value="none"><?= __('Never') ?></option>
                  <option value="count"><?= __('After # times') ?></option>
                  <option value="until"><?= __('On date') ?></option>
                </select>
              </div>
              <div class="md:col-span-2" id="rb-count-wrap-dlg" style="display:none">
                <label class="label"><?= __('Count') ?></label>
                <input type="number" min="1" id="rb-count-dlg" class="input" />
              </div>
              <div class="md:col-span-3" id="rb-until-wrap-dlg" style="display:none">
                <label class="label"><?= __('Until') ?></label>
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
      <button type="button" class="btn" data-close-sched><?= __('Cancel') ?></button>
      <button type="submit" class="btn btn-primary" form="sched-form" id="sched-save"><?= __('Save') ?></button>
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
      const until = `${p.UNTIL.slice(0,4)}-${p.UNTIL.slice(4,6)}-${p.UNTIL.slice(6,8)}`;
      end = rrFormat(rrI18n.until, { date: until });
    }

    const summary = `${everyText}${extras}${end}`.trim();
    return summary || rrI18n.repeats;
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
