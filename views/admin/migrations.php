<?php
  $summaryDefaults = [
    'total' => 0,
    'applied' => 0,
    'skipped' => 0,
    'failed' => 0,
    'pending' => 0,
  ];
  $summary = isset($summary) && is_array($summary)
    ? array_merge($summaryDefaults, $summary)
    : $summaryDefaults;
  $results = isset($results) && is_array($results) ? $results : [];
  $directory = isset($directory) ? $directory : null;
  $ranAt = isset($ranAt) ? $ranAt : null;
  $targetEmail = isset($targetEmail) ? $targetEmail : null;
  $error = isset($error) && $error !== '' ? $error : null;

  $total = (int)$summary['total'];
  $applied = (int)$summary['applied'];
  $skipped = (int)$summary['skipped'];
  $failed = (int)$summary['failed'];
  $pending = (int)$summary['pending'];

  $statusLabels = [
    'applied' => __('Applied'),
    'skipped' => __('Skipped'),
    'failed' => __('Failed'),
    'pending' => __('Pending'),
  ];

  $statusColors = [
    'applied' => 'text-emerald-600 dark:text-emerald-400',
    'skipped' => 'text-slate-500 dark:text-slate-400',
    'failed' => 'text-rose-600 dark:text-rose-400',
    'pending' => 'text-amber-600 dark:text-amber-400',
  ];

  $appliedClass = trim('text-lg font-semibold ' . ($statusColors['applied'] ?? ''));
  $skippedClass = trim('text-lg font-semibold ' . ($statusColors['skipped'] ?? ''));
  $failedClass = trim('text-lg font-semibold ' . ($statusColors['failed'] ?? ''));
  $pendingClass = trim('text-lg font-semibold ' . ($statusColors['pending'] ?? ''));
  $totalClass = 'text-lg font-semibold text-slate-900 dark:text-white';
?>
<section class="max-w-4xl w-full mx-auto">
  <div class="card space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="card-kicker"><?= __('Maintenance') ?></div>
        <h1 class="card-title"><?= __('Database migrations') ?></h1>
        <p class="card-subtle mt-2 text-sm">
          <?= __('Applies any SQL files in the migrations directory that have not been run yet.') ?>
        </p>
        <?php if ($directory): ?>
          <p class="card-subtle text-xs font-mono text-slate-500 dark:text-slate-400">
            <?= htmlspecialchars($directory, ENT_QUOTES) ?>
          </p>
        <?php endif; ?>
        <?php if ($targetEmail): ?>
          <p class="card-subtle text-xs text-slate-500 dark:text-slate-400">
            <?= __('Authorized account:') ?>
            <span class="font-mono">
              <?= htmlspecialchars($targetEmail, ENT_QUOTES) ?>
            </span>
          </p>
        <?php endif; ?>
        <?php if ($ranAt): ?>
          <p class="card-subtle text-xs text-slate-500 dark:text-slate-400">
            <?= __('Executed at :time', ['time' => htmlspecialchars($ranAt, ENT_QUOTES)]) ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="rounded-3xl border border-rose-300/70 bg-rose-50/80 p-4 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
        <?= nl2br(htmlspecialchars($error, ENT_QUOTES)) ?>
      </div>
    <?php elseif ($failed > 0): ?>
      <div class="rounded-3xl border border-rose-300/70 bg-rose-50/80 p-4 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
        <?= __('At least one migration failed. Review the details below before retrying.') ?>
      </div>
    <?php elseif ($applied > 0): ?>
      <div class="rounded-3xl border border-emerald-300/70 bg-emerald-50/80 p-4 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
        <?= __('Successfully applied :count new migration(s).', ['count' => number_format($applied)]) ?>
      </div>
    <?php else: ?>
      <div class="rounded-3xl border border-slate-200/70 bg-white/70 p-4 text-sm text-slate-600 dark:border-slate-800/50 dark:bg-slate-900/40 dark:text-slate-300">
        <?= __('Database is already up to date. No pending migrations found.') ?>
      </div>
    <?php endif; ?>

    <dl class="grid gap-4 sm:grid-cols-5">
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Total files') ?></dt>
        <dd class="<?= htmlspecialchars($totalClass, ENT_QUOTES) ?>"><?= number_format($total) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Applied') ?></dt>
        <dd class="<?= htmlspecialchars($appliedClass, ENT_QUOTES) ?>"><?= number_format($applied) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Skipped') ?></dt>
        <dd class="<?= htmlspecialchars($skippedClass, ENT_QUOTES) ?>"><?= number_format($skipped) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Failed') ?></dt>
        <dd class="<?= htmlspecialchars($failedClass, ENT_QUOTES) ?>"><?= number_format($failed) ?></dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Pending') ?></dt>
        <dd class="<?= htmlspecialchars($pendingClass, ENT_QUOTES) ?>"><?= number_format($pending) ?></dd>
      </div>
    </dl>

    <div class="rounded-3xl border border-white/60 bg-white/70 dark:border-slate-800/60 dark:bg-slate-900/40">
      <?php if ($results): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
              <tr>
                <th scope="col" class="px-4 py-3 font-semibold"><?= __('Migration file') ?></th>
                <th scope="col" class="px-4 py-3 font-semibold"><?= __('Status') ?></th>
                <th scope="col" class="px-4 py-3 font-semibold"><?= __('Details') ?></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($results as $row): ?>
                <?php
                  $status = $row['status'] ?? 'unknown';
                  $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                  $statusClass = 'font-medium ' . ($statusColors[$status] ?? 'text-slate-600 dark:text-slate-300');
                  $message = $row['message'] ?? '';
                  $rowHighlight = '';
                  if ($status === 'failed') {
                    $rowHighlight = 'bg-rose-50/60 dark:bg-rose-500/5';
                  } elseif ($status === 'applied') {
                    $rowHighlight = 'bg-emerald-50/50 dark:bg-emerald-500/5';
                  }
                ?>
                <tr class="<?= htmlspecialchars(trim($rowHighlight), ENT_QUOTES) ?>">
                  <td class="px-4 py-3 font-mono text-xs md:text-sm text-slate-700 dark:text-slate-300">
                    <?= htmlspecialchars($row['filename'] ?? '', ENT_QUOTES) ?>
                  </td>
                  <td class="px-4 py-3 <?= htmlspecialchars($statusClass, ENT_QUOTES) ?>">
                    <?= htmlspecialchars($statusLabel, ENT_QUOTES) ?>
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                    <?php if ($message): ?>
                      <?= nl2br(htmlspecialchars($message, ENT_QUOTES)) ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="px-4 py-4 text-sm text-slate-500 dark:text-slate-400">
          <?= __('No migration files were found.') ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
