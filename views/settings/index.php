<section class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
  <!-- User -->
  <div class="card">
    <div class="card-kicker"><?= htmlspecialchars(__('settings.index.profile_kicker')) ?></div>
    <h2 class="card-title mt-1"><?= htmlspecialchars(__('settings.index.user_title')) ?></h2>
    <ul class="mt-3 card-list text-sm">
      <li class="list-row"><span><?= htmlspecialchars(__('settings.index.email')) ?></span><span class="text-gray-700"><?= htmlspecialchars($user['email']) ?></span></li>
      <li class="list-row"><span><?= htmlspecialchars(__('settings.index.name')) ?></span><span class="text-gray-700"><?= htmlspecialchars($user['full_name'] ?? '') ?></span></li>
    </ul>
  </div>

  <!-- Currencies -->
  <div class="card">
    <div class="card-kicker"><?= htmlspecialchars(__('settings.index.preferences_kicker')) ?></div>
    <h2 class="card-title mt-1"><?= htmlspecialchars(__('settings.index.currencies_title')) ?></h2>
    <ul class="mt-3 card-list text-sm">
      <?php foreach($curr as $c): ?>
        <li class="list-row">
          <span><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['name']) ?></span>
          <?php if($c['is_main']): ?><span class="badge"><?= htmlspecialchars(__('settings.index.main_badge')) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/currencies"><?= htmlspecialchars(__('settings.index.currencies_manage')) ?></a>
    </p>
  </div>

  <!-- Cashflow -->
  <div class="card">
    <div class="card-kicker"><?= htmlspecialchars(__('settings.index.planning_kicker')) ?></div>
    <h2 class="card-title mt-1"><?= htmlspecialchars(__('settings.index.cashflow_title')) ?></h2>
    <p class="card-subtle mt-1"><?= htmlspecialchars(__('settings.index.cashflow_description')) ?></p>
    <div class="card-subtle mt-3">
      <a class="text-accent" href="/settings/cashflow"><?= htmlspecialchars(__('settings.index.cashflow_manage')) ?></a>
    </div>
  </div>

  <!-- Basic incomes -->
  <div class="card">
    <div class="card-kicker"><?= htmlspecialchars(__('settings.index.income_kicker')) ?></div>
    <h2 class="card-title mt-1"><?= htmlspecialchars(__('settings.index.basic_incomes_title')) ?></h2>
    <ul class="mt-3 card-list text-sm">
      <?php foreach($basic as $b): ?>
        <li class="list-row">
          <span><?= htmlspecialchars($b['label']) ?> <span class="card-subtle"><?= htmlspecialchars(__('settings.index.basic_income_from', ['date' => $b['valid_from']] )) ?></span></span>
          <span class="font-medium"><?= moneyfmt($b['amount'], $b['currency'] ?? '') ?></span>
        </li>
      <?php endforeach; if (!count($basic)): ?>
        <li class="list-row card-subtle"><?= htmlspecialchars(__('settings.index.basic_incomes_none')) ?></li>
      <?php endif; ?>
    </ul>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/basic-incomes"><?= htmlspecialchars(__('settings.index.basic_incomes_manage')) ?></a>
    </p>
  </div>

  <!-- Categories -->
  <div class="card">
    <div class="card-kicker"><?= htmlspecialchars(__('settings.index.transactions_kicker')) ?></div>
    <h2 class="card-title mt-1"><?= htmlspecialchars(__('settings.index.categories_title')) ?></h2>
    <p class="card-subtle mt-2"><?= htmlspecialchars(__('settings.index.categories_description')) ?></p>
    <p class="card-subtle mt-3">
      <a class="text-accent" href="/settings/categories"><?= htmlspecialchars(__('settings.index.categories_manage')) ?></a>
    </p>
  </div>

</section>
