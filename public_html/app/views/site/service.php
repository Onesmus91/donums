<section class="page-hero"><div class="wrap">
  <div class="crumbs"><a href="/">Home</a> / <a href="/#services">Services</a> / <?= e($s['title']) ?></div>
  <span class="eyebrow"><?= e($s['title']) ?></span><h1><?= e($s['headline']) ?></h1>
  <p>Choose the option closest to your need, or describe something different — we will scope it with you before any commitment.</p>
  <div class="hero-actions"><a class="btn btn-accent" href="<?= e(url('/cart', ['service' => $slug])) ?>"><?= e($s['cta']) ?> <?= icon('arrow', 16) ?></a></div>
</div></section>
<section class="section"><div class="wrap">
  <div class="grid g3">
    <?php foreach ($s['items'] as [$name, $summary, $price, $preview]): ?>
    <div class="card" style="display:flex;flex-direction:column">
      <div class="card-ic"><?= icon(svc_icon($slug), 20) ?></div>
      <h3><?= e($name) ?></h3><p class="muted"><?= e($summary) ?></p>
      <details class="small muted"><summary style="cursor:pointer;font-weight:700;color:var(--brand-d)">What to expect</summary><p class="mt1"><?= e($preview) ?></p></details>
      <div style="margin-top:auto;padding-top:16px;display:flex;justify-content:space-between;align-items:center;gap:10px">
        <strong><?= e($price) ?></strong>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/cart', ['service' => $slug, 'need' => $name])) ?>">Request</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($extra): ?>
  <h2 class="mt4">Current listings</h2>
  <div class="grid g3">
    <?php foreach ($extra as $c): ?>
    <div class="card pad0">
      <?php if ($c['image']): ?><div class="thumb"><img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy"></div><?php endif; ?>
      <div style="padding:20px"><h3><?= e($c['title']) ?></h3><p class="muted small"><?= e(excerpt($c['description'], 140)) ?></p>
      <div class="meta"><?php if ($c['price']): ?><span><?= icon('dollar', 14) ?><?= e($c['price']) ?></span><?php endif; ?><?php if ($c['location']): ?><span><?= icon('pin', 14) ?><?= e($c['location']) ?></span><?php endif; ?></div>
      <a class="btn btn-ghost btn-sm mt2" href="<?= e(url('/cart', ['service' => $slug, 'need' => $c['title']])) ?>">Enquire</a></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="cta-band mt4"><div><h2>Not sure what you need?</h2><p>Tell us the outcome you want. We will recommend the right approach.</p></div><a class="btn btn-accent" href="/contact">Talk to our team</a></div>
</div></section>
