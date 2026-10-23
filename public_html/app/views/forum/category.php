<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/forum">Community</a> / <?= e($c['name']) ?></div><h1><?= e($c['name']) ?></h1><p><?= e($c['description']) ?></p><div class="hero-actions"><a class="btn btn-accent btn-sm" href="<?= e(url('/forum/new', ['cat' => $c['slug']])) ?>">Start a discussion</a></div></div></section>
<section class="section" style="padding-top:34px"><div class="wrap narrow" style="max-width:900px">
<?php if (!$threads): ?><?= empty_state('chat', 'No discussions here yet', 'Start the first one.', '<a class="btn btn-primary btn-sm" href="' . e(url('/forum/new', ['cat' => $c['slug']])) . '">Start a discussion</a>') ?>
<?php else: ?><div class="card pad0"><?php foreach ($threads as $t) { echo partial('forum/_thread_row', ['t' => $t]); } ?></div><?= pager($pg, '/forum/c/' . $c['slug']) ?><?php endif; ?>
</div></section>
