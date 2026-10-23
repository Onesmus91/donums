<section class="page-hero"><div class="wrap"><span class="eyebrow">Partner network</span><h1>Grow with Donums</h1><p>Join as a supplier, installer, professional or property owner and receive qualified, pre-scoped requests from customers who are ready to proceed.</p></div></section>
<section class="section"><div class="wrap layout-2">
  <form class="card form" method="post" action="/sell">
    <?= csrf_field() . spam_fields() ?>
    <div class="row2"><div class="field"><label for="name">Name or business</label><input id="name" name="name" required maxlength="120"></div><div class="field"><label for="contact">Phone or email</label><input id="contact" name="contact" required maxlength="160"></div></div>
    <div class="row2"><div class="field"><label for="kind">What do you offer?</label><select id="kind" name="kind"><option>Equipment or product supplier</option><option>Installer or contractor</option><option>Professional / consultant</option><option>Property owner or agent</option><option>Marketing or creative services</option><option>Other</option></select></div>
    <div class="field"><label for="location">Where do you operate?</label><input id="location" name="location" maxlength="160"></div></div>
    <div class="field"><label for="details">Tell us about your offer</label><textarea id="details" name="details" rows="5" placeholder="Services or products, experience, coverage area, and any certifications."></textarea></div>
    <button class="btn btn-primary" type="submit">Submit application</button>
  </form>
  <aside><div class="card"><h3>How partnership works</h3><ul class="list-clean list-check"><li><?= icon('check', 16) ?>We verify your business and credentials.</li><li><?= icon('check', 16) ?>Matching requests are sent to you with a clear scope.</li><li><?= icon('check', 16) ?>Donums manages the customer relationship and payment process.</li><li><?= icon('check', 16) ?>You focus on delivering excellent work.</li></ul></div></aside>
</div></section>
