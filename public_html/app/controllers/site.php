<?php
/* Public marketing pages, service requests, tracking, partner applications, legal. */

function svc_icon(string $slug): string { return ['marketing' => 'megaphone', 'solar' => 'sun', 'web' => 'monitor', 'space' => 'building', 'hr' => 'users'][$slug] ?? 'layers'; }

function page_home(): void {
    $services = require APP . '/data/services.php';
    $courses = rows("SELECT c.*, u.name instructor FROM courses c LEFT JOIN users u ON u.id=c.instructor_id WHERE c.status='published' ORDER BY c.featured DESC, c.published_at DESC LIMIT 3");
    $jobs = rows("SELECT * FROM jobs WHERE status='published' AND (deadline IS NULL OR deadline='' OR deadline >= ?) ORDER BY featured DESC, published_at DESC LIMIT 4", [date('Y-m-d')]);
    $events = rows("SELECT * FROM webinars WHERE status IN ('scheduled','live') AND (start_at >= ? OR status='live') ORDER BY start_at LIMIT 3", [date('Y-m-d H:i:s', time() - 7200)]);
    $partners = [['partner-faulu.png', 'Faulu'], ['partner-ismafrica.png', 'ISM Africa'], ['partner-ismengineering.png', 'ISM Engineering'], ['partner-hensonafrica.png', 'Henson Africa'], ['partner-craddleland.png', 'Craddleland'], ['partner-lorenzo.png', 'Lorenzo'], ['partner-dioceseofmarsabit.png', 'Diocese of Marsabit']];
    render('site/home', compact('services', 'courses', 'jobs', 'events', 'partners') + [
        'title' => 'Donums Consultants — business services, talent and capability', 'desc' => 'Request marketing, solar, website, property and HR support in one place — then build skills, hire talent and join live learning through the Donums platform.', 'active' => 'home']);
}

function page_service(array $p): void {
    $all = require APP . '/data/services.php';
    $s = $all[$p['slug']] ?? null; if (!$s) { not_found(); }
    $extra = rows("SELECT * FROM catalog WHERE service = ? AND status = 'Active' ORDER BY updated_at DESC LIMIT 12", [$p['slug']]);
    render('site/service', ['s' => $s, 'slug' => $p['slug'], 'extra' => $extra, 'title' => $s['title'], 'desc' => $s['headline'], 'active' => 'services']);
}

function page_cart(): void {
    $all = require APP . '/data/services.php';
    render('site/cart', ['all' => $all, 'title' => 'Request a quotation', 'desc' => 'Tell us what you need. We scope it, confirm a clear price and connect you with the right provider.', 'noindex' => false, 'active' => 'services']);
}

function create_request(string $service, string $need, string $source): ?string {
    $name = clean('name', 120); $contact = clean('contact', 160);
    if ($name === '' || $contact === '') { flash('error', 'Please add your name and a phone number or email so that we can reach you.'); return null; }
    if (!preg_match('/@/', $contact) && strlen(preg_replace('/\D/', '', $contact)) < 9) { flash('error', 'Please enter a valid phone number or email address.'); return null; }
    $ref = ref_code('DN');
    insert('requests', ['ref' => $ref, 'service' => $service, 'need' => $need, 'name' => $name, 'contact' => $contact, 'organisation' => clean('organisation', 160), 'location' => clean('location', 160),
        'details' => text('details', 4000), 'source' => $source, 'status' => 'New', 'payment' => 'Unpaid', 'created_at' => now(), 'updated_at' => now()]);
    q('INSERT OR IGNORE INTO contacts(email,name,phone,source,subscribed,created_at) SELECT ?,?,?,?,0,? WHERE ? LIKE "%@%"', [strtolower($contact), $name, '', 'Service request', now(), $contact]);
    notify_staff('crm.manage', 'request', 'New request ' . $ref, $service . ' — ' . $name, '/admin/requests/' . $ref);
    if (str_contains($contact, '@') && mail_valid($contact)) {
        queue_mail($contact, 'We received your request — ' . $ref,
            brand_html('Request received', md("Hello " . $name . ",\n\nThank you for contacting Donums Consultants. We have received your request for **" . ($need ?: $service) . "** (reference **$ref**).\n\nA member of our team will review it and respond within one business day with the next step. Keep your reference handy — you can follow progress at any time on our tracking page.\n\nKind regards,\nThe Donums team"), ['dept' => 'Customer Service', 'cta_url' => abs_url('/track', ['ref' => $ref]), 'cta_label' => 'Track your request']),
            ['from' => 'request@' . cfg('domain'), 'to_name' => $name, 'kind' => 'transactional', 'related' => $ref]);
    }
    return $ref;
}

function post_request(): void {
    if (!spam_check()) { flash('error', 'We could not verify that submission. Please wait a moment and try again.'); redirect('/cart'); }
    if (!rate_limit('req-' . client_ip(), 6, 3600)) { flash('error', 'You have submitted several requests recently. Please call us on ' . cfg('phone1') . '.'); redirect('/cart'); }
    $all = require APP . '/data/services.php';
    $svc = clean('service', 60); $svcTitle = $all[$svc]['title'] ?? $svc;
    $ref = create_request($svcTitle, clean('need', 160), 'web');
    if (!$ref) { redirect('/cart?service=' . rawurlencode($svc)); }
    redirect('/thank-you?ref=' . $ref);
}

function page_thanks(): void {
    $ref = preg_replace('/[^A-Z0-9-]/', '', strtoupper(qget('ref')));
    render('site/thanks', ['ref' => $ref, 'title' => 'Request received', 'noindex' => true]);
}

function page_track(): void {
    $ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', qget('ref'))); $contact = qget('contact'); $r = null; $err = '';
    if ($ref !== '' && $contact !== '') {
        if (!rate_limit('track-' . client_ip(), 20, 600)) { $err = 'Too many lookups. Please wait a few minutes.'; }
        else {
            $r = row('SELECT * FROM requests WHERE ref = ?', [$ref]);
            $norm = fn($s) => preg_replace('/\D/', '', $s) ?: strtolower(trim($s));
            if (!$r || ($norm($contact) !== $norm($r['contact']) && substr($norm($contact), -9) !== substr($norm($r['contact']), -9))) { $r = null; $err = 'We could not find a request matching those details. Check the reference and the phone number or email you used.'; }
        }
    }
    render('site/track', ['r' => $r, 'ref' => $ref, 'contact' => $contact, 'err' => $err, 'title' => 'Track your request', 'desc' => 'Follow the progress of your Donums request using your reference.']);
}

function page_sell(): void { render('site/sell', ['title' => 'Become a Donums partner', 'desc' => 'Suppliers, installers, professionals and property owners can join Donums to receive qualified, pre-scoped requests.', 'active' => 'services']); }
function post_sell(): void {
    if (!spam_check()) { flash('error', 'We could not verify that submission. Please try again.'); redirect('/sell'); }
    if (!rate_limit('sell-' . client_ip(), 4, 3600)) { flash('error', 'Too many applications from this connection. Please call us.'); redirect('/sell'); }
    $name = clean('name', 120); $contact = clean('contact', 160);
    if ($name === '' || $contact === '') { flash('error', 'Please add your name and contact details.'); redirect('/sell'); }
    $ref = ref_code('PT');
    insert('partners', ['ref' => $ref, 'name' => $name, 'contact' => $contact, 'kind' => clean('kind', 80), 'location' => clean('location', 160), 'details' => text('details', 3000), 'created_at' => now(), 'updated_at' => now()]);
    notify_staff('crm.manage', 'partner', 'New partner application', $name, '/admin/partners');
    flash('ok', 'Thank you — your application (' . $ref . ') has been received. Our partnerships team will review it and contact you.');
    redirect('/sell');
}

function page_contact(): void { render('site/contact', ['title' => 'Contact us', 'desc' => 'Speak to the Donums team about a service, a partnership or the platform.', 'active' => 'contact']); }
function post_contact(): void {
    if (!spam_check()) { flash('error', 'We could not verify that submission. Please try again.'); redirect('/contact'); }
    if (!rate_limit('contact-' . client_ip(), 5, 3600)) { flash('error', 'Too many messages from this connection. Please call us.'); redirect('/contact'); }
    $ref = create_request('General enquiry', clean('subject', 160) ?: 'General enquiry', 'contact');
    if (!$ref) { redirect('/contact'); }
    flash('ok', 'Thank you. Your message has been received (ref ' . $ref . ') and we will reply within one business day.');
    redirect('/contact');
}

function page_legal(array $p): void {
    $k = $p['page'];
    render('site/legal_' . $k, ['title' => $k === 'privacy' ? 'Privacy Policy' : 'Terms & Conditions', 'desc' => $k === 'privacy' ? 'How Donums collects, uses and protects personal information.' : 'The terms that govern use of Donums services and the platform.']);
}

function page_sitemap(): void {
    header('Content-Type: application/xml; charset=UTF-8');
    $u = ['/', '/cart', '/sell', '/contact', '/learning', '/jobs', '/forum', '/webinars', '/privacy', '/terms'];
    foreach (array_keys(require APP . '/data/services.php') as $s) { $u[] = '/services/' . $s; }
    $urls = [];
    foreach ($u as $x) { $urls[] = [abs_url($x), null]; }
    foreach (rows("SELECT slug, updated_at FROM courses WHERE status='published'") as $c) { $urls[] = [abs_url('/learning/' . $c['slug']), $c['updated_at']]; }
    foreach (rows("SELECT id, slug, updated_at FROM jobs WHERE status='published'") as $j) { $urls[] = [abs_url('/jobs/' . $j['id'] . '-' . $j['slug']), $j['updated_at']]; }
    foreach (rows("SELECT slug, updated_at FROM webinars WHERE status IN ('scheduled','live','ended') AND replay_public IN (0,1)") as $w) { $urls[] = [abs_url('/webinars/' . $w['slug']), $w['updated_at']]; }
    foreach (rows("SELECT id, slug, last_post_at FROM forum_threads WHERE status='published' ORDER BY id DESC LIMIT 500") as $t) { $urls[] = [abs_url('/forum/t/' . $t['id'] . '-' . $t['slug']), $t['last_post_at']]; }
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as [$loc, $mod]) { echo '<url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . '</url>'; }
    echo '</urlset>';
}
