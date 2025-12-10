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
</head>
<body>
  <div class="wrapper">
