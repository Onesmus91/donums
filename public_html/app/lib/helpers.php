<?php
/* =====================================================================
 * General helpers
 * ===================================================================== */

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function now(): string { return date('Y-m-d H:i:s'); }

/** Root-relative URL with optional query string. */
function url(string $path = '/', array $qs = []): string {
    $u = '/' . ltrim($path, '/');
    $qs = array_filter($qs, fn($v) => $v !== null && $v !== '');
    return $qs ? $u . (str_contains($u, '?') ? '&' : '?') . http_build_query($qs) : $u;
}

function base_url(): string {
    $b = (string) cfg('base_url', '');
    if ($b !== '') { return rtrim($b, '/'); }
    $host = $_SERVER['HTTP_HOST'] ?? (string) cfg('domain', 'donums.co.ke');
    if (!preg_match('/^[a-z0-9.\-:\[\]]+$/i', $host)) { $host = (string) cfg('domain', 'donums.co.ke'); }
    return (IS_HTTPS ? 'https://' : 'http://') . $host;
}
function abs_url(string $path = '/', array $qs = []): string { return base_url() . url($path, $qs); }

function redirect(string $to, int $code = 302): void {
    if (!preg_match('#^(https?:)?//#i', $to) || str_starts_with($to, base_url())) {
        // only same-site redirects are allowed
    } else { $to = '/'; }
    header('Location: ' . $to, true, $code);
    exit;
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function wants_json(): bool {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* ---------- request input ---------- */
function post(string $k, $d = '') { return $_POST[$k] ?? $d; }
function clean(string $k, int $max = 255, string $src = 'post'): string {
    $v = $src === 'get' ? ($_GET[$k] ?? '') : ($_POST[$k] ?? '');
    if (is_array($v)) { return ''; }
    return mb_substr(trim(strip_tags((string) $v)), 0, $max);
}
function text(string $k, int $max = 20000): string {   // multi-line, tags stripped
    $v = $_POST[$k] ?? '';
    if (is_array($v)) { return ''; }
    return mb_substr(trim(strip_tags(str_replace("\r\n", "\n", (string) $v))), 0, $max);
}
function qget(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_array($v) ? $d : trim((string) $v); }
function qint(string $k, int $d = 0): int { return isset($_GET[$k]) && is_numeric($_GET[$k]) ? (int) $_GET[$k] : $d; }
function client_ip(): string { return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64); }

/* ---------- CSRF / flash / anti-spam ---------- */
function csrf_token(): string { return $_SESSION['csrf'] ?? ''; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): bool {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/** Hidden honeypot + signed timestamp. Drop into any public form. */
function spam_fields(): string {
    $ts = time();
    $sig = hash_hmac('sha256', (string) $ts, app_key());
    return '<div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
         . '<input type="hidden" name="_ts" value="' . $ts . '.' . substr($sig, 0, 16) . '">';
}
function spam_check(int $minSeconds = 3): bool {
    if (!empty($_POST['website'])) { return false; }
    $p = explode('.', (string) ($_POST['_ts'] ?? ''), 2);
    if (count($p) !== 2 || !ctype_digit($p[0])) { return false; }
    if (!hash_equals(substr(hash_hmac('sha256', $p[0], app_key()), 0, 16), $p[1])) { return false; }
    $age = time() - (int) $p[0];
    return $age >= $minSeconds && $age < 86400 * 2;
}

/** Fixed-window rate limiter. Returns false once the limit is exceeded. */
function rate_limit(string $key, int $max, int $seconds): bool {
    $key = substr(hash('sha256', $key), 0, 40);
    $row = row('SELECT hits, reset_at FROM rate_limits WHERE k = ?', [$key]);
    $t = time();
    if (!$row || $row['reset_at'] < $t) {
        q('INSERT OR REPLACE INTO rate_limits(k,hits,reset_at) VALUES(?,?,?)', [$key, 1, $t + $seconds]);
        return true;
    }
    if ($row['hits'] >= $max) { return false; }
    q('UPDATE rate_limits SET hits = hits + 1 WHERE k = ?', [$key]);
    return true;
}

/* ---------- strings / numbers / dates ---------- */
function slugify(string $s, int $max = 70): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/&/', ' and ', $s);
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s === '' ? 'item' : substr($s, 0, $max);
}
function unique_slug(string $table, string $base, ?int $ignoreId = null, string $col = 'slug'): string {
    $slug = slugify($base); $i = 1; $try = $slug;
    while (val("SELECT COUNT(*) FROM $table WHERE $col = ?" . ($ignoreId ? ' AND id != ' . (int) $ignoreId : ''), [$try])) {
        $try = $slug . '-' . (++$i);
    }
    return $try;
}
function ref_code(string $prefix): string { return $prefix . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); }
function money($n, string $cur = 'KES'): string {
    $n = (float) $n;
    if ($n <= 0) { return 'Free'; }
    return ($cur === 'KES' ? 'KSh ' : $cur . ' ') . number_format($n, $n == floor($n) ? 0 : 2);
}
function human_size(int $b): string {
    foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) { if ($b < 1024 || $i === 3) { return ($i ? round($b, 1) : $b) . ' ' . $u; } $b /= 1024; }
    return '';
}
function ago(?string $dt): string {
    if (!$dt) { return ''; }
    $t = strtotime($dt); if (!$t) { return ''; }
    $d = time() - $t;
    if ($d < 0) { return fmt_dt($dt); }
    if ($d < 60) { return 'just now'; }
    if ($d < 3600) { return floor($d / 60) . ' min ago'; }
    if ($d < 86400) { return floor($d / 3600) . ' h ago'; }
    if ($d < 86400 * 7) { return floor($d / 86400) . ' d ago'; }
    return date('j M Y', $t);
}
function fmt_date(?string $dt): string { return $dt && strtotime($dt) ? date('j M Y', strtotime($dt)) : ''; }
function fmt_dt(?string $dt): string { return $dt && strtotime($dt) ? date('D j M Y, g:i A', strtotime($dt)) . ' EAT' : ''; }
function iso(?string $dt): string { return $dt && strtotime($dt) ? date('Y-m-d\TH:i:s', strtotime($dt)) . '+03:00' : ''; }
function minutes_label(int $m): string {
    if ($m < 60) { return $m . ' min'; }
    $h = intdiv($m, 60); $r = $m % 60;
    return $h . ' h' . ($r ? ' ' . $r . ' min' : '');
}
function excerpt(string $s, int $n = 160): string {
    $s = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}
function initials(string $name): string {
    $p = preg_split('/\s+/', trim($name)); $o = '';
    foreach (array_slice($p, 0, 2) as $w) { $o .= mb_strtoupper(mb_substr($w, 0, 1)); }
    return $o ?: '?';
}
function plural(int $n, string $one, ?string $many = null): string { return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's')); }
function json_dec(?string $s): array { $x = $s ? json_decode($s, true) : []; return is_array($x) ? $x : []; }
function lines(string $s): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', $s)), fn($x) => $x !== '')); }

/* ---------- pagination ---------- */
function paginate(int $total, int $per, int $page): array {
    $pages = max(1, (int) ceil($total / $per));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'per' => $per, 'total' => $total, 'offset' => ($page - 1) * $per];
}
function pager(array $p, string $base, array $qs = []): string {
    if ($p['pages'] <= 1) { return ''; }
    $o = '<nav class="pager" aria-label="Pagination">';
    $link = function (int $n, string $label, bool $on = false) use ($base, $qs) {
        $qs['page'] = $n > 1 ? $n : null;
        return '<a href="' . e(url($base, $qs)) . '"' . ($on ? ' class="on" aria-current="page"' : '') . '>' . $label . '</a>';
    };
    if ($p['page'] > 1) { $o .= $link($p['page'] - 1, '← Previous'); }
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) { $o .= $link($i, (string) $i, $i === $p['page']); }
    if ($p['page'] < $p['pages']) { $o .= $link($p['page'] + 1, 'Next →'); }
    return $o . '</nav>';
}

/* ---------- settings (key/value) ---------- */
function setting(string $key, $default = '') {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (rows('SELECT k, v FROM settings') as $r) { $cache[$r['k']] = $r['v']; } }
    return $cache[$key] ?? $default;
}
function set_setting(string $key, string $value): void {
    q('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$key, $value]);
}

/* ---------- audit trail & activity ---------- */
function audit(string $action, string $entity = '', $id = '', array $meta = []): void {
    q('INSERT INTO audit_log(user_id,action,entity,entity_id,meta,ip,created_at) VALUES(?,?,?,?,?,?,?)',
        [$_SESSION['uid'] ?? null, $action, $entity, (string) $id, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, client_ip(), now()]);
}
function activity(string $entity, $id, string $kind, string $text): void {
    q('INSERT INTO activity(entity,entity_id,user_id,kind,text,created_at) VALUES(?,?,?,?,?,?)', [$entity, (string) $id, $_SESSION['uid'] ?? null, $kind, $text, now()]);
}

/* ---------- Markdown-lite (safe: everything is escaped first) ---------- */
function md(string $t): string {
    $t = str_replace(["\r\n", "\r"], "\n", trim($t));
    if ($t === '') { return ''; }
    $hold = [];
    $t = preg_replace_callback('/```[a-z0-9]*\n(.*?)```/s', function ($m) use (&$hold) {
        $k = "\x02" . count($hold) . "\x03"; $hold[$k] = '<pre><code>' . e(rtrim($m[1])) . '</code></pre>'; return $k;
    }, $t);
    $t = e($t);
    $inline = function (string $s) use (&$hold): string {
        $s = preg_replace_callback('/`([^`\n]+)`/', function ($m) use (&$hold) { $k = "\x02" . count($hold) . "\x03"; $hold[$k] = '<code>' . $m[1] . '</code>'; return $k; }, $s);
        $s = preg_replace_callback('/\[([^\]\n]+)\]\(((?:https?:\/\/|mailto:|\/)[^\s)]+)\)/i', function ($m) use (&$hold) {
            $k = "\x02" . count($hold) . "\x03"; $ext = str_starts_with($m[2], '/') ? '' : ' target="_blank" rel="noopener nofollow ugc"';
            $hold[$k] = '<a href="' . $m[2] . '"' . $ext . '>' . $m[1] . '</a>'; return $k;
        }, $s);
        $s = preg_replace_callback('/(?<![">=\w])(https?:\/\/[^\s<]+[^\s<.,;:!?)\]])/i', function ($m) use (&$hold) {
            $k = "\x02" . count($hold) . "\x03"; $hold[$k] = '<a href="' . $m[1] . '" target="_blank" rel="noopener nofollow ugc">' . $m[1] . '</a>'; return $k;
        }, $s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
        $s = preg_replace('/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/s', '<em>$1</em>', $s);
        return $s;
    };
    $out = ''; $list = null; $para = [];
    $flushPara = function () use (&$out, &$para, $inline) {
        if ($para) { $out .= '<p>' . $inline(implode("<br>\n", $para)) . '</p>'; $para = []; }
    };
    $closeList = function () use (&$out, &$list) { if ($list) { $out .= '</' . $list . '>'; $list = null; } };
    foreach (explode("\n", $t) as $line) {
        $tl = rtrim($line);
        if (trim($tl) === '') { $flushPara(); $closeList(); continue; }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $tl, $m)) { $flushPara(); $closeList(); $n = strlen($m[1]) + 1; $out .= "<h$n>" . $inline($m[2]) . "</h$n>"; continue; }
        if (preg_match('/^(-{3,}|\*{3,})$/', trim($tl))) { $flushPara(); $closeList(); $out .= '<hr>'; continue; }
        if (preg_match('/^&gt;\s?(.*)$/', $tl, $m)) { $flushPara(); $closeList(); $out .= '<blockquote>' . $inline($m[1]) . '</blockquote>'; continue; }
        if (preg_match('/^\s*[-*•]\s+(.+)$/', $tl, $m)) { $flushPara(); if ($list !== 'ul') { $closeList(); $out .= '<ul>'; $list = 'ul'; } $out .= '<li>' . $inline($m[1]) . '</li>'; continue; }
        if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $tl, $m)) { $flushPara(); if ($list !== 'ol') { $closeList(); $out .= '<ol>'; $list = 'ol'; } $out .= '<li>' . $inline($m[1]) . '</li>'; continue; }
        $closeList(); $para[] = $tl;
    }
    $flushPara(); $closeList();
    // restore held fragments (may be nested one level)
    for ($i = 0; $i < 3; $i++) { $out = strtr($out, $hold); }
    return '<div class="md">' . $out . '</div>';
}

/** Embed helper for lesson / promo videos. Returns [kind, src] or null. */
function video_embed(string $url): ?array {
    $url = trim($url);
    if ($url === '') { return null; }
    if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([\w-]{11})~i', $url, $m)) {
        return ['iframe', 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1'];
    }
    if (preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) { return ['iframe', 'https://player.vimeo.com/video/' . $m[1]]; }
    if (preg_match('~^https?://.+\.(mp4|webm|ogg)(\?.*)?$~i', $url) || str_starts_with($url, '/file/')) { return ['video', $url]; }
    return null;
}

/* ---------- icons (Feather-style, inline SVG) ---------- */
function icon(string $n, int $size = 18): string {
    static $p = [
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'book' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'video' => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'video-off' => '<path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.66 0H14a2 2 0 0 1 2 2v3.34l1 1L23 7v10"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'award' => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'play' => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'arrow' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'back' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'send' => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'archive' => '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>',
        'clip' => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'mic' => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
        'mic-off' => '<line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
        'screen' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'hand' => '<path d="M18 11V6a2 2 0 0 0-4 0v1M14 7V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>',
        'chart' => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'thumb' => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
        'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'live' => '<circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>',
        'dots' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'down' => '<polyline points="6 9 12 15 18 9"/>',
        'right' => '<polyline points="9 18 15 12 9 6"/>',
        'alert' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'ok' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'dollar' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="22" x2="9" y2="18"/><line x1="15" y1="22" x2="15" y2="18"/><line x1="8" y1="6" x2="8.01" y2="6"/><line x1="12" y1="6" x2="12.01" y2="6"/><line x1="16" y1="6" x2="16.01" y2="6"/><line x1="8" y1="10" x2="8.01" y2="10"/><line x1="12" y1="10" x2="12.01" y2="10"/><line x1="16" y1="10" x2="16.01" y2="10"/><line x1="8" y1="14" x2="8.01" y2="14"/><line x1="12" y1="14" x2="12.01" y2="14"/><line x1="16" y1="14" x2="16.01" y2="14"/>',
        'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19 5a9 9 0 0 1 0 14"/>',
        'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
    ];
    $d = $p[$n] ?? $p['dots'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
