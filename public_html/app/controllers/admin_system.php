<?php
/* Admin → Administration: users & roles (Super Admin only), settings, system health, security, audit log. */

function admin_routes_system(): void {
    route('GET', '/admin/users', 'admin_users');
    route('POST', '/admin/users', 'admin_users_action');
    route('GET', '/admin/users/{id:\d+}', 'admin_user');
    route('POST', '/admin/users/{id:\d+}', 'admin_user_save');
    route('GET', '/admin/settings', 'admin_settings');
    route('POST', '/admin/settings', 'admin_settings_save');
    route('GET', '/admin/system', 'admin_system');
    route('POST', '/admin/system/2fa', 'admin_2fa');
    route('GET', '/admin/system/backup', 'admin_backup_download');
    route('GET', '/admin/audit', 'admin_audit');
}
function super_guard(): array { $u = admin_guard(); if (!is_super()) { http_response_code(403); render('errors/403', ['title' => 'Access restricted'], 'site'); exit; } return $u; }

function admin_users(): void {
    super_guard(); $s = qget('q'); $f = qget('filter'); $page = max(1, qint('page', 1)); $w = ['1=1']; $par = [];
    if ($s !== '') { $w[] = '(name LIKE ? OR email LIKE ?)'; array_push($par, "%$s%", "%$s%"); }
    if ($f === 'staff') { $w[] = "(is_super = 1 OR roles != '')"; } elseif ($f === 'suspended') { $w[] = "status = 'suspended'"; }
    $pg = paginate((int) val('SELECT COUNT(*) FROM users WHERE ' . implode(' AND ', $w), $par), 30, $page);
    $items = rows('SELECT * FROM users WHERE ' . implode(' AND ', $w) . " ORDER BY is_super DESC, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $par);
    admin_render('admin/users', compact('items', 'pg', 's', 'f') + ['title' => 'Users & roles', 'section' => 'Users & roles']);
}
function admin_users_action(): void {
    super_guard();
    if (post('action') === 'create') {
        $name = clean('name', 100); $email = strtolower(clean('email', 160));
        if ($name === '' || !mail_valid($email)) { flash('error', 'Enter a name and a valid email.'); redirect('/admin/users'); }
        if (val('SELECT 1 FROM users WHERE email = ?', [$email])) { flash('error', 'A user with that email already exists — open them from the list to change their role.'); redirect('/admin/users'); }
        $roles = implode(',', array_values(array_intersect((array) ($_POST['roles'] ?? []), array_keys(ROLE_LABELS))));
        $id = create_user($name, $email, bin2hex(random_bytes(14)), ['roles' => $roles, 'email_verified_at' => now()]);
        $tok = issue_token('password_resets', $id, 86400 * 3);
        queue_template('staff_invite', $email, ['name' => $name, 'role' => $roles ?: 'member', 'cta_url' => abs_url('/account/reset/' . $tok), 'cta_label' => 'Set your password'], ['dept' => 'Access']); process_outbox(3);
        audit('user.create', 'user', $id, ['roles' => $roles]); flash('ok', $name . ' was added and sent an email to set their password (valid 3 days).');
    }
    redirect('/admin/users');
}
function admin_user(array $p): void {
    super_guard(); $x = row('SELECT * FROM users WHERE id = ?', [(int) $p['id']]); if (!$x) { not_found(); }
    $activity = rows('SELECT * FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 15', [$x['id']]);
    $stats = ['courses' => (int) val('SELECT COUNT(*) FROM enrolments WHERE user_id = ?', [$x['id']]), 'apps' => (int) val('SELECT COUNT(*) FROM applications WHERE user_id = ? OR email = ?', [$x['id'], $x['email']]), 'posts' => (int) val('SELECT COUNT(*) FROM forum_posts WHERE user_id = ?', [$x['id']]) + (int) val('SELECT COUNT(*) FROM forum_threads WHERE user_id = ?', [$x['id']])];
    admin_render('admin/user', compact('x', 'activity', 'stats') + ['title' => $x['name'], 'section' => $x['name']]);
}
function admin_user_save(array $p): void {
    super_guard(); $x = row('SELECT * FROM users WHERE id = ?', [(int) $p['id']]); if (!$x) { not_found(); } $act = post('action');
    $self = (int) $x['id'] === uid();
    if ($act === 'roles') {
        if ($x['is_super']) { flash('error', 'The Super Admin’s access cannot be changed here.'); redirect('/admin/users/' . $x['id']); }
        $roles = implode(',', array_values(array_intersect((array) ($_POST['roles'] ?? []), array_keys(ROLE_LABELS)))); update('users', $x['id'], ['roles' => $roles]);
        audit('user.roles', 'user', $x['id'], ['roles' => $roles]); flash('ok', 'Access updated.' . ($roles ? '' : ' This person is now a normal member.'));
    } elseif ($act === 'suspend' && !$self && !$x['is_super']) { update('users', $x['id'], ['status' => $x['status'] === 'active' ? 'suspended' : 'active']); audit('user.status', 'user', $x['id']); flash('ok', 'Account ' . ($x['status'] === 'active' ? 'suspended' : 'reactivated') . '.'); }
    elseif ($act === 'reset') { $tok = issue_token('password_resets', (int) $x['id'], 3600); queue_template('password_reset', $x['email'], ['name' => $x['name'], 'cta_url' => abs_url('/account/reset/' . $tok), 'cta_label' => 'Choose a new password'], ['dept' => 'Account security']); process_outbox(3); flash('ok', 'A password reset link was emailed to ' . $x['email'] . '.'); }
    elseif ($act === 'reset2fa') { update('users', $x['id'], ['totp_enabled' => 0, 'totp_secret' => null]); audit('user.2fa_reset', 'user', $x['id']); flash('ok', 'Two-step verification removed for this account.'); }
    elseif ($act === 'verify') { update('users', $x['id'], ['email_verified_at' => now()]); flash('ok', 'Email marked as confirmed.'); }
    elseif ($act === 'delete' && !$self && !$x['is_super']) {
        foreach (['notifications', 'password_resets', 'email_verifications'] as $t) { q("DELETE FROM $t WHERE user_id = ?", [$x['id']]); }
        q('UPDATE applications SET user_id = NULL WHERE user_id = ?', [$x['id']]); q('UPDATE webinar_regs SET user_id = NULL WHERE user_id = ?', [$x['id']]); q('DELETE FROM enrolments WHERE user_id = ?', [$x['id']]); q('DELETE FROM lesson_progress WHERE user_id = ?', [$x['id']]);
        q("UPDATE forum_posts SET status = 'deleted' WHERE user_id = ?", [$x['id']]); q("UPDATE forum_threads SET status = 'deleted' WHERE user_id = ?", [$x['id']]); q('DELETE FROM users WHERE id = ?', [$x['id']]);
        audit('user.delete', 'user', $x['id'], ['email' => $x['email']]); flash('ok', 'User removed.'); redirect('/admin/users');
    }
    redirect('/admin/users/' . $x['id']);
}

function admin_settings(): void { super_guard(); admin_render('admin/settings', ['title' => 'Settings', 'section' => 'Settings']); }
function admin_settings_save(): void {
    super_guard();
    foreach (['site_tagline', 'pay_mpesa_paybill', 'pay_mpesa_account', 'pay_mpesa_till', 'pay_bank', 'pay_note'] as $k) { set_setting($k, text($k, 800)); }
    set_setting('forum_moderate_new', post('forum_moderate_new') ? '1' : '0');
    set_setting('mail_batch', (string) max(5, min(200, (int) post('mail_batch', 25))));
    $ice = trim((string) post('ice_servers')); if ($ice !== '' && !is_array(json_decode($ice, true))) { flash('error', 'The ICE servers value must be valid JSON. Everything else was saved.'); $ice = (string) setting('ice_servers'); } set_setting('ice_servers', $ice);
    audit('settings.save', 'settings'); flash('ok', 'Settings saved.'); redirect('/admin/settings');
}

function admin_system(): void {
    super_guard();
    $legacy = []; foreach (['learning.php', 'jobs.php', 'forum/index1.php', 'api/forum.php', 'api/learning.php', 'api/jobs.php', 'api/webinars.php', 'api/submit.php', 'api/contact-submit.php', 'admin/index.php', 'admin/ats.php', 'admin/health.php', 'admin/learning.php', 'admin/community.php', 'admin/payments.php', 'service.php', 'cart.php', 'track.php', 'partner.php', 'contact.php', 'privacy.php', 'terms.php', 'download', 'api-contact-submit', 'data/private-cv/.htacess'] as $f) { if (is_file(ROOT . '/' . $f)) { $legacy[] = $f; } }
    $legacyData = array_values(array_filter(['admin.json', 'mail_accounts.json', 'mail_log.json', 'requests.ndjson', 'operations.json', 'partners.json', 'recruitment_jobs.json', 'job_applications.json', 'learning_courses.json', 'learning_enrolments.ndjson', 'catalog.json', 'forum.json', 'webinars.json', 'jobs.json', 'mail_drafts.json', 'mail_display_names.json'], fn($f) => is_file(DATA_DIR . '/' . $f)));
    $backups = array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)], array_reverse(glob(DATA_DIR . '/backups/donums-*.sqlite') ?: []));
    $dbSize = is_file(DATA_DIR . '/donums.sqlite') ? filesize(DATA_DIR . '/donums.sqlite') : 0;
    $me = user(); $secret = null; if (!$me['totp_enabled']) { if (empty($_SESSION['totp_pending'])) { $_SESSION['totp_pending'] = totp_new_secret(); } $secret = $_SESSION['totp_pending']; }
    admin_render('admin/system', ['checks' => system_checks(), 'legacy' => $legacy, 'legacyData' => $legacyData, 'backups' => $backups, 'dbSize' => $dbSize, 'secret' => $secret, 'me' => $me, 'cronKey' => cfg('cron_token') ?: setting('cron_key', ''), 'title' => 'System & security', 'section' => 'System & security']);
}
function admin_2fa(): void {
    super_guard(); $me = user();
    if (post('action') === 'enable') {
        $sec = $_SESSION['totp_pending'] ?? '';
        if ($sec && totp_verify($sec, (string) post('code'))) { update('users', $me['id'], ['totp_secret' => secret_encrypt($sec), 'totp_enabled' => 1]); unset($_SESSION['totp_pending']); audit('2fa.enable', 'user', $me['id']); flash('ok', 'Two-step verification is now on. You will be asked for a code each time you sign in.'); }
        else { flash('error', 'That code did not match. Check the key in your app and try again.'); }
    } elseif (post('action') === 'disable') {
        if (password_verify((string) post('password'), $me['password_hash'])) { update('users', $me['id'], ['totp_enabled' => 0, 'totp_secret' => null]); audit('2fa.disable', 'user', $me['id']); flash('ok', 'Two-step verification turned off.'); } else { flash('error', 'Your password was incorrect.'); }
    }
    redirect('/admin/system');
}
function admin_backup_download(): void {
    super_guard(); $tmp = DATA_DIR . '/cache/export-' . bin2hex(random_bytes(4)) . '.sqlite';
    db()->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'"); audit('backup.download', 'database');
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="donums-backup-' . date('Ymd-Hi') . '.sqlite"'); header('Content-Length: ' . filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
}
function admin_audit(): void {
    super_guard(); $s = qget('q'); $page = max(1, qint('page', 1)); $w = ['1=1']; $par = [];
    if ($s !== '') { $w[] = '(a.action LIKE ? OR a.entity LIKE ? OR a.entity_id LIKE ? OR u.name LIKE ?)'; array_push($par, "%$s%", "%$s%", "%$s%", "%$s%"); }
    $pg = paginate((int) val('SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $w), $par), 50, $page);
    $items = rows('SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $w) . " ORDER BY a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $par);
    admin_render('admin/audit', compact('items', 'pg', 's') + ['title' => 'Audit log', 'section' => 'Audit log']);
}
