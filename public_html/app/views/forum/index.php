<section class="page-hero"><div class="wrap" style="display:flex;gap:24px;align-items:flex-end;justify-content:space-between;flex-wrap:wrap"><div><span class="eyebrow">Donums Community</span><h1>Ask. Share. Grow together.</h1><p>A professional community for people building careers and businesses. Get practical answers, share what you have learned and connect with others.</p></div>
  <a class="btn btn-accent" href="/forum/new"><?= icon('plus', 16) ?> Start a discussion</a></div></section>
<section class="section" style="padding-top:34px"><div class="wrap layout-2">
  <div>
    <form method="get" action="/forum/search" class="mb0" style="margin-bottom:18px"><input type="search" name="q" placeholder="Search discussions…" aria-label="Search the community"></form>
    <div class="tabs"><?php foreach (['latest' => 'Latest', 'top' => 'Top', 'unanswered' => 'Unanswered'] as $k => $v): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('/forum', ['tab' => $k !== 'latest' ? $k : null])) ?>"><?= $v ?></a><?php endforeach; ?></div>
    <?php if (!$threads): ?><?= empty_state('chat', 'No discussions yet', 'Be the first to start a conversation.', '<a class="btn btn-primary btn-sm" href="/forum/new">Start a discussion</a>') ?>
    <?php else: ?><div class="card pad0"><?php foreach ($threads as $t) { echo partial('forum/_thread_row', ['t' => $t]); } ?></div><?= pager($pg, '/forum', ['tab' => $tab !== 'latest' ? $tab : null]) ?><?php endif; ?>
  </div>
  <aside>
    <div class="card"><h3>Categories</h3><?php foreach ($cats as $c): ?><a href="/forum/c/<?= e($c['slug']) ?>" style="display:flex;gap:12px;padding:10px 0;color:inherit;border-bottom:1px solid #edf2ee"><span class="card-ic" style="width:36px;height:36px;margin:0;border-radius:10px"><?= icon($c['icon'], 17) ?></span><span><strong><?= e($c['name']) ?></strong><span class="muted xs" style="display:block"><?= (int) $c['threads'] ?> discussions · <?= (int) $c['posts'] ?> replies</span></span></a><?php endforeach; ?></div>
    <?php if ($top): ?><div class="card mt2"><h3>Top contributors</h3><?php foreach ($top as $m): ?><a href="/forum/u/<?= (int) $m['id'] ?>" style="display:flex;gap:10px;align-items:center;padding:6px 0;color:inherit"><?= avatar_html($m, 34) ?><span style="flex:1"><strong><?= e($m['name']) ?></strong></span><span class="muted small"><?= (int) $m['reputation'] ?> pts</span></a><?php endforeach; ?></div><?php endif; ?>
    <div class="card mt2"><h3>Community guidelines</h3><ul class="small muted" style="padding-left:1.1em;margin:0"><li>Be respectful and constructive.</li><li>Search before you ask.</li><li>No spam, adverts or personal data.</li><li>Mark the answer that solved your question.</li></ul></div>
    <p class="muted xs mt2"><?= number_format($stats['members']) ?> members · <?= number_format($stats['threads']) ?> discussions · <?= number_format($stats['posts']) ?> replies</p>
  </aside>
</div></section>
