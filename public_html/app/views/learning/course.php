<?php $access = has_access($c); $pending = $enrol && $enrol['status'] === 'pending'; $total = array_sum(array_map(fn($m) => count($m['lessons']), $outline)); $promo = video_embed((string) $c['promo_url']); ?>
<section class="page-hero"><div class="wrap">
  <div class="crumbs"><a href="/learning">Academy</a> / <?= e($c['category_name'] ?: 'Courses') ?></div>
  <h1><?= e($c['title']) ?></h1><?php if ($c['subtitle']): ?><p><?= e($c['subtitle']) ?></p><?php endif; ?>
  <div class="meta mt2" style="color:#b8cdc0">
    <?php if ((int) $c['rating_count']): ?><span><strong style="color:#fff"><?= number_format((float) $c['rating_avg'], 1) ?></strong> <?= stars((float) $c['rating_avg']) ?> (<?= (int) $c['rating_count'] ?> reviews)</span><?php endif; ?>
    <span><?= icon('users', 15) ?><?= plural((int) $c['enrol_count'], 'learner') ?></span><span><?= icon('clock', 15) ?><?= e(minutes_label((int) $c['duration_min']) ?: '') ?></span><span><?= e($c['level']) ?></span><span><?= icon('globe', 15) ?><?= e($c['language']) ?></span>
  </div>
  <?php if ($c['status'] !== 'published'): ?><p class="mt2"><?= badge(ucfirst($c['status']) . ' — not visible to the public', 'amber') ?></p><?php endif; ?>
</div></section>
<section class="section" style="padding-top:34px"><div class="wrap layout-2">
  <div>
    <?php if ($c['outcomes'] && ($o = json_dec($c['outcomes']))): ?><div class="card"><h3>What you will learn</h3><ul class="list-clean list-check" style="columns:2;column-gap:28px"><?php foreach ($o as $x): ?><li style="break-inside:avoid"><?= icon('check', 16) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
    <h2 class="mt4" style="font-size:1.4rem">Course content</h2>
    <p class="muted small"><?= plural(count($outline), 'section') ?> · <?= plural($total, 'lesson') ?> · <?= e(minutes_label((int) $c['duration_min']) ?: 'self-paced') ?></p>
    <div class="syllabus"><?php foreach ($outline as $i => $m): ?>
      <details class="mod" <?= $i === 0 ? 'open' : '' ?>><summary><span><?= e($m['title']) ?></span><span class="muted small"><?= plural(count($m['lessons']), 'lesson') ?></span></summary>
        <?php foreach ($m['lessons'] as $l): ?><div class="les"><?= icon(lesson_icon($l['kind']), 17) ?><span class="grow"><?= $l['is_preview'] || $access ? '<a href="/learning/' . e($c['slug']) . '/learn/' . (int) $l['id'] . '">' . e($l['title']) . '</a>' : e($l['title']) ?></span>
          <?php if ($l['is_preview'] && !$access): ?><?= badge('Preview', 'green') ?><?php elseif (!$access): ?><?= icon('lock', 14) ?><?php endif; ?><?php if ($l['duration_min']): ?><span class="muted small"><?= (int) $l['duration_min'] ?> min</span><?php endif; ?></div><?php endforeach; ?>
      </details><?php endforeach; ?>
      <?php if (!$outline): ?><p class="muted">The syllabus is being prepared.</p><?php endif; ?></div>
    <?php if ($c['description']): ?><h2 class="mt4" style="font-size:1.4rem">About this course</h2><?= md($c['description']) ?><?php endif; ?>
    <?php if ($c['requirements'] && ($r = json_dec($c['requirements']))): ?><h3 class="mt3">Requirements</h3><ul><?php foreach ($r as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($c['audience']): ?><h3 class="mt3">Who this course is for</h3><?= md($c['audience']) ?><?php endif; ?>
    <?php if ($c['instructor_name']): ?><h2 class="mt4" style="font-size:1.4rem">Your instructor</h2>
      <div class="card" style="display:flex;gap:16px"><?= avatar_html(['name' => $c['instructor_name'], 'email' => $c['instructor_name'], 'avatar' => $c['instructor_avatar']], 64) ?><div><strong><?= e($c['instructor_name']) ?></strong><div class="muted small"><?= e($c['instructor_headline']) ?></div><p class="small mt1"><?= e($c['instructor_bio']) ?></p></div></div><?php endif; ?>
    <?php if ($c['faq'] && ($faq = json_dec($c['faq']))): ?><h2 class="mt4" style="font-size:1.4rem">Frequently asked questions</h2><?php foreach ($faq as $f): ?><details class="acc"><summary><?= e($f['q'] ?? '') ?></summary><div class="acc-b"><?= e($f['a'] ?? '') ?></div></details><?php endforeach; endif; ?>

    <h2 class="mt4" id="reviews" style="font-size:1.4rem">Learner reviews</h2>
    <?php if ((int) $c['rating_count']): ?><div class="card" style="display:grid;grid-template-columns:140px 1fr;gap:24px;align-items:center"><div class="center"><div class="price"><?= number_format((float) $c['rating_avg'], 1) ?></div><?= stars((float) $c['rating_avg'], 16) ?><div class="muted small"><?= (int) $c['rating_count'] ?> reviews</div></div>
      <div><?php for ($i = 5; $i >= 1; $i--): $pct = $c['rating_count'] ? round($dist[$i] * 100 / $c['rating_count']) : 0; ?><div style="display:flex;gap:10px;align-items:center;font-size:.85rem"><span style="width:14px"><?= $i ?></span><div class="progress" style="flex:1"><i style="width:<?= $pct ?>%"></i></div><span class="muted" style="width:34px"><?= $pct ?>%</span></div><?php endfor; ?></div></div><?php endif; ?>
    <?php foreach ($reviews as $r): ?><div class="card mt2"><div style="display:flex;gap:12px;align-items:center"><?= avatar_for((int) $r['user_id'], 38) ?><div><strong><?= e($r['name']) ?></strong><div><?= stars((float) $r['rating'], 13) ?> <span class="muted xs"><?= e(ago($r['created_at'])) ?></span></div></div></div><?php if ($r['body']): ?><p class="mt1 mb0"><?= nl2br(e($r['body'])) ?></p><?php endif; ?></div><?php endforeach; ?>
    <?php if (!$reviews): ?><p class="muted">No reviews yet. Be the first to share your experience once you start learning.</p><?php endif; ?>
    <?php if ($access && user()): ?><form class="card form mt2" method="post" action="/learning/<?= e($c['slug']) ?>/review"><?= csrf_field() ?><h3><?= $mine ? 'Update your review' : 'Rate this course' ?></h3>
      <div class="field"><label for="rating">Rating</label><select id="rating" name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= ($mine['rating'] ?? 5) == $i ? 'selected' : '' ?>><?= $i ?> — <?= ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'][$i] ?></option><?php endfor; ?></select></div>
      <div class="field"><label for="body">Your review</label><textarea id="body" name="body" rows="3"><?= e($mine['body'] ?? '') ?></textarea></div><div><button class="btn btn-primary btn-sm">Submit review</button></div></form><?php endif; ?>

    <?php if ($threads || user()): ?><h2 class="mt4" style="font-size:1.4rem">Course discussions</h2>
      <?php foreach ($threads as $t): ?><p style="margin:0 0 8px"><?= icon('chat', 15) ?> <a href="/forum/t/<?= (int) $t['id'] ?>-<?= e($t['slug']) ?>"><?= e($t['title']) ?></a> <span class="muted small">· <?= plural((int) $t['reply_count'], 'reply', 'replies') ?></span></p><?php endforeach; ?>
      <?php if ($access): ?><a class="btn btn-ghost btn-sm mt1" href="<?= e(url('/forum/new', ['course' => $c['id']])) ?>">Ask a question about this course</a><?php endif; ?><?php endif; ?>
  </div>

  <aside class="sticky"><div class="card pad0" style="overflow:hidden">
    <div class="thumb"><?php if ($promo && $promo[0] === 'iframe'): ?><iframe src="<?= e($promo[1]) ?>" style="width:100%;height:100%;border:0" allowfullscreen loading="lazy" title="Course preview"></iframe><?php elseif ($c['thumbnail']): ?><img src="<?= e(img_url($c['thumbnail'])) ?>" alt=""><?php else: echo icon('book', 44); endif; ?></div>
    <div style="padding:22px">
      <div class="price"><?= e(money($c['price'], $c['currency'])) ?></div>
      <?php if ($access): ?>
        <a class="btn btn-primary btn-block mt2" href="/learning/<?= e($c['slug']) ?>/learn"><?= $enrol && (int) $enrol['progress'] > 0 ? 'Continue learning' : 'Go to course' ?></a>
        <?php if ($enrol): ?><div class="progress mt2"><i style="width:<?= (int) $enrol['progress'] ?>%"></i></div><div class="small muted mt1"><?= (int) $enrol['progress'] ?>% complete</div><?php endif; ?>
      <?php elseif ($pending): ?>
        <div class="callout warn mt2 small">Your payment is being verified. We will email you as soon as access is active.</div>
        <a class="btn btn-ghost btn-block mt1" href="/learning/<?= e($c['slug']) ?>/pay">View payment details</a>
      <?php else: ?>
        <form method="post" action="/learning/<?= e($c['slug']) ?>/enrol"><?= csrf_field() ?><button class="btn btn-primary btn-block mt2"><?= (float) $c['price'] > 0 ? 'Enrol now' : 'Enrol for free' ?></button></form>
        <?php if (!user()): ?><p class="small muted mt1 center">You will be asked to sign in or create a free account.</p><?php endif; ?>
      <?php endif; ?>
      <ul class="list-clean list-check small mt3">
        <li><?= icon('play', 15) ?><span><?= plural($total, 'lesson') ?> · <?= e(minutes_label((int) $c['duration_min']) ?: 'self-paced') ?></span></li>
        <li><?= icon('clock', 15) ?><span>Learn at your own pace, on any device</span></li>
        <?php if ($c['certificate']): ?><li><?= icon('award', 15) ?><span>Verifiable certificate of completion</span></li><?php endif; ?>
      </ul>
    </div></div></aside>
</div></section>
