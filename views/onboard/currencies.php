<?php
// Expect from controller:
// - $currencies: array of user's rows: id, code, is_main
// - $allCurrencies: optional master list: [ ['code'=>'USD','name'=>'United States Dollar'], ... ]
// Fallback for $allCurrencies if missing:
if (!isset($allCurrencies) || !is_array($allCurrencies) || !count($allCurrencies)) {
  $allCurrencies = [
    ['code'=>'USD','name'=>'United States Dollar'],
    ['code'=>'EUR','name'=>'Euro'],
    ['code'=>'HUF','name'=>'Hungarian Forint'],
    ['code'=>'GBP','name'=>'Pound Sterling'],
    ['code'=>'CHF','name'=>'Swiss Franc'],
    ['code'=>'JPY','name'=>'Japanese Yen'],
    ['code'=>'CAD','name'=>'Canadian Dollar'],
    ['code'=>'AUD','name'=>'Australian Dollar'],
    ['code'=>'CZK','name'=>'Czech Koruna'],
    ['code'=>'PLN','name'=>'Polish Złoty'],
    ['code'=>'SEK','name'=>'Swedish Krona'],
    ['code'=>'NOK','name'=>'Norwegian Krone'],
    ['code'=>'DKK','name'=>'Danish Krone'],
    ['code'=>'RON','name'=>'Romanian Leu'],
    ['code'=>'TRY','name'=>'Turkish Lira'],
    ['code'=>'INR','name'=>'Indian Rupee'],
    ['code'=>'CNY','name'=>'Chinese Yuan'],
    ['code'=>'NZD','name'=>'New Zealand Dollar'],
    ['code'=>'SGD','name'=>'Singapore Dollar'],
    ['code'=>'ZAR','name'=>'South African Rand'],
  ];
}
$currencies = $currencies ?? [];
$hasMain = (bool)array_filter($currencies, fn($c)=>!empty($c['is_main']));
?>

<?php include __DIR__ . '/_progress.php'; ?>

<?php
$mainCurrencyCode = null;
foreach ($currencies as $cur) {
  if (!empty($cur['is_main'])) { $mainCurrencyCode = strtoupper($cur['code']); break; }
}
$hasCurrencies = count($currencies) > 0;
$flashMessage = $_SESSION['flash'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_type']);
?>

<section class="max-w-6xl mx-auto space-y-8">
  <div class="card flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
    <div class="max-w-xl">
      <div class="card-kicker"><?= __('Stay in control globally') ?></div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
        <?= __('Set up your currencies') ?>
      </h1>
      <p class="mt-3 text-base text-gray-600 dark:text-gray-300 leading-relaxed">
        <?= __('Track balances in every currency you use and pick one home currency for reports and budgets. We’ll convert everything to your main choice automatically.') ?>
      </p>
      <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="globe" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Add as many currencies as you need—perfect for travel or multi-country finances.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="star" class="h-3.5 w-3.5"></i></span>
          <span><?= __('Choose one main currency so dashboards, charts, and goals stay consistent.') ?></span>
        </li>
        <li class="flex items-start gap-3">
          <span class="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/15 text-brand-600"><i data-lucide="wallet" class="h-3.5 w-3.5"></i></span>
          <span><?= __('You can edit or remove currencies anytime from Settings → Money.') ?></span>
        </li>
      </ul>
    </div>
    <div class="self-stretch rounded-3xl border border-dashed border-brand-200/60 bg-brand-50/40 p-5 text-sm text-brand-700 shadow-inner dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-100">
      <h2 class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-600 dark:text-brand-200"><?= __('Why pick a main currency?') ?></h2>
      <p class="mt-3 leading-relaxed">
        <?= __('Budgets, baby steps, and performance charts all use your main currency to keep things comparable. We’ll keep the exchange rates in sync so you don’t have to.') ?>
      </p>
      <a href="/logout" class="mt-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand-700 hover:text-brand-800 dark:text-brand-200">
        <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
        <?= __('Exit setup') ?>
      </a>
    </div>
  </div>

  <?php if ($flashMessage): ?>
    <div class="rounded-3xl border <?= $flashType === 'success' ? 'border-emerald-300/70 bg-emerald-50/70 text-emerald-700' : 'border-rose-300/70 bg-rose-50/70 text-rose-700' ?> px-4 py-3 text-sm shadow-sm">
      <div class="flex items-start gap-3">
        <i data-lucide="<?= $flashType === 'success' ? 'check-circle2' : 'alert-triangle' ?>" class="mt-0.5 h-4 w-4"></i>
        <span><?= htmlspecialchars($flashMessage) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid gap-6 lg:grid-cols-5">
    <div class="card space-y-6 lg:col-span-3">
      <form method="post" action="/onboard/currencies/add" class="space-y-4" id="add-cur-form">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="code" id="cur-code" />

        <div>
          <label class="label" for="cur-search"><?= __('Search currency') ?></label>
          <div class="relative">
            <input id="cur-search" class="input pr-12" placeholder="<?= __('Try USD, EUR, HUF…') ?>" autocomplete="off" />
            <button type="button" id="cur-clear" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" aria-label="<?= __('Clear search') ?>" title="<?= __('Clear search') ?>">✕</button>
            <div id="cur-results" class="absolute z-20 mt-2 hidden w-full max-h-64 overflow-auto rounded-2xl border border-white/70 bg-white/90 shadow-lg backdrop-blur"></div>
          </div>
          <p class="help mt-1">
            <?= __('We support ISO codes and names. Pick one to add it to your wallet.') ?>
          </p>
        </div>

        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/50">
          <label class="label" for="cur-main-toggle"><?= __('Main currency') ?></label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-200">
              <?= __('Set this currency as my main one') ?>
            </span>
            <input type="checkbox" id="cur-main-toggle" name="is_main" value="1" class="h-4 w-4" <?= $hasMain ? '' : 'checked' ?> />
          </label>
          <?php if ($hasMain): ?>
            <p class="help mt-2"><?= __('Checking the box will switch your main currency to this new choice.') ?></p>
          <?php else: ?>
            <p class="help mt-2"><?= __('We’ll use the first currency you add as your main one by default.') ?></p>
          <?php endif; ?>
        </div>

        <div class="flex items-center justify-end gap-3">
          <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
            <?= __('You can add more later from Settings.') ?>
          </span>
          <button class="btn btn-primary">
            <?= __('Add currency') ?>
          </button>
        </div>
      </form>

      <div>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300 mb-3"><?= __('Quick picks') ?></h2>
        <div class="flex flex-wrap gap-3">
          <?php foreach (['USD','EUR','HUF','GBP','CHF'] as $quick):
            $exists = array_filter($currencies, fn($c)=>strtoupper($c['code'])=== $quick);
            $disabled = $exists ? 'disabled' : '';
            $classes = $exists ? 'opacity-40 cursor-not-allowed' : 'hover:-translate-y-0.5 hover:shadow-md';
          ?>
            <button type="button" data-pick="<?= $quick ?>" <?= $disabled ?>
              class="flex items-center gap-2 rounded-2xl border border-white/60 bg-white/70 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-gray-700 transition duration-200 <?= $classes ?> dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-gray-200">
              <i data-lucide="flag" class="h-4 w-4"></i>
              <?= $quick ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card space-y-4 lg:col-span-2">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= __('Your wallet') ?></h2>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            <?= $hasCurrencies
              ? __('Main currency: :code', ['code' => $mainCurrencyCode ?: __('not set yet')])
              : __('No currencies added yet. Add at least one to continue.') ?>
          </p>
        </div>
        <span class="chip"><?= __(':count added', ['count' => count($currencies)]) ?></span>
      </div>

      <ul class="glass-stack">
        <?php if (!$hasCurrencies): ?>
          <li class="glass-stack__item text-sm text-gray-500 dark:text-gray-300">
            <?= __('Your list is empty for now. Start by adding your main currency.') ?>
          </li>
        <?php else: foreach ($currencies as $c): ?>
          <li class="glass-stack__item flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="chip text-xs"><?= htmlspecialchars(strtoupper($c['code'])) ?></span>
              <?php if (!empty($c['is_main'])): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-200">
                  <i data-lucide="crown" class="h-3.5 w-3.5"></i>
                  <?= __('Main') ?>
                </span>
              <?php endif; ?>
            </div>
            <form method="post" action="/onboard/currencies/delete" class="inline-flex">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
              <button class="icon-action icon-action--danger" <?= !empty($c['is_main']) && count($currencies) <= 1 ? 'disabled' : '' ?> title="<?= __('Remove') ?>">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('Remove') ?></span>
              </button>
            </form>
          </li>
        <?php endforeach; endif; ?>
      </ul>

      <div class="rounded-2xl border border-white/60 bg-white/60 p-4 text-xs text-gray-600 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60 dark:text-gray-300">
        <p><?= __('Tip: If you remove your main currency, we’ll automatically promote another one so you’re never without a base currency.') ?></p>
      </div>
    </div>
  </div>

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <a href="/onboard/rules" class="btn btn-ghost">
      <?= __('Back to cashflow rules') ?>
    </a>
    <form method="post" action="/onboard/next" class="flex items-center gap-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <button class="btn btn-primary px-6" <?= $hasCurrencies ? '' : 'disabled' ?>>
        <?= __('Continue →') ?>
      </button>
    </form>
  </div>
</section>

<script>
(function(){
  // Data from PHP
  const ALL = <?php echo json_encode($allCurrencies, JSON_UNESCAPED_UNICODE); ?>;
  const TEXT_NO_RESULTS = <?php echo json_encode(__('No results'), JSON_UNESCAPED_UNICODE); ?>;
  const bySearch = (q) => {
    q = q.trim().toLowerCase();
    if (!q) return ALL.slice(0, 50);
    return ALL.filter(x =>
      x.code.toLowerCase().includes(q) ||
      (x.name && x.name.toLowerCase().includes(q))
    ).slice(0, 100);
  };

  const input   = document.getElementById('cur-search');
  const clear   = document.getElementById('cur-clear');
  const results = document.getElementById('cur-results');
  const hidden  = document.getElementById('cur-code');
  const form    = document.getElementById('add-cur-form');

  if (!input || !results || !hidden || !form) return;

  function render(list){
    results.innerHTML = '';
    if (!list.length) {
      const empty = document.createElement('div');
      empty.className = 'px-3 py-2 text-sm text-gray-500';
      empty.textContent = TEXT_NO_RESULTS;
      results.appendChild(empty);
      return;
    }
    list.forEach(({code, name})=>{
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'w-full text-left px-3 py-2 hover:bg-gray-50';
      row.setAttribute('data-code', code);
      row.innerHTML = `<span class="font-medium">${code}</span> <span class="text-gray-500">— ${name || ''}</span>`;
      row.addEventListener('click', ()=>{
        hidden.value = code;
        input.value  = `${code} — ${name || ''}`;
        results.classList.add('hidden');
      });
      results.appendChild(row);
    });
  }

  function openResults(){
    results.classList.remove('hidden');
  }
  function closeResults(){
    results.classList.add('hidden');
  }

  input.addEventListener('input', ()=>{
    const list = bySearch(input.value);
    render(list);
    openResults();
    hidden.value = '';
  });
  input.addEventListener('focus', ()=>{
    const list = bySearch(input.value);
    render(list);
    openResults();
  });
  input.addEventListener('blur', ()=> setTimeout(closeResults, 120));
  clear.addEventListener('click', ()=>{
    input.value = '';
    hidden.value = '';
    input.focus();
    const list = bySearch('');
    render(list);
    openResults();
  });

  form.addEventListener('submit', (e)=>{
    if (!hidden.value) {
      e.preventDefault();
      input.focus();
      openResults();
    }
  });

  document.querySelectorAll('[data-pick]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (btn.disabled) return;
      const code = btn.getAttribute('data-pick');
      const item = ALL.find(x=>x.code === code) || {code, name:''};
      hidden.value = code;
      input.value  = `${code} — ${item.name || ''}`;
      results.classList.add('hidden');
      form.submit();
    });
  });
})();
</script>
