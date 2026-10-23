<div id="app" data-host="<?= $host ? '1' : '0' ?>">
  <!-- Pre-join lobby: device check + explicit click (required so browsers allow audio playback) -->
  <section id="lobby" class="lobby"><div class="lobby-card">
    <img src="/assets/img/logo.png" alt="Donums" class="lobby-logo">
    <h1><?= e($w['title']) ?></h1>
    <p class="lobby-sub" id="lobbySub"><?= $host ? 'You are the host of this session.' : 'You are joining as <strong>' . e($cfg['name']) . '</strong>' ?></p>
    <div class="preview" id="previewBox" hidden><video id="previewVideo" autoplay muted playsinline></video><div class="meter"><i id="meter"></i></div></div>
    <div class="lobby-opts" id="lobbyOpts" hidden><label><input type="checkbox" id="optCam" checked> Camera on</label><label><input type="checkbox" id="optMic" checked> Microphone on</label></div>
    <div id="lobbyErr" class="lobby-err" hidden></div>
    <button class="rbtn primary big" id="joinBtn">Join session</button>
    <p class="muted small" id="lobbyHint"><?= $host ? 'Your camera and microphone will be requested next.' : 'You will be able to watch, chat and ask questions. The host may invite you on camera.' ?></p>
  </div></section>

  <div id="room" hidden>
    <header class="rbar"><div class="rt"><strong id="rTitle"><?= e($w['title']) ?></strong><span class="pill" id="statePill">Not started</span><span class="pill ghost" id="clock"></span></div>
      <div class="rt right"><span class="pill ghost" id="count">1 online</span><?php if ($host): ?><button class="rbtn primary" id="startBtn" hidden>Start webinar</button><button class="rbtn danger" id="endBtn" hidden>End for all</button><?php endif; ?></div></header>
    <div class="banner" id="banner" hidden></div>
    <div class="rmain">
      <main class="stage-wrap"><div id="waiting" class="waiting" hidden><h2>Waiting for the host</h2><p id="waitingText">The host will let you in shortly.</p></div>
        <div id="notLive" class="waiting" hidden><h2>The session hasn’t started yet</h2><p>You’re in the waiting room. This page will update automatically when the host goes live.</p></div>
        <div id="stage" class="stage" data-n="0"></div></main>
      <aside class="side" id="side"><div class="rtabs" role="tablist"><button data-tab="chat" class="on">Chat</button><button data-tab="qa">Q&amp;A</button><button data-tab="poll">Polls</button><button data-tab="people">People <span class="badge-n" id="handBadge" hidden>0</span></button><?php if ($host && !empty($cfg['broadcast'])): ?><button data-tab="broadcast">Broadcast</button><?php endif; ?></div>
        <div class="pane" data-pane="chat"><div class="msgs" id="chatList" aria-live="polite"></div><form class="compose" id="chatForm"><input id="chatInput" maxlength="500" placeholder="Message everyone…" autocomplete="off"><button class="rbtn primary">Send</button></form></div>
        <div class="pane" data-pane="qa" hidden><div class="msgs" id="qaList"></div><form class="compose" id="qaForm"><input id="qaInput" maxlength="500" placeholder="Ask a question…" autocomplete="off"><button class="rbtn primary">Ask</button></form></div>
        <div class="pane" data-pane="poll" hidden><div class="msgs" id="pollBox"><p class="muted">No poll yet.</p></div>
          <?php if ($host): ?><form class="pollform" id="pollForm"><input name="question" placeholder="Poll question" maxlength="200" required><input name="o1" placeholder="Option 1" required><input name="o2" placeholder="Option 2" required><input name="o3" placeholder="Option 3 (optional)"><input name="o4" placeholder="Option 4 (optional)"><div style="display:flex;gap:8px"><button class="rbtn primary">Launch poll</button><button type="button" class="rbtn" id="pollClose">Close poll</button></div></form><?php endif; ?></div>
        <div class="pane" data-pane="people" hidden><div class="msgs" id="people"></div><?php if ($host): ?><div class="compose col"><div style="display:flex;gap:8px"><input id="announceInput" maxlength="200" placeholder="Announcement to everyone"><button class="rbtn" id="announceBtn">Send</button></div><label class="small"><input type="checkbox" id="chatToggle" checked> Allow attendee chat</label></div><?php endif; ?></div>
        <?php if ($host && !empty($cfg['broadcast'])): ?><div class="pane" data-pane="broadcast" hidden><div class="msgs">
          <h3 style="margin:0 0 6px">1 · Attendees</h3><p class="small muted" id="bcState">Not broadcasting.</p><button class="rbtn primary" id="bcProgram">Go live to attendees</button>
          <p class="xs muted">This mixes everyone on stage into one picture and sends it to the media server. Keep this tab open and in front while you are live.</p>
          <h3 style="margin:18px 0 6px">2 · Social media</h3><div id="bcDests"></div><button class="rbtn primary" id="bcSocial" disabled>Start social stream</button><p class="small" id="bcSocialState" style="margin-top:10px"></p>
          <p class="xs muted">Social platforms usually show your live video 10–30 seconds behind real time. Tell people to watch there, and ask questions in the chat.</p><span id="hostLive" hidden></span></div></div><?php endif; ?>
      </aside>
    </div>
    <footer class="controls" id="controls">
      <button class="cbtn" id="micBtn" title="Microphone" hidden><span class="ico" data-ic="mic"></span><span>Mic</span></button>
      <button class="cbtn" id="camBtn" title="Camera" hidden><span class="ico" data-ic="video"></span><span>Camera</span></button>
      <button class="cbtn" id="shareBtn" title="Share your screen" hidden><span class="ico" data-ic="screen"></span><span>Share</span></button>
      <button class="cbtn" id="handBtn" title="Raise your hand" hidden><span class="ico" data-ic="hand"></span><span>Raise hand</span></button>
      <?php if ($host): ?><button class="cbtn" id="recBtn" title="Record this tab (saved to your computer)"><span class="ico" data-ic="rec"></span><span>Record</span></button><button class="cbtn" id="inviteBtn" title="Copy attendee link"><span class="ico" data-ic="link"></span><span>Invite</span></button><?php endif; ?>
      <button class="cbtn" id="sideBtn" title="Chat and people"><span class="ico" data-ic="chat"></span><span>Chat</span></button>
      <button class="cbtn leave" id="leaveBtn"><span class="ico" data-ic="leave"></span><span>Leave</span></button>
    </footer>
  </div>
</div>
<script>window.ROOM = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="/assets/js/room.js?v=<?= DN_VERSION ?>"></script>
