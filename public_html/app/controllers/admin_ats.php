<?php
/* Admin → Careers & ATS: vacancies, pipeline, applicant profiles, interviews, scorecards, reports. */

function admin_routes_ats(): void {
    route('GET', '/admin/ats', 'admin_ats');
    route('POST', '/admin/ats/move', 'admin_ats_move');
    route('GET', '/admin/ats/jobs', 'admin_ats_jobs');
    route('GET', '/admin/ats/job/new', 'admin_ats_job_form');
    route('GET', '/admin/ats/job/{id:\d+}', 'admin_ats_job_form');
    route('POST', '/admin/ats/job/save', 'admin_ats_job_save');
    route('POST', '/admin/ats/job/{id:\d+}/status', 'admin_ats_job_status');
    route('GET', '/admin/ats/applications', 'admin_ats_applications');
    route('POST', '/admin/ats/applications/bulk', 'admin_ats_bulk');
    route('GET', '/admin/ats/application/{id:\d+}', 'admin_ats_application');
    route('POST', '/admin/ats/application/{id:\d+}/{do:stage|note|rate|tags|interview|scorecard|pool|delete}', 'admin_ats_application_action');
    route('GET', '/admin/ats/reports', 'admin_ats_reports');
}

function ats_guard(): array { return admin_guard('ats.manage'); }

function ats_change_stage(array $a, string $stage, string $msg = '', bool $notify = false): void {
    if (!in_array($stage, ATS_STAGES, true) || $stage === $a['stage']) { return; }
    update('applications', $a['id'], ['stage' => $stage, 'stage_changed_at' => now(), 'updated_at' => now(), 'reject_reason' => $stage === 'Rejected' ? ($msg ?: $a['reject_reason']) : $a['reject_reason']]);
    insert('app_events', ['application_id' => $a['id'], 'user_id' => uid(), 'kind' => 'stage', 'from_stage' => $a['stage'], 'to_stage' => $stage, 'note' => $notify ? $msg : null, 'visible_to_candidate' => 1, 'created_at' => now()]);
    if ($notify) {
        $job = $a['job_id'] ? row('SELECT * FROM jobs WHERE id = ?', [$a['job_id']]) : null;
        $vars = ['name' => $a['name'], 'job' => $job['title'] ?? 'our talent network', 'ref' => $a['ref'], 'stage' => $stage, 'message' => $msg, 'cta_url' => abs_url('/jobs/status', ['ref' => $a['ref'], 'email' => $a['email']]), 'cta_label' => 'View your application'];
        queue_template($stage === 'Rejected' ? 'application_rejected' : 'application_stage', $a['email'], $vars, ['dept' => 'Careers', 'from' => 'careers@' . cfg('domain')]);
    }
    if ($a['user_id']) { notify_user((int) $a['user_id'], 'application', 'Your application moved to ' . $stage, $job['title'] ?? '', '/jobs/status?ref=' . $a['ref'] . '&email=' . rawurlencode($a['email'])); }
}

function admin_ats(): void {
    ats_guard(); $jobId = qint('job');
    $jobs = rows("SELECT j.id, j.title, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) n FROM jobs j WHERE j.status IN ('published','closed','pending') ORDER BY j.status = 'published' DESC, j.id DESC");
    $w = ["a.stage != 'Rejected' OR a.stage_changed_at >= '" . date('Y-m-d', strtotime('-30 days')) . "'"]; $par = [];
    if ($jobId) { $w = ['(' . $w[0] . ')', 'a.job_id = ?']; $par[] = $jobId; }
    $apps = rows('SELECT a.id, a.name, a.stage, a.fit_score, a.rating, a.created_at, a.stage_changed_at, a.talent_pool, j.title job_title FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE ' . implode(' AND ', $w) . ' ORDER BY a.fit_score DESC, a.created_at DESC LIMIT 400', $par);
    $cols = array_fill_keys(ATS_STAGES, []); foreach ($apps as $a) { $cols[$a['stage']][] = $a; }
    $stats = ['open' => (int) val("SELECT COUNT(*) FROM jobs WHERE status = 'published'"), 'pending' => (int) val("SELECT COUNT(*) FROM jobs WHERE status = 'pending'"), 'new' => (int) val("SELECT COUNT(*) FROM applications WHERE stage = 'Applied'"), 'interviews' => (int) val("SELECT COUNT(*) FROM interviews WHERE status = 'Scheduled' AND starts_at >= ?", [now()]), 'hired' => (int) val("SELECT COUNT(*) FROM applications WHERE stage = 'Hired' AND stage_changed_at >= ?", [date('Y-m-d', strtotime('-90 days'))])];
    admin_render('admin/ats', compact('jobs', 'jobId', 'cols', 'stats') + ['title' => 'Careers & ATS', 'section' => 'Recruitment pipeline']);
}
function admin_ats_move(): void {
    ats_guard(); $a = row('SELECT * FROM applications WHERE id = ?', [(int) post('id')]); $st = (string) post('stage');
    if (!$a || !in_array($st, ATS_STAGES, true)) { json_out(['ok' => false], 422); }
    ats_change_stage($a, $st, '', false); json_out(['ok' => true]);
}

function admin_ats_jobs(): void {
    ats_guard(); $st = qget('status');
    $items = rows('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) apps, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.stage = \'Applied\') fresh FROM jobs j ' . ($st ? 'WHERE j.status = ?' : '') . ' ORDER BY j.status = \'pending\' DESC, j.id DESC', $st ? [$st] : []);
    $counts = array_column(rows('SELECT status, COUNT(*) n FROM jobs GROUP BY status'), 'n', 'status');
    admin_render('admin/ats_jobs', compact('items', 'st', 'counts') + ['title' => 'Vacancies', 'section' => 'Vacancies']);
}
function admin_ats_job_form(array $p = []): void {
    ats_guard(); $j = !empty($p['id']) ? row('SELECT * FROM jobs WHERE id = ?', [(int) $p['id']]) : null; if (!empty($p['id']) && !$j) { not_found(); }
    admin_render('admin/ats_job_form', compact('j') + ['title' => 'Vacancy', 'section' => $j ? 'Edit vacancy' : 'New vacancy']);
}
function admin_ats_job_save(): void {
    ats_guard(); $id = (int) post('id'); $old = $id ? row('SELECT * FROM jobs WHERE id = ?', [$id]) : null;
    $title = clean('title', 140); if ($title === '' || text('description', 10000) === '') { flash('error', 'A title and description are required.'); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ats/jobs'); }
    $status = in_array(post('status'), ['draft', 'pending', 'published', 'closed', 'rejected'], true) ? post('status') : 'draft';
    $d = ['title' => $title, 'organisation' => clean('organisation', 140), 'employer_email' => clean('employer_email', 160), 'employer_phone' => clean('employer_phone', 40), 'location' => clean('location', 120), 'work_mode' => in_array(post('work_mode'), JOB_MODES, true) ? post('work_mode') : 'On-site',
        'type' => in_array(post('type'), JOB_TYPES, true) ? post('type') : 'Full-time', 'category' => clean('category', 80), 'salary_text' => clean('salary_text', 80), 'description' => text('description', 10000), 'requirements' => text('requirements', 6000), 'required_skills' => clean('required_skills', 500),
        'min_experience' => max(0, (int) post('min_experience', 0)), 'required_quals' => clean('required_quals', 300), 'screening' => json_encode(array_slice(lines((string) post('screening')), 0, 6), JSON_UNESCAPED_UNICODE), 'deadline' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('deadline')) ? post('deadline') : null,
        'status' => $status, 'featured' => post('featured') ? 1 : 0, 'confidential' => post('confidential') ? 1 : 0, 'updated_at' => now()];
    if ($status === 'published' && (!$old || !$old['published_at'])) { $d['published_at'] = now(); }
    if ($old) { update('jobs', $id, $d); } else { $d += ['ref' => ref_code('JOB'), 'slug' => slugify($title), 'source' => 'internal', 'owner_id' => uid(), 'created_at' => now()]; if (empty($d['published_at']) && $status === 'published') { $d['published_at'] = now(); } $id = insert('jobs', $d); }
    if ($old && $old['status'] !== 'published' && $status === 'published' && mail_valid((string) $d['employer_email']) && $old['source'] === 'employer') { queue_template('job_published', $d['employer_email'], ['job' => $title, 'cta_url' => abs_url('/jobs/' . $id . '-' . slugify($title)), 'cta_label' => 'View your vacancy'], ['dept' => 'Careers', 'from' => 'careers@' . cfg('domain')]); }
    audit('job.save', 'job', $id); flash('ok', 'Vacancy saved.'); redirect('/admin/ats/job/' . $id);
}
function admin_ats_job_status(array $p): void {
    ats_guard(); $j = row('SELECT * FROM jobs WHERE id = ?', [(int) $p['id']]); if (!$j) { not_found(); }
    $st = in_array(post('status'), ['published', 'closed', 'rejected', 'pending', 'draft'], true) ? post('status') : $j['status'];
    update('jobs', $j['id'], ['status' => $st, 'updated_at' => now(), 'published_at' => $st === 'published' ? ($j['published_at'] ?: now()) : $j['published_at']]);
    if ($st === 'published' && $j['status'] !== 'published' && $j['source'] === 'employer' && mail_valid((string) $j['employer_email'])) { queue_template('job_published', $j['employer_email'], ['job' => $j['title'], 'cta_url' => abs_url('/jobs/' . $j['id'] . '-' . $j['slug']), 'cta_label' => 'View your vacancy'], ['dept' => 'Careers', 'from' => 'careers@' . cfg('domain')]); process_outbox(3); }
    audit('job.status', 'job', $j['id'], ['to' => $st]); flash('ok', 'Vacancy ' . $st . '.'); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ats/jobs');
}

function admin_ats_applications(): void {
    ats_guard(); $w = ['1=1']; $par = []; $s = qget('q'); $stage = qget('stage'); $job = qint('job'); $minFit = qint('fit'); $pool = qget('pool'); $page = max(1, qint('page', 1));
    if ($s !== '') { $w[] = '(a.name LIKE ? OR a.email LIKE ? OR a.skills LIKE ? OR a.ref LIKE ? OR a.qualification LIKE ?)'; array_push($par, "%$s%", "%$s%", "%$s%", "%$s%", "%$s%"); }
    if (in_array($stage, ATS_STAGES, true)) { $w[] = 'a.stage = ?'; $par[] = $stage; }
    if ($job) { $w[] = 'a.job_id = ?'; $par[] = $job; }
    if ($minFit) { $w[] = 'a.fit_score >= ?'; $par[] = $minFit; }
    if ($pool) { $w[] = 'a.talent_pool = 1'; }
    $sql = ' FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE ' . implode(' AND ', $w);
    if (qget('export') === 'csv') { csv_download('applicants-' . date('Ymd') . '.csv', ['Ref', 'Name', 'Email', 'Phone', 'Role', 'Stage', 'Fit', 'Rating', 'Experience', 'Qualification', 'Applied'], array_map(fn($r) => [$r['ref'], $r['name'], $r['email'], $r['phone'], $r['job_title'], $r['stage'], $r['fit_score'], $r['rating'], $r['experience_years'], $r['qualification'], $r['created_at']], rows('SELECT a.*, j.title job_title' . $sql . ' ORDER BY a.id DESC', $par))); }
    $pg = paginate((int) val('SELECT COUNT(*)' . $sql, $par), 25, $page);
    $items = rows('SELECT a.*, j.title job_title' . $sql . " ORDER BY a.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $par);
    $jobs = rows('SELECT id, title FROM jobs ORDER BY id DESC');
    admin_render('admin/ats_applications', compact('items', 'pg', 's', 'stage', 'job', 'minFit', 'pool', 'jobs') + ['title' => 'Applicants', 'section' => 'Applicants']);
}
function admin_ats_bulk(): void {
    ats_guard(); $ids = array_map('intval', (array) ($_POST['ids'] ?? [])); $stage = (string) post('stage'); $n = 0;
    foreach ($ids as $id) { if ($a = row('SELECT * FROM applications WHERE id = ?', [$id])) { ats_change_stage($a, $stage, text('message', 1000), !empty($_POST['notify'])); $n++; } }
    if (!empty($_POST['notify'])) { process_outbox(10); }
    flash('ok', "$n applicant(s) moved to $stage."); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ats/applications');
}

function admin_ats_application(array $p): void {
    ats_guard(); $a = row('SELECT a.*, j.title job_title, j.organisation, j.required_skills job_skills FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.id = ?', [(int) $p['id']]); if (!$a) { not_found(); }
    $events = rows('SELECT e.*, u.name uname FROM app_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.application_id = ? ORDER BY e.id DESC', [$a['id']]);
    $interviews = rows('SELECT * FROM interviews WHERE application_id = ? ORDER BY starts_at', [$a['id']]);
    $cards = rows('SELECT s.*, u.name uname FROM scorecards s LEFT JOIN users u ON u.id = s.user_id WHERE s.application_id = ? ORDER BY s.id DESC', [$a['id']]);
    $others = rows('SELECT a2.id, a2.ref, a2.stage, j.title FROM applications a2 LEFT JOIN jobs j ON j.id = a2.job_id WHERE a2.email = ? AND a2.id != ?', [$a['email'], $a['id']]);
    $avg = $cards ? array_sum(array_column($cards, 'score')) / count($cards) : 0;
    admin_render('admin/ats_application', compact('a', 'events', 'interviews', 'cards', 'others', 'avg') + ['title' => $a['name'], 'section' => 'Applicant — ' . $a['name']]);
}
function admin_ats_application_action(array $p): void {
    ats_guard(); $a = row('SELECT * FROM applications WHERE id = ?', [(int) $p['id']]); if (!$a) { not_found(); }
    $back = '/admin/ats/application/' . $a['id'];
    switch ($p['do']) {
        case 'stage': ats_change_stage($a, (string) post('stage'), text('message', 1000), !empty($_POST['notify'])); if (!empty($_POST['notify'])) { process_outbox(3); } flash('ok', 'Stage updated.'); break;
        case 'note': if (($t = text('note', 3000)) !== '') { insert('app_events', ['application_id' => $a['id'], 'user_id' => uid(), 'kind' => 'note', 'note' => $t, 'visible_to_candidate' => 0, 'created_at' => now()]); } break;
        case 'rate': update('applications', $a['id'], ['rating' => max(0, min(5, (int) post('rating')))]); break;
        case 'tags': update('applications', $a['id'], ['tags' => clean('tags', 200)]); break;
        case 'pool': update('applications', $a['id'], ['talent_pool' => $a['talent_pool'] ? 0 : 1]); flash('ok', $a['talent_pool'] ? 'Removed from the talent pool.' : 'Added to the talent pool.'); break;
        case 'scorecard': insert('scorecards', ['application_id' => $a['id'], 'user_id' => uid(), 'stage' => $a['stage'], 'score' => max(1, min(5, (int) post('score', 3))), 'recommendation' => in_array(post('recommendation'), ['Strong yes', 'Yes', 'Maybe', 'No'], true) ? post('recommendation') : 'Maybe', 'comment' => text('comment', 2000), 'created_at' => now()]); flash('ok', 'Scorecard added.'); break;
        case 'interview':
            $t = strtotime((string) post('starts_at')); if (!$t) { flash('error', 'Choose a date and time for the interview.'); break; }
            $iv = ['application_id' => $a['id'], 'title' => clean('title', 120) ?: 'Interview', 'starts_at' => date('Y-m-d H:i:s', $t), 'duration_min' => max(10, (int) post('duration_min', 45)), 'mode' => in_array(post('mode'), ['Video', 'Phone', 'On-site'], true) ? post('mode') : 'Video', 'location' => clean('location', 250), 'interviewers' => clean('interviewers', 200), 'notes' => text('notes', 1000), 'created_by' => uid(), 'created_at' => now()];
            insert('interviews', $iv); insert('app_events', ['application_id' => $a['id'], 'user_id' => uid(), 'kind' => 'interview', 'note' => 'Interview scheduled for ' . fmt_dt($iv['starts_at']), 'visible_to_candidate' => 1, 'created_at' => now()]);
            if ($a['stage'] !== 'Interview') { ats_change_stage($a, 'Interview', '', false); }
            if (!empty($_POST['notify'])) { $job = $a['job_id'] ? row('SELECT title FROM jobs WHERE id = ?', [$a['job_id']]) : null; queue_template('interview_invite', $a['email'], ['name' => $a['name'], 'job' => $job['title'] ?? 'your application', 'when' => fmt_dt($iv['starts_at']) . ' (' . $iv['duration_min'] . ' min)', 'mode' => $iv['mode'], 'where' => $iv['location'] ?: 'We will share the details shortly', 'message' => text('message', 800), 'cta_url' => abs_url('/jobs/status', ['ref' => $a['ref'], 'email' => $a['email']]), 'cta_label' => 'View interview details'], ['dept' => 'Careers', 'from' => 'careers@' . cfg('domain')]); process_outbox(3); }
            flash('ok', 'Interview scheduled' . (!empty($_POST['notify']) ? ' and the candidate was emailed.' : '.')); break;
        case 'delete':
            if (!is_super()) { flash('error', 'Only the Super Admin can permanently delete an applicant.'); break; }
            if ($a['cv_path']) { @unlink(DATA_DIR . '/private/' . (str_starts_with($a['cv_path'], 'cv/') ? $a['cv_path'] : 'cv/' . basename($a['cv_path']))); }
            foreach (['app_events', 'interviews', 'scorecards'] as $t) { q("DELETE FROM $t WHERE application_id = ?", [$a['id']]); } q('DELETE FROM applications WHERE id = ?', [$a['id']]);
            audit('applicant.delete', 'application', $a['id']); flash('ok', 'Applicant and CV permanently deleted.'); redirect('/admin/ats/applications');
    }
    redirect($back);
}

function admin_ats_reports(): void {
    ats_guard(); $since = date('Y-m-d', strtotime('-90 days'));
    $funnel = array_column(rows('SELECT stage, COUNT(*) n FROM applications WHERE created_at >= ? GROUP BY stage', [$since]), 'n', 'stage');
    $perJob = rows("SELECT j.id, j.title, j.status, COUNT(a.id) apps, SUM(a.stage='Interview') iv, SUM(a.stage='Offer') off, SUM(a.stage='Hired') hired, ROUND(AVG(a.fit_score)) fit FROM jobs j LEFT JOIN applications a ON a.job_id = j.id GROUP BY j.id ORDER BY apps DESC LIMIT 20");
    $sources = rows('SELECT source, COUNT(*) n FROM applications GROUP BY source ORDER BY n DESC');
    $tth = val("SELECT ROUND(AVG(julianday(stage_changed_at) - julianday(created_at)), 1) FROM applications WHERE stage = 'Hired'");
    $weekly = rows("SELECT strftime('%Y-%W', created_at) w, COUNT(*) n FROM applications WHERE created_at >= ? GROUP BY w ORDER BY w", [$since]);
    admin_render('admin/ats_reports', compact('funnel', 'perJob', 'sources', 'tth', 'weekly') + ['title' => 'Reports', 'section' => 'Recruitment reports']);
}
