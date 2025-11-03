<?php
$goalTransactions = $goalTransactions ?? [];
$activeGoals = $activeGoals ?? [];
$archivedGoals = $archivedGoals ?? [];
$allGoals = $allGoals ?? array_merge($activeGoals, $archivedGoals);
?>

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
      <?php foreach ($activeGoals as $g):
        $cur = $g['currency'] ?: 'HUF';
        $target = (float)($g['target_amount'] ?? 0);
        $current= (float)($g['current_amount'] ?? 0);
        $pct = $target>0 ? min(100, max(0, $current/$target*100)) : 0;
        $statusKey = strtolower((string)($g['status'] ?? 'active'));
        $statusLabel = match ($statusKey) {
          'paused' => __('Paused'),
          'done', 'completed' => __('Done'),
          default  => __('Active'),
        };
        $isCompleted = !empty($g['_is_completed']);
        $canArchive = !empty($g['_can_archive']);
        $completedBySchedule = !empty($g['_completed_by_schedule']);
        $goalLocked = $isCompleted && empty($g['archived_at']);
        $archiveLabel = $completedBySchedule ? __('Complete and withdraw') : __('Withdraw and Archive');
        $archiveConfirm = $completedBySchedule
          ? __('Complete this goal, withdraw the balance, and archive the linked schedule?')
          : __('Withdraw and archive this goal?');
      ?>
        <tr class="border-b align-top">
          <td class="py-3 pr-3">
            <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
            <div class="text-xs text-gray-500 flex flex-wrap items-center gap-2">
              <span><?= $statusLabel ?> · <?= htmlspecialchars($cur) ?></span>
              <?php if ($isCompleted && empty($g['archived_at'])): ?>
                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                  <span aria-hidden="true">🌟</span>
                  <?= __('Finished') ?>
                </span>
              <?php endif; ?>
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
              <button type="button" class="icon-action" data-open="#goal-history-<?= (int)$g['id'] ?>" title="<?= __('View history') ?>">
                <i data-lucide="history" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('View history') ?></span>
              </button>
              <?php if (!$goalLocked): ?>
                <button type="button" class="btn btn-primary !px-3"
                        data-open="#goal-add-<?= (int)$g['id'] ?>"><?= __('Add money') ?></button>
                <button type="button" class="btn !px-3"
                        data-open="#goal-edit-<?= (int)$g['id'] ?>"><?= __('Edit') ?></button>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-500/40 dark:bg-slate-500/10 dark:text-slate-200">
                  <span aria-hidden="true">🔒</span>
                  <?= __('Locked') ?>
                </span>
              <?php endif; ?>
              <?php if ($canArchive): ?>
                <form method="post" action="/goals/archive" onsubmit="return confirm('<?= $archiveConfirm ?>');" class="shrink-0">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= (int)$g['id'] ?>" />
                  <button type="submit" class="btn !px-3"><?= $archiveLabel ?></button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; if(!count($activeGoals)): ?>
        <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500"><?= __('No goals yet.') ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="md:hidden space-y-3">
    <?php foreach ($activeGoals as $g):
      $cur = $g['currency'] ?: 'HUF';
      $target = (float)($g['target_amount'] ?? 0);
      $current= (float)($g['current_amount'] ?? 0);
      $pct = $target>0 ? min(100, max(0, $current/$target*100)) : 0;
      $statusKey = strtolower((string)($g['status'] ?? 'active'));
      $statusLabel = match ($statusKey) {
        'paused' => __('Paused'),
        'done', 'completed' => __('Done'),
        default  => __('Active'),
      };
      $isCompleted = !empty($g['_is_completed']);
      $canArchive = !empty($g['_can_archive']);
      $completedBySchedule = !empty($g['_completed_by_schedule']);
      $goalLocked = $isCompleted && empty($g['archived_at']);
      $archiveLabel = $completedBySchedule ? __('Complete and withdraw') : __('Withdraw and Archive');
      $archiveConfirm = $completedBySchedule
        ? __('Complete this goal, withdraw the balance, and archive the linked schedule?')
        : __('Withdraw and archive this goal?');
    ?>
      <div class="panel p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
            <div class="text-xs text-gray-500 flex flex-wrap items-center gap-2">
              <span><?= $statusLabel ?> · <?= htmlspecialchars($cur) ?></span>
              <?php if ($isCompleted && empty($g['archived_at'])): ?>
                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
                  <span aria-hidden="true">🌟</span>
                  <?= __('Finished') ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="icon-action" data-open="#goal-history-<?= (int)$g['id'] ?>" title="<?= __('View history') ?>">
              <i data-lucide="history" class="h-4 w-4"></i>
              <span class="sr-only"><?= __('View history') ?></span>
            </button>
            <?php if (!$goalLocked): ?>
              <button type="button" class="icon-action icon-action--primary" data-open="#goal-edit-<?= (int)$g['id'] ?>" title="<?= __('Edit') ?>">
                <i data-lucide="pencil" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('Edit') ?></span>
              </button>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-500/40 dark:bg-slate-500/10 dark:text-slate-200">
                <span aria-hidden="true">🔒</span>
                <?= __('Locked') ?>
              </span>
            <?php endif; ?>
          </div>
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
          <?php if (!$goalLocked): ?>
            <button type="button" class="btn btn-primary" data-open="#goal-add-<?= (int)$g['id'] ?>"><?= __('Add money') ?></button>
          <?php endif; ?>
          <?php if ($canArchive): ?>
            <form method="post" action="/goals/archive" onsubmit="return confirm('<?= $archiveConfirm ?>');" class="w-full">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$g['id'] ?>" />
              <button type="submit" class="btn w-full"><?= $archiveLabel ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (count($archivedGoals)): ?>
<section class="mt-6 card">
  <details class="group">
    <summary class="flex cursor-pointer items-center justify-between gap-3 font-semibold">
      <span><?= __('Archived goals') ?></span>
      <span class="text-xs text-gray-500"><?= count($archivedGoals) ?></span>
    </summary>

    <div class="mt-3 text-sm text-gray-500">
      <?= __('Completed goals live here for safekeeping. Balances and schedules are locked, but you can still review their history.') ?>
    </div>

    <div class="hidden md:block overflow-x-auto mt-4">
      <table class="table-glass min-w-full text-sm">
        <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3 w-[38%]"><?= __('Goal') ?></th>
          <th class="py-2 pr-3 w-[20%]"><?= __('Schedule') ?></th>
          <th class="py-2 pr-3 w-[22%]"><?= __('Progress') ?></th>
          <th class="py-2 pr-3 w-[20%] text-right"><?= __('Actions') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($archivedGoals as $g):
          $cur = $g['currency'] ?: 'HUF';
          $target = (float)($g['target_amount'] ?? 0);
          $current = (float)($g['current_amount'] ?? 0);
          $pct = $target > 0 ? min(100, max(0, $current / $target * 100)) : 0;
          $archivedAt = $g['archived_at'] ?? null;
        ?>
          <tr class="border-b align-top bg-emerald-50/50 dark:bg-emerald-500/5">
            <td class="py-3 pr-3">
              <div class="font-medium flex items-center gap-2 flex-wrap">
                <?= htmlspecialchars($g['title']) ?>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-500/40 dark:bg-slate-500/10 dark:text-slate-200">
                  <span aria-hidden="true">📦</span>
                  <?= __('Archived') ?>
                </span>
              </div>
              <div class="text-xs text-gray-500">
                <?= htmlspecialchars($cur) ?>
                <?php if ($archivedAt): ?>
                  · <?= __('Archived on :date', ['date' => htmlspecialchars(date('Y-m-d', strtotime($archivedAt)))]) ?>
                <?php endif; ?>
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
              <div class="text-sm">
                <?= __('Goal saved in full!') ?><br>
                <?= __(':amount stored of :target', ['amount' => moneyfmt($current,$cur), 'target' => moneyfmt($target,$cur)]) ?>
              </div>
            </td>

            <td class="py-3 pr-3 text-right align-middle">
              <div class="flex justify-end gap-2">
                <button type="button" class="icon-action" data-open="#goal-history-<?= (int)$g['id'] ?>" title="<?= __('View history') ?>">
                  <i data-lucide="history" class="h-4 w-4"></i>
                  <span class="sr-only"><?= __('View history') ?></span>
                </button>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-500/40 dark:bg-slate-500/10 dark:text-slate-200">
                  <span aria-hidden="true">✅</span>
                  <?= __('Complete') ?>
                </span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="md:hidden space-y-3 mt-4">
      <?php foreach ($archivedGoals as $g):
        $cur = $g['currency'] ?: 'HUF';
        $target = (float)($g['target_amount'] ?? 0);
        $current = (float)($g['current_amount'] ?? 0);
        $pct = $target > 0 ? min(100, max(0, $current / $target * 100)) : 0;
        $archivedAt = $g['archived_at'] ?? null;
      ?>
        <div class="panel p-4 border-emerald-300/60 bg-emerald-50/60 dark:border-emerald-500/40 dark:bg-emerald-500/10">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="font-medium"><?= htmlspecialchars($g['title']) ?></div>
              <div class="text-xs text-gray-500">
                <?= htmlspecialchars($cur) ?>
                <?php if ($archivedAt): ?>
                  · <?= __('Archived :date', ['date' => htmlspecialchars(date('Y-m-d', strtotime($archivedAt)))]) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" class="icon-action" data-open="#goal-history-<?= (int)$g['id'] ?>" title="<?= __('View history') ?>">
                <i data-lucide="history" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('View history') ?></span>
              </button>
              <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-500/40 dark:bg-slate-500/10 dark:text-slate-200">
                <span aria-hidden="true">📦</span>
                <?= __('Archived') ?>
              </span>
            </div>
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
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
</section>
<?php endif; ?>

<?php foreach ($allGoals as $g): $goalId=(int)$g['id']; $cur=$g['currency'] ?: 'HUF'; $goalTxList = $goalTransactions[$goalId] ?? []; $statusForArchive = strtolower((string)($g['status'] ?? '')); $isArchivedGoal = !empty($g['archived_at']) || in_array($statusForArchive, ['done','completed'], true); $goalLockedForModal = !empty($g['_is_completed']) && empty($g['archived_at']); $completedByScheduleModal = !empty($g['_completed_by_schedule']); ?>
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
      <?php if ($goalLockedForModal && !$isArchivedGoal): ?>
        <div class="rounded-xl border border-amber-300/70 bg-amber-50/80 p-4 text-sm text-amber-700 dark:border-amber-500/50 dark:bg-amber-500/10 dark:text-amber-200">
          <?= $completedByScheduleModal
            ? __('This goal is finished. Complete and withdraw it to make further changes.')
            : __('This goal is finished. Withdraw and archive it to make further changes.') ?>
        </div>
      <?php endif; ?>
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
        <button class="btn btn-primary" form="goal-form-<?= $goalId ?>"<?= $goalLockedForModal ? ' disabled' : '' ?>><?= __('Save') ?></button>
      </div>
    </div>
  </div>
</div>

<?php if (!$isArchivedGoal): ?>
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
        <div class="sm:col-span-12">
          <label class="label"><?= __('Note (optional)') ?></label>
          <input name="note" class="input" placeholder="<?= __('e.g., Transfer from savings') ?>" />
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
<?php endif; ?>
<div id="goal-history-<?= $goalId ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="goal-history-title-<?= $goalId ?>">
  <div class="modal-backdrop" data-close></div>

  <div class="modal-panel">
    <div class="modal-header">
      <h3 id="goal-history-title-<?= $goalId ?>" class="font-semibold"><?= __('Transactions') ?></h3>
      <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="modal-body">
      <?php if ($goalTxList): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                <th class="py-2 pr-3"><?= __('Date') ?></th>
                <th class="py-2 pr-3"><?= __('Amount') ?></th>
                <th class="py-2 pr-3"><?= __('Note') ?></th>
                <th class="py-2 pr-0 text-right"><?= __('Actions') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($goalTxList as $tx): $txId=(int)$tx['id']; $txCur = $tx['currency'] ?: $cur; ?>
              <tr class="border-t">
                <td class="py-2 pr-3 align-middle text-sm"><?= htmlspecialchars($tx['occurred_on']) ?></td>
                <td class="py-2 pr-3 align-middle text-sm">
                  <?= moneyfmt((float)$tx['amount'], $txCur) ?>
                  <?php if ($txCur && strtoupper($txCur) !== strtoupper($cur)): ?>
                    <span class="text-xs text-gray-500">(<?= htmlspecialchars($txCur) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 pr-3 align-middle text-sm">
                  <?php if ($tx['note'] !== null && $tx['note'] !== ''): ?>
                    <?= htmlspecialchars($tx['note']) ?>
                  <?php else: ?>
                    <span class="text-gray-400"><?= __('No note') ?></span>
                  <?php endif; ?>
                </td>
                <td class="py-2 pr-0 align-middle text-right">
                  <?php if (!$isArchivedGoal): ?>
                    <div class="flex justify-end gap-2">
                      <button type="button" class="btn !px-3" data-open="#goal-tx-edit-<?= $txId ?>"><?= __('Edit') ?></button>
                      <form method="post" action="/goals/tx/delete" onsubmit="return confirm('<?= __('Delete this transaction?') ?>');">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                        <input type="hidden" name="id" value="<?= $txId ?>" />
                        <button class="btn btn-danger !px-3" type="submit"><?= __('Delete') ?></button>
                      </form>
                    </div>
                  <?php else: ?>
                    <span class="text-xs uppercase tracking-wide text-gray-400"><?= __('Locked') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-500"><?= __('No transactions yet.') ?></p>
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

<?php foreach ($allGoals as $g): $goalId=(int)$g['id']; $cur=$g['currency'] ?: 'HUF'; $goalTxList = $goalTransactions[$goalId] ?? []; $statusForArchive = strtolower((string)($g['status'] ?? '')); $isArchivedGoal = !empty($g['archived_at']) || in_array($statusForArchive, ['done','completed'], true); ?>
  <?php if ($isArchivedGoal) { continue; } ?>
  <?php foreach ($goalTxList as $tx): $txId=(int)$tx['id']; $txCur = $tx['currency'] ?: $cur; ?>
    <div id="goal-tx-edit-<?= $txId ?>" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="goal-tx-edit-title-<?= $txId ?>">
      <div class="modal-backdrop" data-close></div>
      <div class="modal-panel">
        <div class="modal-header">
          <h3 id="goal-tx-edit-title-<?= $txId ?>" class="font-semibold"><?= __('Edit transaction') ?></h3>
          <button type="button" class="icon-btn" aria-label="<?= __('Close') ?>" data-close>
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>
        <div class="modal-body">
          <form method="post" action="/goals/tx/update" id="goal-tx-form-<?= $txId ?>" class="grid gap-3 sm:grid-cols-12">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= $txId ?>" />
            <div class="sm:col-span-5">
              <label class="label"><?= __('Date') ?></label>
              <input name="occurred_on" type="date" class="input" value="<?= htmlspecialchars($tx['occurred_on']) ?>" required />
            </div>
            <div class="sm:col-span-5">
              <label class="label"><?= __('Amount') ?></label>
              <input name="amount" type="number" step="0.01" class="input" value="<?= htmlspecialchars($tx['amount']) ?>" required />
            </div>
            <div class="sm:col-span-4">
              <label class="label"><?= __('Currency') ?></label>
              <select name="currency" class="select">
                <option value=""><?= __('Default') ?></option>
                <?php foreach ($userCurrencies as $uc): $code=$uc['code']; ?>
                  <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($code)===strtoupper($txCur)?'selected':'' ?>>
                    <?= htmlspecialchars($code) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sm:col-span-12">
              <label class="label"><?= __('Note (optional)') ?></label>
              <input name="note" class="input" value="<?= htmlspecialchars($tx['note'] ?? '') ?>" />
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <div class="flex flex-row flex-wrap gap-2 justify-end">
            <button type="button" class="btn" data-close><?= __('Cancel') ?></button>
            <button class="btn btn-primary" form="goal-tx-form-<?= $txId ?>"><?= __('Save changes') ?></button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
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
  document.querySelectorAll('.rrule-summary[data-rrule]').forEach(el=>{
    const r = el.getAttribute('data-rrule') || '';
    el.textContent = (typeof rrSummary === 'function') ? rrSummary(r) : r;
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
