<div class="wrap"><div class="form-card">
<?php if ($step === '2fa'): ?>
  <h1>Two-step verification</h1><p class="sub">Enter the 6-digit code from your authenticator app.</p>
  <form class="form" method="post" action="/account/login"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="code">Verification code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus style="font-size:1.4rem;letter-spacing:.3em;text-align:center"></div>
    <button class="btn btn-primary btn-block">Verify and continue</button></form>
<?php else: ?>
  <h1>Welcome back</h1><p class="sub">Sign in to continue learning, applying and joining sessions.</p>
  <form class="form" method="post" action="/account/login"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" required autocomplete="username" autofocus></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-block">Sign in</button>
    <div class="small" style="display:flex;justify-content:space-between"><a href="/account/forgot">Forgot password?</a><a href="<?= e(url('/account/register', ['next' => $next])) ?>">Create an account</a></div>
  </form>
<?php endif; ?>
</div></div>
