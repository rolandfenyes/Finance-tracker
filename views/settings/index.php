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
    <div class="card-kicker">Profile</div>
    <h2 class="card-title mt-1">User</h2>
    <ul class="mt-3 card-list text-sm">
      <li class="list-row"><span>Email</span><span class="text-gray-700"><?= htmlspecialchars($user['email']) ?></span></li>
      <li class="list-row"><span>Name</span><span class="text-gray-700"><?= htmlspecialchars($user['full_name'] ?? '') ?></span></li>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/profile">Manage profile →</a>
    </p>
  </div>

  <!-- Currencies -->
  <div class="card">
    <div class="card-kicker">Preferences</div>
    <h2 class="card-title mt-1">Currencies</h2>
    <ul class="mt-3 card-list text-sm">
      <?php foreach($curr as $c): ?>
        <li class="list-row">
          <span><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['name']) ?></span>
          <?php if($c['is_main']): ?><span class="badge">Main</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/currencies">Manage currencies →</a>
    </p>
  </div>

  <?php if ($localeOptions): ?>
  <!-- Language -->
  <div class="card">
    <div class="card-kicker"><?= __('Preferences') ?></div>
    <h2 class="card-title mt-1"><?= __('Language') ?></h2>
    <p class="card-subtle mt-2"><?= __('Choose your preferred interface language.') ?></p>
    <div class="mt-4 rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-gray-50 p-3">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <?php foreach ($localeOptions as $code => $label): ?>
          <?php
          $isActive = $code === $currentLocale;
          $flag = $localeFlags[$code] ?? '🏳️';
          ?>
          <a
            href="<?= htmlspecialchars(url_with_lang($code), ENT_QUOTES) ?>"
            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition-all duration-200 <?= $isActive ? 'bg-gray-900 text-white border-gray-900 shadow-lg shadow-gray-900/15' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-50 hover:shadow-md' ?>"
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
    <div class="card-kicker">Planning</div>
    <h2 class="card-title mt-1">Cashflow Rules</h2>
    <p class="card-subtle mt-1">Define how each income is allocated (e.g., 50% needs, 20% savings).</p>
    <div class="card-subtle mt-3">
      <a class="text-accent" href="/settings/cashflow">Manage rules →</a>
    </div>
  </div>

  <!-- Basic incomes -->
  <div class="card">
    <div class="card-kicker">Income</div>
    <h2 class="card-title mt-1">Basic Incomes</h2>
    <ul class="mt-3 card-list text-sm">
      <?php foreach($basic as $b): ?>
        <li class="list-row">
          <span><?= htmlspecialchars($b['label']) ?> <span class="card-subtle">(from <?= htmlspecialchars($b['valid_from']) ?>)</span></span>
          <span class="font-medium"><?= moneyfmt($b['amount'], $b['currency'] ?? '') ?></span>
        </li>
      <?php endforeach; if (!count($basic)): ?>
        <li class="list-row card-subtle">No basic incomes yet.</li>
      <?php endif; ?>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/basic-incomes">Manage basic incomes →</a>
    </p>
  </div>

  <!-- Categories -->
  <div class="card">
    <div class="card-kicker">Transactions</div>
    <h2 class="card-title mt-1">Categories</h2>
    <p class="card-subtle mt-2">Add, rename, or remove income &amp; spending categories.</p>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/categories">Manage categories →</a>
    </p>
  </div>

  <!-- Tutorial -->
  <div class="card">
    <div class="card-kicker">Help</div>
    <h2 class="card-title mt-1">Tutorial</h2>
    <p class="card-subtle mt-2">New here? Learn how to use budgets, goals, loans, and more.</p>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/tutorial">Read tutorial →</a>
    </p>
  </div>
</section>
