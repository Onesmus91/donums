<?php
/* Admin → Academy: course builder, lessons, quizzes, learners, reviews. */

function admin_routes_learning(): void {
    route('GET', '/admin/courses', 'admin_courses');
    route('GET', '/admin/courses/new', 'admin_course_new');
    route('POST', '/admin/courses/save', 'admin_course_save');
    route('POST', '/admin/courses/categories', 'admin_course_categories');
    route('GET', '/admin/courses/{id:\d+}', 'admin_course_builder');
    route('POST', '/admin/courses/{id:\d+}/delete', 'admin_course_delete');
    route('POST', '/admin/courses/{id:\d+}/module', 'admin_module_action');
    route('POST', '/admin/courses/{id:\d+}/lesson-new', 'admin_lesson_new');
    route('GET', '/admin/courses/{id:\d+}/lesson/{lid:\d+}', 'admin_lesson_edit');
    route('POST', '/admin/courses/{id:\d+}/lesson/{lid:\d+}', 'admin_lesson_save');
    route('POST', '/admin/courses/{id:\d+}/lesson/{lid:\d+}/{do:delete|up|down}', 'admin_lesson_action');
    route('GET', '/admin/courses/{id:\d+}/learners', 'admin_course_learners');
    route('POST', '/admin/courses/{id:\d+}/learners', 'admin_course_learners_action');
    route('POST', '/admin/reviews/{id:\d+}/toggle', 'admin_review_toggle');
}

function course_guard(int $id): array {
    admin_guard('learning.manage', 'learning.own');
    $c = row('SELECT * FROM courses WHERE id = ?', [$id]); if (!$c) { not_found(); }
    if (!can('learning.manage') && (int) $c['instructor_id'] !== uid()) { http_response_code(403); render('errors/403', ['title' => 'Access restricted'], 'site'); exit; }
    return $c;
}

function admin_courses(): void {
    admin_guard('learning.manage', 'learning.own');
    $mine = can('learning.manage') ? '' : 'WHERE c.instructor_id = ' . (int) uid();
    $items = rows("SELECT c.*, u.name instructor_name, cat.name category_name FROM courses c LEFT JOIN users u ON u.id=c.instructor_id LEFT JOIN course_categories cat ON cat.id=c.category_id $mine ORDER BY c.updated_at DESC");
    $cats = rows('SELECT * FROM course_categories ORDER BY pos');
    $reviews = can('learning.manage') ? rows("SELECT r.*, u.name, c.title FROM course_reviews r JOIN users u ON u.id=r.user_id JOIN courses c ON c.id=r.course_id ORDER BY r.id DESC LIMIT 15") : [];
    admin_render('admin/courses', compact('items', 'cats', 'reviews') + ['title' => 'Academy', 'section' => 'Donums Academy']);
}
function admin_course_new(): void { admin_guard('learning.manage', 'learning.own'); admin_course_builder(['id' => 0]); }

function admin_course_save(): void {
    admin_guard('learning.manage', 'learning.own'); $id = (int) post('id');
    $c = $id ? course_guard($id) : null;
    $title = clean('title', 160); if ($title === '') { flash('error', 'A course title is required.'); redirect($id ? '/admin/courses/' . $id : '/admin/courses/new'); }
    $img = save_public_image('thumbnail', 'course'); if ($img !== '' && $img[0] === '!') { flash('error', substr($img, 1)); redirect($id ? '/admin/courses/' . $id : '/admin/courses/new'); }
    $status = in_array(post('status'), ['draft', 'published', 'archived'], true) ? post('status') : 'draft';
    $faq = []; foreach (lines((string) post('faq')) as $l) { $p = explode('|', $l, 2); if (count($p) === 2) { $faq[] = ['q' => trim($p[0]), 'a' => trim($p[1])]; } }
    $d = ['title' => $title, 'subtitle' => clean('subtitle', 240), 'description' => text('description', 12000), 'category_id' => (int) post('category_id') ?: null, 'level' => in_array(post('level'), ['All levels', 'Beginner', 'Intermediate', 'Advanced'], true) ? post('level') : 'All levels', 'language' => clean('language', 40) ?: 'English',
        'price' => max(0, (float) post('price', 0)), 'status' => $status, 'promo_url' => clean('promo_url', 300), 'outcomes' => json_encode(lines((string) post('outcomes')), JSON_UNESCAPED_UNICODE), 'requirements' => json_encode(lines((string) post('requirements')), JSON_UNESCAPED_UNICODE), 'audience' => text('audience', 1500), 'faq' => json_encode($faq, JSON_UNESCAPED_UNICODE),
        'certificate' => post('certificate') ? 1 : 0, 'pass_mark' => max(1, min(100, (int) post('pass_mark', 70))), 'updated_at' => now()];
    if (can('learning.manage')) { $d['featured'] = post('featured') ? 1 : 0; $d['instructor_id'] = (int) post('instructor_id') ?: null; }
    if ($img) { $d['thumbnail'] = $img; }
    if ($status === 'published' && (!$c || !$c['published_at'])) { $d['published_at'] = now(); }
    if ($c) { if ($d['title'] !== $c['title'] && !preg_match('/^' . preg_quote(slugify($d['title']), '/') . '/', $c['slug'])) { /* keep stable slug */ } update('courses', $id, $d); }
    else { $d['slug'] = unique_slug('courses', $title); $d['created_at'] = now(); if (!can('learning.manage')) { $d['instructor_id'] = uid(); } $id = insert('courses', $d); q('INSERT INTO course_modules(course_id,title,pos) VALUES(?,?,0)', [$id, 'Introduction']); }
    require_once APP . '/controllers/learning.php'; recalc_course($id); audit('course.save', 'course', $id);
    flash('ok', 'Course saved.' . ($status === 'published' ? '' : ' It is not visible to learners until you set the status to Published.')); redirect('/admin/courses/' . $id);
}

function admin_course_builder(array $p): void {
    $id = (int) $p['id']; $c = $id ? course_guard($id) : null; if (!$c) { admin_guard('learning.manage', 'learning.own'); }
    $mods = $c ? course_modules_with_lessons($id) : [];
    admin_render('admin/course_builder', ['c' => $c, 'mods' => $mods, 'cats' => rows('SELECT * FROM course_categories ORDER BY pos'), 'instructors' => is_super() ? rows("SELECT id, name FROM users WHERE is_super = 1 OR roles LIKE '%instructor%' ORDER BY name") : [], 'title' => 'Course', 'section' => $c ? $c['title'] : 'New course']);
}
function course_modules_with_lessons(int $id): array {
    $mods = rows('SELECT * FROM course_modules WHERE course_id = ? ORDER BY pos, id', [$id]);
    foreach ($mods as &$m) { $m['lessons'] = rows('SELECT l.*, (SELECT COUNT(*) FROM quiz_questions WHERE lesson_id = l.id) qn FROM lessons l WHERE module_id = ? ORDER BY pos, id', [$m['id']]); } unset($m);
    return $mods;
}
function admin_course_delete(array $p): void {
    $c = course_guard((int) $p['id']); if (!is_super()) { flash('error', 'Only the Super Admin can delete a course.'); redirect('/admin/courses'); }
    $id = (int) $c['id']; foreach (rows('SELECT file_path FROM lessons WHERE course_id = ? AND file_path IS NOT NULL', [$id]) as $f) { @unlink(DATA_DIR . '/private/' . $f['file_path']); }
    foreach (['quiz_questions' => 'lesson_id IN (SELECT id FROM lessons WHERE course_id = ' . $id . ')', 'lessons' => "course_id = $id", 'course_modules' => "course_id = $id", 'lesson_progress' => "course_id = $id", 'quiz_attempts' => "course_id = $id", 'course_reviews' => "course_id = $id", 'enrolments' => "course_id = $id", 'courses' => "id = $id"] as $t => $w) { db()->exec("DELETE FROM $t WHERE $w"); }
    audit('course.delete', 'course', $id, ['title' => $c['title']]); flash('ok', 'Course deleted.'); redirect('/admin/courses');
}
function admin_course_categories(): void {
    admin_guard('learning.manage'); $act = post('action');
    if ($act === 'add' && ($n = clean('name', 80)) !== '') { q('INSERT OR IGNORE INTO course_categories(slug,name,pos) VALUES(?,?,?)', [slugify($n), $n, 99]); }
    if ($act === 'delete') { q('UPDATE courses SET category_id = NULL WHERE category_id = ?', [(int) post('id')]); q('DELETE FROM course_categories WHERE id = ?', [(int) post('id')]); }
    redirect('/admin/courses');
}

function admin_module_action(array $p): void {
    $c = course_guard((int) $p['id']); $act = post('action'); $mid = (int) post('module_id');
    if ($act === 'add') { $pos = (int) val('SELECT COALESCE(MAX(pos),0)+1 FROM course_modules WHERE course_id = ?', [$c['id']]); q('INSERT INTO course_modules(course_id,title,pos) VALUES(?,?,?)', [$c['id'], clean('title', 160) ?: 'New section', $pos]); }
    elseif ($mid && val('SELECT 1 FROM course_modules WHERE id = ? AND course_id = ?', [$mid, $c['id']])) {
        if ($act === 'rename') { q('UPDATE course_modules SET title = ? WHERE id = ?', [clean('title', 160), $mid]); }
        if ($act === 'delete') { foreach (rows('SELECT id, file_path FROM lessons WHERE module_id = ?', [$mid]) as $l) { q('DELETE FROM quiz_questions WHERE lesson_id = ?', [$l['id']]); if ($l['file_path']) { @unlink(DATA_DIR . '/private/' . $l['file_path']); } } q('DELETE FROM lessons WHERE module_id = ?', [$mid]); q('DELETE FROM course_modules WHERE id = ?', [$mid]); }
        if ($act === 'up' || $act === 'down') { swap_order('course_modules', $mid, "course_id = " . (int) $c['id'], $act); }
    }
    require_once APP . '/controllers/learning.php'; recalc_course((int) $c['id']);
    redirect('/admin/courses/' . $c['id'] . '#content');
}
function swap_order(string $table, int $id, string $scope, string $dir): void {
    $list = rows("SELECT id FROM $table WHERE $scope ORDER BY pos, id"); $ids = array_column($list, 'id'); $i = array_search($id, array_map('intval', $ids), true);
    if ($i === false) { return; } $j = $dir === 'up' ? $i - 1 : $i + 1; if (!isset($ids[$j])) { return; }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; foreach ($ids as $pos => $x) { q("UPDATE $table SET pos = ? WHERE id = ?", [$pos, $x]); }
}
function admin_lesson_new(array $p): void {
    $c = course_guard((int) $p['id']); $mid = (int) post('module_id');
    if (!val('SELECT 1 FROM course_modules WHERE id = ? AND course_id = ?', [$mid, $c['id']])) { redirect('/admin/courses/' . $c['id']); }
    $kind = in_array(post('kind'), ['video', 'text', 'file', 'quiz'], true) ? post('kind') : 'text';
    $id = insert('lessons', ['course_id' => $c['id'], 'module_id' => $mid, 'title' => clean('title', 160) ?: 'Untitled lesson', 'kind' => $kind, 'pos' => (int) val('SELECT COALESCE(MAX(pos),0)+1 FROM lessons WHERE module_id = ?', [$mid])]);
    require_once APP . '/controllers/learning.php'; recalc_course((int) $c['id']);
    redirect('/admin/courses/' . $c['id'] . '/lesson/' . $id);
}
function admin_lesson_edit(array $p): void {
    $c = course_guard((int) $p['id']); $l = row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [(int) $p['lid'], $c['id']]); if (!$l) { not_found(); }
    admin_render('admin/lesson_edit', ['c' => $c, 'l' => $l, 'mods' => rows('SELECT id, title FROM course_modules WHERE course_id = ? ORDER BY pos, id', [$c['id']]), 'questions' => rows('SELECT * FROM quiz_questions WHERE lesson_id = ? ORDER BY pos, id', [$l['id']]), 'title' => 'Lesson', 'section' => $l['title']]);
}
function admin_lesson_save(array $p): void {
    $c = course_guard((int) $p['id']); $l = row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [(int) $p['lid'], $c['id']]); if (!$l) { not_found(); }
    $kind = in_array(post('kind'), ['video', 'text', 'file', 'quiz'], true) ? post('kind') : $l['kind'];
    $d = ['title' => clean('title', 160) ?: $l['title'], 'kind' => $kind, 'module_id' => (int) post('module_id') ?: $l['module_id'], 'body' => text('body', 30000), 'video_url' => clean('video_url', 300), 'duration_min' => max(0, (int) post('duration_min', 0)), 'is_preview' => post('is_preview') ? 1 : 0, 'pass_mark' => max(1, min(100, (int) post('pass_mark', 70)))];
    if ($d['video_url'] !== '' && !video_embed($d['video_url'])) { flash('warn', 'That video link is not a YouTube, Vimeo or direct .mp4 link, so it will not play. Use one of those or upload a video file.'); }
    if (!empty($_FILES['file']['name'])) {
        $f = save_private_upload('file', 'lessons', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'mp4', 'webm', 'png', 'jpg', 'jpeg', 'txt', 'csv'], 600 * 1048576);
        if (is_string($f)) { flash('error', $f); redirect('/admin/courses/' . $c['id'] . '/lesson/' . $l['id']); }
        if ($l['file_path']) { @unlink(DATA_DIR . '/private/' . $l['file_path']); }
        $d['file_path'] = $f['path']; $d['file_name'] = $f['name'];
    }
    if (post('remove_file') && $l['file_path']) { @unlink(DATA_DIR . '/private/' . $l['file_path']); $d['file_path'] = null; $d['file_name'] = null; }
    update('lessons', $l['id'], $d);
    if ($kind === 'quiz') {
        q('DELETE FROM quiz_questions WHERE lesson_id = ?', [$l['id']]); $pos = 0;
        foreach ((array) ($_POST['q_prompt'] ?? []) as $i => $prompt) {
            $prompt = trim(strip_tags((string) $prompt)); $opts = lines(strip_tags((string) ($_POST['q_options'][$i] ?? ''))); if ($prompt === '' || count($opts) < 2) { continue; }
            $ans = array_values(array_filter(array_map(fn($x) => (int) trim($x) - 1, explode(',', (string) ($_POST['q_answer'][$i] ?? ''))), fn($x) => $x >= 0 && $x < count($opts)));
            if (!$ans) { flash('warn', 'Question “' . excerpt($prompt, 40) . '” has no valid correct answer and was skipped.'); continue; }
            insert('quiz_questions', ['lesson_id' => $l['id'], 'prompt' => $prompt, 'kind' => count($ans) > 1 ? 'multi' : 'single', 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE), 'answer' => json_encode($ans), 'explanation' => mb_substr(trim(strip_tags((string) ($_POST['q_expl'][$i] ?? ''))), 0, 500), 'pos' => $pos++]);
        }
    }
    require_once APP . '/controllers/learning.php'; recalc_course((int) $c['id']);
    flash('ok', 'Lesson saved.'); redirect('/admin/courses/' . $c['id'] . '/lesson/' . $l['id']);
}
function admin_lesson_action(array $p): void {
    $c = course_guard((int) $p['id']); $l = row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [(int) $p['lid'], $c['id']]); if (!$l) { not_found(); }
    if ($p['do'] === 'delete') { q('DELETE FROM quiz_questions WHERE lesson_id = ?', [$l['id']]); q('DELETE FROM lesson_progress WHERE lesson_id = ?', [$l['id']]); if ($l['file_path']) { @unlink(DATA_DIR . '/private/' . $l['file_path']); } q('DELETE FROM lessons WHERE id = ?', [$l['id']]); flash('ok', 'Lesson deleted.'); }
    else { swap_order('lessons', (int) $l['id'], 'module_id = ' . (int) $l['module_id'], $p['do']); }
    require_once APP . '/controllers/learning.php'; recalc_course((int) $c['id']);
    redirect('/admin/courses/' . $c['id'] . '#content');
}

function admin_course_learners(array $p): void {
    $c = course_guard((int) $p['id']);
    if (qget('export') === 'csv') { csv_download('learners-' . $c['slug'] . '.csv', ['Name', 'Email', 'Status', 'Progress %', 'Enrolled', 'Completed'], array_map(fn($r) => [$r['name'], $r['email'], $r['status'], $r['progress'], $r['enrolled_at'], $r['completed_at']], rows('SELECT u.name, u.email, e.* FROM enrolments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY e.enrolled_at DESC', [$c['id']]))); }
    $items = rows('SELECT u.name, u.email, e.* FROM enrolments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY e.enrolled_at DESC', [$c['id']]);
    $quizAvg = (float) val('SELECT AVG(score) FROM (SELECT MAX(score) score FROM quiz_attempts WHERE course_id = ? GROUP BY user_id, lesson_id)', [$c['id']]);
    admin_render('admin/course_learners', compact('c', 'items', 'quizAvg') + ['title' => 'Learners', 'section' => 'Learners — ' . $c['title']]);
}
function admin_course_learners_action(array $p): void {
    $c = course_guard((int) $p['id']); $act = post('action');
    if ($act === 'enrol') {
        $u = row('SELECT * FROM users WHERE email = ?', [strtolower(clean('email', 160))]);
        if (!$u) { flash('error', 'No registered user has that email address. Ask them to create an account first.'); }
        else { q("INSERT INTO enrolments(user_id,course_id,status,price,enrolled_at) VALUES(?,?,'active',0,?) ON CONFLICT(user_id,course_id) DO UPDATE SET status='active'", [$u['id'], $c['id'], now()]); flash('ok', $u['name'] . ' now has access.'); audit('enrol.manual', 'course', $c['id'], ['user' => $u['id']]); }
    } elseif ($act === 'revoke') { q("UPDATE enrolments SET status = 'cancelled' WHERE id = ? AND course_id = ?", [(int) post('id'), $c['id']]); flash('ok', 'Access revoked.'); }
    elseif ($act === 'restore') { q("UPDATE enrolments SET status = 'active' WHERE id = ? AND course_id = ?", [(int) post('id'), $c['id']]); }
    require_once APP . '/controllers/learning.php'; recalc_course((int) $c['id']);
    redirect('/admin/courses/' . $c['id'] . '/learners');
}
function admin_review_toggle(array $p): void {
    admin_guard('learning.manage'); $r = row('SELECT * FROM course_reviews WHERE id = ?', [(int) $p['id']]); if (!$r) { not_found(); }
    update('course_reviews', $r['id'], ['status' => $r['status'] === 'published' ? 'hidden' : 'published']);
    require_once APP . '/controllers/learning.php'; recalc_course((int) $r['course_id']); redirect('/admin/courses');
}
