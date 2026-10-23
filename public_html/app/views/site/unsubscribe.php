<div class="wrap"><div class="form-card center">
<?php if ($done): ?><h1>You’re unsubscribed</h1><p class="sub"><?= e($r['email']) ?> will no longer receive marketing email from Donums. You will still receive essential messages about your account, requests and applications.</p><a class="btn btn-primary" href="/">Back to the site</a>
<?php else: ?><h1>Unsubscribe</h1><p class="sub">Stop marketing email to <strong><?= e($r['email']) ?></strong>?</p><form method="post" action="/unsubscribe/<?= e($r['token']) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-block">Yes, unsubscribe me</button></form><p class="mt2"><a href="/">Keep me subscribed</a></p><?php endif; ?>
</div></div>
