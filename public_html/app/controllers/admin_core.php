<?php
/* =====================================================================
 * Admin console — shell, navigation, dashboard, first-run setup.
 * Only the Super Admin sees everything; other staff see only the modules
 * their role was explicitly granted.
 * ===================================================================== */

function can_any(string ...$perms): bool { foreach ($perms as $p) { if (can($p)) { return true; } } return false; }

function admin_nav(): array {
    $n = [];
    $n[] = ['Overview', [['/admin', 'Dashboard', 'grid', 'admin.access']]];
    $n[] = ['Operations', [
        ['/admin/requests', 'Requests', 'inbox', 'crm.manage'], ['/admin/partners', 'Partners', 'briefcase', 'crm.manage'],
        ['/admin/deliveries', 'Deliveries', 'layers', 'crm.manage'], ['/admin/listings', 'Listings', 'building', 'crm.manage'], ['/admin/payments', 'Payments', 'dollar', 'crm.manage']]];
    $n[] = ['Communication', [['/admin/mail', 'Mail', 'mail', 'mail.use'], ['/admin/campaigns', 'Campaigns', 'megaphone', 'mail.use'], ['/admin/contacts', 'Contacts', 'users', 'mail.use'], ['/admin/templates', 'Templates', 'file', 'mail.use']]];
    $n[] = ['Platform', [
        ['/admin/courses', 'Academy', 'book', ['learning.manage', 'learning.own']], ['/admin/ats', 'Careers & ATS', 'briefcase', 'ats.manage'],
        ['/admin/forum', 'Community', 'chat', 'forum.moderate'], ['/admin/webinars', 'Live', 'video', ['webinars.manage', 'webinars.own']]]];
    $n[] = ['Administration', [['/admin/users', 'Users & roles', 'shield', 'users.manage'], ['/admin/settings', 'Settings', 'sliders', 'users.manage'], ['/admin/system', 'System & security', 'lock', 'users.manage'], ['/admin/audit', 'Audit log', 'file', 'users.manage']]];
    return $n;
}
function nav_allowed($perm): bool {
    if (is_array($perm)) { return can_any(...$perm); }
    if (in_array($perm, ['users.manage', 'learning.manage', 'webinars.manage'], true)) { return is_super(); }
    return can($perm);
}

function admin_render(string $tpl, array $vars = []): void {
    render($tpl, $vars + ['noindex' => true], 'admin');
}

function admin_guard(string ...$perms): array {
    $u = user();
    if (!$u) { redirect('/account/login?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin')); }
    if (!is_staff()) { http_response_code(403); render('errors/403', ['title' => 'Access restricted'], 'site'); exit; }
    if ($perms && !can_any(...$perms) && !is_super()) { http_response_code(403); render('errors/403', ['title' => 'Access restricted'], 'site'); exit; }
    return $u;
}

function page_admin(): void {
    if (!(int) val('SELECT COUNT(*) FROM users WHERE is_super = 1')) { render('admin/setup', ['title' => 'Set up Donums', 'noindex' => true], 'site'); return; }
    $u = admin_guard('admin.access');
    $c = [];
    if (can('crm.manage')) {
        $c['requests_new'] = (int) val("SELECT COUNT(*) FROM requests WHERE status = 'New'");
        $c['requests_open'] = (int) val("SELECT COUNT(*) FROM requests WHERE status IN ('New','Contacted','Quoted')");
        $c['payments'] = (int) val("SELECT COUNT(*) FROM payments WHERE status = 'submitted'");
        $c['partners'] = (int) val("SELECT COUNT(*) FROM partners WHERE verification = 'Pending'");
        $c['recent_requests'] = rows('SELECT * FROM requests ORDER BY id DESC LIMIT 6');
    }
    if (can('mail.use')) { $c['unread'] = (int) val("SELECT COUNT(*) FROM mail_messages WHERE folder = 'inbox' AND is_read = 0"); $c['queued'] = (int) val("SELECT COUNT(*) FROM outbox WHERE status = 'queued'"); $c['failed'] = (int) val("SELECT COUNT(*) FROM outbox WHERE status = 'failed'"); }
    if (can('ats.manage')) {
        $c['apps_new'] = (int) val("SELECT COUNT(*) FROM applications WHERE stage = 'Applied'"); $c['jobs_pending'] = (int) val("SELECT COUNT(*) FROM jobs WHERE status = 'pending'"); $c['jobs_open'] = (int) val("SELECT COUNT(*) FROM jobs WHERE status = 'published'");
        $c['interviews'] = rows("SELECT i.*, a.name FROM interviews i JOIN applications a ON a.id=i.application_id WHERE i.status='Scheduled' AND i.starts_at >= ? ORDER BY i.starts_at LIMIT 5", [now()]);
    }
    if (can('forum.moderate')) { $c['pending_posts'] = (int) val("SELECT (SELECT COUNT(*) FROM forum_threads WHERE status='pending') + (SELECT COUNT(*) FROM forum_posts WHERE status='pending')"); $c['reports'] = (int) val("SELECT COUNT(*) FROM forum_reports WHERE status='open'"); }
    if (can_any('learning.manage', 'learning.own')) { $c['courses'] = (int) val("SELECT COUNT(*) FROM courses WHERE status='published'"); $c['learners'] = (int) val("SELECT COUNT(DISTINCT user_id) FROM enrolments WHERE status IN ('active','completed')"); }
    if (can_any('webinars.manage', 'webinars.own')) { $c['next_webinars'] = rows("SELECT * FROM webinars WHERE status IN ('scheduled','live') ORDER BY start_at LIMIT 4"); }
    if (is_super()) { $c['users'] = (int) val('SELECT COUNT(*) FROM users'); $c['health'] = system_checks(); }
    admin_render('admin/dashboard', ['c' => $c, 'title' => 'Dashboard', 'section' => 'Dashboard']);
}

function post_admin_setup(): void {
    if ((int) val('SELECT COUNT(*) FROM users WHERE is_super = 1')) { redirect('/admin'); }
    $name = clean('name', 100); $email = strtolower(clean('email', 160)); $pw = (string) post('password');
    if ($name === '' || !mail_valid($email)) { flash('error', 'Enter your name and a valid email address.'); redirect('/admin'); }
    if ($pe = password_problem($pw)) { flash('error', $pe); redirect('/admin'); }
    if ($pw !== (string) post('password2')) { flash('error', 'The passwords do not match.'); redirect('/admin'); }
    $id = create_user($name, $email, $pw, ['is_super' => 1, 'email_verified_at' => now()]);
    login_user(row('SELECT * FROM users WHERE id = ?', [$id]));
    flash('ok', 'Your Super Admin account is ready. Next: add your mailbox passwords under Mail → Settings.');
    redirect('/admin');
}

/** Environment and security self-check shown on the dashboard and System page. */
function system_checks(): array {
    $out = [];
    $out[] = ['PHP version', PHP_VERSION_ID >= 80100 ? 'ok' : 'warn', PHP_VERSION . (PHP_VERSION_ID >= 80100 ? '' : ' — PHP 8.1 or newer is recommended')];
    foreach (['pdo_sqlite' => 'Database (SQLite)', 'mbstring' => 'Multibyte strings', 'openssl' => 'OpenSSL (secure mail)', 'fileinfo' => 'File type detection', 'gd' => 'Image processing (GD)', 'curl' => 'cURL'] as $ext => $label) {
        $out[] = [$label, extension_loaded($ext) ? 'ok' : ($ext === 'gd' || $ext === 'curl' ? 'warn' : 'bad'), extension_loaded($ext) ? 'Enabled' : 'Missing — ask your host to enable ' . $ext];
    }
    $out[] = ['Data folder writable', is_writable(DATA_DIR) ? 'ok' : 'bad', DATA_DIR];
    $inWeb = str_starts_with(DATA_DIR, str_replace('\\', '/', ROOT));
    $out[] = ['Data folder location', $inWeb ? 'warn' : 'ok', $inWeb ? 'Inside public_html (protected by .htaccess). Best practice: move it above public_html and set data_dir in config.php.' : 'Outside the public web root'];
    $out[] = ['HTTPS', IS_HTTPS ? 'ok' : 'warn', IS_HTTPS ? 'Active' : 'Not detected — enable SSL and uncomment the redirect in .htaccess'];
    $cron = (string) setting('last_cron', '');
    $out[] = ['Scheduled tasks (cron)', $cron && time() - strtotime($cron) < 1800 ? 'ok' : 'warn', $cron ? 'Last run ' . ago($cron) : 'Not running yet — add the cron job (see System page)'];
    $out[] = ['Mail queue', (int) val("SELECT COUNT(*) FROM outbox WHERE status='failed'") ? 'warn' : 'ok', (int) val("SELECT COUNT(*) FROM outbox WHERE status='queued'") . ' queued · ' . (int) val("SELECT COUNT(*) FROM outbox WHERE status='failed'") . ' failed'];
    $noPw = (int) val("SELECT COUNT(*) FROM mailboxes WHERE active = 1 AND (password_enc IS NULL OR password_enc = '')");
    $out[] = ['Mailboxes configured', $noPw ? 'warn' : 'ok', $noPw ? $noPw . ' mailbox(es) have no password yet (Mail → Settings)' : 'All active mailboxes have credentials'];
    $su = row('SELECT totp_enabled FROM users WHERE is_super = 1 ORDER BY id LIMIT 1');
    $out[] = ['Two-step verification (Super Admin)', $su && $su['totp_enabled'] ? 'ok' : 'warn', $su && $su['totp_enabled'] ? 'Enabled' : 'Not enabled — strongly recommended (Users & roles → Security)'];
    return $out;
}

function admin_routes(): void {
    route('GET', '/admin', 'page_admin');
    route('POST', '/admin/setup', 'post_admin_setup');
    route('GET', '/admin/login', 'page_admin_login');
    foreach (['crm', 'mail', 'learning', 'ats', 'forum', 'webinars', 'system'] as $m) { $f = 'admin_routes_' . $m; if (function_exists($f)) { $f(); } }
}
function page_admin_login(): void { redirect('/account/login?next=' . rawurlencode('/admin')); }

/* ---------- tiny form builders used across admin views ---------- */
function a_in(string $label, string $name, $val = '', array $o = []): string {
    $type = $o['type'] ?? 'text'; $hint = !empty($o['hint']) ? '<span class="hint">' . e($o['hint']) . '</span>' : '';
    $attrs = ($o['required'] ?? false ? ' required' : '') . (isset($o['ph']) ? ' placeholder="' . e($o['ph']) . '"' : '') . (isset($o['min']) ? ' min="' . e($o['min']) . '"' : '') . (isset($o['step']) ? ' step="' . e($o['step']) . '"' : '') . (isset($o['list']) ? ' list="' . e($o['list']) . '"' : '') . ($o['attrs'] ?? '');
    if ($type === 'textarea') { return '<div class="field"><label for="f-' . e($name) . '">' . e($label) . '</label><textarea id="f-' . e($name) . '" name="' . e($name) . '" rows="' . ($o['rows'] ?? 5) . '"' . $attrs . '>' . e($val) . '</textarea>' . $hint . '</div>'; }
    return '<div class="field"><label for="f-' . e($name) . '">' . e($label) . '</label><input id="f-' . e($name) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($val) . '"' . $attrs . '>' . $hint . '</div>';
}
function a_sel(string $label, string $name, array $options, $val = '', string $hint = ''): string {
    $o = ''; $assoc = array_keys($options) !== range(0, count($options) - 1);
    foreach ($options as $k => $v) { $key = $assoc ? $k : $v; $o .= '<option value="' . e($key) . '"' . ((string) $key === (string) $val ? ' selected' : '') . '>' . e($v) . '</option>'; }
    return '<div class="field"><label for="f-' . e($name) . '">' . e($label) . '</label><select id="f-' . e($name) . '" name="' . e($name) . '">' . $o . '</select>' . ($hint ? '<span class="hint">' . e($hint) . '</span>' : '') . '</div>';
}
function a_check(string $label, string $name, $on = false): string { return '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '><span>' . e($label) . '</span></label>'; }
function csv_download(string $filename, array $header, array $rowsData): void {
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
    $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF"); fputcsv($f, $header);
    foreach ($rowsData as $r) { fputcsv($f, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r)); }
    fclose($f); exit;
}
