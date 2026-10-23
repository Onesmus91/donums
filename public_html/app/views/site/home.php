<section class="hero">
  <div class="wrap hero-grid"><div>
    <span class="eyebrow">Donums Consultants · Kenya</span>
    <h1>Practical support for businesses that need to move faster.</h1>
    <p class="lead">Request marketing, solar and power, websites, property and HR support in one place. We define the scope, confirm a clear price and manage delivery with vetted providers — so you deal with one accountable team.</p>
    <div class="hero-actions">
      <a class="btn btn-accent" href="/cart"><?= icon('send', 18) ?> Request a quotation</a>
      <a class="btn btn-ghost-light" href="#services">Explore services</a>
    </div>
    <div class="hero-stats">
      <div><b>One point of contact</b><span>From first enquiry to final delivery</span></div>
      <div><b>Clear scope and pricing</b><span>Confirmed before any commitment</span></div>
      <div><b>Vetted providers</b><span>Selected and managed by Donums</span></div>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <div class="hc c1"><div class="row"><span class="ic-b"><?= icon('check', 18) ?></span><div><b>Quotation approved</b>Solar installation · Kiambu</div></div></div>
    <div class="hc c2"><div class="row"><span class="ic-b"><?= icon('book', 18) ?></span><div><b>Your course progress</b>Pick up where you left off</div></div><div class="bar"><i></i></div></div>
    <div class="hc c3"><div class="row"><span class="ic-b"><?= icon('briefcase', 18) ?></span><div><b>Interview scheduled</b>Thursday · 10:00 AM</div></div></div>
    <div class="hc c4"><div class="row"><span class="ic-b"><?= icon('video', 18) ?></span><div><b><span class="dotlive"></span>Live session</b>Join from any browser</div></div></div>
  </div></div>
</section>

<section class="section" id="services">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">What we do</span><h2>Services built around real business needs</h2><p>Pick the area closest to your need. Each request is reviewed by our team, scoped properly and quoted before you commit to anything.</p></div>
    <div class="grid g3">
      <?php foreach ($services as $slug => $s): ?>
      <a class="card" href="/services/<?= e($slug) ?>">
        <div class="card-ic"><?= icon(svc_icon($slug), 22) ?></div>
        <h3><?= e($s['title']) ?></h3>
        <p class="muted"><?= e($s['headline']) ?></p>
        <span class="small" style="font-weight:700;color:var(--brand-d)"><?= count($s['items']) ?> offerings · View details <?= icon('arrow', 14) ?></span>
      </a>
      <?php endforeach; ?>
      <a class="card" href="/sell" style="background:var(--dark);border-color:var(--dark)">
        <div class="card-ic" style="background:rgba(0,237,136,.14);color:var(--accent)"><?= icon('briefcase', 22) ?></div>
        <h3 style="color:#fff">Offer your services</h3>
        <p style="color:#a9c1b3">Suppliers, installers, professionals and property owners can join our partner network and receive qualified requests.</p>
        <span class="small" style="font-weight:700;color:var(--accent)">Become a partner <?= icon('arrow', 14) ?></span>
      </a>
    </div>
  </div>
</section>

<section class="section alt">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">How it works</span><h2>From request to result in four steps</h2></div>
    <div class="steps">
      <div class="step"><h3>Tell us what you need</h3><p>Describe the requirement in a few lines. No lengthy forms and no obligation.</p></div>
      <div class="step"><h3>We scope and quote</h3><p>Our team clarifies the details and returns a written quotation with a defined scope.</p></div>
      <div class="step"><h3>We match the right provider</h3><p>Work is assigned to a suitable, vetted provider and managed by Donums.</p></div>
      <div class="step"><h3>Track delivery to completion</h3><p>Follow progress with your reference and receive updates until the job is done.</p></div>
    </div>
  </div>
</section>

<section class="section dark">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow" style="color:var(--accent)">The Donums platform</span><h2>More than services — a place to grow, hire and connect</h2><p>One account gives you access to learning, careers, community and live sessions.</p></div>
    <div class="grid g4">
      <a class="card" href="/learning"><div class="card-ic"><?= icon('book', 22) ?></div><h3>Donums Academy</h3><p>Structured online courses with video lessons, quizzes and verifiable certificates.</p></a>
      <a class="card" href="/jobs"><div class="card-ic"><?= icon('briefcase', 22) ?></div><h3>Donums Careers</h3><p>Find opportunities, apply in minutes and follow your application every step of the way.</p></a>
      <a class="card" href="/forum"><div class="card-ic"><?= icon('chat', 22) ?></div><h3>Community</h3><p>Ask questions, share experience and learn from professionals and entrepreneurs.</p></a>
      <a class="card" href="/webinars"><div class="card-ic"><?= icon('video', 22) ?></div><h3>Donums Live</h3><p>Join live webinars and workshops directly in your browser, with chat, Q&amp;A and replays.</p></a>
    </div>
  </div>
</section>

<?php if ($courses): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">Donums Academy</span><h2>Learn at your own pace</h2></div>
    <div class="grid g3"><?php foreach ($courses as $c) { echo partial('learning/_card', ['c' => $c]); } ?></div>
    <p class="mt3"><a class="btn btn-ghost" href="/learning">Browse all courses <?= icon('arrow', 16) ?></a></p>
  </div>
</section>
<?php endif; ?>

<?php if ($jobs || $events): ?>
<section class="section alt">
  <div class="wrap grid g2" style="gap:36px">
    <div>
      <div class="section-head" style="margin-bottom:18px"><span class="eyebrow">Donums Careers</span><h2 style="font-size:1.5rem">Latest opportunities</h2></div>
      <?php if ($jobs): ?><div class="card pad0"><?php foreach ($jobs as $j) { echo partial('jobs/_row', ['j' => $j, 'compact' => true]); } ?></div><p class="mt2"><a class="link" href="/jobs">See all vacancies →</a></p>
      <?php else: ?><?= empty_state('briefcase', 'No open vacancies right now', 'New roles are published regularly. Join the talent network and we will contact you.', '<a class="btn btn-primary btn-sm" href="/jobs">Visit Careers</a>') ?><?php endif; ?>
    </div>
    <div>
      <div class="section-head" style="margin-bottom:18px"><span class="eyebrow">Donums Live</span><h2 style="font-size:1.5rem">Upcoming sessions</h2></div>
      <?php if ($events): ?><div class="grid"><?php foreach ($events as $w) { echo partial('webinars/_card', ['w' => $w]); } ?></div><p class="mt2"><a class="link" href="/webinars">All sessions →</a></p>
      <?php else: ?><?= empty_state('video', 'No sessions scheduled yet', 'Create an account and we will let you know when the next live session is announced.', '<a class="btn btn-primary btn-sm" href="/webinars">Visit Donums Live</a>') ?><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap">
    <div class="section-head center"><span class="eyebrow">Trusted by</span><h2>Organisations we work with</h2></div>
    <div class="partners"><?php foreach ($partners as [$f, $n]): ?><img src="/assets/img/<?= e($f) ?>" alt="<?= e($n) ?>" loading="lazy"><?php endforeach; ?></div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">Questions</span><h2>Before you request</h2></div>
    <div class="narrow" style="max-width:820px;margin:0">
      <details class="acc"><summary>Does sending a request commit me to anything?</summary><div class="acc-b">No. A request starts a conversation. Scope, price and timelines are confirmed in writing, and nothing is billed until you approve a quotation.</div></details>
      <details class="acc"><summary>How quickly will someone respond?</summary><div class="acc-b">We aim to respond within one business day. For urgent matters call <?= e(cfg('phone1')) ?> or message us on WhatsApp.</div></details>
      <details class="acc"><summary>How do I pay, and is it safe?</summary><div class="acc-b">Pay only against a Donums quotation or invoice, using the payment channels it lists. We never ask you to pay a provider directly unless the quotation says so.</div></details>
      <details class="acc"><summary>Can I follow the progress of my request?</summary><div class="acc-b">Yes. Use the reference we send you on the <a href="/track">tracking page</a> to see the current stage at any time.</div></details>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0"><div class="wrap">
  <div class="cta-band"><div><h2>Ready to get started?</h2><p>Share your requirement and our team will come back with a clear plan and price.</p></div><a class="btn btn-accent" href="/cart">Request a quotation <?= icon('arrow', 16) ?></a></div>
</div></section>
