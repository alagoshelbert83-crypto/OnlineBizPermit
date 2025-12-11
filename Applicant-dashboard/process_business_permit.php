<?php
// Set headers for security and ensure proper error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/db.php';

// Check if the form was submitted using the POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // CRITICAL: Handle authentication and session FIRST, before any database operations
    // Include database connection
    require_once __DIR__ . '/db.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Authentication Check: Only allow users with the 'user' role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
        // Redirect to the main login page if not an applicant
        header("Location: login.php");
        exit;
    }

    $current_user_id = $_SESSION['user_id'];
    $current_user_name = $_SESSION['name'] ?? 'User';

    // CRITICAL: Close session BEFORE any database operations
    // This prevents session handler from interfering with transactions
    session_write_close();

    // Initialize response variables
    $response = [
        'success' => false,
        'message' => '',
        'app_id' => null,
        'business_name' => '',
        'application_type' => '',
        'errors' => []
    ];
    
    // ----------------------------------------------------------------------
    // 1. DATA SANITIZATION (CRITICAL STEP)
    // ----------------------------------------------------------------------
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $application_data = [];

    // Loop through all POST data and sanitize it
    foreach ($_POST as $key => $value) {
        if (is_array($value)) {
            $application_data[$key] = array_map('sanitize_input', $value);
        } else {
            $application_data[$key] = sanitize_input($value);
        }
    }

    // ----------------------------------------------------------------------
    // 2. BASIC VALIDATION (Ensure required fields are present)
    // ----------------------------------------------------------------------
    $required_fields = ['application_type', 'mode_of_payment', 'last_name', 'first_name', 'business_name', 'date_of_application'];
    $errors = [];

    foreach ($required_fields as $field) {
        if (empty($application_data[$field])) {
            $errors[] = "The field '{$field}' is required.";
        }
    }

    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = 'Validation failed';
    } else {
        // ----------------------------------------------------------------------
        // 3. DATABASE INSERTION WITH ROBUST ERROR HANDLING
        // ----------------------------------------------------------------------

        // CRITICAL: Ensure connection is in a completely clean state
        // db.php already handles cleanup at connection time, but we double-check here for safety
        $connection_ready = false;
        $retry_count = 0;
        $max_retries = 2; // Reduced from 3 since db.php already handles initial cleanup

        while (!$connection_ready && $retry_count < $max_retries) {
            try {
                // Check if connection exists
                if (!$conn) {
                    throw new Exception("Database connection is null");
                }

                // If we're in a transaction, roll it back (shouldn't happen if db.php worked correctly)
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                    error_log("WARNING: Rolled back unexpected transaction before application submission (attempt " . ($retry_count + 1) . ")");
                }

                // Test the connection with a simple query
                $test_stmt = $conn->query("SELECT 1");
                if (!$test_stmt) {
                    throw new Exception("Connection test query failed");
                }

                // Connection is ready
                $connection_ready = true;

            } catch (Exception $e) {
                $retry_count++;
                error_log("Connection validation attempt {$retry_count} failed: " . $e->getMessage());

                if ($retry_count >= $max_retries) {
                    $response['message'] = 'Database connection error';
                    $response['errors'] = ['We\'re experiencing technical difficulties connecting to the database. Please try again in a few moments.'];
                    break;
                }

                // Small delay before retry (don't reconnect - db.php already did that)
                usleep(50000); // 50ms delay
            }
        }

        if ($connection_ready) {
            try {
                // CRITICAL: Ensure session is written and closed before transaction
                // This prevents session handler from interfering with transaction
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                // Now we can safely begin the transaction
                $conn->beginTransaction();
                // Prepare the comprehensive application data as JSON
                $form_details_json = json_encode($application_data);

                $business_name = $application_data['business_name'];
                $business_address = $application_data['business_address'] ?? '';
                $type_of_business = $application_data['type_of_business'] ?? '';

                // Insert into applications table
                $stmt = $conn->prepare(
                    "INSERT INTO applications (user_id, business_name, business_address, type_of_business, status, form_details, submitted_at)
                     VALUES (?, ?, ?, ?, 'pending', ?, NOW())"
                );

                if (!$stmt) {
                    $errorInfo = $conn->errorInfo();
                    throw new PDOException('Failed to prepare INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error'), (int)($errorInfo[0] ?? 0));
                }

                // Log the data being inserted for debugging
                error_log('Attempting to insert application: user_id=' . $current_user_id . ', business_name=' . $business_name);

                $execute_result = $stmt->execute([$current_user_id, $business_name, $business_address, $type_of_business, $form_details_json]);

                if (!$execute_result) {
                    $errorInfo = $stmt->errorInfo();
                    $errorMsg = 'Failed to execute INSERT: ' . ($errorInfo[2] ?? 'Unknown error');
                    error_log('=== INSERT EXECUTE FAILED ===');
                    error_log('Error Message: ' . $errorMsg);
                    error_log('SQL State: ' . ($errorInfo[0] ?? 'N/A'));
                    error_log('Error Info: ' . print_r($errorInfo, true));
                    error_log('User ID: ' . $current_user_id);
                    error_log('Business Name: ' . $business_name);
                    error_log('===========================');
                    throw new PDOException($errorMsg, (int)($errorInfo[0] ?? 0));
                }

                error_log('Application INSERT successful');

                // Get last insert id (PDO returns string)
                $app_id = (int)$conn->lastInsertId();

                // Handle File Uploads
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

                // Ensure uploads directory exists and is writable
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0775, true)) {
                        throw new Exception('Configuration Error: Failed to create the uploads directory at ' . $upload_dir);
                    }
                }

                // Best-effort hardening: prevent script execution and indexing in uploads
                $htaccess_path = $upload_dir . '.htaccess';
                if (!file_exists($htaccess_path)) {
                    @file_put_contents($htaccess_path, "Options -Indexes\nphp_flag engine off\n<FilesMatch \.ph(p[0-9]?|t|tml)$>\n\tDeny from all\n</FilesMatch>\n");
                }

                if (!is_writable($upload_dir)) {
                    throw new Exception('Configuration Error: The uploads directory is not writable: ' . $upload_dir);
                }

                // Process uploaded documents
                if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
                    foreach ($_FILES['documents']['name'] as $key => $name) {
                        if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                            $tmp_name = $_FILES['documents']['tmp_name'][$key];
                            $file_type = mime_content_type($tmp_name);
                            $file_size = $_FILES['documents']['size'][$key];

                            if (!in_array($file_type, $allowed_types) || $file_size > 100000000) { // 100MB limit
                                throw new Exception('Invalid file type or size. Only PDF, JPG, PNG under 100MB are allowed.');
                            }

                            $original_name = basename($name);
                            $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                            $unique_filename = uniqid('doc_' . $app_id . '_', true) . '.' . $file_extension;

                            if (!move_uploaded_file($tmp_name, $upload_dir . $unique_filename)) {
                                throw new Exception('File System Error: Could not move uploaded file. Please check server permissions for the "uploads" folder.');
                            }

                            // Insert document record into DB (PDO)
                            $doc_stmt = $conn->prepare("INSERT INTO documents (application_id, document_name, file_path, upload_date) VALUES (?, ?, ?, NOW())");
                            if (!$doc_stmt) {
                                $doc_errorInfo = $conn->errorInfo();
                                throw new PDOException('Failed to prepare document INSERT statement: ' . ($doc_errorInfo[2] ?? 'Unknown error'), (int)($doc_errorInfo[0] ?? 0));
                            }
                            $doc_execute_result = $doc_stmt->execute([$app_id, $original_name, $unique_filename]);
                            if (!$doc_execute_result) {
                                $doc_errorInfo = $doc_stmt->errorInfo();
                                throw new PDOException('Failed to execute document INSERT: ' . ($doc_errorInfo[2] ?? 'Unknown error'), (int)$doc_errorInfo[0]);
                            }
                        } elseif (isset($_FILES['documents']['error'][$key]) && $_FILES['documents']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            throw new Exception('An error occurred during file upload. Code: ' . $_FILES['documents']['error'][$key]);
                        }
                    }
                }

                // Commit the transaction
                $conn->commit();

                // CRITICAL: Reopen session AFTER successful commit
                // This allows session data to be saved after transaction completes
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Create Staff Notification (outside transaction as it's best-effort)
                $notification_message = "New comprehensive application (#{$app_id}) for '{$business_name}' has been submitted.";
                $notification_link = "view_application.php?id={$app_id}";
                try {
                    $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (NULL, ?, ?)");
                    if ($notify_stmt) {
                        $notify_stmt->execute([$notification_message, $notification_link]);
                    }
                } catch (PDOException $e) {
                    error_log('Failed to insert staff notification: ' . $e->getMessage());
                }

                // Send email notification to the applicant confirming submission
                if (file_exists(__DIR__ . '/../Staff-dashboard/email_functions.php')) {
                    require_once __DIR__ . '/../Staff-dashboard/email_functions.php';
                    if (function_exists('sendApplicationEmail')) {
                        try {
                            $applicant_email = $application_data['o_email'] ?? $_SESSION['email'];
                            $applicant_name = $application_data['first_name'] . ' ' . $application_data['last_name'];
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
                            $host = $_SERVER['HTTP_HOST'];
                            $absolute_link = "{$protocol}://{$host}/onlinebizpermit/Applicant-dashboard/view_my_application.php?id={$app_id}";

                            $email_subject = "Application Received: '" . htmlspecialchars($business_name) . "'";
                            $email_body = "
                            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                                <div style='max-width: 600px; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'>
                                    <h2 style='color: #4a69bd;'>Application Received</h2>
                                    <p>Dear " . htmlspecialchars($applicant_name) . ",</p>
                                    <p>We have successfully received your application for <strong>" . htmlspecialchars($business_name) . "</strong>. Its status is currently <strong>Pending</strong> and it will be reviewed by our staff shortly.</p>
                                    <p>You can track the progress of your application by clicking the button below:</p>
                                    <p style='text-align: center; margin: 30px 0;'><a href='" . htmlspecialchars($absolute_link) . "' style='background-color: #4a69bd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Track My Application</a></p>
                                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'><p style='font-size: 0.9em; color: #777;'>Thank you for using our service.<br><strong>The OnlineBizPermit Team</strong></p>
                                </div>
                            </div>";
                            sendApplicationEmail($applicant_email, $applicant_name, $email_subject, $email_body);
                        } catch (Exception $e) {
                            error_log("Confirmation email sending failed for new application ID {$app_id}: " . $e->getMessage());
                        }
                    }
                }

                // Success response
                $response['success'] = true;
                $response['app_id'] = $app_id;
                $response['business_name'] = $business_name;
                $response['application_type'] = $application_data['application_type'];

            } catch (PDOException $e) {
                // Rollback transaction on database error - CRITICAL for PostgreSQL
                // PostgreSQL requires explicit rollback after any error in a transaction
                $rollback_success = false;
                if ($conn && $conn->inTransaction()) {
                    try {
                        $conn->rollback();
                        $rollback_success = true;
                    } catch (PDOException $rollback_e) {
                        error_log('Rollback failed: ' . $rollback_e->getMessage());
                        // If rollback fails, the connection is in a bad state
                        // We need to reconnect for future requests
                        try {
                            $conn = null;
                            require_once __DIR__ . '/db.php';
                        } catch (Exception $reconnect_e) {
                            error_log('Failed to reconnect after rollback failure: ' . $reconnect_e->getMessage());
                        }
                    }
                }

                // CRITICAL: Reopen session AFTER rollback
                // This allows session data to be saved even after transaction failure
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Log the full error for debugging
                error_log('Application submission error: ' . $e->getMessage());
                error_log('SQL State: ' . $e->getCode());

                // PDOException doesn't have errorInfo() - get it from connection if available
                $errorInfo = [];
                if ($e instanceof PDOException) {
                    // Try to get errorInfo from the connection
                    if (isset($conn) && $conn instanceof PDO) {
                        $errorInfo = $conn->errorInfo() ?? [];
                    }
                    // PDOException has a code property that contains SQLSTATE
                    if (empty($errorInfo) && $e->getCode()) {
                        $errorInfo = [$e->getCode(), null, $e->getMessage()];
                    }
                }
                error_log('Error Info: ' . print_r($errorInfo, true));
                error_log('Rollback successful: ' . ($rollback_success ? 'Yes' : 'No'));

                // Get user-friendly error message
                $error_message = $e->getMessage();
                $sql_state = $e->getCode();

                // Provide more specific error messages for common issues
                if (strpos($error_message, 'duplicate key') !== false || strpos($error_message, 'unique constraint') !== false) {
                    $user_message = "An application with similar details already exists. Please check your submissions.";
                } elseif (strpos($error_message, 'foreign key') !== false) {
                    $user_message = "Invalid data reference. Please ensure all required information is correct.";
                } elseif (strpos($error_message, 'not null') !== false) {
                    $user_message = "Some required fields are missing. Please fill in all required information.";
                } elseif ($sql_state == '25P02') {
                    $user_message = "A database transaction error occurred. Please try again.";
                } else {
                    $user_message = "Database Error: " . htmlspecialchars($error_message);
                }

                $response['message'] = $user_message;

            } catch (Exception $e) {
                // Rollback transaction on any other error
                if ($conn && $conn->inTransaction()) {
                    try {
                        $conn->rollback();
                    } catch (Exception $rollback_e) {
                        error_log('Rollback failed: ' . $rollback_e->getMessage());
                        // Reconnect if rollback fails
                        try {
                            $conn = null;
                            require_once __DIR__ . '/db.php';
                        } catch (Exception $reconnect_e) {
                            error_log('Failed to reconnect after rollback failure: ' . $reconnect_e->getMessage());
                        }
                    }
                }

                // CRITICAL: Reopen session AFTER rollback
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Log the error
                error_log('Application submission error: ' . $e->getMessage());

                $response['message'] = htmlspecialchars($e->getMessage());
            }
        }
    }

    // ----------------------------------------------------------------------
    // 4. OUTPUT HTML RESPONSE
    // ----------------------------------------------------------------------

    // Output HTML header
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Application Submission Report - OnlineBizPermit</title>
  <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
  <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
  <link rel='stylesheet' href='applicant_style.css'>
</head>
<body>
  <div class='wrapper'>
    <div class='main'>
      <div class='form-container'>
        <h1>Application Submission Report</h1>
        <p>Thank you for submitting your business permit application for San Miguel, Catanduanes.</p>
        <hr>";

    if ($response['success']) {
        echo "<h2>✅ Success!</h2>";
        echo "<p>Your comprehensive business permit application has been successfully submitted.</p>";
        echo "<p><strong>Application ID:</strong> #{$response['app_id']}</p>";
        echo "<p><strong>Business Name:</strong> " . htmlspecialchars($response['business_name']) . "</p>";
        echo "<p><strong>Application Type:</strong> " . htmlspecialchars($response['application_type']) . "</p>";
        echo "<p><strong>Submission Date:</strong> " . date('F d, Y \a\t H:i') . "</p>";

        echo "<div class='next-steps'>";
        echo "<h3>What happens next?</h3>";
        echo "<ul>";
        echo "<li>Your application will be reviewed by our staff</li>";
        echo "<li>You will receive notifications about the status</li>";
        echo "<li>You can track your application in your dashboard</li>";
        echo "</ul>";
        echo "</div>";

        echo "<div class='action-buttons'>";
        echo "<a href='applicant_dashboard.php' class='btn btn-primary'>Go to Dashboard</a>";
        echo "<a href='view_my_application.php?id={$response['app_id']}' class='btn btn-secondary'>View Application</a>";
        echo "</div>";
    } else {
        echo "<h2 style='color: #dc2626; margin-bottom: 15px;'>❌ Error!</h2>";
        echo "<p style='color: #1e293b; margin-bottom: 10px;'>" . htmlspecialchars($response['message']) . "</p>";
        if (!empty($response['errors'])) {
            echo "<p style='color: #64748b; margin-bottom: 20px;'>Please check that all required fields are filled correctly and try again.</p>";
        }
        echo "<a href='submit_application.php' class='btn' style='background-color: #4a69bd; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; border: none; cursor: pointer; transition: background-color 0.2s ease;'>Try Again</a>";
    }

    echo "      </div>
    </div>
  </div>
</body>
</html>";

} else {
    // If someone tries to access process_business_permit.php directly
    http_response_code(405);
    echo "<h1>Error 405</h1>";
    echo "<p>This file cannot be accessed directly. Please use the application form to submit your data.</p>";
}
?>
