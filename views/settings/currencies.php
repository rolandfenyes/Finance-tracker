<section class="max-w-3xl mx-auto">
  <div class="card space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold"><?= __('Manage Currencies') ?></h1>
      <a href="/settings" class="text-sm text-accent"><?= __('← Back to Settings') ?></a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <div>
        <h2 class="font-medium mb-2"><?= __('Your currencies') ?></h2>
        <ul class="glass-stack">
          <?php foreach($userCurrencies as $c): ?>
            <li class="glass-stack__item flex items-center justify-between gap-3">
              <div>
                <div class="font-medium text-slate-900 dark:text-white">
                  <?= htmlspecialchars($c['code']) ?>
                  <?php if ($c['is_main']): ?>
                    <span class="ml-2 inline-flex items-center rounded-full bg-brand-600/15 px-2 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-100"><?= __('Main') ?></span>
                  <?php endif; ?>
                </div>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($c['name']) ?></div>
              </div>
              <div class="flex gap-2">
                <?php if (!$c['is_main']): ?>
                  <form method="post" action="/settings/currencies/main">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                    <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
                    <button class="btn btn-muted !py-1.5 !px-3"><?= __('Set main') ?></button>
                  </form>
                  <form method="post" action="/settings/currencies/remove" onsubmit="return confirm('<?= addslashes(__('Remove currency :code?', ['code' => $c['code']])) ?>')">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                    <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
                    <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                      <i data-lucide="trash-2" class="h-4 w-4"></i>
                      <span class="sr-only"><?= __('Remove') ?></span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; if (!count($userCurrencies)): ?>
            <li class="glass-stack__item text-sm text-gray-500"><?= __('No currencies yet.') ?></li>
          <?php endif; ?>
        </ul>
      </div>

      <div>
        <h2 class="font-medium mb-2"><?= __('Add currency') ?></h2>
        <form method="post" action="/settings/currencies/add" class="flex flex-wrap gap-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <select name="code" class="select flex-1 min-w-[220px]" required>
            <option value=""><?= __('— Select currency —') ?></option>
            <?php foreach($available as $a): ?>
              <option value="<?= htmlspecialchars($a['code']) ?>"><?= htmlspecialchars($a['code']) ?> — <?= htmlspecialchars($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary"><?= __('Add') ?></button>
        </form>
        <p class="text-xs text-gray-500 mt-2"><?= __('The first currency you add becomes your main by default.') ?></p>
      </div>
    </div>
  </div>
</section>
