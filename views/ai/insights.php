<?php
/** @var array $snapshot */
$snapshot = $snapshot ?? [];
$mainCurrency = $snapshot['main_currency'] ?? 'USD';
$month = $snapshot['month'] ?? [];
$goals = $snapshot['goals'] ?? [];
$loans = $snapshot['loans'] ?? [];
$emergency = $snapshot['emergency'] ?? [];
$recent = $snapshot['recent_activity'] ?? [];
$topCategories = $month['top_categories'] ?? [];

$formatMoney = function (float $amount) use ($mainCurrency): string {
    return moneyfmt($amount, $mainCurrency);
};
$formatDate = function (?string $date): string {
    if (!$date) {
        return '';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return $date;
    }
    return date('M j, Y', $timestamp);
};
?>

<div class="mx-auto w-full max-w-5xl space-y-6 px-4 pb-12 pt-6 sm:px-6 lg:px-0">
  <header class="card bg-gradient-to-br from-brand-500/15 via-brand-500/10 to-brand-500/5 dark:from-brand-400/20 dark:via-brand-500/15 dark:to-slate-900/40">
    <div class="card-kicker text-brand-700 dark:text-brand-200"><?= __('Intelligent guidance') ?></div>
    <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div class="max-w-2xl space-y-3">
        <h1 class="card-title text-3xl sm:text-4xl">
          <?= __('AI insights for your money') ?>
        </h1>
        <p class="text-base text-slate-600 dark:text-slate-300">
          <?= __('Summarise your latest activity and let MyMoneyMap Coach suggest the next best moves for your budget, goals, and debt payoff.') ?>
        </p>
      </div>
      <form id="ai-insights-form" class="w-full max-w-xs lg:w-auto" method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <button
          type="submit"
          class="btn btn-primary w-full justify-center gap-2 text-base font-semibold"
          data-label-default="<?= htmlspecialchars(__('Generate insights'), ENT_QUOTES) ?>"
          data-label-loading="<?= htmlspecialchars(__('Generating...'), ENT_QUOTES) ?>"
        >
          <i data-lucide="sparkles" class="h-4 w-4"></i>
          <span data-button-label><?= __('Generate insights') ?></span>
        </button>
      </form>
    </div>
    <p id="ai-insights-status" class="mt-4 text-sm text-slate-500 dark:text-slate-300/80" aria-live="polite"></p>
    <p id="ai-insights-error" class="mt-2 text-sm font-semibold text-rose-600 dark:text-rose-400" role="alert" aria-live="assertive"></p>
    <pre
      id="ai-insights-output"
      class="mt-4 whitespace-pre-wrap rounded-3xl border border-white/60 bg-white/70 p-5 text-sm leading-relaxed text-slate-700 shadow-inner dark:border-slate-800/70 dark:bg-slate-900/50 dark:text-slate-200"
    ><?= __('Your personalised guidance will appear here once generated.') ?></pre>
  </header>

  <section class="grid gap-6 lg:grid-cols-2">
    <article class="card h-full">
      <div class="card-kicker"><?= __('This month') ?></div>
      <div class="mt-3 flex items-start justify-between gap-4">
        <div>
          <h2 class="card-title text-2xl">
            <?= htmlspecialchars($month['label'] ?? format_month_year(), ENT_QUOTES) ?>
          </h2>
          <p class="card-subtle mt-1 text-sm">
            <?= __('Net in %(currency)s', ['currency' => $mainCurrency]) ?>
          </p>
        </div>
        <div class="rounded-3xl bg-brand-500/15 px-4 py-2 text-right text-brand-900 dark:bg-brand-500/20 dark:text-brand-100">
          <div class="text-xs uppercase tracking-wide text-brand-600 dark:text-brand-200"><?= __('Net') ?></div>
          <div class="text-xl font-semibold">
            <?= $formatMoney((float)($month['net_main'] ?? 0)) ?>
          </div>
        </div>
      </div>
      <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Income') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white">
            <?= $formatMoney((float)($month['income_main'] ?? 0)) ?>
          </dd>
        </div>
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Spending') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white">
            <?= $formatMoney((float)($month['spending_main'] ?? 0)) ?>
          </dd>
        </div>
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Savings rate') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white">
            <?= number_format((float)($month['savings_rate_pct'] ?? 0), 1) ?>%
          </dd>
        </div>
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Main currency') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white">
            <?= htmlspecialchars($mainCurrency, ENT_QUOTES) ?>
          </dd>
        </div>
      </dl>
      <?php if ($topCategories): ?>
        <div class="mt-6">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?= __('Top spending categories') ?></h3>
          <ul class="mt-3 space-y-2 text-sm">
            <?php foreach ($topCategories as $category): ?>
              <li class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/80 px-3 py-2 text-slate-700 shadow-sm dark:border-slate-800/70 dark:bg-slate-900/40 dark:text-slate-200">
                <span><?= htmlspecialchars($category['label'], ENT_QUOTES) ?></span>
                <span class="font-semibold">
                  <?= $formatMoney((float)($category['spending_main'] ?? 0)) ?> · <?= number_format((float)($category['share_pct'] ?? 0), 1) ?>%
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </article>

    <article class="card h-full">
      <div class="card-kicker"><?= __('Safety net') ?></div>
      <div class="mt-3 flex items-start justify-between gap-4">
        <div>
          <h2 class="card-title text-2xl"><?= __('Emergency fund') ?></h2>
          <p class="card-subtle mt-1 text-sm">
            <?= __('Balance vs. target in %(currency)s', ['currency' => $mainCurrency]) ?>
          </p>
        </div>
        <div class="rounded-3xl bg-emerald-500/15 px-4 py-2 text-right text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100">
          <div class="text-xs uppercase tracking-wide text-emerald-600 dark:text-emerald-200"><?= __('Progress') ?></div>
          <div class="text-xl font-semibold"><?= number_format((float)($emergency['progress_pct'] ?? 0), 1) ?>%</div>
        </div>
      </div>
      <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Balance') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white">
            <?= $formatMoney((float)($emergency['balance_main'] ?? 0)) ?>
          </dd>
        </div>
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Target') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white">
            <?= $formatMoney((float)($emergency['target_main'] ?? 0)) ?>
          </dd>
        </div>
      </dl>
      <div class="mt-5 h-3 w-full overflow-hidden rounded-full bg-slate-900/10 dark:bg-slate-800/70" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (float)($emergency['progress_pct'] ?? 0) ?>">
        <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500" style="width: <?= min(100, max(0, (float)($emergency['progress_pct'] ?? 0))) ?>%"></div>
      </div>
    </article>
  </section>

  <section class="grid gap-6 lg:grid-cols-2">
    <article class="card h-full">
      <div class="card-kicker"><?= __('Goals') ?></div>
      <h2 class="card-title text-2xl"><?= __('Savings progress') ?></h2>
      <p class="card-subtle mt-1 text-sm">
        <?= __('Total saved %(current)s of %(target)s', ['current' => $formatMoney((float)($goals['current_main'] ?? 0)), 'target' => $formatMoney((float)($goals['target_main'] ?? 0))]) ?>
      </p>
      <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Active goals') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white"><?= (int)($goals['active_count'] ?? 0) ?></dd>
        </div>
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Completed goals') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white"><?= (int)($goals['completed_count'] ?? 0) ?></dd>
        </div>
      </dl>
      <div class="mt-5 h-3 w-full overflow-hidden rounded-full bg-slate-900/10 dark:bg-slate-800/70" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (float)($goals['progress_pct'] ?? 0) ?>">
        <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-500" style="width: <?= min(100, max(0, (float)($goals['progress_pct'] ?? 0))) ?>%"></div>
      </div>
    </article>

    <article class="card h-full">
      <div class="card-kicker"><?= __('Loans') ?></div>
      <h2 class="card-title text-2xl"><?= __('Debt snapshot') ?></h2>
      <p class="card-subtle mt-1 text-sm">
        <?= __('Outstanding %(balance)s at an average %(rate)s%% interest', ['balance' => $formatMoney((float)($loans['balance_main'] ?? 0)), 'rate' => number_format((float)($loans['average_rate_pct'] ?? 0), 2)]) ?>
      </p>
      <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Active loans') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white"><?= (int)($loans['active_count'] ?? 0) ?></dd>
        </div>
        <div class="rounded-2xl bg-slate-900/5 p-3 dark:bg-slate-900/40">
          <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Finished loans') ?></dt>
          <dd class="text-lg font-semibold text-slate-900 dark:text-white"><?= (int)($loans['finished_count'] ?? 0) ?></dd>
        </div>
      </dl>
    </article>
  </section>

  <?php if ($recent): ?>
    <section class="card">
      <div class="card-kicker"><?= __('Recent activity') ?></div>
      <h2 class="card-title text-2xl"><?= __('Latest transactions at a glance') ?></h2>
      <p class="card-subtle mt-1 text-sm">
        <?= __('Amounts shown in %(currency)s. Personal notes are omitted for privacy.', ['currency' => $mainCurrency]) ?>
      </p>
      <div class="mt-4 overflow-hidden rounded-3xl border border-white/60 bg-white/80 shadow-sm dark:border-slate-800/70 dark:bg-slate-900/50">
        <table class="min-w-full divide-y divide-white/60 text-sm dark:divide-slate-800/70">
          <thead class="bg-slate-900/5 text-left uppercase tracking-wide text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3 font-semibold"><?= __('Date') ?></th>
              <th class="px-4 py-3 font-semibold"><?= __('Type') ?></th>
              <th class="px-4 py-3 font-semibold"><?= __('Category') ?></th>
              <th class="px-4 py-3 font-semibold text-right"><?= __('Amount') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $row): ?>
              <tr class="divide-x divide-white/60 text-slate-700 odd:bg-white/70 dark:divide-slate-800/70 dark:text-slate-200 dark:odd:bg-slate-900/40">
                <td class="px-4 py-3 whitespace-nowrap">
                  <?= htmlspecialchars($formatDate($row['date'] ?? null), ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <?= htmlspecialchars(ucfirst($row['kind'] ?? ''), ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3">
                  <?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-right font-semibold">
                  <?= $formatMoney((float)($row['amount_main'] ?? 0)) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
(function() {
  const form = document.getElementById('ai-insights-form');
  if (!form) return;
  const button = form.querySelector('button[type="submit"]');
  const label = button ? button.querySelector('[data-button-label]') : null;
  const defaultLabel = button ? button.dataset.labelDefault : '';
  const loadingLabel = button ? button.dataset.labelLoading : '';
  const output = document.getElementById('ai-insights-output');
  const status = document.getElementById('ai-insights-status');
  const error = document.getElementById('ai-insights-error');

  form.addEventListener('submit', function(event) {
    event.preventDefault();
    if (!button || !output) {
      return;
    }

    error.textContent = '';
    button.disabled = true;
    button.classList.add('opacity-80');
    button.setAttribute('aria-busy', 'true');
    if (label && loadingLabel) {
      label.textContent = loadingLabel;
    }

    const formData = new FormData(form);

    fetch('/api/ai-insights', {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(async (response) => {
        let payload;
        try {
          payload = await response.json();
        } catch (err) {
          throw new Error('<?= addslashes(__('The AI service returned an unexpected response.')) ?>');
        }

        if (!response.ok || !payload || payload.success !== true) {
          const message = payload && payload.error ? payload.error : '<?= addslashes(__('Unable to generate insights right now.')) ?>';
          throw new Error(message);
        }

        const text = typeof payload.suggestions === 'string' ? payload.suggestions.trim() : '';
        output.textContent = text || '<?= addslashes(__('No guidance was returned.')) ?>';
        if (status) {
          const generatedAt = payload.snapshot && payload.snapshot.generated_at ? payload.snapshot.generated_at : null;
          const timestamp = generatedAt ? new Date(generatedAt).toLocaleString() : new Date().toLocaleString();
          status.textContent = '<?= addslashes(__('Last generated')) ?> ' + timestamp;
        }
      })
      .catch((err) => {
        if (error) {
          error.textContent = err.message || '<?= addslashes(__('Unable to generate insights right now.')) ?>';
        }
      })
      .finally(() => {
        if (label && defaultLabel) {
          label.textContent = defaultLabel;
        }
        button.disabled = false;
        button.classList.remove('opacity-80');
        button.removeAttribute('aria-busy');
      });
  });
})();
</script>
