<?php
/* Accounts: register, sign in (with optional 2FA), password reset, profile, notifications. */

function safe_next(string $n, string $default = '/account'): string {
    return ($n !== '' && $n[0] === '/' && !str_starts_with($n, '//') && !str_contains($n, '\\')) ? $n : $default;
}

function page_login(): void {
    if (user()) { redirect(safe_next(qget('next'))); }
    render('account/login', ['next' => qget('next'), 'step' => !empty($_SESSION['pending_2fa']) ? '2fa' : 'password', 'title' => 'Sign in', 'noindex' => true]);
}

function post_login(): void {
    $next = safe_next(post('next', ''));
    if (!empty($_SESSION['pending_2fa']) && isset($_POST['code'])) {            // step 2: authenticator code
        $u = row('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['pending_2fa']]);
        if (!rate_limit('2fa-' . client_ip(), 10, 600)) { flash('error', 'Too many attempts. Please wait a few minutes.'); redirect('/account/login'); }
        if ($u && totp_verify(secret_decrypt($u['totp_secret']), (string) $_POST['code'])) { login_user($u); redirect($next); }
        flash('error', 'That code is not valid. Check the time on your phone and try again.');
        redirect('/account/login?next=' . rawurlencode($next));
    }
    [$u, $err, $needs2fa] = check_credentials((string) post('email'), (string) post('password'));
    if (!$u) { flash('error', $err); redirect('/account/login?next=' . rawurlencode($next)); }
    if ($needs2fa) { $_SESSION['pending_2fa'] = (int) $u['id']; redirect('/account/login?next=' . rawurlencode($next)); }
    login_user($u);
    redirect($next === '/account' && $_SESSION['staff'] && !empty($_POST['admin']) ? '/admin' : $next);
}

function page_register(): void {
    if (user()) { redirect('/account'); }
    render('account/register', ['next' => qget('next'), 'title' => 'Create your account', 'desc' => 'One Donums account for courses, careers, community and live sessions.', 'noindex' => false]);
}
function post_register(): void {
    $next = safe_next(post('next', ''));
    if (!spam_check()) { flash('error', 'We could not verify that submission. Please try again.'); redirect('/account/register'); }
    if (!rate_limit('reg-' . client_ip(), 8, 3600)) { flash('error', 'Too many sign-ups from this connection. Please try again later.'); redirect('/account/register'); }
    $name = clean('name', 100); $email = strtolower(clean('email', 160)); $pw = (string) post('password');
    if ($name === '' || !mail_valid($email)) { flash('error', 'Please enter your name and a valid email address.'); redirect('/account/register'); }
    if ($pe = password_problem($pw)) { flash('error', $pe); redirect('/account/register'); }
    if (empty($_POST['agree'])) { flash('error', 'Please accept the Terms and Privacy Policy to continue.'); redirect('/account/register'); }
    if (val('SELECT 1 FROM users WHERE email = ?', [$email])) { flash('error', 'An account with this email already exists. Try signing in or resetting your password.'); redirect('/account/login'); }
    $id = create_user($name, $email, $pw, ['phone' => clean('phone', 40)]);
    if (!empty($_POST['newsletter'])) { q('UPDATE contacts SET subscribed = 1 WHERE email = ?', [$email]); }
    // Attach earlier guest activity (applications, webinar registrations) to the new account
    q('UPDATE applications SET user_id = ? WHERE email = ? AND user_id IS NULL', [$id, $email]);
    q('UPDATE webinar_regs SET user_id = ? WHERE email = ? AND user_id IS NULL', [$id, $email]);
    $tok = issue_token('email_verifications', $id, 172800);
    queue_template('verify_email', $email, ['name' => $name, 'cta_url' => abs_url('/account/verify/' . $tok), 'cta_label' => 'Confirm my email'], ['dept' => 'Account']);
    queue_template('welcome', $email, ['name' => $name], ['dept' => 'Welcome']);
    login_user(row('SELECT * FROM users WHERE id = ?', [$id]));
    flash('ok', 'Welcome to Donums, ' . explode(' ', $name)[0] . '. We sent a confirmation link to ' . $email . '.');
    redirect($next);
}
function page_verify(array $p): void {
    $h = hash('sha256', $p['token']);
    $r = row('SELECT * FROM email_verifications WHERE token_hash = ? AND expires_at > ?', [$h, time()]);
    if ($r) { q('UPDATE users SET email_verified_at = ? WHERE id = ?', [now(), $r['user_id']]); q('DELETE FROM email_verifications WHERE token_hash = ?', [$h]); flash('ok', 'Your email address is confirmed. Thank you.'); }
    else { flash('error', 'That confirmation link is invalid or has expired. Request a new one from your profile.'); }
    redirect(user() ? '/account' : '/account/login');
}
function post_resend_verify(): void {
    $u = require_login();
    if ($u['email_verified_at']) { redirect('/account'); }
    if (rate_limit('verify-' . $u['id'], 3, 3600)) {
        $tok = issue_token('email_verifications', (int) $u['id'], 172800);
        queue_template('verify_email', $u['email'], ['name' => $u['name'], 'cta_url' => abs_url('/account/verify/' . $tok), 'cta_label' => 'Confirm my email'], ['dept' => 'Account']);
        process_outbox(3);
        flash('ok', 'A new confirmation link is on its way to ' . $u['email'] . '.');
    } else { flash('error', 'Please wait before requesting another link.'); }
    redirect('/account');
}

function page_logout(): void { render('account/logout', ['title' => 'Sign out', 'noindex' => true]); }
function post_logout(): void { logout_user(); flash('ok', 'You have been signed out.'); redirect('/'); }

function page_forgot(): void { render('account/forgot', ['title' => 'Reset your password', 'noindex' => true]); }
function post_forgot(): void {
    $email = strtolower(clean('email', 160));
    if (rate_limit('forgot-' . client_ip(), 5, 3600) && mail_valid($email)) {
        $u = row("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
        if ($u) {
            $tok = issue_token('password_resets', (int) $u['id'], 3600);
            queue_template('password_reset', $email, ['name' => $u['name'], 'cta_url' => abs_url('/account/reset/' . $tok), 'cta_label' => 'Choose a new password'], ['dept' => 'Account security']);
            process_outbox(3);
        }
    }
    flash('ok', 'If an account exists for that email, a reset link has been sent. It expires in 60 minutes.');
    redirect('/account/forgot');
}
function page_reset(array $p): void {
    $r = row('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?', [hash('sha256', $p['token']), time()]);
    if (!$r) { flash('error', 'This reset link is invalid or has expired. Please request a new one.'); redirect('/account/forgot'); }
    render('account/reset', ['token' => $p['token'], 'title' => 'Choose a new password', 'noindex' => true]);
}
function post_reset(array $p): void {
    $h = hash('sha256', $p['token']);
    $r = row('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?', [$h, time()]);
    if (!$r) { flash('error', 'This reset link is invalid or has expired.'); redirect('/account/forgot'); }
    $pw = (string) post('password');
    if ($pe = password_problem($pw)) { flash('error', $pe); redirect('/account/reset/' . $p['token']); }
    if ($pw !== (string) post('password2')) { flash('error', 'The two passwords do not match.'); redirect('/account/reset/' . $p['token']); }
    q('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), now(), $r['user_id']]);
    q('UPDATE password_resets SET used = 1 WHERE user_id = ?', [$r['user_id']]);
    flash('ok', 'Your password has been changed. You can now sign in.');
    redirect('/account/login');
}

function page_account(): void {
    $u = require_login();
    $enrol = rows("SELECT e.*, c.title, c.slug, c.thumbnail, c.lessons_count FROM enrolments e JOIN courses c ON c.id=e.course_id WHERE e.user_id=? AND e.status IN ('active','completed','pending') ORDER BY e.enrolled_at DESC", [$u['id']]);
    $apps = rows("SELECT a.*, j.title job_title, j.organisation FROM applications a LEFT JOIN jobs j ON j.id=a.job_id WHERE a.user_id=? OR a.email=? ORDER BY a.created_at DESC", [$u['id'], $u['email']]);
    $regs = rows("SELECT r.*, w.title, w.slug, w.start_at, w.status wstatus FROM webinar_regs r JOIN webinars w ON w.id=r.webinar_id WHERE r.user_id=? OR r.email=? ORDER BY w.start_at DESC", [$u['id'], $u['email']]);
    $certs = rows('SELECT * FROM certificates WHERE user_id = ? ORDER BY issued_at DESC', [$u['id']]);
    $threads = rows("SELECT id,title,slug,reply_count,created_at FROM forum_threads WHERE user_id=? AND status='published' ORDER BY id DESC LIMIT 5", [$u['id']]);
    render('account/dashboard', compact('u', 'enrol', 'apps', 'regs', 'certs', 'threads') + ['title' => 'My account', 'noindex' => true]);
}

function page_profile(): void { $u = require_login(); render('account/profile', ['u' => $u, 'title' => 'Profile & security', 'noindex' => true]); }
function post_profile(): void {
    $u = require_login(); $act = post('action');
    if ($act === 'password') {
        if (!password_verify((string) post('current'), $u['password_hash'])) { flash('error', 'Your current password is incorrect.'); redirect('/account/profile'); }
        if ($pe = password_problem((string) post('password'))) { flash('error', $pe); redirect('/account/profile'); }
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string) post('password'), PASSWORD_DEFAULT), $u['id']]);
        flash('ok', 'Password updated.'); redirect('/account/profile');
    }
    $data = ['name' => clean('name', 100) ?: $u['name'], 'phone' => clean('phone', 40), 'headline' => clean('headline', 120), 'location' => clean('location', 100), 'bio' => text('bio', 800), 'notify_email' => empty($_POST['notify_email']) ? 0 : 1];
    $img = save_public_image('avatar', 'avatar', 400);
    if ($img !== '' && $img[0] === '!') { flash('error', substr($img, 1)); redirect('/account/profile'); }
    if ($img) { $data['avatar'] = $img; }
    update('users', $u['id'], $data);
    flash('ok', 'Profile saved.'); redirect('/account/profile');
}

function page_notifications(): void {
    $u = require_login();
    $items = rows('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 60', [$u['id']]);
    q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), $u['id']]);
    render('account/notifications', ['items' => $items, 'title' => 'Notifications', 'noindex' => true]);
}
