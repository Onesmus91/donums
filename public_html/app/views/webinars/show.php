<?php $paid = (float) $w['price'] > 0; $canJoin = $reg && $reg['status'] === 'registered' && in_array($w['status'], ['scheduled', 'live'], true); $host = webinar_is_host($w); $rep = video_embed((string) $w['recording_url']);
$open = $w['status'] === 'live' || (strtotime($w['start_at']) - time()) <= 900; ?>
<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/webinars">Donums Live</a> / <?= e(ucfirst($w['kind'])) ?></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px"><?= $state === 'live' ? badge('Live now', 'live') : '' ?><?= $paid ? badge(money($w['price'], $w['currency']), 'amber') : badge('Free', 'green') ?><?= $state === 'cancelled' ? badge('Cancelled', 'red') : '' ?><?= $w['status'] === 'draft' ? badge('Draft — not public', 'amber') : '' ?></div>
  <h1><?= e($w['title']) ?></h1><?php if ($w['summary']): ?><p><?= e($w['summary']) ?></p><?php endif; ?>
  <div class="meta mt2" style="color:#b8cdc0"><span><?= icon('calendar', 15) ?><time data-iso="<?= e(iso($w['start_at'])) ?>"><?= e(fmt_dt($w['start_at'])) ?></time></span><span><?= icon('clock', 15) ?><?= e(minutes_label((int) $w['duration_min'])) ?></span><?php if ($w['host_name']): ?><span><?= icon('user', 15) ?>Hosted by <?= e($w['host_name']) ?></span><?php endif; ?></div>
  <?php if ($state === 'upcoming'): ?><p class="mt2" style="color:var(--accent);font-weight:700">Starts in <span data-countdown="<?= e(iso($w['start_at'])) ?>">…</span></p><?php endif; ?>
</div></section>
<section class="section" style="padding-top:34px"><div class="wrap layout-2">
  <div>
    <?php if (!empty($w['cover'])): ?><div class="thumb" style="aspect-ratio:16/8;border-radius:18px;margin-bottom:20px"><img src="<?= e(img_url($w['cover'])) ?>" alt="<?= e($w['title']) ?>"></div><?php endif; ?>
    <?php if ($rep && $w['status'] === 'ended' && ($w['replay_public'] || $canJoin || $host)): ?><div class="video-box"><?php if ($rep[0] === 'iframe'): ?><iframe src="<?= e($rep[1]) ?>" allowfullscreen title="Replay"></iframe><?php else: ?><video controls src="<?= e($rep[1]) ?>"></video><?php endif; ?></div><?php endif; ?>
    <?php if ($w['description']): ?><div class="card"><h2 style="font-size:1.3rem">About this session</h2><?= md($w['description']) ?></div><?php endif; ?>
    <?php if ($w['agenda']): ?><div class="card mt2"><h2 style="font-size:1.3rem">Agenda</h2><?= md($w['agenda']) ?></div><?php endif; ?>
    <?php if ($speakers): ?><h2 class="mt3" style="font-size:1.3rem">Speakers</h2><div class="grid g2"><?php foreach ($speakers as $s): ?><div class="card" style="display:flex;gap:14px"><?= avatar_html(['name' => $s['name'] ?? '?', 'email' => $s['name'] ?? ''], 52) ?><div><strong><?= e($s['name'] ?? '') ?></strong><div class="muted small"><?= e($s['title'] ?? '') ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <aside class="sticky"><div class="card">
    <?php if ($state === 'cancelled'): ?><div class="callout warn">This session has been cancelled.</div>
    <?php elseif ($host): ?><a class="btn btn-primary btn-block" href="/webinars/<?= e($w['slug']) ?>/room"><?= icon('video', 16) ?> Open host room</a><a class="btn btn-ghost btn-block mt1" href="/admin/webinars/<?= (int) $w['id'] ?>">Manage session</a>
    <?php elseif ($canJoin): ?>
      <div class="callout"><strong><?= icon('ok', 16) ?> You are registered</strong><br><span class="small">Hello <?= e($reg['name']) ?>. Your personal link is ready.</span></div>
      <?php if ($open): ?><a class="btn btn-primary btn-block mt2" href="/webinars/<?= e($w['slug']) ?>/room?t=<?= e($reg['token']) ?>"><?= icon('video', 16) ?> <?= $state === 'live' ? 'Join now' : 'Enter the waiting room' ?></a>
      <?php else: ?><button class="btn btn-primary btn-block mt2" disabled>The room opens 15 minutes before the start</button><?php endif; ?>
      <a class="btn btn-ghost btn-block mt1" href="/webinars/<?= e($w['slug']) ?>/calendar.ics"><?= icon('calendar', 16) ?> Add to calendar</a>
    <?php elseif ($reg && $reg['status'] === 'pending_payment'): ?><div class="callout warn small">Your seat is reserved while your payment is verified.</div><a class="btn btn-primary btn-block mt1" href="/webinars/<?= e($w['slug']) ?>/pay?t=<?= e($reg['token']) ?>">View payment details</a>
    <?php elseif ($w['access'] === 'public' && $state === 'live'): ?><a class="btn btn-primary btn-block" href="/webinars/<?= e($w['slug']) ?>/room"><?= icon('video', 16) ?> Join now</a><p class="muted small mt1">Open to everyone — you will be asked for your name.</p>
    <?php elseif (in_array($w['status'], ['scheduled', 'live'], true)): ?>
      <div class="price"><?= $paid ? e(money($w['price'], $w['currency'])) : 'Free' ?></div><p class="muted small"><?= $seats > 0 ? $seats . ' seats remaining' : 'Fully booked' ?></p>
      <?php if ($seats > 0): $u = user(); ?><form class="form mt1" method="post" action="/webinars/<?= e($w['slug']) ?>/register"><?= csrf_field() . spam_fields() ?>
        <div class="field"><label for="rn">Full name</label><input id="rn" name="name" required value="<?= e($u['name'] ?? '') ?>"></div><div class="field"><label for="re">Email</label><input id="re" type="email" name="email" required value="<?= e($u['email'] ?? '') ?>"></div>
        <div class="row2"><div class="field"><label for="rp">Phone</label><input id="rp" name="phone" value="<?= e($u['phone'] ?? '') ?>"></div><div class="field"><label for="ro">Organisation</label><input id="ro" name="org"></div></div>
        <button class="btn btn-primary btn-block"><?= $paid ? 'Continue to payment' : 'Register — it’s free' ?></button></form><?php endif; ?>
    <?php else: ?><p class="muted mb0">This session has ended.<?= $w['recording_url'] ? '' : ' A recording may be shared soon.' ?></p><?php endif; ?>
    <ul class="list-clean list-check small mt3"><li><?= icon('globe', 15) ?><span>Join from any modern browser — nothing to install</span></li><li><?= icon('chat', 15) ?><span>Live chat, Q&amp;A and polls</span></li><li><?= icon('play', 15) ?><span>Replay shared after the session</span></li></ul>
  </div></aside>
</div></section>
