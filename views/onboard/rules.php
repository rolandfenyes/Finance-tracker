<?php include __DIR__ . '/_progress.php'; ?>

<section class="max-w-6xl mx-auto space-y-8">
  <div class="card grid gap-6 md:grid-cols-2">
    <div>
      <div class="card-kicker"><?= __('Automate your plan') ?></div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
        <?= __('Set your cashflow rules') ?>
      </h1>
      <p class="mt-3 text-base text-gray-600 dark:text-gray-300 leading-relaxed">
        <?= __('Tell MyMoneyMap how to automatically split every paycheque. Start with the classic 50/30/20 rule or tweak the labels to match your goals.') ?>
      </p>
      <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="target" class="h-3.5 w-3.5"></i></span>
          <span><?= __('These rules power budget recommendations and upcoming alerts.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Percentages automatically scale with your income each month.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="clock" class="h-3.5 w-3.5"></i></span>
          <span><?= __('You can refine or add more buckets later from Settings → Cashflow.') ?></span>
        </li>
      </ul>
    </div>
    <div class="rounded-3xl border border-brand-200/60 bg-brand-50/50 p-6 shadow-inner dark:border-brand-500/30 dark:bg-brand-500/10">
      <h2 class="text-sm font-semibold uppercase tracking-[0.28em] text-brand-600 dark:text-brand-200"><?= __('Suggested split') ?></h2>
      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-white/70 bg-white/90 p-4 shadow-sm dark:border-slate-700/60 dark:bg-slate-900/60">
          <div class="text-sm font-semibold text-gray-800 dark:text-gray-100"><?= __('Needs') ?></div>
          <p class="text-xs text-gray-500 dark:text-gray-300">
            <?= __('Rent, groceries, utilities') ?>
          </p>
          <span class="mt-3 inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-200">
            <?= __('50%') ?>
          </span>
        </div>
        <div class="rounded-2xl border border-white/70 bg-white/90 p-4 shadow-sm dark:border-slate-700/60 dark:bg-slate-900/60">
          <div class="text-sm font-semibold text-gray-800 dark:text-gray-100"><?= __('Wants') ?></div>
          <p class="text-xs text-gray-500 dark:text-gray-300">
            <?= __('Dining out, fun, experiences') ?>
          </p>
          <span class="mt-3 inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-200">
            <?= __('30%') ?>
          </span>
        </div>
        <div class="rounded-2xl border border-white/70 bg-white/90 p-4 shadow-sm dark:border-slate-700/60 dark:bg-slate-900/60">
          <div class="text-sm font-semibold text-gray-800 dark:text-gray-100"><?= __('Savings & debt') ?></div>
          <p class="text-xs text-gray-500 dark:text-gray-300">
            <?= __('Emergency fund, investments, loans') ?>
          </p>
          <span class="mt-3 inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-200">
            <?= __('20%') ?>
          </span>
        </div>
      </div>
      <p class="mt-4 text-xs text-brand-700 dark:text-brand-200">
        <?= __('Tweak the percentages below until your plan totals 100%.') ?>
      </p>
    </div>
  </div>

  <form method="post" action="/onboard/rules" class="card space-y-6" id="cashflow-rules-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

    <div class="space-y-4">
      <?php foreach ([[__('Needs'),50],[__('Wants'),30],[__('Savings/Debt'),20]] as $i=>$s): ?>
        <div class="grid gap-3 rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/50 md:grid-cols-12">
          <div class="md:col-span-7">
            <label class="label" for="rule-label-<?= $i ?>"><?= __('Bucket name') ?></label>
            <input class="input" id="rule-label-<?= $i ?>" name="rules[<?= $i ?>][label]" value="<?= htmlspecialchars($s[0]) ?>" placeholder="<?= __('e.g. Essentials') ?>" />
          </div>
          <div class="md:col-span-5">
            <label class="label" for="rule-percent-<?= $i ?>"><?= __('% of income') ?></label>
            <div class="input-group">
              <input class="ig-input" id="rule-percent-<?= $i ?>" type="number" min="0" step="0.1" name="rules[<?= $i ?>][percent]" value="<?= (float)$s[1] ?>" data-rule-percent />
              <span class="inline-flex items-center px-3 text-xs font-semibold uppercase tracking-wide text-brand-600">%</span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="rounded-3xl border border-brand-200/70 bg-brand-50/70 px-4 py-3 text-sm text-brand-700 shadow-inner dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-100" data-rule-summary>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <span class="font-semibold uppercase tracking-wide text-xs text-brand-600 dark:text-brand-200"><?= __('Total allocation') ?></span>
          <div class="mt-1 text-lg font-semibold" data-rule-total>100%</div>
        </div>
        <div class="sm:text-right" data-rule-feedback aria-live="polite">
          <?= __('Great! You’re assigning your full income.') ?>
        </div>
      </div>
      <div class="mt-3 h-2 rounded-full bg-brand-100/70 dark:bg-brand-500/20">
        <div class="h-2 rounded-full bg-brand-500 transition-all duration-300" data-rule-meter style="width: 100%"></div>
      </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <a href="/onboard/theme" class="btn btn-ghost">
        <?= __('Back to themes') ?>
      </a>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
          <?= __('Need more time? You can edit this later.') ?>
        </span>
        <button class="btn btn-primary">
          <?= __('Save and continue') ?>
        </button>
      </div>
    </div>
  </form>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cashflow-rules-form');
    if (!form) return;
    const inputs = Array.from(form.querySelectorAll('[data-rule-percent]'));
    const totalEl = form.querySelector('[data-rule-total]');
    const feedbackEl = form.querySelector('[data-rule-feedback]');
    const meter = form.querySelector('[data-rule-meter]');

    const format = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });

    function updateSummary() {
      let total = 0;
      inputs.forEach(input => {
        const value = parseFloat(input.value);
        if (!Number.isNaN(value)) {
          total += value;
        }
      });

      if (totalEl) {
        totalEl.textContent = format.format(total) + '%';
      }

      if (meter) {
        const width = Math.max(0, Math.min(100, total));
        meter.style.width = width + '%';
      }

      if (feedbackEl) {
        const missing = Math.round((100 - total) * 10) / 10;
        const over = Math.round((total - 100) * 10) / 10;
        if (Math.abs(total - 100) < 0.1) {
          feedbackEl.textContent = <?= json_encode(__('Perfect balance!')) ?>;
        } else if (total < 100) {
          feedbackEl.textContent = <?= json_encode(__('You still have :percent% to assign.', ['percent' => ':percent'])) ?>.replace(':percent', format.format(missing));
        } else {
          feedbackEl.textContent = <?= json_encode(__('You are allocating :percent% over your income.', ['percent' => ':percent'])) ?>.replace(':percent', format.format(over));
        }
      }
    }

    inputs.forEach(input => {
      input.addEventListener('input', updateSummary);
      input.addEventListener('blur', () => {
        const value = parseFloat(input.value);
        if (Number.isNaN(value) || value < 0) {
          input.value = '0';
        }
        updateSummary();
      });
    });

    updateSummary();
  });
</script>
