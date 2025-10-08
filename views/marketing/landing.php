<?php
$appMeta = app_config('app') ?? [];
$appName = $appMeta['name'] ?? 'MyMoneyMap';
?>
<div class="space-y-24 bg-gradient-to-b from-[#f8fbff] via-white to-white pb-24">
  <header class="mt-8">
    <nav class="mx-auto flex max-w-6xl items-center justify-between rounded-full border border-slate-200/70 bg-white/90 px-6 py-4 shadow-lg shadow-slate-200/60 backdrop-blur">
      <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight text-slate-900">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-200 via-white to-emerald-200 text-white shadow-lg">
          <img src="/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="h-9 w-9 object-contain" />
        </div>
        <span><?= htmlspecialchars($appName) ?></span>
      </a>
      <div class="hidden items-center gap-3 text-sm font-medium text-slate-600 lg:flex">
        <a href="#features" class="rounded-full px-4 py-2 transition hover:bg-slate-50 hover:text-slate-900"><?= __('Features') ?></a>
        <a href="#wellbeing" class="rounded-full px-4 py-2 transition hover:bg-slate-50 hover:text-slate-900"><?= __('Wellbeing rituals') ?></a>
        <a href="#testimonials" class="rounded-full px-4 py-2 transition hover:bg-slate-50 hover:text-slate-900"><?= __('Stories') ?></a>
        <a href="#pricing" class="rounded-full px-4 py-2 transition hover:bg-slate-50 hover:text-slate-900"><?= __('Pricing') ?></a>
        <a href="/subscriptions" class="rounded-full px-4 py-2 transition hover:bg-slate-50 hover:text-slate-900"><?= __('Subscriptions') ?></a>
      </div>
      <div class="flex items-center gap-3 text-sm font-medium text-slate-600">
        <a href="/login" class="rounded-full border border-slate-200 px-4 py-2 text-slate-700 transition hover:border-sky-200 hover:text-sky-700">
          <?= __('Log in') ?>
        </a>
        <a href="/register" class="btn btn-primary hidden bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500 px-5 py-2 text-white shadow-md transition hover:shadow-lg sm:inline-flex">
          <?= __('Start free trial') ?>
        </a>
      </div>
    </nav>
  </header>

  <section class="relative overflow-hidden">
    <div class="mx-auto grid max-w-6xl gap-12 rounded-[3rem] border border-slate-200/70 bg-white/90 px-8 py-16 shadow-xl shadow-slate-200/70 lg:grid-cols-[1.1fr_minmax(0,0.9fr)]">
      <div class="space-y-8">
        <span class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-4 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-sky-700 shadow-sm">
          <?= __('Personal wellbeing finance') ?>
        </span>
        <h1 class="text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
          <?= __('Feel certain about your personal money path.') ?>
        </h1>
        <p class="text-lg leading-relaxed text-slate-600">
          <?= __('MoneyMap keeps everyday finances calm and intentional. Build nourishing routines, celebrate the progress you feel, and keep every goal in clear, confident view.') ?>
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="/register" class="btn btn-primary text-base px-6 py-3">
            <?= __('Start tracking for free') ?>
          </a>
          <a href="#login-card" class="btn btn-ghost text-base px-6 py-3">
            <?= __('Sign in to your account') ?>
          </a>
        </div>
        <div class="grid gap-6 sm:grid-cols-3">
          <div class="space-y-2">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('Trusted members') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-slate-900">
              <i data-lucide="shield" class="h-6 w-6 text-sky-500"></i>
              120K+
            </dd>
            <p class="text-xs text-slate-500"><?= __('Protected with bank-grade security and gentle reminders.') ?></p>
          </div>
          <div class="space-y-2">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('Goals celebrated') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-slate-900">
              <i data-lucide="sparkles" class="h-6 w-6 text-emerald-500"></i>
              460K
            </dd>
            <p class="text-xs text-slate-500"><?= __('Every win is saved to your personal gratitude timeline.') ?></p>
          </div>
          <div class="space-y-2">
            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('Average calm score') ?></dt>
            <dd class="flex items-center gap-2 text-3xl font-semibold text-slate-900">
              <i data-lucide="smile" class="h-6 w-6 text-amber-500"></i>
              9.4/10
            </dd>
            <p class="text-xs text-slate-500"><?= __('Members feel more present with their money habits.') ?></p>
          </div>
        </div>
      </div>
      <div id="login-card" class="lg:pl-8">
        <div class="space-y-4 rounded-[2rem] border border-slate-200 bg-white p-8 shadow-lg shadow-slate-200">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-slate-400"><?= __('Welcome back') ?></p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900"><?= __('Log in securely') ?></h2>
            <p class="mt-2 text-sm text-slate-500"><?= __('Encrypted sessions, biometric-friendly, and privacy by default.') ?></p>
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
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">
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
                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">
                  <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
              </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
              <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-200 text-sky-600 focus:ring-sky-400" />
              <span><?= __('Keep me signed in') ?></span>
            </label>
            <button class="btn btn-primary w-full justify-center text-base bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500">
              <?= __('Log in securely') ?>
            </button>
            <p class="text-center text-xs text-slate-400"><?= __('By continuing you agree to the Terms & Privacy Policy.') ?></p>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="px-6">
    <div class="mx-auto max-w-5xl space-y-12">
      <div class="space-y-3 text-center">
        <p class="chip mx-auto bg-emerald-50 text-emerald-600"><?= __('Why people choose :app', ['app' => $appName]) ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
          <?= __('Designed for personal goals, with calm technology you can trust.') ?>
        </h2>
        <p class="mx-auto max-w-3xl text-lg text-slate-600">
          <?= __('Every feature blends gentle guidance with private, secure automation so you can focus on the progress that matters to you and your loved ones.') ?>
        </p>
      </div>
      <div class="grid gap-6 md:grid-cols-2">
        <article class="tile space-y-4 border border-slate-200 bg-white shadow-sm">
          <div class="flex items-start gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-sky-50 text-sky-600">
              <i data-lucide="line-chart" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-slate-900"><?= __('One home for everyday finances') ?></h3>
              <p class="text-sm leading-relaxed text-slate-600">
                <?= __('Link banks, cards, and savings pots securely. Elegant charts and trends make it simple to understand how each decision supports your life.') ?>
              </p>
            </div>
          </div>
        </article>
        <article class="tile space-y-4 border border-slate-200 bg-white shadow-sm">
          <div class="flex items-start gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600">
              <i data-lucide="heart" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-slate-900"><?= __('Savings that feel encouraging') ?></h3>
              <p class="text-sm leading-relaxed text-slate-600">
                <?= __('Set intentions, track feelings, and receive gentle nudges that keep your personal wellbeing tied to every savings milestone.') ?>
              </p>
            </div>
          </div>
        </article>
        <article class="tile space-y-4 border border-slate-200 bg-white shadow-sm">
          <div class="flex items-start gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-amber-50 text-amber-600">
              <i data-lucide="notebook" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-slate-900"><?= __('Reflections beside the numbers') ?></h3>
              <p class="text-sm leading-relaxed text-slate-600">
                <?= __('Journaling prompts and mood check-ins live next to your transactions so you can notice patterns and celebrate growth without stress.') ?>
              </p>
            </div>
          </div>
        </article>
        <article class="tile space-y-4 border border-slate-200 bg-white shadow-sm">
          <div class="flex items-start gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-rose-50 text-rose-500">
              <i data-lucide="shield-check" class="h-5 w-5"></i>
            </span>
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-slate-900"><?= __('Private by design, always') ?></h3>
              <p class="text-sm leading-relaxed text-slate-600">
                <?= __('Your data is encrypted at rest and in transit, with optional passkeys and recovery codes to keep your personal finances protected.') ?>
              </p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section id="wellbeing" class="px-6">
    <div class="mx-auto max-w-5xl space-y-10 rounded-[2.5rem] border border-slate-200 bg-white px-10 py-14 shadow-xl">
      <div class="space-y-3 text-center">
        <p class="chip mx-auto bg-sky-50 text-sky-600"><?= __('Daily wellbeing rituals') ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
          <?= __('Create a rhythm that feels intentional every day.') ?>
        </h2>
        <p class="mx-auto max-w-3xl text-lg text-slate-600">
          <?= __('MoneyMap gently guides you through mindful check-ins that pair your emotions with your money choices.') ?>
        </p>
      </div>
      <div class="grid gap-8 lg:grid-cols-3">
        <div class="space-y-3">
          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 text-sky-600">
            <i data-lucide="sunrise" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Morning intention') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Set a focus for the day and review which habits support your energy and spending plan.') ?></p>
        </div>
        <div class="space-y-3">
          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <i data-lucide="sparkles" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Afternoon check-in') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Gentle reminders help you track spending moods and keep your goals nourishing, not rigid.') ?></p>
        </div>
        <div class="space-y-3">
          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600">
            <i data-lucide="moon" class="h-5 w-5"></i>
          </span>
          <h3 class="text-lg font-semibold text-slate-900"><?= __('Evening gratitude') ?></h3>
          <p class="text-sm text-slate-600"><?= __('Close the day celebrating wins, noting feelings, and planning next steps with clarity.') ?></p>
        </div>
      </div>
    </div>
  </section>

  <section id="testimonials" class="px-6">
    <div class="mx-auto max-w-5xl space-y-10">
      <div class="space-y-3 text-center">
        <p class="chip mx-auto bg-amber-50 text-amber-600"><?= __('Stories from our community') ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
          <?= __('Personal victories that feel steady and joyful.') ?>
        </h2>
      </div>
      <div class="grid gap-6 md:grid-cols-2">
        <figure class="rounded-3xl border border-slate-200 bg-white p-8 shadow-md">
          <div class="mb-4 flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-full bg-sky-100 text-sky-600">
              <i data-lucide="feather" class="h-5 w-5"></i>
            </span>
            <figcaption class="text-sm font-semibold text-slate-900"><?= __('Amelia, mindful saver') ?></figcaption>
          </div>
          <blockquote class="text-base text-slate-600">
            “<?= __('I finally see how my choices influence the calm I feel. The reflections beside each purchase changed how I plan my weeks.') ?>”
          </blockquote>
        </figure>
        <figure class="rounded-3xl border border-slate-200 bg-white p-8 shadow-md">
          <div class="mb-4 flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-600">
              <i data-lucide="leaf" class="h-5 w-5"></i>
            </span>
            <figcaption class="text-sm font-semibold text-slate-900"><?= __('Mateo, future planner') ?></figcaption>
          </div>
          <blockquote class="text-base text-slate-600">
            “<?= __('MoneyMap made saving for my wellness retreats feel human. It keeps me inspired without the stress or spreadsheets.') ?>”
          </blockquote>
        </figure>
      </div>
    </div>
  </section>

  <section id="pricing" class="px-6">
    <div class="mx-auto max-w-5xl space-y-10 rounded-[2.5rem] border border-slate-200 bg-white px-10 py-14 shadow-xl">
      <div class="space-y-3 text-center">
        <p class="chip mx-auto bg-slate-100 text-slate-600"><?= __('Plans made for personal growth') ?></p>
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
          <?= __('Flexible memberships that keep you encouraged.') ?>
        </h2>
      </div>
      <div class="grid gap-6 lg:grid-cols-2">
        <article class="space-y-5 rounded-3xl border border-slate-200 bg-white p-8 shadow-md">
          <header class="space-y-2">
            <p class="chip bg-emerald-50 text-emerald-600 w-fit"><?= __('Monthly membership') ?></p>
            <h3 class="text-2xl font-semibold text-slate-900"><?= __('MoneyMap Plus') ?></h3>
            <p class="text-sm text-slate-600"><?= __('All features with flexible commitment and personal guidance each week.') ?></p>
          </header>
          <div class="flex items-baseline gap-2 text-slate-900">
            <span class="text-4xl font-semibold">$14</span>
            <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per month') ?></span>
          </div>
          <ul class="space-y-3 text-sm text-slate-600">
            <li class="flex items-start gap-3">
              <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
              <span><?= __('Unlimited accounts, mindful automations, and ritual reminders.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
              <span><?= __('Guided reflections with optional journaling prompts.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span>
              <span><?= __('Human support responses under 12 hours.') ?></span>
            </li>
          </ul>
          <a href="/register" class="btn btn-primary w-full justify-center text-base bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500">
            <?= __('Start monthly plan') ?>
          </a>
        </article>
        <article class="space-y-5 rounded-3xl border border-sky-200 bg-gradient-to-br from-white via-sky-50 to-emerald-50 p-8 shadow-lg">
          <header class="space-y-2">
            <p class="chip bg-sky-50 text-sky-600 w-fit"><?= __('Annual membership') ?></p>
            <h3 class="text-2xl font-semibold text-slate-900"><?= __('MoneyMap Plus Annual') ?></h3>
            <p class="text-sm text-slate-600"><?= __('Best value for steady planners with seasonal wellbeing sessions and gratitude recaps.') ?></p>
          </header>
          <div class="flex items-baseline gap-2 text-slate-900">
            <span class="text-4xl font-semibold">$140</span>
            <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400"><?= __('per year') ?></span>
          </div>
          <ul class="space-y-3 text-sm text-slate-600">
            <li class="flex items-start gap-3">
              <span class="mt-1 text-sky-500"><i data-lucide="sparkles" class="h-4 w-4"></i></span>
              <span><?= __('Seasonal wellbeing workshops included.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-sky-500"><i data-lucide="gift" class="h-4 w-4"></i></span>
              <span><?= __('Exclusive templates for future plans and mindfulness checklists.') ?></span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-1 text-sky-500"><i data-lucide="heart" class="h-4 w-4"></i></span>
              <span><?= __('Quarterly gratitude summaries for your trusted circle.') ?></span>
            </li>
          </ul>
          <a href="/register" class="btn btn-primary w-full justify-center text-base bg-gradient-to-r from-sky-500 via-emerald-500 to-sky-500">
            <?= __('Start annual plan') ?>
          </a>
        </article>
      </div>
      <p class="text-center text-sm text-slate-500">
        <?= __('Every plan begins with a 14-day free trial. Cancel anytime in settings—your data stays yours.') ?>
      </p>
    </div>
  </section>

  <section class="px-6">
    <div class="mx-auto max-w-4xl space-y-6 text-center">
      <h2 class="text-3xl font-semibold tracking-tight text-slate-900">
        <?= __('Available in English, Español, and Magyar to support your journey worldwide.') ?>
      </h2>
      <p class="text-lg text-slate-600">
        <?= __('Switch languages anytime from your profile to enjoy mindful finances in the words that feel most like home.') ?>
      </p>
      <div class="flex flex-wrap justify-center gap-4 text-sm font-semibold text-slate-700">
        <span class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">
          <i data-lucide="globe-2" class="h-4 w-4 text-sky-500"></i>
          <?= __('English') ?>
        </span>
        <span class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">
          <i data-lucide="globe-2" class="h-4 w-4 text-emerald-500"></i>
          <?= __('Español') ?>
        </span>
        <span class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">
          <i data-lucide="globe-2" class="h-4 w-4 text-amber-500"></i>
          <?= __('Magyar') ?>
        </span>
      </div>
    </div>
  </section>
</div>
