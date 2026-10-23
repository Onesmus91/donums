<?php
/* Admin → Operations: customer requests, partners, deliveries, listings, payments. */

const REQ_STATUS = ['New', 'Contacted', 'Quoted', 'Won', 'Lost'];
const PAY_STATUS = ['Unpaid', 'Deposit', 'Paid', 'Refunded'];

function admin_routes_crm(): void {
    route('GET', '/admin/requests', 'admin_requests');
    route('GET', '/admin/requests/{ref}', 'admin_request');
    route('POST', '/admin/requests/{ref}', 'admin_request_save');
    route('GET', '/admin/partners', 'admin_partners');
    route('POST', '/admin/partners/{ref}', 'admin_partner_save');
    route('GET', '/admin/deliveries', 'admin_deliveries');
    route('POST', '/admin/deliveries/{ref}', 'admin_delivery_save');
    route('GET', '/admin/listings', 'admin_listings');
    route('GET', '/admin/listings/new', 'admin_listing_form');
    route('GET', '/admin/listings/{id}', 'admin_listing_form');
    route('POST', '/admin/listings/save', 'admin_listing_save');
    route('POST', '/admin/listings/{id}/delete', 'admin_listing_delete');
    route('GET', '/admin/payments', 'admin_payments');
    route('POST', '/admin/payments/{id:\d+}', 'admin_payment_action');
}

function admin_requests(): void {
    admin_guard('crm.manage'); $st = qget('status'); $s = qget('q'); $page = max(1, qint('page', 1));
    $w = ['1=1']; $p = [];
    if (in_array($st, REQ_STATUS, true)) { $w[] = 'status = ?'; $p[] = $st; }
    if ($s !== '') { $w[] = '(ref LIKE ? OR name LIKE ? OR contact LIKE ? OR need LIKE ? OR organisation LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%", "%$s%"); }
    if (qget('export') === 'csv') { csv_download('requests-' . date('Ymd') . '.csv', ['Ref', 'Date', 'Service', 'Need', 'Name', 'Contact', 'Organisation', 'Location', 'Status', 'Quote', 'Provider', 'Payment', 'Details'], array_map(fn($r) => [$r['ref'], $r['created_at'], $r['service'], $r['need'], $r['name'], $r['contact'], $r['organisation'], $r['location'], $r['status'], $r['quote'], $r['provider'], $r['payment'], $r['details']], rows('SELECT * FROM requests WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC', $p))); }
    $pg = paginate((int) val('SELECT COUNT(*) FROM requests WHERE ' . implode(' AND ', $w), $p), 25, $page);
    $items = rows('SELECT * FROM requests WHERE ' . implode(' AND ', $w) . " ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
    $counts = array_column(rows('SELECT status, COUNT(*) n FROM requests GROUP BY status'), 'n', 'status');
    admin_render('admin/requests', compact('items', 'pg', 'st', 's', 'counts') + ['title' => 'Requests', 'section' => 'Customer requests']);
}
function admin_request(array $p): void {
    admin_guard('crm.manage'); $r = row('SELECT * FROM requests WHERE ref = ?', [$p['ref']]); if (!$r) { not_found(); }
    $d = row('SELECT * FROM deliveries WHERE request_ref = ?', [$r['ref']]);
    $log = rows("SELECT a.*, u.name FROM activity a LEFT JOIN users u ON u.id=a.user_id WHERE entity='request' AND entity_id=? ORDER BY a.id DESC", [$r['ref']]);
    $mails = rows("SELECT id, mailbox, folder, subject, msg_date FROM mail_messages WHERE request_ref = ? ORDER BY msg_date DESC", [$r['ref']]);
    admin_render('admin/request', compact('r', 'd', 'log', 'mails') + ['title' => $r['ref'], 'section' => 'Request ' . $r['ref']]);
}
function admin_request_save(array $p): void {
    admin_guard('crm.manage'); $r = row('SELECT * FROM requests WHERE ref = ?', [$p['ref']]); if (!$r) { not_found(); }
    $new = ['status' => in_array(post('status'), REQ_STATUS, true) ? post('status') : $r['status'], 'quote' => clean('quote', 120), 'provider' => clean('provider', 160), 'provider_cost' => clean('provider_cost', 60),
        'payment' => in_array(post('payment'), PAY_STATUS, true) ? post('payment') : $r['payment'], 'followup' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('followup')) ? post('followup') : null, 'updated_at' => now()];
    update('requests', $r['id'], $new);
    if ($new['status'] !== $r['status']) { activity('request', $r['ref'], 'status', 'Status changed from ' . $r['status'] . ' to ' . $new['status']); }
    if (($note = text('note', 2000)) !== '') { activity('request', $r['ref'], 'note', $note); }
    if ($new['status'] === 'Won' && !val('SELECT 1 FROM deliveries WHERE request_ref = ?', [$r['ref']])) { q('INSERT INTO deliveries(request_ref,stage,provider,value,provider_cost,payment,updated_at) VALUES(?,?,?,?,?,?,?)', [$r['ref'], 'Assigned', $new['provider'], $new['quote'], $new['provider_cost'], $new['payment'], now()]); flash('info', 'A delivery record was created for this won request.'); }
    audit('request.update', 'request', $r['ref']); flash('ok', 'Request updated.'); redirect('/admin/requests/' . $r['ref']);
}

function admin_partners(): void {
    admin_guard('crm.manage'); $items = rows('SELECT * FROM partners ORDER BY (verification = \'Pending\') DESC, id DESC');
    admin_render('admin/partners', compact('items') + ['title' => 'Partners', 'section' => 'Partner applications']);
}
function admin_partner_save(array $p): void {
    admin_guard('crm.manage');
    update('partners', $p['ref'], ['verification' => in_array(post('verification'), ['Pending', 'Verified', 'Rejected'], true) ? post('verification') : 'Pending', 'partner_status' => in_array(post('partner_status'), ['Applicant', 'Active', 'Paused', 'Removed'], true) ? post('partner_status') : 'Applicant', 'notes' => text('notes', 1000), 'updated_at' => now()], 'ref');
    audit('partner.update', 'partner', $p['ref']); flash('ok', 'Partner updated.'); redirect('/admin/partners');
}

function admin_deliveries(): void {
    admin_guard('crm.manage');
    $items = rows('SELECT d.*, r.name, r.service, r.need FROM deliveries d LEFT JOIN requests r ON r.ref = d.request_ref ORDER BY d.updated_at DESC');
    admin_render('admin/deliveries', compact('items') + ['title' => 'Deliveries', 'section' => 'Deliveries']);
}
function admin_delivery_save(array $p): void {
    admin_guard('crm.manage');
    update('deliveries', $p['ref'], ['stage' => in_array(post('stage'), ['Assigned', 'In progress', 'Delivered', 'Completed', 'Disputed'], true) ? post('stage') : 'Assigned', 'provider' => clean('provider', 160), 'value' => clean('value', 60), 'provider_cost' => clean('provider_cost', 60),
        'payment' => in_array(post('payment'), PAY_STATUS, true) ? post('payment') : 'Unpaid', 'notes' => text('notes', 1000), 'updated_at' => now()], 'request_ref');
    flash('ok', 'Delivery updated.'); redirect('/admin/deliveries');
}

function admin_listings(): void { admin_guard('crm.manage'); $items = rows('SELECT * FROM catalog ORDER BY updated_at DESC'); admin_render('admin/listings', compact('items') + ['title' => 'Listings', 'section' => 'Service listings']); }
function admin_listing_form(array $p = []): void {
    admin_guard('crm.manage'); $l = !empty($p['id']) ? row('SELECT * FROM catalog WHERE id = ?', [$p['id']]) : null;
    if (!empty($p['id']) && !$l) { not_found(); }
    admin_render('admin/listing_form', ['l' => $l, 'services' => require APP . '/data/services.php', 'title' => 'Listing', 'section' => $l ? 'Edit listing' : 'New listing']);
}
function admin_listing_save(): void {
    admin_guard('crm.manage'); $id = clean('id', 40);
    $img = save_public_image('image', 'listing');
    if ($img !== '' && $img[0] === '!') { flash('error', substr($img, 1)); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/listings'); }
    $d = ['type' => clean('type', 30) ?: 'project', 'service' => clean('service', 40), 'parent' => clean('parent', 40), 'title' => clean('title', 160), 'description' => text('description', 4000), 'price' => clean('price', 60), 'location' => clean('location', 120), 'video' => clean('video', 300), 'status' => post('status') === 'Hidden' ? 'Hidden' : 'Active', 'updated_at' => now()];
    if ($d['title'] === '') { flash('error', 'A title is required.'); redirect($_SERVER['HTTP_REFERER'] ?? '/admin/listings'); }
    if ($id && ($old = row('SELECT * FROM catalog WHERE id = ?', [$id]))) { $d['image'] = $img ?: $old['image']; update('catalog', $id, $d); }
    else { $d['id'] = 'L' . strtoupper(bin2hex(random_bytes(4))); $d['image'] = $img; insert('catalog', $d); }
    flash('ok', 'Listing saved.'); redirect('/admin/listings');
}
function admin_listing_delete(array $p): void { admin_guard('crm.manage'); q('DELETE FROM catalog WHERE id = ?', [$p['id']]); flash('ok', 'Listing deleted.'); redirect('/admin/listings'); }

/* ---------- payments ---------- */
function admin_payments(): void {
    admin_guard('crm.manage'); $st = qget('status', 'submitted');
    $w = $st === 'all' ? '1=1' : 'p.status = ?'; $params = $st === 'all' ? [] : [$st];
    $items = rows("SELECT p.* FROM payments p WHERE $w ORDER BY p.id DESC LIMIT 200", $params);
    $counts = array_column(rows('SELECT status, COUNT(*) n FROM payments GROUP BY status'), 'n', 'status');
    admin_render('admin/payments', compact('items', 'st', 'counts') + ['title' => 'Payments', 'section' => 'Payments']);
}
function admin_payment_action(array $p): void {
    admin_guard('crm.manage'); $pay = row('SELECT * FROM payments WHERE id = ?', [(int) $p['id']]); if (!$pay) { not_found(); }
    $act = post('action'); $phone = cfg('phone1');
    if ($act === 'verify') {
        update('payments', $pay['id'], ['status' => 'verified', 'verified_at' => now(), 'verified_by' => uid()]);
        $next = '';
        if ($pay['item_type'] === 'course') {
            q("INSERT INTO enrolments(user_id,course_id,status,price,payment_id,enrolled_at) VALUES(?,?,'active',?,?,?) ON CONFLICT(user_id,course_id) DO UPDATE SET status='active', payment_id=excluded.payment_id", [$pay['user_id'], $pay['item_id'], $pay['amount'], $pay['id'], now()]);
            require_once APP . '/controllers/learning.php'; recalc_course((int) $pay['item_id']);
            $c = row('SELECT slug FROM courses WHERE id = ?', [$pay['item_id']]);
            $next = 'You now have full access to the course: ' . abs_url('/learning/' . ($c['slug'] ?? '') . '/learn');
            notify_user((int) $pay['user_id'], 'payment', 'Payment verified', $pay['item_title'], '/learning/' . ($c['slug'] ?? '') . '/learn');
        } elseif ($pay['item_type'] === 'webinar') {
            q("UPDATE webinar_regs SET status = 'registered' WHERE payment_id = ?", [$pay['id']]);
            $w = row('SELECT * FROM webinars WHERE id = ?', [$pay['item_id']]); $reg = row('SELECT * FROM webinar_regs WHERE payment_id = ?', [$pay['id']]);
            if ($w && $reg) { require_once APP . '/controllers/webinars.php'; send_webinar_confirmation($w, $reg); $next = 'Your personal joining link has been sent in a separate email.'; }
        }
        queue_template('payment_verified', $pay['email'], ['name' => $pay['name'], 'item' => $pay['item_title'], 'amount' => money($pay['amount'], $pay['currency']), 'payment_ref' => $pay['ref'], 'next_step' => $next], ['dept' => 'Payments', 'from' => 'finance@' . cfg('domain')]);
        audit('payment.verify', 'payment', $pay['id']); flash('ok', 'Payment verified and access granted.');
    } elseif ($act === 'reject') {
        $reason = text('reason', 300) ?: 'The transaction code did not match our records.';
        update('payments', $pay['id'], ['status' => 'rejected', 'note' => $reason]);
        queue_template('payment_rejected', $pay['email'], ['name' => $pay['name'], 'item' => $pay['item_title'], 'payment_ref' => $pay['ref'], 'reason' => $reason], ['dept' => 'Payments', 'from' => 'finance@' . cfg('domain')]);
        audit('payment.reject', 'payment', $pay['id']); flash('ok', 'Payment rejected and the customer notified.');
    }
    process_outbox(5); redirect('/admin/payments');
}
