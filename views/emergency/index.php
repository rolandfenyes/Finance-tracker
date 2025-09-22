<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= __('Emergency Fund') ?></h1>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-2 text-sm text-emerald-700"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
  <?php endif; ?>

  <!-- Target / balance -->
  <div class="grid gap-4 md:grid-cols-12 mt-4">
    <div class="md:col-span-6 rounded-xl border p-4">
      <?php if (!empty($suggest)): ?>
        <div class="mt-4">
          <div class="text-sm font-medium mb-1"><?= __('Suggestion') ?></div>
          <p class="text-xs text-gray-500 mb-2">
            <?= __('Be able to survive without income. Smaller milestones help maintain momentum.') ?>
          </p>

          <?php if (!empty($suggest['done'])): ?>
            <!-- Done state (9+ months reached) -->
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700">
              <span class="font-medium"><?= htmlspecialchars($suggest['label']) ?></span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500">
              <?= htmlspecialchars($suggest['desc']) ?>
            </div>

          <?php else: ?>
            <!-- Normal milestone suggestion -->
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm hover:bg-gray-50"
                    data-suggest-amount="<?= htmlspecialchars(number_format($suggest['amount_ef'], 2, '.', '')) ?>"
                    data-suggest-currency="<?= htmlspecialchars($suggest['currency_ef']) ?>">
              <span class="font-medium"><?= moneyfmt($suggest['amount_ef'], $suggest['currency_ef']) ?></span>
              <span class="text-xs text-gray-500">· <?= htmlspecialchars($suggest['label']) ?></span>
            </button>

            <?php if (strtoupper($suggest['currency_ef']) !== strtoupper($suggest['currency_main'])): ?>
              <div class="mt-2 text-[11px] text-gray-500">
              ≈ <?= moneyfmt($suggest['amount_main'], $suggest['currency_main']) ?> (<?= htmlspecialchars($suggest['label']) ?>)
              </div>
            <?php endif; ?>

            <div class="mt-1 text-[11px] text-gray-400">
              <?= htmlspecialchars($suggest['desc']) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>



      <form class="grid sm:grid-cols-12 gap-3" method="post" action="/emergency/target">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div class="sm:col-span-7">
          <label class="label"><?= __('Target amount') ?></label>
          <input name="target_amount" type="number" step="0.01" class="input" value="<?= htmlspecialchars($ef_target) ?>" required />
        </div>
        <div class="sm:col-span-5">
          <label class="label"><?= __('Currency') ?></label>
          <select name="currency" class="select">
            <?php foreach($userCurrencies as $uc): $code = $uc['code']; ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper($code)===strtoupper($ef_cur)?'selected':'' ?>>
                <?= htmlspecialchars($code) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-12 flex justify-end">
          <button class="btn btn-primary"><?= __('Save target') ?></button>
        </div>
      </form>

      <div class="mt-3 text-sm text-gray-700">
        <div><?= __('Saved: :current of :target', ['current' => '<strong>'.moneyfmt($ef_total, $ef_cur).'</strong>', 'target' => moneyfmt($ef_target, $ef_cur)]) ?></div>
        <?php if (strtoupper($ef_cur)!==strtoupper($main)): ?>
          <div class="text-xs text-gray-500 mt-1">
            ≈ <?= moneyfmt($total_main, $main) ?> / <?= moneyfmt($target_main, $main) ?> (<?= __('current FX') ?>)
          </div>
        <?php endif; ?>
        <?php
          $pct = $ef_target>0 ? min(100, max(0, $ef_total/$ef_target*100)) : 0;
        ?>
        <div class="mt-2 h-2 bg-gray-100 rounded-full">
          <div class="h-2 bg-emerald-500 rounded-full" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
        </div>
        <div class="mt-1 text-xs text-gray-600"><?= number_format($pct,1) ?>%</div>
      </div>
    </div>

    <!-- Quick actions (always in target currency) -->
    <div class="md:col-span-6 grid gap-3">
      <form method="post" action="/emergency/add" class="rounded-xl border p-4 grid sm:grid-cols-12 gap-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div class="sm:col-span-5">
          <label class="label"><?= __('Date') ?></label>
          <input name="occurred_on" type="date" class="input" value="<?= date('Y-m-d') ?>" />
        </div>
        <div class="sm:col-span-5">
          <label class="label"><?= __('Add money (:currency)', ['currency' => htmlspecialchars($ef_cur)]) ?></label>
          <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
        </div>
        <div class="sm:col-span-12">
          <label class="label"><?= __('Note (optional)') ?></label>
          <input name="note" class="input" placeholder="<?= __('e.g., paycheck buffer') ?>" />
        </div>
        <div class="sm:col-span-12 flex justify-end">
          <button class="btn btn-emerald"><?= __('Add') ?></button>
        </div>
      </form>

      <form method="post" action="/emergency/withdraw" class="rounded-xl border p-4 grid sm:grid-cols-12 gap-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <div class="sm:col-span-5">
          <label class="label"><?= __('Date') ?></label>
          <input name="occurred_on" type="date" class="input" value="<?= date('Y-m-d') ?>" />
        </div>
        <div class="sm:col-span-5">
          <label class="label"><?= __('Withdrawal (:currency)', ['currency' => htmlspecialchars($ef_cur)]) ?></label>
          <input name="amount" type="number" step="0.01" class="input" placeholder="0.00" required />
        </div>
        <div class="sm:col-span-12">
          <label class="label"><?= __('Note (optional)') ?></label>
          <input name="note" class="input" placeholder="<?= __('e.g., car repair') ?>" />
        </div>
        <div class="sm:col-span-12 flex justify-end">
          <button class="btn btn-danger"><?= __('Withdraw') ?></button>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold"><?= __('History') ?></h2>
  </div>

  <!-- Desktop table -->
  <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3 w-[18%]"><?= __('Date') ?></th>
          <th class="py-2 pr-3 w-[18%]"><?= __('Type') ?></th>
          <th class="py-2 pr-3 w-[22%]"><?= __('Amount') ?></th>
          <th class="py-2 pr-3 w-[22%]"><?= __('≈ Main') ?></th>
          <th class="py-2 pr-3 w-[20%]"><?= __('Note') ?></th>
          <th class="py-2 pr-0 text-right"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= htmlspecialchars($r['occurred_on']) ?></td>
            <td class="py-2 pr-3">
              <?php if ($r['kind']==='add'): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200"><?= __('Add money') ?></span>
              <?php else: ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200"><?= __('Withdrawal') ?></span>
              <?php endif; ?>
            </td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount_native'], $r['currency_native']) ?></td>
            <td class="py-2 pr-3 text-gray-600"><?= moneyfmt($r['amount_main'], $r['main_currency']) ?></td>
            <td class="py-2 pr-3 text-gray-600"><?= htmlspecialchars($r['note'] ?? '') ?></td>
            <td class="py-2 pr-0 text-right">
              <form method="post" action="/emergency/tx/delete" onsubmit="return confirm('<?= __('Delete entry?') ?>')" class="inline-block">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="btn btn-danger !py-1 !px-3"><?= __('Delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; if (!count($rows)): ?>
          <tr><td colspan="6" class="py-6 text-center text-gray-500"><?= __('No entries yet.') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile list -->
  <div class="md:hidden space-y-3">
    <?php foreach($rows as $r): ?>
      <div class="rounded-xl border p-4">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-500"><?= htmlspecialchars($r['occurred_on']) ?></div>
          <?php if ($r['kind']==='add'): ?>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs"><?= __('Add') ?></span>
          <?php else: ?>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-xs"><?= __('Withdrawal') ?></span>
          <?php endif; ?>
        </div>
        <div class="mt-2 font-semibold"><?= moneyfmt($r['amount_native'], $r['currency_native']) ?></div>
        <?php if (strtoupper($r['currency_native'])!==strtoupper($r['main_currency'])): ?>
          <div class="text-xs text-gray-500">≈ <?= moneyfmt($r['amount_main'], $r['main_currency']) ?></div>
        <?php endif; ?>
        <?php if (!empty($r['note'])): ?>
          <div class="mt-1 text-sm text-gray-600"><?= htmlspecialchars($r['note']) ?></div>
        <?php endif; ?>
        <div class="mt-3 flex justify-end">
          <form method="post" action="/emergency/tx/delete" onsubmit="return confirm('<?= __('Delete entry?') ?>')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
            <button class="btn btn-danger !py-1 !px-3"><?= __('Delete') ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<script>
  document.addEventListener('click', (e)=>{
    const b = e.target.closest('[data-suggest-amount]');
    if (!b) return;
    const amt = b.getAttribute('data-suggest-amount');
    const cur = b.getAttribute('data-suggest-currency');

    const form       = document.querySelector('form[action="/emergency/target"]');
    const targetInp  = form?.querySelector('input[name="target_amount"]');
    const curSelect  = form?.querySelector('select[name="currency"]');

    if (targetInp) targetInp.value = amt;
    if (curSelect && cur) {
      Array.from(curSelect.options).forEach(o => o.selected = (o.value.toUpperCase() === cur.toUpperCase()));
    }

    b.classList.add('ring-2','ring-emerald-300');
    setTimeout(()=> b.classList.remove('ring-2','ring-emerald-300'), 600);
  });
</script>
