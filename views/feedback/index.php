<?php
require_once __DIR__.'/../layout/page_header.php';

render_page_header([
  'kicker' => __('Community'),
  'title' => __('Feedback & bug reports'),
  'subtitle' => __('Share ideas or flag issues so we can keep improving the experience together.'),
  'actions' => [
    ['label' => __('Refresh'), 'href' => '/feedback', 'icon' => 'refresh-ccw', 'style' => 'muted'],
  ],
  'tabs' => [
    ['label' => __('Submit'), 'href' => '#feedback-form', 'active' => true],
    ['label' => __('Browse'), 'href' => '#feedback-list'],
  ],
]);
?>

<section class="mx-auto max-w-5xl space-y-6">
  <div class="card">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="card-kicker"><?= __('Community') ?></div>
        <h1 class="card-title mt-1"><?= __('Feedback & Bug Reports') ?></h1>
        <p class="card-subtle mt-1"><?= __('Share ideas or report issues. Everyone can see all entries.') ?></p>
      </div>
      <a href="/feedback" class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-200"><?= __('Refresh ↻') ?></a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-3 rounded-2xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <p class="mt-3 rounded-2xl border border-brand-300/60 bg-brand-500/10 px-3 py-2 text-sm font-medium text-brand-700 dark:border-brand-500/40 dark:bg-brand-600/20 dark:text-brand-100"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>

    <!-- Add form -->
    <form id="feedback-form" method="post" action="/feedback/add" class="mt-5 grid gap-3 md:grid-cols-12">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div class="field md:col-span-2">
        <label class="label"><?= __('Type') ?></label>
        <select name="kind" class="select">
          <option value="idea"><?= __('Suggestion') ?></option>
          <option value="bug"><?= __('Bug') ?></option>
        </select>
      </div>
      <div class="field md:col-span-2">
        <label class="label"><?= __('Severity') ?></label>
        <select name="severity" class="select">
          <option value="">—</option>
          <option value="low"><?= __('Low') ?></option>
          <option value="medium"><?= __('Medium') ?></option>
          <option value="high"><?= __('High') ?></option>
        </select>
      </div>
      <div class="field md:col-span-8">
        <label class="label"><?= __('Title') ?></label>
        <input name="title" class="input" placeholder="<?= __('Short title') ?>" required />
      </div>
      <div class="field md:col-span-12">
        <label class="label"><?= __('Message') ?></label>
        <textarea name="message" class="textarea" placeholder="<?= __('Describe your idea or the bug steps…') ?>" required></textarea>
      </div>
      <div class="md:col-span-12 flex justify-end">
        <button class="btn btn-primary"><?= __('Submit') ?></button>
      </div>
    </form>
  </div>

  <!-- Filters -->
  <?php $tab = $flt['tab'] ?? 'all'; $q = urlencode($flt['q'] ?? ''); ?>
  <div class="mt-6 card">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <?php
          $tabs = [
            'all'      => __('All'),
            'open'     => __('Open'),
            'resolved' => __('Resolved'),
            'bugs'     => __('Bugs'),
            'ideas'    => __('Ideas'),
            'mine'     => __('Mine'),
          ];
          foreach ($tabs as $k=>$lbl):
            $active = $tab===$k ? 'tab-btn active' : 'tab-btn';
        ?>
          <a class="row-btn <?= $active ?>" href="/feedback?tab=<?= $k ?>&q=<?= $q ?>"><?= htmlspecialchars($lbl) ?></a>
        <?php endforeach; ?>
      </div>

      <form method="get" action="/feedback" class="input-group w-full md:w-72">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
        <input class="ig-input" name="q" placeholder="<?= __('Search…') ?>" value="<?= htmlspecialchars($flt['q'] ?? '') ?>" />
        <button class="btn btn-ghost"><?= __('Search') ?></button>
      </form>
    </div>
  </div>

  <!-- List -->
  <div id="feedback-list" class="mt-4 space-y-3">
    <?php if (empty($rows)): ?>
      <div class="card-subtle"><?= __('No feedback yet.') ?></div>
    <?php else:
      $severityLabels = [
        'low'    => __('Low'),
        'medium' => __('Medium'),
        'high'   => __('High'),
      ];
      $statusColors = [
        'open'         => 'border-brand-200 bg-brand-50/80 text-brand-700 dark:border-brand-400/60 dark:bg-brand-500/20 dark:text-brand-100',
        'in_progress'  => 'border-sky-300/70 bg-sky-500/15 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/20 dark:text-sky-100',
        'resolved'     => 'border-brand-400/60 bg-brand-500/15 text-brand-700 dark:border-brand-400/50 dark:bg-brand-600/20 dark:text-brand-100',
        'closed'       => 'border-slate-300/60 bg-slate-200/60 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200',
      ];
      $statusLabels = [
        'open'        => __('Open'),
        'in_progress' => __('In progress'),
        'resolved'    => __('Resolved'),
        'closed'      => __('Closed'),
      ];
      foreach ($rows as $f):
      $badge = $f['kind']==='bug'
        ? 'border-rose-300/70 bg-rose-500/10 text-rose-600 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-200'
        : 'border-amber-300/70 bg-amber-400/10 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-200';
      $sev   = $f['severity'] ? ($severityLabels[$f['severity']] ?? ucfirst($f['severity'])) : '—';
      $stCls = $statusColors[$f['status']] ?? $statusColors['open'];
      $statusText = $statusLabels[$f['status']] ?? str_replace('_',' ', $f['status']);
    ?>
      <div class="panel space-y-3 p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip <?= $badge ?>"><?= $f['kind']==='bug' ? __('Bug') : __('Suggestion') ?></span>
              <span class="chip"><?= htmlspecialchars($sev) ?></span>
              <span class="chip <?= $stCls ?>"><?= htmlspecialchars($statusText) ?></span>
            </div>
            <div class="mt-2 text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($f['title']) ?></div>
            <div class="mt-2 text-sm text-slate-600 whitespace-pre-wrap dark:text-slate-300"><?= nl2br(htmlspecialchars($f['message'])) ?></div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
              <?php
                $author = htmlspecialchars($f['full_name'] ?: $f['email']);
                $created = htmlspecialchars(date('Y-m-d H:i', strtotime($f['created_at'])));
                echo __('by :author · :date', ['author' => $author, 'date' => $created]);
              ?>
            </div>
          </div>

          <!-- Owner actions -->
          <?php if ((int)$f['user_id'] === (int)uid()): ?>
            <form method="post" action="/feedback/delete" onsubmit="return confirm('<?= __('Delete this entry?') ?>')" class="shrink-0">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>" />
              <button class="icon-action icon-action--danger" title="<?= __('Remove') ?>">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
                <span class="sr-only"><?= __('Remove') ?></span>
              </button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Status quick-update (author can close; you can extend for admin) -->
        <?php if ((int)$f['user_id'] === (int)uid()): ?>
          <form method="post" action="/feedback/status" class="mt-3 flex flex-wrap items-center gap-3">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>" />
            <select name="status" class="select w-44">
              <option value="open"        <?= $f['status']==='open'?'selected':'' ?>><?= __('Open') ?></option>
              <option value="in_progress" <?= $f['status']==='in_progress'?'selected':'' ?>><?= __('In progress') ?></option>
              <option value="resolved"    <?= $f['status']==='resolved'?'selected':'' ?>><?= __('Resolved') ?></option>
              <option value="closed"      <?= $f['status']==='closed'?'selected':'' ?>><?= __('Closed') ?></option>
            </select>
            <button class="btn btn-muted"><?= __('Update') ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Pagination -->
  <?php if (($pages ?? 1) > 1):
    $mk = function($p) use ($flt){ $q = ['tab'=>$flt['tab'] ?? 'all','q'=>$flt['q'] ?? '','page'=>$p]; return '/feedback?'.http_build_query($q); };
  ?>
    <div class="flex items-center justify-between mt-4 text-sm">
      <div class="text-slate-500 dark:text-slate-400"><?= __('Page :page / :pages', ['page' => (int)$page, 'pages' => (int)$pages]) ?></div>
      <div class="flex gap-2">
        <a class="btn btn-ghost <?= $page<=1?'pointer-events-none opacity-40':'' ?>" href="<?= $page>1?$mk($page-1):'#' ?>"><?= __('Prev') ?></a>
        <a class="btn btn-ghost <?= $page>=$pages?'pointer-events-none opacity-40':'' ?>" href="<?= $page<$pages?$mk($page+1):'#' ?>"><?= __('Next') ?></a>
      </div>
    </div>
  <?php endif; ?>
</section>
