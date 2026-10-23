<?php
/* Tiny router: route('GET', '/learning/{slug}', 'handler_fn', ['csrf' => false]) */
function route(string $method, string $pattern, string $handler, array $opt = []): void {
    $GLOBALS['ROUTES'][] = [$method, $pattern, $handler, $opt];
}
function dispatch(string $method, string $path): void {
    foreach ($GLOBALS['ROUTES'] as [$m, $pat, $handler, $opt]) {
        if ($m !== $method) { continue; }
        $re = '#^' . preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', fn($x) => '(?P<' . $x[1] . '>' . ($x[2] ?? '[^/]+') . ')', $pat) . '/?$#';
        if (!preg_match($re, $path, $mm)) { continue; }
        $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
        if ($method === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !str_starts_with($path, '/api/')) {
            $lim = ini_get('post_max_size');
            flash('error', 'That upload is larger than this server allows (' . $lim . '). Please choose a smaller file — photos are resized automatically in most browsers, but very large files may need to be reduced first.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
        if ($method === 'POST' && ($opt['csrf'] ?? true) && !csrf_check()) {
            if (wants_json()) { json_out(['ok' => false, 'error' => 'Your session expired. Please reload the page.'], 419); }
            flash('error', 'Your session expired or the form was invalid. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
        if (!empty($opt['perm'])) { require_perm($opt['perm']); }
        if (!empty($opt['login'])) { require_login(); }
        if (!function_exists($handler)) { throw new RuntimeException("Missing handler $handler"); }
        $handler($params);
        return;
    }
    not_found();
}
