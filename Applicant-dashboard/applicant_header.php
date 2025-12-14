<?php
// db.php must be included first to set up session handler
require_once __DIR__ . '/db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check: Only allow users with the 'user' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    // Redirect to the main login page if not an applicant
    // Store the current URL to redirect back after login
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $_SESSION['redirect_after_login'] = $current_url;
    
    // Use absolute path to prevent redirect loops
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $login_url = $protocol . '://' . $host . '/Applicant-dashboard/login.php';
    
    header("Location: " . $login_url);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch Current User Info (be defensive: some deployments may lack optional columns)
// CRITICAL: Ensure we're not in a transaction and rollback if any query fails
try {
    // If we're in a transaction, rollback first (shouldn't happen, but be safe)
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log('WARNING: Rolled back transaction before user info query in applicant_header.php');
    }
    
    $stmt = $conn->prepare("SELECT name, email, profile_picture_path FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_user_name = $user_info['name'] ?? 'Applicant';
    $current_user_picture = $user_info['profile_picture_path'] ?? null;
} catch(PDOException $e) {
    // CRITICAL: Rollback if we're in a failed transaction
    if ($conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_e) {
            error_log('Failed to rollback after user info query error: ' . $rollback_e->getMessage());
        }
    }
    
    error_log("Error fetching user info (profile_picture_path may be missing): " . $e->getMessage());
    // Fallback: try without profile_picture_path
    try {
        // Ensure we're not in a transaction for fallback query
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $current_user_name = $user_info['name'] ?? 'Applicant';
        $current_user_picture = null;
    } catch (PDOException $e2) {
        // CRITICAL: Rollback if fallback query also fails
        if ($conn->inTransaction()) {
            try {
                $conn->rollBack();
            } catch (Exception $rollback_e2) {
                error_log('Failed to rollback after fallback query error: ' . $rollback_e2->getMessage());
            }
        }
        error_log("Error fetching user info fallback: " . $e2->getMessage());
        $current_user_name = 'Applicant';
        $current_user_picture = null;
    }
}

// --- Fetch unread notification count for the applicant ---
$unread_notifications_count = 0;
try {
    // CRITICAL: Ensure we're not in a transaction
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log('WARNING: Rolled back transaction before notification count query in applicant_header.php');
    }
    
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->execute([$current_user_id]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications_count = $count_result['unread_count'] ?? 0;
} catch(PDOException $e) {
    // CRITICAL: Rollback if we're in a failed transaction
    if ($conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_e) {
            error_log('Failed to rollback after notification count query error: ' . $rollback_e->getMessage());
        }
    }
    error_log("Error fetching notification count: " . $e->getMessage());
    $unread_notifications_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> - OnlineBizPermit</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="applicant_style.css"> <!-- Main applicant styles -->
  <style>
    /* Global Responsive Styles for Applicant Dashboard */
    @media (max-width: 768px) {
        /* Header adjustments */
        .header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem;
            padding: 1rem 0;
        }
        .header-left, .header-right {
            width: 100%;
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        /* Table responsive */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            min-width: 600px;
        }
        table th, table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.85rem;
        }
        table td[data-label]::before {
            content: attr(data-label) ": ";
            font-weight: 600;
            display: inline-block;
            min-width: 120px;
        }
        
        /* Form responsive */
        .form-container {
            margin: 10px;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        
        /* Card/Grid responsive */
        .stats-container, .stat-grid, .kpi-grid, .dashboard-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem;
        }
        
        /* Chart responsive */
        .chart-container, .chart-wrapper {
            height: 300px !important;
        }
        
        /* Button groups */
        .action-buttons {
            flex-direction: column;
            gap: 0.5rem;
        }
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        /* Alert responsive */
        .renewal-alerts {
            flex-direction: column;
        }
        .alert {
            flex-direction: column;
            align-items: flex-start;
        }
        
        /* Chat section */
        .chat-section {
            margin: 1rem 0;
        }
        .chat-item {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        .chat-actions {
            width: 100%;
        }
        .chat-actions .btn,
        .chat-actions .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .main {
            padding: 10px !important;
        }
        .header h1 {
            font-size: 1.5rem !important;
        }
        table th, table td {
            padding: 0.5rem 0.25rem;
            font-size: 0.8rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        /* Stack table cells on very small screens */
        table, thead, tbody, th, td, tr {
            display: block;
        }
        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        tr {
            border: 1px solid #ccc;
            margin-bottom: 1rem;
            padding: 0.5rem;
        }
        td {
            border: none;
            position: relative;
            padding-left: 50% !important;
        }
        td::before {
            content: attr(data-label) ": ";
            position: absolute;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: 600;
        }
    }
  </style>
</head>
<body>
  <div class="wrapper">