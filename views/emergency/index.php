<section class="card">
  <h1 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Emergency Fund') ?></h1>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="mt-2 rounded-2xl border border-brand-300/60 bg-brand-500/10 px-3 py-2 text-sm font-medium text-brand-700 dark:border-brand-500/50 dark:bg-brand-600/10 dark:text-brand-100">
      <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
    </p>
  <?php endif; ?>

  <div class="mt-6 grid gap-5 md:grid-cols-12">
    <div class="panel md:col-span-6 space-y-5 p-5">
      <?php if (!empty($suggest)): ?>
        <div class="space-y-2">
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?= __('Suggestion') ?></div>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            <?= __('Be able to survive without income. Smaller milestones help maintain momentum.') ?>
          </p>

          <?php if (!empty($suggest['done'])): ?>
            <div class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-500/10 px-3 py-1.5 text-sm font-semibold text-brand-700 dark:border-brand-400/60 dark:bg-brand-600/20 dark:text-brand-100">
              <span><?= htmlspecialchars($suggest['label']) ?></span>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400">
              <?= htmlspecialchars($suggest['desc']) ?>
            </div>
          <?php else: ?>
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 px-3 py-1.5 text-sm font-medium text-brand-700 shadow-sm transition hover:bg-brand-50/70 dark:border-slate-700 dark:bg-slate-900/50 dark:text-brand-100 dark:hover:bg-slate-800"
                    data-suggest-amount="<?= htmlspecialchars(number_format($suggest['amount_ef'], 2, '.', '')) ?>"
                    data-suggest-currency="<?= htmlspecialchars($suggest['currency_ef']) ?>">
              <span><?= moneyfmt($suggest['amount_ef'], $suggest['currency_ef']) ?></span>
              <span class="text-xs text-slate-500 dark:text-slate-400">· <?= htmlspecialchars($suggest['label']) ?></span>
            </button>

            <?php if (strtoupper($suggest['currency_ef']) !== strtoupper($suggest['currency_main'])): ?>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">
                ≈ <?= moneyfmt($suggest['amount_main'], $suggest['currency_main']) ?> (<?= htmlspecialchars($suggest['label']) ?>)
              </div>
            <?php endif; ?>

            <div class="text-[11px] text-slate-500 dark:text-slate-400">
              <?= htmlspecialchars($suggest['desc']) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form class="grid gap-3 sm:grid-cols-12" method="post" action="/emergency/target">
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
          <button class="btn btn-primary">
            <?= __('Save target') ?>
          </button>
        </div>
      </form>

      <div class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
        <div><?= __('Saved: :current of :target', ['current' => '<strong>'.moneyfmt($ef_total, $ef_cur).'</strong>', 'target' => moneyfmt($ef_target, $ef_cur)]) ?></div>
        <?php if (strtoupper($ef_cur)!==strtoupper($main)): ?>
          <div class="text-xs text-slate-500 dark:text-slate-400">
            ≈ <?= moneyfmt($total_main, $main) ?> / <?= moneyfmt($target_main, $main) ?> (<?= __('current FX') ?>)
          </div>
        <?php endif; ?>
        <?php $pct = $ef_target>0 ? min(100, max(0, $ef_total/$ef_target*100)) : 0; ?>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-brand-100/60 dark:bg-slate-800/60">
          <div class="h-2 rounded-full bg-brand-600" style="width: <?= number_format($pct,2,'.','') ?>%"></div>
        </div>
        <div class="text-xs font-semibold text-brand-700 dark:text-brand-200"><?= number_format($pct,1) ?>%</div>
      </div>
    </div>

    <div class="panel md:col-span-6 space-y-5 p-5">
      <form method="post" action="/emergency/add" class="grid gap-3 sm:grid-cols-12">
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
          <button class="btn btn-primary">
            <?= __('Add') ?>
          </button>
        </div>
      </form>

      <form method="post" action="/emergency/withdraw" class="grid gap-3 sm:grid-cols-12">
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
          <button class="btn btn-danger">
            <?= __('Withdraw') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="card mt-8">
  <div class="mb-4 flex items-center justify-between">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('History') ?></h2>
  </div>

  <div class="hidden overflow-x-auto md:block">
    <table class="table-glass min-w-full text-sm">
      <thead>
        <tr class="text-left">
          <th class="py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300 w-[18%]"><?= __('Date') ?></th>
          <th class="py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300 w-[18%]"><?= __('Type') ?></th>
          <th class="py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300 w-[22%]"><?= __('Amount') ?></th>
          <th class="py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300 w-[22%]"><?= __('≈ Main') ?></th>
          <th class="py-2 pr-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300 w-[20%]"><?= __('Note') ?></th>
          <th class="py-2 pr-0 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td class="py-3 pr-3 text-slate-700 dark:text-slate-200"><?= htmlspecialchars($r['occurred_on']) ?></td>
            <td class="py-3 pr-3">
              <?php if ($r['kind']==='add'): ?>
                <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50/80 px-3 py-1 text-xs font-semibold text-brand-700 dark:border-brand-400/60 dark:bg-brand-500/20 dark:text-brand-100"><?= __('Add money') ?></span>
              <?php else: ?>
                <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200"><?= __('Withdrawal') ?></span>
              <?php endif; ?>
            </td>
            <td class="py-3 pr-3 font-semibold text-slate-900 dark:text-white"><?= moneyfmt($r['amount_native'], $r['currency_native']) ?></td>
            <td class="py-3 pr-3 text-slate-500 dark:text-slate-400"><?= moneyfmt($r['amount_main'], $r['main_currency']) ?></td>
            <td class="py-3 pr-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($r['note'] ?? '') ?></td>
            <td class="py-3 pr-0 text-right">
              <form method="post" action="/emergency/tx/delete" onsubmit="return confirm('<?= __('Delete entry?') ?>')" class="inline-block">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="btn btn-danger !px-3 !py-1.5"><?= __('Delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; if (!count($rows)): ?>
          <tr>
            <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400"><?= __('No entries yet.') ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="space-y-3 md:hidden">
    <?php foreach($rows as $r): ?>
      <div class="panel space-y-2 p-4">
        <div class="flex items-center justify-between">
          <div class="text-sm font-medium text-slate-600 dark:text-slate-300"><?= htmlspecialchars($r['occurred_on']) ?></div>
          <?php if ($r['kind']==='add'): ?>
            <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50/80 px-3 py-1 text-xs font-semibold text-brand-700 dark:border-brand-400/60 dark:bg-brand-500/20 dark:text-brand-100"><?= __('Add') ?></span>
          <?php else: ?>
            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200"><?= __('Withdrawal') ?></span>
          <?php endif; ?>
        </div>
        <div class="text-lg font-semibold text-slate-900 dark:text-white"><?= moneyfmt($r['amount_native'], $r['currency_native']) ?></div>
        <?php if (strtoupper($r['currency_native'])!==strtoupper($r['main_currency'])): ?>
          <div class="text-xs text-slate-500 dark:text-slate-400">≈ <?= moneyfmt($r['amount_main'], $r['main_currency']) ?></div>
        <?php endif; ?>
        <?php if (!empty($r['note'])): ?>
          <div class="text-sm text-slate-600 dark:text-slate-300"><?= htmlspecialchars($r['note']) ?></div>
        <?php endif; ?>
        <div class="flex justify-end">
          <form method="post" action="/emergency/tx/delete" onsubmit="return confirm('<?= __('Delete entry?') ?>')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
            <button class="btn btn-danger !px-3 !py-1.5"><?= __('Delete') ?></button>
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

    b.classList.add('ring-2','ring-brand-300');
    setTimeout(()=> b.classList.remove('ring-2','ring-brand-300'), 600);
  });
</script>
