<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="space-y-20 bg-gradient-to-b from-white via-sky-50/40 to-white pb-24">
  <header class="mt-10 text-center space-y-4">
    <p class="chip mx-auto bg-sky-50 text-sky-600"><?= __('Subscriptions') ?></p>
    <h1 class="text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
      <?= __('Choose a rhythm that fits your life') ?>
    </h1>
    <p class="mx-auto max-w-3xl text-lg text-slate-600">
      <?= __('Every plan unlocks the full MoneyMap experience—designed to keep you calm, confident, and connected to the goals that matter most.') ?>
    </p>
    <div class="flex justify-center gap-4">
      <a href="/register" class="btn btn-primary px-6 py-3 text-base"><?= __('Start your free trial') ?></a>
      <a href="/" class="btn btn-ghost px-6 py-3 text-base"><?= __('Back to home') ?></a>
    </div>
  </header>

  <section class="px-6">
    <div class="mx-auto max-w-5xl grid gap-6 lg:grid-cols-2">
      <article class="panel space-y-5 border border-sky-100 bg-white shadow-lg">
        <header class="space-y-2">
          <p class="chip bg-emerald-50 text-emerald-600 w-fit"><?= __('Monthly plan') ?></p>
          <h2 class="text-2xl font-semibold text-slate-900"><?= __('MoneyMap Plus') ?></h2>
          <p class="text-sm text-slate-600">
            <?= __('Flexible commitment with all features, mindful automations, and ongoing inspiration from our community.') ?>
          </p>
        </header>
        <div class="flex items-baseline gap-2 text-slate-900">
          <span class="text-4xl font-semibold">$14</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per month') ?></span>
        </div>
        <ul class="space-y-3 text-sm text-slate-600">
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Unlimited accounts, mindful automations, guided reflections, and community workshops.') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Share progress with someone you trust') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Weekly reflection prompts and guided check-ins') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Priority human support under 12 hours') ?></span>
          </li>
        </ul>
        <a href="/register" class="btn btn-primary w-full justify-center text-base"><?= __('Start monthly') ?></a>
      </article>
      <article class="panel space-y-5 border border-emerald-100 bg-white shadow-lg">
        <header class="space-y-2">
          <p class="chip bg-sky-50 text-sky-600 w-fit"><?= __('Annual plan') ?></p>
          <h2 class="text-2xl font-semibold text-slate-900"><?= __('MoneyMap Plus Annual') ?></h2>
          <p class="text-sm text-slate-600">
            <?= __('Best value for steady planners. Save two months and receive seasonal wellbeing kits to keep your routine inspiring.') ?>
          </p>
        </header>
        <div class="flex items-baseline gap-2 text-slate-900">
          <span class="text-4xl font-semibold">$140</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per year') ?></span>
        </div>
        <ul class="space-y-3 text-sm text-slate-600">
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="sparkles" class="h-4 w-4"></i></span>
            <span><?= __('Seasonal wellbeing workshops included') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="gift" class="h-4 w-4"></i></span>
            <span><?= __('Exclusive goal templates and journaling prompts') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="calendar-range" class="h-4 w-4"></i></span>
            <span><?= __('Plan with confidence all year long') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="heart" class="h-4 w-4"></i></span>
            <span><?= __('Shareable gratitude recap each quarter') ?></span>
          </li>
        </ul>
        <a href="/register" class="btn btn-primary w-full justify-center text-base"><?= __('Start annual') ?></a>
      </article>
    </div>
  </section>

  <section class="px-6">
    <div class="mx-auto max-w-4xl space-y-10 rounded-[2.5rem] border border-sky-100 bg-white px-8 py-12 shadow-xl">
      <h2 class="text-3xl font-semibold text-slate-900 text-center">
        <?= __('Support for your whole wellbeing journey') ?>
      </h2>
      <div class="grid gap-8 md:grid-cols-3">
        <div class="space-y-3 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 text-sky-600">
            <i data-lucide="brain" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Mindful insights') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Track feelings beside your finances to notice patterns and shift habits with compassion.') ?></p>
        </div>
        <div class="space-y-3 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <i data-lucide="users" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Community circles') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Small-group sessions help you learn new strategies, celebrate wins, and stay accountable.') ?></p>
        </div>
        <div class="space-y-3 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-500">
            <i data-lucide="sparkles" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Joyful celebrations') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Milestone badges, gratitude recaps, and intention check-ins keep progress uplifting.') ?></p>
        </div>
      </div>
    </div>
  </section>

  <section class="px-6">
    <div class="mx-auto max-w-4xl space-y-6">
      <h2 class="text-3xl font-semibold text-slate-900 text-center">
        <?= __('Frequently asked questions') ?>
      </h2>
      <div class="space-y-4">
        <details class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900">
            <?= __('Can I switch plans later?') ?>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-open:border-slate-300 group-open:text-slate-500">
              <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
              <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
            </span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?= __('Absolutely. Downgrade or upgrade anytime—your data and routines stay exactly as you leave them.') ?></p>
        </details>
        <details class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900">
            <?= __('Is there a free trial?') ?>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-open:border-slate-300 group-open:text-slate-500">
              <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
              <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
            </span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?= __('Yes—enjoy all premium features for 14 days. We’ll remind you before the trial ends, no surprises.') ?></p>
        </details>
        <details class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900">
            <?= __('What if I need help setting things up?') ?>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-open:border-slate-300 group-open:text-slate-500">
              <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
              <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
            </span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?= __('Our support team and guided onboarding sessions will walk you through linking accounts, setting goals, and building automations that fit your lifestyle.') ?></p>
        </details>
      </div>
    </div>
  </section>
</div>
