<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="bg-gray-100 pb-24">
  <header class="border-b border-gray-200 bg-white/95 backdrop-blur">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight text-gray-900">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
          <img src="/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="h-8 w-8 object-contain" />
        </div>
        <span><?= htmlspecialchars($appName) ?></span>
      </a>
      <div class="hidden items-center gap-4 text-sm font-medium text-gray-600 lg:flex">
        <a href="#features" class="rounded-full px-4 py-2 transition hover:bg-gray-100 hover:text-gray-900"><?= __('Features') ?></a>
        <a href="#confidence" class="rounded-full px-4 py-2 transition hover:bg-gray-100 hover:text-gray-900"><?= __('Confidence') ?></a>
        <a href="#stories" class="rounded-full px-4 py-2 transition hover:bg-gray-100 hover:text-gray-900"><?= __('Stories') ?></a>
        <a href="#pricing" class="rounded-full px-4 py-2 transition hover:bg-gray-100 hover:text-gray-900"><?= __('Pricing') ?></a>
        <a href="/subscriptions" class="rounded-full px-4 py-2 transition hover:bg-gray-100 hover:text-gray-900"><?= __('Subscriptions') ?></a>
      </div>
      <div class="flex items-center gap-3 text-sm font-medium text-gray-600">
        <a href="/login" class="rounded-full border border-gray-300 px-4 py-2 transition hover:border-gray-900 hover:text-gray-900">
          <?= __('Log in') ?>
        </a>
        <a href="/register" class="btn btn-primary hidden px-5 py-2 sm:inline-flex">
          <?= __('Start free trial') ?>
        </a>
      </div>
    </nav>
  </header>

  <section class="px-6 pt-16">
    <div class="mx-auto grid max-w-6xl gap-12 lg:grid-cols-[1.05fr_minmax(0,0.95fr)]">
      <div class="space-y-8">
        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-gray-600">
          <?= __('Personal finance wellbeing') ?>
        </span>
        <h1 class="text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">
          <?= __('Premium money guidance that feels steady and trustworthy.') ?>
        </h1>
        <p class="text-lg leading-relaxed text-gray-600">
          <?= __('MyMoneyMap keeps your day-to-day finances organised, intentional, and private so every decision supports the life you want.') ?>
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="/register" class="btn btn-primary text-base px-6 py-3">
            <?= __('Create your account') ?>
          </a>
          <a href="#login-card" class="btn btn-ghost text-base px-6 py-3">
            <?= __('Sign in securely') ?>
          </a>
        </div>
        <dl class="grid gap-6 sm:grid-cols-3">
          <div class="space-y-1">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-gray-400"><?= __('Households organised') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-gray-900">
              <i data-lucide="shield" class="h-6 w-6 text-gray-700"></i>
              120K+
            </dd>
            <p class="text-xs text-gray-500"><?= __('Encrypted connections with industry-standard safeguards.') ?></p>
          </div>
          <div class="space-y-1">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-gray-400"><?= __('Goals advanced') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-gray-900">
              <i data-lucide="target" class="h-6 w-6 text-gray-700"></i>
              460K
            </dd>
            <p class="text-xs text-gray-500"><?= __('Automations keep every milestone on schedule without stress.') ?></p>
          </div>
          <div class="space-y-1">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-gray-400"><?= __('Average calm score') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-gray-900">
              <i data-lucide="smile" class="h-6 w-6 text-gray-700"></i>
              9.4/10
            </dd>
            <p class="text-xs text-gray-500"><?= __('Members feel more present with their everyday money rituals.') ?></p>
          </div>
        </dl>
      </div>
      <div id="login-card" class="lg:pl-8">
        <div class="space-y-5 rounded-3xl border border-gray-200 bg-white p-8 shadow-lg">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-400"><?= __('Client access') ?></p>
            <h2 class="mt-2 text-2xl font-semibold text-gray-900"><?= __('Log in securely') ?></h2>
            <p class="mt-2 text-sm text-gray-500"><?= __('Biometric-ready sessions, device approvals, and privacy by default.') ?></p>
          </div>
          <form method="post" action="/login" class="space-y-4" novalidate>
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <div class="field">
              <label class="label mb-1"><?= __('Email') ?></label>
              <div class="relative">
                <input
                  type="email"
                  name="email"
                  class="input pl-11"
                  placeholder="<?= __('you@example.com') ?>"
                  autocomplete="email"
                  required
                />
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-gray-400">
                  <i data-lucide="mail" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <div class="field">
              <div class="mb-1 flex items-center justify-between">
                <label class="label"><?= __('Password') ?></label>
                <a href="/forgot" class="text-xs font-semibold text-gray-600 hover:text-gray-900 hover:underline"><?= __('Forgot?') ?></a>
              </div>
              <div class="relative">
                <input
                  type="password"
                  name="password"
                  class="input pl-11"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  required
                />
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-gray-400">
                  <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
              <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900" />
              <span><?= __('Keep me signed in') ?></span>
            </label>
            <button class="btn btn-primary w-full justify-center text-base">
              <?= __('Log in securely') ?>
            </button>
            <p class="text-center text-xs text-gray-400"><?= __('By continuing you agree to the Terms & Privacy Policy.') ?></p>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section id="confidence" class="px-6 pt-16">
    <div class="mx-auto max-w-5xl rounded-3xl border border-gray-200 bg-white px-10 py-14 shadow-xl">
      <div class="grid gap-10 lg:grid-cols-3">
        <div class="space-y-4">
          <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-400"><?= __('Trusted foundations') ?></p>
          <h2 class="text-3xl font-semibold tracking-tight text-gray-900">
            <?= __('Built with the same controls we rely on for our own finances.') ?>
          </h2>
          <p class="text-base text-gray-600">
            <?= __('From read-only banking connections to granular device approvals, every layer of :app is engineered for resilience.', ['app' => $appName]) ?>
          </p>
        </div>
        <div class="space-y-6">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="lock" class="h-5 w-5"></i>
            </span>
            <div class="space-y-1">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Bank-grade encryption') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Data is encrypted in transit and at rest with monitored access policies.') ?></p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="fingerprint" class="h-5 w-5"></i>
            </span>
            <div class="space-y-1">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Biometric friendly') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Passkeys, device reviews, and sign-in alerts keep access under your control.') ?></p>
            </div>
          </div>
        </div>
        <div class="space-y-6">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="check-circle" class="h-5 w-5"></i>
            </span>
            <div class="space-y-1">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Independent audits') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Infrastructure is reviewed regularly for compliance and operational readiness.') ?></p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="timeline" class="h-5 w-5"></i>
            </span>
            <div class="space-y-1">
              <h3 class="text-lg font-semibold text-gray-900"><?= __('Transparent activity trail') ?></h3>
              <p class="text-sm text-gray-600"><?= __('Detailed logs and exports help you verify every automation and manual change.') ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="px-6 pt-16">
    <div class="mx-auto max-w-5xl space-y-12">
      <div class="space-y-3 text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Why people choose :app', ['app' => $appName]) ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
          <?= __('Designed for personal goals with tools that respect your focus.') ?>
        </h2>
        <p class="mx-auto max-w-3xl text-lg text-gray-600">
          <?= __('Every workflow blends mindful rituals with precise automation so you stay in command of the moments that matter most.') ?>
        </p>
      </div>
      <div class="grid gap-6 md:grid-cols-2">
        <article class="space-y-3 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="line-chart" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-gray-900"><?= __('Clarity in one view') ?></h3>
              <p class="text-sm leading-relaxed text-gray-600">
                <?= __('Link accounts, cards, and cash. Dashboards show spending, saving, and wellbeing side-by-side.') ?>
              </p>
            </div>
          </div>
        </article>
        <article class="space-y-3 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="heart" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-gray-900"><?= __('Goals that feel human') ?></h3>
              <p class="text-sm leading-relaxed text-gray-600">
                <?= __('Create milestones with gentle nudges, reflections, and celebrations that keep motivation high.') ?>
              </p>
            </div>
          </div>
        </article>
        <article class="space-y-3 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="notebook" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-gray-900"><?= __('Rituals built in') ?></h3>
              <p class="text-sm leading-relaxed text-gray-600">
                <?= __('Morning intentions, afternoon reviews, and evening gratitude help you stay grounded.') ?>
              </p>
            </div>
          </div>
        </article>
        <article class="space-y-3 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="sparkles" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-gray-900"><?= __('Insights you can audit') ?></h3>
              <p class="text-sm leading-relaxed text-gray-600">
                <?= __('Export-ready reports and rule histories make accountability effortless for personal stewardship.') ?>
              </p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section id="stories" class="px-6 pt-16">
    <div class="mx-auto max-w-5xl space-y-10">
      <div class="space-y-3 text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Stories from our community') ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
          <?= __('Everyday people building confident routines.') ?>
        </h2>
      </div>
      <div class="grid gap-6 md:grid-cols-2">
        <figure class="rounded-3xl border border-gray-200 bg-white p-8 shadow-md">
          <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="feather" class="h-5 w-5"></i>
            </span>
            <figcaption class="text-sm font-semibold text-gray-900"><?= __('Amelia, mindful saver') ?></figcaption>
          </div>
          <blockquote class="text-base text-gray-600">
            “<?= __('Seeing transactions beside my mood notes keeps my intentions aligned week after week.') ?>”
          </blockquote>
        </figure>
        <figure class="rounded-3xl border border-gray-200 bg-white p-8 shadow-md">
          <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700">
              <i data-lucide="leaf" class="h-5 w-5"></i>
            </span>
            <figcaption class="text-sm font-semibold text-gray-900"><?= __('Mateo, future planner') ?></figcaption>
          </div>
          <blockquote class="text-base text-gray-600">
            “<?= __('I track wellness investments alongside budgets so I never doubt if my plans are realistic.') ?>”
          </blockquote>
        </figure>
      </div>
    </div>
  </section>

  <section id="pricing" class="px-6 pt-16">
    <div class="mx-auto max-w-5xl space-y-10 rounded-3xl border border-gray-200 bg-white px-10 py-14 shadow-xl">
      <div class="space-y-3 text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Plans for personal growth') ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
          <?= __('Flexible memberships with the same trusted toolkit.') ?>
        </h2>
      </div>
      <div class="grid gap-6 lg:grid-cols-2">
        <article class="space-y-5 rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
          <header class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-500"><?= __('Monthly membership') ?></p>
            <h3 class="text-2xl font-semibold text-gray-900"><?= __('MoneyMap Plus') ?></h3>
            <p class="text-sm text-gray-600"><?= __('All wellbeing rituals, automations, and support with flexible commitment.') ?></p>
          </header>
          <div class="flex items-baseline gap-2 text-gray-900">
            <span class="text-4xl font-semibold">$14</span>
            <span class="text-sm font-semibold uppercase tracking-[0.3em] text-gray-400"><?= __('per month') ?></span>
          </div>
          <ul class="space-y-3 text-sm text-gray-600">
            <li class="flex items-start gap-3">
              <span class="mt-1 text-gray-700"><i data-lucide="check" class="h-4 w-4"></i></span>
              <span><?= __('Unlimited accounts, mindful automations, and ritual reminders.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-gray-700"><i data-lucide="check" class="h-4 w-4"></i></span>
              <span><?= __('Guided reflections with optional journaling prompts.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-gray-700"><i data-lucide="check" class="h-4 w-4"></i></span>
              <span><?= __('Support responses under 12 hours.') ?></span>
            </li>
          </ul>
          <a href="/register" class="btn btn-primary w-full justify-center text-base">
            <?= __('Start monthly plan') ?>
          </a>
        </article>
        <article class="space-y-5 rounded-3xl border border-gray-900 bg-gray-900/95 p-8 text-white shadow-xl">
          <header class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-gray-300"><?= __('Best value') ?></p>
            <h3 class="text-2xl font-semibold"><?= __('MoneyMap Plus Annual') ?></h3>
            <p class="text-sm text-gray-200"><?= __('Steady planners enjoy seasonal sessions, gratitude recaps, and priority support.') ?></p>
          </header>
          <div class="flex items-baseline gap-2">
            <span class="text-4xl font-semibold">$140</span>
            <span class="text-sm font-semibold uppercase tracking-[0.3em] text-gray-400"><?= __('per year') ?></span>
          </div>
          <ul class="space-y-3 text-sm text-gray-200">
            <li class="flex items-start gap-3">
              <span class="mt-1"><i data-lucide="sparkles" class="h-4 w-4"></i></span>
              <span><?= __('Seasonal wellbeing workshops included.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1"><i data-lucide="gift" class="h-4 w-4"></i></span>
              <span><?= __('Exclusive templates for future plans and mindfulness checklists.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1"><i data-lucide="heart" class="h-4 w-4"></i></span>
              <span><?= __('Quarterly gratitude summaries for your trusted circle.') ?></span>
            </li>
          </ul>
          <a href="/register" class="btn btn-primary w-full justify-center text-base bg-white text-gray-900 hover:bg-gray-100">
            <?= __('Start annual plan') ?>
          </a>
        </article>
      </div>
      <p class="text-center text-sm text-gray-500">
        <?= __('Every plan begins with a 14-day free trial. Cancel anytime—your data remains yours.') ?>
      </p>
    </div>
  </section>

  <section class="px-6 pt-16">
    <div class="mx-auto max-w-4xl space-y-6 text-center">
      <h2 class="text-3xl font-semibold tracking-tight text-gray-900">
        <?= __('Available in English, Español, and Magyar to support your journey worldwide.') ?>
      </h2>
      <p class="text-lg text-gray-600">
        <?= __('Switch languages anytime from your profile to continue mindful money rituals in the words that feel like home.') ?>
      </p>
      <div class="flex flex-wrap justify-center gap-4 text-sm font-semibold text-gray-700">
        <span class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2">
          <i data-lucide="globe-2" class="h-4 w-4 text-gray-700"></i>
          <?= __('English') ?>
        </span>
        <span class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2">
          <i data-lucide="globe-2" class="h-4 w-4 text-gray-700"></i>
          <?= __('Español') ?>
        </span>
        <span class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2">
          <i data-lucide="globe-2" class="h-4 w-4 text-gray-700"></i>
          <?= __('Magyar') ?>
        </span>
      </div>
    </div>
  </section>
</div>
