<?php
// db.php must be included first to set up session handler
require_once __DIR__ . '/db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check (allows both admin and staff)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: ./admin_login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// Fetch Current User Info
try {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_user_name = $user_info['name'] ?? 'User';
} catch(PDOException $e) {
    error_log("Error fetching user info: " . $e->getMessage());
    $current_user_name = 'User';
}

// --- Fetch unread notification count for admin ---
$unread_notifications_count = 0;
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->execute([$current_user_id]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications_count = $count_result['unread_count'] ?? 0;
} catch(PDOException $e) {
    error_log("Error fetching notification count: " . $e->getMessage());
    $unread_notifications_count = 0;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'Admin Panel') ?> - OnlineBizPermit</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="admin_style.css">
  <style>
    /* Global Responsive Styles for Admin Dashboard */
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
        
        /* Filter controls */
        .filter-controls {
            flex-direction: column;
            width: 100%;
        }
        .search-form {
            width: 100%;
        }
        
        /* Pagination */
        .pagination {
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        .pagination a {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
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
