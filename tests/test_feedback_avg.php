<?php
// Basic test for feedback average
require_once __DIR__ . '/../db.php';
try {
    // Insert sample ratings
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, rating) VALUES (?, ?) RETURNING id");
    $stmt->execute([1, 5]);
    $id1 = $stmt->fetchColumn();
    $stmt->execute([1, 3]);
    $id2 = $stmt->fetchColumn();

    $avg = $conn->query("SELECT AVG(rating) as avg_rating FROM feedback WHERE id IN (" . (int)$id1 . "," . (int)$id2 . ")")->fetch(PDO::FETCH_ASSOC);
    echo "Average rating (test): " . ($avg['avg_rating'] ?? 'N/A') . "\n";

    // Cleanup
    $conn->exec("DELETE FROM feedback WHERE id IN (" . (int)$id1 . "," . (int)$id2 . ")");
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
