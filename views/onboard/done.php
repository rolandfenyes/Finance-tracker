<?php include __DIR__ . '/_progress.php'; ?>

<section class="max-w-4xl mx-auto space-y-8">
  <div class="card text-center space-y-4">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-500/15 text-brand-600">
      <i data-lucide="party-popper" class="h-7 w-7"></i>
    </div>
    <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
      <?= __('You’re all set! 🎉') ?>
    </h1>
    <p class="text-base text-gray-600 dark:text-gray-300 leading-relaxed">
      <?= __('Nice work—your profile, cashflow rules, currencies, categories, and incomes are ready. From here on, every transaction you log fuels insights tailored to your goals.') ?>
    </p>
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
      <a href="/" class="btn btn-primary px-6">
        <?= __('Go to my dashboard') ?>
      </a>
      <a href="/tutorial" class="btn btn-ghost px-6">
        <?= __('Start the guided tour') ?>
      </a>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-3">
    <div class="panel">
      <div class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <i data-lucide="notebook-text" class="h-4 w-4"></i>
        <?= __('What’s next') ?>
      </div>
      <ul class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
        <li><?= __('Log your first expense from the dashboard widget.') ?></li>
        <li><?= __('Set a goal or emergency fund target when you’re ready.') ?></li>
        <li><?= __('Invite a partner by sharing your dashboard export or insights.') ?></li>
      </ul>
    </div>
    <div class="panel">
      <div class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <i data-lucide="settings" class="h-4 w-4"></i>
        <?= __('Keep refining') ?>
      </div>
      <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
        <?= __('Need to adjust categories, currencies, or cashflow rules? Visit Settings anytime—everything you set up here can evolve with you.') ?>
      </p>
    </div>
    <div class="panel">
      <div class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <i data-lucide="sparkles" class="h-4 w-4"></i>
        <?= __('Stay inspired') ?>
      </div>
      <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
        <?= __('Bookmark your dashboard. Checking in regularly keeps momentum strong and helps you celebrate wins sooner.') ?>
      </p>
    </div>
  </div>

  <p class="text-center text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
    <?= __('Need the onboarding tutorial later? Find it in the help menu anytime.') ?>
  </p>
</section>
