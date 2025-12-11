<?php
// Include db.php FIRST to set up session handler
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../audit_logger.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log logout before destroying session (so we have user info)
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $logger = AuditLogger::getInstance();
    $logger->logLogout($_SESSION['user_id'], $_SESSION['role']);
}

// Get session ID before destroying (needed for cookie clearing)
$session_id = session_id();

// Destroy all session data
$_SESSION = [];
session_destroy();

// Clear the session cookie
if (isset($_COOKIE[session_name()])) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

// Redirect to login page
header("Location: login.php?status=logout");
exit;
?>
