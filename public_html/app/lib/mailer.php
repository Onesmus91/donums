<?php
/* =====================================================================
 * Donums mail engine
 *   - build_mime():   multipart/alternative + attachments, RFC 2047 headers
 *   - smtp_deliver(): SMTP over SSL/STARTTLS with AUTH PLAIN/LOGIN
 *   - queue_mail():   every outgoing message goes through the outbox
 *   - process_outbox(): sends queued mail in small batches (cron / on-demand)
 *   - brand_html():   the branded Donums letterhead
 * ===================================================================== */

/* ---------- mailbox accounts ---------- */
function mailbox_get(string $address): ?array {
    $m = row('SELECT * FROM mailboxes WHERE address = ?', [strtolower($address)]);
    if ($m) { $m['password'] = secret_decrypt($m['password_enc']); }
    return $m;
}
function mailboxes_all(bool $activeOnly = true): array {
    return rows('SELECT address, display_name, active FROM mailboxes' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY pos, address');
}
function mailbox_display(string $address): string {
    return (string) (val('SELECT display_name FROM mailboxes WHERE address = ?', [strtolower($address)]) ?: cfg('site_name'));
}

/* ---------- MIME helpers ---------- */
function mime_encode_header(string $s): string {
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}
function mime_address(string $email, string $name = ''): string {
    $name = trim(str_replace(["\r", "\n", '"'], '', $name));
    return $name === '' ? '<' . $email . '>' : (preg_match('/[^\x20-\x7e]/', $name) ? mime_encode_header($name) : '"' . $name . '"') . ' <' . $email . '>';
}
function mail_valid(string $e): bool { return (bool) filter_var(trim($e), FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $e); }
function split_addresses(string $list): array {
    $out = [];
    foreach (preg_split('/[;,\n]+/', $list) as $p) {
        $p = trim($p); if ($p === '') { continue; }
        if (preg_match('/<([^>]+)>/', $p, $m)) { $p = trim($m[1]); }
        if (mail_valid($p)) { $out[strtolower($p)] = $p; }
    }
    return array_values($out);
}
function html_to_text(string $html): string {
    $t = preg_replace('~<(script|style|head|title)[^>]*>.*?</\1>~is', '', $html);
    $t = preg_replace('~<\s*br\s*/?>~i', "\n", $t);
    $t = preg_replace('~</\s*(p|div|tr|h[1-6]|li|blockquote)\s*>~i', "\n", $t);
    $t = preg_replace('~<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>~is', '$2 ($1)', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
}

/**
 * Build a complete RFC 5322 message. $m keys: from_email, from_name, to (list), cc, subject, html, text, attachments[], reply_to, in_reply_to, message_id, headers[]
 * @return array{0:string,1:string} [raw message, message-id]
 */
function build_mime(array $m): array {
    $domain = substr(strrchr($m['from_email'], '@'), 1) ?: 'donums.co.ke';
    $mid = $m['message_id'] ?? ('<' . bin2hex(random_bytes(12)) . '@' . $domain . '>');
    $h = [
        'Date: ' . date('r'),
        'From: ' . mime_address($m['from_email'], $m['from_name'] ?? ''),
        'To: ' . implode(', ', array_map(fn($t) => is_array($t) ? mime_address($t[0], $t[1] ?? '') : mime_address($t), $m['to'])),
    ];
    if (!empty($m['cc'])) { $h[] = 'Cc: ' . implode(', ', array_map('mime_address', $m['cc'])); }
    $h[] = 'Reply-To: ' . mime_address($m['reply_to'] ?? $m['from_email'], $m['from_name'] ?? '');
    $h[] = 'Subject: ' . mime_encode_header(str_replace(["\r", "\n"], ' ', $m['subject']));
    $h[] = 'Message-ID: ' . $mid;
    if (!empty($m['in_reply_to'])) { $h[] = 'In-Reply-To: ' . $m['in_reply_to']; $h[] = 'References: ' . $m['in_reply_to']; }
    foreach (($m['headers'] ?? []) as $k => $v) { $h[] = $k . ': ' . str_replace(["\r", "\n"], '', $v); }
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'X-Mailer: Donums Mail ' . DN_VERSION;

    $text = $m['text'] ?? html_to_text($m['html']);
    $alt = 'dn_alt_' . bin2hex(random_bytes(8));
    $body = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) .
            "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($m['html'])) . "--$alt--\r\n";
    if (empty($m['attachments'])) {
        $h[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
        return [implode("\r\n", $h) . "\r\n\r\n" . $body, $mid];
    }
    $mix = 'dn_mix_' . bin2hex(random_bytes(8));
    $h[] = 'Content-Type: multipart/mixed; boundary="' . $mix . '"';
    $out = implode("\r\n", $h) . "\r\n\r\n--$mix\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n" . $body;
    foreach ($m['attachments'] as $a) {
        $name = str_replace(['"', "\r", "\n"], '', $a['name']);
        $enc = preg_match('/[^\x20-\x7e]/', $name) ? "filename*=UTF-8''" . rawurlencode($name) : 'filename="' . $name . '"';
        $out .= "--$mix\r\nContent-Type: " . ($a['type'] ?: 'application/octet-stream') . '; name="' . $name . "\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; $enc\r\n\r\n" . chunk_split(base64_encode($a['data']));
    }
    return [$out . "--$mix--\r\n", $mid];
}

/* ---------- SMTP ---------- */
function smtp_expect($fp, array $codes, string &$last): bool {
    $last = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $last .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') { break; }
    }
    return in_array((int) substr($last, 0, 3), $codes, true);
}
function smtp_cmd($fp, string $cmd, array $codes, string &$last): bool { fwrite($fp, $cmd . "\r\n"); return smtp_expect($fp, $codes, $last); }

/** Opens an authenticated SMTP session. Returns [resource|null, message]. */
function smtp_open(array $a): array {
    $host = trim((string) $a['smtp_host']); $port = (int) ($a['smtp_port'] ?: 465); $sec = strtolower((string) ($a['smtp_security'] ?: 'ssl'));
    if ($host === '' || empty($a['username']) || empty($a['password'])) { return [null, 'SMTP host, username and password are required.']; }
    $ctx = stream_context_create(['ssl' => ['verify_peer' => !cfg('smtp_insecure', false), 'verify_peer_name' => !cfg('smtp_insecure', false), 'SNI_enabled' => true, 'peer_name' => $host]]);
    $target = (in_array($sec, ['ssl', 'ssl/tls', 'tls'], true) && $port !== 587 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { return [null, "Could not connect to $host:$port — $errstr ($errno)"]; }
    stream_set_timeout($fp, 20);
    $last = '';
    if (!smtp_expect($fp, [220], $last)) { fclose($fp); return [null, 'The SMTP server did not greet us: ' . trim($last)]; }
    $ehlo = 'donums.co.ke';
    if (!smtp_cmd($fp, 'EHLO ' . $ehlo, [250], $last)) { fclose($fp); return [null, 'EHLO rejected: ' . trim($last)]; }
    if ($sec === 'starttls' || ($port === 587 && $sec !== 'none')) {
        if (!smtp_cmd($fp, 'STARTTLS', [220], $last)) { fclose($fp); return [null, 'STARTTLS was refused: ' . trim($last)]; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return [null, 'Could not start a secure (TLS) session.']; }
        smtp_cmd($fp, 'EHLO ' . $ehlo, [250], $last);
    }
    $caps = strtoupper($last);
    $u = (string) $a['username']; $p = (string) $a['password'];
    if (str_contains($caps, 'AUTH') && str_contains($caps, 'PLAIN') && smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0$u\0$p"), [235, 334], $last) && (int) substr($last, 0, 3) === 235) {
        return [$fp, 'ok'];
    }
    if (!smtp_cmd($fp, 'AUTH LOGIN', [334], $last) || !smtp_cmd($fp, base64_encode($u), [334], $last) || !smtp_cmd($fp, base64_encode($p), [235], $last)) {
        fclose($fp); return [null, 'The mail server rejected the username or password.'];
    }
    return [$fp, 'ok'];
}

/** Deliver an already-built message. Returns [ok, message]. */
function smtp_send_raw(array $acct, string $envelopeFrom, array $rcpts, string $raw): array {
    [$fp, $msg] = smtp_open($acct);
    if (!$fp) { return [false, $msg]; }
    $last = '';
    if (!smtp_cmd($fp, 'MAIL FROM:<' . $envelopeFrom . '>', [250], $last)) { fclose($fp); return [false, 'Sender rejected: ' . trim($last)]; }
    $accepted = 0; $bad = [];
    foreach (array_unique($rcpts) as $r) {
        if (smtp_cmd($fp, 'RCPT TO:<' . $r . '>', [250, 251], $last)) { $accepted++; } else { $bad[] = $r . ' (' . trim(substr($last, 0, 120)) . ')'; }
    }
    if (!$accepted) { fclose($fp); return [false, 'All recipients were rejected: ' . implode('; ', $bad)]; }
    if (!smtp_cmd($fp, 'DATA', [354], $last)) { fclose($fp); return [false, 'DATA rejected: ' . trim($last)]; }
    $raw = preg_replace('/(?<!\r)\n/', "\r\n", $raw);             // normalise line endings
    $raw = preg_replace('/^\./m', '..', $raw);                    // dot-stuffing
    fwrite($fp, $raw . "\r\n.\r\n");
    $ok = smtp_expect($fp, [250], $last);
    $tmp = ''; smtp_cmd($fp, 'QUIT', [221, 250], $tmp);
    fclose($fp);
    return [$ok, $ok ? 'Accepted by the mail server.' . ($bad ? ' Some recipients were rejected: ' . implode('; ', $bad) : '') : 'Server rejected the message: ' . trim($last)];
}

function smtp_test(string $address): array {
    $a = mailbox_get($address);
    if (!$a) { return [false, 'That mailbox is not configured.']; }
    [$fp, $msg] = smtp_open($a);
    if ($fp) { $t = ''; smtp_cmd($fp, 'QUIT', [221, 250], $t); fclose($fp); return [true, 'SMTP connected and authenticated successfully.']; }
    return [false, $msg];
}

/* ---------- branded HTML ---------- */
function brand_html(string $subject, string $bodyHtml, array $o = []): string {
    $logo = abs_url('/assets/img/logo.png');
    $dept = $o['dept'] ?? '';
    $sig = $o['signature'] ?? '';
    $foot = $o['footer'] ?? '';
    $cta = '';
    if (!empty($o['cta_url'])) {
        $cta = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px 0 8px"><tr><td style="background:#00c46a;border-radius:999px"><a href="' . e($o['cta_url']) . '" style="display:inline-block;padding:13px 26px;font:700 14px Montserrat,Arial,Helvetica,sans-serif;color:#04130d;text-decoration:none">' . e($o['cta_label'] ?? 'Open') . '</a></td></tr></table>';
    }
    $pre = !empty($o['preheader']) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#fff">' . e($o['preheader']) . '</div>' : '';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($subject) . '</title><style>@font-face{font-family:Montserrat;font-weight:100 900;src:url(' . e(abs_url('/assets/fonts/montserrat-latin-wght-normal.woff2')) . ') format("woff2")}body,td,a,div{font-family:Montserrat,Arial,Helvetica,sans-serif}</style></head>'
    . '<body style="margin:0;padding:0;background:#f2f5f3;font-family:Montserrat,Arial,Helvetica,sans-serif;color:#14231b">' . $pre
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f5f3;padding:28px 12px"><tr><td align="center">'
    . '<table role="presentation" width="620" cellspacing="0" cellpadding="0" style="width:100%;max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #dde6e0">'
    . '<tr><td style="background:#ffffff;padding:22px 30px 18px;border-bottom:4px solid #00c46a"><img src="' . e($logo) . '" width="124" alt="Donums Consultants" style="display:block;border:0">'
    . ($dept ? '<div style="font:700 11px Montserrat,Arial,sans-serif;letter-spacing:2px;color:#08733d;margin-top:12px;text-transform:uppercase">' . e($dept) . '</div>' : '') . '</td></tr>'
    . '<tr><td style="padding:30px 30px 10px;font-size:15px;line-height:1.7;color:#14231b">' . $bodyHtml . $cta . '</td></tr>'
    . ($sig !== '' ? '<tr><td style="padding:6px 30px 26px;font-size:14px;line-height:1.6;color:#14231b">' . $sig . '</td></tr>' : '')
    . '<tr><td style="padding:18px 30px;background:#f7faf8;border-top:1px solid #e3ebe6;font-size:12px;line-height:1.6;color:#66756c">'
    . '<strong style="color:#14231b">Donums Consultants</strong> · ' . e(cfg('location')) . '<br>' . e(cfg('phone1')) . ' · <a href="' . e(abs_url('/')) . '" style="color:#08733d">' . e(cfg('domain')) . '</a>'
    . ($foot ? '<div style="margin-top:10px">' . $foot . '</div>' : '') . '</td></tr>'
    . '</table></td></tr></table></body></html>';
}

/* ---------- queue ---------- */
/**
 * Queue a message. $o: from (mailbox address), to_name, cc, bcc, text, attachments [[path,name,type]], kind, campaign_id, recipient_id, related, send_after, in_reply_to
 */
function queue_mail(string $to, string $subject, string $html, array $o = []): int {
    $from = strtolower($o['from'] ?? cfg('noreply_mailbox', cfg('email')));
    return insert('outbox', [
        'mailbox' => $from, 'to_email' => trim($to), 'to_name' => $o['to_name'] ?? '', 'cc' => $o['cc'] ?? '', 'bcc' => $o['bcc'] ?? '',
        'subject' => $subject, 'html' => $html, 'text_body' => $o['text'] ?? null, 'attachments' => !empty($o['attachments']) ? json_encode($o['attachments']) : null,
        'reply_message_id' => $o['in_reply_to'] ?? null, 'status' => 'queued', 'send_after' => $o['send_after'] ?? now(),
        'kind' => $o['kind'] ?? 'transactional', 'campaign_id' => $o['campaign_id'] ?? null, 'recipient_id' => $o['recipient_id'] ?? null,
        'related' => $o['related'] ?? null, 'created_by' => $_SESSION['uid'] ?? null, 'created_at' => now(),
    ]);
}

/** Transactional mail from an editable template. {{tokens}} are replaced and HTML-escaped. */
function queue_template(string $key, string $to, array $vars = [], array $o = []): int {
    $t = row('SELECT * FROM mail_templates WHERE k = ?', [$key]);
    if (!$t) { app_log('mail', "Missing template $key"); return 0; }
    $vars += ['site' => cfg('site_name'), 'phone' => cfg('phone1'), 'email' => cfg('email')];
    $subject = template_fill($t['subject'], $vars, false);
    $body = md(template_fill($t['body'], $vars, false));
    $html = brand_html($subject, $body, ['dept' => $o['dept'] ?? '', 'cta_url' => $vars['cta_url'] ?? '', 'cta_label' => $vars['cta_label'] ?? '', 'preheader' => $vars['preheader'] ?? '']);
    return queue_mail($to, $subject, $html, ['from' => $o['from'] ?? ($t['mailbox'] ?: cfg('noreply_mailbox')), 'to_name' => $vars['name'] ?? '', 'kind' => 'transactional', 'related' => $o['related'] ?? $key] + $o);
}
function template_fill(string $s, array $vars, bool $escape = true): string {
    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn($m) => $escape ? e($vars[$m[1]] ?? '') : (string) ($vars[$m[1]] ?? ''), $s);
}

/** Deliver due messages. Returns number sent. Safe to call from cron or opportunistically. */
function process_outbox(int $limit = 20): int {
    $lock = DATA_DIR . '/cache/outbox.lock';
    $fp = @fopen($lock, 'c');
    if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) { return 0; }
    $sent = 0;
    try {
        $due = rows("SELECT * FROM outbox WHERE status = 'queued' AND send_after <= ? AND attempts < 4 ORDER BY id LIMIT " . (int) $limit, [now()]);
        foreach ($due as $m) {
            q("UPDATE outbox SET status='sending', attempts = attempts + 1 WHERE id = ?", [$m['id']]);
            [$ok, $msg, $mid] = deliver_outbox_row($m);
            if ($ok) {
                q("UPDATE outbox SET status='sent', sent_at=?, error=NULL, message_id=? WHERE id=?", [now(), $mid, $m['id']]);
                if ($m['campaign_id'] && $m['recipient_id']) { q("UPDATE campaign_recipients SET status='sent', sent_at=? WHERE id=?", [now(), $m['recipient_id']]); }
                if ($m['kind'] === 'manual' || $m['kind'] === 'reply') { record_sent_message($m, $mid); }
                foreach (split_addresses((string) $m['to_email']) as $ta) { q('UPDATE contacts SET last_emailed_at = ? WHERE email = ?', [now(), $ta]); }
                $sent++;
            } else {
                $final = ((int) $m['attempts'] + 1) >= 4;
                q("UPDATE outbox SET status=?, error=?, send_after=? WHERE id=?", [$final ? 'failed' : 'queued', mb_substr($msg, 0, 400), date('Y-m-d H:i:s', time() + 300 * ((int) $m['attempts'] + 1)), $m['id']]);
                if ($final && $m['campaign_id'] && $m['recipient_id']) { q("UPDATE campaign_recipients SET status='failed', error=? WHERE id=?", [mb_substr($msg, 0, 200), $m['recipient_id']]); }
                app_log('mail', 'Send failed #' . $m['id'] . ' to ' . $m['to_email'] . ': ' . $msg);
            }
        }
    } finally { flock($fp, LOCK_UN); fclose($fp); }
    refresh_campaign_counts();
    return $sent;
}

function deliver_outbox_row(array $m): array {
    $acct = mailbox_get($m['mailbox']);
    $name = mailbox_display($m['mailbox']);
    $atts = [];
    foreach (json_dec($m['attachments']) as $a) {
        $full = DATA_DIR . '/private/' . ltrim($a['path'], '/');
        if (is_file($full)) { $atts[] = ['name' => $a['name'], 'type' => $a['type'] ?? '', 'data' => file_get_contents($full)]; }
    }
    $hdr = [];
    if ($m['kind'] === 'campaign' && $m['recipient_id']) {
        $tok = val('SELECT token FROM campaign_recipients WHERE id = ?', [$m['recipient_id']]);
        $hdr['List-Unsubscribe'] = '<' . abs_url('/unsubscribe/' . $tok) . '>, <mailto:' . $m['mailbox'] . '?subject=unsubscribe>';
        $hdr['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        $hdr['Precedence'] = 'bulk';
    }
    $toAddrs = split_addresses((string) $m['to_email']) ?: [(string) $m['to_email']];
    [$raw, $mid] = build_mime([
        'from_email' => $m['mailbox'], 'from_name' => $name, 'to' => array_map(fn($a) => [$a, count($toAddrs) === 1 ? (string) $m['to_name'] : ''], $toAddrs),
        'cc' => split_addresses((string) $m['cc']), 'subject' => $m['subject'], 'html' => $m['html'], 'text' => $m['text_body'] ?: null,
        'attachments' => $atts, 'in_reply_to' => $m['reply_message_id'], 'headers' => $hdr,
    ]);
    $rcpts = array_merge($toAddrs, split_addresses((string) $m['cc']), split_addresses((string) $m['bcc']));
    if ($acct && !empty($acct['password'])) {
        [$ok, $msg] = smtp_send_raw($acct, $m['mailbox'], $rcpts, $raw);
        return [$ok, $msg, $mid];
    }
    // No SMTP account configured for this mailbox: fall back to the server's mail() transport.
    $parts = explode("\r\n\r\n", $raw, 2);
    $headers = preg_replace('/^(To|Subject): .*\r\n/mi', '', $parts[0] . "\r\n");
    $ok = @mail($m['to_email'], mime_encode_header($m['subject']), $parts[1] ?? '', trim($headers));
    return [$ok, $ok ? 'Handed to server mail().' : 'The server mail() transport did not accept the message. Configure SMTP for ' . $m['mailbox'] . ' in Mail → Settings.', $mid];
}

function record_sent_message(array $m, string $mid): void {
    insert('mail_messages', [
        'mailbox' => $m['mailbox'], 'folder' => 'sent', 'message_id' => $mid, 'in_reply_to' => $m['reply_message_id'],
        'thread_key' => thread_key($m['subject'], $m['reply_message_id'] ?? ''),
        'from_name' => mailbox_display($m['mailbox']), 'from_email' => $m['mailbox'],
        'to_json' => json_encode(array_map(fn($a) => [$a, ''], split_addresses((string) $m['to_email']) ?: [(string) $m['to_email']])), 'cc_json' => json_encode(split_addresses((string) $m['cc'])),
        'subject' => $m['subject'], 'snippet' => excerpt(html_to_text($m['html']), 140), 'body_text' => html_to_text($m['html']), 'body_html' => $m['html'],
        'has_att' => $m['attachments'] ? 1 : 0, 'is_read' => 1, 'status' => 'Sent', 'msg_date' => now(), 'created_at' => now(),
    ]);
}
function thread_key(string $subject, string $inReplyTo = ''): string {
    $s = mb_strtolower(trim(preg_replace('/^((re|fwd?|aw)\s*:\s*)+/i', '', trim($subject))));
    return substr(sha1($s), 0, 16);
}
function refresh_campaign_counts(): void {
    foreach (rows("SELECT id FROM campaigns WHERE status IN ('sending','scheduled')") as $c) {
        $st = row("SELECT SUM(status IN ('sent')) s, SUM(status='failed') f, SUM(status='queued') qd, COUNT(*) t FROM campaign_recipients WHERE campaign_id = ?", [$c['id']]);
        q('UPDATE campaigns SET sent = ?, failed = ?, total = ? WHERE id = ?', [(int) $st['s'], (int) $st['f'], (int) $st['t'], $c['id']]);
        if ((int) $st['t'] > 0 && (int) $st['qd'] === 0) { q("UPDATE campaigns SET status='sent', sent_at=? WHERE id=? AND status='sending'", [now(), $c['id']]); }
    }
}

/* ---------- default transactional templates ---------- */
function mail_template_defaults(): array {
    return [
        'welcome' => ['Welcome to Donums, {{name}}', "Hello {{name}},\n\nYour Donums account is ready. Please confirm your email address so that we can protect your account and let you take part in the community.\n\nYou can use one account for **Donums Academy** (courses and certificates), **Donums Careers** (applications), the **Community** forum and **Donums Live** webinars.\n\nIf you did not create this account, you can safely ignore this message.\n\nKind regards,\nThe Donums team"],
        'verify_email' => ['Confirm your email address', "Hello {{name}},\n\nPlease confirm your email address to finish setting up your Donums account. The link below is valid for 48 hours.\n\nIf you did not request this, no action is needed."],
        'password_reset' => ['Reset your Donums password', "Hello {{name}},\n\nWe received a request to reset the password on your Donums account. Use the button below to choose a new password. The link expires in 60 minutes and can be used once.\n\nIf you did not ask for this, you can ignore this email — your password will not change."],
        'enrol_active' => ['You are enrolled: {{course}}', "Hello {{name}},\n\nYou now have full access to **{{course}}**. Work through the lessons at your own pace; your progress is saved automatically, and you receive a verifiable certificate when you complete the course.\n\nWe are glad to have you learning with us."],
        'enrol_pending' => ['We have your payment details for {{course}}', "Hello {{name}},\n\nThank you for enrolling in **{{course}}**. We have received your payment details (reference {{payment_ref}}). Our finance team verifies payments manually, usually within one business day, and you will receive another email as soon as your access is active.\n\nIf you need help, reply to this email or call {{phone}}."],
        'payment_verified' => ['Payment confirmed — {{item}}', "Hello {{name}},\n\nYour payment of **{{amount}}** for **{{item}}** has been verified (reference {{payment_ref}}). Thank you.\n\n{{next_step}}"],
        'payment_rejected' => ['We could not verify your payment — {{item}}', "Hello {{name}},\n\nWe were unable to verify the payment you submitted for **{{item}}** (reference {{payment_ref}}).\n\n{{reason}}\n\nPlease reply to this email with the correct M-Pesa confirmation message or call {{phone}} and we will resolve it quickly."],
        'certificate' => ['Your certificate: {{course}}', "Congratulations {{name}}!\n\nYou have completed **{{course}}**. Your certificate is ready and can be verified by anyone using the code **{{code}}**.\n\nWell done — and thank you for learning with Donums."],
        'application_received' => ['Application received — {{job}}', "Hello {{name}},\n\nThank you for applying for **{{job}}** at {{organisation}}. We have received your application (reference **{{ref}}**).\n\nOur recruitment team reviews every application. You can check where you are in the process at any time with your reference and email address. We will contact you directly if we would like to take your application forward.\n\nKind regards,\nDonums Recruitment"],
        'application_stage' => ['Update on your application — {{job}}', "Hello {{name}},\n\nThere is an update on your application for **{{job}}** (reference {{ref}}).\n\nCurrent stage: **{{stage}}**\n\n{{message}}\n\nKind regards,\nDonums Recruitment"],
        'application_rejected' => ['Your application for {{job}}', "Hello {{name}},\n\nThank you for the time you invested in applying for **{{job}}**. After careful consideration we will not be taking your application forward on this occasion.\n\n{{message}}\n\nWe keep strong profiles in our talent network and will contact you if a suitable opportunity opens. We wish you success in your search.\n\nKind regards,\nDonums Recruitment"],
        'interview_invite' => ['Interview invitation — {{job}}', "Hello {{name}},\n\nWe would like to invite you to an interview for **{{job}}**.\n\n**When:** {{when}}\n**Format:** {{mode}}\n**Where / link:** {{where}}\n\n{{message}}\n\nPlease reply to confirm that you can attend. If the time does not suit you, tell us and we will propose another."],
        'job_received' => ['Vacancy received — {{job}}', "Hello,\n\nWe have received your vacancy for **{{job}}** (reference {{ref}}). Our team will review it and publish it once approved, usually within one business day.\n\nIf you would like Donums to handle sourcing, screening and shortlisting for this role, reply to this email and we will share our recruitment terms."],
        'job_published' => ['Your vacancy is live — {{job}}', "Hello,\n\nYour vacancy **{{job}}** has been approved and is now published on Donums Careers. Candidates can apply immediately and we will keep you updated as applications arrive."],
        'webinar_registered' => ['You are registered: {{webinar}}', "Hello {{name}},\n\nYou are registered for **{{webinar}}**.\n\n**When:** {{when}}\n\nUse your personal link below to join. It works on any modern browser — there is nothing to install. Please do not share the link; it is unique to you.\n\nA calendar file is attached to your confirmation page: {{calendar_url}}"],
        'webinar_pending' => ['Registration received — {{webinar}}', "Hello {{name}},\n\nThank you for registering for **{{webinar}}** ({{when}}). Your seat is reserved while we verify your payment (reference {{payment_ref}}). You will receive your personal joining link as soon as it is confirmed."],
        'webinar_reminder_day' => ['Tomorrow: {{webinar}}', "Hello {{name}},\n\nA reminder that **{{webinar}}** starts tomorrow.\n\n**When:** {{when}}\n\nYour personal joining link is below. We recommend joining five minutes early to check your camera and microphone."],
        'webinar_reminder_hour' => ['Starting soon: {{webinar}}', "Hello {{name}},\n\n**{{webinar}}** begins in about an hour ({{when}}). Use the button below to join — the room opens a few minutes before the start time."],
        'webinar_followup' => ['Thank you for joining {{webinar}}', "Hello {{name}},\n\nThank you for attending **{{webinar}}**. {{recording_line}}\n\nWe would value your feedback — it takes less than a minute and helps us improve future sessions."],
        'forum_reply' => ['New reply in “{{thread}}”', "Hello {{name}},\n\n{{author}} replied to a discussion you follow:\n\n> {{snippet}}"],
        'staff_invite' => ['Your Donums staff access', "Hello {{name}},\n\nYou have been given **{{role}}** access to the Donums workspace. Sign in with the email address this message was sent to. If you have not set a password, use the “Forgot password” link on the sign-in page."],
    ];
}
function ensure_mail_templates(): void {
    foreach (mail_template_defaults() as $k => [$subject, $body]) {
        q('INSERT OR IGNORE INTO mail_templates(k,name,subject,body,kind,updated_at) VALUES(?,?,?,?,?,?)', [$k, ucwords(str_replace('_', ' ', $k)), $subject, $body, 'system', now()]);
    }
}
