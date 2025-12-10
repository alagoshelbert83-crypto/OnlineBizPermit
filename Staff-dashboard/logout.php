<?php
// Include db.php FIRST to set up session handler
require './db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
$_SESSION = [];
session_destroy();

// Redirect to login page
header("Location: login.php?status=logout");
exit;
?>
