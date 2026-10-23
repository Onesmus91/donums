<?php
/* =====================================================================
 * Donums Live — the browser room.
 *   Page:  GET  /webinars/{slug}/room
 *   API:   /api/room/{slug}/{action}   (JSON, polled every ~1.2 s)
 * Media runs peer-to-peer over WebRTC (stage peers <-> everyone), with
 * signalling carried by this server. No third-party meeting software.
 * ===================================================================== */

const ROOM_MAX_STAGE = 6;
const PEER_TTL = 25;

function room_ice_servers(): array {
    $j = json_decode((string) setting('ice_servers', ''), true);
    return is_array($j) && $j ? $j : [['urls' => 'stun:stun.l.google.com:19302']];
}

function page_room(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $host = webinar_is_host($w);
    $reg = null;
    if (!empty($_GET['t'])) { $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND token = ?', [$w['id'], qget('t')]); }
    if (!$reg && $host) {
        $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND user_id = ?', [$w['id'], uid()]);
        if (!$reg) {
            $id = insert('webinar_regs', ['webinar_id' => $w['id'], 'user_id' => uid(), 'name' => user()['name'], 'email' => user()['email'], 'token' => bin2hex(random_bytes(14)), 'status' => 'registered', 'role' => 'host', 'created_at' => now()]);
            $reg = row('SELECT * FROM webinar_regs WHERE id = ?', [$id]);
        }
    }
    if (!$reg && !$host && $w['access'] === 'public' && in_array($w['status'], ['scheduled', 'live'], true)) {
        render('webinars/guest', ['w' => $w, 'title' => 'Join ' . $w['title'], 'noindex' => true, 'active' => 'webinars']); return;
    }
    if (!$reg) { flash('info', 'Register for this session to receive your personal joining link.'); redirect('/webinars/' . $w['slug']); }
    if ($reg['status'] === 'pending_payment') { redirect('/webinars/' . $w['slug'] . '/pay?t=' . $reg['token']); }
    if ($reg['status'] !== 'registered') { flash('error', 'This joining link is no longer active.'); redirect('/webinars/' . $w['slug']); }
    if (in_array($w['status'], ['ended', 'cancelled'], true) && !$host) { flash('info', 'This session has ended.'); redirect('/webinars/' . $w['slug'] . '?t=' . $reg['token']); }
    if (!$host && $w['status'] === 'scheduled' && strtotime($w['start_at']) - time() > 900) { flash('info', 'The room opens 15 minutes before the start time.'); redirect('/webinars/' . $w['slug'] . '?t=' . $reg['token']); }
    $cfg = ['slug' => $w['slug'], 'token' => $reg['token'], 'name' => $reg['name'], 'host' => $host, 'role' => $host ? 'host' : $reg['role'], 'csrf' => csrf_token(), 'ice' => room_ice_servers(), 'title' => $w['title'], 'status' => $w['status'], 'start' => iso($w['start_at']), 'chat' => (bool) $w['chat_enabled'], 'qa' => (bool) $w['qa_enabled'], 'broadcast' => webinar_broadcast($w), 'leave' => $host ? '/admin/webinars/' . $w['id'] : '/webinars/' . $w['slug'] . '?t=' . $reg['token']];
    render('webinars/room', ['w' => $w, 'cfg' => $cfg, 'host' => $host, 'title' => $w['title'] . ' · Live', 'noindex' => true], 'room');
}

function post_room_guest(array $p): void {
    $w = webinar_by_slug($p['slug']);
    if ($w['access'] !== 'public' || !in_array($w['status'], ['scheduled', 'live'], true)) { not_found(); }
    if (!spam_check(2)) { flash('error', 'Please try again.'); redirect('/webinars/' . $w['slug'] . '/room'); }
    $name = clean('name', 80); if ($name === '') { flash('error', 'Please enter your name.'); redirect('/webinars/' . $w['slug'] . '/room'); }
    $email = strtolower(clean('email', 160)); if (!mail_valid($email)) { $email = 'guest-' . bin2hex(random_bytes(5)) . '@guest.local'; }
    $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND email = ?', [$w['id'], $email]);
    if (!$reg) { $id = insert('webinar_regs', ['webinar_id' => $w['id'], 'name' => $name, 'email' => $email, 'token' => bin2hex(random_bytes(14)), 'status' => 'registered', 'created_at' => now()]); $reg = row('SELECT * FROM webinar_regs WHERE id = ?', [$id]); }
    redirect('/webinars/' . $w['slug'] . '/room?t=' . $reg['token']);
}

/* ---------------- API ---------------- */
function room_fail(string $msg, int $code = 400): void { json_out(['ok' => false, 'error' => $msg], $code); }

function api_room(array $p): void {
    $w = row('SELECT * FROM webinars WHERE slug = ?', [$p['slug']]); if (!$w) { room_fail('Session not found.', 404); }
    $token = (string) ($_SERVER['HTTP_X_ROOM_TOKEN'] ?? ($_GET['tk'] ?? ''));
    $reg = $token !== '' ? row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND token = ?', [$w['id'], $token]) : null;
    if (!$reg || $reg['status'] !== 'registered') { room_fail('Your access to this session is not valid.', 403); }
    $isHost = $reg['role'] === 'host' && webinar_is_host($w);
    $act = $p['action'];
    $in = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode((string) file_get_contents('php://input'), true) ?: $_POST) : [];
    $pid = (string) ($_SERVER['HTTP_X_PEER_ID'] ?? ($in['peer'] ?? ($_GET['peer'] ?? '')));
    $me = $pid !== '' ? row('SELECT * FROM room_peers WHERE peer_id = ? AND webinar_id = ? AND reg_id = ?', [$pid, $w['id'], $reg['id']]) : null;
    $now = time();

    if ($act === 'join') {
        if (in_array($w['status'], ['ended', 'cancelled'], true)) { room_fail('This session has ended.', 410); }
        if (!$isHost) {
            $active = (int) val('SELECT COUNT(*) FROM room_peers WHERE webinar_id = ? AND last_seen >= ?', [$w['id'], $now - PEER_TTL]);
            if ($active >= (int) $w['capacity'] + 5) { room_fail('This session is full.', 409); }
        }
        q('DELETE FROM room_peers WHERE webinar_id = ? AND reg_id = ?', [$w['id'], $reg['id']]);
        $role = $isHost ? 'host' : ($reg['role'] ?: 'attendee');
        $stage = in_array($role, ['host', 'panelist', 'cohost'], true) ? 1 : 0;
        $adm = ($stage || !$w['waiting_room']) ? 1 : 0;
        $newPid = bin2hex(random_bytes(8));
        insert('room_peers', ['webinar_id' => $w['id'], 'reg_id' => $reg['id'], 'peer_id' => $newPid, 'name' => $reg['name'], 'role' => $role, 'on_stage' => $stage, 'admitted' => $adm, 'last_seen' => $now, 'joined_at' => now()]);
        q('UPDATE webinar_regs SET first_join_at = COALESCE(first_join_at, ?), last_seen_at = ?, attended = ? WHERE id = ?', [now(), now(), $w['status'] === 'live' || $isHost ? 1 : $reg['attended'], $reg['id']]);
        $lastEv = (int) val('SELECT COALESCE(MAX(id),0) FROM room_events WHERE webinar_id = ?', [$w['id']]);
        $bc = webinar_broadcast($w); $media = null; $dests = [];
        if ($bc) {
            $path = media_path($w);
            $media = ['base' => media_url(), 'path' => $path, 'read' => media_token($path, 'read'), 'publish' => $isHost ? media_token($path, 'publish') : null];
            if ($isHost) { $dests = array_map(fn($d) => ['id' => (int) $d['id'], 'label' => $d['label'] ?: $d['platform'], 'platform' => $d['platform'], 'ready' => $d['rtmp_url'] !== '' && $d['stream_key_enc'] !== ''], stream_destinations()); }
        }
        json_out(['ok' => true, 'peer' => $newPid, 'role' => $role, 'ice' => room_ice_servers(), 'last_event' => $lastEv, 'status' => $w['status'], 'max_stage' => ROOM_MAX_STAGE,
            'broadcast' => $bc, 'media' => $media, 'dests' => $dests, 'selected' => json_dec($w['stream_dests']), 'poll_ms' => ($bc && !$stage) ? 4000 : 1200]);
    }
    if (!$me) { json_out(['ok' => true, 'gone' => true]); }

    if ($act === 'poll') {
        // presence + attendance
        if ($w['status'] === 'live' && !(int) $reg['attended']) { q('UPDATE webinar_regs SET attended = 1, first_join_at = COALESCE(first_join_at, ?) WHERE id = ?', [now(), $reg['id']]); }
        // Presence is written at most every 8 seconds per person, which keeps 100+ viewers cheap on a shared host.
        if ($now - (int) $me['last_seen'] >= 8) {
            $dt = $reg['last_seen_at'] ? min(15, max(0, $now - strtotime($reg['last_seen_at']))) : 0;
            q('UPDATE room_peers SET last_seen = ? WHERE id = ?', [$now, $me['id']]);
            if ($w['status'] === 'live') { q('UPDATE webinar_regs SET last_seen_at = ?, seconds_in = seconds_in + ?, attended = 1 WHERE id = ?', [now(), $dt, $reg['id']]); }
            else { q('UPDATE webinar_regs SET last_seen_at = ? WHERE id = ?', [now(), $reg['id']]); }
        }
        $w = row('SELECT * FROM webinars WHERE id = ?', [$w['id']]);
        $out = ['ok' => true, 'status' => $w['status'], 'started' => $w['started_at'] ? iso($w['started_at']) : null, 'chat_enabled' => (bool) $w['chat_enabled'], 'qa_enabled' => (bool) $w['qa_enabled'], 'now' => $now, 'program_live' => (int) ($w['program_live'] && val("SELECT 1 FROM room_peers WHERE webinar_id = ? AND role = 'host' AND last_seen >= ?", [$w['id'], $now - 30])), 'social_live' => (int) $w['social_live']];
        $me = row('SELECT * FROM room_peers WHERE id = ?', [$me['id']]);
        $out['me'] = ['on_stage' => (int) $me['on_stage'], 'admitted' => (int) $me['admitted'], 'role' => $me['role'], 'hand' => (int) $me['hand']];
        $evs = rows('SELECT * FROM room_events WHERE webinar_id = ? AND id > ? ORDER BY id LIMIT 50', [$w['id'], (int) ($_GET['e'] ?? 0)]);
        $out['events'] = array_map(fn($e) => ['id' => (int) $e['id'], 'type' => $e['type'], 'data' => json_dec($e['payload'])], $evs);
        if (!$me['admitted']) { json_out($out + ['waiting' => true]); }
        $roster = rows('SELECT peer_id, name, role, on_stage, hand, admitted, mic, cam FROM room_peers WHERE webinar_id = ? AND last_seen >= ? ORDER BY on_stage DESC, joined_at', [$w['id'], $now - PEER_TTL]);
        $out['online'] = count(array_filter($roster, fn($r) => $r['admitted']));
        $out['roster'] = $isHost ? $roster : array_values(array_filter($roster, fn($r) => $r['admitted'] && (!webinar_broadcast($w) || $r['on_stage'])));
        $out['signals'] = array_map(fn($s) => ['id' => (int) $s['id'], 'from' => $s['from_peer'], 'type' => $s['type'], 'payload' => json_decode($s['payload'], true)], rows('SELECT * FROM room_signals WHERE webinar_id = ? AND to_peer = ? AND id > ? ORDER BY id LIMIT 100', [$w['id'], $me['peer_id'], (int) ($_GET['s'] ?? 0)]));
        $out['chat'] = rows("SELECT id, name, role, body, created_at FROM webinar_msgs WHERE webinar_id = ? AND kind = 'chat' AND hidden = 0 AND id > ? ORDER BY id LIMIT 100", [$w['id'], (int) ($_GET['m'] ?? 0)]);
        $out['qa'] = rows("SELECT id, name, body, answered, pinned, votes FROM webinar_msgs WHERE webinar_id = ? AND kind = 'qa' AND hidden = 0 ORDER BY pinned DESC, answered ASC, votes DESC, id LIMIT 100", [$w['id']]);
        $poll = row("SELECT * FROM webinar_polls WHERE webinar_id = ? ORDER BY id DESC LIMIT 1", [$w['id']]);
        if ($poll) {
            $opts = json_dec($poll['options']); $counts = array_fill(0, count($opts), 0);
            foreach (rows('SELECT option_idx, COUNT(*) n FROM webinar_votes WHERE poll_id = ? GROUP BY option_idx', [$poll['id']]) as $c) { if (isset($counts[$c['option_idx']])) { $counts[$c['option_idx']] = (int) $c['n']; } }
            $mine = val('SELECT option_idx FROM webinar_votes WHERE poll_id = ? AND reg_id = ?', [$poll['id'], $reg['id']]);
            $out['poll'] = ['id' => (int) $poll['id'], 'question' => $poll['question'], 'options' => $opts, 'counts' => $counts, 'status' => $poll['status'], 'mine' => $mine === null ? null : (int) $mine];
        }
        if (random_int(1, 40) === 1) { q('DELETE FROM room_signals WHERE created < ?', [$now - 300]); }
        json_out($out);
    }

    if (!$me['admitted'] && !$isHost) { room_fail('Waiting for the host to admit you.', 403); }

    switch ($act) {
        case 'signal':
            $to = row('SELECT peer_id FROM room_peers WHERE peer_id = ? AND webinar_id = ?', [(string) ($in['to'] ?? ''), $w['id']]);
            $payload = json_encode($in['payload'] ?? null);
            if (!$to || strlen($payload) > 40000) { room_fail('Invalid signal.'); }
            if (!rate_limit('sig-' . $me['peer_id'], 900, 60)) { room_fail('Too many signals.', 429); }
            insert('room_signals', ['webinar_id' => $w['id'], 'from_peer' => $me['peer_id'], 'to_peer' => $to['peer_id'], 'type' => substr((string) ($in['type'] ?? ''), 0, 20), 'payload' => $payload, 'created' => $now]);
            json_out(['ok' => true]);
        case 'chat':
            $kind = ($in['kind'] ?? 'chat') === 'qa' ? 'qa' : 'chat';
            if (($kind === 'chat' && !$w['chat_enabled'] && !$isHost) || ($kind === 'qa' && !$w['qa_enabled'])) { room_fail('This is turned off by the host.', 403); }
            $body = mb_substr(trim(strip_tags((string) ($in['body'] ?? ''))), 0, 500);
            if ($body === '') { room_fail('Empty message.'); }
            if (!rate_limit('chat-' . $me['peer_id'], 20, 60)) { room_fail('You are sending messages too quickly.', 429); }
            $id = insert('webinar_msgs', ['webinar_id' => $w['id'], 'reg_id' => $reg['id'], 'name' => $reg['name'], 'role' => $me['role'], 'kind' => $kind, 'body' => $body, 'created_at' => now()]);
            json_out(['ok' => true, 'id' => $id]);
        case 'hand':
            q('UPDATE room_peers SET hand = ? WHERE id = ?', [!empty($in['on']) ? 1 : 0, $me['id']]); json_out(['ok' => true]);
        case 'media':
            q('UPDATE room_peers SET mic = ?, cam = ? WHERE id = ?', [!empty($in['mic']) ? 1 : 0, !empty($in['cam']) ? 1 : 0, $me['id']]); json_out(['ok' => true]);
        case 'vote':
            $poll = row("SELECT * FROM webinar_polls WHERE id = ? AND webinar_id = ? AND status = 'open'", [(int) ($in['poll'] ?? 0), $w['id']]);
            $opt = (int) ($in['option'] ?? -1);
            if (!$poll || $opt < 0 || $opt >= count(json_dec($poll['options']))) { room_fail('That poll is closed.'); }
            q('INSERT OR IGNORE INTO webinar_votes(poll_id,reg_id,option_idx) VALUES(?,?,?)', [$poll['id'], $reg['id'], $opt]); json_out(['ok' => true]);
        case 'qa_vote':
            q("UPDATE webinar_msgs SET votes = votes + 1 WHERE id = ? AND webinar_id = ? AND kind = 'qa'", [(int) ($in['id'] ?? 0), $w['id']]); json_out(['ok' => true]);
        case 'leave':
            q('DELETE FROM room_peers WHERE id = ?', [$me['id']]); json_out(['ok' => true]);
    }

    /* ----- host-only controls ----- */
    if (!$isHost) { room_fail('Only the host can do that.', 403); }
    $event = function (string $type, array $data = []) use ($w, $now) { insert('room_events', ['webinar_id' => $w['id'], 'type' => $type, 'payload' => json_encode($data), 'created' => $now]); };
    $target = fn() => row('SELECT * FROM room_peers WHERE peer_id = ? AND webinar_id = ?', [(string) ($in['target'] ?? ''), $w['id']]);
    switch ($act) {
        case 'start':
            if ($w['status'] !== 'live') { q("UPDATE webinars SET status = 'live', started_at = ? WHERE id = ?", [now(), $w['id']]); $event('status', ['status' => 'live']); audit('webinar.start', 'webinar', $w['id']); }
            json_out(['ok' => true]);
        case 'end':
            q("UPDATE webinars SET status = 'ended', ended_at = ?, program_live = 0, social_live = 0 WHERE id = ?", [now(), $w['id']]); $event('status', ['status' => 'ended']); audit('webinar.end', 'webinar', $w['id']);
            if (webinar_broadcast($w)) { media_helper('POST', '/stop', ['path' => media_path($w)]); }
            json_out(['ok' => true]);
        case 'admit':
            if (!empty($in['all'])) { q('UPDATE room_peers SET admitted = 1 WHERE webinar_id = ?', [$w['id']]); }
            elseif ($t = $target()) { q('UPDATE room_peers SET admitted = 1 WHERE id = ?', [$t['id']]); }
            json_out(['ok' => true]);
        case 'stage':
            $t = $target(); if (!$t) { room_fail('Participant not found.'); }
            $on = !empty($in['on']) ? 1 : 0;
            if ($on && (int) val('SELECT COUNT(*) FROM room_peers WHERE webinar_id = ? AND on_stage = 1 AND last_seen >= ?', [$w['id'], $now - PEER_TTL]) >= ROOM_MAX_STAGE) { room_fail('The stage is full (' . ROOM_MAX_STAGE . ' people on camera). Remove someone first.'); }
            q('UPDATE room_peers SET on_stage = ?, hand = 0 WHERE id = ?', [$on, $t['id']]); $event($on ? 'stage_in' : 'stage_out', ['peer' => $t['peer_id']]);
            json_out(['ok' => true]);
        case 'mute':
            if ($t = $target()) { $event('mute', ['peer' => $t['peer_id']]); } json_out(['ok' => true]);
        case 'lower_hand':
            if ($t = $target()) { q('UPDATE room_peers SET hand = 0 WHERE id = ?', [$t['id']]); } json_out(['ok' => true]);
        case 'remove':
            if ($t = $target()) { $event('kick', ['peer' => $t['peer_id']]); q('DELETE FROM room_peers WHERE id = ?', [$t['id']]); q("UPDATE webinar_regs SET status = 'removed' WHERE id = ? AND role != 'host'", [$t['reg_id']]); }
            json_out(['ok' => true]);
        case 'poll_create':
            $opts = array_values(array_filter(array_map(fn($x) => mb_substr(trim(strip_tags((string) $x)), 0, 120), (array) ($in['options'] ?? [])), fn($x) => $x !== ''));
            $qtext = mb_substr(trim(strip_tags((string) ($in['question'] ?? ''))), 0, 200);
            if ($qtext === '' || count($opts) < 2) { room_fail('A poll needs a question and at least two options.'); }
            q("UPDATE webinar_polls SET status = 'closed' WHERE webinar_id = ?", [$w['id']]);
            insert('webinar_polls', ['webinar_id' => $w['id'], 'question' => $qtext, 'options' => json_encode(array_slice($opts, 0, 6), JSON_UNESCAPED_UNICODE), 'status' => 'open', 'created_at' => now()]);
            $event('poll', ['open' => true]); json_out(['ok' => true]);
        case 'poll_close':
            q("UPDATE webinar_polls SET status = 'closed' WHERE webinar_id = ?", [$w['id']]); json_out(['ok' => true]);
        case 'qa_update':
            $id = (int) ($in['id'] ?? 0); $f = (string) ($in['field'] ?? '');
            if (in_array($f, ['answered', 'pinned', 'hidden'], true)) { q("UPDATE webinar_msgs SET $f = ? WHERE id = ? AND webinar_id = ?", [!empty($in['value']) ? 1 : 0, $id, $w['id']]); if ($f === 'hidden') { $event('hide_msg', ['id' => $id]); } }
            json_out(['ok' => true]);
        case 'announce':
            $t = mb_substr(trim(strip_tags((string) ($in['text'] ?? ''))), 0, 200); if ($t !== '') { $event('announce', ['text' => $t]); } json_out(['ok' => true]);
        case 'program':
            $on = !empty($in['on']) ? 1 : 0; q('UPDATE webinars SET program_live = ? WHERE id = ?', [$on, $w['id']]);
            if (!$on && $w['social_live']) { media_helper('POST', '/stop', ['path' => media_path($w)]); q('UPDATE webinars SET social_live = 0 WHERE id = ?', [$w['id']]); }
            $event('program', ['live' => $on]); audit('webinar.program', 'webinar', $w['id'], ['on' => $on]); json_out(['ok' => true]);
        case 'social_start':
            if (!webinar_broadcast($w)) { room_fail('Broadcast mode is not enabled for this session.'); }
            if (!$w['program_live']) { room_fail('Go live to attendees first — the social stream copies that feed.'); }
            $ids = array_values(array_unique(array_map('intval', (array) ($in['dests'] ?? [])))); if (!$ids) { room_fail('Choose at least one destination.'); }
            $list = [];
            foreach (rows('SELECT * FROM stream_destinations WHERE enabled = 1 AND id IN (' . in_clause($ids) . ')', $ids) as $d) {
                $key = secret_decrypt($d['stream_key_enc']); if ($d['rtmp_url'] === '' || $key === '') { continue; }
                $list[] = ['id' => (int) $d['id'], 'name' => $d['label'] ?: $d['platform'], 'url' => $d['rtmp_url'], 'key' => $key];
            }
            if (!$list) { room_fail('Those destinations are missing a server address or stream key (Admin → Live → Streaming).'); }
            [$ok, $res] = media_helper('POST', '/start', ['path' => media_path($w), 'relay_password' => media_relay_password(), 'destinations' => $list]);
            if (!$ok) { room_fail((string) $res, 502); }
            q('UPDATE webinars SET social_live = 1, stream_dests = ? WHERE id = ?', [json_encode($ids), $w['id']]); $event('social', ['live' => 1]); audit('webinar.social_start', 'webinar', $w['id'], ['dests' => $ids]);
            json_out(['ok' => true, 'status' => $res]);
        case 'social_stop':
            [$ok, $res] = media_helper('POST', '/stop', ['path' => media_path($w)]); q('UPDATE webinars SET social_live = 0 WHERE id = ?', [$w['id']]); $event('social', ['live' => 0]); audit('webinar.social_stop', 'webinar', $w['id']);
            json_out(['ok' => true, 'warning' => $ok ? null : $res]);
        case 'social_status':
            [$ok, $res] = media_helper('GET', '/status?path=' . rawurlencode(media_path($w))); json_out(['ok' => $ok, 'status' => $ok ? $res : null, 'error' => $ok ? null : $res]);
        case 'chat_toggle':
            q('UPDATE webinars SET chat_enabled = ? WHERE id = ?', [!empty($in['on']) ? 1 : 0, $w['id']]); json_out(['ok' => true]);
    }
    room_fail('Unknown action.', 404);
}
