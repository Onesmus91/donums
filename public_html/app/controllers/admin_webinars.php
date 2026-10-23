<?php
/* Admin → Live: instant and scheduled webinars, registrants, attendance, social destinations. */

function admin_routes_webinars(): void {
    route('GET', '/admin/webinars', 'admin_webinars');
    route('POST', '/admin/webinars/instant', 'admin_webinar_instant');
    route('GET', '/admin/webinars/new', 'admin_webinar_form');
    route('GET', '/admin/webinars/streams', 'admin_webinar_streams');
    route('POST', '/admin/webinars/streams', 'admin_webinar_streams_save');
    route('POST', '/admin/webinars/save', 'admin_webinar_save');
    route('GET', '/admin/webinars/{id:\d+}', 'admin_webinar');
    route('GET', '/admin/webinars/{id:\d+}/edit', 'admin_webinar_form');
    route('POST', '/admin/webinars/{id:\d+}/regs', 'admin_webinar_regs_action');
    route('POST', '/admin/webinars/{id:\d+}/delete', 'admin_webinar_delete');
}

function webinar_guard(?int $id = null): ?array {
    admin_guard('webinars.manage', 'webinars.own');
    if (!$id) { return null; }
    $w = row('SELECT * FROM webinars WHERE id = ?', [$id]); if (!$w) { not_found(); }
    if (!is_super() && (int) $w['host_id'] !== uid()) { http_response_code(403); render('errors/403', ['title' => 'Access restricted'], 'site'); exit; }
    return $w;
}

function admin_webinars(): void {
    admin_guard('webinars.manage', 'webinars.own');
    $own = is_super() ? '' : ' AND w.host_id = ' . (int) uid();
    $items = rows("SELECT w.*, (SELECT COUNT(*) FROM webinar_regs r WHERE r.webinar_id = w.id AND r.status IN ('registered','pending_payment') AND r.role != 'host') regs, (SELECT COUNT(*) FROM webinar_regs r WHERE r.webinar_id = w.id AND r.attended = 1 AND r.role != 'host') att FROM webinars w WHERE 1=1 $own ORDER BY CASE w.status WHEN 'live' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END, w.start_at DESC");
    admin_render('admin/webinars', compact('items') + ['title' => 'Live', 'section' => 'Donums Live']);
}

function admin_webinar_instant(): void {
    admin_guard('webinars.manage', 'webinars.own');
    $title = clean('title', 140) ?: 'Instant session — ' . date('j M, g:i A');
    $slug = unique_slug('webinars', $title, null);
    $id = insert('webinars', ['slug' => $slug, 'title' => $title, 'summary' => '', 'kind' => 'meeting', 'start_at' => now(), 'duration_min' => 60, 'host_id' => uid(), 'host_name' => user()['name'], 'capacity' => 100, 'access' => post('access') === 'registered' ? 'registered' : 'public', 'engine' => (post('engine') === 'broadcast' && media_enabled()) ? 'broadcast' : 'builtin', 'status' => 'live', 'started_at' => now(), 'room_key' => bin2hex(random_bytes(8)), 'created_at' => now(), 'updated_at' => now()]);
    audit('webinar.instant', 'webinar', $id);
    redirect('/webinars/' . $slug . '/room');
}

function admin_webinar_form(array $p = []): void {
    $w = !empty($p['id']) ? webinar_guard((int) $p['id']) : webinar_guard(null);
    $hosts = is_super() ? rows("SELECT id, name FROM users WHERE is_super = 1 OR roles LIKE '%host%' ORDER BY name") : [];
    $speakers = $w ? implode("\n", array_map(fn($s) => ($s['name'] ?? '') . ' | ' . ($s['title'] ?? ''), json_dec($w['speakers']))) : '';
    admin_render('admin/webinar_form', compact('w', 'hosts', 'speakers') + ['title' => 'Session', 'section' => $w ? 'Edit session' : 'Schedule a session']);
}
function admin_webinar_save(): void {
    $id = (int) post('id'); $old = webinar_guard($id ?: null);
    $title = clean('title', 140); $t = strtotime((string) post('start_at'));
    if ($title === '' || !$t) { flash('error', 'A title and a start date/time are required.'); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/webinars'); }
    $cover = save_public_image('cover', 'webinar'); if ($cover !== '' && $cover[0] === '!') { flash('error', substr($cover, 1)); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/webinars'); }
    $speakers = []; foreach (lines((string) post('speakers')) as $l) { $x = explode('|', $l, 2); $speakers[] = ['name' => trim($x[0]), 'title' => trim($x[1] ?? '')]; }
    $status = in_array(post('status'), ['draft', 'scheduled', 'cancelled', 'ended'], true) ? post('status') : 'draft';
    if ($old && $old['status'] === 'live' && $status !== 'ended') { $status = 'live'; }
    $d = ['title' => $title, 'summary' => clean('summary', 300), 'description' => text('description', 10000), 'agenda' => text('agenda', 4000), 'kind' => in_array(post('kind'), ['webinar', 'workshop', 'meeting'], true) ? post('kind') : 'webinar', 'start_at' => date('Y-m-d H:i:s', $t),
        'duration_min' => max(10, min(600, (int) post('duration_min', 60))), 'capacity' => max(2, min(500, (int) post('capacity', 100))), 'price' => max(0, (float) post('price', 0)), 'access' => post('access') === 'public' ? 'public' : 'registered', 'waiting_room' => post('waiting_room') ? 1 : 0, 'engine' => post('engine') === 'broadcast' ? 'broadcast' : 'builtin',
        'chat_enabled' => post('chat_enabled') ? 1 : 0, 'qa_enabled' => post('qa_enabled') ? 1 : 0, 'status' => $status, 'recording_url' => clean('recording_url', 300), 'replay_public' => post('replay_public') ? 1 : 0, 'speakers' => json_encode($speakers, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
    if (is_super()) { $hid = (int) post('host_id') ?: uid(); $d['host_id'] = $hid; $d['host_name'] = clean('host_name', 100) ?: user_name($hid); } elseif (!$old) { $d['host_id'] = uid(); $d['host_name'] = user()['name']; }
    if ($cover) { $d['cover'] = $cover; }
    if ($old) { update('webinars', $id, $d); } else { $d['slug'] = unique_slug('webinars', $title); $d['room_key'] = bin2hex(random_bytes(8)); $d['created_at'] = now(); $id = insert('webinars', $d); }
    audit('webinar.save', 'webinar', $id); flash('ok', 'Session saved.' . ($status === 'draft' ? ' It is a draft and not yet visible to the public.' : '')); redirect('/admin/webinars/' . $id);
}
function admin_webinar(array $p): void {
    $w = webinar_guard((int) $p['id']);
    $regs = rows("SELECT * FROM webinar_regs WHERE webinar_id = ? ORDER BY CASE role WHEN 'host' THEN 0 WHEN 'panelist' THEN 1 ELSE 2 END, created_at", [$w['id']]);
    if (qget('export') === 'csv') { csv_download($w['slug'] . '-registrants.csv', ['Name', 'Email', 'Phone', 'Organisation', 'Status', 'Role', 'Attended', 'Minutes in session', 'Rating', 'Feedback', 'Registered'], array_map(fn($r) => [$r['name'], $r['email'], $r['phone'], $r['org'], $r['status'], $r['role'], $r['attended'] ? 'Yes' : 'No', round($r['seconds_in'] / 60), $r['feedback_rating'], $r['feedback_text'], $r['created_at']], $regs)); }
    $guests = array_values(array_filter($regs, fn($r) => $r['role'] !== 'host'));
    $stats = ['regs' => count(array_filter($guests, fn($r) => in_array($r['status'], ['registered', 'pending_payment']))), 'att' => count(array_filter($guests, fn($r) => $r['attended'])), 'avg' => round(array_sum(array_column(array_filter($guests, fn($r) => $r['attended']), 'seconds_in')) / max(1, count(array_filter($guests, fn($r) => $r['attended']))) / 60), 'rating' => (float) val('SELECT AVG(feedback_rating) FROM webinar_regs WHERE webinar_id = ? AND feedback_rating IS NOT NULL', [$w['id']]), 'questions' => (int) val("SELECT COUNT(*) FROM webinar_msgs WHERE webinar_id = ? AND kind = 'qa'", [$w['id']]), 'revenue' => (float) val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE item_type = 'webinar' AND item_id = ? AND status = 'verified'", [$w['id']])];
    $qa = rows("SELECT name, body, answered, votes FROM webinar_msgs WHERE webinar_id = ? AND kind = 'qa' ORDER BY votes DESC, id LIMIT 50", [$w['id']]);
    $polls = rows('SELECT * FROM webinar_polls WHERE webinar_id = ? ORDER BY id', [$w['id']]);
    foreach ($polls as &$po) { $o = json_dec($po['options']); $c = array_fill(0, count($o), 0); foreach (rows('SELECT option_idx, COUNT(*) n FROM webinar_votes WHERE poll_id = ? GROUP BY option_idx', [$po['id']]) as $v) { if (isset($c[$v['option_idx']])) { $c[$v['option_idx']] = (int) $v['n']; } } $po['opts'] = $o; $po['counts'] = $c; } unset($po);
    admin_render('admin/webinar', compact('w', 'regs', 'stats', 'qa', 'polls') + ['title' => $w['title'], 'section' => $w['title']]);
}
function admin_webinar_regs_action(array $p): void {
    $w = webinar_guard((int) $p['id']); $act = post('action'); $rid = (int) post('rid');
    if ($act === 'add') {
        $name = clean('name', 120); $email = strtolower(clean('email', 160));
        if ($name === '' || !mail_valid($email)) { flash('error', 'Enter a name and valid email.'); redirect('/admin/webinars/' . $w['id']); }
        $role = in_array(post('role'), ['attendee', 'panelist', 'cohost'], true) ? post('role') : 'attendee';
        q('INSERT INTO webinar_regs(webinar_id,name,email,token,status,role,created_at) VALUES(?,?,?,?,?,?,?) ON CONFLICT(webinar_id,email) DO UPDATE SET role=excluded.role, status=\'registered\'', [$w['id'], $name, $email, bin2hex(random_bytes(14)), 'registered', $role, now()]);
        $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND email = ?', [$w['id'], $email]); require_once APP . '/controllers/webinars.php'; send_webinar_confirmation($w, $reg); flash('ok', $name . ' was registered and emailed their personal link.');
    } elseif ($reg = row('SELECT * FROM webinar_regs WHERE id = ? AND webinar_id = ?', [$rid, $w['id']])) {
        if ($act === 'resend') { send_webinar_confirmation($w, $reg); flash('ok', 'Link re-sent.'); }
        if ($act === 'remove') { update('webinar_regs', $rid, ['status' => 'removed']); flash('ok', 'Registration cancelled.'); }
        if ($act === 'restore') { update('webinar_regs', $rid, ['status' => 'registered']); }
        if (in_array($act, ['attendee', 'panelist', 'cohost'], true)) { update('webinar_regs', $rid, ['role' => $act]); flash('ok', 'Role updated. Panelists and co-hosts join the stage automatically.'); }
    }
    redirect('/admin/webinars/' . $w['id']);
}
function admin_webinar_delete(array $p): void {
    $w = webinar_guard((int) $p['id']); if (!is_super()) { flash('error', 'Only the Super Admin can delete a session.'); redirect('/admin/webinars'); }
    foreach (['webinar_votes' => 'poll_id IN (SELECT id FROM webinar_polls WHERE webinar_id = ' . (int) $w['id'] . ')', 'webinar_polls' => 'webinar_id = ' . (int) $w['id'], 'webinar_msgs' => 'webinar_id = ' . (int) $w['id'], 'webinar_regs' => 'webinar_id = ' . (int) $w['id'], 'room_peers' => 'webinar_id = ' . (int) $w['id'], 'room_signals' => 'webinar_id = ' . (int) $w['id'], 'room_events' => 'webinar_id = ' . (int) $w['id'], 'webinars' => 'id = ' . (int) $w['id']] as $t => $c) { db()->exec("DELETE FROM $t WHERE $c"); }
    audit('webinar.delete', 'webinar', $w['id']); flash('ok', 'Session deleted.'); redirect('/admin/webinars');
}

function admin_webinar_streams(): void {
    admin_guard('webinars.manage'); if (!is_super()) { admin_guard('users.manage'); }
    $dests = rows('SELECT * FROM stream_destinations ORDER BY id');
    admin_render('admin/webinar_streams', compact('dests') + ['title' => 'Live — streaming', 'section' => 'Streaming & social destinations']);
}
function admin_webinar_streams_save(): void {
    if (!is_super()) { admin_guard('users.manage'); } else { admin_guard(); }
    $act = post('action'); $id = (int) post('id');
    if ($act === 'save') {
        $d = ['platform' => clean('platform', 30), 'label' => clean('label', 80), 'rtmp_url' => clean('rtmp_url', 300), 'watch_url' => clean('watch_url', 300), 'enabled' => post('enabled') ? 1 : 0];
        if (post('stream_key') !== '') { $d['stream_key_enc'] = secret_encrypt((string) post('stream_key')); }
        if ($id) { update('stream_destinations', $id, $d); } else { $d['created_at'] = now(); insert('stream_destinations', $d); }
        flash('ok', 'Destination saved. Stream keys are stored encrypted.');
    } elseif ($act === 'delete') { q('DELETE FROM stream_destinations WHERE id = ?', [$id]); flash('ok', 'Destination removed.'); }
    elseif ($act === 'media') {
        $u = rtrim(trim((string) post('media_url')), '/');
        if ($u !== '' && !preg_match('~^https?://[a-z0-9.\-]+(:\d+)?$~i', $u)) { flash('error', 'Enter the media server address like https://live.donums.co.ke (no path).'); redirect('/admin/webinars/streams'); }
        set_setting('media_url', $u); flash('ok', 'Media server address saved.');
    }
    elseif ($act === 'gen_secret') { $sec = bin2hex(random_bytes(20)); set_setting('media_secret_enc', secret_encrypt($sec)); audit('media.secret_generated'); flash('ok', 'New shared secret generated: ' . $sec . ' — copy it now into the media server installer. It is stored encrypted and can be shown again from this page.'); }
    elseif ($act === 'reveal') { flash('info', 'Shared secret: ' . (media_secret() ?: '(not set)')); }
    elseif ($act === 'media_test') {
        [$ok, $res] = media_helper('GET', '/status');
        flash($ok ? 'ok' : 'error', $ok ? 'Media server connected. ' . ($res['mediamtx'] ?? '') . ' · ffmpeg ' . (($res['ffmpeg'] ?? false) ? 'ready' : 'MISSING') . ' · active restreams: ' . count($res['streams'] ?? []) : (string) $res);
    }
    elseif ($act === 'test_dest') {
        $d = row('SELECT * FROM stream_destinations WHERE id = ?', [$id]);
        $ok = $d && preg_match('~^rtmps?://[^\s]+$~i', (string) $d['rtmp_url']) && $d['stream_key_enc'] !== '';
        flash($ok ? 'ok' : 'error', $ok ? 'Looks valid: the server address and stream key are set. The real connection test happens when you go live.' : 'The server address must start with rtmp:// or rtmps:// and a stream key is required.');
    }
    redirect('/admin/webinars/streams');
}
