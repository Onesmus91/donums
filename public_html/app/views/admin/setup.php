<div class="wrap"><div class="form-card"><span class="eyebrow">First-time setup</span><h1>Create the Super Admin account</h1><p class="sub">This account controls the entire platform. Use a strong password you do not use anywhere else.</p>
<form class="form" method="post" action="/admin/setup"><?= csrf_field() ?>
<div class="field"><label for="n">Full name</label><input id="n" name="name" required></div><div class="field"><label for="e">Email address</label><input id="e" type="email" name="email" required></div>
<div class="field"><label for="p">Password</label><input id="p" type="password" name="password" required minlength="10"></div><div class="field"><label for="p2">Confirm password</label><input id="p2" type="password" name="password2" required minlength="10"></div>
<button class="btn btn-primary btn-block">Create Super Admin</button></form></div></div>
