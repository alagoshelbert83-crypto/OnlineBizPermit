<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../db.php';
// Load email renderer and sender
if (!file_exists(__DIR__ . '/../Staff-dashboard/email_templates/approval_email.php') || !file_exists(__DIR__ . '/../Staff-dashboard/email_functions.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Email components are missing on the server.']);
    exit;
}
require_once __DIR__ . '/../Staff-dashboard/email_templates/approval_email.php';
require_once __DIR__ . '/../Staff-dashboard/email_functions.php';

// Read POST params
$recipient = trim($_POST['recipient_email'] ?? '');
$applicationId = isset($_POST['application_id']) && is_numeric($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid recipient email is required.']);
    exit;
}

// Build app data for rendering
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
        // ignore errors and fall back to sample data
    }
}

try {
    $html = renderApprovalEmail($conn, $appData, $applicationId, $assessed_total, $fee_rows);
    $subject = "[Test] Approval Email - " . ($appData['business_name'] ?? 'Application');
    $sent = sendApplicationEmail($recipient, $appData['applicant_name'] ?? 'Admin', $subject, $html);
    if ($sent) {
        // Audit log
        try {
            if (file_exists(__DIR__ . '/../audit_logger.php')) {
                require_once __DIR__ . '/../audit_logger.php';
                $logger = AuditLogger::getInstance();
                $logger->log('email_test_sent', "Test approval email sent to {$recipient}", ['application_id' => $applicationId, 'to'=>$recipient], $_SESSION['user_id'] ?? null, 'admin');
            }
        } catch (Exception $ax) {
            error_log('Audit log failed for test email: ' . $ax->getMessage());
        }

        echo json_encode(['ok' => true, 'message' => 'Test email sent successfully to ' . $recipient]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send email. Check SMTP configuration.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
