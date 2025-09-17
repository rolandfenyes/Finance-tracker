<section class="max-w-3xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Currencies</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <div>
      <h2 class="font-medium mb-2">Your currencies</h2>
      <ul class="divide-y">
        <?php foreach($userCurrencies as $c): ?>
          <li class="py-3 flex items-center justify-between">
            <div>
              <div class="font-medium">
                <?= htmlspecialchars($c['code']) ?>
                <?php if ($c['is_main']): ?><span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-900 text-white">Main</span><?php endif; ?>
              </div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($c['name']) ?></div>
            </div>
            <div class="flex gap-2">
              <?php if (!$c['is_main']): ?>
                <form method="post" action="/settings/currencies/main">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
                  <button class="px-3 py-1.5 rounded-lg border hover:bg-gray-50">Set main</button>
                </form>
                <form method="post" action="/settings/currencies/remove" onsubmit="return confirm('Remove currency <?= htmlspecialchars($c['code']) ?>?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
                  <button class="px-3 py-1.5 rounded-lg border text-red-600 hover:bg-red-50">Remove</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; if (!count($userCurrencies)): ?>
          <li class="py-3 text-sm text-gray-500">No currencies yet.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div>
      <h2 class="font-medium mb-2">Add currency</h2>
      <form method="post" action="/settings/currencies/add" class="flex gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <select name="code" class="flex-1 max-w-64 rounded-xl border-gray-300" required>
          <option value="">— Select currency —</option>
          <?php foreach($available as $a): ?>
            <option value="<?= htmlspecialchars($a['code']) ?>"><?= htmlspecialchars($a['code']) ?> — <?= htmlspecialchars($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="px-4 rounded-xl bg-gray-900 text-white">Add</button>
      </form>
      <p class="text-xs text-gray-500 mt-2">The first currency you add becomes your main by default.</p>
    </div>
  </div>
</section>