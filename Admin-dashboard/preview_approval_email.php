<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/email_templates/approval_email.php';

$applicationId = isset($_GET['application_id']) && is_numeric($_GET['application_id']) ? (int)$_GET['application_id'] : 0;

$appData = ['applicant_name' => 'Sample Applicant', 'business_name' => 'Sample Business'];
$assessed_total = 0;
$fee_rows = '';

if ($applicationId > 0) {
    try {
        $stmt = $conn->prepare("SELECT a.business_name, u.name as applicant_name FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
        $stmt->execute([$applicationId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $appData['business_name'] = $r['business_name'] ?? $appData['business_name'];
            $appData['applicant_name'] = $r['applicant_name'] ?? $appData['applicant_name'];
        }

        $fs = $conn->prepare("SELECT form_data FROM staff_form_data WHERE application_id = ?");
        $fs->execute([$applicationId]);
        $fr = $fs->fetch(PDO::FETCH_ASSOC);
        if ($fr && !empty($fr['form_data'])) {
            $staff_form = json_decode($fr['form_data'], true) ?? [];
            if (!empty($staff_form['fees']) && is_array($staff_form['fees'])) {
                foreach ($staff_form['fees'] as $label => $data) {
                    $amt = $data['total'] ?? $data['amount'] ?? 0;
                    if (is_numeric($amt) && (float)$amt > 0) {
                        $assessed_total += (float)$amt;
                        $fee_rows .= "<tr><td style='padding:6px 8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($label) . "</td><td style='padding:6px 8px;border-bottom:1px solid #eee;text-align:right;'>₱ " . number_format((float)$amt, 2) . "</td></tr>";
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Ignore DB errors for preview; fall back to sample data
    }
}

try {
    $html = renderApprovalEmail($conn, $appData, $applicationId, $assessed_total, $fee_rows);
    echo json_encode(['html' => $html]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to render template: ' . $e->getMessage()]);
}
