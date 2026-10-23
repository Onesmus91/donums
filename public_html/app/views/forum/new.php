<section class="page-hero"><div class="wrap"><div class="crumbs"><a href="/forum">Community</a> / New discussion</div><h1>Start a discussion</h1><p>A clear title and enough detail get the best answers.</p></div></section>
<section class="section" style="padding-top:34px"><div class="wrap narrow">
<?php if ($block): ?><div class="callout warn"><?= e($block) ?> <?= user() ? '<a href="/account">Go to my account</a>' : '' ?></div>
<?php else: ?>
<form class="card form" method="post" action="/forum/new"><?= csrf_field() . spam_fields() ?>
  <?php if ($course): ?><input type="hidden" name="course_id" value="<?= (int) $course ?>"><div class="callout info small">This discussion will be linked to the course you came from, so classmates and instructors can help.</div><?php endif; ?>
  <div class="field"><label for="category_id">Category</label><select id="category_id" name="category_id" required><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $sel === $c['slug'] || ($course && $c['slug'] === 'learning') ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="title">Title</label><input id="title" name="title" required minlength="8" maxlength="160" placeholder="Summarise your question or topic"></div>
  <div class="field"><label for="body">Details</label><textarea id="body" name="body" rows="9" required minlength="20" placeholder="Share context, what you have tried, and what you would like help with."></textarea><span class="hint">Formatting: **bold**, _italic_, `code`, lists with “- ”, quotes with “&gt; ”, and [links](https://…).</span></div>
  <div class="field"><label for="tags">Tags <span class="muted">(optional, comma-separated)</span></label><input id="tags" name="tags" maxlength="120" placeholder="cv, interview, payroll"></div>
  <div><button class="btn btn-primary">Post discussion</button></div>
</form><?php endif; ?>
</div></section>
