<?php
// Include db.php FIRST to set up session handler
require './db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Only staff can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

$application_id = (int)($_GET['application_id'] ?? 0);

// Fetch application details, applicant, and documents
$app = null; $user = null; $docs = [];
if ($application_id > 0) {
    $stmt = $conn->prepare("SELECT a.*, u.name as applicant_name, u.email as applicant_email
                 FROM applications a 
                 JOIN users u ON a.user_id = u.id 
                 WHERE a.id = ?");
    $stmt->execute([$application_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    // ✅ Fixed query: match your actual columns
    $d = $conn->prepare("SELECT document_name AS file_name, file_path, upload_date AS uploaded_at 
               FROM documents 
               WHERE application_id=? 
               ORDER BY id DESC");
    $d->execute([$application_id]);
    $docs = $d->fetchAll(PDO::FETCH_ASSOC);
}

if (!$app) {
    echo '<div style="padding:20px; font-family:Inter, sans-serif;">Application not found. <a href="applicants.php">Back</a></div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Application Report</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Inter',sans-serif; margin:0; padding:24px; background:#f4f7fa; color:#343a40; }
    .card { max-width:980px; margin:0 auto; background:#fff; border:1px solid #e9ecef; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.06); padding:24px; }
    h1 { margin:0 0 12px; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .section { border:1px solid #eef1f5; border-radius:10px; padding:14px; background:#fafbfe; }
    .section h3 { margin:0 0 8px; font-size:16px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:8px 10px; border-bottom:1px solid #eef1f5; text-align:left; font-size:14px; }
    th { background:#f7f9fc; font-weight:700; color:#2f3a4a; }
    .docs a { color:#3e5aa2; text-decoration:none; }
    .btn { background:#4a69bd; color:#fff; border:none; padding:10px 14px; border-radius:8px; font-weight:700; cursor:pointer; text-decoration:none; }
    .btn:hover { background:#3e5aa2; }
  </style>
</head>
<body>
  <div class="card">
    <a class="btn" href="applicants.php" style="background:#6c757d; margin-bottom:12px; display:inline-block;">&larr; Back</a>
    <h1>Application Report</h1>
    <div class="grid">
      <div class="section">
        <h3>Applicant</h3>
        <table>
          <tr><th>Name</th><td><?= htmlspecialchars($app['applicant_name']) ?></td></tr>
          <tr><th>Email</th><td><?= htmlspecialchars($app['applicant_email']) ?></td></tr>
        </table>
      </div>
      <div class="section">
        <h3>Application</h3>
        <table>
          <tr><th>ID</th><td>#<?= htmlspecialchars($app['id']) ?></td></tr>
          <tr><th>Business Name</th><td><?= htmlspecialchars($app['business_name']) ?></td></tr>
          <tr><th>Status</th><td><?= htmlspecialchars(ucfirst($app['status'])) ?></td></tr>
          <tr><th>Submitted</th><td><?= htmlspecialchars($app['submitted_at']) ?></td></tr>
          <?php if ($app['renewal_date'] && in_array($app['status'], ['approved', 'complete'])): ?>
            <?php
            $renewal_date = new DateTime($app['renewal_date']);
            $today = new DateTime();
            $days_until_renewal = $today->diff($renewal_date)->days;
            $is_expired = $renewal_date < $today;
            $is_expiring_soon = $days_until_renewal <= 30 && !$is_expired;
            ?>
            <tr><th>Renewal Date</th><td><?= htmlspecialchars($app['renewal_date']) ?></td></tr>
            <tr><th>Renewal Status</th>
                <td>
                    <?php if ($is_expired): ?>
                        <span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 12px; font-size: 12px;">Expired</span>
                    <?php elseif ($is_expiring_soon): ?>
                        <span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 12px; font-size: 12px;">Expires in <?= $days_until_renewal ?> days</span>
                    <?php else: ?>
                        <span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 12px; font-size: 12px;">Active</span>
                    <?php endif; ?>
                </td>
            </tr>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <div class="section" style="margin-top:16px;">
      <h3>Documents</h3>
      <?php if (!empty($docs)): ?>
        <table class="docs">
          <thead><tr><th>File</th><th>Uploaded</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($docs as $doc): ?>
              <tr>
                <td><?= htmlspecialchars($doc['file_name']) ?></td>
                <td><?= htmlspecialchars($doc['uploaded_at'] ?? '-') ?></td>
                <td><a href="../uploads/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" rel="noopener" class="btn" style="padding:6px 10px;">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No documents uploaded.</p>
      <?php endif; ?>
    </div>

    <div class="section" style="margin-top:16px;">
      <h3>Form Details</h3>
      <?php
      $formDetails = json_decode($app['form_details'] ?? '{}', true);
      if (!empty($formDetails)) {
          echo '<table>';
          echo '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
          foreach ($formDetails as $key => $value) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars(ucwords(str_replace("_", " ", $key))) . '</td>';
              echo '<td>' . htmlspecialchars($value) . '</td>';
              echo '</tr>';
          }
          echo '</tbody></table>';
      } else {
          echo '<p>No form details found.</p>';
      }
      ?>
    </div>
  </div>
</body>
</html>
