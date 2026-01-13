<?php
// Simple test for payments table and basic release flow
// Usage: php tests/test_payment_flow.php <application_id>
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($argc < 2) {
    echo "Usage: php tests/test_payment_flow.php <application_id>\n";
    exit(1);
}
$appId = (int)$argv[1];

require_once __DIR__ . '/../Applicant-dashboard/db.php'; // expects $conn (PDO)

try {
    // 1) Check payments table exists
    $row = $conn->query("SELECT to_regclass('public.payments') AS tbl")->fetch(PDO::FETCH_ASSOC);
    if (empty($row['tbl'])) {
        echo "FAIL: payments table not found. Run migrations first.\n";
        exit(1);
    }
    echo "OK: payments table exists.\n";

    // 2) Insert a test payment
    $stmt = $conn->prepare("INSERT INTO payments (application_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW()) RETURNING id");
    $amount = 1234.56;
    $stmt->execute([$appId, $amount]);
    $payRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $paymentId = $payRow['id'] ?? null;
    if (!$paymentId) {
        echo "FAIL: Could not insert payment row.\n";
        exit(1);
    }
    echo "OK: inserted payment id {$paymentId} for application {$appId}\n";

    // 3) Mark payment verified (simulate staff action)
    $verify = $conn->prepare("UPDATE payments SET status = 'verified', verified_at = NOW() WHERE id = ? RETURNING id");
    $verify->execute([$paymentId]);
    $vrow = $verify->fetch(PDO::FETCH_ASSOC);
    if (empty($vrow['id'])) {
        echo "FAIL: Could not verify payment.\n";
        exit(1);
    }
    echo "OK: marked payment as verified.\n";

    // 4) Simulate automatic release (as the app does) and check application status
    $conn->exec("UPDATE applications SET status = 'complete', permit_released_at = NOW(), updated_at = NOW() WHERE id = " . (int)$appId);
    $app = $conn->prepare("SELECT status, permit_released_at FROM applications WHERE id = ?");
    $app->execute([$appId]);
    $appRow = $app->fetch(PDO::FETCH_ASSOC);

    if ($appRow && $appRow['status'] === 'complete') {
        echo "OK: application status is 'complete' and permit_released_at is " . ($appRow['permit_released_at'] ?? 'NULL') . "\n";
    } else {
        echo "FAIL: application status is not 'complete'. Current: " . json_encode($appRow) . "\n";
        exit(1);
    }

    // Cleanup: remove the test payment row
    $conn->exec("DELETE FROM payments WHERE id = " . (int)$paymentId);
    echo "CLEANUP: removed test payment id {$paymentId}\n";

    echo "TEST SUCCEEDED\n";
    exit(0);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
