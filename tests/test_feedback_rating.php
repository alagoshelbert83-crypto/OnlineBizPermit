<?php
// Basic test for feedback rating flow
require_once __DIR__ . '/../db.php';
try {
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, message, rating) VALUES (?, ?, ?) RETURNING id");
    $stmt->execute([1, 'Test rating message', 4]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['id'])) {
        echo "OK: feedback rating inserted with id " . $row['id'] . "\n";
        $conn->exec("DELETE FROM feedback WHERE id = " . (int)$row['id']);
    } else {
        echo "FAIL: could not insert feedback rating\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
