<?php
  // current request path and helpers from your page…
  $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  $qs_clean = function(array $overrides = []) {
    $q = $_GET ?? [];
    foreach ($q as $k=>$v){ if ($v==='' || $v===null) unset($q[$k]); }
    unset($q['page']);
    foreach ($overrides as $k=>$v){ if ($v===null) unset($q[$k]); else $q[$k]=$v; }
    $str = http_build_query($q);
    return $str ? ('?'.$str) : '';
  };
  // $ymPrev, $ymNext, $ymThis, $y, $m already computed in controller
  $ymLabel = format_month_year(sprintf('%04d-%02d-01', $y, $m));
?>
<section class="mb-4">
  <div class="rounded-3xl border border-gray-200 bg-white/80 p-3 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/60">
    <div class="flex items-center justify-between gap-3 sm:justify-start">

      <!-- Prev -->
      <a href="<?= htmlspecialchars($currentPath . $qs_clean(['ym' => $ymPrev])) ?>"
         class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-200 bg-white/80 transition hover:bg-brand-50/70 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:bg-slate-800"
         aria-label="<?= __('Previous month') ?>">
        <i data-lucide="chevron-left" class="h-5 w-5 text-gray-700 dark:text-slate-200"></i>
      </a>

      <!-- Month pill (click anywhere to open) -->
      <div class="relative">
        <input id="month-input"
               type="month"
               name="ym"
               value="<?= htmlspecialchars(sprintf('%04d-%02d', $y, $m)) ?>"
               aria-label="<?= __('Select month') ?>"
               class="peer absolute inset-0 z-10 h-full w-full cursor-pointer appearance-none opacity-0"
        />
        <div class="inline-flex items-center gap-3 rounded-2xl border border-gray-200 bg-white/90 px-5 py-3 text-left shadow-sm transition pointer-events-none peer-hover:bg-brand-50/70 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-white dark:border-slate-700 dark:bg-slate-900/60 dark:peer-hover:bg-slate-800 dark:peer-focus-visible:ring-offset-slate-900">
          <i data-lucide="calendar" class="h-5 w-5 text-gray-700 dark:text-slate-200"></i>
          <span class="leading-tight font-semibold text-gray-900 dark:text-white">
            <?= htmlspecialchars($ymLabel) ?>
          </span>
          <i data-lucide="chevron-down" class="h-4 w-4 text-gray-500 transition peer-hover:translate-y-[1px] peer-focus-visible:translate-y-[1px] dark:text-slate-400"></i>
        </div>
      </div>

      <!-- Next -->
      <a href="<?= htmlspecialchars($currentPath . $qs_clean(['ym' => $ymNext])) ?>"
         class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-200 bg-white/80 transition hover:bg-brand-50/70 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:bg-slate-800"
         aria-label="<?= __('Next month') ?>">
        <i data-lucide="chevron-right" class="h-5 w-5 text-gray-700 dark:text-slate-200"></i>
      </a>

      <!-- “This month” — icon on mobile, pill on ≥sm -->
      <div class="flex items-center gap-2">
        <!-- mobile: icon -->
        <a href="<?= htmlspecialchars($currentPath . $qs_clean(['ym' => $ymThis])) ?>"
           class="sm:hidden inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-200 bg-white/80 transition hover:bg-brand-50/70 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:bg-slate-800"
           aria-label="<?= __('This month') ?>">
          <i data-lucide="calendar-clock" class="h-5 w-5 text-gray-700 dark:text-slate-200"></i>
        </a>

        <!-- desktop: pill -->
        <a href="<?= htmlspecialchars($currentPath . $qs_clean(['ym' => $ymThis])) ?>"
           class="hidden sm:inline-flex items-center gap-2 rounded-2xl bg-gray-900 px-4 py-2.5 text-white shadow-sm transition hover:opacity-90 dark:bg-brand-500 dark:hover:bg-brand-400">
          <i data-lucide="calendar-clock" class="h-4 w-4"></i>
          <?= __('This month') ?>
        </a>
      </div>
    </div>

    <div class="px-1 pt-3 text-sm text-gray-500 dark:text-slate-400">
      <?= __('Viewing') ?> <span class="font-medium text-gray-700 dark:text-slate-200"><?= htmlspecialchars($ymLabel) ?></span>
    </div>
  </div>
</section>

<script>
  // Make the whole pill open the native month picker
  (function(){
    const input = document.getElementById('month-input');

    if (!input) return;

    const openPicker = () => {
      if (typeof input.showPicker === 'function') {
        try {
          input.showPicker();
          return;
        } catch (err) {
          // ignore showPicker errors and fall back
        }
      }
      input.focus();
    };

    input.addEventListener('click', openPicker);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openPicker();
      }
    });

    // When month changes, reload with ?ym=YYYY-MM while preserving other filters
    input.addEventListener('change', () => {
      const ym = input.value; // 'YYYY-MM'
      if (!ym) return;
      const url = new URL(window.location.href);
      url.searchParams.set('ym', ym);
      url.searchParams.delete('page'); // reset pagination
      window.location.href = url.pathname + '?' + url.searchParams.toString();
    });

    // (Re)render Lucide icons (after server render / HTMX / fetch etc.)
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    } else {
      document.addEventListener('DOMContentLoaded', () => {
        window.lucide && window.lucide.createIcons && window.lucide.createIcons();
      });
    }
  })();
</script>


<?php $ym = sprintf('%04d-%02d', $y, $m); ?>
<section class="grid md:grid-cols-3 gap-4">
  <!-- Summary -->
  <div class="card">
    <h2 class="text-lg font-semibold mb-4"><?= __('Monthly Summary') ?></h2>

    <!-- Net focus -->
    <?php $net = $sumIn_main - $sumOut_main; ?>
    <div class="text-center mb-6">
      <div class="text-sm text-gray-500"><?= __('Net') ?> (<?= htmlspecialchars($main) ?>)</div>
      <div class="text-3xl font-bold <?= $net>=0 ? 'text-brand-600' : 'text-red-600' ?>">
        <?= moneyfmt($net, $main) ?>
      </div>
    </div>

    <!-- Income vs Spending -->
    <div class="grid grid-cols-2 gap-4 text-sm">
      <div class="p-3 rounded-xl bg-brand-50/80 text-brand-600 text-center">
        <div class="font-medium"><?= __('Income') ?></div>
        <div class="text-md font-semibold"><?= moneyfmt($sumIn_main, $main) ?></div>
      </div>
      <div class="p-3 rounded-xl bg-red-50 text-red-700 text-center">
        <div class="font-medium"><?= __('Spending') ?></div>
        <div class="text-md font-semibold"><?= moneyfmt($sumOut_main, $main) ?></div>
      </div>
    </div>

    <!-- Native breakdown -->
    <div class="mt-6 text-xs text-gray-500 space-y-1">
      <div>
        <span class="font-medium text-gray-600"><?= __('Native income:') ?></span>
        <?php if (!empty($sumIn_native_by_cur)): ?>
          <?php foreach ($sumIn_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a, $c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
      <div>
        <span class="font-medium text-gray-600"><?= __('Native spending:') ?></span>
        <?php if (!empty($sumOut_native_by_cur)): ?>
          <?php foreach ($sumOut_native_by_cur as $c=>$a): ?>
            <span class="inline-block mr-2"><?= moneyfmt($a, $c) ?></span>
          <?php endforeach; ?>
        <?php else: ?>0.00<?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Cashflow Guidance -->
  <section class="card md:col-span-2">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold"><?= __('Cashflow Guidance') ?></h3>
      <div class="text-xs text-gray-500"><?= __('Budgets are based on your cashflow rules & this month’s income.') ?></div>
    </div>

    <?php if (empty($ruleGuides)): ?>
      <p class="text-sm text-gray-500 mt-2">
        <?= __('No cashflow rules yet. Set them up in <a class="text-accent" href="/settings/cashflow">Settings → Cashflow</a> to get category-by-category guidance.') ?>
      </p>
    <?php else: ?>
      <div class="mt-4 grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($ruleGuides as $rid => $rg): 
          $pctSpent = ($rg['budget'] > 0) ? min(100, round($rg['spent'] / $rg['budget'] * 100)) : 0;
        ?>
          <div class="rounded-xl border p-3">
            <div class="flex items-center justify-between">
              <div class="font-medium"><?= htmlspecialchars($rg['label']) ?></div>
              <span class="chip"><?= (float)$rg['percent'] ?>%</span>
            </div>
            <div class="mt-2 text-xs text-gray-600">
              <?= __('Budget:') ?> <span class="font-medium"><?= moneyfmt($rg['budget'], $main) ?></span>
              · <?= __('Spent:') ?> <span class="font-medium"><?= moneyfmt($rg['spent'], $main) ?></span>
            </div>

            <div class="mt-2 h-2 rounded-full bg-brand-100/60">
              <div class="h-2 rounded-full" style="width: <?= $pctSpent ?>%; background:#111827"></div>
            </div>

            <div class="mt-2 text-sm">
              <?php if ($rg['spent'] <= $rg['budget']): ?>
                <span class="text-brand-600"><?= __('Remaining:') ?></span>
                <span class="font-medium"><?= moneyfmt($rg['remaining'], $main) ?></span>
              <?php else: ?>
                <?php $over = $rg['spent'] - $rg['budget']; ?>
                <span class="text-red-700"><?= __('Over by:') ?></span>
                <span class="font-medium"><?= moneyfmt($over, $main) ?></span>
              <?php endif; ?>
            </div>

            <?php
              // show quick per-category breakdown (equal caps) under each rule
              $catsInRule = array_filter($cats, fn($c) => (int)($c['cashflow_rule_id'] ?? 0) === (int)$rid);
            ?>
            <?php if (!empty($catsInRule)): ?>
              <div class="mt-3 space-y-2">
                <?php foreach ($catsInRule as $c): 
                  $cg = $catGuides[(int)$c['id']] ?? null;
                  if (!$cg) continue;
                  $rem = $cg['remaining'];
                  $over = max(0, $cg['spent'] - $cg['cap']);
                ?>
                  <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: <?= htmlspecialchars($c['color']) ?>"></span>
                      <span class="truncate"><?= htmlspecialchars($c['label']) ?></span>
                    </div>
                    <?php if ($over <= 0): ?>
                      <span class="text-brand-600"><?= __('left') ?> <?= moneyfmt($rem, $main) ?></span>
                    <?php else: ?>
                      <span class="text-red-700"><?= __('over') ?> <?= moneyfmt($over, $main) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</section>

<!-- Charts -->
<section class="mt-6 grid md:grid-cols-2 gap-6">
  <!-- A) Cumulative Net Cashflow (area line) -->
  <div class="card h-80">
    <h3 class="font-semibold mb-3"><?= __('Cumulative Net (:currency)', ['currency' => htmlspecialchars($main)]) ?></h3>
    <?php
      // Prepare day list for the month
      $cursor = new DateTime($first);
      $end    = new DateTime($last);
      $days   = [];
      while ($cursor <= $end) { $days[] = $cursor->format('Y-m-d'); $cursor->modify('+1 day'); }

      // Per-day income & spending in MAIN
      $income = array_fill_keys($days, 0.0);
      $spend  = array_fill_keys($days, 0.0);

      foreach (($allTx ?? []) as $r) {
        $d = substr($r['occurred_on'], 0, 10);
        if (!isset($income[$d])) continue;

        // amount -> main
        if (isset($r['amount_main']) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
          $amtMain = (float)$r['amount_main'];
        } else {
          $from = $r['currency'] ?: $main;
          $amtMain = fx_convert($pdo, (float)$r['amount'], $from, $main, $r['occurred_on']);
        }
        if (($r['kind'] ?? '') === 'income') $income[$d] += max(0, $amtMain);
        if (($r['kind'] ?? '') === 'spending') $spend[$d]  += max(0, $amtMain);
      }

      // Build cumulative net
      $labels = array_keys($income);
      $cum = []; $running = 0.0;
      foreach ($labels as $d) {
        $running += ($income[$d] - $spend[$d]);
        $cum[] = round($running, 2);
      }

      // Also show same-day bars for income/spend (nice context)
      $inVals  = array_values(array_map(fn($v)=>round($v,2), $income));
      $spVals  = array_values(array_map(fn($v)=>round($v,2), $spend));
      $cumVals = $cum;
    ?>
    <div class="relative h-56 md:h-64">
      <canvas id="cum-net" class="absolute inset-0 w-full h-full"></canvas>
    </div>
    <script>
    (function(){
      const el = document.getElementById('cum-net');
      if (!el) return;

      const labels = <?= json_encode(array_map(fn($d)=>substr($d,5), $labels)) ?>; // show as MM-DD
      const income = <?= json_encode($inVals) ?>;
      const spend  = <?= json_encode($spVals) ?>;
      const cum    = <?= json_encode($cumVals) ?>;

      const allZero = arr => arr.every(v => Math.abs(v) < 1e-9);
      if (!labels.length || (allZero(income) && allZero(spend))) {
        const emptyHtml = <?= json_encode('<div class="text-sm text-gray-500">'.__('No activity this month.').'</div>') ?>;
        el.parentElement.innerHTML = emptyHtml;
        return;
      }

      function draw(){
        window.updateChartGlobals && window.updateChartGlobals();

        const ctx = el.getContext('2d');
        const palette = window.getChartPalette ? window.getChartPalette() : {};

        const makeGradient = (chart) => {
          const area = chart && chart.chartArea;
          const top = area ? area.top : 0;
          const bottom = area ? area.bottom : (chart ? chart.canvas.height : el.height);
          const gctx = (chart ? chart.ctx : ctx);
          const gradient = gctx.createLinearGradient(0, top, 0, bottom);
          gradient.addColorStop(0, palette.netFillTop || 'rgba(75,150,110,0.28)');
          gradient.addColorStop(1, palette.netFillBottom || 'rgba(75,150,110,0.04)');
          return gradient;
        };

        const chart = new Chart(ctx, {
          data: {
            labels,
            datasets: [
              {
                type:'bar',
                label:<?= json_encode(__('Income')) ?>,
                data:income,
                borderWidth:0,
                borderRadius:8,
                borderSkipped:false,
                backgroundColor: palette.incomeBar || 'rgba(74,171,125,0.8)'
              },
              {
                type:'bar',
                label:<?= json_encode(__('Spending')) ?>,
                data:spend,
                borderWidth:0,
                borderRadius:8,
                borderSkipped:false,
                backgroundColor: palette.spendBar || 'rgba(236,104,134,0.7)'
              },
              {
                type:'line',
                label:<?= json_encode(__('Cumulative Net')) ?>,
                data:cum,
                borderWidth:2,
                tension:0.25,
                pointRadius:0,
                fill:true,
                backgroundColor: makeGradient()
              }
            ]
          },
          options: {
            responsive:true,
            maintainAspectRatio:false,
            interaction:{ mode:'index', intersect:false },
            plugins:{
              legend:{
                position:'bottom'
              },
              tooltip:{
                backgroundColor: palette.tooltipBg || 'rgba(255,255,255,0.96)',
                borderColor: palette.tooltipBorder || 'rgba(75,150,110,0.35)',
                borderWidth:1,
                titleColor: palette.tooltipText || '#233d30',
                bodyColor: palette.tooltipText || '#233d30',
                callbacks:{
                  label:(ctx)=>{
                    const v = Number(ctx.parsed.y).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
                    return `${ctx.dataset.label}: ${v} <?= $main ?>`;
                  }
                }
              }
            },
            scales:{
              x:{
                grid:{ display:false },
                ticks:{ maxTicksLimit:10, color: palette.axis || '#2f443a' }
              },
              y:{
                grid:{ color: palette.grid || 'rgba(17,36,29,0.08)' },
                ticks:{ color: palette.axis || '#2f443a' }
              }
            }
          }
        });

        const applyTheme = (instance) => {
          if (!window.getChartPalette) return;
          const pal = window.getChartPalette();
          const grad = (() => {
            const area = instance.chartArea;
            const top = area ? area.top : 0;
            const bottom = area ? area.bottom : instance.canvas.height;
            const gradient = instance.ctx.createLinearGradient(0, top, 0, bottom);
            gradient.addColorStop(0, pal.netFillTop);
            gradient.addColorStop(1, pal.netFillBottom);
            return gradient;
          })();
          const [incomeDs, spendDs, netDs] = instance.data.datasets;
          incomeDs.backgroundColor = pal.incomeBar;
          spendDs.backgroundColor = pal.spendBar;
          netDs.borderColor = pal.netLine;
          netDs.backgroundColor = grad;
          instance.options.plugins.legend.labels = instance.options.plugins.legend.labels || {};
          instance.options.plugins.legend.labels.color = pal.axis;
          instance.options.plugins.tooltip.backgroundColor = pal.tooltipBg;
          instance.options.plugins.tooltip.borderColor = pal.tooltipBorder;
          instance.options.plugins.tooltip.titleColor = pal.tooltipText;
          instance.options.plugins.tooltip.bodyColor = pal.tooltipText;
          instance.options.scales.x.ticks.color = pal.axis;
          instance.options.scales.y.ticks.color = pal.axis;
          instance.options.scales.y.grid.color = pal.grid;
        };

        applyTheme(chart);
        chart.update('none');
        const themeKey = 'chart:cum-net';
        if (window.registerChartTheme) {
          window.registerChartTheme(themeKey, () => {
            if (!chart) return false;
            applyTheme(chart);
            chart.update('none');
            return chart;
          });
        }
      }

      if (typeof Chart === 'undefined') {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        s.onload = draw; document.head.appendChild(s);
      } else { draw(); }
    })();
    </script>
  </div>

  <!-- B) Top Spending Categories (horizontal bars) -->
  <div class="card h-80 overflow-hidden">
    <h3 class="font-semibold mb-3"><?= __('Top Spending Categories (:currency)', ['currency' => htmlspecialchars($main)]) ?></h3>
    <?php
      // Build grouped sums from all transactions in MAIN (spending only)
      $grp = []; $cols = [];
      foreach (($allTx ?? []) as $r) {
        if (($r['kind'] ?? '') !== 'spending') continue;

        if (isset($r['amount_main']) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
          $amtMain = (float)$r['amount_main'];
        } else {
          $amtMain = fx_convert($pdo, (float)$r['amount'], ($r['currency'] ?: $main), $main, $r['occurred_on']);
        }
        if ($amtMain <= 0) continue;

        $label = $r['cat_label'] ?? __('Uncategorized');
        $color = $r['cat_color'] ?? '#6B7280';
        $grp[$label] = ($grp[$label] ?? 0) + $amtMain;
        if (!isset($cols[$label])) $cols[$label] = $color;
      }
      arsort($grp);
      // Limit to top 10 for clarity; sum the rest as "Other"
      $labelsAll = array_keys($grp);
      $dataAll   = array_values($grp);
      $colorsAll = array_map(fn($k)=>$cols[$k] ?? '#6B7280', $labelsAll);

      $topN = 10;
      if (count($labelsAll) > $topN) {
        $labels = array_slice($labelsAll, 0, $topN);
        $data   = array_slice($dataAll,   0, $topN);
        $colors = array_slice($colorsAll, 0, $topN);

        $other = array_sum(array_slice($dataAll, $topN));
        if ($other > 0) {
          $labels[] = __('Other');
          $data[]   = $other;
          $colors[] = '#D1D5DB';
        }
      } else {
        $labels = $labelsAll; $data = $dataAll; $colors = $colorsAll;
      }
    ?>
    <div class="relative h-60 w-full">
      <canvas id="spendcat-top" class="absolute inset-0"></canvas>
    </div>
    <script>
      (function(){
        const el = document.getElementById('spendcat-top');
        if (!el) return;

        const labels = <?= json_encode($labels) ?>;
        const data   = <?= json_encode(array_map(fn($v)=>round($v,2), $data)) ?>;
        const colors = <?= json_encode($colors) ?>;

        if (!labels.length) {
          const emptyHtml = <?= json_encode('<div class="text-sm text-gray-500">'.__('No spending this month.').'</div>') ?>;
          el.outerHTML = emptyHtml;
          return;
        }

        function draw(){
          window.updateChartGlobals && window.updateChartGlobals();

          const palette = window.getChartPalette ? window.getChartPalette() : {};
          const chart = new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
              labels,
              datasets: [{ data, backgroundColor: colors, borderWidth:0, borderRadius:8, borderSkipped:false }]
            },
            options: {
              indexAxis: 'y',
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { display:false },
                tooltip: {
                  backgroundColor: palette.tooltipBg || 'rgba(255,255,255,0.96)',
                  borderColor: palette.tooltipBorder || 'rgba(75,150,110,0.35)',
                  borderWidth:1,
                  titleColor: palette.tooltipText || '#233d30',
                  bodyColor: palette.tooltipText || '#233d30',
                  callbacks: {
                    label: (ctx) => `${Number(ctx.parsed.x).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})} <?= $main ?>`
                  }
                }
              },
              scales: {
                x: { grid:{ color: palette.grid || 'rgba(17,36,29,0.08)' }, ticks:{ color: palette.axis || '#2f443a' } },
                y: { grid:{ display:false }, ticks:{ color: palette.axis || '#2f443a' } }
              }
            }
          });

          const applyTheme = (instance) => {
            if (!window.getChartPalette) return;
            const pal = window.getChartPalette();
            instance.options.plugins.tooltip.backgroundColor = pal.tooltipBg;
            instance.options.plugins.tooltip.borderColor = pal.tooltipBorder;
            instance.options.plugins.tooltip.titleColor = pal.tooltipText;
            instance.options.plugins.tooltip.bodyColor = pal.tooltipText;
            instance.options.scales.x.grid.color = pal.grid;
            instance.options.scales.x.ticks.color = pal.axis;
            instance.options.scales.y.ticks.color = pal.axis;
          };

          applyTheme(chart);
          chart.update('none');
          const themeKey = 'chart:spendcat-top';
          if (window.registerChartTheme) {
            window.registerChartTheme(themeKey, () => {
              if (!chart) return false;
              applyTheme(chart);
              chart.update('none');
              return chart;
            });
          }
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

<!-- Add transaction -->
<section id="quick-add" class="mt-6 grid gap-6 md:grid-cols-2">
  <div class="md:hidden space-y-3">
    <button
      type="button"
      class="btn btn-primary w-full"
      onclick="openTxModal('tx-add')"
    >
      <?= __('Add transaction') ?>
    </button>
    <p class="text-xs text-gray-500 text-center">
      <?= __('Quick Add lets you choose kind, amount, currency, and category fast.') ?>
    </p>
  </div>

  <div class="card hidden md:block md:col-span-2">
    <h3 class="text-base font-semibold mb-3"><?= __('Quick Add') ?></h3>
    <?php
      $quickAddConfig = [
        'form_classes' => 'grid gap-4 md:grid-cols-12 md:items-end',
        'amount_id' => 'quick-add-amount',
        'button_wrapper_classes' => 'md:col-span-4 flex md:justify-end',
        'button_classes' => 'btn btn-primary w-full md:w-auto',
        'button_label' => __('Add'),
        'data_restore_focus' => '#quick-add-amount',
      ];
      include __DIR__ . '/_quick_add_form.php';
    ?>
  </div>

  <dialog id="tx-add" class="rounded-2xl p-0 w-[720px] max-w-[95vw] shadow-2xl">
    <div class="modal-header px-5 py-4">
      <div class="font-semibold"><?= __('Add transaction') ?></div>
      <button type="button" class="icon-btn" onclick="closeTxModal('tx-add')" aria-label="<?= __('Close') ?>">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="modal-body px-5 py-4">
      <?php
        $quickAddConfig = [
          'form_classes' => 'grid gap-3',
          'amount_id' => 'quick-add-modal-amount',
          'button_wrapper_classes' => 'flex justify-end',
          'button_classes' => 'btn btn-primary',
          'button_label' => __('Add'),
          'data_restore_focus' => '#quick-add-modal-amount',
          'render_button' => false,
          'form_id' => 'quick-add-modal-form',
        ];
        include __DIR__ . '/_quick_add_form.php';
      ?>
    </div>

    <div class="modal-footer px-5 py-4">
      <div class="flex flex-row flex-wrap gap-2 justify-end">
        <button type="button" class="btn" onclick="closeTxModal('tx-add')"><?= __('Cancel') ?></button>
        <button class="btn btn-primary" form="quick-add-modal-form"><?= __('Add') ?></button>
      </div>
    </div>
  </dialog>
</section>

<!-- Transactions -->
<section class="mt-6 card">
  <h3 class="font-semibold mb-3"><?= __('Transactions') ?></h3>

  <!-- Mobile: stacked cards -->
  <div class="md:hidden space-y-3">
    <?php foreach ($txDisplay as $row): ?>
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
        $kindLabel = $row['kind'];
        if ($row['kind'] === 'income') {
          $kindLabel = __('Income');
        } elseif ($row['kind'] === 'spending') {
          $kindLabel = __('Spending');
        } else {
          $kindLabel = __($row['kind']);
        }
      ?>
      <div class="rounded-xl border p-3 <?= $isVirtual ? 'opacity-95' : '' ?>">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2 text-sm">
              <span class="font-medium"><?= htmlspecialchars($row['occurred_on']) ?></span>
              <?php if ($isVirtual): ?>
                <span class="text-[11px] text-gray-500">🔒 <?= __('Auto-generated') ?></span>
              <?php endif; ?>
            </div>
            <div class="mt-1 flex items-center gap-2">
              <span class="capitalize text-xs px-2 py-0.5 rounded-full border">
                <?= htmlspecialchars($kindLabel) ?>
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
            <div class="text-[13px] text-gray-500"><?= __('Native') ?></div>
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
              <summary class="inline-flex cursor-pointer items-center">
                <span class="icon-action icon-action--primary" aria-hidden="true">
                  <i data-lucide="pencil" class="h-4 w-4"></i>
                </span>
                <span class="sr-only"><?= __('Edit') ?></span>
              </summary>
              <div class="mt-2 bg-gray-50 rounded-xl p-3 border">
                <form class="grid gap-2 sm:grid-cols-6 items-end" method="post" action="/months/tx/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= $y ?>" />
                  <input type="hidden" name="m" value="<?= $m ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />

                  <select name="kind" class="select">
                    <option <?= $row['kind']==='income'?'selected':'' ?> value="income"><?= __('Income') ?></option>
                    <option <?= $row['kind']==='spending'?'selected':'' ?> value="spending"><?= __('Spending') ?></option>
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
                  <button class="btn btn-primary"><?= __('Save') ?></button>
                </form>

                <form class="mt-2" method="post" action="/months/tx/delete" onsubmit="return confirm('<?= addslashes(__('Delete transaction?')) ?>')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= $y ?>" />
                  <input type="hidden" name="m" value="<?= $m ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                    <span class="sr-only"><?= __('Remove') ?></span>
                  </button>
                </form>
              </div>
            </details>
          <?php else: ?>
            <span class="text-xs text-gray-400"><?= __('Auto-generated') ?></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
    $loadQs = array_merge($_GET ?? [], ['ym' => sprintf('%04d-%02d', $y, $m)]);
    // your list endpoint path stays the same if that’s your route:
    $listUrl = '/months/tx/list?' . http_build_query($loadQs);
  ?>
  <div class="md:hidden mt-3 flex justify-center">
    <button id="tx-loadmore" class="btn btn-ghost"
            data-next="<?= (int)$page + 1 ?>"
            data-last="<?= (int)$totalPages ?>"
            data-url="<?= htmlspecialchars($listUrl) ?>">
      <?= __('Load more') ?>
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
      const loadingLabel = '<?= addslashes(__('Loading…')) ?>';
      const moreLabel = '<?= addslashes(__('Load more')) ?>';
      btn.disabled = true; btn.textContent = loadingLabel;
      const url = baseUrl.replace(/([?&])page=\d+/,'$1page='+next) + (baseUrl.includes('page=') ? '' : '&page='+next);
      const res = await fetch(url, {headers:{'X-Requested-With':'fetch'}});
      const html = await res.text();
      const list = btn.closest('section').querySelector('.md\\:hidden.space-y-3');
      list.insertAdjacentHTML('beforeend', html);
      next++;
      if (next>last){ btn.style.display='none'; }
      else { btn.disabled=false; btn.textContent=moreLabel; btn.dataset.next = String(next); }
    });
  })();
  </script>


  <!-- Desktop: classic table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="table-glass min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3"><?= __('Date') ?></th>
          <th class="py-2 pr-3"><?= __('Kind') ?></th>
          <th class="py-2 pr-3"><?= __('Category') ?></th>
          <th class="py-2 pr-3 text-right"><?= __('Amount (native)') ?></th>
          <th class="py-2 pr-3 text-right"><?= __('Amount (:currency)', ['currency' => htmlspecialchars($main)]) ?></th>
          <th class="py-2 pr-3"><?= __('Note') ?></th>
          <th class="py-2 pr-3"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($txDisplay as $row): ?>
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

            <?php
              $kindLabel = $row['kind'];
              if ($row['kind'] === 'income') {
                $kindLabel = __('Income');
              } elseif ($row['kind'] === 'spending') {
                $kindLabel = __('Spending');
              } else {
                $kindLabel = __($row['kind']);
              }
            ?>
            <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($kindLabel) ?></td>

            <td class="py-2 pr-3">
              <span class="inline-flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full"
                      style="background-color: <?= htmlspecialchars($row['cat_color'] ?? '#6B7280') ?>;"></span>
                <?= htmlspecialchars($row['cat_label'] ?? '—') ?>
              </span>
              <?php if ($isVirtual): ?>
                <span class="text-xs text-gray-500 ml-1"><?= __('(auto)') ?></span>
              <?php endif; ?>
              <?php if (!$isVirtual && $isEF): ?>
                <span class="text-xs text-brand-600 ml-1"><?= __('(Emergency Fund)') ?></span>
              <?php endif; ?>
            </td>

            <td class="py-2 pr-3 font-medium text-right"><?= moneyfmt($row['amount'], $nativeCur) ?></td>
            <td class="py-2 pr-3 text-right"><span class="font-medium"><?= moneyfmt($amtMain, $mainCur) ?></span></td>
            <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($row['note'] ?? '') ?></td>

            <td class="py-2 pr-3">
              <?php if (!$isVirtual && !$isLocked): ?>
                <button type="button" class="icon-action icon-action--primary" onclick="openTxModal('tx<?= (int)$row['id'] ?>')" title="<?= __('Edit') ?>">
                  <i data-lucide="pencil" class="h-4 w-4"></i>
                  <span class="sr-only"><?= __('Edit') ?></span>
                </button>

                <form class="inline" method="post" action="/months/tx/delete"
                      onsubmit="return confirm('<?= addslashes(__('Delete transaction?')) ?>')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="y" value="<?= (int)$y ?>" />
                  <input type="hidden" name="m" value="<?= (int)$m ?>" />
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                  <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                    <span class="sr-only"><?= __('Remove') ?></span>
                  </button>
                </form>

                <!-- Modal -->
                <dialog id="tx<?= $row['id'] ?>" class="rounded-2xl p-0 w-[720px] max-w-[95vw] shadow-2xl">
                  <div class="modal-header px-5 py-4">
                    <div class="font-semibold"><?= __('Edit Transaction — :date', ['date' => htmlspecialchars($row['occurred_on'])]) ?></div>
                    <button type="button" class="icon-btn" onclick="closeTxModal('tx<?= (int)$row['id'] ?>')" aria-label="<?= __('Close') ?>">
                      <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                  </div>

                  <div class="modal-body px-5 py-4">
                    <form id="tx-form-<?= (int)$row['id'] ?>" class="grid gap-3 md:grid-cols-12 items-end" method="post" action="/months/tx/edit">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                      <input type="hidden" name="y" value="<?= $y ?>" />
                      <input type="hidden" name="m" value="<?= $m ?>" />
                      <input type="hidden" name="id" value="<?= $row['id'] ?>" />

                      <div class="field md:col-span-3">
                        <label class="label"><?= __('Type') ?></label>
                        <select name="kind" class="select">
                          <option <?= $row['kind']==='income'?'selected':'' ?> value="income"><?= __('Income') ?></option>
                          <option <?= $row['kind']==='spending'?'selected':'' ?> value="spending"><?= __('Spending') ?></option>
                        </select>
                      </div>

                      <div class="field md:col-span-3">
                        <label class="label"><?= __('Amount') ?></label>
                        <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="input" required />
                      </div>

                      <div class="field md:col-span-2">
                        <label class="label"><?= __('Currency') ?></label>
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
                        <label class="label"><?= __('Date') ?></label>
                        <input name="occurred_on" type="date" value="<?= $row['occurred_on'] ?>" class="input" />
                      </div>

                      <div class="field md:col-span-12">
                        <label class="label"><?= __('Note') ?></label>
                        <input name="note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" class="input" />
                      </div>
                    </form>
                  </div>

                  <div class="modal-footer px-5 py-4">
                    <div class="flex flex-row flex-wrap gap-2 justify-end">
                      <button type="button" class="btn" onclick="closeTxModal('tx<?= (int)$row['id'] ?>')"><?= __('Cancel') ?></button>
                      <button class="btn btn-primary" form="tx-form-<?= (int)$row['id'] ?>"><?= __('Save') ?></button>
                    </div>
                  </div>
                </dialog>
              <?php else: ?>
                <span class="text-xs text-gray-400">
                  <?= $isVirtual ? __('Auto-generated') : ($isEF ? __('Locked (Emergency Fund)') : __('Locked')) ?>
                </span>
              <?php endif; ?>
            </td>
          </tr>

        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
      // use the same $currentPath you already compute above
      $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

      if ($totalPages > 1):
        $qs = $_GET ?? [];
        // Always carry the selected month
        $qs['ym'] = sprintf('%04d-%02d', $y, $m);
        unset($qs['page']); // reset page when building links

        $mk = function($p) use ($currentPath, $qs){
          $qs['page'] = $p;
          return $currentPath . '?' . http_build_query($qs);
        };
    ?>
      <div class="hidden md:flex items-center justify-between mt-3 text-sm">
        <div class="text-gray-500"><?= __('Page :current / :total', ['current' => $page, 'total' => $totalPages]) ?></div>
        <div class="flex gap-2">
          <a class="btn btn-ghost <?= $page<=1?'pointer-events-none opacity-40':'' ?>"
            href="<?= $page>1 ? htmlspecialchars($mk($page-1)) : '#' ?>"><?= __('Prev') ?></a>
          <a class="btn btn-ghost <?= $page>=$totalPages?'pointer-events-none opacity-40':'' ?>"
            href="<?= $page<$totalPages ? htmlspecialchars($mk($page+1)) : '#' ?>"><?= __('Next') ?></a>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>
<script>
  function openTxModal(id){
    const dlg = document.getElementById(id);
    if (!dlg) return;
    if (typeof dlg.showModal === 'function') {
      dlg.showModal();
    } else {
      if (!dlg.__mmOverlayActive) {
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.open();
        dlg.__mmOverlayActive = true;
      }
      dlg.setAttribute('open','');
    }
  }
  function closeTxModal(id){
    const dlg = document.getElementById(id);
    if (!dlg) return;
    if (typeof dlg.close === 'function') {
      dlg.close();
    } else {
      dlg.removeAttribute('open');
      if (dlg.__mmOverlayActive) {
        dlg.__mmOverlayActive = false;
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.close();
      }
    }
  }
  // Close on backdrop click
  document.addEventListener('click', (e)=>{
    const dlg = e.target.closest('dialog[open]');
    if (dlg && e.target === dlg) {
      closeTxModal(dlg.id);
    }
  });
</script>

<script>
  function openMonthPicker(wrapper){
    const input = wrapper.querySelector('input[type="month"]');
    if (!input) return;
    // Best: Chrome/Edge support
    if (typeof input.showPicker === 'function') {
      input.showPicker();
      return;
    }
    // Fallbacks for Safari/Firefox
    input.focus({preventScroll:true});
    // Some browsers only open on .click()
    try { input.click(); } catch(e) {}
  }
</script>
