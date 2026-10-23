<section class="page-hero"><div class="wrap"><span class="eyebrow">Donums Academy</span><h1>Practical skills you can use at work on Monday</h1><p>Self-paced online courses with video lessons, quizzes and verifiable certificates — built around the skills Kenyan businesses and professionals actually need.</p></div></section>
<section class="section" style="padding-top:36px"><div class="wrap">
  <form class="filters" method="get" action="/learning">
    <input type="search" name="q" value="<?= e($s) ?>" placeholder="Search courses">
    <select name="cat" data-autosubmit aria-label="Category"><option value="">All categories</option><?php foreach ($cats as $k): ?><option value="<?= e($k['slug']) ?>" <?= $cat === $k['slug'] ? 'selected' : '' ?>><?= e($k['name']) ?> (<?= (int) $k['n'] ?>)</option><?php endforeach; ?></select>
    <select name="level" data-autosubmit aria-label="Level"><option value="">Any level</option><?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $l): ?><option <?= $level === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="sort" data-autosubmit aria-label="Sort"><?php foreach (['new' => 'Newest', 'popular' => 'Most popular', 'rating' => 'Highest rated', 'price' => 'Price: low to high'] as $k => $v): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$courses): ?>
    <?= empty_state('book', $s || $cat || $level ? 'No courses match your filters' : 'Courses are on their way', $s || $cat || $level ? 'Try removing a filter or searching for something broader.' : 'We are preparing our first courses. Create an account to be told when they launch.', $s || $cat || $level ? '<a class="btn btn-ghost btn-sm" href="/learning">Clear filters</a>' : (user() ? '' : '<a class="btn btn-primary btn-sm" href="/account/register">Create an account</a>')) ?>
  <?php else: ?>
    <div class="grid g3"><?php foreach ($courses as $c) { echo partial('learning/_card', ['c' => $c]); } ?></div>
    <?= pager($pg, '/learning', ['q' => $s, 'cat' => $cat, 'level' => $level, 'sort' => $sort !== 'new' ? $sort : null]) ?>
  <?php endif; ?>
  <div class="card mt4" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;justify-content:space-between"><div><h3 style="margin:0"><?= icon('award', 20) ?> Verify a certificate</h3><p class="muted small mb0">Employers can confirm that a Donums certificate is genuine using its code.</p></div><a class="btn btn-ghost btn-sm" href="/learning/verify">Verify a code</a></div>
</div></section>
