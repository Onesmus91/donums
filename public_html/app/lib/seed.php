<?php
/* First-run defaults. Runs once on a brand-new database. */
function seed_defaults(PDO $pdo): void {
    $forum = [
        ['announcements', 'Announcements', 'Official news and updates from the Donums team.', 'megaphone', 1],
        ['general', 'General discussion', 'Open conversation on work, business and professional life.', 'chat', 0],
        ['careers', 'Careers & hiring', 'CV advice, interviews, job-search strategy and hiring practice.', 'briefcase', 0],
        ['business', 'Business & entrepreneurship', 'Starting, running and growing a business in Kenya and beyond.', 'building', 0],
        ['digital', 'Technology & digital skills', 'Websites, tools, automation, marketing technology and online selling.', 'monitor', 0],
        ['learning', 'Learning & development', 'Questions about courses, study habits and professional growth.', 'book', 0],
        ['help', 'Help & feedback', 'Questions about the Donums platform and suggestions to improve it.', 'alert', 0],
    ];
    foreach ($forum as $i => [$slug, $name, $desc, $ic, $staff]) {
        $pdo->prepare('INSERT INTO forum_categories(slug,name,description,icon,staff_only,pos) VALUES(?,?,?,?,?,?)')->execute([$slug, $name, $desc, $ic, $staff, $i]);
    }
    foreach (['Business & management', 'Marketing & sales', 'HR & leadership', 'Digital & technology', 'Finance & operations', 'Career development'] as $i => $n) {
        $pdo->prepare('INSERT INTO course_categories(slug,name,pos) VALUES(?,?,?)')->execute([slugify($n), $n, $i]);
    }
    $boxes = ['info' => 'Donums Consultants', 'admin' => 'Donums Administration', 'support' => 'Donums Support', 'finance' => 'Donums Finance', 'complaint' => 'Donums Customer Care',
        'sales' => 'Donums Sales', 'marketing' => 'Donums Marketing', 'request' => 'Donums Service Requests', 'receipts' => 'Donums Receipts', 'careers' => 'Donums Careers', 'academy' => 'Donums Academy', 'events' => 'Donums Live'];
    $i = 0;
    foreach ($boxes as $local => $name) {
        $pdo->prepare('INSERT OR IGNORE INTO mailboxes(address,display_name,username,imap_host,smtp_host,pos) VALUES(?,?,?,?,?,?)')
            ->execute([$local . '@' . cfg('domain', 'donums.co.ke'), $name, $local . '@' . cfg('domain', 'donums.co.ke'), 'da33.host-ww.net', 'da33.host-ww.net', $i++]);
    }
    $defaults = [
        'pay_mpesa_paybill' => '', 'pay_mpesa_account' => '', 'pay_mpesa_till' => '', 'pay_bank' => '', 'pay_note' => 'Payments are verified manually, usually within one business day.',
        'site_tagline' => 'Business services, talent and capability — delivered with accountability.',
        'forum_moderate_new' => '1', 'jobs_auto_publish' => '0',
    ];
    foreach ($defaults as $k => $v) { $pdo->prepare('INSERT OR IGNORE INTO settings(k,v) VALUES(?,?)')->execute([$k, $v]); }
    foreach (mail_template_defaults() as $k => [$subject, $body]) {
        $pdo->prepare('INSERT OR IGNORE INTO mail_templates(k,name,subject,body,kind,updated_at) VALUES(?,?,?,?,?,?)')->execute([$k, ucwords(str_replace('_', ' ', $k)), $subject, $body, 'system', now()]);
    }
    $canned = [
        ['ack', 'Acknowledge an enquiry', 'Thank you for contacting Donums', "Hello,\n\nThank you for getting in touch. We have received your message and a member of our team will respond within one business day.\n\nKind regards,"],
        ['quote', 'Send a quotation', 'Your Donums quotation', "Hello,\n\nThank you for the opportunity. Please find our quotation for the requirement below.\n\n**Scope:**\n**Investment:**\n**Timeline:**\n\nThe quotation is valid for 14 days. Reply to this email to approve and we will begin.\n\nKind regards,"],
        ['followup', 'Follow up on a quotation', 'Following up on your quotation', "Hello,\n\nI am following up on the quotation we sent. Do you have any questions, or would you like to adjust the scope before we proceed?\n\nKind regards,"],
        ['payment', 'Payment instructions', 'Payment instructions', "Hello,\n\nYou can pay by M-Pesa or bank transfer using the details below. Please reply with the confirmation message so that we can allocate your payment.\n\nKind regards,"],
    ];
    foreach ($canned as [$k, $name, $subj, $body]) {
        $pdo->prepare("INSERT OR IGNORE INTO mail_templates(k,name,subject,body,kind,updated_at) VALUES(?,?,?,?, 'canned', ?)")->execute([$k, $name, $subj, $body, now()]);
    }
}
