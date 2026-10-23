<section class="page-hero"><div class="wrap"><span class="eyebrow">Contact</span><h1>Talk to the Donums team</h1><p>Questions about a service, a partnership, a course or a vacancy? We will point you to the right person.</p></div></section>
<section class="section"><div class="wrap layout-2">
  <form class="card form" method="post" action="/contact">
    <?= csrf_field() . spam_fields() ?>
    <div class="row2"><div class="field"><label for="name">Your name</label><input id="name" name="name" required maxlength="120"></div><div class="field"><label for="contact">Phone or email</label><input id="contact" name="contact" required maxlength="160"></div></div>
    <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="160"></div>
    <div class="field"><label for="details">Message</label><textarea id="details" name="details" rows="6" required></textarea></div>
    <button class="btn btn-primary" type="submit">Send message</button>
  </form>
  <aside><div class="card"><h3>Reach us directly</h3>
    <dl class="kv"><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone1'))) ?>"><?= e(cfg('phone1')) ?></a><br><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone2'))) ?>"><?= e(cfg('phone2')) ?></a></dd><dt>Email</dt><dd><a href="mailto:<?= e(cfg('email')) ?>"><?= e(cfg('email')) ?></a></dd><dt>WhatsApp</dt><dd><a href="https://wa.me/<?= e(cfg('whatsapp')) ?>" target="_blank" rel="noopener">Start a chat</a></dd><dt>Office</dt><dd><?= e(cfg('location')) ?></dd></dl></div></aside>
</div></section>
