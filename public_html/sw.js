/* Donums service worker: caches static assets, shows an offline page when the network is down. */
var V = 'dn-v3';
self.addEventListener('install', function (e) { e.waitUntil(caches.open(V).then(function (c) { return c.addAll(['/offline.html', '/assets/css/app.css', '/assets/img/logo.png', '/assets/fonts/montserrat-latin-wght-normal.woff2']); }).then(function () { return self.skipWaiting(); })); });
self.addEventListener('activate', function (e) { e.waitUntil(caches.keys().then(function (k) { return Promise.all(k.filter(function (x) { return x !== V; }).map(function (x) { return caches.delete(x); })); }).then(function () { return self.clients.claim(); })); });
self.addEventListener('fetch', function (e) {
  var r = e.request; if (r.method !== 'GET') return;
  var u = new URL(r.url); if (u.origin !== location.origin) return;
  if (/^\/(admin|api|account|file|room|t\/)/.test(u.pathname)) return;
  if (r.mode === 'navigate') { e.respondWith(fetch(r).catch(function () { return caches.match('/offline.html'); })); return; }
  if (/^\/assets\//.test(u.pathname)) { e.respondWith(caches.match(r).then(function (c) { return c || fetch(r).then(function (res) { var cp = res.clone(); caches.open(V).then(function (ch) { ch.put(r, cp); }); return res; }); })); }
});
