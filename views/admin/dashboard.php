<?php
/** @var array $kpis */
/** @var array $recentActivity */
/** @var array $quickActions */
/** @var array $systemHealth */
/** @var array $featureSections */

$kpis = $kpis ?? [];
$recentActivity = $recentActivity ?? [];
$quickActions = $quickActions ?? [];
$systemHealth = $systemHealth ?? [];
$featureSections = $featureSections ?? [];

$statusStyles = [
    'success' => 'bg-emerald-100/80 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30',
    'warning' => 'bg-amber-100/80 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30',
    'info' => 'bg-sky-100/70 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:border-sky-500/30',
    'error' => 'bg-rose-100/80 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:border-rose-500/30',
    'operational' => 'bg-emerald-100/70 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30',
];

$sectionIcons = [
    'User Management' => 'users',
    'Plans & Subscriptions' => 'layers',
    'Billing & Invoices' => 'receipt',
    'Error & System Logs' => 'bug',
    'Analytics & Insights' => 'line-chart',
    'Support & Communication' => 'messages-square',
    'Developer & Maintenance Tools' => 'wrench',
    'Security & Access Control' => 'shield-check',
    'Automation' => 'bot',
    'Optional & Future Enhancements' => 'sparkles',
];
?>

<div class="space-y-10 pb-16">
  <section class="card relative overflow-hidden bg-white/70 dark:bg-slate-900/60">
    <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-brand-500/20 blur-3xl"></div>
    <div class="absolute -bottom-16 left-12 h-48 w-48 rounded-full bg-emerald-400/10 blur-2xl"></div>
    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
      <div class="max-w-2xl space-y-4">
        <span class="chip inline-flex items-center gap-2 bg-white/70 text-brand-deep/80 dark:bg-slate-900/70 dark:text-emerald-200">
          <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/20 text-brand-700 dark:bg-emerald-500/10 dark:text-emerald-200">
            <i data-lucide="shield" class="h-3.5 w-3.5"></i>
          </span>
          <?= htmlspecialchars(__('Admin Control Center')) ?>
        </span>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
          <?= htmlspecialchars(__('All signals, one dashboard.')) ?>
        </h1>
        <p class="text-base leading-relaxed text-slate-600 dark:text-slate-300">
          <?= htmlspecialchars(__('Monitor product health, keep subscriptions flowing, and support customers without leaving this space. Every control you need to operate MyMoneyMap at scale lives here.')) ?>
        </p>
      </div>
      <div class="grid gap-3 text-sm text-slate-600 dark:text-slate-300">
        <div class="flex items-center gap-3 rounded-2xl border border-white/70 bg-white/70 px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500/15 text-brand-700 dark:bg-emerald-500/10 dark:text-emerald-200">
            <i data-lucide="clock" class="h-5 w-5"></i>
          </span>
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">Next maintenance window</p>
            <p class="font-medium text-slate-900 dark:text-white">Sunday 02:00 UTC · 45 min</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-white/70 bg-white/70 px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500/15 text-brand-700 dark:bg-emerald-500/10 dark:text-emerald-200">
            <i data-lucide="users" class="h-5 w-5"></i>
          </span>
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">Admin team online</p>
            <p class="font-medium text-slate-900 dark:text-white">Support · Finance · DevOps</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($kpis)): ?>
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <?php foreach ($kpis as $kpi): ?>
        <article class="tile flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">
              <?= htmlspecialchars($kpi['label'] ?? '') ?>
            </p>
            <?php $trend = $kpi['trend'] ?? 'up'; ?>
            <span class="inline-flex items-center gap-1 rounded-full border border-white/70 bg-white/70 px-2 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
              <i data-lucide="<?= $trend === 'down' ? 'arrow-down-right' : 'arrow-up-right' ?>" class="h-3.5 w-3.5"></i>
              <?= htmlspecialchars($kpi['change'] ?? '') ?>
            </span>
          </div>
          <p class="text-3xl font-semibold text-slate-900 dark:text-white">
            <?= htmlspecialchars($kpi['value'] ?? '') ?>
          </p>
          <div class="h-2 overflow-hidden rounded-full bg-slate-200/60 dark:bg-slate-800">
            <div class="h-full rounded-full <?= $trend === 'down' ? 'bg-rose-500/80' : 'bg-emerald-500/80' ?>" style="width: <?= $trend === 'down' ? '40%' : '78%' ?>"></div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="grid gap-6 lg:grid-cols-[3fr,2fr]">
    <div class="card">
      <header class="flex items-center justify-between pb-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">Recent activity</p>
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">What's happening right now</h2>
        </div>
        <a href="#" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-emerald-200 dark:hover:text-emerald-100">View feed</a>
      </header>
      <div class="glass-stack">
        <?php foreach ($recentActivity as $item): ?>
          <?php $status = $item['status'] ?? 'info'; ?>
          <div class="glass-stack__item flex items-center gap-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl border border-white/60 bg-white/80 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
              <?= htmlspecialchars(substr((string)($item['type'] ?? ''), 0, 2)) ?>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                <?= htmlspecialchars($item['title'] ?? '') ?>
              </p>
              <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars($item['meta'] ?? '') ?>
              </p>
            </div>
            <div class="flex flex-col items-end gap-1 text-right">
              <span class="text-xs text-slate-400 dark:text-slate-500">
                <?= htmlspecialchars($item['time'] ?? '') ?>
              </span>
              <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider <?= $statusStyles[$status] ?? $statusStyles['info'] ?>">
                <?= htmlspecialchars($item['status'] ?? '') ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="space-y-6">
      <div class="card">
        <header class="flex items-center justify-between pb-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">Quick actions</p>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Jump straight into work</h2>
          </div>
          <a href="#" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-emerald-200 dark:hover:text-emerald-100">View all</a>
        </header>
        <div class="grid gap-4">
          <?php foreach ($quickActions as $action): ?>
            <a href="<?= htmlspecialchars($action['href'] ?? '#', ENT_QUOTES) ?>" class="group flex items-start gap-4 rounded-2xl border border-white/70 bg-white/70 p-4 transition hover:border-brand-200 hover:bg-brand-50/70 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:border-emerald-500/30 dark:hover:bg-slate-900/70">
              <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-500/10 text-lg"><?= htmlspecialchars($action['icon'] ?? '⚡') ?></span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 transition group-hover:text-brand-700 dark:text-white dark:group-hover:text-emerald-200">
                  <?= htmlspecialchars($action['label'] ?? '') ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  <?= htmlspecialchars($action['description'] ?? '') ?>
                </p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card">
        <header class="flex items-center justify-between pb-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">System health</p>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Infrastructure overview</h2>
          </div>
          <a href="#" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-emerald-200 dark:hover:text-emerald-100">Status page</a>
        </header>
        <div class="grid gap-4 sm:grid-cols-2">
          <?php foreach ($systemHealth as $health): ?>
            <?php $status = $health['status'] ?? 'operational'; ?>
            <div class="rounded-2xl border border-white/70 bg-white/70 p-4 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
              <div class="flex items-center justify-between">
                <p class="font-semibold text-slate-900 dark:text-white">
                  <?= htmlspecialchars($health['label'] ?? '') ?>
                </p>
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider <?= $statusStyles[$status] ?? $statusStyles['operational'] ?>">
                  <?= htmlspecialchars($health['status'] ?? '') ?>
                </span>
              </div>
              <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                <?= htmlspecialchars($health['value'] ?? '') ?>
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= htmlspecialchars($health['detail'] ?? '') ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($featureSections)): ?>
    <section class="space-y-8">
      <div class="flex flex-col gap-2">
        <p class="text-xs uppercase tracking-widest text-slate-500 dark:text-slate-400">Capabilities</p>
        <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Every tool your teams rely on</h2>
        <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
          <?= htmlspecialchars(__('Deep controls span the entire business — from billing and plan configuration to incident response, automation, and forward-looking insights. Use these sections as launchpads to dive deeper.')) ?>
        </p>
      </div>
      <div class="grid gap-6 lg:grid-cols-2">
        <?php foreach ($featureSections as $section): ?>
          <?php
            $title = (string)($section['title'] ?? '');
            $icon = $sectionIcons[$title] ?? 'app-window';
          ?>
          <article class="card flex flex-col gap-5">
            <header class="flex items-start justify-between gap-4">
              <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                  <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" class="h-5 w-5"></i>
                </span>
                <div>
                  <h3 class="text-xl font-semibold text-slate-900 dark:text-white">
                    <?= htmlspecialchars($title) ?>
                  </h3>
                  <p class="text-sm text-slate-600 dark:text-slate-300">
                    <?= htmlspecialchars($section['description'] ?? '') ?>
                  </p>
                </div>
              </div>
              <button type="button" class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500 transition hover:border-brand-200 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300 dark:hover:border-emerald-500/40 dark:hover:text-emerald-200">
                <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                <?= htmlspecialchars(__('Open module')) ?>
              </button>
            </header>
            <ul class="grid gap-3">
              <?php foreach (($section['items'] ?? []) as $feature): ?>
                <li class="group flex items-start gap-3 rounded-2xl border border-white/70 bg-white/70 p-3 transition hover:border-brand-200 hover:bg-brand-50/60 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:border-emerald-500/30 dark:hover:bg-slate-900/70">
                  <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/10 text-[11px] font-bold uppercase tracking-wide text-brand-600 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <?= htmlspecialchars(substr((string)($feature['title'] ?? ''), 0, 1)) ?>
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 transition group-hover:text-brand-700 dark:text-white dark:group-hover:text-emerald-200">
                      <?= htmlspecialchars($feature['title'] ?? '') ?>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                      <?= htmlspecialchars($feature['summary'] ?? '') ?>
                    </p>
                  </div>
                  <?php if (!empty($feature['badge'])): ?>
                    <span class="ml-auto inline-flex items-center rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-600 dark:text-slate-300">
                      <?= htmlspecialchars($feature['badge']) ?>
                    </span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
