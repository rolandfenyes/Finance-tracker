<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Loans</h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add loan</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/loans/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="name" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Name" required>
      <input name="principal" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Principal" required>
      <input name="interest_rate" type="number" step="0.001" class="rounded-xl border-gray-300" placeholder="APR %" required>
      <input name="start_date" type="date" class="rounded-xl border-gray-300" required>
      <input name="end_date" type="date" class="rounded-xl border-gray-300">
      <input name="payment_day" type="number" min="1" max="28" class="rounded-xl border-gray-300" placeholder="Pay day">
      <input name="extra_payment" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Extra">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Loan</th><th class="py-2 pr-3">APR</th><th class="py-2 pr-3">Balance</th><th class="py-2 pr-3">Period</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
    <?php foreach($loans as $l): ?>
      <tr class="border-b">
        <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($l['name']) ?></td>
        <td class="py-2 pr-3"><?= (float)$l['interest_rate'] ?>%</td>
        <td class="py-2 pr-3 font-medium"><?= moneyfmt($l['balance']) ?></td>
        <td class="py-2 pr-3"><?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date']??'—') ?></td>
        <td class="py-2 pr-3">
          <details>
            <summary class="cursor-pointer text-accent">Edit / Pay</summary>
            <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/loans/edit">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $l['id'] ?>" />
              <input name="name" value="<?= htmlspecialchars($l['name']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
              <input name="principal" type="number" step="0.01" value="<?= $l['principal'] ?>" class="rounded-xl border-gray-300">
              <input name="interest_rate" type="number" step="0.001" value="<?= $l['interest_rate'] ?>" class="rounded-xl border-gray-300">
              <input name="start_date" type="date" value="<?= $l['start_date'] ?>" class="rounded-xl border-gray-300">
              <input name="end_date" type="date" value="<?= $l['end_date'] ?>" class="rounded-xl border-gray-300">
              <input name="payment_day" type="number" value="<?= $l['payment_day'] ?>" class="rounded-xl border-gray-300">
              <input name="extra_payment" type="number" step="0.01" value="<?= $l['extra_payment'] ?>" class="rounded-xl border-gray-300">
              <input name="balance" type="number" step="0.01" value="<?= $l['balance'] ?>" class="rounded-xl border-gray-300">
              <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
            </form>
            <form class="mt-2 grid sm:grid-cols-5 gap-2" method="post" action="/loans/payment/add">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="loan_id" value="<?= $l['id'] ?>" />
              <input name="paid_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
              <input name="amount" type="number" step="0.01" placeholder="Payment amount" class="rounded-xl border-gray-300" required>
              <input name="interest_component" type="number" step="0.01" placeholder="Interest" class="rounded-xl border-gray-300">
              <input name="currency" value="HUF" class="rounded-xl border-gray-300">
              <button class="bg-emerald-600 text-white rounded-xl px-4">Record Payment</button>
            </form>
            <form class="mt-2" method="post" action="/loans/delete" onsubmit="return confirm('Delete loan?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $l['id'] ?>" />
              <button class="text-red-600">Remove</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>