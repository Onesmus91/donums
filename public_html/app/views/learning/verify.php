<section class="page-hero"><div class="wrap"><span class="eyebrow">Donums Academy</span><h1>Verify a certificate</h1><p>Enter the certificate ID printed at the bottom of the certificate.</p></div></section>
<section class="section"><div class="wrap narrow">
  <form class="card form" method="get" action="/learning/verify"><div class="field"><label for="code">Certificate ID</label><input id="code" name="code" value="<?= e($code) ?>" placeholder="DN-XXXXXXXXXX" required></div><div><button class="btn btn-primary">Verify</button></div></form>
  <?php if ($code !== ''): ?><?php if ($cert): ?><div class="flash ok mt2"><?= icon('ok') ?><span><strong>Valid certificate.</strong> Issued to <strong><?= e($cert['holder_name'] ?: $cert['name']) ?></strong> for <strong><?= e($cert['title']) ?></strong> on <?= e(fmt_date($cert['issued_at'])) ?>.</span></div>
  <?php else: ?><div class="flash err mt2"><?= icon('alert') ?><span>No certificate matches that ID. Check for typing errors, or contact us if you believe this is a mistake.</span></div><?php endif; endif; ?>
</div></section>
