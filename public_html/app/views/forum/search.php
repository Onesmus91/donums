<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/forum">Community</a> / Search</div><h1>Search the community</h1></div></section>
<section class="section" style="padding-top:34px"><div class="wrap narrow" style="max-width:900px">
<form method="get" action="/forum/search" style="margin-bottom:20px"><input type="search" name="q" value="<?= e($s) ?>" placeholder="Search discussions…" autofocus></form>
<?php if ($s !== '' && !$threads): ?><?= empty_state('search', 'No results for “' . e($s) . '”', 'Try different keywords, or start a new discussion.', '<a class="btn btn-primary btn-sm" href="/forum/new">Start a discussion</a>') ?>
<?php elseif ($threads): ?><p class="muted small"><?= plural(count($threads), 'result') ?></p><div class="card pad0"><?php foreach ($threads as $t) { echo partial('forum/_thread_row', ['t' => $t]); } ?></div><?php endif; ?>
</div></section>
