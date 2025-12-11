<?php
// Include db.php FIRST to set up session handler
require './db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

$application = null;
$applicationId = $_GET['id'] ?? 0;

if ($applicationId > 0) {
    $stmt = $conn->prepare("SELECT a.*, u.name as applicant_name FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ? AND a.status IN ('approved', 'complete')");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$application) {
    echo 'Permit not found, not authorized, or the application is not yet approved. <a href="dashboard.php">Back to Dashboard</a>';
    exit;
}

$form_details = json_decode($application['form_details'], true) ?? [];

// Data for the permit
$approval_date = $application['updated_at'] ?? $application['submitted_at'];
$renewal_date = $application['renewal_date'] ?? date('Y-m-d', strtotime('+1 year', strtotime($approval_date)));

// Generate permit number (format: YYYY-XXX)
$permit_number = date('Y') . '-' . str_pad($application['id'], 3, '0', STR_PAD_LEFT);

// Generate OR number (format: 8 digits)
$or_number = str_pad($application['id'] + 1800000, 8, '0', STR_PAD_LEFT);

// Calculate permit fee (example calculation)
$permit_fee = 10000 + ($application['id'] * 100); // Base fee + application ID * 100
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mayor's Permit - <?= htmlspecialchars($application['business_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body { 
      font-family: 'Inter', sans-serif; 
      margin: 0; 
      background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); 
      color: #000; 
      min-height: 100vh;
    }
    .toolbar { 
      background: #343a40; 
      padding: 10px 20px; 
      text-align: right; 
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
    }
    .btn { 
      background: #4a69bd; 
      color: #fff; 
      border: none; 
      padding: 10px 14px; 
      border-radius: 8px; 
      font-weight: 700; 
      cursor: pointer; 
      text-decoration: none; 
      margin-left: 10px;
    }
    .btn:hover { background: #3e5aa2; }
    .permit-container { 
      max-width: 850px; 
      margin: 80px auto 20px; 
      background: #fff; 
      border: 3px solid #1e3a8a; 
      padding: 40px; 
      box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
      position: relative; 
    }
    .permit-header { 
      text-align: center; 
      margin-bottom: 30px; 
      position: relative;
    }
    .republic-text { font-size: 14px; font-weight: 600; margin-bottom: 5px; }
    .municipality-text { font-size: 18px; font-weight: 700; color: #1e3a8a; margin-bottom: 5px; }
    .catanduanes-text { font-size: 16px; font-weight: 600; color: #1e3a8a; margin-bottom: 5px; }
    .office-text { font-size: 14px; font-weight: 600; margin-bottom: 20px; }
    .seal-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .municipal-seal { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; position: relative; }
    .municipal-logo { width: 80px; height: 80px; object-fit: contain; }
    .bagong-pilipinas { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
    .pilipinas-logo { width: 100px; height: 100px; object-fit: contain; }
    .permit-title { font-family: 'Merriweather', serif; font-size: 32px; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 3px; margin: 20px 0; }
    .permit-number { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; font-size: 16px; font-weight: 600; }
    .application-type { display: flex; gap: 20px; align-items: center; }
    .checkbox { display: flex; align-items: center; gap: 5px; }
    .checkbox input[type="checkbox"] { width: 15px; height: 15px; }
    .permit-body { font-size: 16px; line-height: 1.6; text-align: justify; }
    .permit-body p { margin-bottom: 15px; }
    .grantee-section { margin: 20px 0; text-align: center; }
    .grantee-name { font-weight: 700; font-size: 18px; text-decoration: underline; margin: 10px 0; }
    .business-details { margin: 20px auto; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; max-width: 600px; text-align: center; }
    .effectivity-dates { display: flex; justify-content: center; gap: 50px; margin: 20px 0; font-weight: 600; text-align: center; }
    .legal-text { margin: 20px 0; font-size: 14px; line-height: 1.5; }
    .signature-section { margin-top: 40px; text-align: center; }
    .signature-line { border-top: 2px solid #000; width: 300px; margin: 20px auto; padding-top: 10px; }
    .payment-details { margin-top: 30px; padding: 15px; background: #f0f8ff; border: 1px solid #1e3a8a; }
    .payment-details h4 { margin: 0 0 10px 0; color: #1e3a8a; font-size: 16px; }
    .note-section { margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; font-size: 14px; }
    .hotline { text-align: center; margin-top: 20px; font-weight: 600; color: #1e3a8a; }
    .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 120px; color: rgba(30, 58, 138, 0.1); font-weight: bold; z-index: 0; pointer-events: none; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .permit-container { margin: 0; box-shadow: none; border: 3px solid #1e3a8a; }
        .watermark { color: rgba(30, 58, 138, 0.05); }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a href="view_application.php?id=<?= $applicationId ?>" class="btn" style="background:#6c757d;">&larr; Back to Application</a>
    <button onclick="window.print()" class="btn">Print or Save as PDF</button>
  </div>

  <div class="permit-container">
    <div class="watermark">OFFICIAL</div>
    
    <div class="permit-header">
      <div class="republic-text">Republic of the Philippines</div>
      <div class="municipality-text">MUNICIPALITY OF SAN MIGUEL</div>
      <div class="catanduanes-text">Catanduanes</div>
      <div class="office-text">OFFICE OF THE MUNICIPAL MAYOR</div>
      
      <div class="seal-container">
        <div class="municipal-seal">
          <img src="../Applicant-dashboard/San Miguel.png" alt="Municipality of San Miguel, Catanduanes Logo" class="municipal-logo" onerror="this.style.display='none';">
        </div>
        <div class="permit-title">MAYOR'S PERMIT</div>
        <div class="bagong-pilipinas">
          <img src="../Applicant-dashboard/pilipinas.jpeg" alt="Bagong Pilipinas Logo" class="pilipinas-logo" onerror="this.style.display='none';">
        </div>
      </div>

    <div class="permit-number">
      <div><strong>PERMIT NO.:</strong> <?= htmlspecialchars($permit_number) ?></div>
      <div class="application-type">
        <div class="checkbox">
          <input type="checkbox" <?= ($form_details['application_type'] ?? '') === 'New' ? 'checked' : '' ?> disabled>
          <label>NEW APPLICANT</label>
        </div>
        <div class="checkbox">
          <input type="checkbox" <?= ($form_details['application_type'] ?? '') === 'Renewal' ? 'checked' : '' ?> disabled>
          <label>RENEWAL</label>
        </div>
      </div>
    </div>

    <div class="permit-body">
      <div class="grantee-section">
        <p><strong>PERMIT IS HEREBY GRANTED TO</strong></p>
        <div class="grantee-name"><?= htmlspecialchars(($form_details['first_name'] ?? '') . ' ' . ($form_details['last_name'] ?? '')) ?></div>
        <p><?= htmlspecialchars($form_details['owner_address'] ?? $application['business_address']) ?></p>
        <p>With Community Tax Certificate No. <strong><?= htmlspecialchars($form_details['tin_no'] ?? '12325065') ?></strong> issued at San Miguel, Catanduanes on <strong><?= htmlspecialchars(date('F d, Y', strtotime($approval_date))) ?></strong> to establish and operate the following business:</p>
        
        <div class="business-details">
          <p><strong>Business Name:</strong> <?= htmlspecialchars($application['business_name']) ?></p>
          <p><strong>Business Address:</strong> <?= htmlspecialchars($application['business_address']) ?></p>
          <p><strong>Type of Business:</strong> <?= htmlspecialchars($form_details['type_of_business'] ?? $application['type_of_business']) ?></p>
        </div>
      </div>

      <div class="effectivity-dates">
        <div><strong>Effectivity:</strong> <?= htmlspecialchars(date('F d, Y', strtotime($approval_date))) ?></div>
        <div><strong>Expiration:</strong> <?= htmlspecialchars(date('F d, Y', strtotime($renewal_date))) ?></div>
      </div>

      <div class="legal-text">
        <p>The proprietor/owner having complied with all the requirements and paid all the necessary Permit and license fees at the Office of the Municipal Treasurer of San Miguel, Catanduanes.</p>
        
        <p>This permit shall be subject for revocation in case of any violations of Laws, Ordinances, Rules and Regulations or the holder thereof voluntarily stops engaging in the business.</p>
        
        <p>Issued this <?= htmlspecialchars(date('jS', strtotime($approval_date))) ?> of <?= htmlspecialchars(date('F Y', strtotime($approval_date))) ?> at Municipality of San Miguel, Catanduanes.</p>
      </div>

      <div class="signature-section">
        <div class="signature-line">
          <strong>ANTONIO T. TEVES</strong><br>
          Municipal Mayor
        </div>
      </div>

      <div class="payment-details">
        <h4>Payment Details</h4>
        <p><strong>Paid under O.R. No.</strong> <?= htmlspecialchars($or_number) ?></p>
        <p><strong>Issued at</strong> San Miguel, Catanduanes</p>
        <p><strong>Issued On:</strong> <?= htmlspecialchars(date('F d, Y', strtotime($approval_date))) ?></p>
        <p><strong>Amount Paid:</strong> Php <?= number_format($permit_fee, 2) ?></p>
      </div>

      <div class="note-section">
        <p><strong>NOTE:</strong> This permit must be displayed in conspicuous place within the establishment and must likewise be renewed after every end of the year. This permit is valid if Official Receipt Number is indicated hereon and with Official Seal of the Office.</p>
      </div>

      <div class="hotline">
        <p><strong>San Miguel MPS Hotline No. 09994440885</strong></p>
      </div>
    </div>
  </div>
</body>
</html>