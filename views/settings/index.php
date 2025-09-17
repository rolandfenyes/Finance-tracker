<section class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">User</h2>
    <p class="text-sm text-gray-500">Email: <?= htmlspecialchars($user['email']) ?></p>
    <p class="text-sm text-gray-500">Name: <?= htmlspecialchars($user['full_name'] ?? '') ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">Currencies</h2>
    <ul class="text-sm">
      <?php foreach($curr as $c): ?>
        <li class="py-1 flex items-center justify-between">
          <span><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['name']) ?></span>
          <?php if($c['is_main']): ?><span class="text-xs bg-gray-200 px-2 py-0.5 rounded-full">Main</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs text-gray-400 mt-2">(Add set/unset main handlers as needed)</p>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">Cashflow Rules</h2>
    <ul class="text-sm space-y-1">
      <?php foreach($rules as $r): ?>
        <li class="flex justify-between">
          <span><?= htmlspecialchars($r['label']) ?></span>
          <span><?= (float)$r['percent'] ?>%</span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs text-gray-400 mt-2">(Add CRUD for rules)</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">Basic Incomes</h2>
    <ul class="text-sm space-y-1">
      <?php foreach($basic as $b): ?>
        <li class="flex justify-between">
          <span><?= htmlspecialchars($b['label']) ?> (from <?= htmlspecialchars($b['valid_from']) ?>)</span>
          <span><?= moneyfmt($b['amount']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs text-gray-400 mt-2">(Add CRUD for basic incomes; supports raises via `valid_from`)</p>
  </div>
</section>