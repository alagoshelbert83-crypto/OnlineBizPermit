<?php
// Set a default timezone to prevent potential date/time warnings
date_default_timezone_set('Asia/Manila');

// Include the database connection FIRST - it sets up the session handler
// The path is relative to this header file.
require_once __DIR__ . '/db.php';

// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check: Ensure only logged-in staff or admins can access staff pages.
// The staff login form allows both roles, so we should check for both here.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    // Redirect to the staff login page if not authorized.
    header("Location: login.php");
    exit;
}

// --- Fetch current user's name for display ---
$userName = 'Staff'; // Default name
if (isset($_SESSION['user_id'])) {
  try {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userName = $user['name'] ?? 'Staff';
  } catch (PDOException $e) {
    error_log("Error fetching staff user name: " . $e->getMessage());
    $userName = 'Staff';
  }
}
// You can also include other common files here if needed, for example:
// require_once __DIR__ . '/email_functions.php';

// --- Fetch unread notification count for sidebar ---
$unread_notifications_count = 0;
if (isset($_SESSION['user_id'])) {
  try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id IS NULL AND is_read = 0");
    // Staff notifications are where user_id is NULL
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications_count = (int)($count_result['unread_count'] ?? 0);
  } catch (PDOException $e) {
    error_log("Error fetching unread notifications count: " . $e->getMessage());
    $unread_notifications_count = 0;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Staff Dashboard' ?> - OnlineBizPermit</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="staff_style.css">
  <style>
    /* Global Responsive Styles for Staff Dashboard */
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
        
        /* Form responsive */
        .form-container {
            margin: 10px;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        
        /* Card/Grid responsive */
        .stat-grid, .kpi-grid, .dashboard-grid, .data-grid {
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
        
        /* Modal responsive */
        .modal-content {
            width: 95% !important;
            margin: 5% auto;
            padding: 1.5rem;
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
    }
  </style>
</head>
<body>
  <div class="wrapper">
