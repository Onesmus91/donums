<?php
// Scheduled tasks — run every 5 minutes from your hosting control panel (Cron Jobs):
//   every 5 minutes:  /usr/local/bin/php /home/USER/public_html/cron.php
// or have a monitor fetch  https://yourdomain/cron/YOUR_CRON_KEY  (the key is shown in Admin > System).
$GLOBALS['DN_SKIP_BG'] = true;
require __DIR__ . '/app/boot.php';
require APP . '/controllers/webinars.php';
require APP . '/controllers/admin_mail.php';
echo run_cron(), PHP_EOL;
