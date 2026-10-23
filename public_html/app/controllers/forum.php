<?php
/* =====================================================================
 * Donums Community — categories, threads, replies, voting, accepted
 * answers, reporting, subscriptions, moderation queue and member profiles.
 * ===================================================================== */

function thread_url(array $t): string { return '/forum/t/' . $t['id'] . '-' . ($t['slug'] ?: 'discussion'); }
function is_mod(): bool { return can('forum.moderate'); }

/** Returns an error string if the current user may not post, else null. */
function forum_post_block(): ?string {
    $u = user();
    if (!$u) { return 'Sign in to join the conversation.'; }
    if (!$u['email_verified_at'] && !is_staff()) { return 'Please confirm your email address before posting. Check your inbox, or resend the link from your account.'; }
    if ((int) val("SELECT COUNT(*) FROM forum_reports WHERE target='ban' AND target_id=? AND status='open'", [$u['id']])) { return 'Posting is currently disabled for your account.'; }
    return null;
}
function forum_needs_review(): bool {
    $u = user();
    return setting('forum_moderate_new', '1') === '1' && !is_staff() && !(int) $u['forum_trusted'];
}

function page_forum(): void {
    $tab = qget('tab', 'latest'); $page = max(1, qint('page', 1));
    $cats = rows("SELECT c.*, (SELECT COUNT(*) FROM forum_threads t WHERE t.category_id=c.id AND t.status='published') threads,
        (SELECT COUNT(*) FROM forum_posts p JOIN forum_threads t ON t.id=p.thread_id WHERE t.category_id=c.id AND p.status='published') posts FROM forum_categories c " . (is_staff() ? '' : 'WHERE c.staff_only = 0 OR c.slug = \'announcements\'') . " ORDER BY c.pos");
    $order = ['latest' => 't.is_pinned DESC, t.last_post_at DESC', 'top' => 't.score DESC, t.reply_count DESC', 'unanswered' => 't.created_at DESC'][$tab] ?? 't.is_pinned DESC, t.last_post_at DESC';
    $where = "t.status = 'published'" . ($tab === 'unanswered' ? ' AND t.reply_count = 0' : '');
    $pg = paginate((int) val("SELECT COUNT(*) FROM forum_threads t WHERE $where"), 15, $page);
    $threads = thread_rows("WHERE $where ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}");
    $stats = ['members' => (int) val('SELECT COUNT(*) FROM users'), 'threads' => (int) val("SELECT COUNT(*) FROM forum_threads WHERE status='published'"), 'posts' => (int) val("SELECT COUNT(*) FROM forum_posts WHERE status='published'")];
    $top = rows("SELECT u.id, u.name, u.reputation, u.avatar, u.email FROM users u WHERE u.reputation > 0 ORDER BY u.reputation DESC LIMIT 5");
    render('forum/index', compact('cats', 'threads', 'pg', 'tab', 'stats', 'top') + ['title' => 'Donums Community — discussions', 'desc' => 'Ask questions, share experience and learn from professionals and entrepreneurs.', 'active' => 'forum']);
}

function thread_rows(string $tail, array $params = []): array {
    return rows("SELECT t.*, c.name category_name, c.slug category_slug, u.name author_name, u.email author_email, u.avatar author_avatar, lu.name last_name
        FROM forum_threads t JOIN forum_categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id LEFT JOIN users lu ON lu.id=t.last_user_id $tail", $params);
}

function page_forum_category(array $p): void {
    $c = row('SELECT * FROM forum_categories WHERE slug = ?', [$p['slug']]); if (!$c) { not_found(); }
    $page = max(1, qint('page', 1));
    $pg = paginate((int) val("SELECT COUNT(*) FROM forum_threads WHERE category_id=? AND status='published'", [$c['id']]), 20, $page);
    $threads = thread_rows("WHERE t.category_id = ? AND t.status='published' ORDER BY t.is_pinned DESC, t.last_post_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$c['id']]);
    render('forum/category', compact('c', 'threads', 'pg') + ['title' => $c['name'] . ' · Community', 'desc' => $c['description'], 'active' => 'forum']);
}

function page_forum_search(): void {
    $s = qget('q'); $threads = [];
    if (mb_strlen($s) >= 2) {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
        $threads = thread_rows("WHERE t.status='published' AND (t.title LIKE ? ESCAPE '\\' OR t.body LIKE ? ESCAPE '\\' OR t.id IN (SELECT thread_id FROM forum_posts WHERE status='published' AND body LIKE ? ESCAPE '\\')) ORDER BY t.last_post_at DESC LIMIT 40", [$like, $like, $like]);
    }
    render('forum/search', compact('s', 'threads') + ['title' => 'Search the community', 'noindex' => true, 'active' => 'forum']);
}

function page_thread(array $p): void {
    $t = row("SELECT t.*, c.name category_name, c.slug category_slug, u.name author_name, u.headline author_headline, u.reputation author_rep FROM forum_threads t JOIN forum_categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id WHERE t.id = ?", [(int) $p['id']]);
    if (!$t || ($t['status'] !== 'published' && !is_mod() && (int) $t['user_id'] !== uid())) { not_found('This discussion is not available.'); }
    q('UPDATE forum_threads SET views = views + 1 WHERE id = ?', [$t['id']]);
    $vis = is_mod() ? "p.status != 'deleted'" : "(p.status = 'published' OR p.user_id = " . (int) uid() . ")";
    $posts = rows("SELECT p.*, u.name, u.headline, u.reputation, u.avatar, u.email, u.roles, u.is_super FROM forum_posts p JOIN users u ON u.id=p.user_id WHERE p.thread_id = ? AND $vis ORDER BY p.created_at, p.id", [$t['id']]);
    $myVotes = []; if (uid()) { foreach (rows('SELECT target, target_id, value FROM forum_votes WHERE user_id = ?', [uid()]) as $v) { $myVotes[$v['target'] . $v['target_id']] = (int) $v['value']; } }
    $subscribed = uid() ? (bool) val('SELECT 1 FROM forum_subs WHERE user_id=? AND thread_id=?', [uid(), $t['id']]) : false;
    $course = $t['course_id'] ? row('SELECT title, slug FROM courses WHERE id = ?', [$t['course_id']]) : null;
    $schema = ['@context' => 'https://schema.org', '@type' => 'DiscussionForumPosting', 'headline' => $t['title'], 'datePublished' => date('c', strtotime($t['created_at'])), 'author' => ['@type' => 'Person', 'name' => $t['author_name']], 'commentCount' => (int) $t['reply_count']];
    render('forum/thread', compact('t', 'posts', 'myVotes', 'subscribed', 'course') + ['title' => $t['title'], 'desc' => excerpt($t['body'], 160), 'active' => 'forum', 'schema' => $schema]);
}

function page_forum_new(): void {
    require_login('/forum/new');
    $cats = rows('SELECT * FROM forum_categories WHERE staff_only = 0 ORDER BY pos');
    if (is_staff()) { $cats = rows('SELECT * FROM forum_categories ORDER BY pos'); }
    render('forum/new', ['cats' => $cats, 'sel' => qget('cat'), 'course' => qint('course'), 'block' => forum_post_block(), 'title' => 'Start a discussion', 'noindex' => true, 'active' => 'forum']);
}

function post_forum_new(): void {
    $u = require_login();
    if ($b = forum_post_block()) { flash('error', $b); redirect('/forum/new'); }
    if (!spam_check(4)) { flash('error', 'We could not verify that submission. Please try again.'); redirect('/forum/new'); }
    if (!rate_limit('thread-' . $u['id'], 5, 3600)) { flash('error', 'You have started several discussions recently. Please wait a little before posting again.'); redirect('/forum/new'); }
    $cat = row('SELECT * FROM forum_categories WHERE id = ?', [(int) post('category_id')]);
    $title = clean('title', 160); $body = text('body', 10000);
    if (!$cat || ($cat['staff_only'] && !is_staff())) { flash('error', 'Choose a category.'); redirect('/forum/new'); }
    if (mb_strlen($title) < 8 || mb_strlen($body) < 20) { flash('error', 'Give your discussion a clear title (at least 8 characters) and enough detail (at least 20 characters).'); redirect('/forum/new'); }
    if (preg_match_all('~https?://~i', $body) > 3 && !is_staff()) { flash('error', 'Please limit links to three per post.'); redirect('/forum/new'); }
    $review = forum_needs_review();
    $tags = implode(',', array_slice(array_filter(array_map(fn($x) => slugify($x, 24), explode(',', clean('tags', 120)))), 0, 5));
    $course = (int) post('course_id') ?: null;
    $id = insert('forum_threads', ['category_id' => $cat['id'], 'user_id' => $u['id'], 'title' => $title, 'slug' => slugify($title), 'body' => $body, 'tags' => $tags, 'status' => $review ? 'pending' : 'published', 'course_id' => $course, 'last_post_at' => now(), 'last_user_id' => $u['id'], 'created_at' => now()]);
    q('INSERT OR IGNORE INTO forum_subs(user_id,thread_id) VALUES(?,?)', [$u['id'], $id]);
    if ($review) { notify_staff('forum.moderate', 'forum', 'Discussion awaiting approval', $title, '/admin/forum'); flash('info', 'Thank you — your first discussion is being reviewed by a moderator and will appear shortly. Later posts are published instantly.'); redirect('/forum'); }
    redirect('/forum/t/' . $id . '-' . slugify($title));
}

function post_forum_reply(array $p): void {
    $u = require_login();
    $t = row('SELECT * FROM forum_threads WHERE id = ?', [(int) $p['id']]); if (!$t || $t['status'] !== 'published') { not_found(); }
    $back = thread_url($t);
    if ($t['is_locked'] && !is_mod()) { flash('error', 'This discussion is locked.'); redirect($back); }
    if ($b = forum_post_block()) { flash('error', $b); redirect($back); }
    if (!spam_check(3)) { flash('error', 'We could not verify that submission. Please try again.'); redirect($back); }
    if (!rate_limit('reply-' . $u['id'], 25, 3600)) { flash('error', 'You are posting very quickly. Please slow down for a few minutes.'); redirect($back); }
    $body = text('body', 8000);
    if (mb_strlen($body) < 2) { flash('error', 'Write a reply before posting.'); redirect($back); }
    if (preg_match_all('~https?://~i', $body) > 3 && !is_staff()) { flash('error', 'Please limit links to three per post.'); redirect($back); }
    $parent = (int) post('parent_id') ?: null;
    if ($parent) { $pp = row('SELECT parent_id FROM forum_posts WHERE id = ? AND thread_id = ?', [$parent, $t['id']]); if (!$pp) { $parent = null; } elseif ($pp['parent_id']) { $parent = (int) $pp['parent_id']; } }
    $review = forum_needs_review();
    $id = insert('forum_posts', ['thread_id' => $t['id'], 'user_id' => $u['id'], 'parent_id' => $parent, 'body' => $body, 'status' => $review ? 'pending' : 'published', 'created_at' => now()]);
    q('INSERT OR IGNORE INTO forum_subs(user_id,thread_id) VALUES(?,?)', [$u['id'], $t['id']]);
    if ($review) { notify_staff('forum.moderate', 'forum', 'Reply awaiting approval', excerpt($body, 80), '/admin/forum'); flash('info', 'Your reply is awaiting moderator approval. Future replies will be published immediately.'); redirect($back); }
    forum_after_publish_post($t, $id, $u, $body);
    redirect($back . '#post-' . $id);
}

function forum_after_publish_post(array $t, int $postId, array $author, string $body): void {
    q('UPDATE forum_threads SET reply_count = (SELECT COUNT(*) FROM forum_posts WHERE thread_id = ? AND status = \'published\'), last_post_at = ?, last_user_id = ? WHERE id = ?', [$t['id'], now(), $author['id'], $t['id']]);
    foreach (rows('SELECT u.id, u.email, u.name, u.notify_email FROM forum_subs s JOIN users u ON u.id = s.user_id WHERE s.thread_id = ? AND u.id != ?', [$t['id'], $author['id']]) as $s) {
        notify_user((int) $s['id'], 'reply', $author['name'] . ' replied in “' . $t['title'] . '”', excerpt($body, 140), '/forum/t/' . $t['id'] . '-' . $t['slug'] . '#post-' . $postId);
        if ($s['notify_email'] && rate_limit('fmail-' . $s['id'] . '-' . $t['id'], 1, 3600)) {
            queue_template('forum_reply', $s['email'], ['name' => $s['name'], 'thread' => $t['title'], 'author' => $author['name'], 'snippet' => excerpt($body, 220), 'cta_url' => abs_url('/forum/t/' . $t['id'] . '-' . $t['slug'] . '#post-' . $postId), 'cta_label' => 'View the reply'], ['dept' => 'Community']);
        }
    }
}

function post_forum_vote(): void {
    $u = require_login();
    $target = post('target') === 'thread' ? 'thread' : 'post'; $tid = (int) post('id');
    $row = $target === 'thread' ? row('SELECT user_id FROM forum_threads WHERE id = ?', [$tid]) : row('SELECT user_id FROM forum_posts WHERE id = ?', [$tid]);
    if (!$row) { json_out(['ok' => false], 404); }
    if ((int) $row['user_id'] === (int) $u['id']) { json_out(['ok' => false, 'error' => 'You cannot vote on your own post.'], 422); }
    $cur = (int) val('SELECT value FROM forum_votes WHERE user_id=? AND target=? AND target_id=?', [$u['id'], $target, $tid]);
    if ($cur === 1) { q('DELETE FROM forum_votes WHERE user_id=? AND target=? AND target_id=?', [$u['id'], $target, $tid]); $delta = -1; $mine = 0; }
    else { q('INSERT OR REPLACE INTO forum_votes(user_id,target,target_id,value) VALUES(?,?,?,1)', [$u['id'], $target, $tid]); $delta = 1; $mine = 1; }
    $tbl = $target === 'thread' ? 'forum_threads' : 'forum_posts';
    q("UPDATE $tbl SET score = score + ? WHERE id = ?", [$delta, $tid]);
    q('UPDATE users SET reputation = MAX(0, reputation + ?) WHERE id = ?', [$delta * 2, $row['user_id']]);
    json_out(['ok' => true, 'score' => (int) val("SELECT score FROM $tbl WHERE id = ?", [$tid]), 'mine' => $mine]);
}

function post_forum_action(array $p): void {
    $u = require_login(); $t = row('SELECT * FROM forum_threads WHERE id = ?', [(int) $p['id']]); if (!$t) { not_found(); }
    $act = post('action'); $pid = (int) post('post_id'); $back = thread_url($t);
    $isAuthor = (int) $t['user_id'] === (int) $u['id'];
    switch ($act) {
        case 'solve':
            if (!$isAuthor && !is_mod()) { break; }
            $same = (int) $t['solved_post_id'] === $pid;
            if ($same) { q('UPDATE forum_threads SET solved_post_id = NULL WHERE id = ?', [$t['id']]); }
            else { q('UPDATE forum_threads SET solved_post_id = ? WHERE id = ?', [$pid, $t['id']]); $au = (int) val('SELECT user_id FROM forum_posts WHERE id = ?', [$pid]); if ($au && $au !== (int) $u['id']) { q('UPDATE users SET reputation = reputation + 15 WHERE id = ?', [$au]); notify_user($au, 'answer', 'Your reply was marked as the answer', $t['title'], $back . '#post-' . $pid); } }
            break;
        case 'subscribe':
            if (val('SELECT 1 FROM forum_subs WHERE user_id=? AND thread_id=?', [$u['id'], $t['id']])) { q('DELETE FROM forum_subs WHERE user_id=? AND thread_id=?', [$u['id'], $t['id']]); flash('ok', 'You will no longer receive updates for this discussion.'); }
            else { q('INSERT OR IGNORE INTO forum_subs(user_id,thread_id) VALUES(?,?)', [$u['id'], $t['id']]); flash('ok', 'You are now following this discussion.'); }
            break;
        case 'report':
            $target = $pid ? 'post' : 'thread'; $tid = $pid ?: (int) $t['id'];
            if (!val("SELECT 1 FROM forum_reports WHERE user_id=? AND target=? AND target_id=? AND status='open'", [$u['id'], $target, $tid])) {
                insert('forum_reports', ['target' => $target, 'target_id' => $tid, 'user_id' => $u['id'], 'reason' => clean('reason', 300) ?: 'Reported by a member', 'created_at' => now()]);
                notify_staff('forum.moderate', 'report', 'Content reported', $t['title'], '/admin/forum');
            }
            flash('ok', 'Thank you. A moderator will review this.'); break;
        case 'edit':
            $body = text('body', 8000); if (mb_strlen($body) < 2) { break; }
            if ($pid) { $owner = (int) val('SELECT user_id FROM forum_posts WHERE id = ?', [$pid]); if ($owner === (int) $u['id'] || is_mod()) { q('UPDATE forum_posts SET body = ?, edited_at = ? WHERE id = ?', [$body, now(), $pid]); } }
            elseif ($isAuthor || is_mod()) { q('UPDATE forum_threads SET body = ?, edited_at = ? WHERE id = ?', [$body, now(), $t['id']]); }
            break;
        case 'delete':
            if ($pid) { $owner = (int) val('SELECT user_id FROM forum_posts WHERE id = ?', [$pid]); if ($owner === (int) $u['id'] || is_mod()) { q("UPDATE forum_posts SET status='deleted' WHERE id = ?", [$pid]); q("UPDATE forum_threads SET reply_count = (SELECT COUNT(*) FROM forum_posts WHERE thread_id=? AND status='published') WHERE id=?", [$t['id'], $t['id']]); } }
            elseif ($isAuthor || is_mod()) { q("UPDATE forum_threads SET status='deleted' WHERE id = ?", [$t['id']]); flash('ok', 'Discussion removed.'); redirect('/forum'); }
            break;
        case 'pin': case 'lock': case 'hide':
            if (!is_mod()) { break; }
            if ($act === 'pin') { q('UPDATE forum_threads SET is_pinned = 1 - is_pinned WHERE id = ?', [$t['id']]); }
            if ($act === 'lock') { q('UPDATE forum_threads SET is_locked = 1 - is_locked WHERE id = ?', [$t['id']]); }
            if ($act === 'hide') { q("UPDATE forum_threads SET status = CASE status WHEN 'hidden' THEN 'published' ELSE 'hidden' END WHERE id = ?", [$t['id']]); }
            audit('forum.' . $act, 'thread', $t['id']); break;
    }
    redirect($back . ($pid ? '#post-' . $pid : ''));
}

function page_forum_user(array $p): void {
    $m = row('SELECT id,name,headline,bio,location,avatar,email,reputation,created_at,roles,is_super FROM users WHERE id = ? AND status = \'active\'', [(int) $p['id']]); if (!$m) { not_found(); }
    $threads = thread_rows("WHERE t.user_id = ? AND t.status='published' ORDER BY t.created_at DESC LIMIT 15", [$m['id']]);
    $postCount = (int) val("SELECT COUNT(*) FROM forum_posts WHERE user_id = ? AND status='published'", [$m['id']]);
    $answers = (int) val('SELECT COUNT(*) FROM forum_threads t JOIN forum_posts p ON p.id=t.solved_post_id WHERE p.user_id = ?', [$m['id']]);
    render('forum/user', compact('m', 'threads', 'postCount', 'answers') + ['title' => $m['name'] . ' · Community', 'active' => 'forum']);
}
