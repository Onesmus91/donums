<?php
$u = user();
$nav = ['services' => ['/#services', 'Solutions'], 'learning' => ['/learning', 'Academy'], 'jobs' => ['/jobs', 'Talent'], 'forum' => ['/forum', 'Insights'], 'webinars' => ['/webinars', 'Events'], 'contact' => ['/contact', 'Contact']];
$pageTitle = ($title && $title !== cfg('site_name')) ? $title . ' · ' . cfg('site_name') : cfg('site_name') . ' — ' . setting('site_tagline', 'Global HR, talent strategy and people operations');
$canon = $canonical ?: abs_url(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($desc ?: setting('site_tagline')) ?>">
<link rel="canonical" href="<?= e($canon) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<meta property="og:site_name" content="<?= e(cfg('site_name')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc ?: setting('site_tagline')) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e($og_image ? abs_url($og_image) : abs_url('/assets/img/logo.png')) ?>">
<meta name="theme-color" content="#0f172a">
<link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/assets/img/icon-192.png"><link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= DN_VERSION ?>">
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => cfg('site_name'), 'url' => abs_url('/'), 'logo' => abs_url('/assets/img/logo.png')]) ?></script>
<?php if ($schema): ?><script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
<?= $head_extra ?>
</head>
<body class="<?= e($body_class) ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="site-head">
  <div class="wrap">
    <a class="brand" href="/" aria-label="Donums HR Global — home"><img src="/assets/img/logo.png" alt="Donums HR Global" width="95" height="49"></a>
    <nav class="nav" id="nav" aria-label="Main">
      <?php foreach ($nav as $k => [$href, $label]): ?><a href="<?= e($href) ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= e($label) ?></a><?php endforeach; ?>
      <a class="m-only" href="<?= $u ? '/account' : '/account/login' ?>"><?= $u ? 'My account' : 'Sign in' ?></a>
      <a class="m-only" href="/cart">Book consultation</a>
    </nav>
    <div class="head-cta">
      <?php if ($u): ?>
        <?php if (is_staff()): ?><a class="btn btn-ghost btn-sm" href="/admin"><?= icon('sliders', 16) ?> Admin</a><?php endif; ?>
        <a class="user-chip" href="/account" aria-label="My account"><?= avatar_html($u, 30) ?><span><?= e(explode(' ', $u['name'])[0]) ?></span><?php if (($n = unread_notifications()) > 0): ?><span class="dot"><?= $n > 99 ? '99+' : $n ?></span><?php endif; ?></a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="/account/login">Sign in</a><a class="btn btn-primary btn-sm" href="/cart">Book consultation</a>
      <?php endif; ?>
    </div>
    <button class="menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="nav"><?= icon('menu', 22) ?></button>
  </div>
</header>
<main id="main"><?php $f = flash_html(); if ($f): ?><div class="wrap"><?= $f ?></div><?php endif; ?><?= $content ?></main>
<footer class="site-foot">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand"><img src="/assets/img/logo.png" alt="Donums HR Global" height="40"><p class="mt2" style="max-width:34ch">Global HR partner for founders, growing businesses, and executive teams seeking better people, sharper operations, and measurable performance.</p></div>
      <div><h4>Solutions</h4><?php foreach ((require APP . '/data/services.php') as $slug => $s): ?><a href="/services/<?= e($slug) ?>"><?= e($s['title']) ?></a><?php endforeach; ?></div>
      <div><h4>Platform</h4><a href="/learning">Learning & development</a><a href="/jobs">Talent pipeline</a><a href="/forum">Insights & network</a><a href="/webinars">Leadership events</a><a href="/sell">Partner with us</a></div>
      <div><h4>Contact</h4><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone1'))) ?>"><?= e(cfg('phone1')) ?></a><a href="tel:<?= e(preg_replace('/\s+/', '', cfg('phone2'))) ?>"><?= e(cfg('phone2')) ?></a><a href="mailto:<?= e(cfg('email')) ?>"><?= e(cfg('email')) ?></a><a href="/contact">Book a consultation</a></div>
    </div>
    <form class="news" method="post" action="/subscribe"><?= csrf_field() . spam_fields() ?><label for="nl">Get strategic HR updates</label><div style="display:flex;gap:8px"><input id="nl" type="email" name="email" placeholder="Your work email" required><button class="btn btn-accent btn-sm" type="submit">Join</button></div></form>
    <div class="foot-bottom"><span>© <?= date('Y') ?> Donums HR Global. All rights reserved.</span><span><a style="display:inline;padding:0 8px" href="/privacy">Privacy</a><a style="display:inline;padding:0 8px" href="/terms">Terms</a></span></div>
  </div>
</footer>
<a class="wa-float" href="https://wa.me/<?= e(cfg('whatsapp')) ?>?text=<?= rawurlencode('Hello Donums HR Global, I would like to discuss our HR needs.') ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><?= icon('whatsapp', 18) ?> <span>WhatsApp</span></a>
<script src="/assets/js/app.js?v=<?= DN_VERSION ?>" defer></script>
</body>
</html>
