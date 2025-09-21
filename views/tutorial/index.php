<section class="max-w-4xl mx-auto bg-white rounded-2xl shadow-glass p-6">
  <h1 class="text-2xl font-semibold mb-4">👋 Welcome to Finance Tracker!</h1>

  <p class="text-gray-600 mb-6">
    This short guide will help you understand how the app works. You can revisit it later
    from <span class="font-medium">Settings → Tutorial</span>.
  </p>

  <div class="space-y-6">
    <div>
      <h2 class="font-semibold">💰 Transactions</h2>
      <p class="text-sm text-gray-600">
        Add your incomes and spendings here. Categorize them to see where your money goes.
      </p>
    </div>

    <div>
      <h2 class="font-semibold">📅 Scheduled payments</h2>
      <p class="text-sm text-gray-600">
        Set up recurring bills (like rent or subscriptions). They will appear automatically
        in your monthly view.
      </p>
    </div>

    <div>
      <h2 class="font-semibold">🎯 Goals &amp; Loans</h2>
      <p class="text-sm text-gray-600">
        Track savings goals (like a new laptop) or loans. Link them with schedules to automate
        contributions or repayments.
      </p>
    </div>

    <div>
      <h2 class="font-semibold">🚨 Emergency Fund</h2>
      <p class="text-sm text-gray-600">
        Build a financial cushion. The app suggests milestones (first $1,000, then 3–6 months
        of needs) so you always feel progress.
      </p>
    </div>

    <div>
      <h2 class="font-semibold">📊 Dashboard &amp; Reports</h2>
      <p class="text-sm text-gray-600">
        See your current month at a glance, track progress on goals, and analyze categories.
      </p>
    </div>
  </div>

  <form method="post" action="/tutorial/dismiss" class="mt-8 flex justify-end">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
    <button class="btn btn-primary px-6">Got it — take me to Dashboard</button>
  </form>
</section>
