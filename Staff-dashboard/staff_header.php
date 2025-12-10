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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root {
        --primary-color: #4a69bd; --secondary-color: #3c4b64; --bg-color: #f0f2f5; --card-bg: #ffffff; --text-primary: #343a40; --text-secondary: #6c757d; --border-color: #dee2e6; --shadow-sm: 0 1px 3px rgba(0,0,0,0.05); --border-radius: 8px;
    }
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
    body { background-color: var(--bg-color); color: var(--text-primary); }
    .wrapper { display: flex; min-height: 100vh; }
  </style>
</head>
<body>
  <div class="wrapper">