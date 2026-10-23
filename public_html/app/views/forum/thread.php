<?php $me = user(); $mod = is_mod(); $locked = $t['is_locked'] && !$mod; $top = array_values(array_filter($posts, fn($p) => !$p['parent_id'])); $kids = []; foreach ($posts as $p) { if ($p['parent_id']) { $kids[$p['parent_id']][] = $p; } }
$render = function (array $p, bool $child) use ($t, $myVotes, $me, $mod, $locked) { $isMine = $me && (int) $me['id'] === (int) $p['user_id']; $ans = (int) $t['solved_post_id'] === (int) $p['id']; $staff = $p['is_super'] || trim((string) $p['roles']) !== ''; ?>
  <article class="post <?= $child ? 'child' : '' ?> <?= $ans ? 'answer' : '' ?>" id="post-<?= (int) $p['id'] ?>"><a href="/forum/u/<?= (int) $p['user_id'] ?>"><?= avatar_html($p, 46) ?></a>
    <div>
      <div class="post-head"><b><?= e($p['name']) ?></b><?= $staff ? badge('Staff', 'dark') : '' ?><span><?= (int) $p['reputation'] ?> pts</span><span>· <?= e(ago($p['created_at'])) ?><?= $p['edited_at'] ? ' (edited)' : '' ?></span><?= $ans ? badge('Accepted answer', 'green') : '' ?><?= $p['status'] === 'pending' ? badge('Awaiting approval', 'amber') : '' ?></div>
      <div id="body-<?= (int) $p['id'] ?>"><?= md($p['body']) ?></div>
      <div class="post-actions">
        <button class="vote <?= isset($myVotes['post' . $p['id']]) ? 'vote-on' : '' ?>" data-vote="post" data-id="<?= (int) $p['id'] ?>" <?= $me ? '' : 'disabled' ?>><?= icon('thumb', 15) ?> <span><?= (int) $p['score'] ?></span></button>
        <?php if (!$locked && $me && !$child): ?><a href="#reply-form" data-reply-to="<?= (int) $p['id'] ?>" data-name="<?= e($p['name']) ?>">Reply</a><?php endif; ?>
        <?php if ($me && ((int) $me['id'] === (int) $t['user_id'] || $mod)): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="solve"><input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>"><button><?= icon('check', 15) ?> <?= $ans ? 'Unmark answer' : 'Mark as answer' ?></button></form><?php endif; ?>
        <?php if ($me && !$isMine): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline" onsubmit="var r=prompt('Why are you reporting this? (optional)');if(r===null)return false;this.reason.value=r"><?= csrf_field() ?><input type="hidden" name="action" value="report"><input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="reason" value=""><button><?= icon('flag', 14) ?> Report</button></form><?php endif; ?>
        <?php if ($isMine || $mod): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>"><button data-confirm="Delete this reply?"><?= icon('trash', 14) ?> Delete</button></form><?php endif; ?>
      </div>
    </div></article>
<?php }; ?>
<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/forum">Community</a> / <a href="/forum/c/<?= e($t['category_slug']) ?>"><?= e($t['category_name']) ?></a><?php if ($course): ?> / <a href="/learning/<?= e($course['slug']) ?>"><?= e($course['title']) ?></a><?php endif; ?></div>
  <h1 style="font-size:clamp(1.5rem,3vw,2.2rem)"><?= e($t['title']) ?></h1>
  <div class="meta" style="color:#b8cdc0"><span><?= e($t['author_name']) ?></span><span><?= e(fmt_date($t['created_at'])) ?></span><span><?= number_format((int) $t['views']) ?> views</span><?= $t['solved_post_id'] ? badge('Solved', 'green') : '' ?><?= $t['is_locked'] ? badge('Locked', 'amber') : '' ?><?= $t['status'] !== 'published' ? badge(ucfirst($t['status']), 'amber') : '' ?></div></div></section>
<section class="section" style="padding-top:34px"><div class="wrap narrow" style="max-width:900px">
  <article class="post" id="op"><a href="/forum/u/<?= (int) $t['user_id'] ?>"><?= avatar_for((int) $t['user_id'], 46) ?></a><div>
    <div class="post-head"><b><?= e($t['author_name']) ?></b><span><?= (int) $t['author_rep'] ?> pts</span><span><?= e($t['author_headline']) ?></span><?= $t['edited_at'] ? '<span>(edited)</span>' : '' ?></div>
    <?= md($t['body']) ?>
    <?php if ($t['tags']): ?><div class="chips mt1"><?php foreach (explode(',', $t['tags']) as $tg): ?><span class="chip"><?= e($tg) ?></span><?php endforeach; ?></div><?php endif; ?>
    <div class="post-actions"><button class="vote <?= isset($myVotes['thread' . $t['id']]) ? 'vote-on' : '' ?>" data-vote="thread" data-id="<?= (int) $t['id'] ?>" <?= $me ? '' : 'disabled' ?>><?= icon('thumb', 15) ?> <span><?= (int) $t['score'] ?></span></button>
      <?php if ($me): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="subscribe"><button><?= icon('bell', 14) ?> <?= $subscribed ? 'Following' : 'Follow' ?></button></form><?php endif; ?>
      <?php if ($me && (int) $me['id'] !== (int) $t['user_id']): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline" onsubmit="var r=prompt('Why are you reporting this? (optional)');if(r===null)return false;this.reason.value=r"><?= csrf_field() ?><input type="hidden" name="action" value="report"><input type="hidden" name="reason" value=""><button><?= icon('flag', 14) ?> Report</button></form><?php endif; ?>
      <?php if ($mod): foreach (['pin' => $t['is_pinned'] ? 'Unpin' : 'Pin', 'lock' => $t['is_locked'] ? 'Unlock' : 'Lock', 'hide' => $t['status'] === 'hidden' ? 'Unhide' : 'Hide'] as $a => $lbl): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><button><?= $lbl ?></button></form><?php endforeach; endif; ?>
      <?php if ($me && ((int) $me['id'] === (int) $t['user_id'] || $mod)): ?><form method="post" action="/forum/t/<?= (int) $t['id'] ?>/action" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button data-confirm="Delete this discussion?"><?= icon('trash', 14) ?> Delete</button></form><?php endif; ?></div>
  </div></article>

  <h2 style="font-size:1.25rem" class="mt3"><?= plural(count($posts), 'reply', 'replies') ?></h2>
  <?php foreach ($top as $p) { $render($p, false); foreach ($kids[$p['id']] ?? [] as $k) { $render($k, true); } } ?>
  <?php if (!$posts): ?><p class="muted">No replies yet — be the first to respond.</p><?php endif; ?>

  <div class="mt3" id="reply-form">
  <?php if ($locked): ?><div class="callout warn">This discussion is locked and no longer accepts replies.</div>
  <?php elseif (!$me): ?><div class="callout">Please <a href="<?= e(url('/account/login', ['next' => thread_url($t)])) ?>">sign in</a> or <a href="<?= e(url('/account/register', ['next' => thread_url($t)])) ?>">create an account</a> to reply.</div>
  <?php elseif ($b = forum_post_block()): ?><div class="callout warn"><?= e($b) ?></div>
  <?php else: ?><form class="card form" method="post" action="/forum/t/<?= (int) $t['id'] ?>/reply"><?= csrf_field() . spam_fields() ?><input type="hidden" name="parent_id" id="parent_id" value=""><h3 id="reply-title">Add your reply</h3>
    <div class="field"><textarea name="body" id="reply-body" rows="5" required placeholder="Share your answer or experience…" aria-label="Your reply"></textarea></div><div><button class="btn btn-primary">Post reply</button> <button type="button" class="link" id="cancel-reply" hidden>Cancel reply-to</button></div></form><?php endif; ?></div>
</div></section>
<script>
document.querySelectorAll('[data-vote]').forEach(function(b){b.addEventListener('click',function(){var f=new FormData();f.append('target',b.dataset.vote);f.append('id',b.dataset.id);f.append('_csrf','<?= e(csrf_token()) ?>');
 fetch('/forum/vote',{method:'POST',body:f,headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){if(d.ok){b.querySelector('span').textContent=d.score;b.classList.toggle('vote-on',!!d.mine)}else if(d.error){alert(d.error)}})})});
var pid=document.getElementById('parent_id'),ttl=document.getElementById('reply-title'),cx=document.getElementById('cancel-reply');
document.querySelectorAll('[data-reply-to]').forEach(function(a){a.addEventListener('click',function(){if(!pid)return;pid.value=a.dataset.replyTo;ttl.textContent='Replying to '+a.dataset.name;cx.hidden=false;})});
if(cx)cx.addEventListener('click',function(){pid.value='';ttl.textContent='Add your reply';cx.hidden=true});
</script>
