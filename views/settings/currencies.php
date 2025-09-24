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

<div
  id="currency-adding-overlay"
  class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm dark:bg-slate-900/70"
  aria-hidden="true"
>
  <div class="absolute left-1/2 top-1/2 w-full max-w-sm -translate-x-1/2 -translate-y-1/2 px-4">
    <div
      class="rounded-3xl bg-white/95 px-8 py-6 text-center shadow-xl ring-1 ring-black/5 dark:bg-slate-900/95 dark:text-white dark:ring-white/10"
      role="status"
      aria-live="assertive"
      aria-busy="true"
    >
      <div class="mx-auto flex h-12 w-12 items-center justify-center" aria-hidden="true">
        <div class="h-12 w-12 rounded-full border-4 border-brand-500/30 border-t-brand-500 animate-spin"></div>
      </div>
      <p class="mt-4 text-sm font-medium text-slate-700 dark:text-slate-100">
        <?= __('Adding currency to your account, please wait') ?>
      </p>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action="/settings/currencies/add"]');
    const overlay = document.getElementById('currency-adding-overlay');
    if (!form || !overlay) return;

    const submitButton = form.querySelector('button[type="submit"]');
    let overlayActive = false;

    const showOverlay = () => {
      if (overlayActive) return;
      overlayActive = true;
      overlay.classList.remove('hidden');
      overlay.setAttribute('aria-hidden', 'false');
      if (submitButton) {
        submitButton.setAttribute('disabled', 'disabled');
      }
      if (window.MyMoneyMapOverlay && typeof window.MyMoneyMapOverlay.open === 'function') {
        window.MyMoneyMapOverlay.open();
      }
    };

    const resetOverlay = () => {
      const wasActive = overlayActive;
      overlayActive = false;
      overlay.classList.add('hidden');
      overlay.setAttribute('aria-hidden', 'true');
      if (submitButton) {
        submitButton.removeAttribute('disabled');
      }
      if (wasActive && window.MyMoneyMapOverlay && typeof window.MyMoneyMapOverlay.close === 'function') {
        window.MyMoneyMapOverlay.close();
      }
    };

    form.addEventListener('submit', () => {
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        return;
      }
      showOverlay();
    });

    window.addEventListener('pageshow', resetOverlay);
  });
</script>
