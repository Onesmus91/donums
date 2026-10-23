<div class="wrap"><div class="form-card"><h1>Choose a new password</h1><p class="sub">Use at least 10 characters, with letters and a number.</p>
<form class="form" method="post" action="/account/reset/<?= e($token) ?>"><?= csrf_field() ?>
<div class="field"><label for="password">New password</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password" autofocus></div>
<div class="field"><label for="password2">Confirm password</label><input id="password2" type="password" name="password2" required minlength="10" autocomplete="new-password"></div>
<button class="btn btn-primary btn-block">Update password</button></form></div></div>
