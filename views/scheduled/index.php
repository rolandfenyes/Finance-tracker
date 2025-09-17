<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Scheduled Payments</h1>
  <p class="text-sm text-gray-500">Use RRULE like <code class="bg-gray-100 px-1 rounded">FREQ=MONTHLY;BYMONTHDAY=10</code> or <code class="bg-gray-100 px-1 rounded">FREQ=WEEKLY;BYDAY=MO</code>.</p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add scheduled payment</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/scheduled/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="title" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Title (e.g., Rent)" required>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Amount" required>
      <input name="currency" class="rounded-xl border-gray-300" value="HUF">
      <input name="next_due" type="date" class="rounded-xl border-gray-300" placeholder="Next due">
      <input name="rrule" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="FREQ=MONTHLY;BYMONTHDAY=10" required>
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3">Title</th>
        <th class="py-2 pr-3">Amount</th>
        <th class="py-2 pr-3">Currency</th>
        <th class="py-2 pr-3">RRULE</th>
        <th class="py-2 pr-3">Next Due</th>
        <th class="py-2 pr-3">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr class="border-b">
          <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($r['title']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount']) ?></td>
          <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
          <td class="py-2 pr-3 text-xs text-gray-600 whitespace-pre-wrap"><?= htmlspecialchars($r['rrule']) ?></td>
          <td class="py-2 pr-3"><?= htmlspecialchars($r['next_due'] ?? '—') ?></td>
          <td class="py-2 pr-3">
            <details>
              <summary class="cursor-pointer text-accent">Edit</summary>
              <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/scheduled/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <input name="title" value="<?= htmlspecialchars($r['title']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                <input name="amount" type="number" step="0.01" value="<?= $r['amount'] ?>" class="rounded-xl border-gray-300">
                <input name="currency" value="<?= htmlspecialchars($r['currency']) ?>" class="rounded-xl border-gray-300">
                <input name="next_due" type="date" value="<?= $r['next_due'] ?>" class="rounded-xl border-gray-300">
                <input name="rrule" value="<?= htmlspecialchars($r['rrule']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
              </form>
              <form class="mt-2" method="post" action="/scheduled/delete" onsubmit="return confirm('Delete scheduled payment?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <button class="text-red-600">Remove</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>