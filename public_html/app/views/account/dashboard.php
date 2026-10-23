<section class="page-hero"><div class="wrap" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
  <?= avatar_html($u, 64) ?><div style="flex:1;min-width:220px"><span class="eyebrow">My account</span><h1 style="margin:0">Hello, <?= e(explode(' ', $u['name'])[0]) ?></h1><p><?= e($u['email']) ?></p></div>
  <a class="btn btn-ghost-light btn-sm" href="/account/profile">Profile &amp; security</a>
</div></section>
<section class="section" style="padding-top:34px"><div class="wrap">
  <?php if (!$u['email_verified_at']): ?><div class="callout warn" style="margin-bottom:22px;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:center"><span><strong>Confirm your email address.</strong> We sent a link to <?= e($u['email']) ?>. You need a confirmed email to post in the community.</span>
  <form method="post" action="/account/verify/resend"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Resend link</button></form></div><?php endif; ?>
  <div class="layout-2">
    <div>
      <h2 style="font-size:1.4rem">My learning</h2>
      <?php if (!$enrol): ?><?= empty_state('book', 'You haven’t enrolled in a course yet', 'Browse Donums Academy to start learning.', '<a class="btn btn-primary btn-sm" href="/learning">Browse courses</a>') ?>
      <?php else: ?><div class="grid g2"><?php foreach ($enrol as $e): ?>
        <a class="card" href="/learning/<?= e($e['slug']) ?>/learn"><h3 style="font-size:1rem"><?= e($e['title']) ?></h3>
          <?php if ($e['status'] === 'pending'): ?><?= badge('Awaiting payment verification', 'amber') ?>
          <?php else: ?><div class="progress mt1"><i style="width:<?= (int) $e['progress'] ?>%"></i></div><div class="small muted mt1"><?= (int) $e['progress'] ?>% complete<?= $e['status'] === 'completed' ? ' · ' . badge('Completed', 'green') : '' ?></div><?php endif; ?></a>
      <?php endforeach; ?></div><?php endif; ?>

      <h2 class="mt4" style="font-size:1.4rem">My applications</h2>
      <?php if (!$apps): ?><?= empty_state('briefcase', 'No applications yet', 'Browse current vacancies on Donums Careers.', '<a class="btn btn-primary btn-sm" href="/jobs">View vacancies</a>') ?>
      <?php else: ?><div class="table-wrap"><table class="t"><thead><tr><th>Role</th><th>Stage</th><th>Applied</th><th></th></tr></thead><tbody><?php foreach ($apps as $a): ?>
        <tr><td><strong><?= e($a['job_title'] ?: 'Talent network') ?></strong><div class="muted small"><?= e($a['organisation']) ?></div></td><td><?= badge($a['stage'], in_array($a['stage'], ['Hired', 'Offer']) ? 'green' : ($a['stage'] === 'Rejected' ? 'red' : 'blue')) ?></td><td><?= e(fmt_date($a['created_at'])) ?></td><td><a class="link" href="<?= e(url('/jobs/status', ['ref' => $a['ref'], 'email' => $a['email']])) ?>">Details</a></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>

      <h2 class="mt4" style="font-size:1.4rem">My live sessions</h2>
      <?php if (!$regs): ?><?= empty_state('video', 'No registrations yet', 'Join an upcoming webinar or workshop.', '<a class="btn btn-primary btn-sm" href="/webinars">See sessions</a>') ?>
      <?php else: ?><div class="table-wrap"><table class="t"><thead><tr><th>Session</th><th>When</th><th></th></tr></thead><tbody><?php foreach ($regs as $r): ?>
        <tr><td><strong><?= e($r['title']) ?></strong> <?= $r['status'] === 'pending_payment' ? badge('Payment pending', 'amber') : '' ?></td><td><time data-iso="<?= e(iso($r['start_at'])) ?>"><?= e(fmt_dt($r['start_at'])) ?></time></td>
        <td><?php if ($r['status'] === 'registered' && in_array($r['wstatus'], ['scheduled', 'live'])): ?><a class="btn btn-primary btn-xs" href="/webinars/<?= e($r['slug']) ?>/room?t=<?= e($r['token']) ?>">Join</a><?php else: ?><a class="link" href="/webinars/<?= e($r['slug']) ?>">View</a><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
    <aside>
      <div class="card"><h3>Certificates</h3>
        <?php if (!$certs): ?><p class="muted small">Complete a course to earn a verifiable certificate.</p><?php else: foreach ($certs as $c): ?><p class="small" style="margin:0 0 10px"><?= icon('award', 16) ?> <a href="/learning/certificate/<?= e($c['code']) ?>"><?= e($c['title']) ?></a><br><span class="muted xs"><?= e(fmt_date($c['issued_at'])) ?> · <?= e($c['code']) ?></span></p><?php endforeach; endif; ?></div>
      <div class="card mt2"><h3>My discussions</h3>
        <?php if (!$threads): ?><p class="muted small">You haven’t started a discussion yet.</p><?php else: foreach ($threads as $t): ?><p class="small" style="margin:0 0 8px"><a href="/forum/t/<?= (int) $t['id'] ?>-<?= e($t['slug']) ?>"><?= e($t['title']) ?></a> <span class="muted">· <?= plural((int) $t['reply_count'], 'reply', 'replies') ?></span></p><?php endforeach; endif; ?>
        <a class="btn btn-ghost btn-sm mt1" href="/forum/new">Start a discussion</a></div>
      <div class="card mt2"><a href="/account/notifications" style="display:flex;gap:10px;align-items:center;color:inherit"><?= icon('bell') ?> <strong>Notifications</strong><?= ($n = unread_notifications()) ? badge((string) $n, 'red') : '' ?></a></div>
    </aside>
  </div>
</div></section>
