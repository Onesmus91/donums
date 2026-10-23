<section class="page-hero"><div class="wrap"><span class="eyebrow">Donums Careers</span><h1>Find your next opportunity</h1><p>Current vacancies from Donums and the employers we work with. Apply in minutes and follow every step of your application.</p></div></section>
<section class="section" style="padding-top:34px"><div class="wrap">
  <form class="filters" method="get" action="/jobs" style="grid-template-columns:1.4fr 1fr 150px 140px auto">
    <input type="search" name="q" value="<?= e($s) ?>" placeholder="Job title, skill or company"><input type="search" name="location" value="<?= e($loc) ?>" placeholder="Location">
    <select name="type" data-autosubmit aria-label="Job type"><option value="">Any type</option><?php foreach (JOB_TYPES as $t): ?><option <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
    <select name="mode" data-autosubmit aria-label="Work mode"><option value="">Any mode</option><?php foreach (JOB_MODES as $t): ?><option <?= $mode === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary">Search</button>
  </form>
  <?php if ($cats): ?><div class="chips" style="margin-bottom:20px"><a class="chip <?= $cat === '' ? 'on' : '' ?>" href="/jobs">All</a><?php foreach ($cats as $k): ?><a class="chip <?= $cat === $k ? 'on' : '' ?>" href="<?= e(url('/jobs', ['category' => $k])) ?>"><?= e($k) ?></a><?php endforeach; ?></div><?php endif; ?>
  <div class="layout-2">
    <div>
      <?php if (!$jobs): ?><?= empty_state('briefcase', $s || $loc || $type || $mode || $cat ? 'No vacancies match your search' : 'No open vacancies right now', 'Join our talent network and we will contact you when a suitable role opens.', '<a class="btn btn-primary btn-sm" href="/jobs/talent-network">Join the talent network</a>') ?>
      <?php else: ?><p class="muted small"><?= plural($pg['total'], 'vacancy', 'vacancies') ?></p><div class="card pad0"><?php foreach ($jobs as $j) { echo partial('jobs/_row', ['j' => $j]); } ?></div><?= pager($pg, '/jobs', ['q' => $s, 'location' => $loc, 'type' => $type, 'mode' => $mode, 'category' => $cat]) ?><?php endif; ?>
    </div>
    <aside>
      <div class="card"><h3>Join the talent network</h3><p class="muted small">Not seeing the right role? Share your CV once and we will reach out when something fits.</p><a class="btn btn-primary btn-sm" href="/jobs/talent-network">Share my CV</a></div>
      <div class="card mt2"><h3>Already applied?</h3><p class="muted small">Check where your application stands.</p><a class="btn btn-ghost btn-sm" href="/jobs/status">Track my application</a></div>
      <div class="card mt2"><h3>Hiring?</h3><p class="muted small">Advertise a role, or let Donums source, screen and shortlist for you.</p><a class="btn btn-ghost btn-sm" href="/jobs/employers">Post a vacancy</a></div>
    </aside>
  </div>
</div></section>
