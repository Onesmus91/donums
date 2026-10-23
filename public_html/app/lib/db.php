<?php
/* =====================================================================
 * Database (SQLite via PDO) — zero-configuration, single file, WAL mode.
 * Schema changes are applied automatically through numbered migrations.
 * ===================================================================== */

function db(): PDO {
    static $pdo = null;
    if ($pdo) { return $pdo; }
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        http_response_code(500);
        exit('The PDO SQLite extension is required. Ask your host to enable pdo_sqlite (it is standard on most PHP installs).');
    }
    $file = DATA_DIR . '/donums.sqlite';
    $fresh = !is_file($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 8000');
    if ($fresh) { @chmod($file, 0640); }
    db_migrate($pdo);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function insert(string $table, array $data): int {
    $cols = array_keys($data);
    q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int) db()->lastInsertId();
}
function update(string $table, $id, array $data, string $pk = 'id'): void {
    if (!$data) { return; }
    $set = implode(',', array_map(fn($c) => $c . ' = ?', array_keys($data)));
    q("UPDATE $table SET $set WHERE $pk = ?", array_merge(array_values($data), [$id]));
}
function in_clause(array $ids): string { return implode(',', array_fill(0, max(1, count($ids)), '?')); }

function db_migrate(PDO $pdo): void {
    $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $migrations = db_migrations();
    foreach ($migrations as $n => $sqls) {
        if ($n <= $v) { continue; }
        $pdo->beginTransaction();
        try {
            foreach ($sqls as $sql) { $pdo->exec($sql); }
            $pdo->exec('PRAGMA user_version = ' . (int) $n);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            app_log('error', 'Migration ' . $n . ' failed: ' . $e->getMessage());
            throw $e;
        }
        if ($n === 1) { $GLOBALS['DN_FRESH_INSTALL'] = true; }
    }
    if (!empty($GLOBALS['DN_FRESH_INSTALL']) && empty($GLOBALS['DN_SEEDING'])) {
        $GLOBALS['DN_SEEDING'] = true;
        require_once APP . '/lib/seed.php';
        seed_defaults($pdo);
        require_once APP . '/lib/legacy.php';
        legacy_import($pdo);
    }
}

function db_migrations(): array {
    $m = [];
    $m[1] = [
    /* ---------------- platform ---------------- */
    "CREATE TABLE settings (k TEXT PRIMARY KEY, v TEXT)",
    "CREATE TABLE rate_limits (k TEXT PRIMARY KEY, hits INTEGER, reset_at INTEGER)",
    "CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, entity TEXT, entity_id TEXT, meta TEXT, ip TEXT, created_at TEXT)",
    "CREATE INDEX idx_audit_created ON audit_log(created_at)",
    "CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT, entity TEXT, entity_id TEXT, user_id INTEGER, kind TEXT, text TEXT, created_at TEXT)",
    "CREATE INDEX idx_activity_entity ON activity(entity, entity_id)",
    "CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT, title TEXT, body TEXT, url TEXT, read_at TEXT, created_at TEXT)",
    "CREATE INDEX idx_notif_user ON notifications(user_id, read_at)",
    "CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        name TEXT NOT NULL, phone TEXT, password_hash TEXT NOT NULL,
        is_super INTEGER NOT NULL DEFAULT 0,
        roles TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'active',
        headline TEXT, bio TEXT, location TEXT, avatar TEXT,
        email_verified_at TEXT, totp_secret TEXT, totp_enabled INTEGER NOT NULL DEFAULT 0,
        failed_logins INTEGER NOT NULL DEFAULT 0, locked_until TEXT,
        reputation INTEGER NOT NULL DEFAULT 0, forum_trusted INTEGER NOT NULL DEFAULT 0,
        notify_email INTEGER NOT NULL DEFAULT 1,
        created_at TEXT, last_login_at TEXT
    )",
    "CREATE TABLE password_resets (token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL, expires_at INTEGER NOT NULL, used INTEGER NOT NULL DEFAULT 0)",
    "CREATE TABLE email_verifications (token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL, expires_at INTEGER NOT NULL)",

    /* ---------------- CRM: requests, partners, deliveries, catalogue ---------------- */
    "CREATE TABLE requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE,
        service TEXT, need TEXT, name TEXT, contact TEXT, organisation TEXT, location TEXT, details TEXT,
        source TEXT DEFAULT 'web',
        status TEXT NOT NULL DEFAULT 'New', quote TEXT, provider TEXT, provider_cost TEXT,
        payment TEXT NOT NULL DEFAULT 'Unpaid', followup TEXT, notes TEXT,
        created_at TEXT, updated_at TEXT
    )",
    "CREATE INDEX idx_requests_status ON requests(status)",
    "CREATE TABLE partners (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE,
        name TEXT, contact TEXT, kind TEXT, location TEXT, details TEXT,
        verification TEXT NOT NULL DEFAULT 'Pending', partner_status TEXT NOT NULL DEFAULT 'Applicant', notes TEXT,
        created_at TEXT, updated_at TEXT
    )",
    "CREATE TABLE deliveries (
        id INTEGER PRIMARY KEY AUTOINCREMENT, request_ref TEXT NOT NULL UNIQUE,
        stage TEXT NOT NULL DEFAULT 'Assigned', provider TEXT, value TEXT, provider_cost TEXT,
        payment TEXT NOT NULL DEFAULT 'Unpaid', notes TEXT, updated_at TEXT
    )",
    "CREATE TABLE catalog (
        id TEXT PRIMARY KEY, type TEXT, service TEXT, parent TEXT, title TEXT, description TEXT,
        price TEXT, location TEXT, image TEXT, video TEXT, status TEXT NOT NULL DEFAULT 'Active', updated_at TEXT
    )",

    /* ---------------- payments (manual verification: M-Pesa / bank / link) ---------------- */
    "CREATE TABLE payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE,
        user_id INTEGER, name TEXT, email TEXT,
        item_type TEXT NOT NULL, item_id INTEGER NOT NULL, item_title TEXT,
        amount REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'KES',
        method TEXT, method_ref TEXT, proof_path TEXT,
        status TEXT NOT NULL DEFAULT 'awaiting', note TEXT,
        created_at TEXT, submitted_at TEXT, verified_at TEXT, verified_by INTEGER
    )",
    "CREATE INDEX idx_payments_status ON payments(status)",

    /* ---------------- learning (Donums Academy) ---------------- */
    "CREATE TABLE course_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, name TEXT, pos INTEGER DEFAULT 0)",
    "CREATE TABLE courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL, subtitle TEXT, description TEXT,
        category_id INTEGER, level TEXT DEFAULT 'All levels', language TEXT DEFAULT 'English',
        price REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'KES',
        status TEXT NOT NULL DEFAULT 'draft', thumbnail TEXT, promo_url TEXT,
        outcomes TEXT, requirements TEXT, audience TEXT, faq TEXT,
        instructor_id INTEGER, certificate INTEGER NOT NULL DEFAULT 1, pass_mark INTEGER NOT NULL DEFAULT 70,
        duration_min INTEGER NOT NULL DEFAULT 0, lessons_count INTEGER NOT NULL DEFAULT 0,
        rating_avg REAL NOT NULL DEFAULT 0, rating_count INTEGER NOT NULL DEFAULT 0, enrol_count INTEGER NOT NULL DEFAULT 0,
        featured INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT, published_at TEXT
    )",
    "CREATE INDEX idx_courses_status ON courses(status)",
    "CREATE TABLE course_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INTEGER NOT NULL, title TEXT, description TEXT, pos INTEGER DEFAULT 0)",
    "CREATE INDEX idx_modules_course ON course_modules(course_id, pos)",
    "CREATE TABLE lessons (
        id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INTEGER NOT NULL, module_id INTEGER NOT NULL,
        title TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'text', body TEXT, video_url TEXT,
        duration_min INTEGER NOT NULL DEFAULT 0, file_path TEXT, file_name TEXT,
        is_preview INTEGER NOT NULL DEFAULT 0, pass_mark INTEGER NOT NULL DEFAULT 70, pos INTEGER DEFAULT 0
    )",
    "CREATE INDEX idx_lessons_course ON lessons(course_id, module_id, pos)",
    "CREATE TABLE quiz_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INTEGER NOT NULL, prompt TEXT NOT NULL,
        kind TEXT NOT NULL DEFAULT 'single', options TEXT, answer TEXT, explanation TEXT, pos INTEGER DEFAULT 0
    )",
    "CREATE INDEX idx_quiz_lesson ON quiz_questions(lesson_id, pos)",
    "CREATE TABLE enrolments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, course_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'active', price REAL NOT NULL DEFAULT 0, payment_id INTEGER,
        progress INTEGER NOT NULL DEFAULT 0, last_lesson_id INTEGER,
        enrolled_at TEXT, completed_at TEXT, UNIQUE(user_id, course_id)
    )",
    "CREATE TABLE lesson_progress (user_id INTEGER NOT NULL, lesson_id INTEGER NOT NULL, course_id INTEGER NOT NULL, completed_at TEXT, PRIMARY KEY(user_id, lesson_id))",
    "CREATE TABLE quiz_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, lesson_id INTEGER NOT NULL, course_id INTEGER NOT NULL, score INTEGER, passed INTEGER, answers TEXT, created_at TEXT)",
    "CREATE INDEX idx_attempts_user ON quiz_attempts(user_id, lesson_id)",
    "CREATE TABLE course_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INTEGER NOT NULL, user_id INTEGER NOT NULL, rating INTEGER NOT NULL, body TEXT, status TEXT NOT NULL DEFAULT 'published', created_at TEXT, UNIQUE(course_id, user_id))",
    "CREATE TABLE certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL, course_id INTEGER, kind TEXT NOT NULL DEFAULT 'course', holder_name TEXT, title TEXT, hours INTEGER DEFAULT 0, issued_at TEXT, UNIQUE(user_id, course_id, kind))",

    /* ---------------- careers & ATS ---------------- */
    "CREATE TABLE jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT UNIQUE, slug TEXT,
        title TEXT NOT NULL, organisation TEXT, employer_email TEXT, employer_phone TEXT,
        location TEXT, work_mode TEXT DEFAULT 'On-site', type TEXT DEFAULT 'Full-time', category TEXT,
        salary_text TEXT, description TEXT, requirements TEXT,
        required_skills TEXT, min_experience INTEGER DEFAULT 0, required_quals TEXT,
        screening TEXT, deadline TEXT,
        status TEXT NOT NULL DEFAULT 'pending', featured INTEGER NOT NULL DEFAULT 0,
        confidential INTEGER NOT NULL DEFAULT 0, source TEXT DEFAULT 'internal', owner_id INTEGER,
        views INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT, published_at TEXT
    )",
    "CREATE INDEX idx_jobs_status ON jobs(status, published_at)",
    "CREATE TABLE applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE, token TEXT NOT NULL,
        job_id INTEGER, user_id INTEGER,
        name TEXT NOT NULL, email TEXT NOT NULL COLLATE NOCASE, phone TEXT, location TEXT, linkedin TEXT,
        skills TEXT, experience_years INTEGER DEFAULT 0, qualification TEXT, expected_salary TEXT, notice_period TEXT,
        cover_letter TEXT, cv_path TEXT, cv_name TEXT, answers TEXT,
        fit_score INTEGER DEFAULT 0, fit_breakdown TEXT, matched_skills TEXT,
        stage TEXT NOT NULL DEFAULT 'Applied', rating INTEGER DEFAULT 0, tags TEXT, source TEXT DEFAULT 'Careers site',
        reject_reason TEXT, talent_pool INTEGER NOT NULL DEFAULT 0, assigned_to INTEGER,
        created_at TEXT, stage_changed_at TEXT, updated_at TEXT
    )",
    "CREATE INDEX idx_apps_job ON applications(job_id, stage)",
    "CREATE INDEX idx_apps_email ON applications(email)",
    "CREATE TABLE app_events (id INTEGER PRIMARY KEY AUTOINCREMENT, application_id INTEGER NOT NULL, user_id INTEGER, kind TEXT, from_stage TEXT, to_stage TEXT, note TEXT, visible_to_candidate INTEGER NOT NULL DEFAULT 0, created_at TEXT)",
    "CREATE INDEX idx_appev_app ON app_events(application_id)",
    "CREATE TABLE interviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT, application_id INTEGER NOT NULL, title TEXT,
        starts_at TEXT, duration_min INTEGER DEFAULT 45, mode TEXT DEFAULT 'Video', location TEXT,
        interviewers TEXT, status TEXT NOT NULL DEFAULT 'Scheduled', notes TEXT, created_by INTEGER, created_at TEXT
    )",
    "CREATE TABLE scorecards (id INTEGER PRIMARY KEY AUTOINCREMENT, application_id INTEGER NOT NULL, user_id INTEGER, stage TEXT, score INTEGER, recommendation TEXT, comment TEXT, created_at TEXT)",

    /* ---------------- community forum ---------------- */
    "CREATE TABLE forum_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, name TEXT, description TEXT, icon TEXT DEFAULT 'chat', staff_only INTEGER NOT NULL DEFAULT 0, pos INTEGER DEFAULT 0)",
    "CREATE TABLE forum_threads (
        id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
        title TEXT NOT NULL, slug TEXT, body TEXT NOT NULL, tags TEXT,
        status TEXT NOT NULL DEFAULT 'published', is_pinned INTEGER NOT NULL DEFAULT 0, is_locked INTEGER NOT NULL DEFAULT 0,
        views INTEGER NOT NULL DEFAULT 0, reply_count INTEGER NOT NULL DEFAULT 0, score INTEGER NOT NULL DEFAULT 0,
        solved_post_id INTEGER, course_id INTEGER, last_post_at TEXT, last_user_id INTEGER,
        created_at TEXT, edited_at TEXT
    )",
    "CREATE INDEX idx_threads_cat ON forum_threads(category_id, status, is_pinned, last_post_at)",
    "CREATE TABLE forum_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL, user_id INTEGER NOT NULL, parent_id INTEGER,
        body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'published', score INTEGER NOT NULL DEFAULT 0,
        created_at TEXT, edited_at TEXT
    )",
    "CREATE INDEX idx_posts_thread ON forum_posts(thread_id, created_at)",
    "CREATE TABLE forum_votes (user_id INTEGER NOT NULL, target TEXT NOT NULL, target_id INTEGER NOT NULL, value INTEGER NOT NULL, PRIMARY KEY(user_id, target, target_id))",
    "CREATE TABLE forum_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, target TEXT, target_id INTEGER, user_id INTEGER, reason TEXT, status TEXT NOT NULL DEFAULT 'open', handled_by INTEGER, created_at TEXT)",
    "CREATE TABLE forum_subs (user_id INTEGER NOT NULL, thread_id INTEGER NOT NULL, PRIMARY KEY(user_id, thread_id))",

    /* ---------------- webinars / live ---------------- */
    "CREATE TABLE webinars (
        id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, title TEXT NOT NULL, summary TEXT, description TEXT, agenda TEXT,
        kind TEXT NOT NULL DEFAULT 'webinar', start_at TEXT, duration_min INTEGER NOT NULL DEFAULT 60,
        host_id INTEGER, host_name TEXT, speakers TEXT, capacity INTEGER NOT NULL DEFAULT 100,
        price REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'KES',
        access TEXT NOT NULL DEFAULT 'registered', waiting_room INTEGER NOT NULL DEFAULT 0,
        chat_enabled INTEGER NOT NULL DEFAULT 1, qa_enabled INTEGER NOT NULL DEFAULT 1,
        status TEXT NOT NULL DEFAULT 'draft', room_key TEXT NOT NULL,
        cover TEXT, recording_url TEXT, replay_public INTEGER NOT NULL DEFAULT 0,
        engine TEXT NOT NULL DEFAULT 'builtin', started_at TEXT, ended_at TEXT, created_at TEXT, updated_at TEXT
    )",
    "CREATE INDEX idx_webinars_start ON webinars(status, start_at)",
    "CREATE TABLE webinar_regs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, webinar_id INTEGER NOT NULL, user_id INTEGER,
        name TEXT NOT NULL, email TEXT NOT NULL COLLATE NOCASE, phone TEXT, org TEXT, token TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'registered', payment_id INTEGER, role TEXT NOT NULL DEFAULT 'attendee',
        attended INTEGER NOT NULL DEFAULT 0, first_join_at TEXT, last_seen_at TEXT, seconds_in INTEGER NOT NULL DEFAULT 0,
        reminded_day INTEGER NOT NULL DEFAULT 0, reminded_hour INTEGER NOT NULL DEFAULT 0, feedback_rating INTEGER, feedback_text TEXT,
        created_at TEXT, UNIQUE(webinar_id, email)
    )",
    "CREATE TABLE webinar_msgs (id INTEGER PRIMARY KEY AUTOINCREMENT, webinar_id INTEGER NOT NULL, reg_id INTEGER, name TEXT, role TEXT, kind TEXT NOT NULL DEFAULT 'chat', body TEXT, answered INTEGER NOT NULL DEFAULT 0, pinned INTEGER NOT NULL DEFAULT 0, hidden INTEGER NOT NULL DEFAULT 0, votes INTEGER NOT NULL DEFAULT 0, created_at TEXT)",
    "CREATE INDEX idx_wmsgs ON webinar_msgs(webinar_id, id)",
    "CREATE TABLE webinar_polls (id INTEGER PRIMARY KEY AUTOINCREMENT, webinar_id INTEGER NOT NULL, question TEXT, options TEXT, status TEXT NOT NULL DEFAULT 'open', created_at TEXT)",
    "CREATE TABLE webinar_votes (poll_id INTEGER NOT NULL, reg_id INTEGER NOT NULL, option_idx INTEGER NOT NULL, PRIMARY KEY(poll_id, reg_id))",
    "CREATE TABLE room_peers (id INTEGER PRIMARY KEY AUTOINCREMENT, webinar_id INTEGER NOT NULL, reg_id INTEGER NOT NULL, peer_id TEXT NOT NULL UNIQUE, name TEXT, role TEXT, on_stage INTEGER NOT NULL DEFAULT 0, hand INTEGER NOT NULL DEFAULT 0, admitted INTEGER NOT NULL DEFAULT 1, mic INTEGER NOT NULL DEFAULT 1, cam INTEGER NOT NULL DEFAULT 1, last_seen INTEGER, joined_at TEXT)",
    "CREATE INDEX idx_peers_w ON room_peers(webinar_id, last_seen)",
    "CREATE TABLE room_signals (id INTEGER PRIMARY KEY AUTOINCREMENT, webinar_id INTEGER NOT NULL, from_peer TEXT, to_peer TEXT, type TEXT, payload TEXT, created INTEGER)",
    "CREATE INDEX idx_signals ON room_signals(webinar_id, to_peer, id)",
    "CREATE TABLE room_events (id INTEGER PRIMARY KEY AUTOINCREMENT, webinar_id INTEGER NOT NULL, type TEXT, payload TEXT, created INTEGER)",
    "CREATE INDEX idx_revents ON room_events(webinar_id, id)",
    "CREATE TABLE stream_destinations (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT, label TEXT, rtmp_url TEXT, stream_key_enc TEXT, watch_url TEXT, enabled INTEGER NOT NULL DEFAULT 1, created_at TEXT)",

    /* ---------------- mail ---------------- */
    "CREATE TABLE mailboxes (
        address TEXT PRIMARY KEY COLLATE NOCASE, display_name TEXT, username TEXT, password_enc TEXT,
        imap_host TEXT, imap_port INTEGER DEFAULT 993, imap_security TEXT DEFAULT 'ssl',
        smtp_host TEXT, smtp_port INTEGER DEFAULT 465, smtp_security TEXT DEFAULT 'ssl',
        signature TEXT, active INTEGER NOT NULL DEFAULT 1, last_sync_at TEXT, last_uid INTEGER DEFAULT 0, last_error TEXT, pos INTEGER DEFAULT 0
    )",
    "CREATE TABLE mail_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox TEXT NOT NULL COLLATE NOCASE, folder TEXT NOT NULL DEFAULT 'inbox',
        uid INTEGER, message_id TEXT, in_reply_to TEXT, thread_key TEXT,
        from_name TEXT, from_email TEXT, to_json TEXT, cc_json TEXT, subject TEXT, snippet TEXT,
        body_text TEXT, body_html TEXT, has_att INTEGER NOT NULL DEFAULT 0,
        is_read INTEGER NOT NULL DEFAULT 0, is_starred INTEGER NOT NULL DEFAULT 0, status TEXT,
        msg_date TEXT, created_at TEXT, request_ref TEXT
    )",
    "CREATE UNIQUE INDEX idx_mail_uid ON mail_messages(mailbox, uid) WHERE uid IS NOT NULL",
    "CREATE INDEX idx_mail_list ON mail_messages(mailbox, folder, msg_date)",
    "CREATE INDEX idx_mail_thread ON mail_messages(thread_key)",
    "CREATE TABLE mail_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, filename TEXT, mime TEXT, size INTEGER, path TEXT, content_id TEXT)",
    "CREATE INDEX idx_att_msg ON mail_attachments(message_id)",
    "CREATE TABLE mail_drafts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, mailbox TEXT, to_list TEXT, cc_list TEXT, bcc_list TEXT, subject TEXT, body TEXT, attachments TEXT, reply_to_id INTEGER, updated_at TEXT)",
    "CREATE TABLE mail_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, k TEXT UNIQUE, name TEXT, subject TEXT, body TEXT, kind TEXT NOT NULL DEFAULT 'canned', mailbox TEXT, updated_at TEXT)",
    "CREATE TABLE outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox TEXT NOT NULL, to_email TEXT NOT NULL, to_name TEXT, cc TEXT, bcc TEXT,
        subject TEXT, html TEXT, text_body TEXT, attachments TEXT, reply_message_id TEXT,
        status TEXT NOT NULL DEFAULT 'queued', attempts INTEGER NOT NULL DEFAULT 0, send_after TEXT, sent_at TEXT, error TEXT,
        kind TEXT NOT NULL DEFAULT 'manual', campaign_id INTEGER, recipient_id INTEGER, related TEXT, message_id TEXT, created_by INTEGER, created_at TEXT
    )",
    "CREATE INDEX idx_outbox_q ON outbox(status, send_after)",
    "CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, name TEXT, phone TEXT, organisation TEXT, tags TEXT, source TEXT, subscribed INTEGER NOT NULL DEFAULT 1, unsubscribed_at TEXT, user_id INTEGER, created_at TEXT, last_emailed_at TEXT)",
    "CREATE TABLE campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, mailbox TEXT, subject TEXT, preheader TEXT, body TEXT,
        segment TEXT, status TEXT NOT NULL DEFAULT 'draft', scheduled_at TEXT,
        total INTEGER DEFAULT 0, sent INTEGER DEFAULT 0, failed INTEGER DEFAULT 0, created_by INTEGER, created_at TEXT, sent_at TEXT
    )",
    "CREATE TABLE campaign_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER NOT NULL, contact_id INTEGER, email TEXT NOT NULL COLLATE NOCASE, name TEXT,
        status TEXT NOT NULL DEFAULT 'queued', token TEXT NOT NULL UNIQUE, sent_at TEXT, opened_at TEXT, open_count INTEGER DEFAULT 0,
        clicked_at TEXT, click_count INTEGER DEFAULT 0, error TEXT, UNIQUE(campaign_id, email)
    )",
    "CREATE INDEX idx_camprec ON campaign_recipients(campaign_id, status)",
    ];
    /* ---------------- v2.1: broadcast mode (media server + social restream) ---------------- */
    $m[2] = [
        "ALTER TABLE webinars ADD COLUMN stream_dests TEXT",
        "ALTER TABLE webinars ADD COLUMN program_live INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE webinars ADD COLUMN social_live INTEGER NOT NULL DEFAULT 0",
    ];
    return $m;
}
