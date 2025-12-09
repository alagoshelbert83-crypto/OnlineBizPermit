<?php
// db.php must be included first to set up session handler
require_once __DIR__ . '/db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header("Location: admin_login.php");
exit;
?>
