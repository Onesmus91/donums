<section class="page-hero"><div class="wrap"><span class="eyebrow">Donums Live</span><h1>Live learning, straight from your browser</h1><p>Join webinars and workshops hosted by Donums and guest experts. Ask questions, take part in polls and watch replays — nothing to install.</p></div></section>
<section class="section" style="padding-top:34px"><div class="wrap">
  <?php if ($live): ?><h2 style="font-size:1.3rem"><?= badge('Live now', 'live') ?></h2><div class="grid g2"><?php foreach ($live as $w) { echo partial('webinars/_card', ['w' => $w]); } ?></div><?php endif; ?>
  <h2 class="mt3" style="font-size:1.3rem">Upcoming sessions</h2>
  <?php if (!$upcoming): ?><?= empty_state('video', 'No sessions are scheduled right now', 'Create an account and we will let you know when the next session is announced.', user() ? '' : '<a class="btn btn-primary btn-sm" href="/account/register">Create an account</a>') ?>
  <?php else: ?><div class="grid g2"><?php foreach ($upcoming as $w) { echo partial('webinars/_card', ['w' => $w]); } ?></div><?php endif; ?>
  <?php if ($past): ?><h2 class="mt4" style="font-size:1.3rem">Watch past sessions</h2><div class="grid g3"><?php foreach ($past as $w) { echo partial('webinars/_card', ['w' => $w]); } ?></div><?php endif; ?>
</div></section>
