<div class="stats">
<?php if (isset($c['requests_new'])): ?>
  <a class="stat <?= $c['requests_new'] ? 'alert' : '' ?>" href="/admin/requests?status=New"><b><?= $c['requests_new'] ?></b><span>New requests</span></a>
  <a class="stat" href="/admin/requests"><b><?= $c['requests_open'] ?></b><span>Open requests</span></a>
  <a class="stat <?= $c['payments'] ? 'alert' : '' ?>" href="/admin/payments"><b><?= $c['payments'] ?></b><span>Payments to verify</span></a><?php endif; ?>
<?php if (isset($c['unread'])): ?><a class="stat" href="/admin/mail"><b><?= $c['unread'] ?></b><span>Unread emails</span></a><?php endif; ?>
<?php if (isset($c['apps_new'])): ?><a class="stat" href="/admin/ats"><b><?= $c['apps_new'] ?></b><span>New applications</span></a><a class="stat <?= $c['jobs_pending'] ? 'alert' : '' ?>" href="/admin/ats/jobs?status=pending"><b><?= $c['jobs_pending'] ?></b><span>Vacancies to review</span></a><?php endif; ?>
<?php if (isset($c['pending_posts'])): ?><a class="stat <?= ($c['pending_posts'] + $c['reports']) ? 'alert' : '' ?>" href="/admin/forum"><b><?= $c['pending_posts'] + $c['reports'] ?></b><span>Community items to moderate</span></a><?php endif; ?>
<?php if (isset($c['learners'])): ?><a class="stat" href="/admin/courses"><b><?= $c['learners'] ?></b><span>Active learners · <?= $c['courses'] ?> courses</span></a><?php endif; ?>
<?php if (isset($c['users'])): ?><a class="stat" href="/admin/users"><b><?= $c['users'] ?></b><span>Registered users</span></a><?php endif; ?>
</div>
<div class="two">
  <div>
    <?php if (isset($c['recent_requests'])): ?><div class="panel"><div class="panel-h"><span>Latest requests</span><a class="link" href="/admin/requests">View all</a></div>
      <?php if (!$c['recent_requests']): ?><div class="panel-b muted">No requests yet.</div><?php else: ?><div class="table-wrap" style="border:0"><table class="t"><tbody><?php foreach ($c['recent_requests'] as $r): ?><tr><td><a href="/admin/requests/<?= e($r['ref']) ?>"><strong><?= e($r['name']) ?></strong></a><div class="muted xs"><?= e($r['service']) ?> · <?= e($r['need']) ?></div></td><td><?= badge($r['status'], $r['status'] === 'New' ? 'amber' : ($r['status'] === 'Won' ? 'green' : '')) ?></td><td class="muted xs"><?= e(ago($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><?php endif; ?>
    <?php if (!empty($c['interviews'])): ?><div class="panel"><div class="panel-h">Upcoming interviews</div><div class="panel-b"><?php foreach ($c['interviews'] as $i): ?><p style="margin:0 0 8px"><?= icon('calendar', 15) ?> <strong><?= e($i['name']) ?></strong> — <?= e(fmt_dt($i['starts_at'])) ?> · <?= e($i['mode']) ?></p><?php endforeach; ?></div></div><?php endif; ?>
    <?php if (!empty($c['next_webinars'])): ?><div class="panel"><div class="panel-h"><span>Live sessions</span><a class="link" href="/admin/webinars">Manage</a></div><div class="panel-b"><?php foreach ($c['next_webinars'] as $w): ?><p style="margin:0 0 8px"><?= $w['status'] === 'live' ? badge('Live', 'live') : icon('calendar', 15) ?> <a href="/admin/webinars/<?= (int) $w['id'] ?>"><strong><?= e($w['title']) ?></strong></a> — <?= e(fmt_dt($w['start_at'])) ?></p><?php endforeach; ?></div></div><?php endif; ?>
  </div>
  <div>
    <?php if (isset($c['health'])): ?><div class="panel"><div class="panel-h"><span>System health</span><a class="link" href="/admin/system">Details</a></div><div class="panel-b"><?php foreach ($c['health'] as [$k, $s, $m]): ?><div class="health"><span class="dotc <?= $s ?>"></span><div><strong><?= e($k) ?></strong><div class="muted xs"><?= e($m) ?></div></div></div><?php endforeach; ?></div></div><?php endif; ?>
    <?php if (isset($c['queued'])): ?><div class="panel"><div class="panel-h">Mail queue</div><div class="panel-b small"><?= (int) $c['queued'] ?> waiting to send · <?= (int) $c['failed'] ?> failed. <a href="/admin/mail?folder=outbox">Open outbox</a></div></div><?php endif; ?>
  </div>
</div>
