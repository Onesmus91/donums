<?php $u = user(); $action = $j ? '/jobs/' . (int) $j['id'] . '/apply' : '/jobs/talent-network'; $questions = $questions ?? []; ?>
<form class="form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" id="apply"><?= csrf_field() . spam_fields() ?>
  <div class="row2"><div class="field"><label for="a-name">Full name *</label><input id="a-name" name="name" required maxlength="120" value="<?= e($u['name'] ?? '') ?>" autocomplete="name"></div>
  <div class="field"><label for="a-email">Email *</label><input id="a-email" type="email" name="email" required value="<?= e($u['email'] ?? '') ?>" autocomplete="email"></div></div>
  <div class="row2"><div class="field"><label for="a-phone">Phone *</label><input id="a-phone" type="tel" name="phone" required value="<?= e($u['phone'] ?? '') ?>" autocomplete="tel"></div>
  <div class="field"><label for="a-loc">Current location</label><input id="a-loc" name="location" value="<?= e($u['location'] ?? '') ?>"></div></div>
  <div class="row3"><div class="field"><label for="a-exp">Years of experience</label><input id="a-exp" type="number" name="experience_years" min="0" max="60" value="0"></div>
  <div class="field"><label for="a-sal">Expected salary</label><input id="a-sal" name="expected_salary" placeholder="e.g. KSh 80,000"></div>
  <div class="field"><label for="a-np">Notice period</label><input id="a-np" name="notice_period" placeholder="e.g. 1 month"></div></div>
  <div class="field"><label for="a-q">Highest qualification</label><input id="a-q" name="qualification" placeholder="e.g. BCom Human Resource Management"></div>
  <div class="field"><label for="a-skills">Key skills</label><input id="a-skills" name="skills" placeholder="Comma-separated: recruitment, payroll, Excel…"><?php if ($j && $j['required_skills']): ?><span class="hint">This role values: <?= e($j['required_skills']) ?></span><?php endif; ?></div>
  <div class="field"><label for="a-li">LinkedIn or portfolio link</label><input id="a-li" type="url" name="linkedin" placeholder="https://"></div>
  <div class="field"><label for="a-cv">CV * <span class="muted">(PDF, DOC or DOCX · max 5 MB)</span></label><input id="a-cv" type="file" name="cv" accept=".pdf,.doc,.docx" required></div>
  <div class="field"><label for="a-cl">Cover letter</label><textarea id="a-cl" name="cover_letter" rows="5" placeholder="Tell us briefly why you are a strong fit."></textarea></div>
  <?php foreach ($questions as $i => $qq): ?><div class="field"><label for="a-s<?= $i ?>"><?= e($qq) ?></label><textarea id="a-s<?= $i ?>" name="screen[<?= $i ?>]" rows="3"></textarea></div><?php endforeach; ?>
  <label class="check"><input type="checkbox" required><span>I confirm the information is accurate and agree to the processing of my application as described in the <a href="/privacy" target="_blank">Privacy Policy</a>.</span></label>
  <button class="btn btn-primary" type="submit"><?= $j ? 'Submit application' : 'Join the talent network' ?> <?= icon('send', 16) ?></button>
</form>
