<section class="max-w-5xl mx-auto">
  <div class="card">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="card-kicker">Community</div>
        <h1 class="card-title mt-1">Feedback & Bug Reports</h1>
        <p class="card-subtle mt-1">Share ideas or report issues. Everyone can see all entries.</p>
      </div>
      <a href="/feedback" class="text-sm text-accent">Refresh ↻</a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-3 text-red-600 text-sm"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <p class="mt-3 text-emerald-600 text-sm"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>

    <!-- Add form -->
    <form method="post" action="/feedback/add" class="mt-5 grid gap-3 md:grid-cols-12">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div class="field md:col-span-2">
        <label class="label">Type</label>
        <select name="kind" class="select">
          <option value="idea">Suggestion</option>
          <option value="bug">Bug</option>
        </select>
      </div>
      <div class="field md:col-span-2">
        <label class="label">Severity</label>
        <select name="severity" class="select">
          <option value="">—</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
      </div>
      <div class="field md:col-span-8">
        <label class="label">Title</label>
        <input name="title" class="input" placeholder="Short title" required />
      </div>
      <div class="field md:col-span-12">
        <label class="label">Message</label>
        <textarea name="message" class="textarea" placeholder="Describe your idea or the bug steps…" required></textarea>
      </div>
      <div class="md:col-span-12 flex justify-end">
        <button class="btn btn-primary">Submit</button>
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
            'all'      => 'All',
            'open'     => 'Open',
            'resolved' => 'Resolved',
            'bugs'     => 'Bugs',
            'ideas'    => 'Ideas',
            'mine'     => 'Mine',
          ];
          foreach ($tabs as $k=>$lbl):
            $active = $tab===$k ? 'tab-btn active' : 'tab-btn';
        ?>
          <a class="row-btn <?= $active ?>" href="/feedback?tab=<?= $k ?>&q=<?= $q ?>"><?= htmlspecialchars($lbl) ?></a>
        <?php endforeach; ?>
      </div>

      <form method="get" action="/feedback" class="input-group w-full md:w-72">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
        <input class="ig-input" name="q" placeholder="Search…" value="<?= htmlspecialchars($flt['q'] ?? '') ?>" />
        <button class="btn btn-ghost">Search</button>
      </form>
    </div>
  </div>

  <!-- List -->
  <div class="mt-4 space-y-3">
    <?php if (empty($rows)): ?>
      <div class="card-subtle">No feedback yet.</div>
    <?php else: foreach ($rows as $f):
      $badge = $f['kind']==='bug' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-amber-100 text-amber-800 border-amber-200';
      $sev   = $f['severity'] ? strtoupper($f['severity'][0]).substr($f['severity'],1) : '—';
      $statusColors = [
        'open'         => 'bg-gray-100 text-gray-700 border-gray-200',
        'in_progress'  => 'bg-blue-100 text-blue-700 border-blue-200',
        'resolved'     => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'closed'       => 'bg-gray-200 text-gray-700 border-gray-300',
      ];
      $stCls = $statusColors[$f['status']] ?? $statusColors['open'];
    ?>
      <div class="rounded-2xl border p-4 bg-white">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip <?= $badge ?>"><?= $f['kind']==='bug'?'Bug':'Suggestion' ?></span>
              <span class="chip"><?= htmlspecialchars($sev) ?></span>
              <span class="chip <?= $stCls ?>"><?= htmlspecialchars(str_replace('_',' ', $f['status'])) ?></span>
            </div>
            <div class="mt-1 font-semibold"><?= htmlspecialchars($f['title']) ?></div>
            <div class="mt-1 text-sm text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($f['message'])) ?></div>
            <div class="mt-2 text-xs text-gray-500">
              by <?= htmlspecialchars($f['full_name'] ?: $f['email']) ?>
              · <?= htmlspecialchars(date('Y-m-d H:i', strtotime($f['created_at']))) ?>
            </div>
          </div>

          <!-- Owner actions -->
          <?php if ((int)$f['user_id'] === (int)uid()): ?>
            <form method="post" action="/feedback/delete" onsubmit="return confirm('Delete this entry?')" class="shrink-0">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>" />
              <button class="btn btn-danger !py-1.5 !px-3">Remove</button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Status quick-update (author can close; you can extend for admin) -->
        <?php if ((int)$f['user_id'] === (int)uid()): ?>
          <form method="post" action="/feedback/status" class="mt-3 flex items-center gap-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>" />
            <select name="status" class="select w-44">
              <option value="open"        <?= $f['status']==='open'?'selected':'' ?>>Open</option>
              <option value="in_progress" <?= $f['status']==='in_progress'?'selected':'' ?>>In progress</option>
              <option value="resolved"    <?= $f['status']==='resolved'?'selected':'' ?>>Resolved</option>
              <option value="closed"      <?= $f['status']==='closed'?'selected':'' ?>>Closed</option>
            </select>
            <button class="btn btn-ghost">Update</button>
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
      <div class="text-gray-500">Page <?= (int)$page ?> / <?= (int)$pages ?></div>
      <div class="flex gap-2">
        <a class="btn btn-ghost <?= $page<=1?'pointer-events-none opacity-40':'' ?>" href="<?= $page>1?$mk($page-1):'#' ?>">Prev</a>
        <a class="btn btn-ghost <?= $page>=$pages?'pointer-events-none opacity-40':'' ?>" href="<?= $page<$pages?$mk($page+1):'#' ?>">Next</a>
      </div>
    </div>
  <?php endif; ?>
</section>
