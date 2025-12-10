<?php
// Temporary debug endpoint to inspect session state and DB session row
// Usage: /Admin-dashboard/session_debug.php?token=YOUR_TOKEN

// Secret token: read from environment variable DEBUG_SESSION_TOKEN, fallback to 'local-debug-token'
$expected = getenv('DEBUG_SESSION_TOKEN') ?: 'local-debug-token';
$token = $_GET['token'] ?? '';
if ($token !== $expected) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

require_once __DIR__ . '/db.php';
// start session after db.php sets the session handler
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: text/plain');
echo "SERVER HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'n/a') . "\n";
echo "SESSION NAME: " . session_name() . "\n";
echo "SESSION ID: " . session_id() . "\n\n";

echo "\$_SESSION contents:\n";
var_export($_SESSION);
echo "\n\n";

// If DB connection available, check the user_sessions table for this session id
if (isset($conn) && $conn instanceof PDO) {
    try {
        $stmt = $conn->prepare('SELECT session_id, session_expires, length(session_data) as data_len FROM user_sessions WHERE session_id = :sid');
        $stmt->execute(['sid' => session_id()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "DB row found:\n";
            var_export($row);
            echo "\n";
        } else {
            echo "No DB row found for this session id.\n";
            // Show recent session rows for debugging (limit 5)
            $recent = $conn->query('SELECT session_id, session_expires FROM user_sessions ORDER BY session_expires DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
            echo "Recent sessions:\n";
            var_export($recent);
            echo "\n";
        }
    } catch (Exception $e) {
        echo "Error querying user_sessions: " . $e->getMessage() . "\n";
    }
} else {
    echo "No DB connection available (conn not set).\n";
}

echo "\nNote: This is a temporary debug page. Remove it after troubleshooting.\n";

?>
