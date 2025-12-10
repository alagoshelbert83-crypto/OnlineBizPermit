<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = $_POST['application_id'];
    $form_data = json_encode($_POST);

    try {
        // Check if data for this application already exists
        $stmt = $conn->prepare("SELECT id FROM staff_form_data WHERE application_id = ?");
        $stmt->execute([$application_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing data
            $stmt = $conn->prepare("UPDATE staff_form_data SET form_data = ? WHERE application_id = ?");
            $success = $stmt->execute([$form_data, $application_id]);
        } else {
            // Insert new data
            $stmt = $conn->prepare("INSERT INTO staff_form_data (application_id, form_data) VALUES (?, ?)");
            $success = $stmt->execute([$application_id, $form_data]);
        }

        if ($success) {
            // Redirect back to the application view page
            header("Location: view_application.php?id=" . $application_id);
            exit;
        } else {
            echo "Error: Failed to save staff form data.";
        }
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}
?>