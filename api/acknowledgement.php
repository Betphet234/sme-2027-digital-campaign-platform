<?php
require_once __DIR__ . '/../backend/bootstrap.php';

$reference = strtoupper(clean_string($_GET['reference'] ?? '', 50));
$phone = normalize_phone($_GET['phone'] ?? '');
$download = clean_string($_GET['download'] ?? '', 10) === '1';

if ($reference === '' || $phone === '') {
    http_response_code(422);
    echo 'Reference number and phone number are required.';
    exit;
}

$stmt = db()->prepare('SELECT reference, dataset, type, category, status, name, phone, email, community, ward, created_at FROM submissions WHERE reference = ? AND phone_normalized = ? LIMIT 1');
$stmt->execute([$reference, $phone]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo 'No matching record found.';
    exit;
}

if ($download) {
    header('Content-Disposition: attachment; filename="acknowledgement_' . preg_replace('/[^A-Z0-9-]/', '', $reference) . '.html"');
}
header('Content-Type: text/html; charset=utf-8');

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acknowledgement Slip - <?= h($row['reference']) ?></title>
  <style>
    body{font-family:Arial,sans-serif;background:#f6f7fb;color:#111;margin:0;padding:30px}.slip{max-width:760px;margin:auto;background:#fff;border:1px solid #ddd;border-radius:14px;padding:28px}.top{text-align:center;border-bottom:2px solid #1d4ed8;padding-bottom:18px;margin-bottom:20px}.ref{display:inline-block;background:#1d4ed8;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.item{border:1px solid #eee;border-radius:10px;padding:12px}.item span{display:block;color:#555;font-size:13px;margin-bottom:4px}.item strong{font-size:16px}.notice{background:#f0f7ff;border-left:4px solid #1d4ed8;padding:14px;margin-top:18px}.actions{margin-top:24px;text-align:center}.btn{display:inline-block;border:0;background:#1d4ed8;color:#fff;padding:12px 16px;border-radius:8px;text-decoration:none;cursor:pointer}.btn.secondary{background:#555}@media(max-width:640px){.grid{grid-template-columns:1fr}}@media print{body{background:#fff;padding:0}.slip{border:0;border-radius:0}.actions{display:none}}
  </style>
</head>
<body>
  <main class="slip">
    <div class="top">
      <h1><?= h(campaign_name()) ?></h1>
      <p>Application Acknowledgement Slip</p>
      <p class="ref">Reference: <?= h($row['reference']) ?></p>
    </div>

    <div class="grid">
      <div class="item"><span>Applicant Name</span><strong><?= h($row['name'] ?: 'Applicant') ?></strong></div>
      <div class="item"><span>Phone Number</span><strong><?= h($row['phone']) ?></strong></div>
      <div class="item"><span>Application Category</span><strong><?= h($row['category'] ?: $row['type']) ?></strong></div>
      <div class="item"><span>Status</span><strong><?= h($row['status']) ?></strong></div>
      <div class="item"><span>Community</span><strong><?= h($row['community']) ?></strong></div>
      <div class="item"><span>Ward</span><strong><?= h($row['ward']) ?></strong></div>
      <div class="item"><span>Date Submitted</span><strong><?= h(date('F j, Y g:i A', strtotime($row['created_at']))) ?></strong></div>
      <div class="item"><span>Campaign Contact</span><strong><?= h(campaign_phone()) ?></strong></div>
    </div>

    <div class="notice">
      <strong>Confirmation:</strong> Your application has been received successfully. Keep this reference number safe. You can use it with your phone number to check your application status.
    </div>

    <div class="actions">
      <button class="btn" onclick="window.print()">Print / Save as PDF</button>
      <a class="btn secondary" href="<?= h(app_link('application-status.html')) ?>">Check Status</a>
    </div>
  </main>
</body>
</html>
