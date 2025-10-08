<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="relative isolate overflow-hidden bg-gray-950 text-white">
  <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.08),_transparent_65%)]"></div>
  <header class="border-b border-white/10 bg-transparent">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
          <img src="/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="h-8 w-8 object-contain" />
        </span>
        <span class="text-white/90"><?= htmlspecialchars($appName) ?></span>
      </a>
      <div class="hidden items-center gap-6 text-sm font-medium text-white/60 lg:flex">
        <a href="/" class="transition hover:text-white"><?= __('Home') ?></a>
        <a href="/login" class="transition hover:text-white"><?= __('Log in') ?></a>
        <a href="/register" class="transition hover:text-white"><?= __('Register') ?></a>
      </div>
      <div class="flex items-center gap-3 text-sm font-medium text-white/70">
        <a href="/login" class="rounded-full border border-white/20 px-4 py-2 transition hover:border-white hover:text-white">
          <?= __('Log in') ?>
        </a>
        <a href="/register" class="btn btn-primary hidden border border-transparent bg-white px-5 py-2 text-gray-900 hover:bg-gray-100 sm:inline-flex">
          <?= __('Start free trial') ?>
        </a>
      </div>
    </nav>
  </header>

  <section class="px-6 pb-24 pt-16">
    <div class="mx-auto max-w-4xl space-y-8 text-center">
      <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
        <?= __('Memberships designed for peace of mind') ?>
      </span>
      <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">
        <?= __('Choose the support that keeps your personal goals thriving.') ?>
      </h1>
      <p class="text-lg leading-relaxed text-white/70">
        <?= __('Every subscription unlocks the full MoneyMap experience—crafted for individual wellbeing, gentle accountability, and secure financial clarity.') ?>
      </p>
      <div class="flex flex-wrap justify-center gap-4">
        <a href="/register" class="btn btn-primary border border-transparent bg-white px-6 py-3 text-base font-semibold text-gray-900 hover:bg-gray-100">
          <?= __('Start your free trial') ?>
        </a>
        <a href="/" class="btn btn-ghost border-white/30 px-6 py-3 text-base text-white/80 hover:border-white hover:text-white"><?= __('Back to home') ?></a>
      </div>
      <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-semibold uppercase tracking-[0.28em] text-white/40">
        <span class="flex items-center gap-2"><i data-lucide="globe" class="h-4 w-4"></i><?= __('Available in English · Español · Magyar') ?></span>
        <span class="flex items-center gap-2"><i data-lucide="shield-check" class="h-4 w-4"></i><?= __('Encrypted sessions, biometric-friendly, and privacy by default.') ?></span>
      </div>
    </div>
  </section>
</div>

<section class="bg-white px-6 py-24">
  <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[1fr_1fr]">
    <article class="space-y-6 rounded-4xl border border-gray-200 bg-white p-10 shadow-sm">
      <header class="space-y-2">
        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Flexible monthly') ?></p>
        <h2 class="text-3xl font-semibold text-gray-900"><?= __('MoneyMap Plus') ?></h2>
        <p class="text-sm text-gray-600"><?= __('Perfect for exploring supportive routines with the freedom to pause anytime.') ?></p>
      </header>
      <div class="flex items-baseline gap-2 text-gray-900">
        <span class="text-5xl font-semibold">$14</span>
        <span class="text-sm font-semibold uppercase tracking-[0.3em] text-gray-400"><?= __('per month') ?></span>
      </div>
      <ul class="space-y-4 text-sm text-gray-600">
        <li class="flex gap-3">
          <span class="mt-1 text-gray-500"><i data-lucide="check" class="h-4 w-4"></i></span>
          <span><?= __('Unlimited accounts, mindful automations, and mood tracking.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1 text-gray-500"><i data-lucide="check" class="h-4 w-4"></i></span>
          <span><?= __('Weekly ritual planning with guided reflections and prompts.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1 text-gray-500"><i data-lucide="check" class="h-4 w-4"></i></span>
          <span><?= __('Share progress updates with a trusted partner or friend.') ?></span>
        </li>
      </ul>
      <div class="rounded-3xl border border-gray-200 bg-gray-50 p-6 text-sm text-gray-600">
        <p class="font-semibold text-gray-900"><?= __('Included wellbeing tools') ?></p>
        <ul class="mt-4 space-y-3">
          <li class="flex gap-3">
            <span class="mt-1 text-gray-500"><i data-lucide="sun" class="h-4 w-4"></i></span>
            <span><?= __('Morning focus and gratitude prompts synced to your budget view.') ?></span>
          </li>
          <li class="flex gap-3">
            <span class="mt-1 text-gray-500"><i data-lucide="smile-plus" class="h-4 w-4"></i></span>
            <span><?= __('Mood-aware nudges that reduce overspending stress.') ?></span>
          </li>
          <li class="flex gap-3">
            <span class="mt-1 text-gray-500"><i data-lucide="book" class="h-4 w-4"></i></span>
            <span><?= __('Reflection journal templates that store safely alongside your data.') ?></span>
          </li>
        </ul>
      </div>
      <a href="/register" class="btn btn-primary w-full justify-center border border-transparent bg-gray-900 py-3 text-base font-semibold text-white hover:bg-gray-800">
        <?= __('Begin monthly plan') ?>
      </a>
    </article>
    <article class="space-y-6 rounded-4xl border border-gray-900 bg-gray-900 p-10 text-white shadow-xl">
      <header class="space-y-2">
        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-white/60"><?= __('Best value yearly') ?></p>
        <h2 class="text-3xl font-semibold"><?= __('MoneyMap Plus Annual') ?></h2>
        <p class="text-sm text-white/70"><?= __('Save two months and deepen your rituals with seasonal guidance and exclusive resources.') ?></p>
      </header>
      <div class="flex items-baseline gap-2">
        <span class="text-5xl font-semibold">$140</span>
        <span class="text-sm font-semibold uppercase tracking-[0.3em] text-white/40"><?= __('per year') ?></span>
      </div>
      <ul class="space-y-4 text-sm text-white/70">
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="sparkles" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Seasonal wellbeing retreats and guided visualization sessions.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="calendar-heart" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Annual planning templates for travel, self-care, and future dreams.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="heart" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Quarterly gratitude recaps ready to share with loved ones.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="shield-check" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Priority encrypted support with proactive wellbeing check-ins.') ?></span>
        </li>
      </ul>
      <div class="rounded-3xl border border-gray-800 bg-gray-900 p-6 text-sm text-white/70">
        <p class="font-semibold text-white"><?= __('Annual exclusives') ?></p>
        <ul class="mt-4 space-y-3">
          <li class="flex gap-3">
            <span class="mt-1"><i data-lucide="gift" class="h-4 w-4 text-emerald-300"></i></span>
            <span><?= __('Curated intention kits mailed at the start of each season.') ?></span>
          </li>
          <li class="flex gap-3">
            <span class="mt-1"><i data-lucide="sparkles" class="h-4 w-4 text-emerald-300"></i></span>
            <span><?= __('Invite-only group reflections with MoneyMap coaches.') ?></span>
          </li>
          <li class="flex gap-3">
            <span class="mt-1"><i data-lucide="medal" class="h-4 w-4 text-emerald-300"></i></span>
            <span><?= __('Celebration badges for every major milestone you achieve.') ?></span>
          </li>
        </ul>
      </div>
      <a href="/register" class="btn btn-primary w-full justify-center border border-transparent bg-white py-3 text-base font-semibold text-gray-900 hover:bg-gray-100">
        <?= __('Begin annual plan') ?>
      </a>
    </article>
  </div>
</section>

<section class="bg-gray-50 px-6 py-24">
  <div class="mx-auto max-w-6xl space-y-12 rounded-4xl border border-gray-200 bg-white px-10 py-16 shadow-xl">
    <div class="space-y-4 text-center">
      <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-500">
        <?= __('What you receive') ?>
      </span>
      <h2 class="text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
        <?= __('Support that feels human, secure, and encouraging.') ?>
      </h2>
      <p class="text-base text-gray-600">
        <?= __('Every MoneyMap membership includes calm accountability, privacy-first infrastructure, and supportive rituals that keep your goals within reach.') ?>
      </p>
    </div>
    <div class="grid gap-8 md:grid-cols-3">
      <div class="space-y-3 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700">
          <i data-lucide="lock" class="h-5 w-5"></i>
        </span>
        <h3 class="text-lg font-semibold text-gray-900"><?= __('Bank-grade privacy') ?></h3>
        <p class="text-sm text-gray-600"><?= __('Encrypted data, passkey support, and export controls keep your personal finances protected.') ?></p>
      </div>
      <div class="space-y-3 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700">
          <i data-lucide="sparkles" class="h-5 w-5"></i>
        </span>
        <h3 class="text-lg font-semibold text-gray-900"><?= __('Joyful momentum') ?></h3>
        <p class="text-sm text-gray-600"><?= __('Habit tracking, celebration recaps, and mindful nudges keep progress exciting and kind.') ?></p>
      </div>
      <div class="space-y-3 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700">
          <i data-lucide="users" class="h-5 w-5"></i>
        </span>
        <h3 class="text-lg font-semibold text-gray-900"><?= __('Personalised guidance') ?></h3>
        <p class="text-sm text-gray-600"><?= __('Small-group circles and live chats with MoneyMap specialists who understand personal wellbeing goals.') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="bg-white px-6 py-24">
  <div class="mx-auto max-w-5xl space-y-8 text-center">
    <h2 class="text-3xl font-semibold tracking-tight text-gray-900">
      <?= __('Frequently asked questions') ?>
    </h2>
    <div class="space-y-4 text-left">
      <details class="group rounded-3xl border border-gray-200 bg-gray-50 p-6 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-gray-900">
          <?= __('Can I switch plans later?') ?>
          <span class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition group-open:border-gray-300 group-open:text-gray-500">
            <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
            <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
          </span>
        </summary>
        <p class="mt-3 text-sm text-gray-600"><?= __('Absolutely. Downgrade or upgrade anytime—your data and routines stay exactly as you leave them.') ?></p>
      </details>
      <details class="group rounded-3xl border border-gray-200 bg-gray-50 p-6 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-gray-900">
          <?= __('Is there a free trial?') ?>
          <span class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition group-open:border-gray-300 group-open:text-gray-500">
            <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
            <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
          </span>
        </summary>
        <p class="mt-3 text-sm text-gray-600"><?= __('Yes—enjoy all premium features for 14 days. We will remind you before the trial ends, no surprises.') ?></p>
      </details>
      <details class="group rounded-3xl border border-gray-200 bg-gray-50 p-6 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-gray-900">
          <?= __('What if I need help setting things up?') ?>
          <span class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition group-open:border-gray-300 group-open:text-gray-500">
            <i data-lucide="plus" class="h-4 w-4 group-open:hidden"></i>
            <i data-lucide="minus" class="hidden h-4 w-4 group-open:inline"></i>
          </span>
        </summary>
        <p class="mt-3 text-sm text-gray-600"><?= __('Our specialists and guided onboarding sessions will walk you through linking accounts, setting goals, and building automations that fit your lifestyle.') ?></p>
      </details>
    </div>
    <p class="text-sm text-gray-500">
      <?= __('Need a personalised walkthrough? Email the team and we will schedule a calm, private session.') ?>
    </p>
  </div>
</section>
