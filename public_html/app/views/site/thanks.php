<section class="section"><div class="wrap narrow center">
  <div class="card-ic" style="margin:0 auto 18px;width:64px;height:64px;border-radius:50%"><?= icon('check', 30) ?></div>
  <h1>Request received</h1>
  <p class="muted" style="font-size:1.1rem">Thank you. A member of our team will review your request and respond within one business day.</p>
  <?php if ($ref): ?><div class="card" style="display:inline-block;margin:10px 0"><span class="muted small">Your reference</span><div class="price" style="letter-spacing:.04em"><?= e($ref) ?></div><span class="muted small">Keep this to track your request</span></div><?php endif; ?>
  <div class="hero-actions" style="justify-content:center"><a class="btn btn-primary" href="<?= e(url('/track', ['ref' => $ref])) ?>">Track this request</a><a class="btn btn-ghost" href="/">Back to home</a></div>
</div></section>
