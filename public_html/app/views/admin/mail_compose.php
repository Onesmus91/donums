<form class="panel composer" method="post" action="/admin/mail/send" enctype="multipart/form-data" id="cform" style="max-width:1000px"><?= csrf_field() ?>
<input type="hidden" name="reply_id" value="<?= (int) ($reply['id'] ?? 0) ?>"><input type="hidden" name="draft_id" value="<?= (int) ($draft['id'] ?? 0) ?>"><input type="hidden" name="request" value="<?= e($request) ?>"><input type="hidden" name="existing_atts" value="<?= e(json_encode($attachments)) ?>">
<div class="panel-b form">
  <div class="row2"><div class="field"><label for="from">From</label><select id="from" name="from"><?php foreach ($boxes as $b): ?><option value="<?= e($b['address']) ?>" data-sig="<?= e($b['signature']) ?>" <?= $b['address'] === $mailbox ? 'selected' : '' ?>><?= e($b['display_name']) ?> &lt;<?= e($b['address']) ?>&gt;</option><?php endforeach; ?></select></div>
    <div class="field"><label for="tpl">Insert a saved reply</label><select id="tpl"><option value="">Choose a template…</option><?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>" data-subject="<?= e($t['subject']) ?>" data-body="<?= e(md($t['body'])) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div></div>
  <div class="field"><label for="to">To <span class="muted">(separate with commas)</span></label><input id="to" name="to" value="<?= e($to) ?>" list="contactList" autocomplete="off" required>
    <datalist id="contactList"></datalist></div>
  <div class="row2"><div class="field"><label for="cc">Cc</label><input id="cc" name="cc" value="<?= e($cc) ?>"></div><div class="field"><label for="bcc">Bcc</label><input id="bcc" name="bcc" value="<?= e($draft['bcc_list'] ?? '') ?>"></div></div>
  <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" value="<?= e($subject) ?>"></div>
  <div class="field"><label>Message</label><div class="rte"><div class="rte-bar" role="toolbar"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="underline"><u>U</u></button><button type="button" data-cmd="insertUnorderedList">• List</button><button type="button" data-cmd="insertOrderedList">1. List</button><button type="button" data-cmd="formatBlock" data-val="blockquote">❝</button><button type="button" id="rteLink">Link</button><button type="button" data-cmd="removeFormat">Clear</button></div>
    <div class="rte-area" id="rte" contenteditable="true"><?= $body !== '' ? $body : '<p><br></p>' ?></div></div><textarea name="body" id="bodyField" hidden></textarea></div>
  <div class="row3">
    <div class="field"><label>Attachments</label><input type="file" name="files[]" multiple><span class="hint">Up to 10 MB each.<?php foreach ($attachments as $a) { echo ' · ' . e($a['name']); } ?></span></div>
    <?= a_sel('Letterhead', 'letterhead', ['branded' => 'Donums branded', 'plain' => 'Plain (no header)'], 'branded') ?>
    <div class="field"><label for="schedule">Send later <span class="muted">(optional)</span></label><input id="schedule" type="datetime-local" name="schedule_at"></div></div>
  <label class="check"><input type="checkbox" name="use_signature" value="1" checked><span>Add the mailbox signature</span></label>
  <div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn btn-primary" name="action" value="send"><?= icon('send', 16) ?> Send</button><button class="btn btn-ghost" name="action" value="draft">Save draft</button><a class="btn btn-ghost" href="/admin/mail">Discard</a><span class="muted small" id="saved" style="align-self:center"></span></div>
</div></form>
<script>
(function(){
 var rte=document.getElementById('rte'),form=document.getElementById('cform'),body=document.getElementById('bodyField');
 document.querySelectorAll('[data-cmd]').forEach(function(b){b.addEventListener('mousedown',function(e){e.preventDefault()});b.addEventListener('click',function(){document.execCommand(b.dataset.cmd,false,b.dataset.val||null);rte.focus()})});
 document.getElementById('rteLink').addEventListener('click',function(){var u=prompt('Link address (https://…)');if(u&&/^https?:\/\/|^mailto:/i.test(u)){document.execCommand('createLink',false,u)}});
 form.addEventListener('submit',function(){body.value=rte.innerHTML});
 document.getElementById('tpl').addEventListener('change',function(){var o=this.options[this.selectedIndex];if(!o.value)return;var s=document.getElementById('subject');if(!s.value&&o.dataset.subject)s.value=o.dataset.subject;rte.innerHTML=o.dataset.body+rte.innerHTML;});
 // address suggestions
 var to=document.getElementById('to'),dl=document.getElementById('contactList'),t;
 function lastTerm(){var p=to.value.split(',');return p[p.length-1].trim()}
 to.addEventListener('input',function(){var term=lastTerm();if(term.length<2)return;clearTimeout(t);t=setTimeout(function(){fetch('/admin/contacts/suggest?q='+encodeURIComponent(term),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){var pre=to.value.replace(/[^,]*$/,'').replace(/\s*$/,'');pre=pre?pre+' ':'';dl.innerHTML='';(d.items||[]).forEach(function(c){var o=document.createElement('option');o.value=pre+c.email;o.label=c.name;dl.appendChild(o)})})},200)});
 // autosave draft every 40s
 var saved=document.getElementById('saved');setInterval(function(){if(!rte.textContent.trim()&&!document.getElementById('subject').value)return;var fd=new FormData(form);fd.set('body',rte.innerHTML);fd.set('action','draft');fetch('/admin/mail/send',{method:'POST',body:fd,headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){if(d.ok){form.draft_id.value=d.id;saved.textContent='Draft saved '+new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}}).catch(function(){})},40000);
})();
</script>
