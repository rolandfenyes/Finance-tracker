<?php
$localeOptions = available_locales();
$currentLocale = app_locale();
$localeIndicators = [
  'en' => ['icon' => 'languages', 'abbr' => 'EN'],
  'hu' => ['icon' => 'book', 'abbr' => 'HU'],
  'es' => ['icon' => 'sparkle', 'abbr' => 'ES'],
  'el' => ['icon' => 'globe-2', 'abbr' => 'EL'],
];
$activeIndicator = $localeIndicators[$currentLocale] ?? ['icon' => 'languages', 'abbr' => strtoupper($currentLocale)];
$activeLabel = $localeOptions[$currentLocale] ?? strtoupper($currentLocale);

$demoCards = [
  [
    'title' => 'Dashboard overview',
    'description' => 'Monthly totals, balances, and a friendly summary that keeps you grounded.',
  ],
  [
    'title' => 'Categories & monthly summary',
    'description' => 'Color-coded spending that shows trends without digging into spreadsheets.',
  ],
  [
    'title' => 'Goals progress view',
    'description' => 'Track savings targets with motivating progress bars and helpful reminders.',
  ],
  [
    'title' => 'Emergency Fund tracker',
    'description' => 'Visualize your 3-month buffer so you know exactly how prepared you are.',
  ],
  [
    'title' => 'Loans overview',
    'description' => 'Understand principal vs. interest with payoff forecasts you can trust.',
  ],
  [
    'title' => 'Currencies view',
    'description' => 'See balances in HUF, EUR, USD, and more with daily FX conversions applied.',
  ],
];

$heroHighlights = [
  ['icon' => 'layout-dashboard', 'label' => 'Smart dashboards'],
  ['icon' => 'bell-ring', 'label' => 'Scheduled insights'],
  ['icon' => 'shield-check', 'label' => 'Encrypted storage'],
];

$features = [
  [
    'title' => 'Cashflow rules',
    'description' => 'Automate monthly budget allocations so needs, wants, and savings stay on target.',
    'icon' => 'sliders-horizontal',
    'tag' => 'Automation',
  ],
  [
    'title' => 'Categories',
    'description' => 'Organize expenses with vibrant categories, rollups, and insights at a glance.',
    'icon' => 'grid',
    'tag' => 'Organization',
  ],
  [
    'title' => 'Basic incomes',
    'description' => 'Track salary and side hustles, comparing expected vs. actual instantly.',
    'icon' => 'badge-dollar-sign',
    'tag' => 'Automation',
  ],
  [
    'title' => 'Currencies',
    'description' => 'Multi-currency support (HUF, EUR, USD) with daily FX for accurate reports.',
    'icon' => 'globe-2',
    'tag' => 'Organization',
  ],
  [
    'title' => 'Months',
    'description' => 'Open or close months, compare periods, and follow trends over time.',
    'icon' => 'calendar-range',
    'tag' => 'Organization',
  ],
  [
    'title' => 'Goals',
    'description' => 'Set savings targets, add deadlines, and watch motivation grow as you progress.',
    'icon' => 'target',
    'tag' => 'Security',
  ],
  [
    'title' => 'Loans',
    'description' => 'Manage amortization with interest vs. principal charts and payoff tracking.',
    'icon' => 'landmark',
    'tag' => 'Security',
  ],
  [
    'title' => 'Emergency Fund',
    'description' => 'Build a resilient 3-month buffer and keep stability in sight.',
    'icon' => 'life-buoy',
    'tag' => 'Security',
  ],
  [
    'title' => 'Scheduled transactions',
    'description' => 'Never miss recurring bills—plan ahead for everything coming your way.',
    'icon' => 'alarm-clock',
    'tag' => 'Automation',
  ],
];

$faqItems = [
  ['q' => 'Is MyMoneyMap safe?', 'a' => 'Yes. Your financial data is encrypted with your private key so it stays yours.', 'icon' => 'shield-check'],
  ['q' => 'Do I need to connect my bank account?', 'a' => 'No. You can add or import transactions manually whenever you like.', 'icon' => 'link-2-off'],
  ['q' => 'How does the 14-day trial work?', 'a' => 'Try Pro or Premium for 14 days, cancel anytime—no credit card required.', 'icon' => 'calendar-clock'],
  ['q' => 'What’s the difference between Pro and Premium?', 'a' => 'Premium includes everything in Pro plus stock and investment tracking.', 'icon' => 'layers'],
  ['q' => 'Do you support multiple currencies?', 'a' => 'Yes. MyMoneyMap works with daily exchange rates for accurate conversions.', 'icon' => 'globe-2'],
  ['q' => 'Can I export my data?', 'a' => 'Absolutely. CSV and JSON exports are available whenever you need them.', 'icon' => 'download'],
  ['q' => 'Is it available in Hungarian and other languages?', 'a' => 'Yes. Switch between English, Hungarian, Spanish, and Greek instantly.', 'icon' => 'languages'],
  ['q' => 'What platforms can I use it on?', 'a' => 'Use MyMoneyMap in any modern browser on desktop or mobile.', 'icon' => 'monitor-smartphone'],
];
?>
<div class="relative isolate overflow-hidden bg-gradient-to-br from-white via-emerald-50 to-white">
  <div class="absolute inset-0 -z-10 opacity-60">
    <div class="absolute -top-24 left-24 h-40 w-40 rounded-full bg-emerald-200 blur-3xl"></div>
    <div class="absolute bottom-0 right-0 h-60 w-60 rounded-full bg-emerald-100 blur-3xl"></div>
  </div>
  <div class="relative mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 pb-24 pt-20 sm:pt-24 lg:px-16">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
      <a href="/" class="inline-flex items-center gap-3 text-lg font-semibold text-slate-900">
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/90 shadow-brand-glow">
          <img src="/logo.png" alt="MyMoneyMap logo" class="h-10 w-10" />
        </span>
        <span class="text-xl font-semibold">MyMoneyMap</span>
      </a>
      <div class="flex items-center gap-3">
        <div x-data="{ open:false }" class="relative">
          <button
            @click="open = !open"
            @keydown.escape.window="open=false"
            class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200 bg-white/80 px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm backdrop-blur hover:bg-white"
            aria-label="Change language"
          >
            <span class="flex h-5 w-5 items-center justify-center text-emerald-500">
              <i data-lucide="<?= htmlspecialchars($activeIndicator['icon']) ?>" class="h-4 w-4"></i>
            </span>
            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.2em] text-emerald-700">
              <?= htmlspecialchars($activeIndicator['abbr']) ?>
            </span>
            <span><?= htmlspecialchars($activeLabel) ?></span>
            <svg class="h-4 w-4 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 11.188l3.71-3.958a.75.75 0 111.1 1.02l-4.25 4.53a.75.75 0 01-1.1 0l-4.25-4.53a.75.75 0 01.02-1.06z" /></svg>
          </button>
          <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            @click.outside="open=false"
            class="absolute right-0 mt-3 w-56 rounded-2xl border border-emerald-100 bg-white/95 p-2 shadow-2xl backdrop-blur"
          >
            <div class="grid gap-1 text-sm">
              <?php foreach ($localeOptions as $code => $label): ?>
                <?php
                  $indicator = $localeIndicators[$code] ?? ['icon' => 'languages', 'abbr' => strtoupper($code)];
                  $isActive = $code === $currentLocale;
                ?>
                <a
                  href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
                  class="flex items-center gap-2 rounded-xl px-2 py-2 <?= $isActive ? 'bg-emerald-500 text-white shadow-brand-glow' : 'text-slate-700 hover:bg-emerald-50' ?>"
                  aria-current="<?= $isActive ? 'true' : 'false' ?>"
                >
                  <span class="flex h-5 w-5 items-center justify-center <?= $isActive ? 'text-white' : 'text-emerald-500' ?>">
                    <i data-lucide="<?= htmlspecialchars($indicator['icon']) ?>" class="h-4 w-4"></i>
                  </span>
                  <span class="rounded-full border <?= $isActive ? 'border-white/40 bg-white/20 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-700' ?> px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.2em]">
                    <?= htmlspecialchars($indicator['abbr']) ?>
                  </span>
                  <span class="flex-1"><?= htmlspecialchars($label) ?></span>
                  <?php if ($isActive): ?><span class="text-xs">●</span><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <a href="/login" class="text-sm font-semibold text-slate-700 hover:text-emerald-600">Sign in</a>
        <a href="/register?plan=free" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:bg-emerald-600">
          Start Free
        </a>
      </div>
    </div>

    <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-16">
      <div class="space-y-8">
        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm">Clarity for every euro</div>
        <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
          Your money. Clear, simple, visual.
        </h1>
        <p class="max-w-xl text-lg text-slate-600 sm:text-xl">
          Understand where your money goes and build savings faster with MyMoneyMap. Budgets, goals, loans, and currencies—all beautifully connected.
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="/register?plan=free" class="inline-flex items-center gap-3 rounded-full bg-emerald-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-500/40 transition hover:bg-emerald-700">
            <span>Start Free</span>
            <span aria-hidden="true">→</span>
          </a>
          <a href="/demo" class="inline-flex items-center gap-3 rounded-full border border-emerald-300 bg-white/80 px-6 py-3 text-base font-semibold text-emerald-700 transition hover:border-emerald-400 hover:text-emerald-800">
            Try the Demo
          </a>
        </div>
        <p class="text-sm font-medium text-slate-500">14-day free trial on Pro &amp; Premium plans.</p>
        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500">
          <?php foreach ($heroHighlights as $highlight): ?>
            <span class="inline-flex items-center gap-2 rounded-full bg-white/75 px-3 py-1 shadow-sm">
              <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <i data-lucide="<?= htmlspecialchars($highlight['icon']) ?>" class="h-4 w-4"></i>
              </span>
              <?= htmlspecialchars($highlight['label']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="relative">
        <div class="absolute -inset-x-12 -inset-y-12 rounded-full bg-emerald-200/40 blur-3xl"></div>
        <div class="relative rounded-3xl border border-white/80 bg-white/80 p-6 shadow-2xl backdrop-blur-lg">
          <div class="mb-6 flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-slate-500">Monthly cashflow</p>
              <p class="text-2xl font-semibold text-slate-900">€3,840</p>
            </div>
            <span class="rounded-full bg-emerald-500/10 px-4 py-1 text-sm font-semibold text-emerald-600">+12.4%</span>
          </div>
          <div class="grid gap-4">
            <div class="rounded-2xl bg-emerald-500/10 p-4">
              <p class="text-sm font-semibold text-emerald-700">Spending</p>
              <div class="mt-3 flex items-center justify-between text-sm text-slate-600">
                <span>Housing</span><span>€1,120</span>
              </div>
              <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                <div class="h-full w-3/4 rounded-full bg-emerald-500"></div>
              </div>
              <div class="mt-3 flex items-center justify-between text-sm text-slate-600">
                <span>Food</span><span>€640</span>
              </div>
              <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                <div class="h-full w-2/5 rounded-full bg-emerald-400"></div>
              </div>
            </div>
            <div class="grid gap-3 rounded-2xl border border-emerald-100 bg-white/70 p-4">
              <div class="flex items-center justify-between text-sm text-slate-500">
                <span>Emergency fund</span>
                <span class="font-semibold text-emerald-600">€4,500 / €6,000</span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                <div class="h-full w-3/4 rounded-full bg-emerald-500"></div>
              </div>
              <div class="flex items-center justify-between text-sm text-slate-500">
                <span>Loan payoff</span>
                <span class="font-semibold text-emerald-600">36% complete</span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                <div class="h-full w-1/3 rounded-full bg-emerald-400"></div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-2xl border border-emerald-100 bg-white/70 p-4 text-sm text-slate-500">
                <p class="font-semibold text-slate-700">Goals this month</p>
                <p class="mt-2 text-emerald-600">3 of 4 on track</p>
              </div>
              <div class="rounded-2xl border border-emerald-100 bg-white/70 p-4 text-sm text-slate-500">
                <p class="font-semibold text-slate-700">Currencies</p>
                <p class="mt-2">HUF · EUR · USD</p>
              </div>
            </div>
          </div>
        </div>
        <div class="pointer-events-none absolute -top-10 left-1/2 hidden -translate-x-1/2 -rotate-6 items-center gap-2 rounded-2xl border border-emerald-100 bg-white/80 px-4 py-2 text-xs font-semibold text-emerald-600 shadow-lg md:flex">
          <span class="flex h-4 w-4 items-center justify-center text-emerald-500">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
          </span>
          Budget automation active
        </div>
      </div>
    </div>
  </div>
</div>

<section id="demo" class="relative mx-auto w-full max-w-7xl px-6 py-20 lg:px-16">
  <div class="mx-auto max-w-2xl text-center">
    <h2 class="text-3xl font-semibold text-slate-900 sm:text-4xl">See it in action</h2>
    <p class="mt-3 text-lg text-slate-600">Visual clarity for your finances — at a glance.</p>
  </div>
  <div class="mt-12 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($demoCards as $card): ?>
      <article class="group relative overflow-hidden rounded-3xl border border-emerald-100 bg-white/80 p-6 shadow-lg shadow-emerald-500/10 transition hover:-translate-y-1 hover:shadow-brand-glow">
        <div class="absolute -top-32 right-0 h-64 w-64 rounded-full bg-emerald-200/40 blur-3xl transition group-hover:scale-110"></div>
        <div class="relative flex h-full flex-col gap-4">
          <div class="grid gap-2 rounded-2xl border border-white/60 bg-white/70 p-4 shadow-inner">
            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-600">
              <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <i data-lucide="line-chart" class="h-4 w-4"></i>
              </span>
              <span><?= htmlspecialchars($card['title']) ?></span>
            </div>
            <div class="grid gap-2 text-slate-500">
              <div class="h-2 w-full rounded-full bg-emerald-100"></div>
              <div class="flex gap-2">
                <div class="h-20 flex-1 rounded-2xl bg-gradient-to-tr from-emerald-200 via-emerald-100 to-white"></div>
                <div class="h-20 flex-1 rounded-2xl bg-gradient-to-tr from-emerald-100 via-white to-emerald-50"></div>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <div class="h-16 rounded-2xl bg-emerald-50"></div>
                <div class="h-16 rounded-2xl bg-emerald-100"></div>
                <div class="h-16 rounded-2xl bg-emerald-200"></div>
              </div>
            </div>
          </div>
          <p class="relative text-sm text-slate-600"><?= htmlspecialchars($card['description']) ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="relative mx-auto w-full max-w-7xl px-6 py-20 lg:px-16">
  <div class="mx-auto max-w-2xl text-center">
    <h2 class="text-3xl font-semibold text-slate-900 sm:text-4xl">Everything you need to stay ahead</h2>
    <p class="mt-3 text-lg text-slate-600">Nine focused features to automate, organize, and secure your finances.</p>
  </div>
  <div class="mt-12 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($features as $feature): ?>
      <article class="group h-full rounded-3xl border border-emerald-100 bg-white/80 p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-brand-glow">
        <div class="flex h-full flex-col gap-4">
          <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
            <i data-lucide="<?= htmlspecialchars($feature['icon']) ?>" class="h-6 w-6"></i>
          </span>
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-500/80"><?= htmlspecialchars($feature['tag']) ?></p>
            <h3 class="mt-2 text-xl font-semibold text-slate-900"><?= htmlspecialchars($feature['title']) ?></h3>
          </div>
          <p class="text-sm text-slate-600 flex-1"><?= htmlspecialchars($feature['description']) ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="relative mx-auto w-full max-w-7xl px-6 py-20 lg:px-16" x-data="{ billing: 'monthly' }">
  <div class="mx-auto max-w-3xl text-center">
    <h2 class="text-3xl font-semibold text-slate-900 sm:text-4xl">Simple, transparent pricing.</h2>
    <p class="mt-3 text-lg text-slate-600">Choose monthly or yearly billing. Either way, your first 14 days are on us.</p>
  </div>
  <div class="mt-10 flex items-center justify-center gap-3">
    <button
      type="button"
      @click="billing = 'monthly'"
      :class="billing === 'monthly' ? 'bg-emerald-600 text-white shadow-brand-glow' : 'bg-white/60 text-slate-600'"
      class="rounded-full px-5 py-2 text-sm font-semibold transition"
    >
      Monthly
    </button>
    <button
      type="button"
      @click="billing = 'yearly'"
      :class="billing === 'yearly' ? 'bg-emerald-600 text-white shadow-brand-glow' : 'bg-white/60 text-slate-600'"
      class="rounded-full px-5 py-2 text-sm font-semibold transition"
    >
      Yearly (save 2 months)
    </button>
  </div>
  <div class="mt-12 grid gap-6 lg:grid-cols-3">
    <article class="flex h-full flex-col rounded-3xl border border-emerald-100 bg-white/80 p-8 shadow-sm">
      <div class="flex-1 space-y-5">
        <h3 class="text-2xl font-semibold text-slate-900">Free</h3>
        <p class="text-4xl font-bold text-emerald-600">€0</p>
        <p class="text-sm text-slate-600">Great to explore basic features.</p>
        <ul class="space-y-2 text-sm text-slate-600">
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> 1 currency</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Fixed cashflow rules</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Limited categories</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Limited goals &amp; loans</li>
          <li class="flex items-center gap-2 text-slate-400"><span><i data-lucide="minus" class="h-4 w-4"></i></span> No investments</li>
        </ul>
      </div>
      <a href="/register?plan=free" class="mt-8 inline-flex items-center justify-center rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-600">Start Free</a>
    </article>

    <article class="relative flex h-full flex-col overflow-hidden rounded-3xl border-2 border-emerald-500 bg-white p-8 shadow-brand-glow">
      <div class="absolute right-4 top-4 rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Most popular</div>
      <div class="flex-1 space-y-5">
        <h3 class="text-2xl font-semibold text-slate-900">Pro</h3>
        <p class="text-4xl font-bold text-emerald-600">
          <span x-show="billing === 'monthly'">€5</span>
          <span x-cloak x-show="billing === 'yearly'">€50</span>
          <span class="text-base font-medium text-slate-500" x-show="billing === 'monthly'">/month</span>
          <span class="text-base font-medium text-slate-500" x-cloak x-show="billing === 'yearly'">/year</span>
        </p>
        <p class="text-sm font-semibold text-emerald-600">Best for serious budgeters.</p>
        <p class="text-sm text-slate-600">14-day free trial included.</p>
        <ul class="space-y-2 text-sm text-slate-600">
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Unlimited currencies</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Unlimited categories</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Unlimited goals, loans, rules</li>
          <li class="flex items-center gap-2 text-slate-400"><span><i data-lucide="minus" class="h-4 w-4"></i></span> No investments</li>
        </ul>
      </div>
      <div class="mt-8 grid gap-3">
        <a
          :href="billing === 'monthly' ? '/register?plan=pro&cycle=monthly' : '/register?plan=pro&cycle=yearly'"
          class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-emerald-700"
        >
          Get Pro — 14-day free trial
        </a>
      </div>
    </article>

    <article class="flex h-full flex-col rounded-3xl border border-emerald-100 bg-white/80 p-8 shadow-sm">
      <div class="flex-1 space-y-5">
        <h3 class="text-2xl font-semibold text-slate-900">Premium</h3>
        <p class="text-4xl font-bold text-emerald-600">
          <span x-show="billing === 'monthly'">€7.5</span>
          <span x-cloak x-show="billing === 'yearly'">€75</span>
          <span class="text-base font-medium text-slate-500" x-show="billing === 'monthly'">/month</span>
          <span class="text-base font-medium text-slate-500" x-cloak x-show="billing === 'yearly'">/year</span>
        </p>
        <p class="text-sm font-semibold text-emerald-600">For investors and advanced planners.</p>
        <p class="text-sm text-slate-600">14-day free trial included.</p>
        <ul class="space-y-2 text-sm text-slate-600">
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Everything in Pro</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Investments (stocks, P/L, allocations)</li>
          <li class="flex items-center gap-2"><span class="text-emerald-500"><i data-lucide="check" class="h-4 w-4"></i></span> Insights for long-term planning</li>
        </ul>
      </div>
      <a
        :href="billing === 'monthly' ? '/register?plan=premium&cycle=monthly' : '/register?plan=premium&cycle=yearly'"
        class="mt-8 inline-flex items-center justify-center rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-600"
      >
        Get Premium — 14-day free trial
      </a>
    </article>
  </div>
  <p class="mt-6 text-center text-sm text-slate-500">Cancel anytime. Keep your data safe and exportable. All prices include VAT.</p>
</section>

<section class="relative mx-auto w-full max-w-7xl px-6 py-20 lg:px-16">
  <div class="mx-auto max-w-3xl text-center">
    <h2 class="text-3xl font-semibold text-slate-900 sm:text-4xl">Frequently asked questions</h2>
    <p class="mt-3 text-lg text-slate-600">Answers to the most common questions before you get started.</p>
  </div>
  <div class="mt-12 grid gap-4">
    <?php foreach ($faqItems as $faq): ?>
      <details class="group overflow-hidden rounded-3xl border border-emerald-100 bg-white/80 p-6 shadow-sm">
        <summary class="flex cursor-pointer items-center justify-between gap-4 text-left text-lg font-semibold text-slate-800">
          <span class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
              <i data-lucide="<?= htmlspecialchars($faq['icon']) ?>" class="h-5 w-5"></i>
            </span>
            <?= htmlspecialchars($faq['q']) ?>
          </span>
          <span class="text-emerald-500 transition group-open:rotate-45">+</span>
        </summary>
        <p class="mt-4 text-sm text-slate-600"><?= htmlspecialchars($faq['a']) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<section class="relative mx-auto w-full max-w-7xl px-6 pb-20 lg:px-16">
  <div class="relative overflow-hidden rounded-4xl bg-gradient-to-br from-emerald-600 via-emerald-500 to-emerald-400 px-8 py-16 text-white shadow-brand-glow">
    <div class="absolute -top-12 right-12 h-40 w-40 rounded-full bg-white/20 blur-3xl"></div>
    <div class="relative grid gap-10 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-center">
      <div class="space-y-6">
        <h2 class="text-4xl font-bold tracking-tight text-white">Financial calm starts here.</h2>
        <p class="text-lg text-white/90">Take control of your money today with MyMoneyMap. Your goals, cashflow, and peace of mind—together.</p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="/register?plan=free" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-base font-semibold text-emerald-700 shadow-md hover:bg-emerald-50">Start Free</a>
          <a href="/register?plan=pro" class="inline-flex items-center gap-2 rounded-full border border-white/70 px-6 py-3 text-base font-semibold text-white hover:bg-white/10">Get Pro (14-day trial)</a>
          <a href="/register?plan=premium" class="inline-flex items-center gap-2 rounded-full border border-white/70 px-6 py-3 text-base font-semibold text-white hover:bg-white/10">Get Premium (14-day trial)</a>
        </div>
      </div>
      <div class="rounded-3xl border border-white/40 bg-white/10 p-6 text-white backdrop-blur">
        <p class="text-sm uppercase tracking-[0.3em] text-white/80">You’ll love the calm</p>
        <div class="mt-4 space-y-3">
          <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-white">
              <i data-lucide="check-circle" class="h-4 w-4"></i>
            </span>
            <span>Cancel anytime</span>
          </div>
          <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-white">
              <i data-lucide="shield-check" class="h-4 w-4"></i>
            </span>
            <span>Private key encryption</span>
          </div>
          <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-white">
              <i data-lucide="globe-2" class="h-4 w-4"></i>
            </span>
            <span>Works everywhere</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="relative mx-auto w-full max-w-7xl px-6 pb-16 lg:px-16">
  <div class="grid gap-10 rounded-4xl border border-emerald-100 bg-white/80 p-10 shadow-sm">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)] lg:items-start">
      <div class="space-y-4">
        <a href="/" class="inline-flex items-center gap-3 text-lg font-semibold text-slate-900">
          <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/90 shadow-brand-glow">
            <img src="/logo.png" alt="MyMoneyMap logo" class="h-10 w-10" />
          </span>
          <span>MyMoneyMap</span>
        </a>
        <p class="max-w-sm text-sm text-slate-600">Clarity for your money. Built for people who want calm, confident financial decisions.</p>
      </div>
      <div class="grid gap-3 text-sm text-slate-600">
        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-500/80">Quick Links</p>
        <a href="/about" class="hover:text-emerald-600">About</a>
        <a href="/privacy" class="hover:text-emerald-600">Privacy Policy</a>
        <a href="/terms" class="hover:text-emerald-600">Terms of Service</a>
        <a href="/contact" class="hover:text-emerald-600">Contact</a>
        <a href="https://github.com/rolandcsaba/Finance-tracker" target="_blank" rel="noopener" class="hover:text-emerald-600">GitHub</a>
      </div>
      <div class="grid gap-3 text-sm text-slate-600">
        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-500/80">Languages</p>
        <div class="grid gap-2">
          <?php foreach ($localeOptions as $code => $label): ?>
            <?php $indicator = $localeIndicators[$code] ?? ['icon' => 'languages', 'abbr' => strtoupper($code)]; ?>
            <a href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>" class="inline-flex items-center gap-3 rounded-xl border border-emerald-100 px-3 py-2 <?= $code === $currentLocale ? 'bg-emerald-500 text-white shadow-brand-glow' : 'bg-white/70 hover:bg-emerald-50' ?>">
              <span class="flex h-5 w-5 items-center justify-center <?= $code === $currentLocale ? 'text-white' : 'text-emerald-500' ?>">
                <i data-lucide="<?= htmlspecialchars($indicator['icon']) ?>" class="h-4 w-4"></i>
              </span>
              <span class="rounded-full border <?= $code === $currentLocale ? 'border-white/40 bg-white/20 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-700' ?> px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.2em]">
                <?= htmlspecialchars($indicator['abbr']) ?>
              </span>
              <span><?= htmlspecialchars($label) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="flex items-center gap-3 pt-4 text-lg text-emerald-500">
          <a href="https://github.com/rolandcsaba/Finance-tracker" aria-label="GitHub" target="_blank" rel="noopener"><i data-lucide="github"></i></a>
          <a href="https://twitter.com" aria-label="Twitter" target="_blank" rel="noopener"><i data-lucide="twitter"></i></a>
          <a href="https://instagram.com" aria-label="Instagram" target="_blank" rel="noopener"><i data-lucide="instagram"></i></a>
        </div>
      </div>
    </div>
    <div class="flex flex-col gap-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
      <span>© 2025 MyMoneyMap. All rights reserved.</span>
      <span>Secure sessions &amp; encrypted storage by default.</span>
    </div>
  </div>
</footer>
