<?php
/* Permission-checked downloads for private files. */
function get_private_file(array $p): void {
    $kind = $p['kind']; $id = (int) $p['id'];
    switch ($kind) {
        case 'cv':
            require_perm('ats.manage');
            $a = row('SELECT cv_path, cv_name FROM applications WHERE id = ?', [$id]); if (!$a || !$a['cv_path']) { not_found(); }
            audit('cv.download', 'application', $id);
            $path = str_starts_with($a['cv_path'], 'cv/') ? $a['cv_path'] : 'cv/' . basename($a['cv_path']);
            serve_private($path, $a['cv_name'] ?: 'cv.pdf', true);
        case 'proof':
            require_perm('crm.manage');
            $pay = row('SELECT proof_path FROM payments WHERE id = ?', [$id]); if (!$pay || !$pay['proof_path']) { not_found(); }
            serve_private($pay['proof_path'], 'payment-proof-' . $id, true);
        case 'mail':
            require_perm('mail.use');
            $a = row('SELECT a.*, m.mailbox FROM mail_attachments a JOIN mail_messages m ON m.id = a.message_id WHERE a.id = ?', [$id]); if (!$a) { not_found(); }
            serve_private($a['path'], $a['filename'], isset($_GET['inline']), (string) $a['mime']);
        case 'cid':
            require_perm('mail.use');
            $a = row('SELECT * FROM mail_attachments WHERE id = ?', [$id]); if (!$a || !str_starts_with((string) $a['mime'], 'image/')) { not_found(); }
            serve_private($a['path'], $a['filename'], true, (string) $a['mime']);
    }
    not_found();
}

/* ---- mail tracking, unsubscribe ---- */
function track_open(array $p): void {
    $GLOBALS['DN_SKIP_BG'] = true;
    q('UPDATE campaign_recipients SET open_count = open_count + 1, opened_at = COALESCE(opened_at, ?) WHERE token = ?', [now(), $p['token']]);
    header('Content-Type: image/gif'); header('Cache-Control: no-store, no-cache, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); exit;
}
function track_click(array $p): void {
    $r = row('SELECT id, campaign_id FROM campaign_recipients WHERE token = ?', [$p['token']]);
    $dest = (string) ($_GET['u'] ?? '');
    $dest = verify_token($dest, 16) ?? '';
    if ($r) { q('UPDATE campaign_recipients SET click_count = click_count + 1, clicked_at = COALESCE(clicked_at, ?) WHERE id = ?', [now(), $r['id']]); }
    if (!preg_match('~^https?://~i', $dest)) { redirect('/'); }
    header('Location: ' . $dest, true, 302); exit;
}
function page_unsubscribe(array $p): void {
    $r = row('SELECT * FROM campaign_recipients WHERE token = ?', [$p['token']]);
    if (!$r) { not_found(); }
    render('site/unsubscribe', ['r' => $r, 'done' => false, 'title' => 'Unsubscribe', 'noindex' => true]);
}
function post_unsubscribe(array $p): void {
    $r = row('SELECT * FROM campaign_recipients WHERE token = ?', [$p['token']]);
    if (!$r) { not_found(); }
    q('UPDATE contacts SET subscribed = 0, unsubscribed_at = ? WHERE email = ?', [now(), $r['email']]);
    q("UPDATE campaign_recipients SET status = 'skipped' WHERE email = ? AND status = 'queued'", [$r['email']]);
    if (wants_json() || isset($_POST['List-Unsubscribe'])) { json_out(['ok' => true]); }
    render('site/unsubscribe', ['r' => $r, 'done' => true, 'title' => 'Unsubscribed', 'noindex' => true]);
}
function http_cron(array $p): void {
    $key = (string) cfg('cron_token', '') ?: (string) setting('cron_key', '');
    if ($key === '' || !hash_equals($key, $p['key'])) { http_response_code(403); exit('Forbidden'); }
    $GLOBALS['DN_SKIP_BG'] = true;
    header('Content-Type: text/plain'); echo run_cron(); exit;
}
