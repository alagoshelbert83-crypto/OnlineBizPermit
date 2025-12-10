<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/staff_header.php'; // Starts session and auth for staff

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['application_id'])) {
    // Redirect if not a POST request or no application ID
    header("Location: applicants.php");
    exit;
}

$applicationId = (int)$_POST['application_id'];

// --- Security Check: Verify the application exists ---
$verify_stmt = $conn->prepare("SELECT id FROM applications WHERE id = ?");
$verify_stmt->bind_param("i", $applicationId);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
if ($verify_result->num_rows === 0) {
    // User does not own this application, or it doesn't exist.
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Application not found.'];
    header("Location: applicants.php");
    exit;
}
$verify_stmt->close();


// --- Data Sanitization ---
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$application_data = [];
foreach ($_POST as $key => $value) {
    if (is_array($value)) {
        $application_data[$key] = array_map('sanitize_input', $value);
    } else {
        $application_data[$key] = sanitize_input($value);
    }
}

$conn->begin_transaction();
try {
    // 1. Update the main application details
    $form_details_json = json_encode($application_data);
    $business_name = $application_data['business_name'];
    $business_address = $application_data['business_address'] ?? '';
    $type_of_business = $application_data['type_of_business'] ?? '';

    $stmt = $conn->prepare(
        "UPDATE applications 
         SET business_name = ?, business_address = ?, type_of_business = ?, form_details = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->bind_param("ssssi", $business_name, $business_address, $type_of_business, $form_details_json, $applicationId);
    if (!$stmt->execute()) {
        error_log("Primary update failed (updated_at may be missing): " . $stmt->error);
        // Attempt fallback without updated_at
        $stmt->close();
        $stmt_fb = $conn->prepare(
            "UPDATE applications 
             SET business_name = ?, business_address = ?, type_of_business = ?, form_details = ?
             WHERE id = ?"
        );
        $stmt_fb->bind_param("ssssi", $business_name, $business_address, $type_of_business, $form_details_json, $applicationId);
        if (!$stmt_fb->execute()) {
            throw new Exception("Database Error (fallback): Could not update application details. " . $stmt_fb->error);
        }
        $stmt_fb->close();
    } else {
        $stmt->close();
    }

    // 2. Handle File Uploads (New or Replacements)
    $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_size = 100 * 1024 * 1024; // 100MB

    // Function to process a single file upload
    function process_upload($file_key, $doc_name, $app_id, $conn, $upload_dir, $allowed_types, $max_size) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES[$file_key]['tmp_name'];
            $file_type = mime_content_type($tmp_name);
            $file_size = $_FILES[$file_key]['size'];

            if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                throw new Exception("Invalid file type or size for {$doc_name}.");
            }

            $original_name = basename($_FILES[$file_key]['name']);
            $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
            $unique_filename = uniqid('doc_' . $app_id . '_', true) . '.' . $file_extension;

            if (!move_uploaded_file($tmp_name, $upload_dir . $unique_filename)) {
                throw new Exception("File System Error: Could not move uploaded file for {$doc_name}.");
            }

            // Use REPLACE (or INSERT...ON DUPLICATE KEY UPDATE) to handle existing docs
            // This assumes `document_name` is a unique key for a given `application_id`
            $doc_stmt = $conn->prepare("INSERT INTO documents (application_id, document_name, file_path) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), upload_date = NOW()");
            $doc_stmt->bind_param("iss", $app_id, $doc_name, $unique_filename);
            if (!$doc_stmt->execute()) {
                throw new Exception("Database Error: Could not save document record for {$doc_name}. " . $doc_stmt->error);
            }
            $doc_stmt->close();
        }
    }

    // Process standard documents
    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        foreach ($_FILES['documents']['name'] as $key => $name) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                // Re-structure the file info for the process_upload function
                $file_info = [
                    'name' => $_FILES['documents']['name'][$key],
                    'type' => $_FILES['documents']['type'][$key],
                    'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                    'error' => $_FILES['documents']['error'][$key],
                    'size' => $_FILES['documents']['size'][$key]
                ];
                // The $key here is the document type, e.g., 'dti_registration'
                process_upload_from_array($file_info, $key, $applicationId, $conn, $upload_dir, $allowed_types, $max_size);
            }
        }
    }

    // Process payment receipt specifically
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
        process_upload_from_array($_FILES['payment_receipt'], 'payment_receipt', $applicationId, $conn, $upload_dir, $allowed_types, $max_size);
    }

    $conn->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Application #' . $applicationId . ' has been updated successfully.'];

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'An error occurred: ' . $e->getMessage()];
}

// Redirect back to the view page to see the changes
header("Location: view_application.php?id=" . $applicationId);
exit;

// Helper function to handle the structured file array from the form
function process_upload_from_array($file, $doc_name, $app_id, $conn, $upload_dir, $allowed_types, $max_size) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $file['tmp_name'];
        $file_type = mime_content_type($tmp_name);
        $file_size = $file['size'];

        if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
            throw new Exception("Invalid file type or size for {$doc_name}.");
        }

        $original_name = basename($file['name']);
        $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid('doc_' . $app_id . '_', true) . '.' . $file_extension;

        if (!move_uploaded_file($tmp_name, $upload_dir . $unique_filename)) {
            throw new Exception("File System Error: Could not move uploaded file for {$doc_name}.");
        }

        // Use REPLACE (or INSERT...ON DUPLICATE KEY UPDATE) to handle existing docs
        $doc_stmt = $conn->prepare("INSERT INTO documents (application_id, document_name, file_path) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), upload_date = NOW()");
        $doc_stmt->bind_param("iss", $app_id, $doc_name, $unique_filename);
        if (!$doc_stmt->execute()) {
            throw new Exception("Database Error: Could not save document record for {$doc_name}. " . $doc_stmt->error);
        }
        $doc_stmt->close();
    }
}
?>