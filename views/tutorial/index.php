<?php
require_once __DIR__.'/../layout/page_header.php';

render_page_header([
  'kicker' => __('Guides'),
  'title' => __('Learn the workflow'),
  'subtitle' => __('Use this tour as a companion while you explore dashboards, automation, and planning tools.'),
  'actions' => [
    ['label' => __('Back to settings'), 'href' => '/settings', 'icon' => 'arrow-left', 'style' => 'muted'],
  ],
]);
?>

<section class="max-w-5xl mx-auto">
  <div class="grid md:grid-cols-12 gap-6">
    <!-- Sidebar / TOC -->
    <aside class="md:col-span-4 lg:col-span-3">
      <div class="sticky top-4 card">
        <h2 class="text-sm font-semibold text-gray-700 mb-3"><?= __('Tutorial') ?></h2>
        <nav class="space-y-1 text-sm" id="toc">
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#intro"><?= __('Welcome') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#dashboard"><?= __('Dashboard') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#month"><?= __('Monthly view') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#transactions"><?= __('Transactions') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#scheduled"><?= __('Scheduled payments') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#goals"><?= __('Goals') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#loans"><?= __('Loans') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#emergency"><?= __('Emergency Fund') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#rules"><?= __('Cashflow rules') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#currencies"><?= __('Currencies & FX') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#categories"><?= __('Categories') ?></a>
          <a class="toc-link block px-2 py-1 rounded hover:bg-gray-50" href="#shortcuts"><?= __('Tips & shortcuts') ?></a>
        </nav>
      </div>
    </aside>

    <!-- Content -->
    <div class="md:col-span-8 lg:col-span-9 space-y-6">
      <!-- Card -->
      <div id="intro" class="card">
        <h1 class="text-xl font-semibold mb-2"><?= __('Welcome to Finance Tracker 👋') ?></h1>
        <p class="text-gray-600"><?= __('This quick tour shows you how to get the most out of the app—tracking month-to-month, setting goals, handling loans, and building your emergency fund.') ?></p>
      </div>

      <div id="dashboard" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Dashboard') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('See income vs. spending, net position, and quick links to add transactions.') ?></li>
          <li><?= __('Widgets summarize <em>Goals, Loans, Emergency Fund</em> at a glance.') ?></li>
          <li><?= __('Use the currency selector where available to view in your preferred currency.') ?></li>
        </ul>
      </div>

      <div id="month" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Monthly view') ?></h2>
        <p class="text-gray-700"><?= __('Your single source of truth for the month. It combines:') ?></p>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('<strong>Real transactions</strong> you entered/imported.') ?></li>
          <li><?= __('<strong>Virtual rows</strong> from scheduled payments, basic incomes, and goal/EF contributions.') ?></li>
          <li><?= __('EF mirrors appear as: <em>add ➜ spending</em>, <em>withdraw ➜ income</em> (locked).') ?></li>
        </ul>
        <p class="text-xs text-gray-500 mt-2"><?= __('Locked/virtual rows can’t be edited in the Month list to preserve consistency.') ?></p>
      </div>

      <div id="transactions" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Transactions') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Quick Add lets you choose kind, amount, currency, and category fast.') ?></li>
          <li><?= __('Filters: text search, category, kind, date range, amount range, currency.') ?></li>
          <li><?= __('FX is applied for totals in your main currency.') ?></li>
        </ul>
      </div>

      <div id="scheduled" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Scheduled payments') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Create monthly/weekly repeating items using RRULEs.') ?></li>
          <li><?= __('Link a schedule to <em>one</em> Goal or Loan at a time (UI filters out already-linked ones).') ?></li>
          <li><?= __('Virtual instances show in Monthly view; they’re not editable there.') ?></li>
        </ul>
      </div>

      <div id="goals" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Goals') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Pick a currency, set a target, and add money manually or via schedule.') ?></li>
          <li><?= __('Link/unlink an existing schedule; when linked, the create form is hidden.') ?></li>
          <li><?= __('Manual contributions appear in Monthly view as spending (virtual row with a “Goal” badge).') ?></li>
        </ul>
      </div>

      <div id="loans" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Loans') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Enter principal, APR, start/end dates; optionally auto-create a repayment schedule.') ?></li>
          <li><?= __('“Record Payment” logs principal/interest split and updates balance.') ?></li>
          <li><?= __('When a schedule is linked, an Unlink button appears and the create form is hidden.') ?></li>
        </ul>
      </div>

      <div id="emergency" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Emergency Fund') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Choose a target currency; totals are also shown in main currency using current FX.') ?></li>
          <li><?= __('EF transactions store their FX snapshot. Adds mirror to spending; withdrawals to income, using protected categories.') ?></li>
          <li><?= __('Suggestions: $1k starter → 3 months of needs → then +1 month, up to 9 months. After that, we suggest focusing on investments.') ?></li>
        </ul>
      </div>

      <div id="rules" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Cashflow rules') ?></h2>
        <p class="text-gray-700"><?= __('Rules classify transactions automatically (e.g., “Title contains <em>Spotify</em> → Category: Subscriptions”). Start simple; refine over time.') ?></p>
      </div>

      <div id="currencies" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Currencies & FX') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Pick your main currency; add others you use.') ?></li>
          <li><?= __('We convert to main currency for totals; historical entries use the stored rate/amount for accuracy.') ?></li>
        </ul>
      </div>

      <div id="categories" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Categories') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('Create Income/Spending categories. Colors help you scan lists quickly.') ?></li>
          <li><?= __('EF categories are protected from deletion but can be renamed/recolored.') ?></li>
        </ul>
      </div>

      <div id="shortcuts" class="card">
        <h2 class="text-lg font-semibold mb-2"><?= __('Tips & shortcuts') ?></h2>
        <ul class="list-disc pl-5 text-gray-700 space-y-1">
          <li><?= __('<kbd>Esc</kbd> closes dialogs.') ?></li>
          <li><?= __('Use filters on Monthly view to find anything quickly.') ?></li>
          <li><?= __('Use “Quick Add” for the fastest entry flow.') ?></li>
        </ul>
        <form method="post" action="/tutorial/done" class="mt-4">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
          <button class="btn btn-primary"><?= __('Finish tutorial') ?></button>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
// Smooth-scroll + active section highlighting
document.querySelectorAll('.toc-link').forEach(a=>{
  a.addEventListener('click', e=>{
    e.preventDefault();
    const el = document.querySelector(a.getAttribute('href'));
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});

const links = Array.from(document.querySelectorAll('.toc-link'));
const ids = links.map(l => l.getAttribute('href'));
const sections = ids.map(id => document.querySelector(id)).filter(Boolean);

function onScroll(){
  const y = window.scrollY + 100; // offset for sticky header
  let current = null;
  for (const s of sections) {
    if (s.offsetTop <= y) current = s.id;
  }
  links.forEach(l => l.classList.toggle('bg-brand-100/60', l.getAttribute('href') === '#'+current));
}
document.addEventListener('scroll', onScroll, { passive: true });
onScroll();
</script>
