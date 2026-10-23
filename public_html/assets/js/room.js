/* Donums Live — browser room client (WebRTC + PHP signalling, no third-party service). */
(function () {
  'use strict';
  var C = window.ROOM, $ = function (s) { return document.querySelector(s); }, $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
  var ICONS = {
    mic: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>',
    video: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
    screen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
    hand: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-4 0v1M14 7V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>',
    chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    link: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
    rec: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>',
    leave: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>'
  };
  $$('[data-ic]').forEach(function (el) { el.innerHTML = ICONS[el.dataset.ic] || ''; });
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function ini(n) { return (n || '?').split(/\s+/).slice(0, 2).map(function (x) { return x[0]; }).join('').toUpperCase(); }

  var me = null, roster = [], status = C.status, sinceS = 0, sinceM = 0, sinceE = 0, polling = false, stopped = false, fails = 0;
  var peers = {}, tiles = {}, localStream = null, screenStream = null, camTrack = null, wantCam = true, wantMic = true, sharing = false, amStage = false, mediaReady = false;
  var seenChat = {}, lastPollId = null, previewStream = null, recorder = null;
  // Broadcast mode (media server): viewers watch one program feed; stage people still talk peer-to-peer.
  var BC = !!C.broadcast, media = null, pollMs = 1200, programLive = false, socialLive = false, dests = [], selDests = [];
  var whep = null, pub = null, comp = null, audioCtx = null, audioDest = null, audioSrc = {}, bcTimer = null;

  /* ---------- API ---------- */
  function api(action, data, query) {
    var url = '/api/room/' + C.slug + '/' + action + (query ? '?' + query : '');
    var opt = { method: data === undefined ? 'GET' : 'POST', headers: { 'X-Room-Token': C.token, 'X-CSRF-Token': C.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (me) opt.headers['X-Peer-Id'] = me.peer;
    if (data !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data); }
    return fetch(url, opt).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Bad response' }; }); });
  }

  /* ---------- lobby ---------- */
  var stageRole = C.role === 'host' || C.role === 'panelist' || C.role === 'cohost';
  function showLobbyErr(m) { var e = $('#lobbyErr'); e.textContent = m; e.hidden = false; }
  function startPreview() {
    if (!stageRole || !navigator.mediaDevices) { return; }
    $('#lobbyOpts').hidden = false;
    navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then(function (s) {
      previewStream = s; $('#previewBox').hidden = false; $('#previewVideo').srcObject = s; meter(s);
    }).catch(function () { showLobbyErr('We could not access your camera or microphone. Check your browser permissions — you can still join and watch.'); $('#optCam').checked = false; $('#optMic').checked = false; });
  }
  function meter(stream) {
    try { var ac = new (window.AudioContext || window.webkitAudioContext)(), src = ac.createMediaStreamSource(stream), an = ac.createAnalyser(); an.fftSize = 256; src.connect(an); var buf = new Uint8Array(an.frequencyBinCount);
      (function loop() { if (!previewStream) { ac.close(); return; } an.getByteFrequencyData(buf); var v = 0; for (var i = 0; i < buf.length; i++) v += buf[i]; $('#meter').style.width = Math.min(100, v / buf.length * 1.6) + '%'; requestAnimationFrame(loop); })(); } catch (e) {}
  }
  startPreview();

  $('#joinBtn').addEventListener('click', function () {
    var btn = this; btn.disabled = true; btn.textContent = 'Joining…';
    wantCam = $('#optCam').checked; wantMic = $('#optMic').checked;
    api('join', {}).then(function (r) {
      if (!r.ok) { showLobbyErr(r.error || 'Could not join.'); btn.disabled = false; btn.textContent = 'Join session'; return; }
      me = r; C.ice = r.ice || C.ice; sinceE = r.last_event || 0;
      BC = !!r.broadcast; media = r.media || null; pollMs = r.poll_ms || 1200; dests = r.dests || []; selDests = r.selected || [];
      if (previewStream) { localStream = previewStream; previewStream = null; localStream.getTracks().forEach(function (t) { if (t.kind === 'video') { camTrack = t; t.enabled = wantCam; } else { t.enabled = wantMic; } }); mediaReady = true; }
      $('#lobby').hidden = true; $('#room').hidden = false; initUI(); poll();
    }).catch(function () { showLobbyErr('Network error. Please try again.'); btn.disabled = false; btn.textContent = 'Join session'; });
  });

  /* ---------- UI wiring ---------- */
  function initUI() {
    $$('.rtabs button').forEach(function (b) { b.addEventListener('click', function () { $$('.rtabs button').forEach(function (x) { x.classList.toggle('on', x === b); }); $$('.pane').forEach(function (p) { p.hidden = p.dataset.pane !== b.dataset.tab; }); }); });
    $('#sideBtn').addEventListener('click', function () { $('#side').classList.toggle('open'); });
    $('#chatForm').addEventListener('submit', function (e) { e.preventDefault(); var i = $('#chatInput'); if (!i.value.trim()) return; api('chat', { kind: 'chat', body: i.value }).then(function (r) { if (!r.ok) toast(r.error); }); i.value = ''; });
    $('#qaForm').addEventListener('submit', function (e) { e.preventDefault(); var i = $('#qaInput'); if (!i.value.trim()) return; api('chat', { kind: 'qa', body: i.value }).then(function (r) { if (!r.ok) toast(r.error); else toast('Question sent'); }); i.value = ''; });
    $('#micBtn').addEventListener('click', toggleMic); $('#camBtn').addEventListener('click', toggleCam); $('#shareBtn').addEventListener('click', toggleShare);
    $('#handBtn').addEventListener('click', function () { var on = !this.classList.contains('on'); this.classList.toggle('on', on); api('hand', { on: on }); });
    $('#leaveBtn').addEventListener('click', function () { if (confirm(C.host ? 'Leave the room? The session keeps running until you choose “End for all”.' : 'Leave this session?')) leave(); });
    window.addEventListener('beforeunload', function () { if (me) navigator.sendBeacon && navigator.sendBeacon('/api/room/' + C.slug + '/leave?peer=' + me.peer + '&tk=' + encodeURIComponent(C.token)); });
    if (C.host) {
      $('#startBtn').addEventListener('click', function () { api('start', {}).then(function (r) { if (!r.ok) toast(r.error); }); });
      $('#endBtn').addEventListener('click', function () { if (confirm('End the session for everyone?')) api('end', {}); });
      $('#pollForm').addEventListener('submit', function (e) { e.preventDefault(); var f = e.target, o = [f.o1.value, f.o2.value, f.o3.value, f.o4.value].filter(Boolean); api('poll_create', { question: f.question.value, options: o }).then(function (r) { if (!r.ok) toast(r.error); else f.reset(); }); });
      $('#pollClose').addEventListener('click', function () { api('poll_close', {}); });
      $('#announceBtn').addEventListener('click', function () { var i = $('#announceInput'); if (i.value.trim()) { api('announce', { text: i.value }); i.value = ''; } });
      $('#chatToggle').addEventListener('change', function () { api('chat_toggle', { on: this.checked }); });
      $('#inviteBtn').addEventListener('click', function () { var u = location.origin + '/webinars/' + C.slug; (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { toast('Session page link copied'); }, function () { prompt('Copy this link', u); }); });
      $('#recBtn').addEventListener('click', toggleRecord);
    }
    setInterval(function () { if (status === 'live' && C.started) { var s = Math.floor((Date.now() - C.started) / 1000); $('#clock').textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60); } }, 1000);
    updateControls(); initBroadcastUi();
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toast(m) { var b = $('#banner'); b.textContent = m; b.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(function () { b.hidden = true; }, 5000); }

  /* ---------- local media ---------- */
  function ensureMedia() {
    if (mediaReady) return Promise.resolve(true);
    if (!navigator.mediaDevices) return Promise.resolve(false);
    return navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then(function (s) {
      localStream = s; mediaReady = true; s.getTracks().forEach(function (t) { if (t.kind === 'video') { camTrack = t; t.enabled = wantCam; } else { t.enabled = wantMic; } }); return true;
    }).catch(function () { toast('Camera or microphone is blocked. Allow access in your browser to go on camera.'); return false; });
  }
  function pushMedia() { api('media', { mic: audioOn(), cam: videoOn() }); updateControls(); renderStage(); }
  function audioOn() { return !!(localStream && localStream.getAudioTracks()[0] && localStream.getAudioTracks()[0].enabled); }
  function videoOn() { return !!(localStream && camTrack && camTrack.enabled) || sharing; }
  function toggleMic() { if (!localStream) return; var t = localStream.getAudioTracks()[0]; if (!t) return; t.enabled = !t.enabled; pushMedia(); }
  function toggleCam() { if (!camTrack) return; camTrack.enabled = !camTrack.enabled; pushMedia(); }
  function toggleShare() {
    if (sharing) { stopShare(); return; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) { toast('Screen sharing is not supported on this device.'); return; }
    navigator.mediaDevices.getDisplayMedia({ video: true }).then(function (s) {
      screenStream = s; sharing = true; var t = s.getVideoTracks()[0]; t.onended = stopShare;
      Object.keys(peers).forEach(function (id) { swapVideo(peers[id].pc, t); }); pushMedia();
    }).catch(function () {});
  }
  function stopShare() {
    if (!sharing) return; sharing = false;
    if (screenStream) { screenStream.getTracks().forEach(function (t) { t.stop(); }); screenStream = null; }
    Object.keys(peers).forEach(function (id) { swapVideo(peers[id].pc, camTrack || null); }); pushMedia();
  }
  function swapVideo(pc, track) {
    var s = pc.getSenders().filter(function (x) { return x.track && x.track.kind === 'video' || (x.__v); })[0];
    if (!s) { if (track) { var ns = pc.addTrack(track, localStream || new MediaStream([track])); ns.__v = true; } return; }
    s.replaceTrack(track).catch(function () {});
  }

  /* ---------- peer connections (perfect negotiation) ---------- */
  function makePeer(id) {
    var pc = new RTCPeerConnection({ iceServers: C.ice });
    var st = { pc: pc, polite: me.peer > id, making: false, ignore: false, stream: null, pendingIce: [] };
    peers[id] = st;
    pc.onicecandidate = function (e) { if (e.candidate) api('signal', { to: id, type: 'ice', payload: e.candidate }); };
    pc.onnegotiationneeded = function () {
      st.making = true;
      pc.setLocalDescription().then(function () { return api('signal', { to: id, type: 'desc', payload: pc.localDescription }); }).catch(function () {}).then(function () { st.making = false; });
    };
    pc.ontrack = function (e) { st.stream = e.streams[0] || new MediaStream([e.track]); renderStage(); };
    pc.onconnectionstatechange = function () { if (pc.connectionState === 'failed') { try { pc.restartIce(); } catch (x) {} } };
    attachLocal(st);
    return st;
  }
  function attachLocal(st) {
    if (!amStage || !localStream) return;
    var have = st.pc.getSenders().map(function (s) { return s.track; });
    localStream.getTracks().forEach(function (t) {
      if (t.kind === 'video' && sharing && screenStream) t = screenStream.getVideoTracks()[0];
      if (have.indexOf(t) === -1) { var s = st.pc.addTrack(t, localStream); if (t.kind === 'video') s.__v = true; }
    });
  }
  function detachLocal(st) { st.pc.getSenders().forEach(function (s) { if (s.track) { try { st.pc.removeTrack(s); } catch (e) {} } }); }
  function closePeer(id) { var st = peers[id]; if (!st) return; try { st.pc.close(); } catch (e) {} delete peers[id]; renderStage(); }

  function handleSignal(sig) {
    var id = sig.from, st = peers[id] || (me && makePeer(id)); if (!st) return; var pc = st.pc;
    if (sig.type === 'desc') {
      var d = sig.payload, collision = d.type === 'offer' && (st.making || pc.signalingState !== 'stable');
      st.ignore = !st.polite && collision; if (st.ignore) return;
      var p = collision ? Promise.all([pc.setLocalDescription({ type: 'rollback' }).catch(function () {}), pc.setRemoteDescription(d)]) : pc.setRemoteDescription(d);
      p.then(function () { st.pendingIce.splice(0).forEach(function (c) { pc.addIceCandidate(c).catch(function () {}); }); if (d.type === 'offer') { return pc.setLocalDescription().then(function () { return api('signal', { to: id, type: 'desc', payload: pc.localDescription }); }); } }).catch(function () {});
    } else if (sig.type === 'ice') {
      if (pc.remoteDescription) { pc.addIceCandidate(sig.payload).catch(function () { }); } else { st.pendingIce.push(sig.payload); }
    }
  }

  function syncPeers() {
    if (!me) return;
    var mine = roster.filter(function (r) { return r.peer_id === me.peer; })[0];
    var ids = {};
    if (status === 'live' || C.host) {
      roster.forEach(function (r) {
        if (r.peer_id === me.peer || !r.admitted) return;
        ids[r.peer_id] = 1;
        var need = BC ? (amStage && r.on_stage) : (amStage || r.on_stage);
        if (need && !peers[r.peer_id]) makePeer(r.peer_id);
        if (!need && peers[r.peer_id]) closePeer(r.peer_id);
      });
    }
    Object.keys(peers).forEach(function (id) { if (!ids[id]) closePeer(id); });
  }

  /* ---------- poll loop ---------- */
  function poll() {
    if (stopped) return;
    api('poll', undefined, 's=' + sinceS + '&m=' + sinceM + '&e=' + sinceE).then(function (r) {
      fails = 0;
      if (r.gone) { return rejoin(); }
      if (!r.ok) { setTimeout(poll, 3000); return; }
      handle(r); setTimeout(poll, BC && !amStage && !C.host ? 4000 : 1200);
    }).catch(function () { fails++; if (fails > 40) { toast('Connection lost. Please reload the page.'); return; } setTimeout(poll, Math.min(8000, 1200 * fails)); });
  }
  function rejoin() { api('join', {}).then(function (r) { if (r.ok) { me = r; poll(); } else { toast(r.error || 'Session unavailable'); } }); }

  function handle(r) {
    var prevStatus = status; status = r.status;
    if (r.started) { C.started = new Date(r.started).getTime(); }
    var st = $('#statePill'); st.textContent = status === 'live' ? 'LIVE' : status === 'ended' ? 'Ended' : 'Not started'; st.className = 'pill' + (status === 'live' ? ' live' : '');
    if (C.host) { $('#startBtn').hidden = status === 'live' || status === 'ended'; $('#endBtn').hidden = status !== 'live'; }
    if (status === 'ended') { stopped = true; leave(true); return; }
    programLive = !!r.program_live; socialLive = !!r.social_live;
    if (r.online != null) $('#count').textContent = r.online + ' online';
    (r.events || []).forEach(function (ev) { sinceE = Math.max(sinceE, ev.id); onEvent(ev); });
    var wasStage = amStage;
    if (r.me) { amStage = !!r.me.on_stage; $('#handBtn').classList.toggle('on', !!r.me.hand); }
    $('#waiting').hidden = !r.waiting; $('#notLive').hidden = r.waiting || status === 'live' || C.host;
    if (r.waiting) { updateControls(); return; }
    roster = r.roster || [];
    if (r.online == null) $('#count').textContent = roster.length + ' online';
    if (amStage && !wasStage) { ensureMedia().then(function (ok) { if (ok) { Object.keys(peers).forEach(function (id) { attachLocal(peers[id]); }); pushMedia(); } syncPeers(); renderStage(); }); }
    if (!amStage && wasStage) { Object.keys(peers).forEach(function (id) { detachLocal(peers[id]); }); if (sharing) stopShare(); }
    if (C.host && !mediaReady && amStage) { ensureMedia().then(function () { pushMedia(); }); }
    syncPeers();
    (r.signals || []).forEach(function (s) { sinceS = Math.max(sinceS, s.id); handleSignal(s); });
    (r.chat || []).forEach(function (m) { sinceM = Math.max(sinceM, m.id); addChat(m); });
    renderQA(r.qa || []); renderPoll(r.poll); renderPeople(); renderStage(); updateControls();
    if (BC) { viewerSync(); bcUi(); }
    $('#chatInput').disabled = !r.chat_enabled && !C.host; $('#chatInput').placeholder = r.chat_enabled || C.host ? 'Message everyone…' : 'Chat is turned off by the host';
    if (C.host) { $('#chatToggle').checked = !!r.chat_enabled; }
  }

  function onEvent(ev) {
    var d = ev.data || {};
    if (ev.type === 'announce') toast('📣 ' + d.text);
    if (ev.type === 'mute' && me && d.peer === me.peer && localStream) { var t = localStream.getAudioTracks()[0]; if (t) { t.enabled = false; pushMedia(); toast('The host muted your microphone.'); } }
    if (ev.type === 'kick' && me && d.peer === me.peer) { stopped = true; alert('You have been removed from this session by the host.'); location.href = '/webinars/' + C.slug; }
    if (ev.type === 'stage_in' && me && d.peer === me.peer) toast('The host invited you on stage. Allow camera and microphone when asked.');
    if (ev.type === 'hide_msg') { var el = document.getElementById('c' + d.id); if (el) el.remove(); }
  }

  /* ---------- rendering ---------- */
  function addChat(m) {
    if (seenChat[m.id]) return; seenChat[m.id] = 1;
    var div = document.createElement('div'); div.className = 'msg' + (m.role === 'host' ? ' host' : ''); div.id = 'c' + m.id;
    div.innerHTML = '<b>' + esc(m.name) + '</b><span class="t">' + esc((m.created_at || '').slice(11, 16)) + '</span><div>' + esc(m.body) + '</div>';
    var list = $('#chatList'), near = list.scrollHeight - list.scrollTop - list.clientHeight < 80; list.appendChild(div); if (near) list.scrollTop = list.scrollHeight;
  }
  function renderQA(items) {
    var sig = JSON.stringify(items); if (sig === renderQA.last) return; renderQA.last = sig;
    var box = $('#qaList'); if (!items.length) { box.innerHTML = '<p class="muted">No questions yet. Ask the first one below.</p>'; return; }
    box.innerHTML = items.map(function (q) {
      return '<div class="q' + (q.answered == 1 ? ' answered' : '') + '"><div><b>' + esc(q.name) + '</b>' + (q.pinned == 1 ? ' 📌' : '') + (q.answered == 1 ? ' ✅' : '') + '</div><div>' + esc(q.body) + '</div><div class="row"><button class="rbtn xs" data-up="' + q.id + '">▲ ' + q.votes + '</button>' +
        (C.host ? '<button class="rbtn xs" data-qa="answered" data-id="' + q.id + '" data-v="' + (q.answered == 1 ? 0 : 1) + '">' + (q.answered == 1 ? 'Reopen' : 'Mark answered') + '</button><button class="rbtn xs" data-qa="pinned" data-id="' + q.id + '" data-v="' + (q.pinned == 1 ? 0 : 1) + '">' + (q.pinned == 1 ? 'Unpin' : 'Pin') + '</button><button class="rbtn xs" data-qa="hidden" data-id="' + q.id + '" data-v="1">Hide</button>' : '') + '</div></div>';
    }).join('');
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    if (b.dataset.up) api('qa_vote', { id: +b.dataset.up });
    if (b.dataset.qa) api('qa_update', { id: +b.dataset.id, field: b.dataset.qa, value: +b.dataset.v });
    if (b.dataset.opt != null && b.dataset.poll) api('vote', { poll: +b.dataset.poll, option: +b.dataset.opt });
    if (b.dataset.act) { var d = { target: b.dataset.peer }; if (b.dataset.on != null) d.on = +b.dataset.on; if (b.dataset.act === 'admit_all') d = { all: 1 }; api(b.dataset.act === 'admit_all' ? 'admit' : b.dataset.act, d).then(function (r) { if (!r.ok) toast(r.error); }); }
  });
  function renderPoll(p) {
    var box = $('#pollBox'); if (!p) return; var sig = JSON.stringify(p); if (sig === renderPoll.last) return; renderPoll.last = sig;
    var total = p.counts.reduce(function (a, b) { return a + b; }, 0), voted = p.mine !== null || p.status !== 'open';
    box.innerHTML = '<h3 style="margin:0 0 10px">' + esc(p.question) + '</h3>' + p.options.map(function (o, i) {
      var pc = total ? Math.round(p.counts[i] * 100 / total) : 0;
      return '<button class="opt" data-poll="' + p.id + '" data-opt="' + i + '" ' + (voted ? 'disabled' : '') + '>' + (voted || C.host ? '<i style="width:' + pc + '%"></i>' : '') + '<span><span>' + esc(o) + (p.mine === i ? ' ✓' : '') + '</span>' + (voted || C.host ? '<span>' + pc + '% · ' + p.counts[i] + '</span>' : '') + '</span></button>';
    }).join('') + '<p class="muted small">' + (p.status === 'open' ? 'Poll open · ' : 'Poll closed · ') + total + ' vote' + (total === 1 ? '' : 's') + '</p>';
  }
  function renderPeople() {
    var hands = roster.filter(function (r) { return r.hand == 1; }).length, hb = $('#handBadge'); hb.hidden = !C.host || !hands; hb.textContent = hands;
    var box = $('#people'), html = '';
    if (C.host) { var w = roster.filter(function (r) { return r.admitted == 0; }); if (w.length) html += '<div class="q"><b>Waiting room (' + w.length + ')</b><div class="row"><button class="rbtn xs primary" data-act="admit_all">Admit everyone</button></div></div>'; }
    html += roster.map(function (r) {
      var isMe = me && r.peer_id === me.peer, acts = '';
      if (C.host && !isMe && r.role !== 'host') {
        if (r.admitted == 0) acts += '<button class="rbtn xs primary" data-act="admit" data-peer="' + r.peer_id + '">Admit</button>';
        else { acts += '<button class="rbtn xs" data-act="stage" data-peer="' + r.peer_id + '" data-on="' + (r.on_stage ? 0 : 1) + '">' + (r.on_stage ? 'Remove from stage' : 'Invite on stage') + '</button>';
          if (r.on_stage) acts += '<button class="rbtn xs" data-act="mute" data-peer="' + r.peer_id + '">Mute</button>'; if (r.hand == 1) acts += '<button class="rbtn xs" data-act="lower_hand" data-peer="' + r.peer_id + '">Lower hand</button>'; }
        acts += '<button class="rbtn xs" data-act="remove" data-peer="' + r.peer_id + '" onclick="return confirm(\'Remove this person from the session?\')">Remove</button>';
      }
      return '<div class="person"><div class="grow"><div><b>' + esc(r.name) + (isMe ? ' (you)' : '') + '</b> ' + (r.role === 'host' ? '<span class="pill">Host</span>' : r.on_stage ? '<span class="pill">On stage</span>' : '') + (r.hand == 1 ? ' ✋' : '') + '</div></div><div class="acts">' + acts + '</div></div>';
    }).join('');
    if (renderPeople.last !== html) { box.innerHTML = html; renderPeople.last = html; }
  }
  function renderProgramTile() {
    var stage = $('#stage'); Object.keys(tiles).forEach(function (id) { if (id !== '__program') { tiles[id].el.remove(); delete tiles[id]; } });
    var t = tiles.__program;
    if (!t) { var el = document.createElement('div'); el.className = 'tile program'; el.innerHTML = '<video autoplay playsinline></video><div class="av" style="font-size:1.1rem;text-align:center;padding:20px"></div><button class="rbtn primary" id="unmuteBtn" hidden style="position:absolute;right:12px;top:12px">🔊 Tap to hear</button>'; stage.innerHTML = ''; stage.appendChild(el); t = tiles.__program = { el: el, v: el.querySelector('video'), av: el.querySelector('.av'), nm: null, bound: null }; t.el.querySelector('#unmuteBtn').addEventListener('click', function () { t.v.muted = false; t.v.play(); this.hidden = true; }); }
    var live = programLive && whep && whep.stream;
    t.av.style.display = live ? 'none' : 'grid';
    t.av.textContent = programLive ? 'Connecting to the live stream…' : 'The presenter has not gone live yet. Please stay on this page — the video will start automatically.';
    stage.dataset.n = 1;
  }
  function renderStage() {
    if (BC && !amStage && !C.host) { renderProgramTile(); return; }
    var stage = $('#stage'), want = [];
    roster.forEach(function (r) { if (r.on_stage && r.admitted) want.push(r); });
    if (me && amStage && !want.some(function (r) { return r.peer_id === me.peer; })) want.push({ peer_id: me.peer, name: C.name, role: C.role, on_stage: 1 });
    if (status !== 'live' && !C.host) want = [];
    Object.keys(tiles).forEach(function (id) { if (!want.some(function (r) { return r.peer_id === id; })) { tiles[id].el.remove(); delete tiles[id]; } });
    var anyShare = false;
    want.forEach(function (r) {
      var isMe = me && r.peer_id === me.peer, t = tiles[r.peer_id];
      if (!t) { var el = document.createElement('div'); el.className = 'tile' + (isMe ? ' me' : ''); el.innerHTML = '<video autoplay playsinline' + (isMe ? ' muted' : '') + '></video><div class="av">' + esc(ini(r.name)) + '</div><div class="nm"></div>'; stage.appendChild(el); t = tiles[r.peer_id] = { el: el, v: el.querySelector('video'), av: el.querySelector('.av'), nm: el.querySelector('.nm'), bound: null }; }
      var stream = isMe ? (sharing && screenStream ? screenStream : localStream) : (peers[r.peer_id] && peers[r.peer_id].stream);
      if (stream && t.bound !== stream) { t.v.srcObject = stream; t.bound = stream; var pr = t.v.play(); if (pr && pr.catch) pr.catch(function () {}); }
      var vt = stream && stream.getVideoTracks()[0], camOn = isMe ? videoOn() : (r.cam == 1 && vt && !vt.muted !== false);
      var isShare = isMe ? sharing : !!(vt && /screen|window|monitor|display/i.test(vt.label || ''));
      t.av.style.display = (isMe ? videoOn() : (stream && vt && r.cam == 1)) ? 'none' : 'grid';
      t.el.classList.toggle('sharing', isShare); if (isShare) anyShare = true;
      t.nm.innerHTML = esc(r.name + (isMe ? ' (you)' : '')) + (isMe ? (audioOn() ? '' : ' 🔇') : (r.mic == 0 ? ' 🔇' : ''));
      if (r.role === 'host') stage.insertBefore(t.el, stage.firstChild);
    });
    stage.dataset.n = want.length; stage.classList.toggle('has-share', anyShare && want.length > 1);
    if (!want.length && status === 'live' && !C.host && !BC) stage.innerHTML = '<div class="empty-stage"><h2>Waiting for the presenter to appear…</h2></div>';
  }
  function updateControls() {
    var s = amStage;
    $('#micBtn').hidden = !s; $('#camBtn').hidden = !s; $('#shareBtn').hidden = !s; $('#handBtn').hidden = s || C.host;
    var m = $('#micBtn'), c = $('#camBtn'); m.classList.toggle('off', !audioOn()); c.classList.toggle('off', !videoOn() || !camTrack); $('#shareBtn').classList.toggle('on', sharing);
    m.querySelector('span:last-child').textContent = audioOn() ? 'Mute' : 'Unmute'; c.querySelector('span:last-child').textContent = camTrack && camTrack.enabled ? 'Stop video' : 'Start video';
  }


  /* ======================= broadcast mode ======================= */
  function waitIce(pc) {
    return new Promise(function (res) {
      if (pc.iceGatheringState === 'complete') return res();
      var done = function () { pc.removeEventListener('icegatheringstatechange', chk); res(); };
      var chk = function () { if (pc.iceGatheringState === 'complete') done(); };
      pc.addEventListener('icegatheringstatechange', chk); setTimeout(done, 2500);
    });
  }
  function mediaUrl(kind) { return media.base + '/' + media.path + '/' + kind + '?token=' + encodeURIComponent(kind === 'whip' ? media.publish : media.read); }

  /* viewers: pull the program feed */
  function viewerSync() {
    if (C.host) return;
    if (amStage || !programLive || status !== 'live') { stopWhep(); return; }
    if (!whep) startWhep();
  }
  function startWhep() {
    if (whep || !media) return;
    var pc = new RTCPeerConnection({ iceServers: C.ice }); var w = whep = { pc: pc, stream: null, t: Date.now() };
    pc.addTransceiver('video', { direction: 'recvonly' }); pc.addTransceiver('audio', { direction: 'recvonly' });
    pc.ontrack = function (e) {
      w.stream = e.streams[0]; var t = tiles.__program; if (!t) { renderProgramTile(); t = tiles.__program; }
      t.v.srcObject = w.stream; t.bound = w.stream; var p = t.v.play(); if (p && p.catch) p.catch(function () { t.v.muted = true; t.v.play(); var b = t.el.querySelector('#unmuteBtn'); if (b) b.hidden = false; });
      renderProgramTile();
    };
    pc.onconnectionstatechange = function () { if (['failed', 'closed'].indexOf(pc.connectionState) >= 0 && whep === w) { stopWhep(); } };
    pc.createOffer().then(function (o) { return pc.setLocalDescription(o); }).then(function () { return waitIce(pc); })
      .then(function () { return fetch(mediaUrl('whep'), { method: 'POST', headers: { 'Content-Type': 'application/sdp' }, body: pc.localDescription.sdp }); })
      .then(function (r) { if (r.status !== 201) throw new Error('whep ' + r.status); return r.text(); })
      .then(function (ans) { return pc.setRemoteDescription({ type: 'answer', sdp: ans }); })
      .catch(function () { if (whep === w) stopWhep(); });
  }
  function stopWhep() {
    var w = whep; if (!w) return; whep = null; try { w.pc.close(); } catch (e) {}
    var t = tiles.__program; if (t) { t.v.srcObject = null; t.bound = null; } renderStage();
    setTimeout(function () { if (!whep && programLive && !amStage && !C.host && !stopped) viewerSync(); }, 3000);
  }

  /* host: mix the stage into one picture + sound and publish it */
  function drawProgram() {
    if (!comp) return; var ctx = comp.ctx, W = comp.c.width, H = comp.c.height;
    ctx.fillStyle = '#0a1511'; ctx.fillRect(0, 0, W, H);
    var list = Object.keys(tiles).filter(function (id) { return id !== '__program'; }).map(function (id) { return { id: id, t: tiles[id] }; });
    var share = list.filter(function (x) { return x.t.el.classList.contains('sharing'); })[0], rest = share ? list.filter(function (x) { return x !== share; }) : list;
    function box(x, y, w, h, it, contain) {
      var v = it.t.v, showAv = it.t.av.style.display !== 'none' || !(v.videoWidth > 0);
      ctx.save(); ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
      ctx.fillStyle = '#0d2419'; ctx.fillRect(x, y, w, h);
      if (!showAv) { var vr = v.videoWidth / v.videoHeight, br = w / h, dw, dh; if ((vr > br) === !!contain) { dw = w; dh = w / vr; } else { dh = h; dw = h * vr; } ctx.drawImage(v, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh); }
      else { ctx.fillStyle = '#00ed88'; ctx.font = '700 ' + Math.round(Math.min(w, h) / 4) + 'px Montserrat, Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(it.t.av.textContent, x + w / 2, y + h / 2); }
      var nm = it.t.nm ? it.t.nm.textContent : ''; if (nm) { ctx.font = '600 20px Montserrat, Arial'; var tw = ctx.measureText(nm).width + 24; ctx.fillStyle = 'rgba(0,0,0,.6)'; ctx.fillRect(x + 14, y + h - 46, tw, 34); ctx.fillStyle = '#fff'; ctx.textAlign = 'left'; ctx.textBaseline = 'middle'; ctx.fillText(nm, x + 26, y + h - 29); }
      ctx.restore();
    }
    if (!list.length) return;
    if (share) { var rw = rest.length ? W * 0.22 : 0; box(0, 0, W - rw, H, share, true); rest.slice(0, 4).forEach(function (it, i) { var h = H / Math.min(rest.length, 4); box(W - rw + 6, i * h, rw - 6, h - 6, it, false); }); return; }
    var n = list.length, cols = n <= 1 ? 1 : n <= 4 ? 2 : 3, rows = Math.ceil(n / cols), cw = (W - 12 * (cols - 1)) / cols, ch = (H - 12 * (rows - 1)) / rows;
    list.forEach(function (it, i) { var r = Math.floor(i / cols), c = i % cols, cnt = r === rows - 1 ? n - r * cols : cols, off = (cols - cnt) * (cw + 12) / 2; box(c * (cw + 12) + off, r * (ch + 12), cw, ch, it, false); });
  }
  function syncAudio() {
    if (!audioCtx) return; var want = {};
    if (localStream && localStream.getAudioTracks()[0]) want.local = localStream;
    Object.keys(peers).forEach(function (id) { var st = peers[id].stream; if (st && st.getAudioTracks().length) want[id] = st; });
    Object.keys(audioSrc).forEach(function (k) { if (want[k] !== audioSrc[k].stream) { try { audioSrc[k].node.disconnect(); } catch (e) {} delete audioSrc[k]; } });
    Object.keys(want).forEach(function (k) { if (!audioSrc[k]) { try { var n = audioCtx.createMediaStreamSource(want[k]); n.connect(audioDest); audioSrc[k] = { node: n, stream: want[k] }; } catch (e) {} } });
  }
  function startProgram() {
    if (!media || !media.publish) { toast('The media server is not available for this session.'); return Promise.reject(); }
    audioCtx = new (window.AudioContext || window.webkitAudioContext)(); audioCtx.resume(); audioDest = audioCtx.createMediaStreamDestination();
    var c = document.createElement('canvas'); c.width = 1280; c.height = 720; comp = { c: c, ctx: c.getContext('2d'), timer: setInterval(function () { drawProgram(); syncAudio(); }, 40) };
    var vt = c.captureStream(25).getVideoTracks()[0], out = new MediaStream([vt, audioDest.stream.getAudioTracks()[0]]);
    var pc = new RTCPeerConnection({ iceServers: C.ice }); pub = { pc: pc, loc: null };
    try { vt.contentHint = 'detail'; } catch (e) {}
    var vtr = pc.addTransceiver(vt, { direction: 'sendonly', streams: [out], sendEncodings: [{ maxBitrate: 2500000, maxFramerate: 25 }] }); pc.addTrack(out.getAudioTracks()[0], out);
    pc.onconnectionstatechange = function () { if (pc.connectionState === 'failed') { toast('The connection to the media server dropped. Press “Go live” again.'); stopProgram(true); } bcUi(); };
    return pc.createOffer().then(function (o) { return pc.setLocalDescription(o); }).then(function () { return waitIce(pc); })
      .then(function () { return fetch(mediaUrl('whip'), { method: 'POST', headers: { 'Content-Type': 'application/sdp' }, body: pc.localDescription.sdp }); })
      .then(function (r) { if (r.status !== 201) throw new Error('The media server refused the connection (' + r.status + ').'); pub.loc = r.headers.get('Location'); return r.text(); })
      .then(function (ans) { return pc.setRemoteDescription({ type: 'answer', sdp: ans }); })
      .then(function () { try { var prm = vtr.sender.getParameters(); prm.degradationPreference = 'maintain-resolution'; if (prm.encodings && prm.encodings[0]) { prm.encodings[0].maxBitrate = 2500000; prm.encodings[0].maxFramerate = 25; prm.encodings[0].scaleResolutionDownBy = 1; } return vtr.sender.setParameters(prm); } catch (e) {} })
      .then(function () { return api('program', { on: 1 }); })
      .catch(function (e) { toast((e && e.message) || 'Could not start the broadcast.'); stopProgram(true); throw e; });
  }
  function stopProgram(quiet) {
    if (comp) { clearInterval(comp.timer); comp = null; }
    if (pub) { try { pub.pc.close(); } catch (e) {} if (pub.loc) { try { fetch(new URL(pub.loc, mediaUrl('whip')).href, { method: 'DELETE' }); } catch (e) {} } pub = null; }
    Object.keys(audioSrc).forEach(function (k) { try { audioSrc[k].node.disconnect(); } catch (e) {} }); audioSrc = {};
    if (audioCtx) { try { audioCtx.close(); } catch (e) {} audioCtx = null; }
    if (!quiet || programLive) api('program', { on: 0 }); bcUi();
  }

  /* host panel */
  function bcUi() {
    if (!C.host || !BC) return;
    var live = !!pub && pub.pc.connectionState !== 'failed';
    $('#bcProgram').textContent = live ? 'Stop broadcast' : 'Go live to attendees'; $('#bcProgram').className = 'rbtn ' + (live ? 'danger' : 'primary');
    $('#bcState').innerHTML = live ? '<span class="pill live">ON AIR</span> Attendees are watching the program feed.' : 'Not broadcasting. Attendees see a waiting message until you go live.';
    $('#bcSocial').disabled = !live; $('#bcSocial').textContent = socialLive ? 'Stop social stream' : 'Start social stream'; $('#bcSocial').className = 'rbtn ' + (socialLive ? 'danger' : 'primary');
    $('#hostLive').hidden = false;
  }
  function renderDests() {
    var box = $('#bcDests'); if (!box) return;
    box.innerHTML = dests.length ? dests.map(function (d) { return '<label class="chk"><input type="checkbox" value="' + d.id + '" ' + (selDests.indexOf(d.id) >= 0 ? 'checked' : '') + (d.ready ? '' : ' disabled') + '> ' + esc(d.label) + ' <span class="muted">' + esc(d.platform) + (d.ready ? '' : ' — missing key') + '</span></label>'; }).join('') : '<p class="muted small">No social destinations yet. Add them in Admin → Live → Streaming.</p>';
  }
  function pollSocial() {
    if (!socialLive) { $('#bcSocialState').textContent = ''; return; }
    api('social_status').then(function (r) {
      var s = r.ok && r.status && r.status.streams && r.status.streams[0];
      $('#bcSocialState').innerHTML = s ? '<b>' + esc(s.state === 'live' ? 'LIVE' : s.state) + '</b> · ' + esc((s.destinations || []).join(', ')) + (s.speed && s.state === 'live' ? ' · ' + esc(s.speed) + ' realtime' : '') + (s.last_error && s.state !== 'live' ? '<br><span class="muted">' + esc(s.last_error) + '</span>' : '') : (r.error ? esc(r.error) : 'Starting…');
    });
  }
  function initBroadcastUi() {
    if (!BC || !C.host) { var tb = document.querySelector('[data-tab=broadcast]'); if (tb) tb.hidden = true; return; }
    renderDests();
    $('#bcProgram').addEventListener('click', function () { var b = this; if (pub) { if (socialLive) api('social_stop', {}); stopProgram(); return; } b.disabled = true; startProgram().then(function () { bcUi(); }, function () {}).then(function () { b.disabled = false; }); });
    $('#bcSocial').addEventListener('click', function () {
      var b = this; if (socialLive) { api('social_stop', {}).then(function () { socialLive = false; bcUi(); pollSocial(); }); return; }
      var ids = Array.prototype.slice.call(document.querySelectorAll('#bcDests input:checked')).map(function (i) { return +i.value; });
      if (!ids.length) { toast('Tick at least one destination first.'); return; }
      b.disabled = true; api('social_start', { dests: ids }).then(function (r) { b.disabled = false; if (!r.ok) { toast(r.error || 'Could not start the social stream.'); return; } socialLive = true; bcUi(); toast('Connecting to your destinations… it can take 10–20 seconds to appear live.'); pollSocial(); });
    });
    bcTimer = setInterval(pollSocial, 5000);
  }

  /* ---------- recording (host's browser, saved locally) ---------- */
  function toggleRecord() {
    var b = $('#recBtn');
    if (recorder) { recorder.stop(); return; }
    if (!navigator.mediaDevices.getDisplayMedia) { toast('Recording is not supported in this browser.'); return; }
    navigator.mediaDevices.getDisplayMedia({ video: true, audio: true, preferCurrentTab: true }).then(function (s) {
      var chunks = []; recorder = new MediaRecorder(s, { mimeType: MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus') ? 'video/webm;codecs=vp9,opus' : 'video/webm' });
      recorder.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
      recorder.onstop = function () { s.getTracks().forEach(function (t) { t.stop(); }); var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(chunks, { type: 'video/webm' })); a.download = C.slug + '-' + new Date().toISOString().slice(0, 16).replace(':', '') + '.webm'; a.click(); recorder = null; b.classList.remove('on'); b.querySelector('span:last-child').textContent = 'Record'; toast('Recording saved to your downloads.'); };
      s.getVideoTracks()[0].onended = function () { if (recorder) recorder.stop(); };
      recorder.start(1000); b.classList.add('on'); b.querySelector('span:last-child').textContent = 'Stop rec';
      toast('Recording this tab. Choose “This tab” and tick “Share tab audio” for sound.');
    }).catch(function () {});
  }

  function leave(ended) {
    stopped = true; if (pub) stopProgram(true); stopWhep(); Object.keys(peers).forEach(closePeer);
    [localStream, screenStream, previewStream].forEach(function (s) { if (s) s.getTracks().forEach(function (t) { t.stop(); }); });
    var done = function () { if (ended && !C.host) { alert('The session has ended. Thank you for joining.'); } location.href = C.leave; };
    if (me) api('leave', {}).then(done, done); else done();
  }
})();
