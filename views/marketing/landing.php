<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="space-y-24 bg-gradient-to-b from-sky-50 via-white to-white pb-24">
  <header class="mt-6">
    <nav class="flex items-center justify-between rounded-3xl border border-white/80 bg-white/90 px-6 py-4 shadow-lg shadow-sky-100/60 backdrop-blur">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-900">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-emerald-400 text-white shadow-lg">
          <img src="/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="h-9 w-9 object-contain" />
        </div>
        <span><?= htmlspecialchars($appName) ?></span>
      </a>
      <div class="flex items-center gap-3 text-sm font-medium text-slate-600">
        <a href="#features" class="hidden rounded-full border border-slate-200 px-4 py-2 transition hover:border-sky-200 hover:text-sky-700 sm:inline-flex"><?= __('Features') ?></a>
        <a href="#rituals" class="hidden rounded-full border border-slate-200 px-4 py-2 transition hover:border-sky-200 hover:text-sky-700 sm:inline-flex"><?= __('Daily rituals') ?></a>
        <a href="#pricing" class="hidden rounded-full border border-slate-200 px-4 py-2 transition hover:border-sky-200 hover:text-sky-700 sm:inline-flex"><?= __('Pricing') ?></a>
        <a href="/subscriptions" class="hidden rounded-full border border-slate-200 px-4 py-2 transition hover:border-sky-200 hover:text-sky-700 sm:inline-flex"><?= __('Subscriptions') ?></a>
        <a href="/login" class="rounded-full border border-sky-200/70 px-4 py-2 text-sky-700 transition hover:bg-sky-50">
          <?= __('Log in') ?>
        </a>
        <a href="/register" class="btn btn-primary hidden bg-gradient-to-r from-sky-500 to-emerald-500 text-white shadow-md transition hover:shadow-lg sm:inline-flex">
          <?= __('Start free trial') ?>
        </a>
      </div>
    </nav>
  </header>

  <section class="relative overflow-hidden rounded-[3rem] border border-sky-100 bg-white px-6 py-16 shadow-xl">
    <div class="pointer-events-none absolute -right-32 -top-32 h-80 w-80 rounded-full bg-gradient-to-br from-sky-200 via-sky-100 to-transparent blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-20 h-96 w-96 rounded-full bg-gradient-to-tr from-emerald-200 via-sky-100 to-transparent blur-3xl"></div>
    <div class="relative grid gap-12 lg:grid-cols-[1.1fr_minmax(0,0.9fr)]">
      <div class="space-y-8">
        <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-sky-700 shadow-sm">
          <?= __('New') ?> · <?= __('Personal finance for real life') ?>
        </span>
        <h1 class="text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
          <?= __('Feel good about money again—plan, spend, and save with ease.') ?>
        </h1>
        <p class="text-lg leading-relaxed text-slate-600">
          <?= __('MoneyMap is your calming home base for budgets, goals, mindful spending, and future dreams. Build routines that support your wellbeing, celebrate every win, and stay gently on track.') ?>
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="/register" class="btn btn-primary text-base px-6 py-3">
            <?= __('Start tracking for free') ?>
          </a>
          <a href="#login-card" class="btn btn-ghost text-base px-6 py-3">
            <?= __('Sign in to your account') ?>
          </a>
        </div>
        <dl class="grid gap-6 sm:grid-cols-3">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('Clarity moments logged') ?></dt>
            <dd class="mt-2 flex items-center gap-2 text-3xl font-semibold text-slate-900">
              <i data-lucide="sparkles" class="h-6 w-6 text-emerald-500"></i>
              320K+
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('Average monthly calm regained') ?></dt>
            <dd class="mt-2 flex items-center gap-2 text-3xl font-semibold text-slate-900">
              <i data-lucide="smile" class="h-6 w-6 text-sky-500"></i>
              12 hrs
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('People staying on personal goals') ?></dt>
            <dd class="mt-2 flex items-center gap-2 text-3xl font-semibold text-slate-900">
              <i data-lucide="heart" class="h-6 w-6 text-rose-500"></i>
              98%
            </dd>
          </div>
        </dl>
      </div>
      <div id="login-card" class="lg:pl-8">
        <div class="card space-y-6 border border-sky-100 shadow-lg">
          <div>
            <p class="card-kicker mb-3 text-sky-600"><?= __('Welcome back') ?></p>
            <h2 class="text-2xl font-semibold text-slate-900"><?= __('Log in instantly') ?></h2>
            <p class="mt-2 text-sm text-slate-500"><?= __('Keep your rhythm going—secure sessions, biometric-ready, and protected by encryption out of the box.') ?></p>
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
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-sky-500">
                  <i data-lucide="mail" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <div class="field">
              <div class="mb-1 flex items-center justify-between">
                <label class="label"><?= __('Password') ?></label>
                <a href="/forgot" class="text-xs font-semibold text-sky-600 hover:underline"><?= __('Forgot?') ?></a>
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
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-sky-500">
                  <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
              <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-sky-200 text-sky-600 focus:ring-sky-400" />
              <span><?= __('Keep me signed in') ?></span>
            </label>
            <button class="btn btn-primary w-full text-base"><?= __('Log in securely') ?></button>
            <p class="text-center text-xs text-slate-400"><?= __('By continuing you agree to the Terms & Privacy Policy.') ?></p>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="space-y-12">
    <div class="space-y-3 text-center">
      <p class="chip mx-auto bg-sky-50 text-sky-600"><?= __('Why people love :app', ['app' => $appName]) ?></p>
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
        <?= __('The gentle money companion cheering on your personal goals.') ?>
      </h2>
      <p class="mx-auto max-w-3xl text-lg text-slate-600">
        <?= __('From mindful spending check-ins to future-focused savings, MoneyMap blends smart automations with uplifting guidance so you feel confident every step.') ?>
      </p>
    </div>
    <div class="grid gap-6 md:grid-cols-2">
      <article class="tile space-y-4 border border-sky-100 bg-white shadow-sm">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-100 text-sky-600">
              <i data-lucide="line-chart" class="h-5 w-5"></i>
            </span>
            <h3 class="text-xl font-semibold text-slate-900"><?= __('All accounts, one beautiful view') ?></h3>
          </div>
          <span class="chip hidden bg-emerald-50 text-emerald-600 sm:inline-flex"><?= __('Real time') ?></span>
        </div>
        <p class="text-sm leading-relaxed text-slate-600">
          <?= __('Connect banks, cards, and savings pots in seconds. Friendly charts and colour-coded trends make it easy to spot what’s working for your lifestyle.') ?>
        </p>
      </article>
      <article class="tile space-y-4 border border-sky-100 bg-white shadow-sm">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-100 text-sky-600">
            <i data-lucide="wand2" class="h-5 w-5"></i>
          </span>
          <h3 class="text-xl font-semibold text-slate-900"><?= __('Automations that feel like a helping hand') ?></h3>
        </div>
        <p class="text-sm leading-relaxed text-slate-600">
          <?= __('Set up gentle reminders, automatic transfers, and personalised nudges so the essentials happen while you focus on what lights you up.') ?>
        </p>
      </article>
      <article class="tile space-y-4 border border-sky-100 bg-white shadow-sm">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-100 text-sky-600">
            <i data-lucide="target" class="h-5 w-5"></i>
          </span>
          <h3 class="text-xl font-semibold text-slate-900"><?= __('Goals that stay on track') ?></h3>
        </div>
        <p class="text-sm leading-relaxed text-slate-600">
          <?= __('Stack savings, debt payoff, and dream purchases with progress bars that update themselves. Encouraging alerts keep momentum high and stress low.') ?>
        </p>
      </article>
      <article class="tile space-y-4 border border-sky-100 bg-white shadow-sm">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-100 text-sky-600">
            <i data-lucide="shield-check" class="h-5 w-5"></i>
          </span>
          <h3 class="text-xl font-semibold text-slate-900"><?= __('Security engineered for peace of mind') ?></h3>
        </div>
        <p class="text-sm leading-relaxed text-slate-600">
          <?= __('Passkeys, encryption, and privacy controls are built into the core. You choose what to store, how to share, and when to wipe data—instantly.') ?>
        </p>
      </article>
    </div>
  </section>

  <section class="grid gap-12 lg:grid-cols-[1.05fr_minmax(0,0.95fr)]">
    <div class="card space-y-6 border border-sky-100 bg-white shadow-lg">
      <span class="chip w-fit bg-emerald-50 text-emerald-600"><?= __('Customer spotlight') ?></span>
      <p class="text-2xl font-semibold leading-tight text-slate-900">
        “<?= __('MoneyMap helped me pay off debt and still budget for weekend adventures with my family.') ?>”
      </p>
      <div class="flex items-center gap-4">
        <div class="h-14 w-14 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm">
          <img src="https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=facearea&w=120&h=120&q=80" alt="<?= __('Happy customer portrait') ?>" class="h-full w-full object-cover" />
        </div>
        <div>
          <p class="font-semibold text-slate-900">Amina</p>
          <p class="text-sm text-slate-500"><?= __('Parent & creative, Austin TX') ?></p>
        </div>
      </div>
    </div>
    <div class="grid gap-6 md:grid-cols-2">
      <div class="panel space-y-3 border border-sky-100 bg-white">
        <h3 class="text-lg font-semibold text-slate-900"><?= __('Bank-grade encryption') ?></h3>
        <p class="text-sm text-slate-600"><?= __('256-bit encryption, isolated vaults, and optional local key storage keep sensitive data sealed, even from us.') ?></p>
      </div>
      <div class="panel space-y-3 border border-sky-100 bg-white">
        <h3 class="text-lg font-semibold text-slate-900"><?= __('Private by default') ?></h3>
        <p class="text-sm text-slate-600"><?= __('Control retention, export cleanly, or purge with a click. MoneyMap never sells or brokers your financial data.') ?></p>
      </div>
      <div class="panel space-y-3 border border-sky-100 bg-white">
        <h3 class="text-lg font-semibold text-slate-900"><?= __('Human support on your side') ?></h3>
        <p class="text-sm text-slate-600"><?= __('Real people answer within 12 hours with practical guidance—not canned scripts.') ?></p>
      </div>
      <div class="panel space-y-3 border border-sky-100 bg-white">
        <h3 class="text-lg font-semibold text-slate-900"><?= __('Always improving') ?></h3>
        <p class="text-sm text-slate-600"><?= __('Weekly releases deliver new insights, smarter automations, and integrations with the accounts you rely on.') ?></p>
      </div>
    </div>
  </section>

  <section id="rituals" class="space-y-12">
    <div class="space-y-3 text-center">
      <p class="chip mx-auto bg-rose-50 text-rose-500"><?= __('Daily rituals') ?></p>
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl"><?= __('Stay grounded with gentle routines') ?></h2>
      <p class="mx-auto max-w-2xl text-lg text-slate-600"><?= __('Build nourishing habits around money. MoneyMap nudges you to check in, celebrate progress, and align spending with what makes you feel alive.') ?></p>
    </div>
    <div class="grid gap-6 md:grid-cols-3">
      <div class="panel space-y-3 border border-rose-100 bg-white">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-rose-100 text-rose-500">
            <i data-lucide="sun" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Morning clarity check') ?></h3>
        </div>
        <p class="text-sm text-slate-600"><?= __('Peek at your balance, see upcoming bills, and head into the day with purpose in under two minutes.') ?></p>
      </div>
      <div class="panel space-y-3 border border-emerald-100 bg-white">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-100 text-emerald-500">
            <i data-lucide="notebook-pen" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Weekly reflection prompt') ?></h3>
        </div>
        <p class="text-sm text-slate-600"><?= __('Answer a thoughtful question, jot a note, and celebrate a win. Your progress timeline keeps each story.') ?></p>
      </div>
      <div class="panel space-y-3 border border-sky-100 bg-white">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-100 text-sky-600">
            <i data-lucide="calendar-heart" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Monthly intention reset') ?></h3>
        </div>
        <p class="text-sm text-slate-600"><?= __('Realign budgets with your values, adjust goals in a tap, and set fresh intentions for the month ahead.') ?></p>
      </div>
    </div>
  </section>

  <section id="pricing" class="space-y-10">
    <div class="space-y-3 text-center">
      <p class="chip mx-auto bg-sky-50 text-sky-600"><?= __('Simple pricing') ?></p>
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl"><?= __('Everything you need, one transparent plan.') ?></h2>
      <p class="mx-auto max-w-2xl text-lg text-slate-600"><?= __('Start with a 14-day free trial. Keep your data, cancel anytime—no hidden fees, ever.') ?></p>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
      <div class="panel space-y-5 border border-sky-100 bg-white shadow-lg">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-semibold text-slate-900"><?= __('MoneyMap Plus') ?></h3>
          <span class="chip bg-emerald-50 text-emerald-600"><?= __('Most loved') ?></span>
        </div>
        <p class="text-sm text-slate-600"><?= __('Unlimited accounts, mindful automations, guided reflections, and community workshops.') ?></p>
        <div class="flex items-baseline gap-2 text-slate-900">
          <span class="text-4xl font-semibold">$14</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per month') ?></span>
        </div>
        <ul class="space-y-3 text-sm text-slate-600">
          <li class="flex items-start gap-3"><span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span><span><?= __('Smart dashboards &amp; forecasting') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span><span><?= __('Unlimited automation rules &amp; alerts') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span><span><?= __('Share progress with someone you trust') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span><span><?= __('Priority human support under 12 hours') ?></span></li>
        </ul>
        <a href="/register" class="btn btn-primary w-full justify-center text-base">
          <?= __('Start your free trial') ?>
        </a>
      </div>
      <div class="panel space-y-5 border border-sky-100 bg-white shadow-lg">
        <h3 class="text-xl font-semibold text-slate-900"><?= __('Prefer to pay annually?') ?></h3>
        <p class="text-sm text-slate-600"><?= __('Get two months free, lock in your routine, and receive seasonal planning guides straight to your inbox.') ?></p>
        <div class="flex items-baseline gap-2 text-slate-900">
          <span class="text-4xl font-semibold">$140</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per year') ?></span>
        </div>
        <ul class="space-y-3 text-sm text-slate-600">
          <li class="flex items-start gap-3"><span class="mt-1 text-sky-500"><i data-lucide="sparkles" class="h-4 w-4"></i></span><span><?= __('Seasonal wellbeing workshops included') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-sky-500"><i data-lucide="gift" class="h-4 w-4"></i></span><span><?= __('Exclusive goal templates and journaling prompts') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-sky-500"><i data-lucide="calendar-range" class="h-4 w-4"></i></span><span><?= __('Plan with confidence all year long') ?></span></li>
        </ul>
        <a href="/subscriptions" class="btn btn-ghost w-full justify-center text-base">
          <?= __('Explore subscriptions') ?>
        </a>
      </div>
    </div>
  </section>

  <section class="rounded-[2.75rem] border border-sky-100 bg-white px-8 py-12 text-center shadow-xl">
    <p class="chip mx-auto bg-emerald-50 text-emerald-600"><?= __('Ready when you are') ?></p>
    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
      <?= __('Take back control of your money in less than 10 minutes.') ?>
    </h2>
    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">
      <?= __('Join thousands of calm, confident MoneyMappers transforming their finances with clarity. Start free, invite your accountability buddy, and keep your data for life.') ?>
    </p>
    <div class="mt-8 flex flex-wrap justify-center gap-4">
      <a href="/register" class="btn btn-primary px-8 py-3 text-base"><?= __('Create your account') ?></a>
      <a href="/login" class="btn btn-ghost px-8 py-3 text-base"><?= __('I already have an account') ?></a>
    </div>
  </section>
</div>
