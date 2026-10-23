<?php
// Donums configuration. Anything not listed here uses a sensible default (see app/boot.php).
return [
    'site_name' => 'Donums Consultants',
    'email'     => 'info@donums.co.ke',
    'phone1'    => '+254 106 026 970',
    'phone2'    => '+254 707 153 426',
    'whatsapp'  => '254106026970',
    'location'  => 'Kiambu Mall, Kiambu County, Nairobi Metropolitan Area',
    'domain'    => 'donums.co.ke',

    // Where the database, uploads (CVs, payment proofs), mail attachments and logs are stored.
    // Leave empty to use /data inside public_html (protected by .htaccess).
    // BEST PRACTICE: use a folder ABOVE public_html, e.g. '/home/YOURUSER/donums_private'
    'data_dir'  => '',

    'debug'     => false,
];
