<?php
require_once __DIR__ . '/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get log ID from request
$log_id = $_GET['id'] ?? null;

if (!$log_id || !is_numeric($log_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid log ID']);
    exit;
}

try {
    // Fetch log details with user information
    $sql = "
        SELECT 
            al.*,
            u.name as user_name,
            u.email as user_email
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        http_response_code(404);
        echo json_encode(['error' => 'Log not found']);
        exit;
    }
    
    // Parse metadata if it's a JSON string
    if (!empty($log['metadata'])) {
        if (is_string($log['metadata'])) {
            $log['metadata'] = json_decode($log['metadata'], true);
        }
    } else {
        $log['metadata'] = [];
    }
    
    // Format the response
    $response = [
        'id' => $log['id'],
        'user' => [
            'id' => $log['user_id'],
            'name' => $log['user_name'] ?? 'System',
            'email' => $log['user_email'] ?? 'N/A',
            'role' => $log['user_role']
        ],
        'action' => $log['action'],
        'description' => $log['description'],
        'ip_address' => $log['ip_address'] ?? 'N/A',
        'user_agent' => $log['user_agent'] ?? 'N/A',
        'session_id' => $log['session_id'] ?? 'N/A',
        'metadata' => $log['metadata'],
        'created_at' => $log['created_at']
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Error fetching log details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
