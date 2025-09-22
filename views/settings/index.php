<?php
$localeOptions = available_locales();
$currentLocale = app_locale();
$localeFlags = [
  'en' => '🇺🇸',
  'hu' => '🇭🇺',
  'es' => '🇪🇸',
];
?>
<section class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
  <!-- User -->
  <div class="card">
    <div class="card-kicker"><?= __('Profile') ?></div>
    <h2 class="card-title mt-1"><?= __('User') ?></h2>
    <ul class="mt-3 card-list text-sm">
      <li class="list-row"><span><?= __('Email') ?></span><span class="text-gray-700"><?= htmlspecialchars($user['email']) ?></span></li>
      <li class="list-row"><span><?= __('Name') ?></span><span class="text-gray-700"><?= htmlspecialchars($user['full_name'] ?? '') ?></span></li>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/profile"><?= __('Manage profile →') ?></a>
    </p>
  </div>

  <!-- Currencies -->
  <div class="card">
    <div class="card-kicker"><?= __('Preferences') ?></div>
    <h2 class="card-title mt-1"><?= __('Currencies') ?></h2>
    <ul class="mt-3 card-list text-sm">
      <?php foreach($curr as $c): ?>
        <li class="list-row">
          <span><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['name']) ?></span>
          <?php if($c['is_main']): ?><span class="badge"><?= __('Main') ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/currencies"><?= __('Manage currencies →') ?></a>
    </p>
  </div>

  <?php if ($localeOptions): ?>
  <!-- Language -->
  <div class="card">
    <div class="card-kicker"><?= __('Preferences') ?></div>
    <h2 class="card-title mt-1"><?= __('Language') ?></h2>
    <p class="card-subtle mt-2"><?= __('Choose your preferred interface language.') ?></p>
    <div class="mt-4 rounded-3xl border border-white/50 bg-white/70 p-3 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/50">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <?php foreach ($localeOptions as $code => $label): ?>
          <?php
          $isActive = $code === $currentLocale;
          $flag = $localeFlags[$code] ?? '🏳️';
          ?>
          <a
            href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
            class="flex items-center gap-2 rounded-2xl border px-3 py-2 text-sm font-medium transition-all duration-200 <?= $isActive ? 'border-brand-500 bg-brand-600 text-white shadow-brand-glow' : 'border-white/60 bg-white/60 text-slate-600 hover:border-brand-200 hover:bg-brand-50/70 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:hover:bg-slate-800/70' ?>"
            title="<?= htmlspecialchars($label) ?>"
            aria-current="<?= $isActive ? 'true' : 'false' ?>"
          >
            <span class="text-lg leading-none"><?= $flag ?></span>
            <span><?= htmlspecialchars($label) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Cashflow -->
  <div class="card">
    <div class="card-kicker"><?= __('Planning') ?></div>
    <h2 class="card-title mt-1"><?= __('Cashflow Rules') ?></h2>
    <p class="card-subtle mt-1"><?= __('Define how each income is allocated (e.g., 50% needs, 20% savings).') ?></p>
    <div class="card-subtle mt-3">
      <a class="text-accent" href="/settings/cashflow"><?= __('Manage rules →') ?></a>
    </div>
  </div>

  <!-- Basic incomes -->
  <div class="card">
    <div class="card-kicker"><?= __('Income') ?></div>
    <h2 class="card-title mt-1"><?= __('Basic Incomes') ?></h2>
    <ul class="mt-3 card-list text-sm">
      <?php foreach($basic as $b): ?>
        <li class="list-row">
          <span><?= htmlspecialchars($b['label']) ?> <span class="card-subtle"><?= __('(from :date)', ['date' => htmlspecialchars($b['valid_from'])]) ?></span></span>
          <span class="font-medium"><?= moneyfmt($b['amount'], $b['currency'] ?? '') ?></span>
        </li>
      <?php endforeach; if (!count($basic)): ?>
        <li class="list-row card-subtle"><?= __('No basic incomes yet.') ?></li>
      <?php endif; ?>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/basic-incomes"><?= __('Manage basic incomes →') ?></a>
    </p>
  </div>

  <!-- Categories -->
  <div class="card">
    <div class="card-kicker"><?= __('Transactions') ?></div>
    <h2 class="card-title mt-1"><?= __('Categories') ?></h2>
    <p class="card-subtle mt-2"><?= __('Add, rename, or remove income &amp; spending categories.') ?></p>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/categories"><?= __('Manage categories →') ?></a>
    </p>
  </div>

  <!-- Tutorial -->
  <div class="card">
    <div class="card-kicker"><?= __('Help') ?></div>
    <h2 class="card-title mt-1"><?= __('Tutorial') ?></h2>
    <p class="card-subtle mt-2"><?= __('New here? Learn how to use budgets, goals, loans, and more.') ?></p>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/tutorial"><?= __('Read tutorial →') ?></a>
    </p>
  </div>
</section>
