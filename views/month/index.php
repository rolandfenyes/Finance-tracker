<?php $ym = sprintf('%04d-%02d', $y, $m); ?>
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-6 shadow-glass">
    <h2 class="text-lg font-semibold mb-4"><?= $ym ?></h2>

    <!-- Net focus -->
    <?php $net = $sumIn_main - $sumOut_main; ?>
    <div class="text-center mb-6">
      <div class="text-sm text-gray-500">Net (<?= htmlspecialchars($main) ?>)</div>
      <div class="text-3xl font-bold <?= $net>=0 ? 'text-green-600' : 'text-red-600' ?>">
        <?= moneyfmt($net, $main) ?>
      </div>
    </div>

    <!-- Income vs Spending -->
    <div class="grid grid-cols-2 gap-4 text-sm">
      <div class="p-3 rounded-xl bg-green-50 text-green-700 text-center">
        <div class="font-medium">Income</div>
        <div class="text-md font-semibold"><?= moneyfmt($sumIn_main, $main) ?></div>
      </div>
      <div class="p-3 rounded-xl bg-red-50 text-red-700 text-center">
        <div class="font-medium">Spending</div>
        <div class="text-md font-semibold"><?= moneyfmt($sumOut_main, $main) ?></div>
      </div>
    </div>

    <!-- Native breakdown -->
    <div class="mt-6 text-xs text-gray-500 space-y-1">
      <div>
        <span class="font-medium text-gray-600">Native income:</span>
        <?php if (!empty($sumIn_native_by_cur)): ?>
          <?php foreach ($sumIn_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a, $c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
      <div>
        <span class="font-medium text-gray-600">Native spending:</span>
        <?php if (!empty($sumOut_native_by_cur)): ?>
          <?php foreach ($sumOut_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a, $c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="text-base font-semibold mb-3">Quick Add</h3>

    <form class="grid gap-4 md:grid-cols-12 md:items-end" method="post" action="/months/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="y" value="<?= $y ?>" />
      <input type="hidden" name="m" value="<?= $m ?>" />

      <!-- Type -->
      <div class="field md:col-span-2">
        <label class="label">Type</label>
        <select name="kind" class="select">
          <option value="income">Income</option>
          <option value="spending">Spending</option>
        </select>
      </div>

      <!-- Category -->
      <div class="field md:col-span-3">
        <label class="label">Category</label>
        <select name="category_id" class="select">
          <option value="">— Category —</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= $c['id'] ?>"><?= ucfirst($c['kind']) ?> · <?= htmlspecialchars($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Amount + Currency -->
      <div class="field md:col-span-4">
        <label class="label">Amount</label>
        <div class="grid grid-cols-5 gap-2">
          <input name="amount" type="number" step="0.01" class="input col-span-3" placeholder="0.00" required />
          <select name="currency" class="select col-span-2">
            <?php foreach ($userCurrencies as $c): ?>
              <option value="<?= htmlspecialchars($c['code']) ?>" <?= $c['is_main'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['code']) ?>
              </option>
            <?php endforeach; ?>
            <?php if (!count($userCurrencies)): ?><option value="HUF">HUF</option><?php endif; ?>
          </select>
        </div>
      </div>



      <!-- Date -->
      <div class="field md:col-span-2">
        <label class="label">Date</label>
        <input name="occurred_on" type="date" value="<?= $ym ?>-01" class="input" />
      </div>

      <!-- Note -->
      <div class="field md:col-span-8">
        <label class="label">Note <span class="help">(optional)</span></label>
        <input name="note" class="input" placeholder="Add a short note…" />
      </div>

      <!-- Submit -->
      <div class="md:col-span-4 flex md:justify-end">
        <button class="btn btn-primary w-full md:w-auto">Add</button>
      </div>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <!-- Spending by category -->
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80 overflow-hidden">
    <h3 class="font-semibold mb-3">Spending by Category (<?= htmlspecialchars($main) ?>)</h3>
    <?php
      // Build grouped sums from $allTx in MAIN currency
      $grp = []; $cols = [];
      foreach (($allTx ?? []) as $r) {
        if (($r['kind'] ?? '') !== 'spending') continue;
        // amount in main currency
        if (isset($r['amount_main']) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
          $amtMain = (float)$r['amount_main'];
        } else {
          $nativeCur = $r['currency'] ?: $main;
          $amtMain = fx_convert($pdo, (float)$r['amount'], $nativeCur, $main, $r['occurred_on']);
        }
        $label = $r['cat_label'] ?? 'Uncategorized';
        $color = $r['cat_color'] ?? '#6B7280';
        // squash tiny negatives/rounding noise
        if ($amtMain <= 0) continue;
        $grp[$label] = ($grp[$label] ?? 0) + $amtMain;
        if (!isset($cols[$label])) $cols[$label] = $color;
      }
      // sort desc
      arsort($grp);
      $labels = array_keys($grp);
      $data   = array_values($grp);
      $colors = array_map(fn($k)=>$cols[$k] ?? '#6B7280', $labels);
    ?>
      <div class="relative h-60 w-full">
        <canvas id="spendcat-month" class="absolute inset-0"></canvas>
      </div>
    <script>
      (function(){
        const el = document.getElementById('spendcat-month');
        if (!el) return;
        const labels = <?= json_encode($labels) ?>;
        const data   = <?= json_encode($data) ?>;
        const colors = <?= json_encode($colors) ?>;

        // guard: nothing to show
        if (!labels.length) {
          el.outerHTML = '<div class="text-sm text-gray-500">No spending this month.</div>';
          return;
        }

        // ensure Chart.js present
        if (typeof Chart === 'undefined') {
          const s = document.createElement('script');
          s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
          s.onload = draw;
          document.head.appendChild(s);
        } else {
          draw();
        }

        function draw(){
          new Chart(el.getContext('2d'), {
            type: 'doughnut',
            data: {
              labels,
              datasets: [{ data, backgroundColor: colors }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'right' },
                tooltip: { callbacks: {
                  label: (ctx) => `${ctx.label}: ${Number(ctx.parsed).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})} <?= $main ?>`
                }}
              },
              cutout: '55%'
            }
          });
        }
      })();
    </script>
  </div>

  <!-- Daily Spending (bars) + 7-day MA (line) -->
  <div class="bg-white rounded-2xl p-5 shadow-glass  h-80">
    <h3 class="font-semibold mb-3">Daily Spending (<?= htmlspecialchars($main) ?>)</h3>
    <?php
      $rows = $allTx ?? $tx ?? [];

      // prepare day list for the month
      $cursor = new DateTime($first);
      $end    = new DateTime($last);
      $days   = [];
      while ($cursor <= $end) { $days[] = $cursor->format('Y-m-d'); $cursor->modify('+1 day'); }

      // spending per day (MAIN currency, positive)
      $spend = array_fill_keys($days, 0.0);
      foreach ($rows as $r) {
        if (($r['kind'] ?? '') !== 'spending') continue;
        $d = substr($r['occurred_on'], 0, 10);
        if (!isset($spend[$d])) continue;

        // amount -> main
        if (isset($r['amount_main']) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
          $amtMain = (float)$r['amount_main'];
        } else {
          $from = $r['currency'] ?: $main;
          $amtMain = fx_convert($pdo, (float)$r['amount'], $from, $main, $r['occurred_on']);
        }
        $spend[$d] += max(0, $amtMain);
      }

      // 7-day rolling average
      $labels = array_keys($spend);
      $vals   = array_values($spend);
      $ma7    = [];
      $win = 7;
      for ($i=0; $i<count($vals); $i++) {
        $start = max(0, $i-$win+1);
        $slice = array_slice($vals, $start, $i-$start+1);
        $ma7[] = round(array_sum($slice)/max(1,count($slice)), 2);
      }
    ?>
    <div class="relative h-56 md:h-64">
      <canvas id="daily-spend" class="absolute inset-0 w-full h-full"></canvas>
    </div>

    <script>
    (function(){
      const el = document.getElementById('daily-spend');
      if (!el) return;

      const labels = <?= json_encode($labels) ?>;
      const bars   = <?= json_encode(array_map(fn($v)=>round($v,2), $vals)) ?>;
      const avg7   = <?= json_encode($ma7) ?>;

      const allZero = (arr)=>arr.every(v => Math.abs(v) < 1e-9);
      if (!labels.length || (allZero(bars))) {
        el.parentElement.innerHTML = '<div class="text-sm text-gray-500">No spending this month.</div>';
        return;
      }

      function draw(){
        new Chart(el.getContext('2d'), {
          data: {
            labels,
            datasets: [
              { type:'bar',  label:'Spending', data:bars, borderWidth:0 },
              { type:'line', label:'7-day Avg', data:avg7, borderWidth:2, tension:0.25, pointRadius:0, fill:false }
            ]
          },
          options: {
            responsive:true, maintainAspectRatio:false,
            interaction:{ mode:'index', intersect:false },
            plugins:{
              legend:{ position:'bottom' },
              tooltip:{ callbacks:{ label:(ctx)=>{
                const v = Number(ctx.parsed.y).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
                return `${ctx.dataset.label}: ${v} <?= $main ?>`;
              }}}
            },
            scales:{
              x:{ grid:{ display:false }, ticks:{ maxTicksLimit:10 } },
              y:{ grid:{ color:'rgba(0,0,0,0.05)' } }
            }
          }
        });
      }

      if (typeof Chart === 'undefined') {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        s.onload = draw; document.head.appendChild(s);
      } else { draw(); }
    })();
    </script>
  </div>



</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <h3 class="font-semibold mb-3">Transactions</h3>

  <!-- Mobile: stacked cards -->
  <div class="md:hidden space-y-3">
    <?php foreach ($allTx as $row): ?>
      <?php
        $isVirtual = !empty($row['is_virtual']);
        $nativeCur = $row['currency'] ?: $main;

        if ($isVirtual && isset($row['amount_main'])) {
          $amtMain = (float)$row['amount_main'];
          $mainCur = $row['main_currency'] ?? $main;
        } else {
          if (isset($row['amount_main']) && $row['amount_main'] !== null && !empty($row['main_currency'])) {
            $amtMain = (float)$row['amount_main'];
            $mainCur = $row['main_currency'];
          } else {
            $amtMain = fx_convert($pdo, (float)$row['amount'], $nativeCur, $main, $row['occurred_on']);
            $mainCur = $main;
          }
        }
        $dot = $row['cat_color'] ?? '#6B7280';
      ?>
      <div class="rounded-xl border p-3 <?= $isVirtual ? 'opacity-95' : '' ?>">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2 text-sm">
              <span class="font-medium"><?= htmlspecialchars($row['occurred_on']) ?></span>
              <?php if ($isVirtual): ?>
                <span class="text-[11px] text-gray-500">🔒 auto</span>
              <?php endif; ?>
            </div>
            <div class="mt-1 flex items-center gap-2">
              <span class="capitalize text-xs px-2 py-0.5 rounded-full border">
                <?= htmlspecialchars($row['kind']) ?>
              </span>
              <span class="inline-flex items-center gap-2 text-sm">
                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($dot) ?>;"></span>
                <?= htmlspecialchars($row['cat_label'] ?? '—') ?>
              </span>
            </div>
          </div>

          <?php
            $sameCur = strtoupper($nativeCur) === strtoupper($mainCur);
          ?>
          <div class="text-right shrink-0">
            <div class="text-[13px] text-gray-500">Native</div>
            <div class="font-medium"><?= moneyfmt($row['amount'], $nativeCur) ?></div>

            <?php if (!$sameCur): ?>
              <div class="text-[13px] text-gray-500 mt-1"><?= htmlspecialchars($mainCur) ?></div>
              <div class="font-medium"><?= moneyfmt($amtMain, $mainCur) ?></div>
            <?php endif; ?>
          </div>

        </div>

        <?php if (!empty($row['note'])): ?>
          <div class="mt-2 text-xs text-gray-500"><?= htmlspecialchars($row['note']) ?></div>
        <?php endif; ?>

        <div class="mt-3">
          <?php if (!$isVirtual): ?>
            <details class="group">
              <summary class="btn btn-ghost cursor-pointer">Edit</summary>
              <div class="mt-2 bg-gray-50 rounded-xl p-3 border">
                <form class="grid gap-2 sm:grid-cols-6 items-end" method="post" action="/months/tx/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= $y ?>" />
                  <input type="hidden" name="m" value="<?= $m ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />

                  <select name="kind" class="select">
                    <option <?= $row['kind']==='income'?'selected':'' ?> value="income">Income</option>
                    <option <?= $row['kind']==='spending'?'selected':'' ?> value="spending">Spending</option>
                  </select>

                  <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="input" required />

                  <select name="currency" class="select">
                    <?php foreach ($userCurrencies as $c): ?>
                      <option value="<?= htmlspecialchars($c['code']) ?>" <?= ($row['currency']===$c['code'] ? 'selected' : ($c['is_main'] && !$row['currency'] ? 'selected' : '')) ?>>
                        <?= htmlspecialchars($c['code']) ?>
                      </option>
                    <?php endforeach; ?>
                    <?php if (!count($userCurrencies)): ?><option value="HUF">HUF</option><?php endif; ?>
                  </select>

                  <input name="occurred_on" type="date" value="<?= $row['occurred_on'] ?>" class="input" />
                  <input name="note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" class="input" />
                  <button class="btn btn-primary">Save</button>
                </form>

                <form class="mt-2" method="post" action="/months/tx/delete" onsubmit="return confirm('Delete transaction?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= $y ?>" />
                  <input type="hidden" name="m" value="<?= $m ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <button class="btn btn-danger">Remove</button>
                </form>
              </div>
            </details>
          <?php else: ?>
            <span class="text-xs text-gray-400">Auto-generated</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
    // compute if there are more pages for mobile (reuse desktop count)
    $hasMoreMobile = ($totalPages > 1 && $page < $totalPages);
  ?>
  <div class="md:hidden mt-3 flex justify-center">
    <button id="tx-loadmore" class="btn btn-ghost" 
            data-next="<?= $page+1 ?>" 
            data-last="<?= $totalPages ?>"
            data-url="/months/tx/list?<?= http_build_query(array_merge($_GET,['y'=>$y,'m'=>$m])) ?>">
      Load more
    </button>
  </div>

  <script>
  (function(){
    const btn = document.getElementById('tx-loadmore');
    if (!btn) return;
    let next = parseInt(btn.dataset.next || '2',10);
    const last = parseInt(btn.dataset.last || '1',10);
    const baseUrl = btn.dataset.url;

    if (next>last) btn.style.display='none';

    btn.addEventListener('click', async ()=>{
      btn.disabled = true; btn.textContent = 'Loading…';
      const url = baseUrl.replace(/([?&])page=\d+/,'$1page='+next) + (baseUrl.includes('page=') ? '' : '&page='+next);
      const res = await fetch(url, {headers:{'X-Requested-With':'fetch'}});
      const html = await res.text();
      const list = btn.closest('section').querySelector('.md\\:hidden.space-y-3');
      list.insertAdjacentHTML('beforeend', html);
      next++;
      if (next>last){ btn.style.display='none'; }
      else { btn.disabled=false; btn.textContent='Load more'; btn.dataset.next = String(next); }
    });
  })();
  </script>


  <!-- Desktop: classic table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Date</th>
          <th class="py-2 pr-3">Kind</th>
          <th class="py-2 pr-3">Category</th>
          <th class="py-2 pr-3 text-right">Amount (native)</th>
          <th class="py-2 pr-3 text-right">Amount (<?= htmlspecialchars($main) ?>)</th>
          <th class="py-2 pr-3">Note</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allTx as $row): ?>
          <?php
            $isVirtual = !empty($row['is_virtual']);
            $isEF      = isset($row['source']) && $row['source'] === 'ef';
            $isLocked  = !empty($row['locked']) || $isEF; // ← use $row, not $r

            $nativeCur = $row['currency'] ?: $main;

            if ($isVirtual && isset($row['amount_main'])) {
              $amtMain = (float)$row['amount_main'];
              $mainCur = $row['main_currency'] ?? $main;
            } else {
              if (isset($row['amount_main']) && $row['amount_main'] !== null && !empty($row['main_currency'])) {
                $amtMain = (float)$row['amount_main'];
                $mainCur = $row['main_currency'];
              } else {
                $amtMain = fx_convert($pdo, (float)$row['amount'], $nativeCur, $main, $row['occurred_on']);
                $mainCur = $main;
              }
            }
          ?>
          <tr class="border-b hover:bg-gray-50 <?= $isVirtual ? 'opacity-95' : '' ?>">
            <td class="py-2 pr-3">
              <?= htmlspecialchars($row['occurred_on']) ?>
              <?php if ($isVirtual || $isLocked): ?>
                <span class="ml-1 text-[11px] text-gray-500">🔒</span>
              <?php endif; ?>
            </td>

            <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($row['kind']) ?></td>

            <td class="py-2 pr-3">
              <span class="inline-flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full"
                      style="background-color: <?= htmlspecialchars($row['cat_color'] ?? '#6B7280') ?>;"></span>
                <?= htmlspecialchars($row['cat_label'] ?? '—') ?>
              </span>
              <?php if ($isVirtual): ?>
                <span class="text-xs text-gray-500 ml-1">(auto)</span>
              <?php endif; ?>
              <?php if (!$isVirtual && $isEF): ?>
                <span class="text-xs text-emerald-600 ml-1">(Emergency Fund)</span>
              <?php endif; ?>
            </td>

            <td class="py-2 pr-3 font-medium text-right"><?= moneyfmt($row['amount'], $nativeCur) ?></td>
            <td class="py-2 pr-3 text-right"><span class="font-medium"><?= moneyfmt($amtMain, $mainCur) ?></span></td>
            <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($row['note'] ?? '') ?></td>

            <td class="py-2 pr-3">
              <?php if (!$isVirtual && !$isLocked): ?>
                <button type="button" class="btn btn-ghost" onclick="openTxModal('tx<?= (int)$row['id'] ?>')">Edit</button>
                <form class="inline" method="post" action="/months/tx/delete" onsubmit="return confirm('Delete transaction?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= (int)$y ?>" />
                  <input type="hidden" name="m" value="<?= (int)$m ?>" />
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                  <button class="btn btn-danger">Remove</button>
                </form>

                <!-- Modal -->
                <dialog id="tx<?= $row['id'] ?>" class="rounded-2xl p-0 w-[720px] max-w-[95vw] shadow-2xl">
                  <form method="dialog" class="m-0">
                    <div class="flex items-center justify-between px-5 py-3 border-b">
                      <div class="font-semibold">Edit Transaction — <?= htmlspecialchars($row['occurred_on']) ?></div>
                      <button class="btn btn-ghost" value="close">Close</button>
                    </div>
                  </form>

                  <div class="p-5">
                    <form class="grid gap-3 md:grid-cols-12 items-end" method="post" action="/months/tx/edit">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="y" value="<?= $y ?>" />
                      <input type="hidden" name="m" value="<?= $m ?>" />
                      <input type="hidden" name="id" value="<?= $row['id'] ?>" />

                      <div class="field md:col-span-3">
                        <label class="label">Type</label>
                        <select name="kind" class="select">
                          <option <?= $row['kind']==='income'?'selected':'' ?> value="income">Income</option>
                          <option <?= $row['kind']==='spending'?'selected':'' ?> value="spending">Spending</option>
                        </select>
                      </div>

                      <div class="field md:col-span-3">
                        <label class="label">Amount</label>
                        <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="input" required />
                      </div>

                      <div class="field md:col-span-2">
                        <label class="label">Currency</label>
                        <select name="currency" class="select">
                          <?php foreach ($userCurrencies as $c): ?>
                            <option value="<?= htmlspecialchars($c['code']) ?>"
                              <?= ($row['currency']===$c['code'] ? 'selected' : ($c['is_main'] && !$row['currency'] ? 'selected' : '')) ?>>
                              <?= htmlspecialchars($c['code']) ?>
                            </option>
                          <?php endforeach; ?>
                          <?php if (!count($userCurrencies)): ?><option value="HUF">HUF</option><?php endif; ?>
                        </select>
                      </div>

                      <div class="field md:col-span-4">
                        <label class="label">Date</label>
                        <input name="occurred_on" type="date" value="<?= $row['occurred_on'] ?>" class="input" />
                      </div>

                      <div class="field md:col-span-12">
                        <label class="label">Note</label>
                        <input name="note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" class="input" />
                      </div>

                      <div class="md:col-span-12 flex justify-end gap-2">
                        <button class="btn btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </dialog>
              <?php else: ?>
                <span class="text-xs text-gray-400">
                  <?= $isVirtual ? 'Auto-generated' : ($isEF ? 'Locked (Emergency Fund)' : 'Locked') ?>
                </span>
              <?php endif; ?>
            </td>
          </tr>

        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($totalPages > 1): 
      $qs = $_GET; unset($qs['page']); $base = '/years/'.$y.'/'.$m;
      $mk = function($p) use ($base,$qs){ $qs['page']=$p; return $base.'?'.http_build_query($qs); };
    ?>
      <div class="hidden md:flex items-center justify-between mt-3 text-sm">
        <div class="text-gray-500">Page <?= $page ?> / <?= $totalPages ?></div>
        <div class="flex gap-2">
          <a class="btn btn-ghost <?= $page<=1?'pointer-events-none opacity-40':'' ?>" href="<?= $page>1?$mk($page-1):'#' ?>">Prev</a>
          <a class="btn btn-ghost <?= $page>=$totalPages?'pointer-events-none opacity-40':'' ?>" href="<?= $page<$totalPages?$mk($page+1):'#' ?>">Next</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
  function openTxModal(id){
    const dlg = document.getElementById(id);
    if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
    else if (dlg) dlg.setAttribute('open','');
  }
  // Close on backdrop click
  document.addEventListener('click', (e)=>{
    const dlg = e.target.closest('dialog[open]');
    if (dlg && e.target === dlg) dlg.close();
  });
</script>



