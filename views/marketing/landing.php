<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="relative isolate overflow-hidden bg-gray-950 text-white">
  <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.08),_transparent_60%)]"></div>
  <header class="border-b border-white/10 bg-transparent">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
          <img src="/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="h-8 w-8 object-contain" />
        </span>
        <span class="text-white/90"><?= htmlspecialchars($appName) ?></span>
      </a>
      <div class="hidden items-center gap-6 text-sm font-medium text-white/60 lg:flex">
        <a href="#features" class="transition hover:text-white"><?= __('Features') ?></a>
        <a href="#confidence" class="transition hover:text-white"><?= __('Confidence') ?></a>
        <a href="#rituals" class="transition hover:text-white"><?= __('Rituals') ?></a>
        <a href="#stories" class="transition hover:text-white"><?= __('Stories') ?></a>
        <a href="#pricing" class="transition hover:text-white"><?= __('Pricing') ?></a>
        <a href="/subscriptions" class="transition hover:text-white"><?= __('Subscriptions') ?></a>
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

  <section class="px-6 pb-20 pt-16">
    <div class="mx-auto grid max-w-6xl gap-16 lg:grid-cols-[1.15fr_minmax(0,0.85fr)] lg:items-center">
      <div class="space-y-8">
        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
          <?= __('Personal wellbeing finance') ?>
        </span>
        <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">
          <?= __('Feel certain about your personal money path.') ?>
        </h1>
        <p class="text-lg leading-relaxed text-white/70">
          <?= __('MoneyMap keeps everyday finances calm and intentional. Build nourishing routines, celebrate the progress you feel, and keep every goal in clear, confident view.') ?>
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="/register" class="btn btn-primary text-base px-6 py-3 bg-white text-gray-900 hover:bg-gray-100">
            <?= __('Create your account') ?>
          </a>
          <a href="#login-card" class="btn btn-ghost text-base px-6 py-3 border-white/30 text-white/80 hover:border-white hover:text-white">
            <?= __('Sign in securely') ?>
          </a>
        </div>
        <dl class="grid gap-6 sm:grid-cols-3">
          <div class="space-y-1 rounded-2xl border border-white/10 bg-white/5 p-5">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-white/50"><?= __('Trusted members') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-white">
              <i data-lucide="users" class="h-6 w-6 text-emerald-300"></i>
              120K+
            </dd>
            <p class="text-xs text-white/60"><?= __('Protected with bank-grade security and gentle reminders.') ?></p>
          </div>
          <div class="space-y-1 rounded-2xl border border-white/10 bg-white/5 p-5">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-white/50"><?= __('Goals celebrated') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-white">
              <i data-lucide="sparkles" class="h-6 w-6 text-emerald-300"></i>
              460K
            </dd>
            <p class="text-xs text-white/60"><?= __('Every win is saved to your personal gratitude timeline.') ?></p>
          </div>
          <div class="space-y-1 rounded-2xl border border-white/10 bg-white/5 p-5">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-white/50"><?= __('Average calm score') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-white">
              <i data-lucide="smile" class="h-6 w-6 text-emerald-300"></i>
              9.4/10
            </dd>
            <p class="text-xs text-white/60"><?= __('Members feel more present with their money habits.') ?></p>
          </div>
        </dl>
        <div class="flex flex-wrap items-center gap-4 text-xs font-semibold uppercase tracking-[0.3em] text-white/40">
          <span class="flex items-center gap-2">
            <i data-lucide="globe" class="h-4 w-4"></i>
            <?= __('Available in English · Español · Magyar') ?>
          </span>
          <span class="hidden items-center gap-2 sm:flex">
            <i data-lucide="shield-check" class="h-4 w-4"></i>
            <?= __('Encrypted sessions, biometric-friendly, and privacy by default.') ?>
          </span>
        </div>
      </div>
      <div id="login-card" class="lg:pl-10">
        <div class="space-y-6 rounded-3xl border border-white/15 bg-white/5 p-10 shadow-[0_30px_80px_rgba(15,15,35,0.45)] backdrop-blur">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-white/50"><?= __('Client access') ?></p>
            <h2 class="mt-3 text-2xl font-semibold text-white"><?= __('Log in securely') ?></h2>
            <p class="mt-3 text-sm text-white/60"><?= __('Encrypted sessions, biometric-friendly, and privacy by default.') ?></p>
          </div>
          <form method="post" action="/login" class="space-y-5" novalidate>
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <div class="field">
              <label class="label mb-1 text-white/70"><?= __('Email') ?></label>
              <div class="relative">
                <input
                  type="email"
                  name="email"
                  class="input border-white/20 bg-white/10 text-white placeholder:text-white/40 focus:border-white focus:ring-white/80 pl-11"
                  placeholder="<?= __('you@example.com') ?>"
                  autocomplete="email"
                  required
                />
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-white/40">
                  <i data-lucide="mail" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <div class="field">
              <div class="mb-1 flex items-center justify-between text-white/70">
                <label class="label text-white/70"><?= __('Password') ?></label>
                <a href="/forgot" class="text-xs font-semibold text-white/60 hover:text-white"><?= __('Forgot?') ?></a>
              </div>
              <div class="relative">
                <input
                  type="password"
                  name="password"
                  class="input border-white/20 bg-white/10 text-white placeholder:text-white/40 focus:border-white focus:ring-white/80 pl-11"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  required
                />
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-white/40">
                  <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-white/70">
              <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-white/20 bg-white/5 text-white focus:ring-white/70" />
              <span><?= __('Keep me signed in') ?></span>
            </label>
            <button class="btn btn-primary w-full justify-center border border-transparent bg-white py-3 text-base font-semibold text-gray-900 hover:bg-gray-100">
              <?= __('Log in securely') ?>
            </button>
            <p class="text-center text-xs text-white/40"><?= __('By continuing you agree to the Terms & Privacy Policy.') ?></p>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<section id="features" class="bg-white px-6 py-24">
  <div class="mx-auto max-w-6xl space-y-14">
    <div class="max-w-3xl space-y-4">
      <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-500">
        <?= __('Why people choose :app', ['app' => $appName]) ?>
      </span>
      <h2 class="text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">
        <?= __('Designed for personal goals, with calm technology you can trust.') ?>
      </h2>
      <p class="text-lg text-gray-600">
        <?= __('Every feature blends gentle guidance with private, secure automation so you can focus on the progress that matters to you and your loved ones.') ?>
      </p>
    </div>
    <div class="grid gap-8 md:grid-cols-3">
      <article class="space-y-4 rounded-3xl border border-gray-200 bg-gray-50 p-8 shadow-sm">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-900/10 text-gray-900">
          <i data-lucide="layout-grid" class="h-5 w-5"></i>
        </span>
        <h3 class="text-xl font-semibold text-gray-900"><?= __('One home for everyday finances') ?></h3>
        <p class="text-sm text-gray-600">
          <?= __('Link banks, cards, and savings pots securely. Elegant charts and trends make it simple to understand how each decision supports your life.') ?>
        </p>
      </article>
      <article class="space-y-4 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-900/10 text-gray-900">
          <i data-lucide="piggy-bank" class="h-5 w-5"></i>
        </span>
        <h3 class="text-xl font-semibold text-gray-900"><?= __('Savings that feel encouraging') ?></h3>
        <p class="text-sm text-gray-600">
          <?= __('Set intentions, track feelings, and receive gentle nudges that keep your personal wellbeing tied to every savings milestone.') ?>
        </p>
      </article>
      <article class="space-y-4 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-900/10 text-gray-900">
          <i data-lucide="notebook-text" class="h-5 w-5"></i>
        </span>
        <h3 class="text-xl font-semibold text-gray-900"><?= __('Reflections beside the numbers') ?></h3>
        <p class="text-sm text-gray-600">
          <?= __('Journaling prompts and mood check-ins live next to your transactions so you can notice patterns and celebrate growth without stress.') ?>
        </p>
      </article>
    </div>
  </div>
</section>

<section id="confidence" class="bg-gray-50 px-6 py-24">
  <div class="mx-auto grid max-w-6xl gap-12 rounded-4xl border border-gray-200 bg-white px-10 py-16 shadow-xl lg:grid-cols-[1.1fr_minmax(0,0.9fr)]">
    <div class="space-y-5">
      <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-500">
        <?= __('Trusted foundations') ?>
      </span>
      <h2 class="text-4xl font-semibold tracking-tight text-gray-900">
        <?= __('Built with the same controls we rely on for our own finances.') ?>
      </h2>
      <p class="text-base text-gray-600">
        <?= __('From read-only banking connections to granular device approvals, every layer of :app is engineered for resilience.', ['app' => $appName]) ?>
      </p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2 rounded-3xl border border-gray-200 bg-gray-50 p-6">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
            <i data-lucide="lock" class="h-5 w-5"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900"><?= __('Bank-grade encryption') ?></h3>
          <p class="text-sm text-gray-600"><?= __('Data is encrypted in transit and at rest with monitored access policies.') ?></p>
        </div>
        <div class="space-y-2 rounded-3xl border border-gray-200 bg-gray-50 p-6">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
            <i data-lucide="shield-check" class="h-5 w-5"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900"><?= __('Granular approvals') ?></h3>
          <p class="text-sm text-gray-600"><?= __('Decide which devices, passkeys, and automations stay connected at any moment.') ?></p>
        </div>
        <div class="space-y-2 rounded-3xl border border-gray-200 bg-gray-50 p-6">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
            <i data-lucide="fingerprint" class="h-5 w-5"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900"><?= __('Biometric-ready sessions') ?></h3>
          <p class="text-sm text-gray-600"><?= __('Instant approvals from your trusted devices with session integrity monitoring.') ?></p>
        </div>
        <div class="space-y-2 rounded-3xl border border-gray-200 bg-gray-50 p-6">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
            <i data-lucide="file-lock" class="h-5 w-5"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900"><?= __('Export and erasure controls') ?></h3>
          <p class="text-sm text-gray-600"><?= __('Download structured data or request deletion in a few guided steps whenever you need.') ?></p>
        </div>
      </div>
    </div>
    <div class="space-y-8 rounded-3xl border border-gray-200 bg-gray-900 p-10 text-white shadow-lg">
      <div class="space-y-3">
        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-white/50"><?= __('Secure rituals, human tone') ?></p>
        <h3 class="text-3xl font-semibold"><?= __('Personal wellbeing stays private by design.') ?></h3>
        <p class="text-sm text-white/70"><?= __('Encrypted journaling, optional passkeys, and recovery codes keep reflections alongside finances without ever compromising trust.') ?></p>
      </div>
      <ul class="space-y-5 text-sm text-white/70">
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="sparkles" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Gentle prompts surface only to you—never shared or marketed.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="folder-lock" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Private vault separates mood notes from transactional data exports.') ?></span>
        </li>
        <li class="flex gap-3">
          <span class="mt-1"><i data-lucide="shield" class="h-4 w-4 text-emerald-300"></i></span>
          <span><?= __('Independent audits keep our open-source controls transparent.') ?></span>
        </li>
      </ul>
    </div>
  </div>
</section>

<section id="rituals" class="bg-white px-6 py-24">
  <div class="mx-auto max-w-6xl grid gap-12 lg:grid-cols-[1.1fr_minmax(0,0.9fr)] lg:items-center">
    <div class="space-y-5">
      <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-500">
        <?= __('Daily wellbeing rituals') ?>
      </span>
      <h2 class="text-4xl font-semibold tracking-tight text-gray-900">
        <?= __('Create a rhythm that feels intentional every day.') ?>
      </h2>
      <p class="text-base text-gray-600">
        <?= __('MoneyMap gently guides you through mindful check-ins that pair your emotions with your money choices.') ?>
      </p>
      <div class="grid gap-4">
        <article class="rounded-3xl border border-gray-200 bg-gray-50 p-6">
          <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
              <i data-lucide="sunrise" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Morning intention') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Set a focus for the day and review which habits support your energy and spending plan.') ?></p>
            </div>
          </div>
        </article>
        <article class="rounded-3xl border border-gray-200 bg-white p-6">
          <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
              <i data-lucide="coffee" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Afternoon check-in') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Gentle reminders help you track spending moods and keep your goals nourishing, not rigid.') ?></p>
            </div>
          </div>
        </article>
        <article class="rounded-3xl border border-gray-200 bg-white p-6">
          <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900/10 text-gray-900">
              <i data-lucide="moon-star" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Evening gratitude') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Close the day celebrating wins, noting feelings, and planning next steps with clarity.') ?></p>
            </div>
          </div>
        </article>
      </div>
    </div>
    <div class="space-y-6 rounded-4xl border border-gray-200 bg-gray-100 p-10">
      <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Personal dashboard preview') ?></p>
      <div class="space-y-4 text-sm text-gray-600">
        <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between text-gray-900">
            <span class="text-sm font-semibold uppercase tracking-[0.24em]"><?= __('Today’s focus') ?></span>
            <span class="flex items-center gap-2 text-xs text-gray-500"><i data-lucide="sparkle" class="h-4 w-4"></i><?= __('In sync') ?></span>
          </div>
          <p class="mt-3 text-base text-gray-700">
            <?= __('Stay nourished · celebrate mindful wins · move with calm confidence.') ?>
          </p>
        </div>
        <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between text-gray-900">
            <span class="text-sm font-semibold uppercase tracking-[0.24em]"><?= __('Feelings log') ?></span>
            <span class="flex items-center gap-2 text-xs text-gray-500"><i data-lucide="smile-plus" class="h-4 w-4"></i><?= __('Private') ?></span>
          </div>
          <ul class="mt-4 space-y-3">
            <li class="flex items-start gap-3">
              <span class="mt-1"><i data-lucide="feather" class="h-4 w-4 text-emerald-500"></i></span>
              <span><?= __('Morning: grounded and grateful after reviewing savings ritual.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1"><i data-lucide="heart" class="h-4 w-4 text-emerald-500"></i></span>
              <span><?= __('Midday: calm after adjusting grocery plan with mindful spending cue.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1"><i data-lucide="moon" class="h-4 w-4 text-emerald-500"></i></span>
              <span><?= __('Evening: celebratory gratitude, logged a new wellbeing milestone.') ?></span>
            </li>
          </ul>
        </div>
      </div>
      <a href="/register" class="btn btn-primary w-full justify-center border border-transparent bg-gray-900 py-3 text-base font-semibold text-white hover:bg-gray-800">
        <?= __('Start your free trial') ?>
      </a>
    </div>
  </div>
</section>

<section id="stories" class="bg-gray-50 px-6 py-24">
  <div class="mx-auto max-w-6xl space-y-12">
    <div class="space-y-4 text-center">
      <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-500">
        <?= __('Stories from our community') ?>
      </span>
      <h2 class="text-4xl font-semibold tracking-tight text-gray-900">
        <?= __('Personal victories that feel steady and joyful.') ?>
      </h2>
      <p class="text-base text-gray-600">
        <?= __('Real members share how MoneyMap helps them stay grounded while reaching personal goals and wellbeing milestones.') ?>
      </p>
    </div>
    <div class="grid gap-6 md:grid-cols-3">
      <article class="space-y-4 rounded-3xl border border-gray-200 bg-white p-8 text-left shadow-sm">
        <div class="flex items-center gap-3">
          <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-900/10 text-gray-900">
            <i data-lucide="leaf" class="h-5 w-5"></i>
          </span>
          <div class="text-left">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-gray-500"><?= __('Amelia, mindful saver') ?></p>
            <p class="text-sm text-gray-400"><?= __('Joined in 2021') ?></p>
          </div>
        </div>
        <p class="text-sm text-gray-600">
          <?= __('I finally see how my choices influence the calm I feel. The reflections beside each purchase changed how I plan my weeks.') ?>
        </p>
      </article>
      <article class="space-y-4 rounded-3xl border border-gray-200 bg-white p-8 text-left shadow-sm">
        <div class="flex items-center gap-3">
          <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-900/10 text-gray-900">
            <i data-lucide="compass" class="h-5 w-5"></i>
          </span>
          <div class="text-left">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-gray-500"><?= __('Mateo, future planner') ?></p>
            <p class="text-sm text-gray-400"><?= __('Joined in 2019') ?></p>
          </div>
        </div>
        <p class="text-sm text-gray-600">
          <?= __('MoneyMap made saving for my wellness retreats feel human. It keeps me inspired without the stress or spreadsheets.') ?>
        </p>
      </article>
      <article class="space-y-4 rounded-3xl border border-gray-200 bg-white p-8 text-left shadow-sm">
        <div class="flex items-center gap-3">
          <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-900/10 text-gray-900">
            <i data-lucide="star" class="h-5 w-5"></i>
          </span>
          <div class="text-left">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-gray-500"><?= __('Sofia, new beginnings') ?></p>
            <p class="text-sm text-gray-400"><?= __('Joined in 2023') ?></p>
          </div>
        </div>
        <p class="text-sm text-gray-600">
          <?= __('After a career change, the rituals helped me rebuild a sense of safety with my spending. Each check-in feels like a supportive friend.') ?>
        </p>
      </article>
    </div>
  </div>
</section>

<section id="pricing" class="bg-white px-6 py-24">
  <div class="mx-auto max-w-6xl space-y-14">
    <div class="grid gap-8 lg:grid-cols-[1.1fr_minmax(0,0.9fr)] lg:items-end">
      <div class="space-y-4">
        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-500">
          <?= __('Plans made for personal growth') ?>
        </span>
        <h2 class="text-4xl font-semibold tracking-tight text-gray-900">
          <?= __('Flexible memberships that keep you encouraged.') ?>
        </h2>
        <p class="text-base text-gray-600">
          <?= __('Choose the rhythm that suits your personal goals. Every plan includes encrypted access, mindful rituals, and personalised support.') ?>
        </p>
      </div>
      <div class="flex flex-wrap justify-start gap-4 lg:justify-end">
        <a href="/register" class="btn btn-primary border border-transparent bg-gray-900 px-6 py-3 text-base text-white hover:bg-gray-800">
          <?= __('Start your free trial') ?>
        </a>
        <a href="/subscriptions" class="btn btn-ghost px-6 py-3 text-base text-gray-700">
          <?= __('Explore plans in detail') ?>
        </a>
      </div>
    </div>
    <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
      <article class="space-y-6 rounded-4xl border border-gray-200 bg-white p-10 shadow-sm">
        <header class="space-y-2">
          <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Flexible monthly') ?></p>
          <h3 class="text-3xl font-semibold text-gray-900"><?= __('MoneyMap Plus') ?></h3>
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
        <a href="/register" class="btn btn-primary w-full justify-center border border-transparent bg-gray-900 py-3 text-base font-semibold text-white hover:bg-gray-800">
          <?= __('Begin monthly plan') ?>
        </a>
      </article>
      <article class="space-y-6 rounded-4xl border border-gray-900 bg-gray-900 p-10 text-white shadow-xl">
        <header class="space-y-2">
          <p class="text-sm font-semibold uppercase tracking-[0.28em] text-white/60"><?= __('Best value yearly') ?></p>
          <h3 class="text-3xl font-semibold"><?= __('MoneyMap Plus Annual') ?></h3>
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
        <a href="/register" class="btn btn-primary w-full justify-center border border-transparent bg-white py-3 text-base font-semibold text-gray-900 hover:bg-gray-100">
          <?= __('Begin annual plan') ?>
        </a>
      </article>
    </div>
  </div>
</section>

<section class="bg-gray-950 px-6 py-20 text-white">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6">
    <div class="space-y-3 max-w-xl">
      <span class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
        <?= __('Ready when you are') ?>
      </span>
      <h2 class="text-3xl font-semibold tracking-tight text-white">
        <?= __('Start with clarity, continue with confidence.') ?>
      </h2>
      <p class="text-sm text-white/70">
        <?= __('We support personal money journeys in English, Español, and Magyar, with human support that understands the wellbeing-first approach.') ?>
      </p>
    </div>
    <div class="flex flex-wrap gap-4">
      <a href="/register" class="btn btn-primary border border-transparent bg-white px-6 py-3 text-base font-semibold text-gray-900 hover:bg-gray-100">
        <?= __('Create your account') ?>
      </a>
      <a href="/login" class="btn btn-ghost border border-white/30 px-6 py-3 text-base text-white/80 hover:border-white hover:text-white">
        <?= __('Log in') ?>
      </a>
    </div>
  </div>
</section>
