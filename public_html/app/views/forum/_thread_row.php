<div class="thread-row">
  <?= avatar_html(['name' => $t['author_name'], 'email' => $t['author_email'], 'avatar' => $t['author_avatar']], 42) ?>
  <div style="min-width:0;flex:1">
    <h3><?php if ($t['is_pinned']): ?><?= icon('pin', 15) ?> <?php endif; ?><?php if ($t['is_locked']): ?><?= icon('lock', 14) ?> <?php endif; ?><a href="<?= e(thread_url($t)) ?>"><?= e($t['title']) ?></a> <?= $t['solved_post_id'] ? badge('Solved', 'green') : '' ?></h3>
    <div class="meta"><a href="/forum/c/<?= e($t['category_slug']) ?>"><?= e($t['category_name']) ?></a><span><?= e($t['author_name']) ?></span><span><?= e(ago($t['last_post_at'] ?: $t['created_at'])) ?></span></div>
  </div>
  <div class="thread-stats"><b><?= (int) $t['reply_count'] ?></b>replies</div>
  <div class="thread-stats"><b><?= (int) $t['score'] ?></b>votes</div>
</div>
