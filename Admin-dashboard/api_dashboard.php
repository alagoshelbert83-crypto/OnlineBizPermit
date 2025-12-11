<?php
/**
 * API Endpoint for Admin Dashboard Real-time Data
 * Returns JSON data for statistics, users, and chart data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/functions.php';

// Check if user is authenticated and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // --- Fetch Recent Activity (Users) ---
    $recent_users_sql = "SELECT id, name, email, role, created_at
                        FROM users
                        ORDER BY created_at DESC
                        LIMIT 7";
    $recent_users_result = $conn->query($recent_users_sql);
    $recent_users = $recent_users_result ? $recent_users_result->fetchAll(PDO::FETCH_ASSOC) : [];

    // Format recent users
    $formatted_users = [];
    foreach ($recent_users as $user) {
        $formatted_users[] = [
            'id' => $user['id'],
            'name' => htmlspecialchars($user['name']),
            'email' => htmlspecialchars($user['email']),
            'role' => $user['role'],
            'created_at' => $user['created_at'],
            'time_ago' => time_ago($user['created_at'])
        ];
    }

    // --- Fetch All Dashboard Stats in a Single, Optimized Query ---
    $dashboard_stats_sql = "SELECT
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'staff') as staff_count,
        (SELECT COUNT(*) FROM users WHERE role = 'user' AND is_approved = 0) as pending_users,
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE)) as new_users_this_month,
        (SELECT COUNT(*) FROM applications) as total_applications,
        (SELECT COUNT(*) FROM applications WHERE status = 'pending') as pending_applications,
        (SELECT COUNT(*) FROM applications WHERE status = 'approved') as approved_applications,
        (SELECT COUNT(*) FROM applications WHERE submitted_at >= DATE_TRUNC('month', CURRENT_DATE)) as new_applications_this_month";
    $dashboard_stats_result = $conn->query($dashboard_stats_sql);
    $dashboard_stats = $dashboard_stats_result ? $dashboard_stats_result->fetch(PDO::FETCH_ASSOC) : [];

    // --- Monthly User Registrations and Applications for Combined Chart ---
    $monthlyUserData = [];
    $monthlyAppData = [];
    for ($i = 11; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $monthKey = $date->format('M Y');
        $monthlyUserData[$monthKey] = 0;
        $monthlyAppData[$monthKey] = 0;
    }

    // Fetch actual user registration counts from the database for the last 12 months
    $twelveMonthsAgo = new DateTime('-11 months');
    $startDate = $twelveMonthsAgo->format('Y-m-01 00:00:00');

    $userRes = $conn->query("SELECT TO_CHAR(created_at, 'Mon YYYY') AS month, COUNT(*) AS total
                         FROM users
                         WHERE created_at >= '{$startDate}'
                         GROUP BY TO_CHAR(created_at, 'YYYY-MM'), TO_CHAR(created_at, 'Mon YYYY')
                         ORDER BY TO_CHAR(created_at, 'YYYY-MM') ASC");

    if ($userRes) {
        while ($row = $userRes->fetch(PDO::FETCH_ASSOC)) {
            if (isset($monthlyUserData[$row['month']])) {
                $monthlyUserData[$row['month']] = (int)$row['total'];
            }
        }
    }

    // Fetch application counts for the last 12 months
    $appRes = $conn->query("SELECT TO_CHAR(submitted_at, 'Mon YYYY') AS month, COUNT(*) AS total
                         FROM applications
                         WHERE submitted_at >= '{$startDate}'
                         GROUP BY TO_CHAR(submitted_at, 'YYYY-MM'), TO_CHAR(submitted_at, 'Mon YYYY')
                         ORDER BY TO_CHAR(submitted_at, 'YYYY-MM') ASC");

    if ($appRes) {
        while ($row = $appRes->fetch(PDO::FETCH_ASSOC)) {
            if (isset($monthlyAppData[$row['month']])) {
                $monthlyAppData[$row['month']] = (int)$row['total'];
            }
        }
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => [
                'total_users' => (int)($dashboard_stats['total_users'] ?? 0),
                'staff_count' => (int)($dashboard_stats['staff_count'] ?? 0),
                'pending_users' => (int)($dashboard_stats['pending_users'] ?? 0),
                'new_users_this_month' => (int)($dashboard_stats['new_users_this_month'] ?? 0),
                'total_applications' => (int)($dashboard_stats['total_applications'] ?? 0),
                'pending_applications' => (int)($dashboard_stats['pending_applications'] ?? 0),
                'approved_applications' => (int)($dashboard_stats['approved_applications'] ?? 0),
                'new_applications_this_month' => (int)($dashboard_stats['new_applications_this_month'] ?? 0)
            ],
            'recent_users' => $formatted_users,
            'chart' => [
                'months' => array_keys($monthlyUserData),
                'user_data' => array_values($monthlyUserData),
                'app_data' => array_values($monthlyAppData)
            ],
            'timestamp' => time()
        ]
    ]);

} catch (PDOException $e) {
    error_log("Admin Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
