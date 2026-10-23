<?php
/**
 * Donums platform bootstrap.
 * Loads configuration, prepares the private data directory, starts the session
 * and registers the error handlers. Every request enters through here.
 */
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Donums requires PHP 8.0 or newer. Your server is running PHP ' . PHP_VERSION . '.');
}

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('DN_VERSION', '2.0.0');

/* ---------- configuration (config.php is merged over defaults) ---------- */
$GLOBALS['CFG'] = array_replace([
    'site_name'  => 'Donums Consultants',
    'email'      => 'info@donums.co.ke',
    'phone1'     => '+254 106 026 970',
    'phone2'     => '+254 707 153 426',
    'whatsapp'   => '254106026970',
    'location'   => 'Kiambu Mall, Kiambu County, Nairobi Metropolitan Area',
    'domain'     => 'donums.co.ke',
    'timezone'   => 'Africa/Nairobi',
    'data_dir'   => '',            // leave empty to use /data inside public_html (protected). Better: a folder ABOVE public_html.
    'debug'      => false,
    'cron_token' => '',            // auto-generated on first run when empty
    'socials'    => [
        'LinkedIn' => '', 'Facebook' => '', 'Instagram' => '', 'X' => '', 'TikTok' => '', 'YouTube' => '',
    ],
    'noreply_mailbox' => 'info@donums.co.ke',
], is_file(ROOT . '/config.php') ? (array) require ROOT . '/config.php' : []);

function cfg(string $key, $default = null) {
    return $GLOBALS['CFG'][$key] ?? $default;
}

date_default_timezone_set((string) cfg('timezone', 'Africa/Nairobi'));
mb_internal_encoding('UTF-8');

/* ---------- private data directory ---------- */
$dd = cfg('data_dir') ?: ROOT . '/data';
if (!is_dir($dd)) { @mkdir($dd, 0750, true); }
define('DATA_DIR', rtrim(str_replace('\\', '/', $dd), '/'));
foreach (['private', 'private/cv', 'private/mail', 'private/lessons', 'private/proofs', 'logs', 'backups', 'cache'] as $sub) {
    if (!is_dir(DATA_DIR . '/' . $sub)) { @mkdir(DATA_DIR . '/' . $sub, 0750, true); }
}
// Defence in depth: if the data directory lives inside the web root, deny all web access to it.
if (!is_file(DATA_DIR . '/.htaccess')) {
    @file_put_contents(DATA_DIR . '/.htaccess', "# Donums private data — never serve over HTTP\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
}
if (!is_file(DATA_DIR . '/index.html')) { @file_put_contents(DATA_DIR . '/index.html', ''); }

/* ---------- errors ---------- */
ini_set('display_errors', cfg('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_DIR . '/logs/php-error.log');
error_reporting(E_ALL);

function app_log(string $channel, string $msg): void {
    @file_put_contents(DATA_DIR . '/logs/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}
set_exception_handler(function (Throwable $e) {
    app_log('error', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); }
    if (cfg('debug')) { echo '<pre>' . htmlspecialchars((string) $e) . '</pre>'; }
    elseif (is_file(ROOT . '/errors/500.html')) { readfile(ROOT . '/errors/500.html'); }
    else { echo 'Something went wrong. Please try again shortly.'; }
    exit;
});

/* ---------- libraries ---------- */
foreach (['helpers', 'crypto', 'db', 'auth', 'view', 'upload', 'notify', 'mailer', 'mime', 'imap', 'media'] as $lib) {
    require_once APP . '/lib/' . $lib . '.php';
}

/* ---------- session (skipped for CLI / cron / static tracking pixels) ---------- */
if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    define('IS_HTTPS', $https);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '86400');
    session_name('dn_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    $sp = DATA_DIR . '/cache/sessions';
    if (!is_dir($sp)) { @mkdir($sp, 0700, true); }
    if (is_dir($sp) && is_writable($sp)) { session_save_path($sp); }
    session_start();
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(24)); }
    if (!empty($_SESSION['uid']) && !empty($_SESSION['staff'])) {
        if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 3600) {
            $csrf = $_SESSION['csrf']; session_unset(); $_SESSION['csrf'] = $csrf; $_SESSION['flash'] = [['info', 'Your admin session timed out. Please sign in again.']];
        }
        $_SESSION['last'] = time();
    }
} else {
    define('IS_HTTPS', true);
}

// Security headers for every response
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-Download-Options: noopen');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(self), display-capture=(self), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
    if (IS_HTTPS) { header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); }
}

db(); // opens, migrates, and imports legacy data on first run
