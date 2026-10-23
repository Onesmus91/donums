<section class="page-hero"><div class="wrap"><span class="eyebrow">Quotation request</span><h1>Tell us what you need</h1><p>A few details are enough. We review every request personally and respond within one business day with a clear scope and price.</p></div></section>
<section class="section"><div class="wrap layout-2">
  <form class="card form" method="post" action="/request">
    <?= csrf_field() . spam_fields() ?>
    <div class="row2">
      <div class="field"><label for="service">Service area</label>
        <select id="service" name="service" required><option value="">Select a service</option><?php foreach ($all as $k => $s): ?><option value="<?= e($k) ?>"><?= e($s['title']) ?></option><?php endforeach; ?><option value="general">Something else</option></select></div>
      <div class="field"><label for="need">What do you need?</label><input id="need" name="need" list="needs" maxlength="160" placeholder="e.g. Business website, home solar system" value="<?= e(qget('need')) ?>"><datalist id="needs"><?php foreach ($all as $s) { foreach ($s['items'] as $it) { echo '<option value="' . e($it[0]) . '">'; } } ?></datalist></div>
    </div>
    <div class="row2">
      <div class="field"><label for="name">Your name</label><input id="name" name="name" required maxlength="120" autocomplete="name"></div>
      <div class="field"><label for="contact">Phone or email</label><input id="contact" name="contact" required maxlength="160" autocomplete="email" placeholder="So that we can reach you"></div>
    </div>
    <div class="row2">
      <div class="field"><label for="organisation">Organisation <span class="muted">(optional)</span></label><input id="organisation" name="organisation" maxlength="160" autocomplete="organization"></div>
      <div class="field"><label for="location">Location</label><input id="location" name="location" maxlength="160" placeholder="Town / county"></div>
    </div>
    <div class="field"><label for="details">Details</label><textarea id="details" name="details" rows="5" maxlength="4000" placeholder="Describe the requirement, timeline and budget if you have one."></textarea><span class="hint">Please do not include passwords or payment card details.</span></div>
    <label class="check"><input type="checkbox" required> <span>I agree that Donums may contact me about this request, as described in the <a href="/privacy" target="_blank">Privacy Policy</a>.</span></label>
    <button class="btn btn-primary" type="submit">Send request <?= icon('send', 16) ?></button>
  </form>
  <aside class="sticky">
    <div class="card"><h3>What happens next</h3>
      <ul class="list-clean list-check"><li><?= icon('check', 16) ?>We acknowledge your request immediately.</li><li><?= icon('check', 16) ?>A consultant reviews it and contacts you.</li><li><?= icon('check', 16) ?>You receive a written quotation.</li><li><?= icon('check', 16) ?>Work starts only after you approve.</li></ul></div>
    <div class="card mt2"><h3>Prefer to talk?</h3><p class="muted small">Call <a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone1'))) ?>"><?= e(cfg('phone1')) ?></a> or <a href="https://wa.me/<?= e(cfg('whatsapp')) ?>" target="_blank" rel="noopener">message us on WhatsApp</a>.</p></div>
  </aside>
</div></section>
