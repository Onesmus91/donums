<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/webinars">Donums Live</a></div><h1><?= e($w['title']) ?></h1><p>This session is open to everyone. Enter your name to join.</p></div></section>
<section class="section"><div class="wrap"><div class="form-card"><form class="form" method="post" action="/webinars/<?= e($w['slug']) ?>/room/guest"><?= csrf_field() . spam_fields() ?>
<div class="field"><label for="n">Your name</label><input id="n" name="name" required maxlength="80" autofocus></div><div class="field"><label for="e">Email <span class="muted">(optional — for the replay and follow-up)</span></label><input id="e" type="email" name="email"></div>
<button class="btn btn-primary btn-block">Continue to the room</button></form></div></div></section>
