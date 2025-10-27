<?php
$feedbackEntries = $feedbackEntries ?? [];
$filters = $filters ?? [];
$statusOptions = $statusOptions ?? [];
$kindOptions = $kindOptions ?? [];
$severityOptions = $severityOptions ?? [];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0];
$currentUrl = $currentUrl ?? '/admin/feedbacks';
?>

<div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
  <section class="card">
    <div class="card-kicker flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">
      <i data-lucide="message-circle" class="h-4 w-4"></i>
      <?= __('Administration') ?>
    </div>
    <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="card-title text-3xl font-semibold text-slate-900 dark:text-white">
          <?= __('User feedback') ?>
        </h1>
        <p class="card-subtle mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300/80">
          <?= __('Review every piece of feedback shared by customers and jump into user details in a click.') ?>
        </p>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash']) || !empty($_SESSION['flash_success'])): ?>
      <div class="mt-6 space-y-3">
        <?php if (!empty($_SESSION['flash'])): ?>
          <div class="rounded-3xl border border-rose-300/70 bg-rose-50/80 p-4 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-100">
            <?= htmlspecialchars($_SESSION['flash']) ?>
          </div>
          <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
          <div class="rounded-3xl border border-emerald-300/70 bg-emerald-50/80 p-4 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-100">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
          </div>
          <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="get" action="/admin/feedbacks" class="mt-6 grid gap-4 rounded-3xl border border-white/60 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40 sm:grid-cols-2 lg:grid-cols-5">
      <div class="sm:col-span-2">
        <label for="filter-search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Search') ?>
        </label>
        <input
          id="filter-search"
          name="q"
          type="text"
          value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
          placeholder="<?= __('Search title, message, or email') ?>"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        />
      </div>
      <div>
        <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Status') ?>
        </label>
        <select
          id="filter-status"
          name="status"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        >
          <option value=""><?= __('All') ?></option>
          <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="filter-kind" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Type') ?>
        </label>
        <select
          id="filter-kind"
          name="kind"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        >
          <option value=""><?= __('All') ?></option>
          <?php foreach ($kindOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['kind'] ?? '') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="filter-severity" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <?= __('Severity') ?>
        </label>
        <select
          id="filter-severity"
          name="severity"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/40 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200"
        >
          <option value=""><?= __('All') ?></option>
          <?php foreach ($severityOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['severity'] ?? '') === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-1">
        <button type="submit" class="btn btn-primary w-full justify-center">
          <?= __('Apply') ?>
        </button>
        <a class="btn btn-muted justify-center" href="/admin/feedbacks">
          <?= __('Clear') ?>
        </a>
      </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-3xl border border-white/60 bg-white/60 shadow-sm backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/40">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
          <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Feedback') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Type') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('Status') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold"><?= __('User') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap"><?= __('Submitted') ?></th>
              <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap"><?= __('Updated') ?></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php if ($feedbackEntries): ?>
              <?php foreach ($feedbackEntries as $entry):
                $kind = strtolower((string)($entry['kind'] ?? ''));
                $kindLabel = $kindOptions[$kind] ?? ucfirst($kind);
                $severityRaw = $entry['severity'] ?? null;
                $severityKey = $severityRaw !== null ? strtolower((string)$severityRaw) : '';
                $severityLabel = $severityKey !== '' ? ($severityOptions[$severityKey] ?? ucfirst($severityKey)) : __('None');
                $statusKey = strtolower((string)($entry['status'] ?? ''));
                $statusLabel = $statusOptions[$statusKey] ?? ucfirst($statusKey);
                $title = trim((string)($entry['title'] ?? '')) ?: __('(untitled)');
                $preview = trim((string)($entry['message_preview'] ?? ''));
                $fullMessage = (string)($entry['message'] ?? '');
                $submittedAt = $entry['created_at'] ? date('Y-m-d H:i', strtotime((string)$entry['created_at'])) : __('Unknown');
                $updatedAt = $entry['updated_at'] ? date('Y-m-d H:i', strtotime((string)$entry['updated_at'])) : __('Unknown');
                $displayName = trim((string)($entry['user_name'] ?? '')) ?: (string)($entry['user_email'] ?? __('Unknown user'));
                $userEmail = (string)($entry['user_email'] ?? '');
                $manageUrl = '/admin/users/manage?id=' . (int)($entry['user_id'] ?? 0);
                if (!empty($currentUrl)) {
                  $manageUrl .= '&return=' . rawurlencode($currentUrl);
                }
                $kindBadgeClass = $kind === 'bug'
                  ? 'border-rose-300/70 bg-rose-100/50 text-rose-700 dark:border-rose-400/50 dark:bg-rose-500/20 dark:text-rose-100'
                  : 'border-sky-300/70 bg-sky-100/50 text-sky-700 dark:border-sky-400/50 dark:bg-sky-500/20 dark:text-sky-100';
                $severityBadgeClass = match ($severityKey) {
                  'high' => 'border-rose-300/70 bg-rose-100/60 text-rose-700 dark:border-rose-400/60 dark:bg-rose-500/25 dark:text-rose-100',
                  'medium' => 'border-amber-300/70 bg-amber-100/60 text-amber-700 dark:border-amber-400/60 dark:bg-amber-500/25 dark:text-amber-100',
                  'low' => 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-400/60 dark:bg-emerald-500/20 dark:text-emerald-100',
                  default => 'border-slate-300/70 bg-slate-100/60 text-slate-700 dark:border-slate-500/50 dark:bg-slate-800/40 dark:text-slate-200',
                };
                $statusBadgeClass = match ($statusKey) {
                  'open' => 'border-amber-300/70 bg-amber-100/60 text-amber-700 dark:border-amber-400/60 dark:bg-amber-500/20 dark:text-amber-100',
                  'in_progress' => 'border-sky-300/70 bg-sky-100/60 text-sky-700 dark:border-sky-400/60 dark:bg-sky-500/20 dark:text-sky-100',
                  'resolved' => 'border-emerald-300/70 bg-emerald-100/60 text-emerald-700 dark:border-emerald-400/60 dark:bg-emerald-500/20 dark:text-emerald-100',
                  'closed' => 'border-slate-300/70 bg-slate-100/60 text-slate-700 dark:border-slate-500/60 dark:bg-slate-800/40 dark:text-slate-200',
                  default => 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
                };
                $statusIcon = match ($statusKey) {
                  'open' => 'circle-dot',
                  'in_progress' => 'loader-2',
                  'resolved' => 'check-circle-2',
                  'closed' => 'lock',
                  default => 'circle',
                };
                $kindIcon = $kind === 'bug' ? 'bug' : 'sparkles';
              ?>
                <tr class="align-top transition hover:bg-brand-50/40 dark:hover:bg-slate-800/30">
                  <td class="px-4 py-4 text-sm text-slate-700 dark:text-slate-200">
                    <div class="font-semibold text-slate-900 dark:text-white">
                      <?= htmlspecialchars($title) ?>
                    </div>
                    <?php if ($preview !== ''): ?>
                      <p class="mt-2 whitespace-pre-line text-xs text-slate-600 dark:text-slate-300" title="<?= htmlspecialchars($fullMessage) ?>">
                        <?= htmlspecialchars($preview) ?>
                      </p>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 text-sm text-slate-700 dark:text-slate-200">
                    <div class="flex flex-col gap-2">
                      <span class="inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= $kindBadgeClass ?>">
                        <i data-lucide="<?= $kindIcon ?>" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($kindLabel) ?>
                      </span>
                      <span class="inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= $severityBadgeClass ?>">
                        <i data-lucide="activity" class="h-3.5 w-3.5"></i>
                        <?= htmlspecialchars($severityLabel) ?>
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-4 text-sm text-slate-700 dark:text-slate-200">
                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= $statusBadgeClass ?>">
                      <i data-lucide="<?= $statusIcon ?>" class="h-3.5 w-3.5"></i>
                      <?= htmlspecialchars($statusLabel) ?>
                    </span>
                  </td>
                  <td class="px-4 py-4 text-sm text-slate-700 dark:text-slate-200">
                    <div class="flex flex-col">
                      <a class="font-semibold text-brand-700 transition hover:text-brand-500 dark:text-brand-200 dark:hover:text-brand-300" href="<?= htmlspecialchars($manageUrl) ?>">
                        <?= htmlspecialchars($displayName) ?>
                      </a>
                      <?php if ($userEmail !== ''): ?>
                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($userEmail) ?></span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                    <?= htmlspecialchars($submittedAt) ?>
                  </td>
                  <td class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                    <?= htmlspecialchars($updatedAt) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-300">
                  <?= __('No feedback has been submitted yet.') ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
      <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="text-sm text-slate-600 dark:text-slate-300">
          <?= __('Showing :count feedback item(s)', ['count' => number_format((int)($pagination['total'] ?? 0))]) ?>
        </div>
        <div class="flex items-center gap-2">
          <?php if (!empty($pagination['prev'])): ?>
            <a class="btn btn-muted" href="<?= htmlspecialchars($pagination['prev']) ?>">
              <?= __('Previous') ?>
            </a>
          <?php endif; ?>
          <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            <?= __('Page :current of :total', ['current' => (int)($pagination['page'] ?? 1), 'total' => (int)($pagination['pages'] ?? 1)]) ?>
          </span>
          <?php if (!empty($pagination['next'])): ?>
            <a class="btn btn-muted" href="<?= htmlspecialchars($pagination['next']) ?>">
              <?= __('Next') ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>
</div>
