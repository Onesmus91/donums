<section class="page-hero"><div class="wrap"><span class="eyebrow">Contact</span><h1>Talk to the Donums team</h1><p>For recruitment, HR strategy, payroll, compliance, training or operational support, we are ready to help.</p></div></section>
<section class="section"><div class="wrap layout-2">
  <form class="card form premium-form" method="post" action="/contact">
    <?= csrf_field() . spam_fields() ?>
    <div class="row2"><div class="field"><label for="name">Your name</label><input id="name" name="name" required maxlength="120"></div><div class="field"><label for="contact">Phone or email</label><input id="contact" name="contact" required maxlength="160"></div></div>
    <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="160" placeholder="e.g. Executive hiring support, HR outsourcing, compliance review"></div>
    <div class="field"><label for="details">Message</label><textarea id="details" name="details" rows="6" required placeholder="Tell us briefly what you need and when you need it."></textarea></div>
    <button class="btn btn-primary" type="submit">Send message</button>
  </form>
  <aside>
    <div class="card premium-side-card"><h3>Reach us directly</h3>
      <dl class="kv"><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone1'))) ?>"><?= e(cfg('phone1')) ?></a><br><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone2'))) ?>"><?= e(cfg('phone2')) ?></a></dd><dt>Email</dt><dd><a href="mailto:<?= e(cfg('email')) ?>"><?= e(cfg('email')) ?></a></dd><dt>Location</dt><dd><?= e(cfg('location')) ?></dd></dl>
    </div>
    <div class="card mt2 premium-side-card"><h3>Hours</h3><p class="muted small">Monday to Friday<br>8:00 AM – 5:00 PM (EAT)</p></div>
  </aside>
</div></section>
