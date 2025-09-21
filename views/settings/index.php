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
