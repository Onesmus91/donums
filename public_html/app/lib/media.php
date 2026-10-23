<?php
/* =====================================================================
 * Broadcast engine integration.
 *
 * Interactive rooms (up to ~25 viewers) run browser-to-browser with no server.
 * Broadcast rooms add a small media server (MediaMTX) that fans the host's
 * "program" feed out to 100+ viewers over WebRTC and, through a restream
 * helper, pushes the same feed to YouTube / Facebook / LinkedIn / any RTMP.
 *
 * This file is the website's side of that: settings, signed access tokens
 * for the media server, and calls to the restream helper.
 * ===================================================================== */

function media_url(): string { return rtrim((string) setting('media_url', ''), '/'); }
function media_secret(): string { return secret_decrypt((string) setting('media_secret_enc', '')); }
function media_enabled(): bool { return media_url() !== '' && media_secret() !== ''; }
function webinar_broadcast(array $w): bool { return ($w['engine'] ?? '') === 'broadcast' && media_enabled(); }
function media_path(array $w): string { return 'live/' . $w['room_key']; }

/** Signed, expiring permission slip the media server asks us to check (see post_media_auth). */
function media_token(string $path, string $action, int $ttl = 21600): string {
    return sign_token(json_encode(['p' => $path, 'a' => $action, 'e' => time() + $ttl]), 24);
}
function media_token_ok(string $token, string $path, string $action): bool {
    $j = verify_token($token, 24); if ($j === null) { return false; }
    $c = json_decode($j, true);
    return is_array($c) && ($c['p'] ?? '') === $path && ($c['a'] ?? '') === $action && (int) ($c['e'] ?? 0) >= time();
}
/** Password the restream helper (ffmpeg) uses to read the program feed from the media server on localhost. */
function media_relay_password(): string { return substr(hash_hmac('sha256', 'relay', media_secret()), 0, 40); }

/** Called BY the media server for every publish/read attempt (MediaMTX "external HTTP authentication"). */
function post_media_auth(): void {
    $GLOBALS['DN_SKIP_BG'] = true;
    $b = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $action = (string) ($b['action'] ?? ''); $path = trim((string) ($b['path'] ?? ''), '/');
    parse_str((string) ($b['query'] ?? ''), $q);
    $ok = false;
    if (media_enabled() && str_starts_with($path, 'live/') && in_array($action, ['publish', 'read'], true)) {
        $w = row('SELECT id, status FROM webinars WHERE room_key = ? AND engine = ?', [substr($path, 5), 'broadcast']);
        if ($w && in_array($w['status'], ['scheduled', 'live'], true)) {
            $tok = (string) ($q['token'] ?? '');
            if ($action === 'publish') { $ok = $tok !== '' && media_token_ok($tok, $path, 'publish'); }
            else { $ok = ($tok !== '' && media_token_ok($tok, $path, 'read')) || (($b['user'] ?? '') === 'relay' && hash_equals(media_relay_password(), (string) ($b['password'] ?? ''))); }
        }
    }
    http_response_code($ok ? 200 : 401);
    header('Content-Type: application/json'); echo $ok ? '{}' : '{"error":"denied"}'; exit;
}

/** Talk to the restream helper running next to the media server. @return array{0:bool,1:mixed} */
function media_helper(string $method, string $endpoint, ?array $body = null): array {
    if (!media_enabled()) { return [false, 'The media server is not configured yet (Admin → Live → Streaming).']; }
    $base = rtrim((string) (cfg('media_helper_url') ?: setting('media_helper_url', '') ?: media_url()), '/');
    $ch = curl_init($base . '/restream' . $endpoint);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . media_secret(), 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => !cfg('media_insecure', false), CURLOPT_SSL_VERIFYHOST => cfg('media_insecure', false) ? 0 : 2]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($r === false) { return [false, 'Could not reach the media server: ' . $err]; }
    $j = json_decode((string) $r, true);
    if ($code >= 400 || !is_array($j)) { return [false, is_array($j) && isset($j['error']) ? $j['error'] : 'Media server error (HTTP ' . $code . ').']; }
    return [true, $j];
}

/** Destinations the host may stream to (keys stay on the server; never sent to browsers). */
function stream_destinations(bool $enabledOnly = true): array {
    return rows('SELECT id, platform, label, rtmp_url, stream_key_enc, watch_url, enabled FROM stream_destinations ' . ($enabledOnly ? 'WHERE enabled = 1 ' : '') . 'ORDER BY id');
}
