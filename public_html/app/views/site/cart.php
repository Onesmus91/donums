<section class="page-hero"><div class="wrap">
  <div class="crumbs"><a href="/">Home</a> / <a href="/#services">Solutions</a> / <?= e($s['title']) ?></div>
  <span class="eyebrow"><?= e($s['title']) ?></span>
  <h1><?= e($s['headline']) ?></h1>
  <p>Strategic HR support for real business growth. Choose the service area closest to your need and we will help you scope the right approach.</p>
  <div class="hero-actions"><a class="btn btn-accent" href="<?= e(url('/cart', ['service' => $slug])) ?>"><?= e($s['cta']) ?> <?= icon('arrow', 16) ?></a></div>
</div></section>

<section class="section"><div class="wrap">
  <div class="grid g3">
    <?php foreach ($s['items'] as [$name, $summary, $price, $preview]): ?>
    <div class="card service-card" style="display:flex;flex-direction:column">
      <div class="card-ic"><?= icon(svc_icon($slug), 20) ?></div>
      <h3><?= e($name) ?></h3>
      <p class="muted"><?= e($summary) ?></p>
      <div class="service-meta">
        <span class="chip chip-soft"><?= e($price) ?></span>
      </div>
      <details class="service-details">
        <summary>What to expect</summary>
        <p class="mt1"><?= e($preview) ?></p>
      </details>
      <div style="margin-top:auto;padding-top:18px;display:flex;justify-content:space-between;align-items:center;gap:10px">
        <span class="small muted">Tailored support</span>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/cart', ['service' => $slug, 'need' => $name])) ?>">Request</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($extra): ?>
  <h2 class="mt4">Current opportunities</h2>
  <div class="grid g3">
    <?php foreach ($extra as $c): ?>
    <div class="card pad0 service-listing">
      <?php if ($c['image']): ?><div class="thumb"><img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy"></div><?php endif; ?>
      <div style="padding:20px">
        <h3><?= e($c['title']) ?></h3>
        <p class="muted small"><?= e(excerpt($c['description'], 140)) ?></p>
        <div class="meta"><?php if ($c['price']): ?><span><?= icon('dollar', 14) ?><?= e($c['price']) ?></span><?php endif; ?><?php if ($c['location']): ?><span><?= icon('pin', 14) ?><?= e($c['location']) ?></span><?php endif; ?></div>
        <a class="btn btn-ghost btn-sm mt2" href="<?= e(url('/cart', ['service' => $slug, 'need' => $c['title']])) ?>">Enquire</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="cta-band mt4"><div><h2>Need a custom HR solution?</h2><p>Tell us the business challenge and we will recommend the most appropriate engagement model.</p></div><a class="btn btn-accent" href="/contact">Talk to us</a></div>
</div></section>
