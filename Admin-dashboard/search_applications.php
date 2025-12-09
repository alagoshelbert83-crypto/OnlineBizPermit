<?php
// Admin-dashboard/search_applications.php

require_once __DIR__ . '/db.php';
session_start();

// Authentication Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$term = trim($_GET['term'] ?? '');

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$like_term = "%" . $term . "%";
$id_term = (int)$term;

// Search by business name, assigned applicant name, or owner name from form details.
// Also search by ID if the term is numeric.
// This requires MySQL 5.7+ or MariaDB 10.2+ for JSON functions.
$where_clauses = [
    "a.business_name LIKE ?",
    "u.name LIKE ?",
    "JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.owner_name')) LIKE ?", // For seeded data
    "TRIM(CONCAT_WS(' ',
        JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.first_name')),
        JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.middle_name')),
        JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.last_name'))
    )) LIKE ?" // For real form submissions
];
$params = [$like_term, $like_term, $like_term, $like_term];
$types = "ssss";

if ($id_term > 0) {
    $where_clauses[] = "a.id = ?";
    $params[] = $id_term;
    $types .= "i";
}
// Use COALESCE to intelligently select the best available name for display.
// This makes the search results more informative, especially for unassigned applications.
$sql = "SELECT a.id, a.business_name, a.status,
               COALESCE(
                   u.name, 
                   JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.owner_name')),
                   TRIM(CONCAT_WS(' ',
                       JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.first_name')),
                       JSON_UNQUOTE(JSON_EXTRACT(a.form_details, '$.last_name'))
                   ))
               ) as current_owner_name
        FROM applications a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE " . implode(" OR ", $where_clauses) . "
        ORDER BY a.id DESC LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed to prepare.']);
    exit;
}
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($applications);