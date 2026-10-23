<?php
$flow = ['New' => ['Received', 'We have your request and it is waiting for review.'], 'Contacted' => ['In review', 'A consultant has reviewed your request and is in touch.'], 'Quoted' => ['Quotation sent', 'A written quotation has been shared with you.'], 'Won' => ['Confirmed', 'Your order is confirmed and work is under way.'], 'Lost' => ['Closed', 'This request has been closed.']];
$order = ['New', 'Contacted', 'Quoted', 'Won'];
?>
<section class="page-hero"><div class="wrap"><span class="eyebrow">Request tracking</span><h1>Where is my request?</h1><p>Enter the reference we sent you and the phone number or email you used.</p></div></section>
<section class="section"><div class="wrap" style="max-width:820px">
  <form class="card form" method="get" action="/track">
    <div class="row2"><div class="field"><label for="ref">Reference</label><input id="ref" name="ref" value="<?= e($ref) ?>" placeholder="DN-261017-A1B2C3" required></div>
    <div class="field"><label for="contact">Phone or email used</label><input id="contact" name="contact" value="<?= e($contact) ?>" required></div></div>
    <button class="btn btn-primary" type="submit">Check status</button>
  </form>
  <?php if ($err): ?><div class="flash err mt2"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
  <?php if ($r): $cur = array_search($r['status'], $order, true); $closed = $r['status'] === 'Lost'; ?>
  <div class="card mt3">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><div><span class="muted small">Reference</span><h2 style="margin:0"><?= e($r['ref']) ?></h2></div><div><?= badge($flow[$r['status']][0] ?? $r['status'], $closed ? 'red' : 'green') ?></div></div>
    <p class="muted"><?= e($r['service']) ?><?= $r['need'] ? ' — ' . e($r['need']) : '' ?> · submitted <?= e(fmt_date($r['created_at'])) ?></p>
    <?php if (!$closed): ?><ul class="timeline mt2"><?php foreach ($order as $i => $k): ?>
      <li class="<?= $i <= (int) $cur ? 'cur' : '' ?>"><strong><?= e($flow[$k][0]) ?></strong><br><span class="muted small"><?= e($flow[$k][1]) ?></span></li><?php endforeach; ?></ul>
    <?php else: ?><p><?= e($flow['Lost'][1]) ?> If you would still like to proceed, please <a href="/contact">contact us</a>.</p><?php endif; ?>
    <?php if ($r['followup']): ?><p class="small muted">Next follow-up planned: <?= e(fmt_date($r['followup'])) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>
</div></section>
