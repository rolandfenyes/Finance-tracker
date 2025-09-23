<?php
$onboardSteps = [
  ['slug' => 'theme',      'title' => __('Choose a theme')],
  ['slug' => 'rules',      'title' => __('Set cashflow rules')],
  ['slug' => 'currencies', 'title' => __('Add currencies')],
  ['slug' => 'categories', 'title' => __('Create categories')],
  ['slug' => 'income',     'title' => __('Add income')],
  ['slug' => 'done',       'title' => __('Celebrate')],
];
$totalSteps = count($onboardSteps);
$current = (int)($currentStep ?? 1);
if ($current < 1) {
  $current = 1;
}
if ($current > $totalSteps) {
  $current = $totalSteps;
}
$progressPercent = $totalSteps > 1
  ? (($current - 1) / ($totalSteps - 1)) * 100
  : 100;
$progressPercent = max(0, min(100, $progressPercent));
?>

<div class="mb-8">
  <div class="rounded-3xl border border-white/60 bg-white/70 p-6 shadow-brand-glow backdrop-blur-xs dark:border-slate-800/70 dark:bg-slate-900/60">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-brand-700 dark:text-brand-200">
          <?= __('Onboarding progress') ?>
        </p>
        <h2 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
          <?= __('Step :current of :total', ['current' => $current, 'total' => $totalSteps]) ?>
        </h2>
      </div>
      <div class="min-w-[140px] rounded-2xl border border-brand-200/60 bg-brand-50/70 px-4 py-2 text-sm font-medium text-brand-700 shadow-sm dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-200">
        <span class="flex items-center gap-2">
          <i data-lucide="sparkles" class="h-4 w-4"></i>
          <?= $current === $totalSteps ? __('Ready to launch') : __('Keep going!') ?>
        </span>
      </div>
    </div>

    <div class="mt-4 h-2 rounded-full bg-gray-200/80 dark:bg-slate-800">
      <div class="h-2 rounded-full bg-brand-500 transition-all duration-500" style="width: <?= number_format($progressPercent, 2, '.', '') ?>%"></div>
    </div>

    <ol class="mt-4 grid gap-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300 sm:grid-cols-3 lg:grid-cols-6">
      <?php foreach ($onboardSteps as $index => $step):
        $stepNumber = $index + 1;
        $isActive = $stepNumber === $current;
        $isCompleted = $stepNumber < $current;
      ?>
        <li class="flex items-center gap-3 rounded-2xl border px-3 py-2 transition-all duration-200
          <?= $isActive
              ? 'border-brand-400/80 bg-brand-50/80 text-brand-700 shadow-sm dark:border-brand-500/60 dark:bg-brand-500/10 dark:text-brand-200'
              : ($isCompleted
                  ? 'border-brand-200/70 bg-white/80 text-brand-600 dark:border-brand-500/30 dark:bg-slate-900/40 dark:text-brand-100'
                  : 'border-white/70 bg-white/60 text-gray-500 dark:border-slate-800/70 dark:bg-slate-900/40 dark:text-gray-300')
          ?>">
          <span class="flex h-7 w-7 items-center justify-center rounded-full border text-xs font-bold
            <?= $isCompleted
                ? 'border-brand-400 bg-brand-100 text-brand-700 dark:border-brand-400/60 dark:bg-brand-500/20 dark:text-brand-200'
                : ($isActive
                    ? 'border-brand-500 bg-brand-500 text-white shadow-sm'
                    : 'border-gray-300 bg-white text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-gray-300')
            ?>">
            <?= $isCompleted ? '✓' : $stepNumber ?>
          </span>
          <span class="leading-tight">
            <?= htmlspecialchars($step['title']) ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</div>
