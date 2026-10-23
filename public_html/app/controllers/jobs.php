<?php
/* =====================================================================
 * Donums Careers — public job board, applications, candidate tracking,
 * employer vacancy submission. The recruiter console is in admin/ats.
 * ===================================================================== */

const ATS_STAGES = ['Applied', 'Screening', 'Shortlisted', 'Interview', 'Assessment', 'Offer', 'Hired', 'Rejected'];
const JOB_TYPES = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Temporary', 'Freelance'];
const JOB_MODES = ['On-site', 'Hybrid', 'Remote'];

function job_open_sql(): string { return "j.status = 'published' AND (j.deadline IS NULL OR j.deadline = '' OR j.deadline >= '" . date('Y-m-d') . "')"; }
function job_url(array $j): string { return '/jobs/' . $j['id'] . '-' . ($j['slug'] ?: slugify($j['title'])); }

function csv_list(?string $s): array { return array_values(array_filter(array_map('trim', preg_split('/[,\n;]+/', (string) $s)), fn($x) => $x !== '')); }

/** Transparent fit score: skills 50, experience 25, qualification 15, completeness 10. */
function fit_score(array $job, array $a): array {
    $req = array_map('mb_strtolower', csv_list($job['required_skills'] ?? ''));
    $hay = mb_strtolower(($a['skills'] ?? '') . ' ' . ($a['cover_letter'] ?? '') . ' ' . ($a['qualification'] ?? ''));
    $matched = []; foreach ($req as $r) { if ($r !== '' && mb_strpos($hay, $r) !== false) { $matched[] = $r; } }
    $skillsPts = $req ? (int) round(50 * count($matched) / count($req)) : 25;
    $minExp = (int) ($job['min_experience'] ?? 0); $yrs = (int) ($a['experience_years'] ?? 0);
    $expPts = $minExp > 0 ? (int) round(25 * min(1, $yrs / $minExp)) : 20;
    $quals = array_map('mb_strtolower', csv_list($job['required_quals'] ?? '')); $q = mb_strtolower((string) ($a['qualification'] ?? ''));
    $qualPts = 10;
    if ($quals) { $qualPts = 4; foreach ($quals as $x) { if ($x !== '' && mb_strpos($q, $x) !== false) { $qualPts = 15; break; } } }
    $comp = (mb_strlen((string) ($a['cover_letter'] ?? '')) >= 100 ? 5 : 0) + (!empty($a['linkedin']) ? 2 : 0) + (!empty($a['phone']) ? 3 : 0);
    $score = max(0, min(100, $skillsPts + $expPts + $qualPts + $comp));
    return [$score, ['skills' => $skillsPts, 'experience' => $expPts, 'qualification' => $qualPts, 'completeness' => $comp], $matched];
}

function page_jobs(): void {
    $s = qget('q'); $loc = qget('location'); $type = qget('type'); $mode = qget('mode'); $cat = qget('category'); $page = max(1, qint('page', 1));
    $w = [job_open_sql()]; $p = [];
    if ($s !== '') { $w[] = '(j.title LIKE ? OR j.organisation LIKE ? OR j.description LIKE ? OR j.required_skills LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%"); }
    if ($loc !== '') { $w[] = 'j.location LIKE ?'; $p[] = "%$loc%"; }
    if (in_array($type, JOB_TYPES, true)) { $w[] = 'j.type = ?'; $p[] = $type; }
    if (in_array($mode, JOB_MODES, true)) { $w[] = 'j.work_mode = ?'; $p[] = $mode; }
    if ($cat !== '') { $w[] = 'j.category = ?'; $p[] = $cat; }
    $from = 'FROM jobs j WHERE ' . implode(' AND ', $w);
    $pg = paginate((int) val("SELECT COUNT(*) $from", $p), 12, $page);
    $jobs = rows("SELECT j.* $from ORDER BY j.featured DESC, j.published_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
    $cats = array_column(rows("SELECT DISTINCT category FROM jobs j WHERE " . job_open_sql() . " AND category != '' ORDER BY category"), 'category');
    render('jobs/index', compact('jobs', 'pg', 'cats', 's', 'loc', 'type', 'mode', 'cat') + ['title' => 'Donums Careers — jobs and opportunities', 'desc' => 'Browse current vacancies, apply in minutes and track your application on Donums Careers.', 'active' => 'jobs']);
}

function page_job(array $p): void {
    $j = row('SELECT * FROM jobs WHERE id = ?', [(int) $p['id']]);
    if (!$j || ($j['status'] !== 'published' && !can('ats.manage'))) { not_found('This vacancy is no longer available.'); }
    q('UPDATE jobs SET views = views + 1 WHERE id = ?', [$j['id']]);
    $closed = $j['status'] !== 'published' || ($j['deadline'] && $j['deadline'] < date('Y-m-d'));
    $questions = json_dec($j['screening']);
    $similar = rows("SELECT j.* FROM jobs j WHERE " . job_open_sql() . " AND j.id != ? AND (j.category = ? OR j.location = ?) ORDER BY j.published_at DESC LIMIT 3", [$j['id'], $j['category'], $j['location']]);
    $schema = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $j['title'], 'description' => strip_tags(md((string) $j['description'])), 'datePosted' => date('Y-m-d', strtotime($j['published_at'] ?: $j['created_at'])),
        'employmentType' => strtoupper(str_replace('-', '_', $j['type'])), 'hiringOrganization' => ['@type' => 'Organization', 'name' => $j['confidential'] ? 'Confidential employer' : ($j['organisation'] ?: cfg('site_name'))],
        'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $j['location'], 'addressCountry' => 'KE']]];
    if ($j['deadline']) { $schema['validThrough'] = $j['deadline']; }
    if ($j['work_mode'] === 'Remote') { $schema['jobLocationType'] = 'TELECOMMUTE'; }
    render('jobs/show', compact('j', 'closed', 'questions', 'similar') + ['title' => $j['title'] . ($j['confidential'] ? '' : ' — ' . $j['organisation']), 'desc' => excerpt($j['description'], 160), 'active' => 'jobs', 'schema' => $schema]);
}

function post_apply(array $p): void {
    $jid = (int) $p['id'];
    $j = $jid ? row("SELECT * FROM jobs WHERE id = ? AND status = 'published'", [$jid]) : null;
    $back = $j ? job_url($j) : '/jobs/talent-network';
    if ($jid && !$j) { not_found(); }
    if (!spam_check(4)) { flash('error', 'We could not verify that submission. Please wait a moment and try again.'); redirect($back); }
    if (!rate_limit('apply-' . client_ip(), 8, 3600)) { flash('error', 'Too many applications from this connection. Please try again later.'); redirect($back); }
    $name = clean('name', 120); $email = strtolower(clean('email', 160)); $phone = clean('phone', 40);
    if ($name === '' || !mail_valid($email) || strlen(preg_replace('/\D/', '', $phone)) < 9) { flash('error', 'Please enter your full name, a valid email address and a phone number.'); redirect($back); }
    if ($j && val('SELECT 1 FROM applications WHERE job_id = ? AND email = ?', [$j['id'], $email])) { flash('info', 'You have already applied for this role. Use your reference to check progress.'); redirect('/jobs/status'); }
    $cv = save_private_upload('cv', 'cv', ['pdf', 'doc', 'docx'], 5 * 1048576);
    if (is_string($cv)) { flash('error', 'CV: ' . $cv); redirect($back); }
    $answers = []; foreach (json_dec($j['screening'] ?? '') as $i => $qq) { $answers[] = ['q' => $qq, 'a' => mb_substr(trim(strip_tags((string) ($_POST['screen'][$i] ?? ''))), 0, 1500)]; }
    $a = ['skills' => text('skills', 800), 'experience_years' => max(0, min(60, (int) post('experience_years', 0))), 'qualification' => clean('qualification', 200), 'cover_letter' => text('cover_letter', 5000), 'linkedin' => clean('linkedin', 200), 'phone' => $phone];
    [$score, $break, $matched] = $j ? fit_score($j, $a) : [0, [], []];
    $ref = ref_code('APP'); $token = bin2hex(random_bytes(12));
    $id = insert('applications', ['ref' => $ref, 'token' => $token, 'job_id' => $j['id'] ?? null, 'user_id' => uid() ?: null, 'name' => $name, 'email' => $email, 'phone' => $phone, 'location' => clean('location', 120), 'linkedin' => $a['linkedin'],
        'skills' => $a['skills'], 'experience_years' => $a['experience_years'], 'qualification' => $a['qualification'], 'expected_salary' => clean('expected_salary', 60), 'notice_period' => clean('notice_period', 60), 'cover_letter' => $a['cover_letter'],
        'cv_path' => $cv['path'], 'cv_name' => $cv['name'], 'answers' => json_encode($answers, JSON_UNESCAPED_UNICODE), 'fit_score' => $score, 'fit_breakdown' => json_encode($break), 'matched_skills' => json_encode($matched),
        'stage' => 'Applied', 'talent_pool' => $j ? 0 : 1, 'source' => clean('source', 60) ?: 'Careers site', 'created_at' => now(), 'stage_changed_at' => now(), 'updated_at' => now()]);
    insert('app_events', ['application_id' => $id, 'kind' => 'applied', 'to_stage' => 'Applied', 'note' => 'Application submitted', 'visible_to_candidate' => 1, 'created_at' => now()]);
    q('INSERT OR IGNORE INTO contacts(email,name,phone,source,subscribed,created_at) VALUES(?,?,?,?,0,?)', [$email, $name, $phone, 'Job application', now()]);
    queue_template('application_received', $email, ['name' => $name, 'job' => $j['title'] ?? 'our talent network', 'organisation' => $j ? ($j['confidential'] ? 'the employer' : ($j['organisation'] ?: 'Donums')) : 'Donums', 'ref' => $ref, 'cta_url' => abs_url('/jobs/status', ['ref' => $ref, 'email' => $email]), 'cta_label' => 'Track my application'], ['dept' => 'Careers', 'from' => 'careers@' . cfg('domain')]);
    notify_staff('ats.manage', 'application', 'New application: ' . $name, ($j['title'] ?? 'Talent network') . ' · fit ' . $score . '%', '/admin/ats/application/' . $id);
    process_outbox(2);
    redirect('/jobs/status?ref=' . $ref . '&email=' . rawurlencode($email) . '&new=1');
}

function page_job_status(): void {
    $ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', qget('ref'))); $email = strtolower(qget('email')); $a = null; $err = '';
    if ($ref !== '' && $email !== '') {
        if (!rate_limit('jstat-' . client_ip(), 20, 600)) { $err = 'Too many lookups. Please wait a few minutes.'; }
        else { $a = row('SELECT a.*, j.title job_title, j.organisation, j.confidential FROM applications a LEFT JOIN jobs j ON j.id=a.job_id WHERE a.ref = ? AND a.email = ?', [$ref, $email]); if (!$a) { $err = 'We could not find an application with that reference and email address.'; } }
    }
    $events = $a ? rows('SELECT * FROM app_events WHERE application_id = ? AND visible_to_candidate = 1 ORDER BY id', [$a['id']]) : [];
    $interviews = $a ? rows("SELECT * FROM interviews WHERE application_id = ? AND status = 'Scheduled' AND starts_at >= ? ORDER BY starts_at", [$a['id'], date('Y-m-d H:i:s', time() - 3600)]) : [];
    render('jobs/status', compact('a', 'ref', 'email', 'err', 'events', 'interviews') + ['title' => 'Track your application', 'new' => !empty($_GET['new']), 'noindex' => true, 'active' => 'jobs']);
}

function page_talent(): void { render('jobs/talent', ['title' => 'Join the Donums talent network', 'desc' => 'Share your CV once and we will contact you when a suitable opportunity opens.', 'active' => 'jobs']); }

function page_employers(): void { render('jobs/employers', ['title' => 'Post a vacancy', 'desc' => 'Advertise a role to Donums Careers candidates, or let our team run the recruitment for you.', 'active' => 'jobs']); }
function post_employers(): void {
    if (!spam_check()) { flash('error', 'We could not verify that submission. Please try again.'); redirect('/jobs/employers'); }
    if (!rate_limit('emp-' . client_ip(), 5, 3600)) { flash('error', 'Too many submissions from this connection.'); redirect('/jobs/employers'); }
    $title = clean('title', 140); $org = clean('organisation', 140); $email = strtolower(clean('email', 160));
    if ($title === '' || $org === '' || !mail_valid($email) || text('description', 8000) === '') { flash('error', 'Please complete the organisation, role title, description and a valid contact email.'); redirect('/jobs/employers'); }
    $ref = ref_code('JOB');
    $id = insert('jobs', ['ref' => $ref, 'slug' => slugify($title), 'title' => $title, 'organisation' => $org, 'employer_email' => $email, 'employer_phone' => clean('phone', 40), 'location' => clean('location', 120), 'work_mode' => in_array(post('work_mode'), JOB_MODES, true) ? post('work_mode') : 'On-site',
        'type' => in_array(post('type'), JOB_TYPES, true) ? post('type') : 'Full-time', 'category' => clean('category', 80), 'salary_text' => clean('salary', 80), 'description' => text('description', 8000), 'requirements' => text('requirements', 4000), 'required_skills' => clean('skills', 400),
        'min_experience' => max(0, (int) post('min_experience', 0)), 'deadline' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('deadline')) ? post('deadline') : null, 'status' => 'pending', 'source' => 'employer', 'created_at' => now(), 'updated_at' => now()]);
    queue_template('job_received', $email, ['job' => $title, 'ref' => $ref], ['dept' => 'Careers', 'from' => 'careers@' . cfg('domain')]);
    notify_staff('ats.manage', 'job', 'Vacancy awaiting review: ' . $title, $org, '/admin/ats/job/' . $id);
    flash('ok', 'Thank you. Your vacancy (' . $ref . ') has been received and will be reviewed before it is published.');
    redirect('/jobs/employers');
}
