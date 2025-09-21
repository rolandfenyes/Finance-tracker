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
<section class="max-w-2xl mx-auto bg-white rounded-2xl shadow-glass p-6">
  <div class="flex items-center justify-between mb-2">
    <h1 class="text-xl font-semibold">🌍 Set up your currencies</h1>
    <a href="/logout" class="text-sm text-gray-400 hover:text-gray-500">Exit</a>
  </div>
  <p class="text-sm text-gray-600 mb-4">
    Add the currencies you use. Choose <strong>one main currency</strong> — reports and budgets are shown in that currency.
  </p>

  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="text-red-600 mb-3"><?=$_SESSION['flash']; unset($_SESSION['flash']);?></p>
  <?php endif; ?>

  <!-- Add currency form -->
  <form method="post" action="/onboard/currencies/add" class="grid sm:grid-cols-12 gap-3 mb-6" id="add-cur-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <input type="hidden" name="code" id="cur-code" />

    <div class="sm:col-span-8">
      <label class="label">Currency</label>
      <!-- Searchable selector -->
      <div class="relative">
        <input id="cur-search" class="input pr-10" placeholder="Type to search (e.g. USD, Euro)" autocomplete="off" />
        <button type="button" id="cur-clear"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                aria-label="Clear" title="Clear">✕</button>

        <!-- Results dropdown -->
        <div id="cur-results"
             class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-auto hidden">
          <!-- items injected by JS -->
        </div>
      </div>
      <p class="help mt-1">Search by code or name, then click to select.</p>
    </div>

    <div class="sm:col-span-4">
      <label class="label">Main currency</label>
      <label class="inline-flex items-center gap-2 h-[42px]">
        <input type="checkbox" name="is_main" value="1" <?= $hasMain ? '' : 'checked' ?> />
        <span class="text-sm text-gray-700">Set as main</span>
      </label>
      <?php if ($hasMain): ?>
        <p class="help">You already have a main; checking this will switch it.</p>
      <?php endif; ?>
    </div>

    <div class="sm:col-span-12 flex justify-end">
      <button class="btn btn-primary">Add currency</button>
    </div>
  </form>

  <!-- Quick picks -->
  <div class="mb-6">
    <div class="text-sm font-medium mb-2">Quick picks</div>
    <div class="flex flex-wrap gap-2">
      <?php foreach (['USD','EUR','HUF','GBP','CHF'] as $quick): 
        $exists = array_filter($currencies, fn($c)=>strtoupper($c['code'])=== $quick);
      ?>
        <button type="button"
          class="chip <?= $exists ? 'opacity-50 cursor-not-allowed' : '' ?>"
          data-pick="<?= $quick ?>" <?= $exists ? 'disabled' : '' ?>><?= $quick ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- List user currencies -->
  <div>
    <h2 class="font-medium mb-2">Your currencies</h2>
    <ul class="divide-y rounded-xl border">
      <?php if (!count($currencies)): ?>
        <li class="p-3 text-sm text-gray-500">No currencies yet. Add at least one main currency to continue.</li>
      <?php else: foreach ($currencies as $c): ?>
        <li class="p-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="chip"><?= htmlspecialchars(strtoupper($c['code'])) ?></span>
            <?php if (!empty($c['is_main'])): ?>
              <span class="ml-1 text-xs text-emerald-600">Main</span>
            <?php endif; ?>
          </div>
          <form method="post" action="/onboard/currencies/delete" class="inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
            <button class="btn btn-danger btn-xs"
                    <?= !empty($c['is_main']) && count($currencies) <= 1 ? 'disabled' : '' ?>>
                Remove
            </button>
        </form>

        </li>
      <?php endforeach; endif; ?>
    </ul>
  </div>

  <!-- Continue -->
  <form method="post" action="/onboard/next" class="mt-6 flex justify-end">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <button class="btn btn-primary px-6">Continue →</button>
  </form>
</section>

<script>
(function(){
  // Data from PHP
  const ALL = <?php echo json_encode($allCurrencies, JSON_UNESCAPED_UNICODE); ?>;
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

  function render(list){
    results.innerHTML = '';
    if (!list.length) {
      const empty = document.createElement('div');
      empty.className = 'px-3 py-2 text-sm text-gray-500';
      empty.textContent = 'No results';
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
    // If user started typing again, clear selected code
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

  // Prevent submit unless a valid code is selected
  form.addEventListener('submit', (e)=>{
    if (!hidden.value) {
      e.preventDefault();
      input.focus();
      openResults();
    }
  });

  // Quick pick chips
  document.querySelectorAll('[data-pick]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const code = btn.getAttribute('data-pick');
      const item = ALL.find(x=>x.code === code) || {code, name:''};
      hidden.value = code;
      input.value  = `${code} — ${item.name || ''}`;
      results.classList.add('hidden');
      // auto-submit for speed
      form.submit();
    });
  });
})();
</script>
