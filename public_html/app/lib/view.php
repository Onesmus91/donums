<?php
/* =====================================================================
 * Views: render($template, $vars, $layout)
 * Templates live in app/views/*.php and receive $vars as local variables.
 * ===================================================================== */

function render(string $tpl, array $vars = [], string $layout = 'site'): void {
    $file = APP . '/views/' . $tpl . '.php';
    if (!is_file($file)) { throw new RuntimeException('Missing view: ' . $tpl); }
    $vars += ['title' => cfg('site_name'), 'desc' => '', 'active' => '', 'schema' => null, 'og_image' => '', 'canonical' => '', 'noindex' => false, 'body_class' => '', 'head_extra' => ''];
    extract($vars, EXTR_SKIP);
    ob_start();
    require $file;
    $content = ob_get_clean();
    if ($layout === '') { echo $content; return; }
    require APP . '/views/layouts/' . $layout . '.php';
}

/** Render a partial and return it as a string. */
function partial(string $tpl, array $vars = []): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP . '/views/' . $tpl . '.php';
    return ob_get_clean();
}

function not_found(string $msg = ''): void {
    http_response_code(404);
    render('errors/404', ['title' => 'Page not found', 'msg' => $msg, 'noindex' => true], 'site');
    exit;
}

function flash_html(): string {
    $o = '';
    foreach (flashes() as [$type, $msg]) {
        $cls = ['ok' => 'ok', 'error' => 'err', 'info' => 'info', 'warn' => 'warn'][$type] ?? 'info';
        $o .= '<div class="flash ' . $cls . '" role="status">' . icon($cls === 'ok' ? 'ok' : 'alert', 18) . '<span>' . e($msg) . '</span></div>';
    }
    return $o;
}

/** Small UI pieces reused across modules. */
function badge(string $text, string $tone = ''): string { return '<span class="badge ' . e($tone) . '">' . e($text) . '</span>'; }
function stars(float $r, int $size = 14): string {
    $o = '<span class="stars" aria-label="' . round($r, 1) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $f = $r >= $i ? 'full' : ($r >= $i - .5 ? 'half' : 'empty');
        $o .= '<span class="st ' . $f . '">' . icon('star', $size) . '</span>';
    }
    return $o . '</span>';
}
function empty_state(string $icon, string $title, string $text = '', string $cta = ''): string {
    return '<div class="empty">' . icon($icon, 28) . '<h3>' . e($title) . '</h3>' . ($text ? '<p>' . e($text) . '</p>' : '') . $cta . '</div>';
}
function avatar_for(?int $uid, int $size = 36): string {
    static $c = [];
    if (!$uid) { return '<span class="avatar" style="width:' . $size . 'px;height:' . $size . 'px">?</span>'; }
    $c[$uid] = $c[$uid] ?? row('SELECT id,name,email,avatar FROM users WHERE id=?', [$uid]);
    return $c[$uid] ? avatar_html($c[$uid], $size) : '<span class="avatar">?</span>';
}
function user_name(?int $uid): string {
    static $c = [];
    if (!$uid) { return 'Unknown'; }
    $c[$uid] = $c[$uid] ?? (val('SELECT name FROM users WHERE id=?', [$uid]) ?: 'Former member');
    return $c[$uid];
}

/** Public image URL: prefer an optimised .webp sibling when one exists. */
function img_url(?string $path): string {
    if (!$path) { return ''; }
    if (preg_match('~^https?://~i', $path)) { return $path; }
    if (preg_match('~^/assets/uploads/(.+)\.(png|jpe?g)$~i', $path, $m) && is_file(ROOT . '/assets/uploads/' . $m[1] . '.webp')) {
        return '/assets/uploads/' . $m[1] . '.webp';
    }
    return $path;
}
