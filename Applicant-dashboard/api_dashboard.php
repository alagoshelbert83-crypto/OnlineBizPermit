<?php
/**
 * API Endpoint for Applicant Dashboard Real-time Data
 * Returns JSON data for applications, statistics, and active chats
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/applicant_header.php';

// Check if user is authenticated
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || $user_role !== 'user') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // --- Fetch applications for the logged-in user ---
    $stmt = $conn->prepare("SELECT id, business_name, status, submitted_at, business_address, type_of_business, 
                                   renewal_date, renewal_status, renewal_count
                             FROM applications 
                             WHERE user_id = ? 
                             ORDER BY submitted_at DESC");
    $stmt->execute([$current_user_id]);
    $my_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format applications for JSON
    $formatted_apps = [];
    foreach ($my_apps as $app) {
        $renewal_info = null;
        if ($app['renewal_date'] && in_array($app['status'], ['approved', 'complete'])) {
            $renewal_date = new DateTime($app['renewal_date']);
            $today = new DateTime();
            $days_until_renewal = $today->diff($renewal_date)->days;
            $is_expired = $renewal_date < $today;
            $is_expiring_soon = $days_until_renewal <= 30 && !$is_expired;
            
            $renewal_info = [
                'date' => $app['renewal_date'],
                'formatted_date' => date('M d, Y', strtotime($app['renewal_date'])),
                'is_expired' => $is_expired,
                'is_expiring_soon' => $is_expiring_soon,
                'days_until_renewal' => $days_until_renewal
            ];
        }

        $formatted_apps[] = [
            'id' => $app['id'],
            'business_name' => htmlspecialchars($app['business_name']),
            'status' => $app['status'],
            'submitted_at' => $app['submitted_at'],
            'business_address' => htmlspecialchars($app['business_address']),
            'type_of_business' => htmlspecialchars($app['type_of_business']),
            'renewal_date' => $app['renewal_date'],
            'renewal_status' => $app['renewal_status'],
            'renewal_count' => $app['renewal_count'],
            'renewal_info' => $renewal_info,
            'formatted_submitted_date' => date('M d, Y', strtotime($app['submitted_at'])),
            'formatted_submitted_time' => date('H:i', strtotime($app['submitted_at']))
        ];
    }

    // --- Fetch active live chats for the user ---
    $active_chats = [];
    try {
        $stmt = $conn->prepare("SELECT id, status, created_at FROM live_chats WHERE user_id = ? AND status IN ('Pending', 'Open') ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$current_user_id]);
        $active_chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching active chats: " . $e->getMessage());
    }

    // Format active chats
    $formatted_chats = [];
    foreach ($active_chats as $chat) {
        $formatted_chats[] = [
            'id' => $chat['id'],
            'status' => $chat['status'],
            'created_at' => $chat['created_at'],
            'formatted_date' => date('M d, Y H:i', strtotime($chat['created_at']))
        ];
    }

    // --- Fetch application statistics ---
    $app_stats = [
        'total' => 0,
        'approved_complete' => 0,
        'pending' => 0,
        'rejected' => 0,
        'expiring_soon' => 0,
        'expired' => 0
    ];

    $stmt = $conn->prepare("
        SELECT 
            status, 
            renewal_status,
            COUNT(id) as count 
        FROM applications 
        WHERE user_id = ? 
        GROUP BY status, renewal_status
    ");
    $stmt->execute([$current_user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $status = strtolower($row['status']);
        $renewal_status = $row['renewal_status'];
        $count = (int)$row['count'];
        
        $app_stats['total'] += $count;
        
        if (in_array($status, ['approved', 'complete'])) {
            $app_stats['approved_complete'] += $count;
            if ($renewal_status === 'expiring_soon') {
                $app_stats['expiring_soon'] += $count;
            } elseif ($renewal_status === 'expired') {
                $app_stats['expired'] += $count;
            }
        } elseif ($status === 'pending') {
            $app_stats['pending'] += $count;
        } elseif ($status === 'rejected') {
            $app_stats['rejected'] += $count;
        }
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => [
            'applications' => $formatted_apps,
            'active_chats' => $formatted_chats,
            'stats' => $app_stats,
            'timestamp' => time()
        ]
    ]);

} catch (PDOException $e) {
    error_log("Applicant Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
