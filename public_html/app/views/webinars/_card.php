<?php $st = webinar_state($w); $ts = strtotime($w['start_at']); ?>
<a class="card<?= !empty($w['cover']) ? ' pad0' : '' ?>" href="/webinars/<?= e($w['slug']) ?>">
  <?php if (!empty($w['cover'])): ?><div class="thumb"><img src="<?= e(img_url($w['cover'])) ?>" alt="" loading="lazy"></div><?php endif; ?>
  <div style="display:flex;gap:16px;align-items:flex-start;<?= !empty($w['cover']) ? 'padding:18px 20px 20px' : '' ?>">
  <div class="event-date"><b><?= date('j', $ts) ?></b><span><?= date('M', $ts) ?></span></div>
  <div style="min-width:0"><div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px"><?= $st === 'live' ? badge('Live now', 'live') : '' ?><?= (float) $w['price'] > 0 ? badge(money($w['price'], $w['currency']), 'amber') : badge('Free', 'green') ?></div>
    <h3 style="margin-bottom:4px"><?= e($w['title']) ?></h3>
    <div class="meta"><span><?= icon('clock', 14) ?><time data-iso="<?= e(iso($w['start_at'])) ?>"><?= e(fmt_dt($w['start_at'])) ?></time></span><span><?= e(minutes_label((int) $w['duration_min'])) ?></span><?php if ($w['host_name']): ?><span><?= icon('user', 14) ?><?= e($w['host_name']) ?></span><?php endif; ?></div></div></div>
</a>
