<section class="page-hero"><div class="wrap" style="display:flex;gap:18px;align-items:center"><?= avatar_html($m, 72) ?><div><div class="crumbs"><a href="/forum">Community</a> / Member</div><h1 style="margin:0;font-size:2rem"><?= e($m['name']) ?></h1><p><?= e($m['headline']) ?><?= $m['location'] ? ' · ' . e($m['location']) : '' ?></p></div></div></section>
<section class="section" style="padding-top:34px"><div class="wrap narrow" style="max-width:900px">
  <div class="grid g3"><div class="card center"><div class="price"><?= (int) $m['reputation'] ?></div><span class="muted small">Reputation</span></div><div class="card center"><div class="price"><?= $postCount ?></div><span class="muted small">Replies</span></div><div class="card center"><div class="price"><?= $answers ?></div><span class="muted small">Accepted answers</span></div></div>
  <?php if ($m['bio']): ?><div class="card mt2"><h3>About</h3><p class="mb0"><?= nl2br(e($m['bio'])) ?></p></div><?php endif; ?>
  <h2 class="mt3" style="font-size:1.25rem">Discussions</h2>
  <?php if (!$threads): ?><p class="muted">No discussions yet.</p><?php else: ?><div class="card pad0"><?php foreach ($threads as $t) { echo partial('forum/_thread_row', ['t' => $t]); } ?></div><?php endif; ?>
  <p class="muted xs mt2">Member since <?= e(fmt_date($m['created_at'])) ?></p>
</div></section>
