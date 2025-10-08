<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="space-y-24 pb-24">
  <header class="mt-6">
    <nav class="flex items-center justify-between rounded-3xl border border-white/70 bg-white/80 px-6 py-4 shadow-glass backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/90 shadow-brand-glow">
          <img src="/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="h-9 w-9 object-contain" />
        </div>
        <span><?= htmlspecialchars($appName) ?></span>
      </a>
      <div class="flex items-center gap-3 text-sm font-medium">
        <a href="#features" class="hidden rounded-2xl border border-white/60 px-4 py-2 text-slate-600 transition hover:border-brand-200 hover:text-brand-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-brand-400 dark:hover:text-brand-100 sm:inline-flex"><?= __('Features') ?></a>
        <a href="#pricing" class="hidden rounded-2xl border border-white/60 px-4 py-2 text-slate-600 transition hover:border-brand-200 hover:text-brand-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-brand-400 dark:hover:text-brand-100 sm:inline-flex"><?= __('Pricing') ?></a>
        <a href="/login" class="rounded-2xl border border-brand-200/80 px-4 py-2 text-brand-700 transition hover:bg-brand-50/80 dark:border-brand-400/60 dark:text-brand-100 dark:hover:bg-slate-800"><?= __('Log in') ?></a>
        <a href="/register" class="btn btn-primary hidden sm:inline-flex">
          <?= __('Start free trial') ?>
        </a>
      </div>
    </nav>
  </header>

  <section class="relative overflow-hidden rounded-[3rem] border border-white/70 bg-white/80 px-6 py-16 shadow-glass backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
    <div class="pointer-events-none absolute -right-32 -top-32 h-80 w-80 rounded-full bg-gradient-to-br from-brand-400/40 via-brand-500/30 to-brand-600/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-20 h-96 w-96 rounded-full bg-gradient-to-tr from-brand-400/25 via-emerald-400/25 to-transparent blur-3xl"></div>
    <div class="relative grid gap-12 lg:grid-cols-[1.1fr_minmax(0,0.9fr)]">
      <div class="space-y-8">
        <span class="inline-flex items-center gap-2 rounded-full border border-brand-200/80 bg-brand-50/70 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-brand-700 shadow-sm dark:border-brand-500/40 dark:bg-brand-500/15 dark:text-brand-100">
          <?= __('New') ?> · <?= __('Premium personal finance suite') ?>
        </span>
        <h1 class="text-4xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-5xl">
          <?= __('Master your money with clarity, confidence, and calm.') ?>
        </h1>
        <p class="text-lg leading-relaxed text-slate-600 dark:text-slate-300">
          <?= __('MoneyMap gives you one elegant command center for budgets, goals, investments, and everyday spending. See everything in real time, automate the boring stuff, and make every decision with a calm mind.') ?>
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
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400 dark:text-slate-500"><?= __('Accounts synced') ?></dt>
            <dd class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">120K+</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400 dark:text-slate-500"><?= __('Avg. savings unlocked') ?></dt>
            <dd class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">$4,820</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400 dark:text-slate-500"><?= __('Customer satisfaction') ?></dt>
            <dd class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">98%</dd>
          </div>
        </dl>
      </div>
      <div id="login-card" class="lg:pl-8">
        <div class="card space-y-6">
          <div>
            <p class="card-kicker mb-3 text-brand-600 dark:text-brand-200"><?= __('Welcome back') ?></p>
            <h2 class="text-2xl font-semibold text-slate-900 dark:text-white"><?= __('Log in instantly') ?></h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><?= __('Keep your streak going—secure sessions, biometric-ready, and protected by encryption out of the box.') ?></p>
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
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">✉️</span>
              </div>
            </div>
            <div class="field">
              <div class="mb-1 flex items-center justify-between">
                <label class="label"><?= __('Password') ?></label>
                <a href="/forgot" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-200"><?= __('Forgot?') ?></a>
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
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-lg text-brand-600/70 dark:text-brand-200/80">🔑</span>
              </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-brand-200 text-brand-600 focus:ring-brand-400" />
              <span><?= __('Keep me signed in') ?></span>
            </label>
            <button class="btn btn-primary w-full text-base"><?= __('Log in securely') ?></button>
            <p class="text-center text-xs text-slate-400 dark:text-slate-500"><?= __('By continuing you agree to the Terms & Privacy Policy.') ?></p>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="space-y-12">
    <div class="space-y-3 text-center">
      <p class="chip mx-auto"><?= __('Why people love :app', ['app' => $appName]) ?></p>
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
        <?= __('The modern money cockpit your future self will thank you for.') ?>
      </h2>
      <p class="mx-auto max-w-3xl text-lg text-slate-600 dark:text-slate-300">
        <?= __('From daily habits to long-term planning, MoneyMap combines intelligence, automation, and human design to keep every dollar aligned with your goals.') ?>
      </p>
    </div>
    <div class="grid gap-6 md:grid-cols-2">
      <article class="tile space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-500/15 text-brand-600 dark:text-brand-200">📊</span>
            <h3 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('All accounts, one beautiful view') ?></h3>
          </div>
          <span class="chip hidden sm:inline-flex"><?= __('Real time') ?></span>
        </div>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          <?= __('Link banks, cards, loans, and investments in seconds. Interactive net worth timelines, cashflow projections, and spending heatmaps keep you informed without overwhelm.') ?>
        </p>
      </article>
      <article class="tile space-y-4">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-500/15 text-brand-600 dark:text-brand-200">🤖</span>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Automations that work like a private CFO') ?></h3>
        </div>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          <?= __('Scheduled rules automatically file transactions, fund sinking buckets, and nudge you when bills are due. No spreadsheets, no mental math—just effortless flow.') ?>
        </p>
      </article>
      <article class="tile space-y-4">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-500/15 text-brand-600 dark:text-brand-200">🎯</span>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Goals that stay on track') ?></h3>
        </div>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          <?= __('Stack savings, debt payoff, and investing goals with progress bars that update themselves. Smart alerts keep momentum high and stress low.') ?>
        </p>
      </article>
      <article class="tile space-y-4">
        <div class="flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-500/15 text-brand-600 dark:text-brand-200">🛡️</span>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Security engineered for peace of mind') ?></h3>
        </div>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          <?= __('Passkeys, encryption, and privacy controls are built into the core. You choose what to store, how to share, and when to wipe data—instantly.') ?>
        </p>
      </article>
    </div>
  </section>

  <section class="grid gap-12 lg:grid-cols-[1.05fr_minmax(0,0.95fr)]">
    <div class="card space-y-6">
      <span class="chip w-fit"><?= __('Customer spotlight') ?></span>
      <p class="text-2xl font-semibold leading-tight text-slate-900 dark:text-white">
        “<?= __('MoneyMap gave us the clarity to pay off $42k in debt and boost investments without sacrificing the joy in our budget.') ?>”
      </p>
      <div class="flex items-center gap-4">
        <div class="h-14 w-14 overflow-hidden rounded-2xl border border-white/70 bg-white/70 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <img src="https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=facearea&w=120&h=120&q=80" alt="<?= __('Happy customer portrait') ?>" class="h-full w-full object-cover" />
        </div>
        <div>
          <p class="font-semibold text-slate-900 dark:text-white">Amina &amp; Leo</p>
          <p class="text-sm text-slate-500 dark:text-slate-400"><?= __('Creative entrepreneurs, Austin TX') ?></p>
        </div>
      </div>
    </div>
    <div class="grid gap-6 md:grid-cols-2">
      <div class="panel space-y-3">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Bank-grade encryption') ?></h3>
        <p class="text-sm text-slate-600 dark:text-slate-300"><?= __('256-bit encryption, isolated vaults, and optional local key storage keep sensitive data sealed, even from us.') ?></p>
      </div>
      <div class="panel space-y-3">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Private by default') ?></h3>
        <p class="text-sm text-slate-600 dark:text-slate-300"><?= __('Control retention, export cleanly, or purge with a click. MoneyMap never sells or brokers your financial data.') ?></p>
      </div>
      <div class="panel space-y-3">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Human support on your side') ?></h3>
        <p class="text-sm text-slate-600 dark:text-slate-300"><?= __('Dedicated specialists answer in under 12 hours with practical guidance—not canned scripts.') ?></p>
      </div>
      <div class="panel space-y-3">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('Always improving') ?></h3>
        <p class="text-sm text-slate-600 dark:text-slate-300"><?= __('Weekly releases deliver new insights, smarter automations, and integrations with the accounts you rely on.') ?></p>
      </div>
    </div>
  </section>

  <section id="pricing" class="space-y-10">
    <div class="space-y-3 text-center">
      <p class="chip mx-auto"><?= __('Simple pricing') ?></p>
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl"><?= __('Everything you need, one transparent plan.') ?></h2>
      <p class="mx-auto max-w-2xl text-lg text-slate-600 dark:text-slate-300"><?= __('Start with a 14-day free trial. Keep your data, cancel anytime—no hidden fees, ever.') ?></p>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
      <div class="panel space-y-5 border border-brand-200/70 bg-white/85 shadow-brand-glow dark:border-brand-500/40 dark:bg-slate-900/70">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-semibold text-slate-900 dark:text-white"><?= __('MoneyMap Pro') ?></h3>
          <span class="chip"><?= __('Most loved') ?></span>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-300"><?= __('Unlimited accounts, collaborative budgets, AI-powered insights, and concierge onboarding.') ?></p>
        <div class="flex items-baseline gap-2 text-slate-900 dark:text-white">
          <span class="text-4xl font-semibold">$14</span>
          <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400 dark:text-slate-500"><?= __('per month') ?></span>
        </div>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
          <li class="flex items-start gap-3"><span class="mt-1 text-brand-500">✔</span><span><?= __('Smart dashboards &amp; forecasting') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-brand-500">✔</span><span><?= __('Unlimited automation rules &amp; alerts') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-brand-500">✔</span><span><?= __('Joint workspaces for partners or advisors') ?></span></li>
          <li class="flex items-start gap-3"><span class="mt-1 text-brand-500">✔</span><span><?= __('Priority human support under 12 hours') ?></span></li>
        </ul>
        <a href="/register" class="btn btn-primary w-full justify-center text-base">
          <?= __('Start your free trial') ?>
        </a>
      </div>
      <div class="panel space-y-5">
        <h3 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Need more seats or corporate features?') ?></h3>
        <p class="text-sm text-slate-600 dark:text-slate-300"><?= __('MoneyMap Enterprise includes dedicated success managers, audit-ready exports, and SSO.') ?></p>
        <a href="mailto:hello@mymoneymap.app" class="btn btn-ghost w-full justify-center text-base">
          <?= __('Talk to sales') ?>
        </a>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl border border-white/60 bg-white/70 p-4 text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
            <h4 class="mb-1 font-semibold text-slate-900 dark:text-white"><?= __('SOC 2 on the roadmap') ?></h4>
            <p><?= __('We partner with industry leaders to guarantee compliance and trust at scale.') ?></p>
          </div>
          <div class="rounded-2xl border border-white/60 bg-white/70 p-4 text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
            <h4 class="mb-1 font-semibold text-slate-900 dark:text-white"><?= __('Integrations that delight') ?></h4>
            <p><?= __('Connect with 18,000+ financial institutions plus tools like Slack and Notion.') ?></p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="rounded-[2.75rem] border border-white/70 bg-white/80 px-8 py-12 text-center shadow-glass backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70">
    <p class="chip mx-auto"><?= __('Ready when you are') ?></p>
    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
      <?= __('Take back control of your money in less than 10 minutes.') ?>
    </h2>
    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 dark:text-slate-300">
      <?= __('Join thousands of calm, confident MoneyMappers transforming their finances with clarity. Start free, invite your partner, and keep your data for life.') ?>
    </p>
    <div class="mt-8 flex flex-wrap justify-center gap-4">
      <a href="/register" class="btn btn-primary px-8 py-3 text-base"><?= __('Create your account') ?></a>
      <a href="/login" class="btn btn-ghost px-8 py-3 text-base"><?= __('I already have an account') ?></a>
    </div>
  </section>
</div>
