<?php $org = $j['confidential'] ? 'Confidential employer' : ($j['organisation'] ?: 'Donums'); ?>
<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/jobs">Careers</a> / <?= e($j['category'] ?: 'Vacancy') ?></div>
  <h1><?= e($j['title']) ?></h1><p><?= e($org) ?></p>
  <div class="meta mt2" style="color:#b8cdc0"><?php if ($j['location']): ?><span><?= icon('pin', 15) ?><?= e($j['location']) ?></span><?php endif; ?><span><?= icon('briefcase', 15) ?><?= e($j['type']) ?></span><span><?= e($j['work_mode']) ?></span><?php if ($j['salary_text']): ?><span><?= icon('dollar', 15) ?><?= e($j['salary_text']) ?></span><?php endif; ?><?php if ($j['deadline']): ?><span><?= icon('calendar', 15) ?>Apply by <?= e(fmt_date($j['deadline'])) ?></span><?php endif; ?></div>
  <?php if ($j['status'] !== 'published'): ?><p class="mt2"><?= badge(ucfirst($j['status']) . ' — not public', 'amber') ?></p><?php endif; ?>
</div></section>
<section class="section" style="padding-top:34px"><div class="wrap layout-2">
  <div>
    <div class="card"><h2 style="font-size:1.3rem">About the role</h2><?= md((string) $j['description']) ?>
      <?php if ($j['requirements']): ?><h3 class="mt3">Requirements</h3><?= md((string) $j['requirements']) ?><?php endif; ?>
      <?php if ($j['required_skills']): ?><h3 class="mt3">Skills</h3><div class="chips"><?php foreach (csv_list($j['required_skills']) as $s): ?><span class="chip"><?= e($s) ?></span><?php endforeach; ?></div><?php endif; ?></div>
    <div class="card mt3" id="apply-box"><h2 style="font-size:1.3rem">Apply for this role</h2>
      <?php if ($closed): ?><div class="callout warn">Applications for this vacancy are closed. <a href="/jobs/talent-network">Join the talent network</a> to be considered for similar roles.</div>
      <?php else: ?><p class="muted small">Takes about three minutes. You will receive a reference so that you can follow your application.</p><?= partial('jobs/_apply_form', ['j' => $j, 'questions' => $questions]) ?><?php endif; ?></div>
  </div>
  <aside class="sticky"><div class="card"><h3>Job summary</h3><dl class="kv"><dt>Employer</dt><dd><?= e($org) ?></dd><dt>Type</dt><dd><?= e($j['type']) ?></dd><dt>Mode</dt><dd><?= e($j['work_mode']) ?></dd><?php if ($j['min_experience']): ?><dt>Experience</dt><dd><?= (int) $j['min_experience'] ?>+ years</dd><?php endif; ?><dt>Posted</dt><dd><?= e(fmt_date($j['published_at'] ?: $j['created_at'])) ?></dd></dl>
    <?php if (!$closed): ?><a class="btn btn-primary btn-block mt2" href="#apply-box">Apply now</a><?php endif; ?></div>
    <?php if ($similar): ?><div class="card mt2"><h3>Similar vacancies</h3><?php foreach ($similar as $s): ?><p class="small" style="margin:0 0 10px"><a href="<?= e(job_url($s)) ?>"><strong><?= e($s['title']) ?></strong></a><br><span class="muted"><?= e($s['location']) ?></span></p><?php endforeach; ?></div><?php endif; ?></aside>
</div></section>
