<div class="wrap no-print" style="padding-top:22px;display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap"><a class="btn btn-ghost btn-sm" href="/account"><?= icon('back', 15) ?> My account</a><button class="btn btn-primary btn-sm" onclick="window.print()"><?= icon('download', 15) ?> Print or save as PDF</button></div>
<div class="wrap"><div class="cert">
  <img src="/assets/img/logo.png" alt="Donums Consultants">
  <div class="eyebrow mt2">Certificate of completion</div>
  <small>This certifies that</small>
  <div class="nm"><?= e($cert['holder_name'] ?: $cert['name']) ?></div>
  <small>has successfully completed</small>
  <h1><?= e($cert['title']) ?></h1>
  <small>Donums Academy<?= (int) $cert['hours'] ? ' · ' . (int) $cert['hours'] . ' hour' . ((int) $cert['hours'] === 1 ? '' : 's') . ' of learning' : '' ?> · Issued <?= e(fmt_date($cert['issued_at'])) ?></small>
  <div style="margin-top:3%;font-size:.85rem;color:var(--muted)">Certificate ID <strong style="color:var(--ink);letter-spacing:.06em"><?= e($cert['code']) ?></strong><br>Verify at <?= e(cfg('domain')) ?>/learning/verify</div>
</div></div>
