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
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

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
                if (!$conn || !($conn instanceof PDO)) {
                    throw new Exception("Database connection is null or invalid");
                }

                // If we're in a transaction, roll it back (shouldn't happen if db.php worked correctly)
                if ($conn->inTransaction()) {
                    try {
                        $conn->rollBack();
                        error_log("WARNING: Rolled back unexpected transaction before application submission (attempt " . ($retry_count + 1) . ")");
                    } catch (PDOException $rollback_e) {
                        // If rollback fails, connection is in bad state - we need a new connection
                        error_log("WARNING: Failed to rollback existing transaction: " . $rollback_e->getMessage());
                        $conn = null;
                        // Reconnect
                        $old_skip = getenv('SKIP_DB_CONNECT');
                        putenv('SKIP_DB_CONNECT=0');
                        require_once __DIR__ . '/db.php';
                        if ($old_skip !== false) {
                            putenv('SKIP_DB_CONNECT=' . $old_skip);
                        } else {
                            putenv('SKIP_DB_CONNECT');
                        }
                        // Continue to test the new connection
                    }
                }

                // Test the connection with a simple query
                // Use a prepared statement to avoid any potential issues
                $test_stmt = $conn->prepare("SELECT 1");
                if (!$test_stmt) {
                    throw new Exception("Failed to prepare connection test query");
                }
                
                $test_result = $test_stmt->execute();
                if (!$test_result) {
                    $errorInfo = $test_stmt->errorInfo();
                    throw new Exception("Connection test query failed: " . ($errorInfo[2] ?? 'Unknown error'));
                }
                
                // Fetch the result to ensure the query actually worked
                $test_row = $test_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$test_row) {
                    throw new Exception("Connection test query returned no result");
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

                // Small delay before retry
                usleep(50000); // 50ms delay
                
                // Try to reconnect if connection is null
                if (!$conn || !($conn instanceof PDO)) {
                    try {
                        $old_skip = getenv('SKIP_DB_CONNECT');
                        putenv('SKIP_DB_CONNECT=0');
                        require_once __DIR__ . '/db.php';
                        if ($old_skip !== false) {
                            putenv('SKIP_DB_CONNECT=' . $old_skip);
                        } else {
                            putenv('SKIP_DB_CONNECT');
                        }
                    } catch (Exception $reconnect_e) {
                        error_log("Failed to reconnect during validation: " . $reconnect_e->getMessage());
                    }
                }
            }
        }

        if ($connection_ready) {
            try {
                // CRITICAL: Ensure session is written and closed before transaction
                // This prevents session handler from interfering with transaction
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                // Double-check connection is still valid before starting transaction
                if (!$conn || !($conn instanceof PDO)) {
                    throw new Exception("Database connection lost before transaction start");
                }
                
                // Ensure we're not already in a transaction (safety check)
                if ($conn->inTransaction()) {
                    error_log("WARNING: Already in transaction before beginTransaction() call");
                    try {
                        $conn->rollBack();
                    } catch (PDOException $e) {
                        error_log("Failed to rollback existing transaction: " . $e->getMessage());
                        throw new Exception("Connection is in an invalid transaction state");
                    }
                }

                // Now we can safely begin the transaction
                try {
                    $conn->beginTransaction();
                } catch (PDOException $begin_e) {
                    error_log('Failed to begin transaction: ' . $begin_e->getMessage());
                    error_log('SQL State: ' . $begin_e->getCode());
                    throw new Exception('Failed to start database transaction. Please try again.');
                }
                
                // Prepare the comprehensive application data as JSON
                $form_details_json = json_encode($application_data);
                if ($form_details_json === false) {
                    $json_error = json_last_error_msg();
                    error_log('JSON encoding failed: ' . $json_error);
                    throw new Exception('Failed to encode application data. Please check your input.');
                }

                $business_name = $application_data['business_name'];
                $business_address = $application_data['business_address'] ?? '';
                $type_of_business = $application_data['type_of_business'] ?? '';

                // Validate that user_id exists (foreign key constraint)
                if (empty($current_user_id) || !is_numeric($current_user_id)) {
                    throw new Exception('Invalid user ID. Please log in again.');
                }

                // Insert into applications table using RETURNING clause for PostgreSQL
                // This is more reliable than lastInsertId() especially with connection pooling
                $stmt = $conn->prepare(
                    "INSERT INTO applications (user_id, business_name, business_address, type_of_business, status, form_details, submitted_at)
                     VALUES (?, ?, ?, ?, 'pending', ?, NOW())
                     RETURNING id"
                );

                if (!$stmt) {
                    $errorInfo = $conn->errorInfo();
                    throw new PDOException('Failed to prepare INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error'), (int)($errorInfo[0] ?? 0));
                }

                // Log the data being inserted for debugging
                error_log('Attempting to insert application: user_id=' . $current_user_id . ', business_name=' . $business_name);
                error_log('Form details length: ' . strlen($form_details_json) . ' bytes');

                try {
                    $execute_result = $stmt->execute([$current_user_id, $business_name, $business_address, $type_of_business, $form_details_json]);
                } catch (PDOException $execute_e) {
                    // Log the specific error immediately
                    error_log('=== INSERT EXECUTE EXCEPTION ===');
                    error_log('Error Message: ' . $execute_e->getMessage());
                    error_log('SQL State: ' . $execute_e->getCode());
                    error_log('Error Info: ' . print_r($stmt->errorInfo(), true));
                    error_log('===========================');
                    // Re-throw to be caught by outer catch block
                    throw $execute_e;
                }

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

                // Get the ID from RETURNING clause (more reliable than lastInsertId)
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$result || !isset($result['id'])) {
                    // Fallback to lastInsertId if RETURNING didn't work
                    error_log('RETURNING clause did not return ID, trying lastInsertId()');
                    try {
                        $app_id = (int)$conn->lastInsertId('applications_id_seq');
                        if (!$app_id || $app_id === 0) {
                            $app_id = (int)$conn->lastInsertId();
                        }
                        if (!$app_id || $app_id === 0) {
                            // Last resort: query the database directly
                            $id_stmt = $conn->query("SELECT lastval()");
                            $id_result = $id_stmt->fetchColumn();
                            $app_id = (int)$id_result;
                        }
                    } catch (PDOException $id_e) {
                        error_log('Error getting last insert ID: ' . $id_e->getMessage());
                        throw new Exception('Failed to retrieve application ID. Please contact support.');
                    }
                } else {
                    $app_id = (int)$result['id'];
                }

                if (!$app_id || $app_id === 0) {
                    throw new Exception('Failed to retrieve application ID. The application may not have been saved correctly.');
                }

                error_log('Application INSERT successful, ID: ' . $app_id);

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

                // Document type mapping for display labels
                $document_type_labels = [
                    'dti_registration' => 'DTI Registration Certificate',
                    'bir_registration' => 'BIR Registration Certificate',
                    'barangay_clearance' => 'Barangay Clearance',
                    'fire_safety_certificate' => 'Fire Safety Certificate',
                    'sanitary_permit' => 'Sanitary Permit',
                    'health_inspection' => 'Health Inspection Certificate',
                    'building_permit' => 'Building Permit'
                ];

                // Process uploaded documents with named types
                if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
                    foreach ($_FILES['documents']['name'] as $doc_type => $name) {
                        // Handle both array format (documents[]) and named format (documents[type])
                        if (is_numeric($doc_type)) {
                            // Legacy array format - use generic type
                            $document_type = 'Other';
                            $document_label = basename($name);
                        } else {
                            // Named format - use the type as key
                            $document_type = $doc_type;
                            $document_label = $document_type_labels[$doc_type] ?? ucfirst(str_replace('_', ' ', $doc_type));
                        }

                        $error_key = is_numeric($doc_type) ? $doc_type : $doc_type;
                        if (isset($_FILES['documents']['error'][$error_key]) && $_FILES['documents']['error'][$error_key] === UPLOAD_ERR_OK) {
                            $tmp_name = $_FILES['documents']['tmp_name'][$error_key];
                            $file_type = mime_content_type($tmp_name);
                            $file_size = $_FILES['documents']['size'][$error_key];

                            if (!in_array($file_type, $allowed_types) || $file_size > 100000000) { // 100MB limit
                                throw new Exception('Invalid file type or size for ' . $document_label . '. Only PDF, JPG, PNG under 100MB are allowed.');
                            }

                            $original_name = basename($name);
                            $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                            $unique_filename = uniqid('doc_' . $app_id . '_' . $document_type . '_', true) . '.' . $file_extension;

                            if (!move_uploaded_file($tmp_name, $upload_dir . $unique_filename)) {
                                throw new Exception('File System Error: Could not move uploaded file for ' . $document_label . '. Please check server permissions for the "uploads" folder.');
                            }

                            // Insert document record into DB with document_type
                            $doc_stmt = $conn->prepare("INSERT INTO documents (application_id, document_name, file_path, document_type, upload_date) VALUES (?, ?, ?, ?, NOW())");
                            if (!$doc_stmt) {
                                $doc_errorInfo = $conn->errorInfo();
                                throw new PDOException('Failed to prepare document INSERT statement: ' . ($doc_errorInfo[2] ?? 'Unknown error'), (int)($doc_errorInfo[0] ?? 0));
                            }
                            $doc_execute_result = $doc_stmt->execute([$app_id, $original_name, $unique_filename, $document_type]);
                            if (!$doc_execute_result) {
                                $doc_errorInfo = $doc_stmt->errorInfo();
                                throw new PDOException('Failed to execute document INSERT for ' . $document_label . ': ' . ($doc_errorInfo[2] ?? 'Unknown error'), (int)$doc_errorInfo[0]);
                            }
                            
                            error_log('Document uploaded: ' . $document_label . ' (Type: ' . $document_type . ')');
                        } elseif (isset($_FILES['documents']['error'][$error_key]) && $_FILES['documents']['error'][$error_key] !== UPLOAD_ERR_NO_FILE) {
                            $error_label = isset($document_type_labels[$doc_type]) ? $document_type_labels[$doc_type] : 'document';
                            throw new Exception('An error occurred during file upload for ' . $error_label . '. Code: ' . $_FILES['documents']['error'][$error_key]);
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
                $connection_reset_needed = false;
                
                // Get error details before attempting rollback
                $error_message = $e->getMessage();
                $sql_state = $e->getCode();
                
                // Log the full error for debugging BEFORE rollback attempts
                error_log('Application submission error: ' . $error_message);
                error_log('SQL State: ' . $sql_state);
                
                // For 25P02 (transaction aborted) or other transaction errors, we MUST rollback
                if ($conn && $conn->inTransaction()) {
                    try {
                        $conn->rollback();
                        $rollback_success = true;
                        error_log('Transaction rollback successful');
                    } catch (PDOException $rollback_e) {
                        error_log('Rollback failed: ' . $rollback_e->getMessage());
                        error_log('Rollback SQL State: ' . $rollback_e->getCode());
                        // If rollback fails, the connection is in a bad state - mark for reset
                        $connection_reset_needed = true;
                    }
                } elseif ($sql_state == '25P02') {
                    // Even if not in a transaction according to inTransaction(), 
                    // 25P02 means the connection is in an aborted state - we need to reset it
                    error_log('25P02 error detected - connection reset needed');
                    $connection_reset_needed = true;
                }
                
                // If connection is in a bad state, try to create a new connection
                if ($connection_reset_needed) {
                    try {
                        // Close the bad connection
                        $conn = null;
                        
                        // Re-establish connection by re-including db.php
                        // We need to temporarily bypass the SKIP_DB_CONNECT check
                        $old_skip = getenv('SKIP_DB_CONNECT');
                        putenv('SKIP_DB_CONNECT=0');
                        
                        // Reconnect
                        require_once __DIR__ . '/db.php';
                        
                        // Restore SKIP_DB_CONNECT if it was set
                        if ($old_skip !== false) {
                            putenv('SKIP_DB_CONNECT=' . $old_skip);
                        } else {
                            putenv('SKIP_DB_CONNECT');
                        }
                        
                        error_log('Connection reset successful after transaction error');
                    } catch (Exception $reconnect_e) {
                        error_log('Failed to reset connection after transaction error: ' . $reconnect_e->getMessage());
                    }
                }

                // CRITICAL: Reopen session AFTER rollback/reset
                // This allows session data to be saved even after transaction failure
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Try to get errorInfo from the exception itself
                $errorInfo = [];
                if ($e instanceof PDOException) {
                    // PDOException has a code property that contains SQLSTATE
                    $errorInfo = [$sql_state, null, $error_message];
                }
                error_log('Error Info: ' . print_r($errorInfo, true));
                error_log('Rollback successful: ' . ($rollback_success ? 'Yes' : 'No'));
                error_log('Connection reset needed: ' . ($connection_reset_needed ? 'Yes' : 'No'));

                // Provide more specific error messages for common issues
                if (strpos($error_message, 'duplicate key') !== false || strpos($error_message, 'unique constraint') !== false) {
                    $user_message = "An application with similar details already exists. Please check your submissions.";
                } elseif (strpos($error_message, 'foreign key') !== false) {
                    $user_message = "Invalid data reference. Please ensure all required information is correct.";
                } elseif (strpos($error_message, 'not null') !== false || strpos($error_message, 'null value') !== false) {
                    $user_message = "Some required fields are missing. Please fill in all required information.";
                } elseif ($sql_state == '25P02' || strpos($error_message, 'current transaction is aborted') !== false) {
                    $user_message = "A database transaction error occurred. Please try again.";
                } elseif (strpos($error_message, 'connection') !== false || strpos($error_message, 'timeout') !== false) {
                    $user_message = "Database connection issue. Please wait a moment and try again.";
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
  <title>Application Submitted Successfully - OnlineBizPermit</title>
  <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
  <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .success-container {
      background: #ffffff;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 700px;
      width: 100%;
      padding: 50px 40px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .success-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, #10b981, #059669, #10b981);
      background-size: 200% 100%;
      animation: shimmer 3s infinite;
    }
    @keyframes shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    .success-icon {
      width: 100px;
      height: 100px;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 30px;
      animation: scaleIn 0.5s ease-out;
      box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
    }
    @keyframes scaleIn {
      0% {
        transform: scale(0);
        opacity: 0;
      }
      50% {
        transform: scale(1.1);
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }
    .success-icon i {
      font-size: 50px;
      color: #ffffff;
      animation: checkmark 0.6s ease-out 0.3s both;
    }
    @keyframes checkmark {
      0% {
        transform: scale(0) rotate(45deg);
        opacity: 0;
      }
      50% {
        transform: scale(1.2) rotate(45deg);
      }
      100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
      }
    }
    .success-title {
      font-size: 32px;
      font-weight: 800;
      color: #1e293b;
      margin-bottom: 15px;
      animation: fadeInUp 0.6s ease-out 0.2s both;
    }
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .success-message {
      font-size: 18px;
      color: #64748b;
      margin-bottom: 40px;
      line-height: 1.6;
      animation: fadeInUp 0.6s ease-out 0.4s both;
    }
    .application-details {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border-radius: 16px;
      padding: 30px;
      margin-bottom: 40px;
      text-align: left;
      animation: fadeInUp 0.6s ease-out 0.6s both;
    }
    .detail-item {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #e2e8f0;
    }
    .detail-item:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    .detail-icon {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, #4a69bd 0%, #3b82f6 100%);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 20px;
      flex-shrink: 0;
    }
    .detail-icon i {
      font-size: 20px;
      color: #ffffff;
    }
    .detail-content {
      flex: 1;
    }
    .detail-label {
      font-size: 13px;
      color: #64748b;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 5px;
    }
    .detail-value {
      font-size: 18px;
      color: #1e293b;
      font-weight: 700;
    }
    .next-steps {
      background: #fef3c7;
      border-left: 4px solid #f59e0b;
      border-radius: 12px;
      padding: 25px;
      margin-bottom: 40px;
      text-align: left;
      animation: fadeInUp 0.6s ease-out 0.8s both;
    }
    .next-steps h3 {
      font-size: 20px;
      color: #92400e;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .next-steps h3 i {
      font-size: 24px;
    }
    .next-steps ul {
      list-style: none;
      padding: 0;
    }
    .next-steps li {
      padding: 12px 0;
      color: #78350f;
      font-size: 16px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .next-steps li::before {
      content: '✓';
      width: 24px;
      height: 24px;
      background: #f59e0b;
      color: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      flex-shrink: 0;
    }
    .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
      animation: fadeInUp 0.6s ease-out 1s both;
    }
    .btn {
      padding: 16px 32px;
      border-radius: 12px;
      font-weight: 700;
      font-size: 16px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }
    .btn-primary {
      background: linear-gradient(135deg, #4a69bd 0%, #3b82f6 100%);
      color: #ffffff;
      box-shadow: 0 4px 15px rgba(74, 105, 189, 0.4);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(74, 105, 189, 0.5);
    }
    .btn-secondary {
      background: #ffffff;
      color: #4a69bd;
      border: 2px solid #4a69bd;
    }
    .btn-secondary:hover {
      background: #f8fafc;
      transform: translateY(-2px);
    }
    .error-container {
      background: #ffffff;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 600px;
      width: 100%;
      padding: 50px 40px;
      text-align: center;
    }
    .error-icon {
      width: 100px;
      height: 100px;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 30px;
      box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
    }
    .error-icon i {
      font-size: 50px;
      color: #ffffff;
    }
    .error-title {
      font-size: 32px;
      font-weight: 800;
      color: #dc2626;
      margin-bottom: 15px;
    }
    .error-message {
      font-size: 18px;
      color: #64748b;
      margin-bottom: 30px;
      line-height: 1.6;
    }
    @media (max-width: 640px) {
      .success-container, .error-container {
        padding: 30px 20px;
      }
      .success-title, .error-title {
        font-size: 24px;
      }
      .action-buttons {
        flex-direction: column;
      }
      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>";

    if ($response['success']) {
        echo "<div class='success-container'>
          <div class='success-icon'>
            <i class='fas fa-check'></i>
          </div>
          <h1 class='success-title'>Application Submitted Successfully!</h1>
          <p class='success-message'>Your business permit application has been received and is now being processed.</p>
          
          <div class='application-details'>
            <div class='detail-item'>
              <div class='detail-icon'>
                <i class='fas fa-hashtag'></i>
              </div>
              <div class='detail-content'>
                <div class='detail-label'>Application ID</div>
                <div class='detail-value'>#{$response['app_id']}</div>
              </div>
            </div>
            <div class='detail-item'>
              <div class='detail-icon'>
                <i class='fas fa-building'></i>
              </div>
              <div class='detail-content'>
                <div class='detail-label'>Business Name</div>
                <div class='detail-value'>" . htmlspecialchars($response['business_name']) . "</div>
              </div>
            </div>
            <div class='detail-item'>
              <div class='detail-icon'>
                <i class='fas fa-file-alt'></i>
              </div>
              <div class='detail-content'>
                <div class='detail-label'>Application Type</div>
                <div class='detail-value'>" . htmlspecialchars($response['application_type']) . "</div>
              </div>
            </div>
            <div class='detail-item'>
              <div class='detail-icon'>
                <i class='fas fa-calendar-check'></i>
              </div>
              <div class='detail-content'>
                <div class='detail-label'>Submission Date</div>
                <div class='detail-value'>" . date('F d, Y \a\t H:i') . "</div>
              </div>
            </div>
          </div>

          <div class='next-steps'>
            <h3><i class='fas fa-info-circle'></i> What happens next?</h3>
            <ul>
              <li>Your application will be reviewed by our staff</li>
              <li>You will receive email notifications about status updates</li>
              <li>You can track your application progress in your dashboard</li>
            </ul>
          </div>

          <div class='action-buttons'>
            <a href='applicant_dashboard.php' class='btn btn-primary'>
              <i class='fas fa-tachometer-alt'></i>
              Go to Dashboard
            </a>
            <a href='view_my_application.php?id={$response['app_id']}' class='btn btn-secondary'>
              <i class='fas fa-eye'></i>
              View Application
            </a>
          </div>
        </div>";
    } else {
        echo "<div class='error-container'>
          <div class='error-icon'>
            <i class='fas fa-times'></i>
          </div>
          <h1 class='error-title'>Submission Failed</h1>
          <p class='error-message'>" . htmlspecialchars($response['message']) . "</p>";
        if (!empty($response['errors'])) {
            echo "<p style='color: #64748b; margin-bottom: 20px;'>Please check that all required fields are filled correctly and try again.</p>";
        }
        echo "<a href='submit_application.php' class='btn btn-primary'>
            <i class='fas fa-redo'></i>
            Try Again
          </a>
        </div>";
    }

    echo "</body>
</html>";

} else {
    // If someone tries to access process_business_permit.php directly
    http_response_code(405);
    echo "<h1>Error 405</h1>";
    echo "<p>This file cannot be accessed directly. Please use the application form to submit your data.</p>";
}
?>
