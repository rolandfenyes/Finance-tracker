<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= $year ?></h1>
  <div class="mt-4 grid sm:grid-cols-3 md:grid-cols-4 gap-3">
    <?php for($m=1;$m<=12;$m++): $r=$byMonth[$m]; $net=$r['income']-$r['spending']; ?>
      <a href="/years/<?= $year ?>/<?= $m ?>" class="border rounded-2xl p-4 hover:shadow block">
        <div class="font-medium"><?= date('M', mktime(0,0,0,$m,1,$year)) ?></div>
        <div class="text-xs text-gray-500 mt-1">Inc: <?= moneyfmt($r['income']) ?> · Sp: <?= moneyfmt($r['spending']) ?></div>
        <div class="text-sm font-medium mt-1 <?= $net>=0?'text-emerald-600':'text-red-600' ?>"><?= moneyfmt($net) ?></div>
      </a>
    <?php endfor; ?>
  </div>
</section>