<div class="two"><div>
<?php foreach ($boxes as $b): ?>
<form class="panel" method="post" action="/admin/mail/settings" id="<?= md5($b['address']) ?>"><?= csrf_field() ?><input type="hidden" name="address" value="<?= e($b['address']) ?>">
  <div class="panel-h"><span><strong><?= e($b['address']) ?></strong> <?= $b['password_enc'] ? badge('Password set', 'green') : badge('No password', 'amber') ?> <?= $b['active'] ? '' : badge('Disabled') ?></span><span class="muted xs"><?= $b['last_sync_at'] ? 'Last sync ' . e(ago($b['last_sync_at'])) : 'Never synced' ?></span></div>
  <div class="panel-b form">
    <?php if ($b['last_error']): ?><div class="flash err"><?= icon('alert') ?><span><?= e($b['last_error']) ?></span></div><?php endif; ?>
    <div class="row2"><?= a_in('Sender name (shown to recipients)', 'display_name', $b['display_name']) ?><?= a_in('Login username', 'username', $b['username']) ?></div>
    <?= a_in('Password', 'password', '', ['type' => 'password', 'hint' => $b['password_enc'] ? 'Leave blank to keep the current password. Stored encrypted.' : 'Enter the mailbox password. Stored encrypted.', 'attrs' => ' autocomplete="new-password"']) ?>
    <div class="row3"><?= a_in('Incoming (IMAP) host', 'imap_host', $b['imap_host']) ?><?= a_in('Port', 'imap_port', $b['imap_port'], ['type' => 'number']) ?><?= a_sel('Security', 'imap_security', ['ssl' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => 'None'], $b['imap_security']) ?></div>
    <div class="row3"><?= a_in('Outgoing (SMTP) host', 'smtp_host', $b['smtp_host']) ?><?= a_in('Port', 'smtp_port', $b['smtp_port'], ['type' => 'number']) ?><?= a_sel('Security', 'smtp_security', ['ssl' => 'SSL/TLS (465)', 'starttls' => 'STARTTLS (587)', 'none' => 'None'], $b['smtp_security']) ?></div>
    <?= a_in('Signature', 'signature', $b['signature'], ['type' => 'textarea', 'rows' => 3, 'hint' => 'Plain text with **bold** and [links](https://…). Added to messages from this mailbox.']) ?>
    <?= a_check('Mailbox is active', 'active', $b['active']) ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-primary btn-sm">Save</button><button class="btn btn-ghost btn-sm" formaction="/admin/mail/settings/test">Save, then test connection</button></div>
  </div></form>
<?php endforeach; ?>
</div><div>
  <form class="panel" method="post" action="/admin/mail/settings"><?= csrf_field() ?><input type="hidden" name="action" value="add"><div class="panel-h">Add a mailbox</div><div class="panel-b form"><?= a_in('Address', 'address', '', ['type' => 'email', 'required' => true]) ?><?= a_in('Display name', 'display_name', '') ?><?= a_in('Mail server host', 'host', 'da33.host-ww.net') ?><button class="btn btn-ghost btn-sm">Add mailbox</button></div></form>
  <div class="panel"><div class="panel-h">Deliverability</div><div class="panel-b"><p class="small muted">Check the DNS records that decide whether your email lands in the inbox or the spam folder.</p><a class="btn btn-ghost btn-sm" href="/admin/mail/settings?dns=1#dns">Run domain check</a>
    <?php if ($dns): ?><div id="dns" class="mt2"><?php foreach ($dns as [$k, $s, $m]): ?><div class="health"><span class="dotc <?= $s ?>"></span><div><strong><?= e($k) ?></strong><div class="muted xs" style="word-break:break-word"><?= e($m) ?></div></div></div><?php endforeach; ?></div><?php endif; ?></div></div>
  <div class="panel"><div class="panel-h">Sending queue</div><div class="panel-b small"><?= (int) $queue['queued'] ?> queued · <?= (int) $queue['failed'] ?> failed. Messages are retried automatically up to 4 times. Campaign mail is released gradually in batches by the scheduled task.<br><br>Mail received on the server is fetched every 5 minutes by the scheduled task, and when you open a mailbox.</div></div>
</div></div>
