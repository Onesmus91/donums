<?php
/* =====================================================================
 * Secrets at rest + TOTP two-factor
 * The application key is generated once and stored in the private data
 * directory. Back it up with the database: without it, encrypted values
 * (mailbox passwords, stream keys, 2FA secrets) cannot be recovered.
 * ===================================================================== */

function app_key(): string {
    static $k = null;
    if ($k !== null) { return $k; }
    $f = DATA_DIR . '/app.key';
    if (!is_file($f)) {
        @file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($f, 0600);
    }
    $k = trim((string) @file_get_contents($f));
    if (strlen($k) < 32) { $k = hash('sha256', 'fallback-' . ROOT); }
    return $k;
}

function secret_encrypt(string $plain): string {
    if ($plain === '') { return ''; }
    $key = hash('sha256', app_key(), true);
    if (function_exists('sodium_crypto_secretbox')) {
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 's1:' . base64_encode($n . sodium_crypto_secretbox($plain, $n, $key));
    }
    $iv = random_bytes(12);
    $c = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'o1:' . base64_encode($iv . $tag . $c);
}

function secret_decrypt(?string $blob): string {
    if (!$blob) { return ''; }
    $key = hash('sha256', app_key(), true);
    if (str_starts_with($blob, 's1:') && function_exists('sodium_crypto_secretbox_open')) {
        $raw = base64_decode(substr($blob, 3), true); if ($raw === false) { return ''; }
        $n = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $p = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $n, $key);
        return $p === false ? '' : $p;
    }
    if (str_starts_with($blob, 'o1:')) {
        $raw = base64_decode(substr($blob, 3), true); if ($raw === false) { return ''; }
        $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $p === false ? '' : $p;
    }
    return '';
}

/** Short signed token (for unsubscribe links, tracking, calendar feeds). */
function sign_token(string $payload, int $len = 20): string {
    return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . substr(hash_hmac('sha256', $payload, app_key()), 0, $len);
}
function verify_token(string $token, int $len = 20): ?string {
    $p = explode('.', $token, 2);
    if (count($p) !== 2) { return null; }
    $payload = base64_decode(strtr($p[0], '-_', '+/'), true);
    if ($payload === false) { return null; }
    return hash_equals(substr(hash_hmac('sha256', $payload, app_key()), 0, $len), $p[1]) ? $payload : null;
}

/* ---------- TOTP (RFC 6238) ---------- */
function base32_encode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $o = '';
    foreach (str_split($s) as $c) { $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); }
    foreach (str_split($bits, 5) as $chunk) { $o .= $a[bindec(str_pad($chunk, 5, '0'))]; }
    return $o;
}
function base32_decode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $o = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) { $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT); }
    foreach (str_split($bits, 8) as $b) { if (strlen($b) === 8) { $o .= chr(bindec($b)); } }
    return $o;
}
function totp_new_secret(): string { return base32_encode(random_bytes(20)); }
function totp_code(string $secret, ?int $t = null): string {
    $counter = intdiv($t ?? time(), 30);
    $h = hash_hmac('sha1', pack('N*', 0, $counter), base32_decode($secret), true);
    $o = ord($h[19]) & 15;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_verify(string $secret, string $code): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) { return false; }
    foreach ([-1, 0, 1] as $w) { if (hash_equals(totp_code($secret, time() + 30 * $w), $code)) { return true; } }
    return false;
}
