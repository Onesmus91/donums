<?php $paybill = setting('pay_mpesa_paybill'); $till = setting('pay_mpesa_till'); $acct = setting('pay_mpesa_account'); $bank = setting('pay_bank'); ?>
<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/learning">Academy</a> / <a href="/learning/<?= e($c['slug']) ?>"><?= e($c['title']) ?></a> / Payment</div><h1>Complete your enrolment</h1><p>Pay using one of the options below, then submit your confirmation so that we can verify it.</p></div></section>
<section class="section" style="padding-top:34px"><div class="wrap layout-2" style="grid-template-columns:minmax(0,1fr) 340px">
  <div>
    <div class="card"><h3>1 · Make your payment</h3>
      <p>Amount due: <strong style="font-size:1.3rem"><?= e(money($pay['amount'], $pay['currency'])) ?></strong> · Reference: <strong><?= e($pay['ref']) ?></strong></p>
      <?php if ($paybill || $till): ?><div class="callout"><strong>M-Pesa</strong><br><?php if ($paybill): ?>Paybill <strong><?= e($paybill) ?></strong> · Account <strong><?= e($acct ?: $pay['ref']) ?></strong><?php endif; ?><?php if ($till): ?><br>Buy Goods Till <strong><?= e($till) ?></strong><?php endif; ?></div><?php endif; ?>
      <?php if ($bank): ?><div class="callout info mt2"><strong>Bank transfer</strong><br><?= nl2br(e($bank)) ?></div><?php endif; ?>
      <?php if (!$paybill && !$till && !$bank): ?><div class="callout warn">Payment details have not been configured yet. Please contact <a href="mailto:<?= e(cfg('email')) ?>"><?= e(cfg('email')) ?></a> or call <?= e(cfg('phone1')) ?> and we will assist you.</div><?php endif; ?>
      <p class="small muted mt2"><?= e(setting('pay_note')) ?></p></div>
    <form class="card form mt2" method="post" enctype="multipart/form-data" action="/payment/<?= e($pay['ref']) ?>/submit"><?= csrf_field() ?><input type="hidden" name="method" value="mpesa">
      <h3>2 · Submit your confirmation</h3>
      <?php if ($pay['status'] === 'rejected'): ?><div class="flash err"><?= icon('alert') ?><span>Your previous submission could not be verified. <?= e($pay['note']) ?> Please check the details and submit again.</span></div><?php endif; ?>
      <?php if ($pay['status'] === 'submitted'): ?><div class="flash info"><?= icon('clock') ?><span>We received your details (<?= e($pay['method_ref']) ?>) and are verifying them.</span></div><?php endif; ?>
      <div class="field"><label for="mr">M-Pesa or bank transaction code</label><input id="mr" name="method_ref" value="<?= e($pay['method_ref']) ?>" required maxlength="40" placeholder="e.g. SJK7X2ABCD" style="text-transform:uppercase"></div>
      <div class="field"><label for="pf">Payment screenshot or receipt <span class="muted">(optional)</span></label><input id="pf" type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png"></div>
      <div><button class="btn btn-primary">Submit for verification</button></div>
    </form>
  </div>
  <aside class="sticky"><div class="card"><h3><?= e($c['title']) ?></h3><p class="muted small"><?= e($c['subtitle']) ?></p><p class="small"><?= icon('shield', 15) ?> Access is activated as soon as your payment is verified.</p></div></aside>
</div></section>
