<?php
/**
 * Donums front controller. Every page request enters here (see .htaccess).
 */
require __DIR__ . '/app/boot.php';

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'HEAD') { $method = 'GET'; }

// Light background work on normal page loads (cheap, lock-protected): send queued mail.
register_shutdown_function(function () {
    if (!empty($GLOBALS['DN_SKIP_BG']) || (random_int(1, 12) !== 1)) { return; }
    if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
    try { process_outbox(5); } catch (Throwable $e) { app_log('error', 'bg outbox: ' . $e->getMessage()); }
});

require APP . '/routes.php';
dispatch($method, $path);
