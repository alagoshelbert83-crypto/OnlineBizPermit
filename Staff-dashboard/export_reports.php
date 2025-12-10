<?php
session_start();
require './db.php';

// ✅ Only staff can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// --- Fetch all applications for the report ---
$sql = "SELECT 
            a.id as application_id, 
            a.business_name, 
            u.name as applicant_name, 
            a.status, 
            a.submitted_at, 
            a.renewal_date
        FROM 
            applications a
        JOIN 
            users u ON a.user_id = u.id
        ORDER BY 
            a.submitted_at DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Report query error: ' . $e->getMessage());
    $result = [];
}

// --- Generate CSV ---
$filename = "business_applications_report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add headers to the CSV
fputcsv($output, [
    'Application ID',
    'Business Name',
    'Applicant Name',
    'Status',
    'Date Submitted',
    'Renewal Date'
]);

// Add data rows
if (!empty($result)) {
    foreach ($result as $row) {
        fputcsv($output, [
            $row['application_id'],
            $row['business_name'],
            $row['applicant_name'],
            ucfirst($row['status']), // Capitalize status for better readability
            date('M d, Y', strtotime($row['submitted_at'])),
            $row['renewal_date'] ? date('M d, Y', strtotime($row['renewal_date'])) : 'N/A'
        ]);
    }
}

fclose($output);
exit;
?>