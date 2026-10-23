<section class="page-hero"><div class="wrap"><span class="eyebrow">Account</span><h1>Profile &amp; security</h1></div></section>
<section class="section" style="padding-top:34px"><div class="wrap layout-2" style="grid-template-columns:minmax(0,1fr) 380px">
  <form class="card form" method="post" action="/account/profile" enctype="multipart/form-data"><?= csrf_field() ?>
    <h3>Your profile</h3>
    <div style="display:flex;gap:16px;align-items:center"><?= avatar_html($u, 64) ?><div class="field" style="flex:1"><label for="avatar">Profile photo</label><input id="avatar" type="file" name="avatar" accept="image/*"></div></div>
    <div class="row2"><div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($u['name']) ?>" required></div><div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($u['phone']) ?>"></div></div>
    <div class="row2"><div class="field"><label for="headline">Headline</label><input id="headline" name="headline" value="<?= e($u['headline']) ?>" placeholder="e.g. HR officer · Nairobi"></div><div class="field"><label for="location">Location</label><input id="location" name="location" value="<?= e($u['location']) ?>"></div></div>
    <div class="field"><label for="bio">About you</label><textarea id="bio" name="bio" rows="3"><?= e($u['bio']) ?></textarea></div>
    <label class="check"><input type="checkbox" name="notify_email" value="1" <?= $u['notify_email'] ? 'checked' : '' ?>> <span>Email me about replies to discussions I follow and updates to my courses and applications.</span></label>
    <div><button class="btn btn-primary">Save profile</button></div>
  </form>
  <aside>
    <form class="card form" method="post" action="/account/profile"><?= csrf_field() ?><input type="hidden" name="action" value="password"><h3>Change password</h3>
      <div class="field"><label for="current">Current password</label><input id="current" type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label for="np">New password</label><input id="np" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
      <div><button class="btn btn-dark">Update password</button></div></form>
    <div class="card mt2"><h3>Email</h3><p class="small muted"><?= e($u['email']) ?> · <?= $u['email_verified_at'] ? badge('Confirmed', 'green') : badge('Not confirmed', 'amber') ?></p></div>
  </aside>
</div></section>
