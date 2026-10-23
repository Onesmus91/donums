<?php $isDone = in_array($lesson['id'], array_map('intval', $done)); $emb = $lesson['video_url'] ? video_embed($lesson['video_url']) : null;
$uploadedVideo = $lesson['file_path'] && $lesson['kind'] === 'video'; ?>
<div class="player">
  <nav class="player-nav" aria-label="Course content">
    <div style="padding:16px 18px"><a class="small" href="/learning/<?= e($c['slug']) ?>"><?= icon('back', 14) ?> Course overview</a><h3 style="font-size:1rem;margin:8px 0 8px"><?= e($c['title']) ?></h3>
      <?php if ($enrol): ?><div class="progress"><i style="width:<?= (int) $enrol['progress'] ?>%"></i></div><div class="xs muted mt1"><?= (int) $enrol['progress'] ?>% complete</div><?php endif; ?></div>
    <?php foreach ($outline as $m): ?><div class="mod-t"><?= e($m['title']) ?></div>
      <?php foreach ($m['lessons'] as $l): $d = in_array($l['id'], array_map('intval', $done)); $locked = !$access && !$l['is_preview']; ?>
      <a class="l <?= $l['id'] == $lesson['id'] ? 'on' : '' ?> <?= $d ? 'done' : '' ?>" href="<?= $locked ? '#' : '/learning/' . e($c['slug']) . '/learn/' . (int) $l['id'] ?>"><?= icon($d ? 'ok' : ($locked ? 'lock' : lesson_icon($l['kind'])), 16) ?><span><?= e($l['title']) ?><?php if ($l['duration_min']): ?><span class="muted xs" style="display:block"><?= (int) $l['duration_min'] ?> min</span><?php endif; ?></span></a>
    <?php endforeach; endforeach; ?>
  </nav>
  <div class="player-main">
    <?= flash_html() ?>
    <span class="eyebrow"><?= e(ucfirst($lesson['kind'])) ?> lesson</span><h1 style="font-size:1.8rem"><?= e($lesson['title']) ?></h1>
    <?php if ($emb && $emb[0] === 'iframe'): ?><div class="video-box"><iframe src="<?= e($emb[1]) ?>" allow="accelerometer;encrypted-media;picture-in-picture;fullscreen" allowfullscreen title="<?= e($lesson['title']) ?>"></iframe></div>
    <?php elseif ($emb && $emb[0] === 'video'): ?><div class="video-box"><video controls controlsList="nodownload" src="<?= e($emb[1]) ?>"></video></div>
    <?php elseif ($uploadedVideo): ?><div class="video-box"><video controls controlsList="nodownload" src="/file/lesson/<?= (int) $lesson['id'] ?>"></video></div><?php endif; ?>
    <?php if ($lesson['body']): ?><?= md($lesson['body']) ?><?php endif; ?>
    <?php if ($lesson['file_path'] && $lesson['kind'] !== 'video'): ?><div class="card mt2" style="display:flex;gap:14px;align-items:center;justify-content:space-between"><span><?= icon('file', 20) ?> <strong><?= e($lesson['file_name'] ?: 'Lesson file') ?></strong></span><a class="btn btn-primary btn-sm" href="/file/lesson/<?= (int) $lesson['id'] ?>"><?= icon('download', 15) ?> Download</a></div><?php endif; ?>

    <?php if ($lesson['kind'] === 'quiz'): ?>
      <?php if ($attempt): ?><div class="flash <?= $attempt['passed'] ? 'ok' : 'warn' ?> mt2"><?= icon($attempt['passed'] ? 'ok' : 'alert') ?><span>Your latest score: <strong><?= (int) $attempt['score'] ?>%</strong> — pass mark <?= (int) $lesson['pass_mark'] ?>%.</span></div><?php endif; ?>
      <?php if (!$questions): ?><p class="muted">This quiz has no questions yet.</p><?php elseif ($access): $last = $attempt ? json_dec($attempt['answers']) : []; ?>
      <form method="post" action="/learning/quiz/<?= (int) $lesson['id'] ?>" class="mt2"><?= csrf_field() ?>
        <?php foreach ($questions as $n => $qq): $opts = json_dec($qq['options']); $multi = $qq['kind'] === 'multi'; $ans = array_map('intval', json_dec($qq['answer'])); $given = $last[$qq['id']]['given'] ?? []; ?>
        <div class="card mb0" style="margin-bottom:14px"><p style="font-weight:700"><?= $n + 1 ?>. <?= e($qq['prompt']) ?> <?= $multi ? '<span class="muted small">(select all that apply)</span>' : '' ?></p>
          <?php foreach ($opts as $i => $o): $cls = $attempt ? (in_array($i, $ans, true) ? 'right' : (in_array($i, $given, true) ? 'wrong' : '')) : ''; ?>
          <label class="q-opt <?= $cls ?>"><input type="<?= $multi ? 'checkbox' : 'radio' ?>" name="q<?= (int) $qq['id'] ?>[]" value="<?= $i ?>" <?= in_array($i, $given, true) ? 'checked' : '' ?>><span><?= e($o) ?></span></label><?php endforeach; ?>
          <?php if ($attempt && $qq['explanation']): ?><p class="small muted mt1"><?= icon('alert', 14) ?> <?= e($qq['explanation']) ?></p><?php endif; ?></div>
        <?php endforeach; ?>
        <button class="btn btn-primary"><?= $attempt ? 'Retake quiz' : 'Submit answers' ?></button>
      </form><?php endif; ?>
    <?php endif; ?>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:34px;padding-top:22px;border-top:1px solid var(--line);align-items:center">
      <?php if ($prev): ?><a class="btn btn-ghost" href="/learning/<?= e($c['slug']) ?>/learn/<?= (int) $prev['id'] ?>"><?= icon('back', 16) ?> Previous</a><?php endif; ?>
      <span style="flex:1"></span>
      <?php if ($access && $lesson['kind'] !== 'quiz'): ?>
        <form method="post" action="/learning/lesson/<?= (int) $lesson['id'] ?>/complete"><?= csrf_field() ?><button class="btn btn-primary"><?= $isDone ? ($next ? 'Next lesson' : 'Done') : ($next ? 'Mark complete &amp; continue' : 'Mark complete') ?> <?= icon('arrow', 16) ?></button></form>
      <?php elseif ($next && ($isDone || $access)): ?><a class="btn btn-primary" href="/learning/<?= e($c['slug']) ?>/learn/<?= (int) $next['id'] ?>">Next lesson <?= icon('arrow', 16) ?></a>
      <?php elseif (!$access): ?><a class="btn btn-primary" href="/learning/<?= e($c['slug']) ?>">Enrol to unlock the full course</a><?php endif; ?>
    </div>
    <?php if ($enrol && $enrol['status'] === 'completed'): ?><div class="callout mt3"><?= icon('award', 18) ?> <strong>Course completed.</strong> <a href="/account">View your certificate in your account.</a></div><?php endif; ?>
    <?php if ($access): ?><div class="mt3 small muted"><?= icon('chat', 15) ?> Stuck? <a href="<?= e(url('/forum/new', ['course' => $c['id']])) ?>">Ask the community</a><?php foreach ($threads as $t): ?> · <a href="/forum/t/<?= (int) $t['id'] ?>-<?= e($t['slug']) ?>"><?= e(excerpt($t['title'], 40)) ?></a><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
