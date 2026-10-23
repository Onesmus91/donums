<?php $u = user(); $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?'); ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(($section ?? $title) . ' · Donums Admin') ?></title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#07150f">
<link rel="icon" href="/assets/img/icon-192.png"><link rel="stylesheet" href="/assets/css/app.css?v=<?= DN_VERSION ?>"><link rel="stylesheet" href="/assets/css/admin.css?v=<?= DN_VERSION ?>"><?= $head_extra ?></head>
<body class="admin <?= e($body_class) ?>">
<aside class="a-side" id="aSide"><a class="a-brand" href="/admin"><img src="/assets/img/logo.png" alt="Donums"><span>Admin</span></a>
<?php foreach (admin_nav() as [$group, $items]): $vis = array_filter($items, fn($i) => nav_allowed($i[3])); if (!$vis) { continue; } ?>
  <div class="a-group"><?= e($group) ?></div><?php foreach ($vis as [$href, $label, $ic]): $on = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
  <a class="a-link <?= $on ? 'on' : '' ?>" href="<?= e($href) ?>"><?= icon($ic, 17) ?><span><?= e($label) ?></span></a><?php endforeach; endforeach; ?>
<div class="a-foot"><a class="a-link" href="/" target="_blank"><?= icon('globe', 17) ?><span>View website</span></a><a class="a-link" href="/account"><?= icon('user', 17) ?><span><?= e($u['name']) ?></span></a>
<form method="post" action="/account/logout"><?= csrf_field() ?><button class="a-link" style="width:100%;background:none;border:0;text-align:left;font:inherit;cursor:pointer"><?= icon('logout', 17) ?><span>Sign out</span></button></form></div></aside>
<div class="a-main"><header class="a-top"><button class="a-menu" id="aMenu" aria-label="Menu"><?= icon('menu', 22) ?></button><h1><?= e($section ?? $title) ?></h1><div class="a-top-r"><?= $topbar ?? '' ?><a class="a-bell" href="/account/notifications" title="Notifications"><?= icon('bell', 19) ?><?php if (($n = unread_notifications()) > 0): ?><span class="dot"><?= $n > 9 ? '9+' : $n ?></span><?php endif; ?></a></div></header>
<div class="a-body"><?= flash_html() ?><?= $content ?></div></div>
<script src="/assets/js/app.js?v=<?= DN_VERSION ?>" defer></script><script>document.getElementById('aMenu').onclick=function(){document.getElementById('aSide').classList.toggle('open')}</script>
</body></html>
