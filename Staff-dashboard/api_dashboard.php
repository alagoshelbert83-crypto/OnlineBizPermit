<?php
/**
 * API Endpoint for Staff Dashboard Real-time Data
 * Returns JSON data for KPIs, recent applications, and chart data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/staff_header.php';

// Check if user is authenticated and is staff
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($user_role, ['staff', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // --- Fetch data for KPIs ---
    $kpi_sql = "SELECT
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM applications";
    $kpi_stmt = $conn->query($kpi_sql);
    $kpis = $kpi_stmt ? $kpi_stmt->fetch(PDO::FETCH_ASSOC) : [];

    // --- Fetch recent applications ---
    $recent_apps_sql = "SELECT a.id, a.business_name, u.name as applicant_name, a.status, a.submitted_at
                        FROM applications a
                        JOIN users u ON a.user_id = u.id
                        ORDER BY a.submitted_at DESC
                        LIMIT 5";
    $recent_apps_stmt = $conn->query($recent_apps_sql);
    $recent_applications = $recent_apps_stmt ? $recent_apps_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Format recent applications for JSON
    $formatted_recent = [];
    foreach ($recent_applications as $app) {
        $formatted_recent[] = [
            'id' => $app['id'],
            'business_name' => htmlspecialchars($app['business_name']),
            'applicant_name' => htmlspecialchars($app['applicant_name']),
            'status' => $app['status'],
            'submitted_at' => $app['submitted_at'],
            'formatted_date' => date('M d', strtotime($app['submitted_at']))
        ];
    }

    // --- Fetch data for monthly trend chart ---
    $monthly_labels = [];
    $monthly_counts = [];
    $counts_by_month = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_key = date('Y-m', strtotime("-$i month"));
        $monthly_labels[] = date('M Y', strtotime($month_key));
        $counts_by_month[$month_key] = 0;
    }

    $monthly_sql = "SELECT to_char(submitted_at, 'YYYY-MM') AS month, COUNT(id) AS count
            FROM applications
            WHERE submitted_at >= (current_date - INTERVAL '6 months')
            GROUP BY month
            ORDER BY month ASC";
    $monthly_stmt = $conn->query($monthly_sql);
    if ($monthly_stmt) {
        while ($row = $monthly_stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($counts_by_month[$row['month']])) {
                $counts_by_month[$row['month']] = (int)$row['count'];
            }
        }
    }
    $monthly_counts = array_values($counts_by_month);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => [
            'kpis' => [
                'total_applications' => (int)($kpis['total_applications'] ?? 0),
                'approved_count' => (int)($kpis['approved_count'] ?? 0),
                'pending_count' => (int)($kpis['pending_count'] ?? 0),
                'rejected_count' => (int)($kpis['rejected_count'] ?? 0)
            ],
            'recent_applications' => $formatted_recent,
            'chart' => [
                'labels' => $monthly_labels,
                'data' => $monthly_counts
            ],
            'timestamp' => time()
        ]
    ]);

} catch (PDOException $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
