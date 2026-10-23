<section class="page-hero"><div class="wrap"><span class="eyebrow">Request a consultation</span><h1>Tell us what you need</h1><p>Share the outcome you want and our team will review it with a strategic, people-first lens.</p></div></section>
<section class="section"><div class="wrap layout-2">
  <form class="card form premium-form" method="post" action="/request">
    <?= csrf_field() . spam_fields() ?>
    <div class="row2">
      <div class="field"><label for="service">Service area</label>
        <select id="service" name="service" required><option value="">Select a solution</option><?php foreach ($all as $k => $s): ?><option value="<?= e($k) ?>"><?= e($s['title']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="field"><label for="need">Requirement</label><input id="need" name="need" list="needs" maxlength="160" placeholder="e.g. Senior recruiter, payroll cleanup, hiring strategy" value="<?= e(qget('need') ?: '') ?>"></div>
    </div>
    <div class="row2">
      <div class="field"><label for="name">Your name</label><input id="name" name="name" required maxlength="120" autocomplete="name"></div>
      <div class="field"><label for="contact">Phone or email</label><input id="contact" name="contact" required maxlength="160" autocomplete="email" placeholder="So that we can reach you"></div>
    </div>
    <div class="row2">
      <div class="field"><label for="organisation">Organisation <span class="muted">(optional)</span></label><input id="organisation" name="organisation" maxlength="160" autocomplete="organization"></div>
      <div class="field"><label for="location">Location</label><input id="location" name="location" maxlength="160" placeholder="Town / city / country"></div>
    </div>
    <div class="field"><label for="details">Details</label><textarea id="details" name="details" rows="5" maxlength="4000" placeholder="Describe your people challenge, timeline, size of team, and any immediate priorities."></textarea></div>
    <label class="check"><input type="checkbox" required> <span>I agree that Donums may contact me about this request in line with the <a href="/privacy" target="_blank">Privacy Policy</a>.</span></label>
    <button class="btn btn-primary" type="submit">Send request <?= icon('send', 16) ?></button>
  </form>
  <aside class="sticky">
    <div class="card premium-side-card">
      <h3>What happens next</h3>
      <ul class="list-clean list-check"><li><?= icon('check', 16) ?>We acknowledge your request immediately.</li><li><?= icon('check', 16) ?>Our team reviews the brief and clarifies scope.</li><li><?= icon('check', 16) ?>We recommend the right people, process or engagement model.</li><li><?= icon('check', 16) ?>You receive a clear recommendation before any commitment.</li></ul>
    </div>
    <div class="card mt2 premium-side-card">
      <h3>Prefer to talk?</h3>
      <p class="muted small">Speak directly with the Donums HR team for a quick assessment.</p>
      <p><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone1'))) ?>"><?= e(cfg('phone1')) ?></a></p>
      <p><a href="https://wa.me/<?= e(cfg('whatsapp')) ?>?text=<?= rawurlencode('Hello Donums HR Global, I would like to discuss a people or hiring need.') ?>" target="_blank" rel="noopener">WhatsApp us</a></p>
    </div>
  </aside>
</div></section>
