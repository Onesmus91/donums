<section class="page-hero"><div class="wrap"><span class="eyebrow">Account</span><h1>Notifications</h1></div></section>
<section class="section"><div class="wrap narrow">
<?php if (!$items): ?><?= empty_state('bell', 'You’re all caught up', 'Updates about your courses, applications, sessions and discussions will appear here.') ?>
<?php else: ?><div class="card pad0"><?php foreach ($items as $n): ?><a class="thread-row" href="<?= e($n['url'] ?: '/account') ?>" style="color:inherit"><div class="card-ic" style="margin:0;flex:none"><?= icon('bell', 18) ?></div><div><strong><?= e($n['title']) ?></strong><div class="muted small"><?= e($n['body']) ?></div></div><span class="thread-stats"><?= e(ago($n['created_at'])) ?></span></a><?php endforeach; ?></div><?php endif; ?>
</div></section>
