<?php $compact = $compact ?? false; $org = $j['confidential'] ? 'Confidential employer' : ($j['organisation'] ?: 'Donums'); ?>
<a class="job-row" href="<?= e(job_url($j)) ?>" style="color:inherit;border-bottom:1px solid #edf2ee;<?= $compact ? '' : '' ?>">
  <div class="job-logo"><?= e(initials($org)) ?></div>
  <div style="flex:1;min-width:0">
    <h3><?= e($j['title']) ?> <?= $j['featured'] ? badge('Featured', 'dark') : '' ?></h3>
    <div class="muted small"><?= e($org) ?></div>
    <div class="meta mt1"><?php if ($j['location'] && strcasecmp($j['location'], $j['work_mode']) !== 0): ?><span><?= icon('pin', 14) ?><?= e($j['location']) ?></span><?php endif; ?><span><?= icon('briefcase', 14) ?><?= e($j['type']) ?></span><?php if (strcasecmp($j['type'], $j['work_mode']) !== 0): ?><span><?= e($j['work_mode']) ?></span><?php endif; ?><?php if ($j['salary_text']): ?><span><?= icon('dollar', 14) ?><?= e($j['salary_text']) ?></span><?php endif; ?></div>
  </div>
  <?php if (!$compact): ?><div class="small muted" style="text-align:right;white-space:nowrap"><?= e(ago($j['published_at'])) ?><?php if ($j['deadline']): ?><br>Closes <?= e(fmt_date($j['deadline'])) ?><?php endif; ?></div><?php endif; ?>
</a>
