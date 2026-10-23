<?php
/* =====================================================================
 * Admin → Mail workspace: shared mailboxes, reader, composer, outbox,
 * templates, contacts, campaigns, mailbox settings and deliverability.
 * ===================================================================== */

function admin_routes_mail(): void {
    route('GET', '/admin/mail', 'admin_mail');
    route('GET', '/admin/mail/compose', 'admin_mail_compose');
    route('POST', '/admin/mail/send', 'admin_mail_send');
    route('POST', '/admin/mail/action', 'admin_mail_action');
    route('POST', '/admin/mail/sync', 'admin_mail_sync', ['csrf' => true]);
    route('GET', '/admin/mail/settings', 'admin_mail_settings');
    route('POST', '/admin/mail/settings', 'admin_mail_settings_save');
    route('POST', '/admin/mail/settings/test', 'admin_mail_test');
    route('GET', '/admin/mail/{id:\d+}', 'admin_mail');
    route('POST', '/admin/mail/{id:\d+}/request', 'admin_mail_to_request');
    route('POST', '/admin/mail/outbox/{id:\d+}', 'admin_outbox_action');
    route('GET', '/admin/templates', 'admin_templates');
    route('GET', '/admin/templates/{id:\d+}', 'admin_template_edit');
    route('POST', '/admin/templates/{id:\d+}', 'admin_template_save');
    route('POST', '/admin/templates/new', 'admin_template_new');
    route('GET', '/admin/contacts', 'admin_contacts');
    route('POST', '/admin/contacts', 'admin_contacts_action');
    route('GET', '/admin/campaigns', 'admin_campaigns');
    route('GET', '/admin/campaigns/new', 'admin_campaign_form');
    route('GET', '/admin/campaigns/{id:\d+}', 'admin_campaign_form');
    route('POST', '/admin/campaigns/save', 'admin_campaign_save');
    route('POST', '/admin/campaigns/{id:\d+}/launch', 'admin_campaign_launch');
    route('POST', '/admin/campaigns/{id:\d+}/pause', 'admin_campaign_pause');
    route('POST', '/admin/campaigns/{id:\d+}/test', 'admin_campaign_test');
    route('GET', '/admin/contacts/suggest', 'admin_contacts_suggest');
    route('POST', '/subscribe', 'post_subscribe');
}

/* ---------- helpers ---------- */
function mail_guard(): array { return admin_guard('mail.use'); }

/** Allow-list HTML sanitiser for received and composed mail. */
function sanitize_email_html(string $html, bool $allowRemoteImages = false, array $cidMap = []): string {
    if (trim($html) === '') { return ''; }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="dn-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $allowed = array_flip(['a', 'b', 'strong', 'i', 'em', 'u', 's', 'p', 'br', 'hr', 'div', 'span', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'img', 'font', 'center', 'small', 'sub', 'sup', 'caption', 'col', 'colgroup']);
    $walk = function (DOMNode $n) use (&$walk, $allowed, $allowRemoteImages, $cidMap) {
        foreach (iterator_to_array($n->childNodes) as $c) {
            if ($c->nodeType === XML_COMMENT_NODE) { $n->removeChild($c); continue; }
            if ($c->nodeType !== XML_ELEMENT_NODE) { continue; }
            $tag = strtolower($c->nodeName);
            if (!isset($allowed[$tag])) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'head', 'title'], true)) { $n->removeChild($c); continue; }
                $walk($c); while ($c->firstChild) { $n->insertBefore($c->firstChild, $c); } $n->removeChild($c); continue;
            }
            foreach (iterator_to_array($c->attributes) as $a) {
                $name = strtolower($a->nodeName); $v = trim($a->nodeValue);
                $keep = in_array($name, ['href', 'src', 'alt', 'title', 'width', 'height', 'align', 'valign', 'colspan', 'rowspan', 'cellpadding', 'cellspacing', 'border', 'bgcolor', 'color', 'style', 'target', 'dir'], true);
                if (!$keep) { $c->removeAttribute($a->nodeName); continue; }
                if ($name === 'style') {
                    $v = preg_replace('~(position|z-index|behavior|-moz-binding)\s*:[^;]*;?~i', '', $v);
                    if (preg_match('~expression\(|javascript:|url\s*\(~i', $v)) { $v = preg_replace('~url\s*\([^)]*\)~i', '', $v); }
                    $c->setAttribute('style', $v);
                }
                if ($name === 'href') {
                    if (!preg_match('~^(https?:|mailto:|tel:|#)~i', $v)) { $c->removeAttribute('href'); } else { $c->setAttribute('target', '_blank'); $c->setAttribute('rel', 'noopener noreferrer nofollow'); }
                }
                if ($name === 'src' && $tag === 'img') {
                    if (stripos($v, 'cid:') === 0) { $cid = trim(substr($v, 4), '<> '); if (isset($cidMap[$cid])) { $c->setAttribute('src', '/file/cid/' . $cidMap[$cid]); } else { $c->removeAttribute('src'); } }
                    elseif (preg_match('~^data:image/(png|jpe?g|gif|webp);base64,~i', $v)) { /* inline image: fine */ }
                    elseif (preg_match('~^https?://~i', $v)) { if ($allowRemoteImages) { $c->setAttribute('referrerpolicy', 'no-referrer'); } else { $c->setAttribute('data-blocked', $v); $c->removeAttribute('src'); $c->setAttribute('alt', $c->getAttribute('alt') ?: '[image hidden]'); } }
                    else { $c->removeAttribute('src'); }
                }
            }
            $walk($c);
        }
    };
    $root = $dom->getElementById('dn-root') ?: $dom->documentElement;
    $walk($root);
    $out = ''; foreach ($root->childNodes as $c) { $out .= $dom->saveHTML($c); }
    return $out;
}
function mail_has_blocked_images(string $sanitized): bool { return str_contains($sanitized, 'data-blocked='); }

function mail_folders(): array {
    return ['inbox' => ['Inbox', 'inbox'], 'starred' => ['Starred', 'star'], 'sent' => ['Sent', 'send'], 'drafts' => ['Drafts', 'edit'], 'scheduled' => ['Scheduled', 'clock'], 'outbox' => ['Outbox', 'upload'], 'archive' => ['Archive', 'archive'], 'trash' => ['Trash', 'trash']];
}
function mail_counts(string $mailbox): array {
    $c = [];
    $c['inbox'] = (int) val("SELECT COUNT(*) FROM mail_messages WHERE mailbox = ? AND folder = 'inbox' AND is_read = 0", [$mailbox]);
    $c['drafts'] = (int) val('SELECT COUNT(*) FROM mail_drafts WHERE mailbox = ?', [$mailbox]);
    $c['scheduled'] = (int) val("SELECT COUNT(*) FROM outbox WHERE mailbox = ? AND status = 'queued' AND send_after > ? AND kind IN ('manual','reply')", [$mailbox, now()]);
    $c['outbox'] = (int) val("SELECT COUNT(*) FROM outbox WHERE mailbox = ? AND status IN ('queued','failed') AND (send_after <= ? OR status = 'failed')", [$mailbox, now()]);
    return $c;
}
function ensure_default_mailbox(): string {
    $m = qget('mailbox');
    if ($m !== '' && val('SELECT 1 FROM mailboxes WHERE address = ?', [$m])) { return $m; }
    return (string) (val("SELECT address FROM mailboxes WHERE active = 1 ORDER BY (SELECT COUNT(*) FROM mail_messages WHERE mailbox = mailboxes.address AND is_read = 0) DESC, pos LIMIT 1") ?: cfg('email'));
}

/* ---------- workspace ---------- */
function admin_mail(array $p = []): void {
    mail_guard();
    $mailbox = ensure_default_mailbox(); $folder = qget('folder', 'inbox'); $s = qget('q'); $page = max(1, qint('page', 1));
    $boxes = rows('SELECT address, display_name, last_sync_at, last_error, (password_enc IS NOT NULL AND password_enc != \'\') has_pw FROM mailboxes WHERE active = 1 ORDER BY pos, address');
    foreach ($boxes as &$b) { $b['unread'] = (int) val("SELECT COUNT(*) FROM mail_messages WHERE mailbox = ? AND folder = 'inbox' AND is_read = 0", [$b['address']]); } unset($b);
    $msg = null; if (!empty($p['id'])) {
        $msg = row('SELECT * FROM mail_messages WHERE id = ?', [(int) $p['id']]); if (!$msg) { not_found(); }
        $mailbox = $msg['mailbox'];
        if (!$msg['is_read']) { q('UPDATE mail_messages SET is_read = 1 WHERE id = ?', [$msg['id']]); $msg['is_read'] = 1; }
    }
    $items = []; $pg = paginate(0, 30, 1); $drafts = []; $queue = [];
    if ($folder === 'drafts') { $drafts = rows('SELECT * FROM mail_drafts WHERE mailbox = ? ORDER BY updated_at DESC', [$mailbox]); }
    elseif (in_array($folder, ['outbox', 'scheduled'], true)) {
        $queue = rows($folder === 'scheduled' ? "SELECT * FROM outbox WHERE mailbox = ? AND status = 'queued' AND send_after > ? AND kind IN ('manual','reply') ORDER BY send_after" : "SELECT * FROM outbox WHERE mailbox = ? AND status IN ('queued','failed') AND (send_after <= ? OR status='failed') ORDER BY id DESC LIMIT 100", [$mailbox, now()]);
    } else {
        $w = ['mailbox = ?']; $par = [$mailbox];
        if ($folder === 'starred') { $w[] = 'is_starred = 1'; $w[] = "folder != 'trash'"; } else { $w[] = 'folder = ?'; $par[] = $folder; }
        if ($s !== '') { $w[] = '(subject LIKE ? OR from_email LIKE ? OR from_name LIKE ? OR body_text LIKE ?)'; array_push($par, "%$s%", "%$s%", "%$s%", "%$s%"); }
        if (qget('unread')) { $w[] = 'is_read = 0'; }
        $pg = paginate((int) val('SELECT COUNT(*) FROM mail_messages WHERE ' . implode(' AND ', $w), $par), 30, $page);
        $items = rows('SELECT id, folder, from_name, from_email, to_json, subject, snippet, is_read, is_starred, has_att, msg_date, thread_key FROM mail_messages WHERE ' . implode(' AND ', $w) . " ORDER BY msg_date DESC, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $par);
    }
    $thread = []; $atts = []; $bodyHtml = ''; $blocked = false; $req = null;
    if ($msg) {
        $atts = rows('SELECT * FROM mail_attachments WHERE message_id = ?', [$msg['id']]);
        $cid = []; foreach ($atts as $a) { if ($a['content_id']) { $cid[$a['content_id']] = $a['id']; } }
        $raw = $msg['body_html'] !== '' && $msg['body_html'] !== null ? $msg['body_html'] : '<pre style="white-space:pre-wrap;font:inherit">' . e($msg['body_text']) . '</pre>';
        $bodyHtml = sanitize_email_html($raw, !empty($_GET['images']), $cid); $blocked = mail_has_blocked_images($bodyHtml);
        $thread = rows('SELECT id, folder, from_name, from_email, subject, msg_date FROM mail_messages WHERE mailbox = ? AND thread_key = ? AND id != ? ORDER BY msg_date DESC LIMIT 8', [$mailbox, $msg['thread_key'], $msg['id']]);
        if ($msg['request_ref']) { $req = row('SELECT ref, name FROM requests WHERE ref = ?', [$msg['request_ref']]); }
    }
    admin_render('admin/mail', compact('boxes', 'mailbox', 'folder', 'items', 'pg', 'msg', 'atts', 'bodyHtml', 'blocked', 'thread', 'req', 'drafts', 'queue', 's') + ['counts' => mail_counts($mailbox), 'title' => 'Mail', 'section' => 'Mail', 'body_class' => 'mail-page']);
}

function admin_mail_action(): void {
    mail_guard(); $ids = array_map('intval', (array) ($_POST['ids'] ?? [])); $act = post('action'); $back = $_POST['back'] ?? '/admin/mail';
    $back = ($back !== '' && $back[0] === '/' && !str_starts_with($back, '//')) ? $back : '/admin/mail';
    if ($ids) {
        $in = in_clause($ids);
        $map = ['read' => 'is_read = 1', 'unread' => 'is_read = 0', 'star' => 'is_starred = 1', 'unstar' => 'is_starred = 0', 'archive' => "folder = 'archive'", 'trash' => "folder = 'trash'", 'inbox' => "folder = 'inbox'"];
        if (isset($map[$act])) { q("UPDATE mail_messages SET {$map[$act]} WHERE id IN ($in)", $ids); }
        elseif ($act === 'delete') { foreach (rows("SELECT path FROM mail_attachments WHERE message_id IN ($in)", $ids) as $a) { @unlink(DATA_DIR . '/private/' . $a['path']); } q("DELETE FROM mail_attachments WHERE message_id IN ($in)", $ids); q("DELETE FROM mail_messages WHERE id IN ($in) AND folder = 'trash'", $ids); }
    }
    if ($act === 'empty_trash') { $mb = clean('mailbox', 160); q("DELETE FROM mail_messages WHERE mailbox = ? AND folder = 'trash'", [$mb]); }
    if (wants_json()) { json_out(['ok' => true]); }
    redirect(in_array($act, ['archive', 'trash', 'delete']) && preg_match('~^/admin/mail/\d+~', $back) ? '/admin/mail' : $back);
}

function admin_mail_sync(): void {
    mail_guard(); $mb = clean('mailbox', 160);
    $targets = $mb !== '' ? [$mb] : array_column(rows('SELECT address FROM mailboxes WHERE active = 1'), 'address');
    $total = 0; $errs = [];
    foreach ($targets as $t) { [$n, $err] = mail_sync($t); $total += $n; if ($err) { $errs[] = $t . ': ' . $err; } }
    if (wants_json()) { json_out(['ok' => !$errs, 'added' => $total, 'errors' => $errs]); }
    flash($errs ? 'error' : 'ok', $errs ? implode(' · ', $errs) : ($total ? "$total new message(s) received." : 'You are up to date.'));
    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/mail');
}

function admin_mail_to_request(array $p): void {
    mail_guard(); $m = row('SELECT * FROM mail_messages WHERE id = ?', [(int) $p['id']]); if (!$m) { not_found(); }
    $ref = ref_code('DN');
    insert('requests', ['ref' => $ref, 'service' => 'Email enquiry', 'need' => mb_substr($m['subject'], 0, 160), 'name' => $m['from_name'] ?: $m['from_email'], 'contact' => $m['from_email'], 'details' => mb_substr($m['body_text'], 0, 3000), 'source' => 'email', 'status' => 'New', 'created_at' => now(), 'updated_at' => now()]);
    q('UPDATE mail_messages SET request_ref = ? WHERE id = ?', [$ref, $m['id']]);
    activity('request', $ref, 'note', 'Created from email in ' . $m['mailbox']);
    flash('ok', 'Request ' . $ref . ' created from this email.'); redirect('/admin/requests/' . $ref);
}

/* ---------- compose / send ---------- */
function admin_mail_compose(): void {
    mail_guard(); $mailbox = ensure_default_mailbox(); $to = qget('to'); $cc = ''; $subject = qget('subject'); $body = ''; $reply = null; $draft = null; $attachments = [];
    if (qint('draft')) { $draft = row('SELECT * FROM mail_drafts WHERE id = ?', [qint('draft')]); if ($draft) { $mailbox = $draft['mailbox']; $to = $draft['to_list']; $cc = $draft['cc_list']; $subject = $draft['subject']; $body = $draft['body']; $attachments = json_dec($draft['attachments']); } }
    $mode = qget('mode');
    if (qint('reply') && $mode) {
        $reply = row('SELECT * FROM mail_messages WHERE id = ?', [qint('reply')]);
        if ($reply) {
            $mailbox = $reply['mailbox']; $orig = sanitize_email_html($reply['body_html'] ?: nl2br(e($reply['body_text'])));
            $who = trim(($reply['from_name'] ? $reply['from_name'] . ' ' : '') . '<' . $reply['from_email'] . '>');
            $quote = '<br><br><div style="border-left:3px solid #ccd;padding-left:12px;color:#556">On ' . e(fmt_dt($reply['msg_date'])) . ', ' . e($who) . ' wrote:<br>' . $orig . '</div>';
            if ($mode === 'forward') { $subject = 'Fwd: ' . preg_replace('/^(fwd?:\s*)+/i', '', $reply['subject']); $to = ''; $body = '<br><br>---------- Forwarded message ----------<br>' . $quote; }
            else {
                $subject = 'Re: ' . preg_replace('/^(re:\s*)+/i', '', $reply['subject']); $to = $reply['folder'] === 'sent' ? implode(', ', array_column(json_dec($reply['to_json']), 0)) : $reply['from_email'];
                if ($mode === 'replyall') { $others = array_filter(array_merge(array_column(json_dec($reply['to_json']), 0), array_column(json_dec($reply['cc_json']), 0)), fn($a) => strcasecmp($a, $mailbox) !== 0 && strcasecmp($a, $reply['from_email']) !== 0); $cc = implode(', ', $others); }
                $body = $quote;
            }
        }
    }
    $boxes = rows('SELECT address, display_name, signature FROM mailboxes WHERE active = 1 ORDER BY pos, address');
    $templates = rows("SELECT id, name, subject, body FROM mail_templates WHERE kind = 'canned' ORDER BY name");
    admin_render('admin/mail_compose', compact('boxes', 'mailbox', 'to', 'cc', 'subject', 'body', 'reply', 'draft', 'templates', 'attachments') + ['mode' => $mode, 'request' => qget('request'), 'title' => 'Compose', 'section' => 'Compose']);
}

function admin_mail_send(): void {
    mail_guard();
    $from = strtolower(clean('from', 160)); $mb = mailbox_get($from); if (!$mb) { flash('error', 'Choose a valid sending mailbox.'); redirect('/admin/mail/compose'); }
    $subject = clean('subject', 250); $rawBody = (string) ($_POST['body'] ?? ''); $body = sanitize_email_html($rawBody, true);
    $toList = implode(', ', split_addresses((string) post('to'))); $cc = implode(', ', split_addresses((string) post('cc'))); $bcc = implode(', ', split_addresses((string) post('bcc')));
    // keep any already-saved attachments from a draft
    $atts = json_dec((string) post('existing_atts', '[]'));
    foreach ((array) ($_FILES['files']['name'] ?? []) as $i => $n) {
        if ($n === '' || ($_FILES['files']['error'][$i] ?? 4) === UPLOAD_ERR_NO_FILE) { continue; }
        $f = save_private_upload('files', 'mail/out', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'zip'], 10 * 1048576, $i);
        if (is_string($f)) { flash('error', $n . ': ' . $f); redirect('/admin/mail/compose'); }
        $atts[] = ['path' => $f['path'], 'name' => $f['name'], 'type' => $f['mime']];
    }
    $act = post('action', 'send');
    if ($act === 'draft') {
        $data = ['user_id' => uid(), 'mailbox' => $from, 'to_list' => $toList, 'cc_list' => $cc, 'bcc_list' => $bcc, 'subject' => $subject, 'body' => $body, 'attachments' => json_encode($atts), 'reply_to_id' => (int) post('reply_id') ?: null, 'updated_at' => now()];
        if ($id = (int) post('draft_id')) { update('mail_drafts', $id, $data); } else { $id = insert('mail_drafts', $data); }
        if (wants_json()) { json_out(['ok' => true, 'id' => $id]); }
        flash('ok', 'Draft saved.'); redirect('/admin/mail?folder=drafts&mailbox=' . rawurlencode($from));
    }
    if (!split_addresses($toList)) { flash('error', 'Add at least one valid recipient.'); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/mail/compose'); }
    if ($subject === '') { $subject = '(No subject)'; }
    $replyMsg = (int) post('reply_id') ? row('SELECT * FROM mail_messages WHERE id = ?', [(int) post('reply_id')]) : null;
    $sig = post('use_signature') ? md((string) $mb['signature']) : '';
    $html = post('letterhead', 'branded') === 'plain' ? '<div style="font:15px/1.6 Arial,Helvetica,sans-serif;color:#14231b">' . $body . ($sig ? '<br>' . $sig : '') . '</div>'
        : brand_html($subject, $body, ['dept' => mailbox_display($from), 'signature' => $sig]);
    $send = now();
    if (post('schedule_at') && ($t = strtotime((string) post('schedule_at'))) && $t > time() + 60) { $send = date('Y-m-d H:i:s', $t); }
    $oid = queue_mail($toList, $subject, $html, ['from' => $from, 'cc' => $cc, 'bcc' => $bcc, 'attachments' => $atts, 'kind' => $replyMsg ? 'reply' : 'manual', 'in_reply_to' => $replyMsg['message_id'] ?? null, 'send_after' => $send, 'related' => post('request') ?: null]);
    if ($did = (int) post('draft_id')) { q('DELETE FROM mail_drafts WHERE id = ?', [$did]); }
    if ($ref = post('request')) { activity('request', $ref, 'email', 'Email sent: ' . $subject); }
    if ($replyMsg) { q("UPDATE mail_messages SET status = 'Replied' WHERE id = ?", [$replyMsg['id']]); }
    audit('mail.send', 'outbox', $oid, ['to' => $toList, 'subject' => $subject]);
    if ($send === now() || strtotime($send) <= time()) {
        process_outbox(5); $o = row('SELECT status, error FROM outbox WHERE id = ?', [$oid]);
        if ($o['status'] === 'sent') { flash('ok', 'Message sent.'); } else { flash('warn', 'The message is queued and will be retried automatically. ' . ($o['error'] ? 'Last error: ' . $o['error'] : '')); }
    } else { flash('ok', 'Message scheduled for ' . fmt_dt($send) . '.'); }
    redirect('/admin/mail?folder=' . (strtotime($send) > time() ? 'scheduled' : 'sent') . '&mailbox=' . rawurlencode($from));
}

function admin_outbox_action(array $p): void {
    mail_guard(); $o = row('SELECT * FROM outbox WHERE id = ?', [(int) $p['id']]); if (!$o) { not_found(); }
    $act = post('action');
    if ($act === 'retry') { q("UPDATE outbox SET status = 'queued', attempts = 0, send_after = ?, error = NULL WHERE id = ?", [now(), $o['id']]); process_outbox(3); flash('info', 'Retry attempted.'); }
    if ($act === 'cancel') { q("UPDATE outbox SET status = 'cancelled' WHERE id = ? AND status IN ('queued','failed')", [$o['id']]); flash('ok', 'Cancelled.'); }
    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/mail');
}

/* ---------- mailbox settings ---------- */
function admin_mail_settings(): void {
    admin_guard('mail.use'); if (!is_super()) { admin_guard('users.manage'); }
    $boxes = rows('SELECT * FROM mailboxes ORDER BY pos, address');
    admin_render('admin/mail_settings', ['boxes' => $boxes, 'dns' => qget('dns') ? mail_dns_check((string) cfg('domain')) : null, 'queue' => ['queued' => (int) val("SELECT COUNT(*) FROM outbox WHERE status='queued'"), 'failed' => (int) val("SELECT COUNT(*) FROM outbox WHERE status='failed'")], 'title' => 'Mail settings', 'section' => 'Mail settings']);
}
function admin_mail_settings_save(): void {
    if (!is_super()) { admin_guard('users.manage'); } else { admin_guard(); }
    $act = post('action');
    if ($act === 'add') {
        $addr = strtolower(clean('address', 160)); if (!mail_valid($addr)) { flash('error', 'Enter a valid mailbox address.'); redirect('/admin/mail/settings'); }
        q('INSERT OR IGNORE INTO mailboxes(address,display_name,username,imap_host,smtp_host,pos) VALUES(?,?,?,?,?,99)', [$addr, clean('display_name', 100) ?: $addr, $addr, clean('host', 120) ?: 'da33.host-ww.net', clean('host', 120) ?: 'da33.host-ww.net']);
        flash('ok', 'Mailbox added. Set its password below.'); redirect('/admin/mail/settings');
    }
    $addr = strtolower(clean('address', 160)); $m = row('SELECT * FROM mailboxes WHERE address = ?', [$addr]); if (!$m) { not_found(); }
    $d = ['display_name' => clean('display_name', 100), 'username' => clean('username', 160) ?: $addr, 'imap_host' => clean('imap_host', 160), 'imap_port' => (int) post('imap_port', 993) ?: 993, 'imap_security' => in_array(post('imap_security'), ['ssl', 'starttls', 'none'], true) ? post('imap_security') : 'ssl',
        'smtp_host' => clean('smtp_host', 160), 'smtp_port' => (int) post('smtp_port', 465) ?: 465, 'smtp_security' => in_array(post('smtp_security'), ['ssl', 'starttls', 'none'], true) ? post('smtp_security') : 'ssl', 'signature' => text('signature', 1500), 'active' => post('active') ? 1 : 0];
    if (post('password') !== '') { $d['password_enc'] = secret_encrypt((string) post('password')); }
    update('mailboxes', $addr, $d, 'address'); audit('mailbox.update', 'mailbox', $addr);
    flash('ok', 'Saved ' . $addr . '.'); redirect('/admin/mail/settings#' . md5($addr));
}
function admin_mail_test(): void {
    admin_guard(); $addr = strtolower(clean('address', 160));
    [$ok1, $m1] = imap_test($addr); [$ok2, $m2] = smtp_test($addr);
    flash($ok1 && $ok2 ? 'ok' : 'error', $addr . ' — Incoming: ' . $m1 . ' Outgoing: ' . $m2);
    redirect('/admin/mail/settings#' . md5($addr));
}
function mail_dns_check(string $domain): array {
    $out = []; $get = function (string $host, int $type) { $r = @dns_get_record($host, $type); return is_array($r) ? $r : []; };
    $txt = fn($h) => array_map(fn($r) => $r['txt'] ?? implode('', $r['entries'] ?? []), $get($h, DNS_TXT));
    $spf = array_values(array_filter($txt($domain), fn($t) => stripos($t, 'v=spf1') === 0));
    $out[] = ['SPF record', $spf ? 'ok' : 'bad', $spf ? $spf[0] : 'No SPF record found. Add a TXT record such as: v=spf1 +a +mx include:YOUR-HOST ~all  — without it, many inboxes will treat your email as spam.'];
    $dm = array_values(array_filter($txt('_dmarc.' . $domain), fn($t) => stripos($t, 'v=DMARC1') === 0));
    $out[] = ['DMARC policy', $dm ? 'ok' : 'warn', $dm ? $dm[0] : 'No DMARC record. Add TXT _dmarc → v=DMARC1; p=none; rua=mailto:' . cfg('email') . ' (start with p=none, tighten later).'];
    $dk = null; foreach (['default', 'mail', 'x', 'selector1', 'selector2', 'google', 'dkim', 's1'] as $sel) { $r = $txt($sel . '._domainkey.' . $domain); foreach ($r as $t) { if (stripos($t, 'v=DKIM1') !== false || stripos($t, 'p=') !== false) { $dk = $sel; break 2; } } }
    $out[] = ['DKIM signature', $dk ? 'ok' : 'warn', $dk ? "Found selector “$dk”." : 'No DKIM key found on common selectors. Enable DKIM in DirectAdmin (Email Accounts → Email Authentication) so messages are cryptographically signed.'];
    $mx = $get($domain, DNS_MX); $out[] = ['MX (incoming mail)', $mx ? 'ok' : 'bad', $mx ? implode(', ', array_map(fn($r) => $r['target'], $mx)) : 'No MX records — you will not receive email.'];
    $ptr = @gethostbyname($domain); $out[] = ['Domain resolves', $ptr !== $domain ? 'ok' : 'bad', $ptr];
    return $out;
}

/* ---------- templates ---------- */
function admin_templates(): void {
    mail_guard(); $items = rows("SELECT * FROM mail_templates ORDER BY kind DESC, name");
    admin_render('admin/templates', compact('items') + ['title' => 'Templates', 'section' => 'Email templates']);
}
function admin_template_edit(array $p): void {
    mail_guard(); $t = row('SELECT * FROM mail_templates WHERE id = ?', [(int) $p['id']]); if (!$t) { not_found(); }
    $defaults = mail_template_defaults(); $vars = ['name', 'site', 'phone', 'email', 'course', 'job', 'ref', 'stage', 'message', 'webinar', 'when', 'code', 'item', 'amount', 'payment_ref', 'thread', 'author', 'snippet'];
    $sample = array_fill_keys($vars, '…'); $sample['name'] = 'Amina'; $sample['site'] = cfg('site_name'); $sample['course'] = 'Sample Course'; $sample['job'] = 'Sample Vacancy'; $sample['webinar'] = 'Sample Webinar'; $sample['when'] = 'Tue 20 Oct 2026, 3:00 PM EAT';
    $preview = brand_html(template_fill($t['subject'], $sample, false), md(template_fill($t['body'], $sample, false)), ['dept' => 'Preview', 'cta_url' => '#', 'cta_label' => 'Call to action']);
    admin_render('admin/template_edit', compact('t', 'preview') + ['has_default' => isset($defaults[$t['k']]), 'title' => 'Template', 'section' => 'Edit template']);
}
function admin_template_save(array $p): void {
    mail_guard(); $t = row('SELECT * FROM mail_templates WHERE id = ?', [(int) $p['id']]); if (!$t) { not_found(); }
    if (post('action') === 'reset' && ($d = mail_template_defaults()[$t['k']] ?? null)) { update('mail_templates', $t['id'], ['subject' => $d[0], 'body' => $d[1], 'updated_at' => now()]); flash('ok', 'Restored the default wording.'); redirect('/admin/templates/' . $t['id']); }
    if (post('action') === 'delete' && $t['kind'] === 'canned') { q('DELETE FROM mail_templates WHERE id = ?', [$t['id']]); flash('ok', 'Template deleted.'); redirect('/admin/templates'); }
    update('mail_templates', $t['id'], ['name' => clean('name', 100) ?: $t['name'], 'subject' => clean('subject', 250), 'body' => text('body', 8000), 'mailbox' => strtolower(clean('mailbox', 160)) ?: null, 'updated_at' => now()]);
    flash('ok', 'Template saved.'); redirect('/admin/templates/' . $t['id']);
}
function admin_template_new(): void {
    mail_guard(); $id = insert('mail_templates', ['k' => 'canned-' . bin2hex(random_bytes(4)), 'name' => clean('name', 100) ?: 'New reply template', 'subject' => '', 'body' => '', 'kind' => 'canned', 'updated_at' => now()]);
    redirect('/admin/templates/' . $id);
}

/* ---------- contacts ---------- */
function admin_contacts(): void {
    mail_guard(); $s = qget('q'); $f = qget('filter'); $page = max(1, qint('page', 1)); $w = ['1=1']; $par = [];
    if ($s !== '') { $w[] = '(email LIKE ? OR name LIKE ? OR organisation LIKE ? OR tags LIKE ?)'; array_push($par, "%$s%", "%$s%", "%$s%", "%$s%"); }
    if ($f === 'subscribed') { $w[] = 'subscribed = 1'; } elseif ($f === 'unsubscribed') { $w[] = 'subscribed = 0'; } elseif ($f === 'optout') { $w[] = 'unsubscribed_at IS NOT NULL'; }
    if (qget('export') === 'csv') { csv_download('contacts-' . date('Ymd') . '.csv', ['Email', 'Name', 'Phone', 'Organisation', 'Tags', 'Source', 'Subscribed'], array_map(fn($c) => [$c['email'], $c['name'], $c['phone'], $c['organisation'], $c['tags'], $c['source'], $c['subscribed']], rows('SELECT * FROM contacts WHERE ' . implode(' AND ', $w) . ' ORDER BY email', $par))); }
    $pg = paginate((int) val('SELECT COUNT(*) FROM contacts WHERE ' . implode(' AND ', $w), $par), 40, $page);
    $items = rows('SELECT * FROM contacts WHERE ' . implode(' AND ', $w) . " ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $par);
    $stats = ['total' => (int) val('SELECT COUNT(*) FROM contacts'), 'subscribed' => (int) val('SELECT COUNT(*) FROM contacts WHERE subscribed = 1')];
    admin_render('admin/contacts', compact('items', 'pg', 's', 'f', 'stats') + ['title' => 'Contacts', 'section' => 'Contacts']);
}
function admin_contacts_action(): void {
    mail_guard(); $act = post('action');
    if ($act === 'add') {
        $e = strtolower(clean('email', 160)); if (!mail_valid($e)) { flash('error', 'Enter a valid email address.'); redirect('/admin/contacts'); }
        q('INSERT INTO contacts(email,name,phone,organisation,tags,source,subscribed,created_at) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(email) DO UPDATE SET name=COALESCE(NULLIF(excluded.name,\'\'),name), tags=excluded.tags', [$e, clean('name', 120), clean('phone', 40), clean('organisation', 120), clean('tags', 200), 'Added by staff', post('subscribed') ? 1 : 0, now()]);
        flash('ok', 'Contact saved.');
    } elseif ($act === 'import') {
        if (!post('consent')) { flash('error', 'Please confirm that these contacts have agreed to receive your email.'); redirect('/admin/contacts'); }
        $n = 0; $tags = clean('tags', 200);
        if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $fp = fopen($_FILES['csv']['tmp_name'], 'r'); $first = true;
            while (($r = fgetcsv($fp)) !== false) {
                if ($first) { $first = false; if (!mail_valid(trim($r[0] ?? '')) && !mail_valid(trim($r[1] ?? ''))) { continue; } }
                $em = mail_valid(trim($r[0] ?? '')) ? trim($r[0]) : trim($r[1] ?? ''); $nm = mail_valid(trim($r[0] ?? '')) ? trim($r[1] ?? '') : trim($r[0] ?? '');
                if (!mail_valid($em)) { continue; }
                q('INSERT INTO contacts(email,name,tags,source,subscribed,created_at) VALUES(?,?,?,?,1,?) ON CONFLICT(email) DO UPDATE SET subscribed = CASE WHEN unsubscribed_at IS NULL THEN 1 ELSE 0 END', [strtolower($em), mb_substr($nm, 0, 120), $tags, 'CSV import', now()]); $n++;
            } fclose($fp);
        }
        flash('ok', "$n contact(s) imported."); audit('contacts.import', 'contacts', '', ['n' => $n]);
    } elseif ($act === 'toggle') { q('UPDATE contacts SET subscribed = 1 - subscribed, unsubscribed_at = CASE WHEN subscribed = 1 THEN ? ELSE NULL END WHERE id = ?', [now(), (int) post('id')]); }
    elseif ($act === 'delete') { q('DELETE FROM contacts WHERE id = ?', [(int) post('id')]); flash('ok', 'Contact removed.'); }
    elseif ($act === 'tags') { q('UPDATE contacts SET tags = ? WHERE id = ?', [clean('tags', 200), (int) post('id')]); }
    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/contacts');
}
function post_subscribe(): void {
    if (!spam_check(2)) { flash('error', 'Please try again.'); redirect($_SERVER['HTTP_REFERER'] ?? '/'); }
    $e = strtolower(clean('email', 160));
    if (mail_valid($e) && rate_limit('sub-' . client_ip(), 6, 3600)) { q('INSERT INTO contacts(email,name,source,subscribed,created_at) VALUES(?,?,?,1,?) ON CONFLICT(email) DO UPDATE SET subscribed = CASE WHEN unsubscribed_at IS NULL THEN 1 ELSE subscribed END', [$e, clean('name', 120), 'Website newsletter', now()]); }
    flash('ok', 'Thank you — you are subscribed to Donums updates. You can unsubscribe from any email.'); redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

/* ---------- campaigns ---------- */
function campaign_segments(): array {
    return ['all' => 'Everyone who subscribed', 'learners' => 'Subscribed learners (Academy)', 'candidates' => 'Subscribed job candidates', 'webinars' => 'Subscribed webinar registrants', 'customers' => 'Subscribed service customers', 'tag' => 'Contacts with a specific tag'];
}
function campaign_audience(string $segment, string $tag = ''): array {
    $base = 'SELECT c.id, c.email, c.name FROM contacts c WHERE c.subscribed = 1 AND c.unsubscribed_at IS NULL';
    $extra = [
        'all' => '', 'learners' => " AND c.email IN (SELECT u.email FROM users u JOIN enrolments e ON e.user_id = u.id)", 'candidates' => ' AND c.email IN (SELECT email FROM applications)',
        'webinars' => ' AND c.email IN (SELECT email FROM webinar_regs)', 'customers' => " AND c.email IN (SELECT lower(contact) FROM requests WHERE contact LIKE '%@%')",
    ];
    if ($segment === 'tag') { return rows($base . " AND (',' || replace(c.tags, ' ', '') || ',') LIKE ?", ['%,' . str_replace(' ', '', $tag) . ',%']); }
    return rows($base . ($extra[$segment] ?? ''));
}
function admin_campaigns(): void {
    mail_guard(); $items = rows('SELECT * FROM campaigns ORDER BY id DESC');
    foreach ($items as &$c) { $s = row("SELECT COALESCE(SUM(open_count>0),0) o, COALESCE(SUM(click_count>0),0) k FROM campaign_recipients WHERE campaign_id = ?", [$c['id']]); $c['opens'] = (int) $s['o']; $c['clicks'] = (int) $s['k']; } unset($c);
    admin_render('admin/campaigns', compact('items') + ['title' => 'Campaigns', 'section' => 'Campaigns']);
}
function admin_campaign_form(array $p = []): void {
    mail_guard(); $c = !empty($p['id']) ? row('SELECT * FROM campaigns WHERE id = ?', [(int) $p['id']]) : null; if (!empty($p['id']) && !$c) { not_found(); }
    $stats = null; $recips = [];
    if ($c && $c['status'] !== 'draft') { $stats = row("SELECT COUNT(*) total, SUM(status='sent') sent, SUM(status='failed') failed, SUM(status='queued') queued, SUM(open_count>0) opened, SUM(click_count>0) clicked FROM campaign_recipients WHERE campaign_id = ?", [$c['id']]); $unsub = (int) val("SELECT COUNT(*) FROM contacts WHERE unsubscribed_at >= ?", [$c['created_at']]); $stats['unsub'] = $unsub; $recips = rows('SELECT * FROM campaign_recipients WHERE campaign_id = ? ORDER BY id LIMIT 200', [$c['id']]); }
    $audience = $c ? count(campaign_audience(json_dec($c['segment'])['type'] ?? 'all', json_dec($c['segment'])['tag'] ?? '')) : (int) val('SELECT COUNT(*) FROM contacts WHERE subscribed = 1');
    admin_render('admin/campaign_form', compact('c', 'stats', 'recips', 'audience') + ['boxes' => mailboxes_all(), 'segments' => campaign_segments(), 'title' => 'Campaign', 'section' => $c ? $c['name'] : 'New campaign']);
}
function admin_campaign_save(): void {
    mail_guard(); $id = (int) post('id'); $c = $id ? row('SELECT * FROM campaigns WHERE id = ?', [$id]) : null;
    if ($c && $c['status'] !== 'draft') { flash('error', 'A campaign that has been launched can no longer be edited.'); redirect('/admin/campaigns/' . $id); }
    $d = ['name' => clean('name', 120) ?: 'Untitled campaign', 'mailbox' => strtolower(clean('mailbox', 160)), 'subject' => clean('subject', 250), 'preheader' => clean('preheader', 160), 'body' => sanitize_email_html((string) ($_POST['body'] ?? ''), true), 'segment' => json_encode(['type' => array_key_exists(post('segment'), campaign_segments()) ? post('segment') : 'all', 'tag' => clean('tag', 60)]), 'scheduled_at' => post('scheduled_at') && strtotime((string) post('scheduled_at')) ? date('Y-m-d H:i:s', strtotime((string) post('scheduled_at'))) : null];
    if ($id) { update('campaigns', $id, $d); } else { $d += ['status' => 'draft', 'created_by' => uid(), 'created_at' => now()]; $id = insert('campaigns', $d); }
    flash('ok', 'Campaign saved.'); redirect('/admin/campaigns/' . $id);
}
function campaign_render_for(array $c, array $r, bool $preview = false): string {
    $first = explode(' ', trim($r['name'] ?: 'there'))[0];
    $body = template_fill($c['body'], ['first_name' => $first, 'name' => $r['name'] ?: 'there', 'email' => $r['email']], false);
    if (!$preview) {
        $tok = $r['token'];
        $body = preg_replace_callback('~href="(https?://[^"]+)"~i', fn($m) => 'href="' . e(abs_url('/t/c/' . $tok, ['u' => sign_token(html_entity_decode($m[1]), 16)])) . '"', $body);
    }
    $unsub = $preview ? '#' : abs_url('/unsubscribe/' . $r['token']);
    $foot = 'You are receiving this because you subscribed to Donums updates. <a href="' . e($unsub) . '" style="color:#08733d">Unsubscribe</a>.';
    $html = brand_html($c['subject'], $body, ['dept' => mailbox_display($c['mailbox']), 'footer' => $foot, 'preheader' => $c['preheader']]);
    if (!$preview) { $html = str_replace('</body>', '<img src="' . e(abs_url('/t/o/' . $r['token'] . '.gif')) . '" width="1" height="1" alt="" style="display:none"></body>', $html); }
    return $html;
}
function admin_campaign_launch(array $p): void {
    mail_guard(); $c = row('SELECT * FROM campaigns WHERE id = ?', [(int) $p['id']]); if (!$c) { not_found(); }
    if ($c['status'] !== 'draft') { flash('error', 'This campaign has already been launched.'); redirect('/admin/campaigns/' . $c['id']); }
    if (trim($c['subject']) === '' || trim(strip_tags($c['body'])) === '' || !mailbox_get($c['mailbox'])) { flash('error', 'Add a subject, message and sending mailbox first.'); redirect('/admin/campaigns/' . $c['id']); }
    $seg = json_dec($c['segment']); $aud = campaign_audience($seg['type'] ?? 'all', $seg['tag'] ?? '');
    if (!$aud) { flash('error', 'No subscribed contacts match this audience.'); redirect('/admin/campaigns/' . $c['id']); }
    $when = $c['scheduled_at'] && strtotime($c['scheduled_at']) > time() ? $c['scheduled_at'] : now();
    foreach ($aud as $a) {
        $tok = bin2hex(random_bytes(12));
        q('INSERT OR IGNORE INTO campaign_recipients(campaign_id,contact_id,email,name,token,status) VALUES(?,?,?,?,?,?)', [$c['id'], $a['id'], $a['email'], $a['name'], $tok, 'queued']);
        $r = row('SELECT * FROM campaign_recipients WHERE campaign_id = ? AND email = ?', [$c['id'], $a['email']]);
        $oid = queue_mail($a['email'], $c['subject'], campaign_render_for($c, $r), ['from' => $c['mailbox'], 'to_name' => $a['name'], 'kind' => 'campaign', 'campaign_id' => $c['id'], 'recipient_id' => $r['id'], 'send_after' => $when]);
    }
    update('campaigns', $c['id'], ['status' => 'sending', 'total' => count($aud)]);
    audit('campaign.launch', 'campaign', $c['id'], ['recipients' => count($aud)]);
    flash('ok', count($aud) . ' recipients queued. Messages are released gradually (' . (int) setting('mail_batch', '25') . ' per run) to protect your sender reputation.'); redirect('/admin/campaigns/' . $c['id']);
}
function admin_campaign_pause(array $p): void {
    mail_guard(); $c = row('SELECT * FROM campaigns WHERE id = ?', [(int) $p['id']]); if (!$c) { not_found(); }
    q("UPDATE outbox SET status = 'cancelled' WHERE campaign_id = ? AND status = 'queued'", [$c['id']]); q("UPDATE campaign_recipients SET status = 'skipped' WHERE campaign_id = ? AND status = 'queued'", [$c['id']]);
    update('campaigns', $c['id'], ['status' => 'sent', 'sent_at' => now()]); flash('ok', 'Campaign stopped. Messages already sent are unaffected.'); redirect('/admin/campaigns/' . $c['id']);
}
function admin_campaign_test(array $p): void {
    mail_guard(); $c = row('SELECT * FROM campaigns WHERE id = ?', [(int) $p['id']]); if (!$c) { not_found(); } $u = user();
    queue_mail($u['email'], '[Test] ' . $c['subject'], campaign_render_for($c, ['name' => $u['name'], 'email' => $u['email'], 'token' => 'preview'], true), ['from' => $c['mailbox'] ?: cfg('noreply_mailbox'), 'kind' => 'manual']);
    process_outbox(3); flash('ok', 'A test email was sent to ' . $u['email'] . '.'); redirect('/admin/campaigns/' . $c['id']);
}

/* ---------- scheduled tasks ---------- */
function run_cron(): string {
    $log = [];
    set_setting('last_cron', now());
    if (!cfg('cron_token') && !setting('cron_key')) { set_setting('cron_key', bin2hex(random_bytes(12))); }
    $n = process_outbox(max(5, (int) setting('mail_batch', '25'))); $log[] = "mail sent: $n";
    $added = 0; foreach (rows('SELECT address FROM mailboxes WHERE active = 1 AND password_enc IS NOT NULL AND password_enc != \'\'') as $b) { if (!($b['address'])) { continue; } [$a] = mail_sync($b['address'], 25); $added += $a; }
    $log[] = "mail received: $added";
    if (function_exists('process_webinar_jobs')) { $w = process_webinar_jobs(); $log[] = 'webinar reminders: ' . json_encode($w); }
    q('DELETE FROM rate_limits WHERE reset_at < ?', [time()]); q('DELETE FROM password_resets WHERE expires_at < ?', [time() - 86400]); q('DELETE FROM email_verifications WHERE expires_at < ?', [time() - 86400]);
    // nightly database backup (keeps the 7 most recent copies)
    $today = DATA_DIR . '/backups/donums-' . date('Ymd') . '.sqlite';
    if (!is_file($today) && date('G') >= 1) { try { db()->exec("VACUUM INTO '" . str_replace("'", "''", $today) . "'"); $log[] = 'backup created'; $old = glob(DATA_DIR . '/backups/donums-*.sqlite'); sort($old); foreach (array_slice($old, 0, max(0, count($old) - 7)) as $f) { @unlink($f); } } catch (Throwable $e) { app_log('error', 'backup: ' . $e->getMessage()); } }
    return date('c') . ' ' . implode(' | ', $log);
}

function admin_contacts_suggest(): void {
    mail_guard(); $t = qget('q'); if (mb_strlen($t) < 2) { json_out(['items' => []]); }
    json_out(['items' => rows('SELECT email, name FROM contacts WHERE email LIKE ? OR name LIKE ? ORDER BY last_emailed_at DESC, email LIMIT 8', ["%$t%", "%$t%"])]);
}
