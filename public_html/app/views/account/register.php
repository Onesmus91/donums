<div class="wrap"><div class="form-card">
  <h1>Create your Donums account</h1><p class="sub">One account for courses, job applications, the community and live sessions. It takes less than a minute.</p>
  <form class="form" method="post" action="/account/register"><?= csrf_field() . spam_fields() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="name">Full name</label><input id="name" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="row2"><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" required autocomplete="email"></div>
    <div class="field"><label for="phone">Phone <span class="muted">(optional)</span></label><input id="phone" type="tel" name="phone" autocomplete="tel"></div></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="hint">At least 10 characters, with letters and a number.</span></div>
    <label class="check"><input type="checkbox" name="agree" value="1" required><span>I agree to the <a href="/terms" target="_blank">Terms</a> and <a href="/privacy" target="_blank">Privacy Policy</a>.</span></label>
    <label class="check"><input type="checkbox" name="newsletter" value="1"><span>Send me occasional updates about courses, jobs and live sessions. <span class="muted">(optional)</span></span></label>
    <button class="btn btn-primary btn-block">Create account</button>
    <div class="small center">Already registered? <a href="<?= e(url('/account/login', ['next' => $next])) ?>">Sign in</a></div>
  </form>
</div></div>
