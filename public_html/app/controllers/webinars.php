<?php
/* =====================================================================
 * Donums Live — public side: listing, registration (free or paid),
 * calendar files, join links, feedback. The live room itself is in room.php.
 * ===================================================================== */

function webinar_by_slug(string $slug): array {
    $w = row('SELECT * FROM webinars WHERE slug = ?', [$slug]);
    if (!$w || ($w['status'] === 'draft' && !can('webinars.own') && !can('webinars.manage'))) { not_found('This session is not available.'); }
    return $w;
}
function webinar_end_ts(array $w): int { return strtotime($w['start_at']) + ((int) $w['duration_min']) * 60; }
function webinar_is_host(array $w): bool {
    if (!user()) { return false; }
    return is_super() || ($w['host_id'] && (int) $w['host_id'] === uid() && can('webinars.own'));
}
function webinar_state(array $w): string {
    if ($w['status'] === 'live') { return 'live'; }
    if (in_array($w['status'], ['ended', 'cancelled'], true)) { return $w['status']; }
    return time() > webinar_end_ts($w) + 3600 ? 'missed' : 'upcoming';
}
function webinar_seats_left(array $w): int {
    return max(0, (int) $w['capacity'] - (int) val("SELECT COUNT(*) FROM webinar_regs WHERE webinar_id = ? AND status IN ('registered','pending_payment')", [$w['id']]));
}
function reg_link(array $w, array $r): string { return abs_url('/webinars/' . $w['slug'] . '/room', ['t' => $r['token']]); }
function when_label(array $w): string { return fmt_dt($w['start_at']) . ' · ' . minutes_label((int) $w['duration_min']); }

function page_webinars(): void {
    $live = rows("SELECT * FROM webinars WHERE status = 'live' ORDER BY started_at DESC");
    $upcoming = rows("SELECT * FROM webinars WHERE status = 'scheduled' AND start_at >= ? ORDER BY start_at", [date('Y-m-d H:i:s', time() - 3600 * 3)]);
    $past = rows("SELECT * FROM webinars WHERE status = 'ended' AND (recording_url != '' OR replay_public = 1) ORDER BY ended_at DESC LIMIT 9");
    render('webinars/index', compact('live', 'upcoming', 'past') + ['title' => 'Donums Live — webinars and workshops', 'desc' => 'Join live webinars and workshops in your browser, with chat, Q&A and replays. Nothing to install.', 'active' => 'webinars']);
}

function page_webinar(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $reg = null;
    if (user()) { $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND (user_id = ? OR email = ?)', [$w['id'], uid(), user()['email']]); }
    if (!$reg && !empty($_GET['t'])) { $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND token = ?', [$w['id'], qget('t')]); }
    $speakers = json_dec($w['speakers']);
    $schema = ['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $w['title'], 'description' => excerpt($w['summary'] ?: $w['description'], 300), 'startDate' => iso($w['start_at']), 'endDate' => date('Y-m-d\TH:i:s', webinar_end_ts($w)) . '+03:00',
        'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode', 'eventStatus' => $w['status'] === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled', 'location' => ['@type' => 'VirtualLocation', 'url' => abs_url('/webinars/' . $w['slug'])],
        'organizer' => ['@type' => 'Organization', 'name' => cfg('site_name'), 'url' => abs_url('/')], 'offers' => ['@type' => 'Offer', 'price' => (float) $w['price'], 'priceCurrency' => $w['currency'], 'availability' => 'https://schema.org/InStock', 'url' => abs_url('/webinars/' . $w['slug'])]];
    render('webinars/show', compact('w', 'reg', 'speakers') + ['state' => webinar_state($w), 'seats' => webinar_seats_left($w), 'title' => $w['title'], 'desc' => excerpt($w['summary'] ?: $w['description'], 160), 'active' => 'webinars', 'schema' => $schema, 'og_image' => $w['cover']]);
}

function post_webinar_register(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $back = '/webinars/' . $w['slug'];
    if (!in_array($w['status'], ['scheduled', 'live'], true)) { flash('error', 'Registration for this session is closed.'); redirect($back); }
    if (!spam_check(3)) { flash('error', 'We could not verify that submission. Please try again.'); redirect($back); }
    if (!rate_limit('wreg-' . client_ip(), 10, 3600)) { flash('error', 'Too many registrations from this connection.'); redirect($back); }
    $u = user();
    $name = clean('name', 120) ?: ($u['name'] ?? ''); $email = strtolower(clean('email', 160) ?: ($u['email'] ?? ''));
    if ($name === '' || !mail_valid($email)) { flash('error', 'Please enter your name and a valid email address.'); redirect($back); }
    $existing = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND email = ?', [$w['id'], $email]);
    if ($existing) { flash('info', 'You are already registered. We have shown your joining details below.'); redirect($back . '?t=' . $existing['token']); }
    if (webinar_seats_left($w) <= 0) { flash('error', 'Sorry — this session is full.'); redirect($back); }
    $paid = (float) $w['price'] > 0;
    $token = bin2hex(random_bytes(14));
    $id = insert('webinar_regs', ['webinar_id' => $w['id'], 'user_id' => $u['id'] ?? null, 'name' => $name, 'email' => $email, 'phone' => clean('phone', 40), 'org' => clean('org', 120), 'token' => $token, 'status' => $paid ? 'pending_payment' : 'registered', 'created_at' => now()]);
    q('INSERT OR IGNORE INTO contacts(email,name,phone,organisation,source,subscribed,created_at) VALUES(?,?,?,?,?,0,?)', [$email, $name, clean('phone', 40), clean('org', 120), 'Webinar: ' . $w['title'], now()]);
    $reg = row('SELECT * FROM webinar_regs WHERE id = ?', [$id]);
    if ($paid) {
        $payId = insert('payments', ['ref' => ref_code('PAY'), 'user_id' => $u['id'] ?? null, 'name' => $name, 'email' => $email, 'item_type' => 'webinar', 'item_id' => $w['id'], 'item_title' => $w['title'], 'amount' => $w['price'], 'currency' => $w['currency'], 'status' => 'awaiting', 'created_at' => now()]);
        update('webinar_regs', $id, ['payment_id' => $payId]);
        redirect('/webinars/' . $w['slug'] . '/pay?t=' . $token);
    }
    send_webinar_confirmation($w, $reg);
    flash('ok', 'You are registered. We have emailed your personal joining link.');
    redirect($back . '?t=' . $token);
}

function send_webinar_confirmation(array $w, array $reg): void {
    queue_template('webinar_registered', $reg['email'], ['name' => $reg['name'], 'webinar' => $w['title'], 'when' => when_label($w), 'calendar_url' => abs_url('/webinars/' . $w['slug'] . '/calendar.ics'), 'cta_url' => reg_link($w, $reg), 'cta_label' => 'Join the session'], ['dept' => 'Donums Live', 'from' => 'events@' . cfg('domain'), 'related' => 'webinar:' . $w['id']]);
    process_outbox(2);
}

function page_webinar_pay(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND token = ?', [$w['id'], qget('t')]); if (!$reg || !$reg['payment_id']) { not_found(); }
    $pay = row('SELECT * FROM payments WHERE id = ?', [$reg['payment_id']]);
    if ($reg['status'] === 'registered') { redirect('/webinars/' . $w['slug'] . '?t=' . $reg['token']); }
    render('webinars/pay', compact('w', 'reg', 'pay') + ['title' => 'Complete your registration', 'noindex' => true, 'active' => 'webinars']);
}

function get_webinar_ics(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $fmt = fn($ts) => gmdate('Ymd\THis\Z', $ts);
    $esc = fn($s) => str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], (string) $s);
    header('Content-Type: text/calendar; charset=UTF-8'); header('Content-Disposition: attachment; filename="' . $w['slug'] . '.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Donums//Live//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:webinar-" . $w['id'] . '@' . cfg('domain') . "\r\nDTSTAMP:" . $fmt(time()) . "\r\nDTSTART:" . $fmt(strtotime($w['start_at'])) . "\r\nDTEND:" . $fmt(webinar_end_ts($w)) . "\r\nSUMMARY:" . $esc($w['title']) . "\r\nDESCRIPTION:" . $esc(excerpt($w['summary'] ?: $w['description'], 400) . ' Join: ' . abs_url('/webinars/' . $w['slug'])) . "\r\nURL:" . abs_url('/webinars/' . $w['slug']) . "\r\nBEGIN:VALARM\r\nTRIGGER:-PT30M\r\nACTION:DISPLAY\r\nDESCRIPTION:Starts in 30 minutes\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    exit;
}

function page_webinar_feedback(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND token = ?', [$w['id'], qget('t')]); if (!$reg) { not_found(); }
    render('webinars/feedback', compact('w', 'reg') + ['title' => 'Feedback · ' . $w['title'], 'noindex' => true, 'active' => 'webinars']);
}
function post_webinar_feedback(array $p): void {
    $w = webinar_by_slug($p['slug']);
    $reg = row('SELECT * FROM webinar_regs WHERE webinar_id = ? AND token = ?', [$w['id'], (string) post('t')]); if (!$reg) { not_found(); }
    update('webinar_regs', $reg['id'], ['feedback_rating' => max(1, min(5, (int) post('rating', 5))), 'feedback_text' => text('comment', 1500)]);
    flash('ok', 'Thank you for your feedback.');
    redirect('/webinars/' . $w['slug']);
}

/** Called by cron.php: reminders and follow-ups. */
function process_webinar_jobs(): array {
    $sent = ['day' => 0, 'hour' => 0, 'follow' => 0];
    foreach (rows("SELECT * FROM webinars WHERE status = 'scheduled'") as $w) {
        $start = strtotime($w['start_at']); $until = $start - time();
        foreach (rows("SELECT * FROM webinar_regs WHERE webinar_id = ? AND status = 'registered' AND role != 'host' AND email NOT LIKE '%@guest.local'", [$w['id']]) as $r) {
            $vars = ['name' => $r['name'], 'webinar' => $w['title'], 'when' => when_label($w), 'cta_url' => reg_link($w, $r), 'cta_label' => 'Join the session'];
            if ($until > 0 && $until <= 86400 + 1800 && $until > 7200 && !$r['reminded_day']) { queue_template('webinar_reminder_day', $r['email'], $vars, ['dept' => 'Donums Live', 'from' => 'events@' . cfg('domain')]); update('webinar_regs', $r['id'], ['reminded_day' => 1]); $sent['day']++; }
            if ($until > -600 && $until <= 3600 + 600 && !$r['reminded_hour']) { queue_template('webinar_reminder_hour', $r['email'], $vars, ['dept' => 'Donums Live', 'from' => 'events@' . cfg('domain')]); update('webinar_regs', $r['id'], ['reminded_hour' => 1]); $sent['hour']++; }
        }
        // sessions nobody started: close them off after a grace period
        if (webinar_end_ts($w) + 7200 < time()) { q("UPDATE webinars SET status = 'ended', ended_at = ? WHERE id = ?", [now(), $w['id']]); }
    }
    foreach (rows("SELECT * FROM webinars WHERE status = 'ended' AND ended_at >= ? ", [date('Y-m-d H:i:s', time() - 86400 * 3)]) as $w) {
        foreach (rows("SELECT * FROM webinar_regs WHERE webinar_id = ? AND attended = 1 AND reminded_hour < 2 AND role != 'host' AND email NOT LIKE '%@guest.local'", [$w['id']]) as $r) {
            queue_template('webinar_followup', $r['email'], ['name' => $r['name'], 'webinar' => $w['title'], 'recording_line' => $w['recording_url'] ? 'The recording is available here: ' . $w['recording_url'] : 'We will share the recording as soon as it is ready.', 'cta_url' => abs_url('/webinars/' . $w['slug'] . '/feedback', ['t' => $r['token']]), 'cta_label' => 'Give feedback'], ['dept' => 'Donums Live', 'from' => 'events@' . cfg('domain')]);
            update('webinar_regs', $r['id'], ['reminded_hour' => 2]); $sent['follow']++;
        }
    }
    // safety net: stop any social restream whose session ended or whose host has been gone for a while
    foreach (rows("SELECT * FROM webinars WHERE social_live = 1") as $w) {
        $hostGone = !val("SELECT 1 FROM room_peers WHERE webinar_id = ? AND role = 'host' AND last_seen >= ?", [$w['id'], time() - 180]);
        if ($w['status'] !== 'live' || $hostGone) { media_helper('POST', '/stop', ['path' => media_path($w)]); q('UPDATE webinars SET social_live = 0, program_live = 0 WHERE id = ?', [$w['id']]); app_log('media', 'Stopped orphaned social stream for webinar ' . $w['id']); }
    }
    // clean up stale room signalling data
    q('DELETE FROM room_signals WHERE created < ?', [time() - 600]); q('DELETE FROM room_peers WHERE last_seen < ?', [time() - 3600]); q('DELETE FROM room_events WHERE created < ?', [time() - 86400]);
    return $sent;
}
