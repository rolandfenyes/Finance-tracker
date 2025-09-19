<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= htmlspecialchars(__('years.title')) ?></h1>
  <ul class="mt-4 grid sm:grid-cols-3 gap-3">
    <?php for($y=$ymax; $y>=$ymin; $y--): $row=$byYear[$y] ?? ['income'=>0,'spending'=>0]; $net=$row['income']-$row['spending']; ?>
      <li class="border rounded-2xl p-4 hover:shadow">
        <a class="flex items-center justify-between" href="/years/<?= $y ?>">
          <div>
            <div class="text-lg font-semibold"><?= $y ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars(__('years.list.income_spending', ['income' => moneyfmt($row['income']), 'spending' => moneyfmt($row['spending'])])) ?></div>
          </div>
          <div class="text-sm font-medium <?= $net>=0?'text-emerald-600':'text-red-600' ?>"><?= moneyfmt($net) ?></div>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</section>