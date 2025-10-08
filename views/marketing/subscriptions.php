<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="space-y-20 bg-gradient-to-b from-white via-[#f5f9ff] to-white pb-24">
  <header class="mt-12 text-center space-y-5 px-6">
    <p class="chip mx-auto bg-sky-50 text-sky-600"><?= __('Memberships designed for peace of mind') ?></p>
    <h1 class="mx-auto max-w-4xl text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
      <?= __('Choose the support that keeps your personal goals thriving.') ?>
    </h1>
    <p class="mx-auto max-w-2xl text-lg text-slate-600">
      <?= __('Every subscription unlocks the full MoneyMap experience—crafted for individual wellbeing, gentle accountability, and secure financial clarity.') ?>
    </p>
    <div class="flex justify-center gap-4">
      <a href="/register" class="btn btn-primary px-6 py-3 text-base bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500">
        <?= __('Start your free trial') ?>
      </a>
      <a href="/" class="btn btn-ghost px-6 py-3 text-base"><?= __('Back to home') ?></a>
    </div>
  </header>

  <section class="px-6">
    <div class="mx-auto max-w-5xl grid gap-6 lg:grid-cols-[1.05fr_minmax(0,0.95fr)]">
      <article class="space-y-6 rounded-[2.5rem] border border-slate-200 bg-white p-10 shadow-lg">
        <header class="space-y-2">
          <p class="chip bg-emerald-50 text-emerald-600 w-fit"><?= __('Flexible monthly') ?></p>
          <h2 class="text-3xl font-semibold text-slate-900"><?= __('MoneyMap Plus') ?></h2>
          <p class="text-sm text-slate-600"><?= __('Perfect for exploring supportive routines with the freedom to pause anytime.') ?></p>
        </header>
        <div class="flex items-baseline gap-2 text-slate-900">
          <span class="text-5xl font-semibold">$14</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per month') ?></span>
        </div>
        <ul class="space-y-4 text-sm text-slate-600">
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Unlimited accounts, mindful automations, and mood tracking.') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Weekly ritual planning with guided reflections and prompts.') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
            <span><?= __('Share progress updates with a trusted partner or friend.') ?></span>
          </li>
        </ul>
        <div class="space-y-4 rounded-2xl border border-slate-100 bg-slate-50/60 p-6 text-sm text-slate-600">
          <p class="font-semibold text-slate-900"><?= __('Included wellbeing tools') ?></p>
          <ul class="space-y-3">
            <li class="flex items-start gap-3">
              <span class="mt-1 text-sky-500"><i data-lucide="sun" class="h-4 w-4"></i></span>
              <span><?= __('Morning focus and gratitude prompts synced to your budget view.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-sky-500"><i data-lucide="smile-plus" class="h-4 w-4"></i></span>
              <span><?= __('Mood-aware nudges that reduce overspending stress.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-sky-500"><i data-lucide="book" class="h-4 w-4"></i></span>
              <span><?= __('Reflection journal templates that store safely alongside your data.') ?></span>
            </li>
          </ul>
        </div>
        <a href="/register" class="btn btn-primary w-full justify-center text-base bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500">
          <?= __('Begin monthly plan') ?>
        </a>
      </article>
      <article class="space-y-6 rounded-[2.5rem] border border-sky-200 bg-gradient-to-br from-white via-sky-50 to-emerald-50 p-10 shadow-xl">
        <header class="space-y-2">
          <p class="chip bg-sky-50 text-sky-600 w-fit"><?= __('Best value yearly') ?></p>
          <h2 class="text-3xl font-semibold text-slate-900"><?= __('MoneyMap Plus Annual') ?></h2>
          <p class="text-sm text-slate-600"><?= __('Save two months and deepen your rituals with seasonal guidance and exclusive resources.') ?></p>
        </header>
        <div class="flex items-baseline gap-2 text-slate-900">
          <span class="text-5xl font-semibold">$140</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per year') ?></span>
        </div>
        <ul class="space-y-4 text-sm text-slate-600">
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="sparkles" class="h-4 w-4"></i></span>
            <span><?= __('Seasonal wellbeing retreats and guided visualization sessions.') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="calendar-heart" class="h-4 w-4"></i></span>
            <span><?= __('Annual planning templates for travel, self-care, and future dreams.') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="heart" class="h-4 w-4"></i></span>
            <span><?= __('Quarterly gratitude recaps ready to share with loved ones.') ?></span>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-1 text-sky-500"><i data-lucide="shield-check" class="h-4 w-4"></i></span>
            <span><?= __('Priority encrypted support with proactive wellbeing check-ins.') ?></span>
          </li>
        </ul>
        <div class="space-y-4 rounded-2xl border border-sky-100 bg-white/70 p-6 text-sm text-slate-600">
          <p class="font-semibold text-slate-900"><?= __('Annual exclusives') ?></p>
          <ul class="space-y-3">
            <li class="flex items-start gap-3">
              <span class="mt-1 text-emerald-500"><i data-lucide="gift" class="h-4 w-4"></i></span>
              <span><?= __('Curated intention kits mailed at the start of each season.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-emerald-500"><i data-lucide="sparkles" class="h-4 w-4"></i></span>
              <span><?= __('Invite-only group reflections with MoneyMap coaches.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-emerald-500"><i data-lucide="medal" class="h-4 w-4"></i></span>
              <span><?= __('Celebration badges for every major milestone you achieve.') ?></span>
            </li>
          </ul>
        </div>
        <a href="/register" class="btn btn-primary w-full justify-center text-base bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500">
          <?= __('Begin annual plan') ?>
        </a>
      </article>
    </div>
  </section>

  <section class="px-6">
    <div class="mx-auto max-w-4xl space-y-12 rounded-[2.5rem] border border-slate-200 bg-white px-10 py-14 shadow-xl">
      <div class="space-y-3 text-center">
        <p class="chip mx-auto bg-amber-50 text-amber-600"><?= __('What you receive') ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
          <?= __('Support that feels human, secure, and encouraging.') ?>
        </h2>
      </div>
      <div class="grid gap-8 md:grid-cols-3">
        <div class="space-y-3 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 text-sky-600">
            <i data-lucide="lock" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Bank-grade privacy') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Encrypted data, passkey support, and export controls keep your personal finances protected.') ?></p>
        </div>
        <div class="space-y-3 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <i data-lucide="sparkles" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Joyful momentum') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Habit tracking, celebration recaps, and mindful nudges keep progress exciting and kind.') ?></p>
        </div>
        <div class="space-y-3 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-500">
            <i data-lucide="users" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Personalised guidance') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Small-group circles and live chats with MoneyMap specialists who understand personal wellbeing goals.') ?></p>
        </div>
      </div>
    </div>
  </section>

  <section class="px-6">
    <div class="mx-auto max-w-4xl space-y-6">
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900 text-center">
        <?= __('Frequently asked questions') ?>
      </h2>
      <div class="space-y-4">
        <details class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900">
            <?= __('Can I switch plans later?') ?>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-open:border-slate-300 group-open:text-slate-500">
              <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
              <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
            </span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?= __('Absolutely. Downgrade or upgrade anytime—your data and routines stay exactly as you leave them.') ?></p>
        </details>
        <details class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900">
            <?= __('Is there a free trial?') ?>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-open:border-slate-300 group-open:text-slate-500">
              <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
              <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
            </span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?= __('Yes—enjoy all premium features for 14 days. We will remind you before the trial ends, no surprises.') ?></p>
        </details>
        <details class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900">
            <?= __('What if I need help setting things up?') ?>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-open:border-slate-300 group-open:text-slate-500">
              <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
              <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
            </span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?= __('Our specialists and guided onboarding sessions will walk you through linking accounts, setting goals, and building automations that fit your lifestyle.') ?></p>
        </details>
      </div>
      <p class="text-center text-sm text-slate-500">
        <?= __('Need a personalised walkthrough? Email the team and we will schedule a calm, private session.') ?>
      </p>
    </div>
  </section>
</div>
