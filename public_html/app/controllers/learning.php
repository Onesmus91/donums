<?php
/* =====================================================================
 * Donums Academy — catalogue, course pages, enrolment, lesson player,
 * quizzes, progress, reviews and certificates.
 * ===================================================================== */

function course_by_slug(string $slug, bool $publishedOnly = true): array {
    $c = row('SELECT c.*, u.name instructor_name, u.headline instructor_headline, u.bio instructor_bio, u.avatar instructor_avatar, cat.name category_name FROM courses c LEFT JOIN users u ON u.id=c.instructor_id LEFT JOIN course_categories cat ON cat.id=c.category_id WHERE c.slug = ?', [$slug]);
    if (!$c || ($publishedOnly && $c['status'] !== 'published' && !can('learning.manage') && !(can('learning.own') && (int) $c['instructor_id'] === uid()))) { not_found('This course is not available.'); }
    return $c;
}
function course_outline(int $courseId): array {
    $mods = rows('SELECT * FROM course_modules WHERE course_id = ? ORDER BY pos, id', [$courseId]);
    $les = rows('SELECT id, module_id, title, kind, duration_min, is_preview, pos FROM lessons WHERE course_id = ? ORDER BY pos, id', [$courseId]);
    foreach ($mods as &$m) { $m['lessons'] = array_values(array_filter($les, fn($l) => (int) $l['module_id'] === (int) $m['id'])); }
    return $mods;
}
function enrolment_for(int $courseId): ?array { return uid() ? row('SELECT * FROM enrolments WHERE user_id = ? AND course_id = ?', [uid(), $courseId]) : null; }
function has_access(array $c): bool {
    $e = enrolment_for((int) $c['id']);
    return ($e && in_array($e['status'], ['active', 'completed'], true)) || can('learning.manage') || (can('learning.own') && (int) $c['instructor_id'] === uid());
}
function lesson_icon(string $kind): string { return ['video' => 'play', 'text' => 'file', 'file' => 'download', 'quiz' => 'check', 'live' => 'live'][$kind] ?? 'file'; }

function recalc_course(int $courseId): void {
    $s = row('SELECT COUNT(*) n, COALESCE(SUM(duration_min),0) d FROM lessons WHERE course_id = ?', [$courseId]);
    $r = row("SELECT COUNT(*) n, COALESCE(AVG(rating),0) a FROM course_reviews WHERE course_id = ? AND status='published'", [$courseId]);
    $en = (int) val("SELECT COUNT(*) FROM enrolments WHERE course_id = ? AND status IN ('active','completed')", [$courseId]);
    q('UPDATE courses SET lessons_count=?, duration_min=?, rating_avg=?, rating_count=?, enrol_count=?, updated_at=? WHERE id=?', [$s['n'], $s['d'], round((float) $r['a'], 2), $r['n'], $en, now(), $courseId]);
}
function recalc_progress(int $userId, int $courseId): int {
    $total = (int) val('SELECT COUNT(*) FROM lessons WHERE course_id = ?', [$courseId]);
    $done = (int) val('SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)', [$userId, $courseId, $courseId]);
    $pct = $total ? (int) floor($done * 100 / $total) : 0;
    q('UPDATE enrolments SET progress = ? WHERE user_id = ? AND course_id = ?', [$pct, $userId, $courseId]);
    return $pct;
}

/* ---------- catalogue ---------- */
function page_learning(): void {
    $cat = qget('cat'); $level = qget('level'); $s = qget('q'); $sort = qget('sort', 'new'); $page = max(1, qint('page', 1));
    $where = ["c.status = 'published'"]; $par = [];
    if ($cat !== '') { $where[] = 'cat.slug = ?'; $par[] = $cat; }
    if (in_array($level, ['Beginner', 'Intermediate', 'Advanced'], true)) { $where[] = 'c.level = ?'; $par[] = $level; }
    if ($s !== '') { $where[] = '(c.title LIKE ? OR c.subtitle LIKE ? OR c.description LIKE ?)'; $par = array_merge($par, ["%$s%", "%$s%", "%$s%"]); }
    if (qget('price') === 'free') { $where[] = 'c.price = 0'; }
    $order = ['new' => 'c.published_at DESC', 'popular' => 'c.enrol_count DESC', 'rating' => 'c.rating_avg DESC, c.rating_count DESC', 'price' => 'c.price ASC'][$sort] ?? 'c.published_at DESC';
    $from = 'FROM courses c LEFT JOIN course_categories cat ON cat.id=c.category_id LEFT JOIN users u ON u.id=c.instructor_id WHERE ' . implode(' AND ', $where);
    $pg = paginate((int) val("SELECT COUNT(*) $from", $par), 12, $page);
    $courses = rows("SELECT c.*, u.name instructor_name, cat.name category_name $from ORDER BY c.featured DESC, $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", $par);
    $cats = rows("SELECT cat.*, (SELECT COUNT(*) FROM courses c WHERE c.category_id=cat.id AND c.status='published') n FROM course_categories cat ORDER BY pos");
    $mine = uid() ? array_column(rows('SELECT course_id, status, progress FROM enrolments WHERE user_id = ?', [uid()]), null, 'course_id') : [];
    render('learning/index', compact('courses', 'cats', 'pg', 'mine', 'cat', 'level', 's', 'sort') + ['title' => 'Donums Academy — online courses', 'desc' => 'Practical online courses with video lessons, quizzes and verifiable certificates from Donums Academy.', 'active' => 'learning']);
}

/* ---------- course landing page ---------- */
function page_course(array $p): void {
    $c = course_by_slug($p['slug']);
    $outline = course_outline((int) $c['id']);
    $enrol = enrolment_for((int) $c['id']);
    $reviews = rows("SELECT r.*, u.name FROM course_reviews r JOIN users u ON u.id=r.user_id WHERE r.course_id=? AND r.status='published' ORDER BY r.created_at DESC LIMIT 20", [$c['id']]);
    $mine = uid() ? row('SELECT * FROM course_reviews WHERE course_id = ? AND user_id = ?', [$c['id'], uid()]) : null;
    $dist = array_fill(1, 5, 0); foreach (rows("SELECT rating, COUNT(*) n FROM course_reviews WHERE course_id=? AND status='published' GROUP BY rating", [$c['id']]) as $d) { $dist[(int) $d['rating']] = (int) $d['n']; }
    $threads = rows("SELECT t.id,t.title,t.slug,t.reply_count FROM forum_threads t WHERE t.course_id=? AND t.status='published' ORDER BY t.last_post_at DESC LIMIT 5", [$c['id']]);
    $schema = ['@context' => 'https://schema.org', '@type' => 'Course', 'name' => $c['title'], 'description' => excerpt($c['subtitle'] ?: $c['description'], 300), 'provider' => ['@type' => 'Organization', 'name' => cfg('site_name'), 'sameAs' => abs_url('/')]];
    render('learning/course', compact('c', 'outline', 'enrol', 'reviews', 'mine', 'dist', 'threads') + ['title' => $c['title'], 'desc' => excerpt($c['subtitle'] ?: $c['description'], 160), 'active' => 'learning', 'schema' => $schema, 'og_image' => $c['thumbnail'] ?: '']);
}

/* ---------- enrolment (free = instant, paid = manual payment verification) ---------- */
function post_enrol(array $p): void {
    $u = require_login('/learning/' . $p['slug']);
    $c = course_by_slug($p['slug']);
    $e = enrolment_for((int) $c['id']);
    if ($e && in_array($e['status'], ['active', 'completed'], true)) { redirect('/learning/' . $c['slug'] . '/learn'); }
    if ((float) $c['price'] <= 0) {
        q('INSERT INTO enrolments(user_id,course_id,status,price,enrolled_at) VALUES(?,?,?,?,?) ON CONFLICT(user_id,course_id) DO UPDATE SET status=excluded.status', [$u['id'], $c['id'], 'active', 0, now()]);
        recalc_course((int) $c['id']);
        queue_template('enrol_active', $u['email'], ['name' => $u['name'], 'course' => $c['title'], 'cta_url' => abs_url('/learning/' . $c['slug'] . '/learn'), 'cta_label' => 'Start learning'], ['dept' => 'Donums Academy', 'from' => 'academy@' . cfg('domain')]);
        flash('ok', 'You are enrolled in ' . $c['title'] . '. Let’s begin.');
        redirect('/learning/' . $c['slug'] . '/learn');
    }
    redirect('/learning/' . $c['slug'] . '/pay');
}

function page_course_pay(array $p): void {
    $u = require_login('/learning/' . $p['slug'] . '/pay');
    $c = course_by_slug($p['slug']);
    if (has_access($c)) { redirect('/learning/' . $c['slug'] . '/learn'); }
    if ((float) $c['price'] <= 0) { redirect('/learning/' . $c['slug']); }
    $pay = row("SELECT * FROM payments WHERE user_id = ? AND item_type = 'course' AND item_id = ? AND status IN ('awaiting','submitted') ORDER BY id DESC", [$u['id'], $c['id']]);
    if (!$pay) {
        $id = insert('payments', ['ref' => ref_code('PAY'), 'user_id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'item_type' => 'course', 'item_id' => $c['id'], 'item_title' => $c['title'], 'amount' => $c['price'], 'currency' => $c['currency'], 'status' => 'awaiting', 'created_at' => now()]);
        $pay = row('SELECT * FROM payments WHERE id = ?', [$id]);
    }
    render('learning/pay', ['c' => $c, 'pay' => $pay, 'title' => 'Complete your enrolment', 'noindex' => true, 'active' => 'learning']);
}

function post_payment_submit(array $p): void {
    $u = require_login();
    $pay = row('SELECT * FROM payments WHERE ref = ? AND user_id = ?', [$p['ref'], $u['id']]);
    if (!$pay || !in_array($pay['status'], ['awaiting', 'submitted', 'rejected'], true)) { not_found(); }
    $code = strtoupper(clean('method_ref', 40)); $method = clean('method', 20);
    if ($method !== 'link' && strlen($code) < 6) { flash('error', 'Enter the M-Pesa or bank transaction code from your confirmation message.'); redirect($_SERVER['HTTP_REFERER'] ?? '/account'); }
    $proof = null;
    if (!empty($_FILES['proof']['name'])) {
        $f = save_private_upload('proof', 'proofs', ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1048576);
        if (is_string($f)) { flash('error', $f); redirect($_SERVER['HTTP_REFERER'] ?? '/account'); }
        $proof = $f['path'];
    }
    update('payments', $pay['id'], ['method' => $method ?: 'mpesa', 'method_ref' => $code, 'proof_path' => $proof ?: $pay['proof_path'], 'status' => 'submitted', 'submitted_at' => now()]);
    if ($pay['item_type'] === 'course') {
        q("INSERT INTO enrolments(user_id,course_id,status,price,payment_id,enrolled_at) VALUES(?,?,?,?,?,?) ON CONFLICT(user_id,course_id) DO UPDATE SET status='pending', payment_id=excluded.payment_id", [$u['id'], $pay['item_id'], 'pending', $pay['amount'], $pay['id'], now()]);
    }
    queue_template($pay['item_type'] === 'course' ? 'enrol_pending' : 'webinar_pending', $u['email'], ['name' => $u['name'], 'course' => $pay['item_title'], 'webinar' => $pay['item_title'], 'when' => '', 'payment_ref' => $pay['ref']], ['dept' => 'Payments', 'from' => 'finance@' . cfg('domain')]);
    notify_staff('crm.manage', 'payment', 'Payment to verify: ' . $pay['ref'], $pay['item_title'] . ' — ' . money($pay['amount'], $pay['currency']), '/admin/payments');
    flash('ok', 'Thank you. We have your payment details and will confirm your access shortly — usually within one business day.');
    redirect($pay['item_type'] === 'course' ? '/account' : '/account');
}

/* ---------- player ---------- */
function page_learn(array $p): void {
    $c = course_by_slug($p['slug'], false);
    $u = require_login('/learning/' . $c['slug'] . '/learn');
    $outline = course_outline((int) $c['id']);
    $flat = []; foreach ($outline as $m) { foreach ($m['lessons'] as $l) { $flat[] = $l; } }
    if (!$flat) { flash('info', 'This course has no lessons yet. Please check back soon.'); redirect('/learning/' . $c['slug']); }
    $lessonId = (int) ($p['lid'] ?? 0);
    $enrol = enrolment_for((int) $c['id']);
    $access = has_access($c);
    if (!$access && !$lessonId) { redirect('/learning/' . $c['slug']); }
    if (!$lessonId) { $lessonId = (int) ($enrol['last_lesson_id'] ?? 0) ?: (int) $flat[0]['id']; }
    $lesson = row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [$lessonId, $c['id']]);
    if (!$lesson) { redirect('/learning/' . $c['slug'] . '/learn'); }
    if (!$access && !$lesson['is_preview']) { flash('info', 'Enrol to unlock this lesson.'); redirect('/learning/' . $c['slug']); }
    if ($access && $enrol) { q('UPDATE enrolments SET last_lesson_id = ? WHERE user_id = ? AND course_id = ?', [$lessonId, $u['id'], $c['id']]); }
    $done = array_column(rows('SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND course_id = ?', [$u['id'], $c['id']]), 'lesson_id');
    $questions = $lesson['kind'] === 'quiz' ? rows('SELECT * FROM quiz_questions WHERE lesson_id = ? ORDER BY pos, id', [$lessonId]) : [];
    $attempt = $lesson['kind'] === 'quiz' ? row('SELECT * FROM quiz_attempts WHERE user_id = ? AND lesson_id = ? ORDER BY id DESC', [$u['id'], $lessonId]) : null;
    $idx = array_search($lessonId, array_column($flat, 'id')); $prev = $idx > 0 ? $flat[$idx - 1] : null; $next = $idx < count($flat) - 1 ? $flat[$idx + 1] : null;
    $threads = rows("SELECT id,title,slug,reply_count FROM forum_threads WHERE course_id=? AND status='published' ORDER BY last_post_at DESC LIMIT 4", [$c['id']]);
    render('learning/player', compact('c', 'outline', 'lesson', 'done', 'questions', 'attempt', 'prev', 'next', 'enrol', 'access', 'threads') + ['title' => $lesson['title'] . ' · ' . $c['title'], 'noindex' => true, 'active' => 'learning', 'body_class' => 'player-page']);
}

function post_lesson_complete(array $p): void {
    $u = require_login();
    $l = row('SELECT * FROM lessons WHERE id = ?', [(int) $p['lid']]); if (!$l) { not_found(); }
    $c = row('SELECT * FROM courses WHERE id = ?', [$l['course_id']]);
    if (!has_access($c)) { http_response_code(403); exit; }
    if ($l['kind'] === 'quiz' && !val('SELECT 1 FROM quiz_attempts WHERE user_id=? AND lesson_id=? AND passed=1', [$u['id'], $l['id']])) { flash('error', 'Pass the quiz to complete this lesson.'); redirect('/learning/' . $c['slug'] . '/learn/' . $l['id']); }
    q('INSERT OR IGNORE INTO lesson_progress(user_id,lesson_id,course_id,completed_at) VALUES(?,?,?,?)', [$u['id'], $l['id'], $c['id'], now()]);
    finish_course_if_done($u, $c);
    $ids = []; foreach (course_outline((int) $c['id']) as $m) { foreach ($m['lessons'] as $x) { $ids[] = (int) $x['id']; } }
    $i = array_search((int) $l['id'], $ids, true);
    if (isset($ids[$i + 1])) { redirect('/learning/' . $c['slug'] . '/learn/' . $ids[$i + 1]); }
    redirect('/learning/' . $c['slug'] . '/learn/' . $l['id']);
}

function finish_course_if_done(array $u, array $c): void {
    $pct = recalc_progress((int) $u['id'], (int) $c['id']);
    if ($pct < 100) { return; }
    $e = enrolment_for((int) $c['id']);
    if (!$e || $e['status'] === 'completed') { return; }
    q("UPDATE enrolments SET status='completed', completed_at=? WHERE id=?", [now(), $e['id']]);
    if ($c['certificate']) {
        $code = 'DN-' . strtoupper(bin2hex(random_bytes(5)));
        q('INSERT OR IGNORE INTO certificates(code,user_id,course_id,kind,holder_name,title,hours,issued_at) VALUES(?,?,?,?,?,?,?,?)', [$code, $u['id'], $c['id'], 'course', $u['name'], $c['title'], (int) round($c['duration_min'] / 60), now()]);
        $code = (string) val('SELECT code FROM certificates WHERE user_id=? AND course_id=? AND kind=?', [$u['id'], $c['id'], 'course']);
        queue_template('certificate', $u['email'], ['name' => $u['name'], 'course' => $c['title'], 'code' => $code, 'cta_url' => abs_url('/learning/certificate/' . $code), 'cta_label' => 'View certificate'], ['dept' => 'Donums Academy', 'from' => 'academy@' . cfg('domain')]);
        notify_user((int) $u['id'], 'certificate', 'Your certificate is ready', $c['title'], '/learning/certificate/' . $code);
        flash('ok', 'Congratulations — you completed the course! Your certificate is ready.');
    }
}

function post_quiz_submit(array $p): void {
    $u = require_login();
    $l = row("SELECT * FROM lessons WHERE id = ? AND kind = 'quiz'", [(int) $p['lid']]); if (!$l) { not_found(); }
    $c = row('SELECT * FROM courses WHERE id = ?', [$l['course_id']]);
    if (!has_access($c)) { http_response_code(403); exit; }
    $qs = rows('SELECT * FROM quiz_questions WHERE lesson_id = ? ORDER BY pos, id', [$l['id']]);
    if (!$qs) { redirect('/learning/' . $c['slug'] . '/learn/' . $l['id']); }
    $right = 0; $ans = [];
    foreach ($qs as $q) {
        $given = $_POST['q' . $q['id']] ?? []; $given = array_map('intval', (array) $given); sort($given);
        $correct = array_map('intval', json_dec($q['answer'])); sort($correct);
        $ok = $given === $correct; if ($ok) { $right++; }
        $ans[$q['id']] = ['given' => $given, 'ok' => $ok];
    }
    $score = (int) round($right * 100 / count($qs)); $passed = $score >= (int) $l['pass_mark'] ? 1 : 0;
    insert('quiz_attempts', ['user_id' => $u['id'], 'lesson_id' => $l['id'], 'course_id' => $c['id'], 'score' => $score, 'passed' => $passed, 'answers' => json_encode($ans), 'created_at' => now()]);
    if ($passed) { q('INSERT OR IGNORE INTO lesson_progress(user_id,lesson_id,course_id,completed_at) VALUES(?,?,?,?)', [$u['id'], $l['id'], $c['id'], now()]); finish_course_if_done($u, $c); }
    flash($passed ? 'ok' : 'warn', $passed ? "Well done — you scored $score%." : "You scored $score%. You need {$l['pass_mark']}% to pass. Review the lesson and try again.");
    redirect('/learning/' . $c['slug'] . '/learn/' . $l['id']);
}

function get_lesson_file(array $p): void {
    $l = row('SELECT * FROM lessons WHERE id = ?', [(int) $p['lid']]); if (!$l || !$l['file_path']) { not_found(); }
    $c = row('SELECT * FROM courses WHERE id = ?', [$l['course_id']]);
    if (!has_access($c) && !$l['is_preview']) { http_response_code(403); exit('Enrol to access this file.'); }
    serve_private($l['file_path'], $l['file_name'] ?: 'lesson-file', $l['kind'] === 'video');
}

function post_review(array $p): void {
    $u = require_login(); $c = course_by_slug($p['slug']);
    if (!has_access($c)) { flash('error', 'Only enrolled learners can review a course.'); redirect('/learning/' . $c['slug']); }
    $r = max(1, min(5, (int) post('rating', 5)));
    q('INSERT INTO course_reviews(course_id,user_id,rating,body,created_at) VALUES(?,?,?,?,?) ON CONFLICT(course_id,user_id) DO UPDATE SET rating=excluded.rating, body=excluded.body', [$c['id'], $u['id'], $r, text('body', 1500), now()]);
    recalc_course((int) $c['id']);
    flash('ok', 'Thank you for your review.');
    redirect('/learning/' . $c['slug'] . '#reviews');
}

/* ---------- certificates ---------- */
function page_certificate(array $p): void {
    $cert = row('SELECT ce.*, u.name FROM certificates ce JOIN users u ON u.id=ce.user_id WHERE ce.code = ?', [strtoupper($p['code'])]);
    if (!$cert) { not_found('No certificate matches that code.'); }
    render('learning/certificate', ['cert' => $cert, 'title' => 'Certificate · ' . $cert['title'], 'noindex' => true, 'owner' => uid() === (int) $cert['user_id']]);
}
function page_verify_cert(): void {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', qget('code'))); $cert = null;
    if ($code !== '') { $cert = row('SELECT ce.*, u.name FROM certificates ce JOIN users u ON u.id=ce.user_id WHERE ce.code = ?', [$code]); }
    render('learning/verify', ['code' => $code, 'cert' => $cert, 'title' => 'Verify a certificate', 'desc' => 'Confirm that a Donums Academy certificate is genuine.']);
}
