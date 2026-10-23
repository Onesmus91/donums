<?php
/* Admin → Community: moderation queue, reports, categories. */

function admin_routes_forum(): void {
    route('GET', '/admin/forum', 'admin_forum');
    route('POST', '/admin/forum/moderate', 'admin_forum_moderate');
    route('POST', '/admin/forum/category', 'admin_forum_category');
}
function admin_forum(): void {
    admin_guard('forum.moderate'); $tab = qget('tab', 'queue');
    $pendingThreads = rows("SELECT t.*, u.name author, c.name cat FROM forum_threads t JOIN users u ON u.id=t.user_id JOIN forum_categories c ON c.id=t.category_id WHERE t.status = 'pending' ORDER BY t.id");
    $pendingPosts = rows("SELECT p.*, u.name author, t.title thread_title, t.id tid FROM forum_posts p JOIN users u ON u.id=p.user_id JOIN forum_threads t ON t.id=p.thread_id WHERE p.status = 'pending' ORDER BY p.id");
    $reports = rows("SELECT r.*, u.name reporter FROM forum_reports r LEFT JOIN users u ON u.id = r.user_id WHERE r.status = 'open' ORDER BY r.id");
    foreach ($reports as &$r) {
        if ($r['target'] === 'post') { $x = row('SELECT p.body, p.user_id, p.thread_id, u.name author FROM forum_posts p JOIN users u ON u.id=p.user_id WHERE p.id = ?', [$r['target_id']]); }
        else { $x = row('SELECT t.body, t.user_id, t.id thread_id, u.name author, t.title FROM forum_threads t JOIN users u ON u.id=t.user_id WHERE t.id = ?', [$r['target_id']]); }
        $r['content'] = $x; } unset($r);
    $cats = rows('SELECT c.*, (SELECT COUNT(*) FROM forum_threads t WHERE t.category_id = c.id) n FROM forum_categories c ORDER BY pos');
    $recent = rows("SELECT t.id, t.title, t.slug, t.status, t.reply_count, u.name author FROM forum_threads t JOIN users u ON u.id = t.user_id ORDER BY t.id DESC LIMIT 15");
    admin_render('admin/forum', compact('tab', 'pendingThreads', 'pendingPosts', 'reports', 'cats', 'recent') + ['title' => 'Community', 'section' => 'Community moderation']);
}
function admin_forum_moderate(): void {
    admin_guard('forum.moderate'); $act = post('action'); $id = (int) post('id'); $kind = post('kind');
    if ($kind === 'thread' && ($t = row('SELECT * FROM forum_threads WHERE id = ?', [$id]))) {
        if ($act === 'approve') { q("UPDATE forum_threads SET status = 'published', last_post_at = ? WHERE id = ?", [now(), $id]); q('UPDATE users SET forum_trusted = 1 WHERE id = ?', [$t['user_id']]); notify_user((int) $t['user_id'], 'forum', 'Your discussion was approved', $t['title'], '/forum/t/' . $id . '-' . $t['slug']); }
        if ($act === 'reject') { q("UPDATE forum_threads SET status = 'deleted' WHERE id = ?", [$id]); }
    }
    if ($kind === 'post' && ($p = row('SELECT * FROM forum_posts WHERE id = ?', [$id]))) {
        $t = row('SELECT * FROM forum_threads WHERE id = ?', [$p['thread_id']]);
        if ($act === 'approve') { q("UPDATE forum_posts SET status = 'published' WHERE id = ?", [$id]); q('UPDATE users SET forum_trusted = 1 WHERE id = ?', [$p['user_id']]); $au = row('SELECT * FROM users WHERE id = ?', [$p['user_id']]); if ($t && $au) { forum_after_publish_post($t, $id, $au, $p['body']); } }
        if ($act === 'reject') { q("UPDATE forum_posts SET status = 'deleted' WHERE id = ?", [$id]); }
    }
    if ($act === 'dismiss') { q("UPDATE forum_reports SET status = 'dismissed', handled_by = ? WHERE id = ?", [uid(), $id]); }
    if ($act === 'remove') { $r = row('SELECT * FROM forum_reports WHERE id = ?', [$id]); if ($r) { if ($r['target'] === 'post') { q("UPDATE forum_posts SET status = 'deleted' WHERE id = ?", [$r['target_id']]); } else { q("UPDATE forum_threads SET status = 'hidden' WHERE id = ?", [$r['target_id']]); } q("UPDATE forum_reports SET status = 'actioned', handled_by = ? WHERE id = ?", [uid(), $id]); } }
    if ($act === 'suspend_author' && is_super()) { $uidx = (int) post('user_id'); if ($uidx && !val('SELECT is_super FROM users WHERE id = ?', [$uidx])) { q("UPDATE users SET status = 'suspended' WHERE id = ?", [$uidx]); } }
    audit('forum.' . $act, $kind ?: 'report', $id); flash('ok', 'Done.'); redirect('/admin/forum?tab=' . ($kind ? 'queue' : 'reports'));
}
function admin_forum_category(): void {
    admin_guard('forum.moderate'); if (!is_super()) { flash('error', 'Only the Super Admin can change categories.'); redirect('/admin/forum?tab=categories'); }
    $act = post('action'); $id = (int) post('id');
    $d = ['name' => clean('name', 80), 'description' => clean('description', 240), 'icon' => in_array(post('icon'), ['chat', 'megaphone', 'briefcase', 'building', 'monitor', 'book', 'alert', 'users', 'zap', 'globe'], true) ? post('icon') : 'chat', 'staff_only' => post('staff_only') ? 1 : 0, 'pos' => (int) post('pos', 50)];
    if ($act === 'save' && $d['name'] !== '') { if ($id) { update('forum_categories', $id, $d); } else { $d['slug'] = unique_slug('forum_categories', $d['name']); insert('forum_categories', $d); } flash('ok', 'Category saved.'); }
    if ($act === 'delete' && $id) { if ((int) val('SELECT COUNT(*) FROM forum_threads WHERE category_id = ?', [$id])) { flash('error', 'Move or delete the discussions in this category first.'); } else { q('DELETE FROM forum_categories WHERE id = ?', [$id]); flash('ok', 'Category deleted.'); } }
    redirect('/admin/forum?tab=categories');
}
