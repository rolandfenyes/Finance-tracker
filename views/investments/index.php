<?php
/** @var array $investments */
/** @var array $availableSchedules */

$types = [
  'savings' => [
    'label' => __('Savings account'),
    'description' => __('Track bank deposits and high-yield savings balances.'),
    'icon' => 'piggy-bank',
  ],
  'etf' => [
    'label' => __('ETF'),
    'description' => __('Monitor diversified funds like the S&P 500.'),
    'icon' => 'line-chart',
  ],
  'stock' => [
    'label' => __('Individual stock'),
    'description' => __('Follow single-company positions you hold.'),
    'icon' => 'trending-up',
  ],
];

?>

<div class="mx-auto w-full max-w-5xl space-y-6 pb-6 pt-6 sm:px-6 lg:px-0">
  <section class="card">
    <div class="card-kicker"><?= __('Investments') ?></div>
    <h1 class="card-title mt-1"><?= __('Manage your investment accounts') ?></h1>
    <p class="card-subtle mt-2 text-sm">
      <?= __('Keep track of savings accounts, ETFs, and individual stocks. Link scheduled payments to stay on autopilot.') ?>
    </p>

    <form method="post" action="/investments/add" class="mt-6 space-y-4">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <div class="grid gap-3 md:grid-cols-3">
        <?php foreach ($types as $value => $meta): ?>
          <label class="group relative flex cursor-pointer flex-col gap-2 rounded-2xl border border-slate-200 p-4 transition hover:border-brand-300 hover:bg-brand-50/70 dark:border-slate-700 dark:hover:border-brand-500/70 dark:hover:bg-slate-900/60">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 text-sm font-semibold">
                <input
                  type="radio"
                  name="type"
                  value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"
                  class="h-4 w-4 text-brand-600 focus:ring-brand-500"
                  <?= $value === 'savings' ? 'checked' : '' ?>
                />
                <span><?= htmlspecialchars($meta['label']) ?></span>
              </div>
              <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 group-hover:bg-brand-200 dark:bg-brand-500/15 dark:text-brand-50">
                <i data-lucide="<?= htmlspecialchars($meta['icon']) ?>" class="h-4 w-4"></i>
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-300/80">
              <?= htmlspecialchars($meta['description']) ?>
            </p>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <label class="label" for="investment-name"><?= __('Name') ?></label>
          <input id="investment-name" name="name" class="input" placeholder="<?= __('e.g., Smart Savings 2.5%') ?>" required />
        </div>
        <div>
          <label class="label" for="investment-provider"><?= __('Institution or broker') ?></label>
          <input id="investment-provider" name="provider" class="input" placeholder="<?= __('e.g., MyBank') ?>" />
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <label class="label" for="investment-identifier"><?= __('Account number / Ticker') ?></label>
          <input id="investment-identifier" name="identifier" class="input" placeholder="<?= __('e.g., HU12 3456 7890 1234 or VOO') ?>" />
        </div>
        <div>
          <label class="label" for="investment-interest"><?= __('Annual rate (EBKM)') ?></label>
          <div class="relative">
            <input id="investment-interest" name="interest_rate" type="number" step="0.01" min="0" class="input pr-12" placeholder="<?= __('Optional') ?>" />
            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">%</span>
          </div>
        </div>
      </div>

      <div>
        <label class="label" for="investment-notes"><?= __('Notes') ?></label>
        <textarea id="investment-notes" name="notes" rows="3" class="textarea" placeholder="<?= __('Add details like goals, strategy, or reminders.') ?>"></textarea>
      </div>

      <div>
        <label class="label" for="investment-schedule"><?= __('Linked scheduled payment') ?></label>
        <select id="investment-schedule" name="scheduled_payment_id" class="select">
          <option value=""><?= __('No linked schedule') ?></option>
          <?php foreach ($availableSchedules as $schedule): ?>
            <option value="<?= (int)$schedule['id'] ?>">
              <?= htmlspecialchars($schedule['title']) ?> · <?= moneyfmt($schedule['amount']) ?> <?= htmlspecialchars($schedule['currency']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-300/80">
          <?= __('Link a scheduled payment to automate contributions or transfers.') ?>
        </p>
      </div>

      <div class="flex justify-end">
        <button class="btn btn-primary">
          <i data-lucide="plus" class="mr-2 h-4 w-4"></i>
          <?= __('Add investment') ?>
        </button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2 class="card-title flex items-center gap-2">
      <i data-lucide="briefcase" class="h-5 w-5"></i>
      <?= __('Your investments') ?>
    </h2>
    <?php if (!$investments): ?>
      <p class="card-subtle mt-2 text-sm">
        <?= __('No investments yet. Add your first one above to start tracking performance and linked transfers.') ?>
      </p>
    <?php else: ?>
      <div class="mt-4 space-y-4">
        <?php foreach ($investments as $investment):
          $currentType = $investment['type'] ?? 'savings';
          $meta = $types[$currentType] ?? $types['savings'];
          $scheduleId = (int)($investment['sched_id'] ?? 0);
        ?>
          <div class="panel space-y-4 p-5">
            <form method="post" action="/investments/update" class="space-y-4" id="investment-update-<?= (int)$investment['id'] ?>">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$investment['id'] ?>" />

              <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                  <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-50">
                    <i data-lucide="<?= htmlspecialchars($meta['icon']) ?>" class="h-5 w-5"></i>
                  </span>
                  <div>
                  <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <?= htmlspecialchars($meta['label']) ?>
                  </div>
                  <div class="text-lg font-semibold text-slate-900 dark:text-white">
                    <?= htmlspecialchars($investment['name'] ?? '') ?>
                  </div>
                </div>
              </div>
              <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                <?= __('Last updated: :date', ['date' => htmlspecialchars(substr((string)($investment['updated_at'] ?? ''), 0, 10))]) ?>
              </div>
              </div>

            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="label"><?= __('Investment type') ?></label>
                <select name="type" class="select">
                  <?php foreach ($types as $value => $metaOption): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $value === $currentType ? 'selected' : '' ?>>
                      <?= htmlspecialchars($metaOption['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="label"><?= __('Name') ?></label>
                <input name="name" class="input" value="<?= htmlspecialchars($investment['name'] ?? '') ?>" required />
              </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="label"><?= __('Institution or broker') ?></label>
                <input name="provider" class="input" value="<?= htmlspecialchars($investment['provider'] ?? '') ?>" />
              </div>
              <div>
                <label class="label"><?= __('Account number / Ticker') ?></label>
                <input name="identifier" class="input" value="<?= htmlspecialchars($investment['identifier'] ?? '') ?>" />
              </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="label"><?= __('Annual rate (EBKM)') ?></label>
                <div class="relative">
                  <input name="interest_rate" type="number" step="0.01" min="0" class="input pr-12" value="<?= htmlspecialchars($investment['interest_rate'] ?? '') ?>" />
                  <span class="absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">%</span>
                </div>
              </div>
              <div>
                <label class="label"><?= __('Linked scheduled payment') ?></label>
                <select name="scheduled_payment_id" class="select">
                  <option value=""><?= __('No linked schedule') ?></option>
                  <?php if ($scheduleId): ?>
                    <option value="<?= $scheduleId ?>" selected>
                      <?= htmlspecialchars($investment['sched_title'] ?? '') ?>
                    </option>
                  <?php endif; ?>
                  <?php foreach ($availableSchedules as $schedule): ?>
                    <option value="<?= (int)$schedule['id'] ?>">
                      <?= htmlspecialchars($schedule['title']) ?> · <?= moneyfmt($schedule['amount']) ?> <?= htmlspecialchars($schedule['currency']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($scheduleId): ?>
                  <p class="mt-1 text-xs text-slate-500 dark:text-slate-300/80">
                    <?= __('Next payment on :date', ['date' => htmlspecialchars($investment['sched_next_due'] ?? '—')]) ?>
                  </p>
                <?php else: ?>
                  <p class="mt-1 text-xs text-slate-500 dark:text-slate-300/80">
                    <?= __('No schedule linked yet.') ?>
                  </p>
                <?php endif; ?>
              </div>
            </div>

            <div>
              <label class="label"><?= __('Notes') ?></label>
              <textarea name="notes" rows="3" class="textarea"><?= htmlspecialchars($investment['notes'] ?? '') ?></textarea>
            </div>

              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  <?= __('Created on :date', ['date' => htmlspecialchars(substr((string)($investment['created_at'] ?? ''), 0, 10))]) ?>
                </div>
                <div class="flex flex-wrap gap-2">
                  <button class="btn btn-primary">
                    <i data-lucide="save" class="mr-2 h-4 w-4"></i>
                    <?= __('Save changes') ?>
                  </button>
                  <button
                    type="submit"
                    form="investment-delete-<?= (int)$investment['id'] ?>"
                    class="btn btn-muted text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/30"
                  >
                    <i data-lucide="trash-2" class="mr-2 h-4 w-4"></i>
                    <?= __('Delete investment') ?>
                  </button>
                </div>
              </div>
            </form>

            <form
              method="post"
              action="/investments/delete"
              id="investment-delete-<?= (int)$investment['id'] ?>"
              onsubmit="return confirm('<?= __('Delete this investment?') ?>');"
            >
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$investment['id'] ?>" />
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
