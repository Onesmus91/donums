<?php
/* In-app notifications. E-mail notifications are queued via queue_template() in mailer.php. */

function notify_user(int $userId, string $type, string $title, string $body = '', string $url = ''): void {
    if (!$userId) { return; }
    q('INSERT INTO notifications(user_id,type,title,body,url,created_at) VALUES(?,?,?,?,?,?)', [$userId, $type, mb_substr($title, 0, 200), mb_substr($body, 0, 500), $url, now()]);
}
function notify_staff(string $perm, string $type, string $title, string $body = '', string $url = ''): void {
    foreach (rows("SELECT id, is_super, roles FROM users WHERE status='active' AND (is_super=1 OR roles != '')") as $u) {
        $ok = (int) $u['is_super'] === 1;
        if (!$ok) { $map = role_perms(); foreach (explode(',', $u['roles']) as $r) { if (in_array($perm, $map[trim($r)] ?? [], true)) { $ok = true; } } }
        if ($ok) { notify_user((int) $u['id'], $type, $title, $body, $url); }
    }
}
function unread_notifications(): int { return uid() ? (int) val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [uid()]) : 0; }
