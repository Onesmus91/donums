<?php
/* =====================================================================
 * One-time, non-destructive import of the previous JSON/NDJSON data
 * (found in /data) into the new database. Source files are left in place.
 * ===================================================================== */

function legacy_read(string $name): array {
    $f = DATA_DIR . '/' . $name;
    if (!is_file($f)) { return []; }
    if (str_ends_with($name, '.ndjson')) {
        $o = [];
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) { $j = json_decode($l, true); if (is_array($j)) { $o[] = $j; } }
        return $o;
    }
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}
function legacy_dt(?string $s): string { $t = $s ? strtotime($s) : false; return $t ? date('Y-m-d H:i:s', $t) : now(); }

function legacy_import(PDO $pdo): void {
    $log = [];
    $count = fn(string $t) => (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();

    // --- Super admin keeps the existing password ---
    $adm = legacy_read('admin.json');
    if (!empty($adm['hash']) && !$count('users')) {
        $pdo->prepare("INSERT INTO users(email,name,password_hash,is_super,email_verified_at,created_at) VALUES(?,?,?,1,?,?)")
            ->execute([strtolower($adm['email'] ?? 'admin@donums.co.ke'), 'Super Admin', $adm['hash'], now(), now()]);
        $log[] = 'super admin';
    }

    // --- CRM ---
    $ops = legacy_read('operations.json');
    foreach (legacy_read('requests.ndjson') as $r) {
        $id = $r['id'] ?? ''; if ($id === '') { continue; }
        if (strtolower($r['service'] ?? '') === 'partner application') {
            $pdo->prepare('INSERT OR IGNORE INTO partners(ref,name,contact,kind,location,details,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$id, $r['name'] ?? '', $r['contact'] ?? '', $r['need'] ?? '', $r['location'] ?? '', $r['details'] ?? '', legacy_dt($r['time'] ?? ''), now()]);
            continue;
        }
        $o = $ops[$id] ?? [];
        $pdo->prepare('INSERT OR IGNORE INTO requests(ref,service,need,name,contact,organisation,location,details,source,status,quote,provider,provider_cost,payment,followup,notes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $r['service'] ?? '', $r['need'] ?? '', $r['name'] ?? '', $r['contact'] ?? '', $r['organisation'] ?? '', $r['location'] ?? '', $r['details'] ?? '', 'legacy',
                $o['status'] ?? 'New', $o['quote'] ?? '', $o['provider'] ?? '', $o['provider_cost'] ?? '', $o['payment'] ?? 'Unpaid', $o['followup'] ?? '', $o['notes'] ?? '', legacy_dt($r['time'] ?? ''), now()]);
    }
    foreach (legacy_read('partners.json') as $id => $p) {
        $pdo->prepare('INSERT OR REPLACE INTO partners(ref,name,contact,kind,location,details,verification,partner_status,notes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $p['name'] ?? '', $p['contact'] ?? '', $p['need'] ?? '', $p['location'] ?? '', $p['details'] ?? '', $p['verification'] ?? 'Pending', $p['partner_status'] ?? 'Applicant', $p['notes'] ?? '', legacy_dt($p['time'] ?? ''), now()]);
    }
    foreach (legacy_read('jobs.json') as $id => $j) {
        $pdo->prepare('INSERT OR IGNORE INTO deliveries(request_ref,stage,provider,value,provider_cost,payment,notes,updated_at) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$id, $j['stage'] ?? 'Assigned', $j['provider'] ?? '', $j['value'] ?? '', $j['provider_cost'] ?? '', $j['payment'] ?? 'Unpaid', $j['notes'] ?? '', now()]);
    }
    foreach (legacy_read('catalog.json') as $id => $c) {
        $pdo->prepare('INSERT OR REPLACE INTO catalog(id,type,service,parent,title,description,price,location,image,video,status,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $c['type'] ?? 'project', $c['service'] ?? '', $c['parent'] ?? '', $c['title'] ?? '', $c['description'] ?? '', $c['price'] ?? '', $c['location'] ?? '', $c['image'] ?? '', $c['video'] ?? '', $c['status'] ?? 'Active', legacy_dt($c['updated'] ?? '')]);
    }

    // --- Careers ---
    $jobMap = [];
    $statusMap = ['Published' => 'published', 'Pending review' => 'pending', 'Closed' => 'closed', 'Rejected' => 'rejected'];
    foreach (legacy_read('recruitment_jobs.json') as $oldId => $j) {
        $title = $j['title'] ?? 'Vacancy';
        $pdo->prepare('INSERT INTO jobs(ref,slug,title,organisation,employer_email,location,work_mode,type,salary_text,description,requirements,required_skills,min_experience,required_quals,deadline,status,source,created_at,updated_at,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$oldId, slugify($title), $title, $j['organisation'] ?? '', $j['email'] ?? '', $j['location'] ?? '', (($j['type'] ?? '') === 'Remote') ? 'Remote' : 'On-site', in_array($j['type'] ?? '', ['Full-time', 'Part-time', 'Contract', 'Internship', 'Temporary', 'Freelance'], true) ? $j['type'] : 'Full-time', $j['salary'] ?? '',
                $j['description'] ?? '', $j['requirements'] ?? '', $j['required_skills'] ?? '', (int) ($j['min_experience'] ?? 0), $j['required_qualifications'] ?? '', $j['deadline'] ?? '',
                $statusMap[$j['status'] ?? ''] ?? 'pending', ($j['email'] ?? '') === cfg('email') ? 'internal' : 'employer', legacy_dt($j['created'] ?? ''), now(), legacy_dt($j['created'] ?? '')]);
        $jobMap[$oldId] = (int) $pdo->lastInsertId();
    }
    $stageMap = ['New' => 'Applied', 'Screening' => 'Screening', 'Shortlisted' => 'Shortlisted', 'Interview' => 'Interview', 'Offer' => 'Offer', 'Hired' => 'Hired', 'Rejected' => 'Rejected'];
    foreach (legacy_read('job_applications.json') as $oldId => $a) {
        $pdo->prepare('INSERT OR IGNORE INTO applications(ref,token,job_id,name,email,phone,location,linkedin,skills,experience_years,qualification,cover_letter,cv_path,cv_name,fit_score,fit_breakdown,matched_skills,stage,created_at,stage_changed_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$oldId, bin2hex(random_bytes(10)), $jobMap[$a['job_id'] ?? ''] ?? null, $a['name'] ?? '', $a['email'] ?? '', $a['phone'] ?? '', $a['location'] ?? '', $a['linkedin'] ?? '', $a['skills'] ?? '', (int) ($a['experience_years'] ?? 0),
                $a['qualification'] ?? '', $a['cover_letter'] ?? '', !empty($a['cv_file']) ? 'cv/' . basename($a['cv_file']) : null, $a['cv_file'] ?? null, (int) ($a['fit_score'] ?? 0), json_encode($a['fit_breakdown'] ?? []), json_encode($a['matched_skills'] ?? []),
                $stageMap[$a['status'] ?? 'New'] ?? 'Applied', legacy_dt($a['created'] ?? ''), legacy_dt($a['created'] ?? ''), now()]);
        // move legacy CV into private storage
        if (!empty($a['cv_file'])) { $src = DATA_DIR . '/private-cv/' . basename($a['cv_file']); if (is_file($src)) { @copy($src, DATA_DIR . '/private/cv/' . basename($a['cv_file'])); } }
    }

    // --- Learning ---
    $cats = (int) $pdo->query('SELECT id FROM course_categories ORDER BY id LIMIT 1')->fetchColumn();
    $courseMap = [];
    foreach (legacy_read('learning_courses.json') as $oldId => $c) {
        $title = $c['title'] ?? 'Course';
        $slug = slugify($title); $n = 1; while ($pdo->query("SELECT 1 FROM courses WHERE slug = " . $pdo->quote($slug))->fetchColumn()) { $slug = slugify($title) . '-' . (++$n); }
        $st = ['Published' => 'published', 'Draft' => 'draft', 'Archived' => 'archived'][$c['status'] ?? 'Draft'] ?? 'draft';
        $pdo->prepare('INSERT INTO courses(slug,title,subtitle,description,category_id,price,status,created_at,updated_at,published_at,lessons_count) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$slug, $title, '', $c['description'] ?? '', $cats, (float) ($c['price'] ?? 0), $st, now(), legacy_dt($c['updated'] ?? ''), $st === 'published' ? now() : null, count($c['lessons'] ?? [])]);
        $cid = (int) $pdo->lastInsertId(); $courseMap[$oldId] = $cid;
        $pdo->prepare('INSERT INTO course_modules(course_id,title,pos) VALUES(?,?,0)')->execute([$cid, 'Course content']);
        $mid = (int) $pdo->lastInsertId();
        foreach (($c['lessons'] ?? []) as $i => $l) {
            $pdo->prepare('INSERT INTO lessons(course_id,module_id,title,kind,body,video_url,pos) VALUES(?,?,?,?,?,?,?)')
                ->execute([$cid, $mid, $l['title'] ?? 'Lesson', !empty($l['video_url']) ? 'video' : 'text', $l['content'] ?? '', $l['video_url'] ?? '', $i]);
        }
    }
    // Old enrolment requests had no accounts: keep them as mailing contacts so nobody is lost.
    foreach (legacy_read('learning_enrolments.ndjson') as $r) {
        if (!empty($r['email'])) { $pdo->prepare('INSERT OR IGNORE INTO contacts(email,name,source,subscribed,created_at) VALUES(?,?,?,0,?)')->execute([strtolower($r['email']), $r['name'] ?? '', 'Legacy course enrolment', legacy_dt($r['created'] ?? '')]); }
    }

    // --- Webinars & forum (usually empty, but imported if present) ---
    foreach (legacy_read('webinars.json') as $oldId => $w) {
        if (!is_array($w) || empty($w['title'])) { continue; }
        $pdo->prepare('INSERT INTO webinars(slug,title,summary,description,kind,start_at,host_name,capacity,price,status,room_key,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([slugify($w['title']) . '-' . substr(md5((string) $oldId), 0, 4), $w['title'], '', $w['description'] ?? '', 'webinar', legacy_dt($w['start'] ?? ''), $w['host'] ?? 'Donums', (int) ($w['capacity'] ?? 100), (float) ($w['price_amount'] ?? 0),
                ['Published' => 'scheduled', 'Draft' => 'draft', 'Archived' => 'ended'][$w['status'] ?? 'Draft'] ?? 'draft', bin2hex(random_bytes(8)), now(), now()]);
    }
    $topics = legacy_read('forum_topics.json');
    foreach ($topics as $t) {
        if (!is_array($t) || empty($t['email']) || empty($t['title'])) { continue; }
        $email = strtolower($t['email']);
        $uid = (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote($email))->fetchColumn();
        if (!$uid) { $uid = create_user($t['name'] ?? 'Member', $email, bin2hex(random_bytes(12))); }
        $pdo->prepare('INSERT INTO forum_threads(category_id,user_id,title,slug,body,status,last_post_at,created_at) VALUES(2,?,?,?,?,?,?,?)')
            ->execute([$uid, $t['title'], slugify($t['title']), $t['body'] ?? '', ($t['status'] ?? '') === 'Published' ? 'published' : 'pending', legacy_dt($t['created'] ?? ''), legacy_dt($t['created'] ?? '')]);
        $tid = (int) $pdo->lastInsertId();
        foreach (($t['replies'] ?? []) as $r) {
            $ru = (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote(strtolower($r['email'] ?? ''))) ->fetchColumn();
            if (!$ru && !empty($r['email'])) { $ru = create_user($r['name'] ?? 'Member', $r['email'], bin2hex(random_bytes(12))); }
            if ($ru) { $pdo->prepare('INSERT INTO forum_posts(thread_id,user_id,body,created_at) VALUES(?,?,?,?)')->execute([$tid, $ru, $r['body'] ?? '', legacy_dt($r['created'] ?? '')]); }
        }
        $pdo->exec('UPDATE forum_threads SET reply_count = (SELECT COUNT(*) FROM forum_posts WHERE thread_id = ' . $tid . ') WHERE id = ' . $tid);
    }

    // --- Mail: accounts (passwords are now encrypted), display names, message history ---
    $names = legacy_read('mail_display_names.json');
    foreach ($names as $addr => $dn) { $pdo->prepare('UPDATE mailboxes SET display_name = ? WHERE address = ?')->execute([$dn, strtolower($addr)]); }
    $acc = legacy_read('mail_accounts.json');
    foreach (($acc['accounts'] ?? []) as $addr => $a) {
        if (empty($a['password']) || $a['password'] === 'CHANGE_ME') { continue; }
        $pdo->prepare('INSERT OR IGNORE INTO mailboxes(address,display_name) VALUES(?,?)')->execute([strtolower($addr), $names[$addr] ?? $addr]);
        $pdo->prepare('UPDATE mailboxes SET username=?, password_enc=?, imap_host=?, imap_port=?, imap_security=?, smtp_host=?, smtp_port=?, smtp_security=? WHERE address=?')
            ->execute([$a['username'] ?? $addr, secret_encrypt((string) $a['password']), $a['imap_host'] ?? 'da33.host-ww.net', (int) ($a['imap_port'] ?? 993), $a['imap_security'] ?? 'ssl', $a['smtp_host'] ?? 'da33.host-ww.net', (int) ($a['smtp_port'] ?? 465), $a['smtp_security'] ?? 'ssl', strtolower($addr)]);
    }
    foreach (legacy_read('mail_log.json') as $m) {
        $folder = ['inbox' => 'inbox', 'sent' => 'sent', 'archive' => 'archive', 'trash' => 'trash'][$m['folder'] ?? 'sent'] ?? 'sent';
        $mailbox = strtolower($m['mailbox'] ?? ($folder === 'inbox' ? ($m['to'] ?? '') : ($m['from'] ?? '')));
        if ($mailbox === '') { $mailbox = 'info@donums.co.ke'; }
        $body = (string) ($m['body'] ?? '');
        $pdo->prepare('INSERT INTO mail_messages(mailbox,folder,from_name,from_email,to_json,cc_json,subject,snippet,body_text,thread_key,is_read,status,msg_date,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$mailbox, $folder, $m['display'] ?? '', strtolower((string) ($m['from'] ?? '')), json_encode([[$m['to'] ?? '', '']]), json_encode([]), $m['subject'] ?? '(No subject)', excerpt($body, 140), $body,
                thread_key($m['subject'] ?? ''), 1, $m['status'] ?? '', legacy_dt($m['time'] ?? ''), now()]);
    }
    foreach (rows('SELECT DISTINCT mailbox FROM mail_messages') as $r) { $pdo->prepare('INSERT OR IGNORE INTO mailboxes(address,display_name,username) VALUES(?,?,?)')->execute([strtolower($r['mailbox']), $r['mailbox'], $r['mailbox']]); }

    // --- Shipped assets ---
    set_setting_raw($pdo, 'legacy_imported_at', now());
    app_log('migration', 'Legacy import complete: ' . implode(', ', $log));
}
function set_setting_raw(PDO $pdo, string $k, string $v): void { $pdo->prepare('INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)')->execute([$k, $v]); }
