/* Donums — shared front-end behaviour (no dependencies) */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  // Mobile menu
  var mb = $('#menuBtn'), nav = $('#nav');
  if (mb && nav) mb.addEventListener('click', function () { var o = nav.classList.toggle('open'); mb.setAttribute('aria-expanded', o); });

  // Confirm dialogs: <button data-confirm="Are you sure?">
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-confirm]');
    if (t && !window.confirm(t.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); }
    var c = e.target.closest('[data-copy]');
    if (c) {
      var txt = c.getAttribute('data-copy');
      (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).catch(function () {
        var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (x) {} ta.remove();
      }).then(function () { var o = c.innerHTML; c.textContent = 'Copied'; setTimeout(function () { c.innerHTML = o; }, 1400); });
    }
  });

  // Prevent double submits
  document.addEventListener('submit', function (e) {
    var f = e.target; if (f.hasAttribute('data-nolock')) return;
    $$('button[type=submit],input[type=submit]', f).forEach(function (b) { setTimeout(function () { b.disabled = true; b.dataset.t = b.textContent; if (b.tagName === 'BUTTON') b.textContent = 'Please wait…'; }, 0); });
    setTimeout(function () { $$('button[type=submit]', f).forEach(function (b) { b.disabled = false; if (b.dataset.t) b.textContent = b.dataset.t; }); }, 12000);
  });

  // Show times in the visitor's own timezone: <time data-iso="..." data-fmt="full|time">
  $$('time[data-iso]').forEach(function (t) {
    var d = new Date(t.getAttribute('data-iso')); if (isNaN(d)) return;
    var opts = t.getAttribute('data-fmt') === 'time' ? { hour: 'numeric', minute: '2-digit' } : { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' };
    var s = d.toLocaleString(undefined, opts); var tz = new Intl.DateTimeFormat(undefined, { timeZoneName: 'short' }).formatToParts(d).filter(function (p) { return p.type === 'timeZoneName'; })[0];
    t.textContent = s + (tz ? ' ' + tz.value : ''); t.title = 'Shown in your local time';
  });

  // Countdowns: <span data-countdown="ISO">
  var cds = $$('[data-countdown]');
  function tick() {
    cds.forEach(function (el) {
      var diff = new Date(el.getAttribute('data-countdown')) - new Date();
      if (diff <= 0) { el.textContent = el.getAttribute('data-done') || 'Starting now'; return; }
      var d = Math.floor(diff / 864e5), h = Math.floor(diff % 864e5 / 36e5), m = Math.floor(diff % 36e5 / 6e4), s = Math.floor(diff % 6e4 / 1e3);
      el.textContent = (d ? d + 'd ' : '') + (d || h ? h + 'h ' : '') + m + 'm ' + (d ? '' : s + 's');
    });
  }
  if (cds.length) { tick(); setInterval(tick, 1000); }

  // Auto-submit selects: <select data-autosubmit>
  $$('select[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  // Tabs on a single page: <div data-tabs> with [data-tab=id] buttons and [data-pane=id]
  $$('[data-tabs]').forEach(function (box) {
    var btns = $$('[data-tab]', box), panes = $$('[data-pane]', box);
    function show(id) { btns.forEach(function (b) { b.classList.toggle('on', b.dataset.tab === id); }); panes.forEach(function (p) { p.hidden = p.dataset.pane !== id; }); }
    btns.forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab); }); });
    var h = location.hash.slice(1); show(btns.some(function (b) { return b.dataset.tab === h; }) ? h : (btns[0] && btns[0].dataset.tab));
  });

  // Cart / quote form: prefill from ?service=
  var qs = new URLSearchParams(location.search);
  var sv = $('select[name=service]'); if (sv && qs.get('service')) { for (var i = 0; i < sv.options.length; i++) { if (sv.options[i].value === qs.get('service')) sv.selectedIndex = i; } }

  // Large photos (phones take 4-12 MB pictures) are shrunk in the browser before upload so they always attach.
  $$('input[type=file]').forEach(function (inp) {
    if (!/image/.test(inp.getAttribute('accept') || '')) return;
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0]; if (!f || !/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 1.5 * 1048576 || !window.DataTransfer) return;
      var img = new Image(), url = URL.createObjectURL(f);
      img.onload = function () {
        var max = 1800, r = Math.min(1, max / Math.max(img.width, img.height)), c = document.createElement('canvas');
        c.width = Math.round(img.width * r); c.height = Math.round(img.height * r); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) { if (!b) return; var nf = new File([b], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }), dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
          var hint = inp.parentNode.querySelector('.resized'); if (!hint) { hint = document.createElement('span'); hint.className = 'hint resized'; inp.parentNode.appendChild(hint); } hint.textContent = 'Photo optimised for upload (' + (nf.size / 1048576).toFixed(1) + ' MB).'; URL.revokeObjectURL(url); }, 'image/jpeg', 0.86);
      };
      img.src = url;
    });
  });

  // Service worker (offline fallback page + faster repeat visits)
  if ('serviceWorker' in navigator && location.protocol === 'https:') navigator.serviceWorker.register('/sw.js').catch(function () {});
})();
