<a class="card pad0 course-card" href="/learning/<?= e($c['slug']) ?>">
  <div class="thumb"><?php if (!empty($c['thumbnail'])): ?><img src="<?= e(img_url($c['thumbnail'])) ?>" alt="" loading="lazy"><?php else: echo icon('book', 40); endif; ?></div>
  <div class="body">
    <?php if (!empty($c['category_name'])): ?><span class="xs muted" style="font-weight:700;text-transform:uppercase;letter-spacing:.08em"><?= e($c['category_name']) ?></span><?php endif; ?>
    <h3><?= e($c['title']) ?></h3>
    <div class="by"><?= e($c['instructor_name'] ?? 'Donums Academy') ?></div>
    <?php if ((int) $c['rating_count'] > 0): ?><div class="small"><strong><?= number_format((float) $c['rating_avg'], 1) ?></strong> <?= stars((float) $c['rating_avg'], 13) ?> <span class="muted">(<?= (int) $c['rating_count'] ?>)</span></div><?php endif; ?>
    <div class="meta mt1"><span><?= icon('clock', 14) ?><?= $c['duration_min'] ? e(minutes_label((int) $c['duration_min'])) : e(plural((int) $c['lessons_count'], 'lesson')) ?></span><span><?= e($c['level']) ?></span></div>
    <div class="mt1" style="font-weight:800"><?= e(money($c['price'], $c['currency'])) ?></div>
  </div>
</a>
