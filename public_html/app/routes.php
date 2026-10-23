<?php
/* =====================================================================
 * Route table. Handlers live in app/controllers/*.php
 * ===================================================================== */
require_once APP . '/router.php';
foreach (['site', 'account', 'learning', 'jobs', 'forum', 'webinars', 'room', 'files', 'tracking', 'admin_core', 'admin_crm', 'admin_mail', 'admin_learning', 'admin_ats', 'admin_forum', 'admin_webinars', 'admin_system'] as $c) {
    if (is_file(APP . '/controllers/' . $c . '.php')) { require_once APP . '/controllers/' . $c . '.php'; }
}

/* ----- public site ----- */
route('GET', '/', 'page_home');
route('GET', '/services/{slug}', 'page_service');
route('GET', '/cart', 'page_cart');
route('POST', '/request', 'post_request');
route('GET', '/thank-you', 'page_thanks');
route('GET', '/track', 'page_track');
route('GET', '/sell', 'page_sell');
route('POST', '/sell', 'post_sell');
route('GET', '/contact', 'page_contact');
route('POST', '/contact', 'post_contact');
route('GET', '/{page:privacy|terms}', 'page_legal');
route('GET', '/sitemap.xml', 'page_sitemap');

/* ----- accounts ----- */
route('GET', '/account', 'page_account');
route('GET', '/account/login', 'page_login');
route('POST', '/account/login', 'post_login');
route('GET', '/account/register', 'page_register');
route('POST', '/account/register', 'post_register');
route('GET', '/account/logout', 'page_logout');
route('POST', '/account/logout', 'post_logout');
route('GET', '/account/forgot', 'page_forgot');
route('POST', '/account/forgot', 'post_forgot');
route('GET', '/account/reset/{token}', 'page_reset');
route('POST', '/account/reset/{token}', 'post_reset');
route('GET', '/account/verify/{token}', 'page_verify');
route('POST', '/account/verify/resend', 'post_resend_verify');
route('GET', '/account/profile', 'page_profile');
route('POST', '/account/profile', 'post_profile');
route('GET', '/account/notifications', 'page_notifications');
route('POST', '/payment/{ref}/submit', 'post_payment_submit');

/* ----- Donums Academy ----- */
route('GET', '/learning', 'page_learning');
route('GET', '/learning/verify', 'page_verify_cert');
route('GET', '/learning/certificate/{code}', 'page_certificate');
route('POST', '/learning/quiz/{lid:\d+}', 'post_quiz_submit');
route('POST', '/learning/lesson/{lid:\d+}/complete', 'post_lesson_complete');
route('GET', '/learning/{slug}', 'page_course');
route('POST', '/learning/{slug}/enrol', 'post_enrol');
route('GET', '/learning/{slug}/pay', 'page_course_pay');
route('GET', '/learning/{slug}/learn', 'page_learn');
route('GET', '/learning/{slug}/learn/{lid:\d+}', 'page_learn');
route('POST', '/learning/{slug}/review', 'post_review');
route('GET', '/file/lesson/{lid:\d+}', 'get_lesson_file');

/* ----- Donums Careers ----- */
route('GET', '/jobs', 'page_jobs');
route('GET', '/jobs/status', 'page_job_status');
route('GET', '/jobs/talent-network', 'page_talent');
route('POST', '/jobs/talent-network', 'post_apply');
route('GET', '/jobs/employers', 'page_employers');
route('POST', '/jobs/employers', 'post_employers');
route('GET', '/jobs/{id:\d+}-{slug}', 'page_job');
route('GET', '/jobs/{id:\d+}', 'page_job');
route('POST', '/jobs/{id:\d+}/apply', 'post_apply');

/* ----- Community ----- */
route('GET', '/forum', 'page_forum');
route('GET', '/forum/new', 'page_forum_new');
route('POST', '/forum/new', 'post_forum_new');
route('GET', '/forum/search', 'page_forum_search');
route('POST', '/forum/vote', 'post_forum_vote');
route('GET', '/forum/c/{slug}', 'page_forum_category');
route('GET', '/forum/u/{id:\d+}', 'page_forum_user');
route('GET', '/forum/t/{id:\d+}-{slug}', 'page_thread');
route('GET', '/forum/t/{id:\d+}', 'page_thread');
route('POST', '/forum/t/{id:\d+}/reply', 'post_forum_reply');
route('POST', '/forum/t/{id:\d+}/action', 'post_forum_action');

/* ----- Donums Live ----- */
route('GET', '/webinars', 'page_webinars');
route('GET', '/webinars/{slug}', 'page_webinar');
route('POST', '/webinars/{slug}/register', 'post_webinar_register');
route('GET', '/webinars/{slug}/pay', 'page_webinar_pay');
route('GET', '/webinars/{slug}/calendar.ics', 'get_webinar_ics');
route('GET', '/webinars/{slug}/feedback', 'page_webinar_feedback');
route('POST', '/webinars/{slug}/feedback', 'post_webinar_feedback');
route('GET', '/webinars/{slug}/room', 'page_room');
route('POST', '/webinars/{slug}/room/guest', 'post_room_guest');
route('GET', '/api/room/{slug}/{action}', 'api_room');
route('POST', '/api/room/{slug}/{action}', 'api_room', ['csrf' => false]);

route('POST', '/api/media/auth', 'post_media_auth', ['csrf' => false]);

/* ----- mail tracking / unsubscribe / cron ----- */
route('GET', '/file/{kind:cv|proof|mail|cid}/{id:\d+}', 'get_private_file');
route('GET', '/t/o/{token}.gif', 'track_open');
route('GET', '/t/c/{token}', 'track_click');
route('GET', '/unsubscribe/{token}', 'page_unsubscribe');
route('POST', '/unsubscribe/{token}', 'post_unsubscribe', ['csrf' => false]);
route('GET', '/cron/{key}', 'http_cron');

/* ----- admin ----- */
if (function_exists('admin_routes')) { admin_routes(); }
