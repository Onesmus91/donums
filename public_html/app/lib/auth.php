<?php
/* =====================================================================
 * Accounts, roles and permissions
 *
 *  - The Super Admin is the only person who can create staff roles or
 *    change anyone's access.
 *  - Everyone else is a normal member (learner / candidate / forum member)
 *    unless the Super Admin explicitly grants a scoped role.
 * ===================================================================== */

const ROLE_LABELS = [
    'instructor' => 'Instructor — manage their own courses',
    'recruiter'  => 'Recruiter — vacancies, applicants and interviews',
    'moderator'  => 'Moderator — forum moderation',
    'host'       => 'Host — create and run their own webinars',
    'support'    => 'Support — customer requests and the shared mailboxes',
];

function role_perms(): array {
    return [
        'instructor' => ['admin.access', 'learning.own'],
        'recruiter'  => ['admin.access', 'ats.manage'],
        'moderator'  => ['admin.access', 'forum.moderate'],
        'host'       => ['admin.access', 'webinars.own'],
        'support'    => ['admin.access', 'crm.manage', 'mail.use'],
    ];
}

function user(): ?array {
    static $cache = false;
    if (empty($_SESSION['uid'])) { return null; }
    if ($cache !== false && $cache && (int) $cache['id'] === (int) $_SESSION['uid']) { return $cache; }
    $u = row('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
    if (!$u || $u['status'] !== 'active') { unset($_SESSION['uid'], $_SESSION['staff']); return $cache = null; }
    return $cache = $u;
}
function uid(): int { $u = user(); return $u ? (int) $u['id'] : 0; }
function user_roles(?array $u = null): array {
    $u = $u ?? user();
    return $u ? array_values(array_filter(array_map('trim', explode(',', (string) $u['roles'])))) : [];
}
function is_super(): bool { $u = user(); return $u && (int) $u['is_super'] === 1; }

function can(string $perm): bool {
    $u = user();
    if (!$u) { return false; }
    if ((int) $u['is_super'] === 1) { return true; }
    $map = role_perms();
    foreach (user_roles($u) as $r) { if (in_array($perm, $map[$r] ?? [], true)) { return true; } }
    return false;
}
function is_staff(): bool { return can('admin.access'); }

function require_login(string $returnTo = ''): array {
    $u = user();
    if (!$u) {
        flash('info', 'Please sign in to continue.');
        redirect('/account/login?next=' . rawurlencode($returnTo ?: ($_SERVER['REQUEST_URI'] ?? '/')));
    }
    return $u;
}
function require_perm(string $perm): array {
    $u = user();
    if (!$u) { redirect('/admin/login?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin')); }
    if (!can($perm)) { http_response_code(403); render('errors/403', ['title' => 'Access restricted'], 'site'); exit; }
    return $u;
}

function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['staff'] = ((int) $u['is_super'] === 1 || trim((string) $u['roles']) !== '') ? 1 : 0;
    $_SESSION['last'] = time();
    unset($_SESSION['pending_2fa']);
    q('UPDATE users SET last_login_at = ?, failed_logins = 0, locked_until = NULL WHERE id = ?', [now(), $u['id']]);
    if ($_SESSION['staff']) { audit('login', 'user', $u['id']); }
}
function logout_user(): void {
    $csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(24));
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = $csrf;
}

/** Verify email+password. Returns [user|null, error|null, needs2fa]. */
function check_credentials(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (!rate_limit('login-ip-' . client_ip(), 25, 900)) { return [null, 'Too many sign-in attempts. Please wait a few minutes and try again.', false]; }
    $u = row('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u) { password_verify($password, '$2y$10$usesomesillystringforsaltuQ9m4HxYQeZk9vZ0tW8mVnqXr1b2c3d4e5f6'); return [null, 'The email or password is incorrect.', false]; }
    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) { return [null, 'This account is temporarily locked after repeated failed attempts. Try again in a few minutes or reset your password.', false]; }
    if (!password_verify($password, $u['password_hash'])) {
        $f = (int) $u['failed_logins'] + 1;
        q('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?', [$f, $f >= 6 ? date('Y-m-d H:i:s', time() + 900) : null, $u['id']]);
        return [null, 'The email or password is incorrect.', false];
    }
    if ($u['status'] !== 'active') { return [null, 'This account has been suspended. Please contact support.', false]; }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]); }
    return [$u, null, (int) $u['totp_enabled'] === 1 && $u['totp_secret'] !== ''];
}

function password_problem(string $p): ?string {
    if (strlen($p) < 10) { return 'Use at least 10 characters for your password.'; }
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) { return 'Your password must include letters and at least one number.'; }
    return null;
}

function create_user(string $name, string $email, string $password, array $extra = []): int {
    $id = insert('users', array_merge([
        'name' => $name, 'email' => strtolower(trim($email)), 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => now(),
    ], $extra));
    // Make sure the person exists in the mailing contacts list (consent is recorded as subscribed to service mail only).
    q('INSERT OR IGNORE INTO contacts(email,name,source,subscribed,user_id,created_at) VALUES(?,?,?,?,?,?)', [strtolower(trim($email)), $name, 'Account', 0, $id, now()]);
    return $id;
}

function issue_token(string $table, int $userId, int $ttl): string {
    $raw = bin2hex(random_bytes(24));
    q("INSERT INTO $table(token_hash,user_id,expires_at" . ($table === 'password_resets' ? ',used' : '') . ") VALUES(?,?,?" . ($table === 'password_resets' ? ',0' : '') . ")", [hash('sha256', $raw), $userId, time() + $ttl]);
    return $raw;
}

function avatar_html(array $u, int $size = 36): string {
    $s = (int) $size;
    if (!empty($u['avatar'])) { return '<img class="avatar" width="' . $s . '" height="' . $s . '" src="' . e($u['avatar']) . '" alt="">'; }
    $hue = crc32(strtolower($u['email'] ?? $u['name'] ?? 'x')) % 360;
    return '<span class="avatar" style="width:' . $s . 'px;height:' . $s . 'px;font-size:' . round($s * .4) . 'px;background:hsl(' . $hue . ' 38% 30%)">' . e(initials($u['name'] ?? '?')) . '</span>';
}
