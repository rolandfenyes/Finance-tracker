<section class="card">
  <h1 class="text-xl font-semibold"><?= __('Goals') ?></h1>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-2 text-sm text-brand-600"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
  <?php endif; ?>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent"><?= __('Add goal') ?></summary>
    <form class="mt-3 grid sm:grid-cols-12 gap-3" method="post" action="/goals/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div class="sm:col-span-5">
        <label class="label"><?= __('Title') ?></label>
        <input name="title" class="input" placeholder="<?= __('e.g., New laptop') ?>" required />
      </div>
      <div class="sm:col-span-3">
        <label class="label"><?= __('Target') ?></label>
        <input name="target_amount" type="number" step="0.01" class="input" placeholder="0.00" required />
      </div>
      <div class="sm:col-span-2">
        <label class="label"><?= __('Currency') ?></label>
        <select name="currency" class="select">
          <?php foreach ($userCurrencies as $uc): ?>
            <option value="<?= htmlspecialchars($uc['code']) ?>" <?= !empty($uc['is_main'])?'selected':'' ?>>
              <?= htmlspecialchars($uc['code']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sm:col-span-2">
        <label class="label"><?= __('Current (optional)') ?></label>
        <input name="current_amount" type="number" step="0.01" class="input" placeholder="0.00" />
      </div>
      <div class="sm:col-span-12">
        <label class="label"><?= __('Status') ?></label>
        <select name="status" class="select w-full max-w-xs">
          <option value="active"><?= __('Active') ?></option>
          <option value="paused"><?= __('Paused') ?></option>
          <option value="done"><?= __('Done') ?></option>
        </select>
      </div>
      <div class="sm:col-span-12 flex justify-end">
        <button class="btn btn-primary"><?= __('Save') ?></button>
      </div>
    </form>
  </details>
</section>

<section class="mt-6 card">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold"><?= __('Your goals') ?></h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="table-glass min-w-full text-sm">
      <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3 w-[38%]"><?= __('Goal') ?></th>
        <th class="py-2 pr-3 w-[20%]"><?= __('Schedule') ?></th>
        <th class="py-2 pr-3 w-[22%]"><?= __('Progress') ?></th>
        <th class="py-2 pr-3 w-[20%]" style="text-align:right;"><?= __('Actions') ?></th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $g):
        $cur = $g['currency'] ?: 'HUF';
        $target = (float)($g['target_amount'] ?? 0);
        $current= (float)($g['current_amount'] ?? 0);
        $pct = $target>0 ? min(100, max(0, $current/$target*100)) : 0;
        $statusKey = $g['status'] ?? 'active';
        $statusLabel = match ($statusKey) {
          'paused' => __('Paused'),
          'done'   => __('Done'),
          default  => __('Active'),
        };
      ?>
        <tr class="border-b align-top">
          <td class="py-3 pr-3">
            <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
            <div class="text-xs text-gray-500">
              <?= $statusLabel ?> · <?= htmlspecialchars($cur) ?>
            </div>
            <div class="mt-2">
              <div class="h-2 bg-brand-100/60 rounded-full">
                <div class="h-2 bg-brand-500 rounded-full" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
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
              <div class="text-xs text-gray-500"><?= __('No schedule') ?></div>
            <?php endif; ?>
          </td>

          <td class="py-3 pr-3 align-middle">
            <div class="text-sm"><?= __(':amount to go', ['amount' => moneyfmt($target - $current, $cur)]) ?></div>
          </td>

          <td class="py-3 pr-0 align-middle" style="text-align:right;">
            <div class="flex justify-end gap-2">
              <button class="btn btn-primary !px-3"
                      data-open="#goal-add-<?= (int)$g['id'] ?>"><?= __('Add money') ?></button>
              <button class="btn !px-3"
                      data-open="#goal-edit-<?= (int)$g['id'] ?>"><?= __('Edit') ?></button>
            </div>
          </td>
        </tr>
      <?php endforeach; if(!count($rows)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500"><?= __('No goals yet.') ?></td></tr>
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
      $statusKey = $g['status'] ?? 'active';
      $statusLabel = match ($statusKey) {
        'paused' => __('Paused'),
        'done'   => __('Done'),
        default  => __('Active'),
      };
    ?>
      <div class="panel p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
            <div class="text-xs text-gray-500"><?= $statusLabel ?> · <?= htmlspecialchars($cur) ?></div>
          </div>
          <button class="icon-action icon-action--primary" data-open="#goal-edit-<?= (int)$g['id'] ?>" title="<?= __('Edit') ?>">
            <i data-lucide="pencil" class="h-4 w-4"></i>
            <span class="sr-only"><?= __('Edit') ?></span>
          </button>
        </div>
        <div class="mt-3">
          <div class="h-2 bg-brand-100/60 rounded-full">
            <div class="h-2 bg-brand-500 rounded-full" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
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

        <div class="mt-3 flex flex-col gap-2">
          <button class="btn btn-primary" data-open="#goal-add-<?= (int)$g['id'] ?>"><?= __('Add money') ?></button>
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
      <h3 id="goal-edit-title-<?= $goalId ?>" class="font-semibold"><?= __('Edit goal') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="modal-body flex flex-col gap-6">
      <div class="grid gap-4 md:grid-cols-12">
        <form id="goal-form-<?= $goalId ?>" method="post" action="/goals/edit" class="col-span-6 grid gap-4 md:grid-cols-7">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <input type="hidden" name="id" value="<?= $goalId ?>" />
  
          <!-- Left column: Goal details (like Loans left column) -->
          <div class="md:col-span-7">
            <div class="grid sm:grid-cols-12 gap-3">
              <div class="sm:col-span-7">
                <label class="label"><?= __('Name') ?></label>
                <input name="title" class="input" value="<?= htmlspecialchars($g['title']) ?>" required />
              </div>
              <div class="sm:col-span-5">
                <label class="label"><?= __('Target') ?></label>
                <input name="target_amount" type="number" step="0.01" class="input" value="<?= htmlspecialchars($g['target_amount']) ?>" required />
              </div>

              <div class="sm:col-span-6">
                <label class="label"><?= __('Currency') ?></label>
                <select name="currency" class="select">
                  <?php foreach ($userCurrencies as $uc): $code=$uc['code']; ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($code)===strtoupper($cur)?'selected':'' ?>><?= htmlspecialchars($code) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="sm:col-span-6">
                <label class="label"><?= __('Status') ?></label>
                <select name="status" class="select">
                  <option value="active" <?= $g['status']==='active'?'selected':'' ?>><?= __('Active') ?></option>
                  <option value="paused" <?= $g['status']==='paused'?'selected':'' ?>><?= __('Paused') ?></option>
                  <option value="done"   <?= $g['status']==='done'  ?'selected':'' ?>><?= __('Done') ?></option>
                </select>
              </div>

              <div class="sm:col-span-12">
                <label class="label"><?= __('Note (optional)') ?></label>
                <input name="note" class="input" value="<?= htmlspecialchars($g['note'] ?? '') ?>" />
              </div>
            </div>
          </div>
        </form>
  
        <!-- Right column: Scheduled OR Manual (like Loans right column) -->
        <div class="md:col-span-6 grid gap-4">

          <h4 class="font-semibold">Add money</h4>

          <?php if (!empty($g['sched_id'])): ?>
            <!-- Linked card -->
            <div class="rounded-xl border p-3 bg-gray-50">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="font-medium"><?= htmlspecialchars($g['sched_title']) ?></div>
                  <div class="text-xs text-gray-600">
                    <?= moneyfmt($g['sched_amount'], $g['sched_currency']) ?>
                    <?php if (!empty($g['sched_rrule'])): ?>
                      · <span class="rrule-summary" data-rrule="<?= htmlspecialchars($g['sched_rrule']) ?>"></span>
                    <?php endif; ?>
                    <?php if (!empty($g['sched_next_due'])): ?>
                      · <?= __('next :date', ['date' => htmlspecialchars($g['sched_next_due'])]) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <!-- Unlink -->
                <form method="post" action="/goals/unlink-schedule" class="shrink-0" id="unlink-form-<?= $goalId ?>">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
                  <button class="btn btn-danger !py-1 !px-3" data-unlink><?= __('Unlink') ?></button>
                </form>
              </div>
            </div>

            <!-- Hidden containers that will show after unlink (via JS enhancement) -->
            <div class="hidden" id="link-wrap-<?= $goalId ?>">
              <!-- Link existing schedule -->
              <form method="post" action="/goals/link-schedule" class="grid gap-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
                <label class="label"><?= __('Link existing schedule') ?></label>
                <select name="scheduled_payment_id" class="select">
                  <option value=""><?= __('— None —') ?></option>
                  <?php foreach ($scheduledList as $sp): ?>
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

              <!-- Create schedule -->
              <form method="post" action="/goals/create-schedule" class="grid sm:grid-cols-12 gap-3">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
                <div class="sm:col-span-12">
                <label class="label"><?= __('Schedule title') ?></label>
                <input name="title" class="input" placeholder="<?= __('Goal: :title', ['title' => htmlspecialchars($g['title'])]) ?>">
                </div>
                <div class="sm:col-span-6">
                <label class="label"><?= __('Category') ?></label>
                <select name="category_id" class="select">
                    <option value=""><?= __('No category') ?></option>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="sm:col-span-6">
                <label class="label"><?= __('First due') ?></label>
                  <input name="next_due" type="date" class="input" />
                </div>
                <div class="sm:col-span-6">
                <label class="label"><?= __('Due day') ?></label>
                  <input name="due_day" type="number" min="1" max="31" class="input" placeholder="<?= __('e.g., 10') ?>" />
                </div>
                <div class="sm:col-span-6">
                <label class="label"><?= __('Monthly amount') ?></label>
                  <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" />
                </div>
                <div class="sm:col-span-6">
                <label class="label"><?= __('Currency') ?></label>
                  <select name="currency" class="select">
                    <?php foreach ($userCurrencies as $uc): ?>
                      <option value="<?= htmlspecialchars($uc['code']) ?>" <?= strtoupper($uc['code'])===strtoupper($cur)?'selected':'' ?>>
                        <?= htmlspecialchars($uc['code']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="sm:col-span-12 flex justify-end">
                  <button class="btn btn-primary"><?= __('Create schedule') ?></button>
                </div>
              </form>
            </div>

          <?php else: ?>
            <!-- No linked schedule: show link + create immediately -->
            <form method="post" action="/goals/link-schedule" class="grid gap-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
                <label class="label"><?= __('Link existing schedule') ?></label>
              <select name="scheduled_payment_id" class="select">
                <option value=""><?= __('— None —') ?></option>
                <?php foreach ($scheduledList as $sp): ?>
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

            <form method="post" action="/goals/create-schedule" class="grid sm:grid-cols-12 gap-3">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
              <div class="sm:col-span-12">
                <label class="label"><?= __('Schedule title') ?></label>
                <input name="title" class="input" placeholder="<?= __('Goal: :title', ['title' => htmlspecialchars($g['title'])]) ?>">
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Category') ?></label>
                <select name="category_id" class="select">
                  <option value=""><?= __('No category') ?></option>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('First due') ?></label>
                <input name="next_due" type="date" class="input" />
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Due day') ?></label>
                <input name="due_day" type="number" min="1" max="31" class="input" placeholder="<?= __('e.g., 10') ?>" />
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Monthly amount') ?></label>
                <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" />
              </div>
              <div class="sm:col-span-6">
                <label class="label"><?= __('Currency') ?></label>
                <select name="currency" class="select">
                  <?php foreach ($userCurrencies as $uc): ?>
                    <option value="<?= htmlspecialchars($uc['code']) ?>" <?= strtoupper($uc['code'])===strtoupper($cur)?'selected':'' ?>>
                      <?= htmlspecialchars($uc['code']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="sm:col-span-12 flex justify-end">
                <button class="btn btn-primary"><?= __('Create schedule') ?></button>
              </div>
            </form>
          <?php endif; ?>

        </div>

      </div>

      <section class="rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 text-sm text-rose-600 dark:border-rose-500/50 dark:bg-rose-500/10 dark:text-rose-200">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="space-y-1">
            <h4 class="text-base font-semibold"><?= __('Danger zone') ?></h4>
            <p class="text-sm text-rose-500 dark:text-rose-200/80">
              <?= __('Deleting a goal will remove its transactions.') ?>
            </p>
          </div>
          <form
            method="post"
            action="/goals/delete"
            onsubmit="return confirm('<?= __('Delete this goal?') ?>')"
            class="sm:shrink-0"
          >
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= $goalId ?>" />
            <button class="btn btn-danger w-full sm:w-auto">
              <?= __('Delete') ?>
            </button>
          </form>
        </div>
      </section>
    </div>

    <div class="modal-footer">
      <div class="flex flex-row flex-wrap gap-2 justify-end">
        <button class="btn" data-close><?= __('Cancel') ?></button>
        <button class="btn btn-primary" form="goal-form-<?= $goalId ?>"><?= __('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<div id="goal-add-<?= $goalId ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="goal-add-title-<?= $goalId ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel">
    <div class="modal-header">
      <h3 id="goal-add-title-<?= $goalId ?>" class="font-semibold"><?= __('Add money') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="modal-body">
      <form id="goal-add-form-<?= $goalId ?>" method="post" action="/goals/tx/add" class="grid gap-3 sm:grid-cols-12">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="goal_id" value="<?= $goalId ?>" />
        <div class="sm:col-span-5">
          <label class="label"><?= __('Date') ?></label>
          <input name="occurred_on" type="date" class="input" value="<?= date('Y-m-d') ?>" />
        </div>
        <div class="sm:col-span-5">
          <label class="label"><?= __('Amount (:currency)', ['currency' => htmlspecialchars($cur)]) ?></label>
          <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
        </div>
      </form>
    </div>

    <div class="modal-footer">
      <div class="flex flex-row flex-wrap gap-2 justify-end">
        <button type="button" class="btn" data-close><?= __('Cancel') ?></button>
        <button class="btn btn-primary" form="goal-add-form-<?= $goalId ?>"><?= __('Add money') ?></button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
  // modal open/close
  document.addEventListener('click', (e)=>{
    const open = e.target.closest('[data-open]');
    if (open){
      const modal = document.querySelector(open.dataset.open);
      if (modal){
        modal.classList.remove('hidden');
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.open();
      }
      return;
    }
    const close = e.target.closest('[data-close]');
    if (close){
      const modal = close.closest('.modal');
      if (modal && !modal.classList.contains('hidden')){
        modal.classList.add('hidden');
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.close();
      }
    }
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal:not(.hidden)').forEach((modal)=>{
        modal.classList.add('hidden');
        window.MyMoneyMapOverlay && window.MyMoneyMapOverlay.close();
      });
    }
  });

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

  const goalRruleText = {
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
  const goalDayNames = <?= json_encode([
    'MO' => __('Mon'),
    'TU' => __('Tue'),
    'WE' => __('Wed'),
    'TH' => __('Thu'),
    'FR' => __('Fri'),
    'SA' => __('Sat'),
    'SU' => __('Sun'),
  ]) ?>;
  const goalReplace = (tpl, replacements) => {
    if (!tpl) return '';
    let out = tpl;
    for (const [key, value] of Object.entries(replacements || {})) {
      out = out.replace(new RegExp(':'+key, 'g'), String(value ?? ''));
    }
    return out;
  };

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
      const ivStr = String(iv);
      let s = '';
      if (freq.value==='WEEKLY') {
        s = goalReplace(goalRruleText.everyWeeks, {count: ivStr});
      } else {
        s = goalReplace(goalRruleText.everyMonths, {count: ivStr});
        if (bymd.value) {
          s += goalReplace(goalRruleText.onDay, {day: bymd.value});
        }
      }
      if (endtype.value==='count' && countI.value) {
        s += goalReplace(goalRruleText.times, {count: countI.value});
      }
      if (endtype.value==='until' && untilI.value) {
        s += goalReplace(goalRruleText.until, {date: untilI.value});
      }
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

<script>
  document.querySelectorAll('[data-unlink]').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      const form = btn.closest('form');
      const panel = form.closest('.modal-body');
      const goalId = form.querySelector('input[name="goal_id"]')?.value;
      const linkWrap = document.getElementById('link-wrap-'+goalId);

      if (!form || !goalId || !panel || !linkWrap) return; // fall back to normal submit

      e.preventDefault();
      btn.disabled = true;

      try {
        const fd = new FormData(form);
        const res = await fetch(form.action, { method:'POST', body: fd, credentials:'same-origin' });
        if (!res.ok) throw new Error('HTTP '+res.status);

        // Hide the linked card (its container is the parent rounded box)
        const linkedCard = form.closest('.rounded-xl.border');
        if (linkedCard) linkedCard.classList.add('hidden');

        // Reveal link/create blocks
        linkWrap.classList.remove('hidden');
      } catch(err){
        form.submit(); // fallback full submit/redirect
      } finally {
        btn.disabled = false;
      }
    });
  });
</script>
