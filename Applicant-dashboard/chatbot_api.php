<?php
// Start output buffering to prevent any accidental output before headers
ob_start();

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
ini_set('log_errors', 1);

// Set proper error handler to prevent fatal errors from causing 502
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    // Don't output anything that could break JSON response
    return false;
});

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean(); // Clear any output
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal server error',
            'reply' => 'Sorry, something went wrong. Please try again.',
            'choices' => null
        ]);
        error_log("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}");
        ob_end_flush();
        exit;
    }
});

// Clear any output that might have been generated before headers
ob_clean();
header('Content-Type: application/json');

// Basic security check
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request']);
    exit;
}

// Determine if the request is for the live chat system or the FAQ bot
$action = $_REQUEST['action'] ?? null;
$is_live_chat_action = in_array($action, ['create_live_chat', 'send_message', 'get_messages', 'close_chat', 'update_typing', 'transfer_chat']);
$is_faq_bot_action = !$is_live_chat_action; // Any other action is for the FAQ bot

// --- Live Chat API Logic ---
if ($is_live_chat_action) {
    // Connect to DB ONLY for live chat actions to improve resilience.
    $db_path = __DIR__ . '/db.php';
    if (file_exists($db_path)) {
        try {
            require_once $db_path;
            // Verify database connection is available
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection not established');
            }
        } catch (Exception $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed. Please try again later.']);
            exit;
        }
    } else {
        error_log('Database file not found: ' . $db_path);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database configuration is missing.']);
        exit;
    }

    // Include audit logger (with error handling to prevent HTML output)
    $audit_logger_available = false;
    try {
        if (file_exists(__DIR__ . '/../audit_logger.php')) {
            require_once __DIR__ . '/../audit_logger.php';
            $audit_logger_available = true;
        }
    } catch (Exception $e) {
        error_log('Failed to load audit logger: ' . $e->getMessage());
        $audit_logger_available = false;
    }

    // Start session AFTER database connection is established (required for custom session handler)
    try {
        if (session_status() == PHP_SESSION_NONE) {
            @session_start(); // Suppress warnings if session already started
        }
    } catch (Exception $e) {
        error_log('Session start error: ' . $e->getMessage());
        // Continue without session if it fails
    }
    // Determine authentication state: allow guest creation of chats if a guest name is provided.
    $is_authenticated = isset($_SESSION['user_id']);
    $current_user_id = $_SESSION['user_id'] ?? null;
    $current_user_name = $_SESSION['name'] ?? ($_SESSION['guest_name'] ?? 'Guest');
    $current_user_role = $_SESSION['role'] ?? (isset($_SESSION['guest_chat_id']) ? 'guest' : 'user');
    // All live chat action handlers go inside this block
    if ($action === 'create_live_chat') {
        try {
            $conn->beginTransaction();

            // Allow guests to start a chat by supplying a 'guest_name' parameter.
            $guest_name = trim($_REQUEST['guest_name'] ?? '');
            if (!$is_authenticated && empty($guest_name)) {
                // Require either authenticated user or a guest_name
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Authentication required for live chat or supply guest_name.']);
                exit;
            }

            // Insert new chat session and return the id (Postgres RETURNING)
            // Use NULL for user_id when guest
            $user_id_param = $is_authenticated ? $current_user_id : null;
            $stmt = $conn->prepare("INSERT INTO live_chats (user_id, status, created_at) VALUES (:user_id, 'Pending', NOW()) RETURNING id");
            $stmt->execute([':user_id' => $user_id_param]);
            $chat_id = $stmt->fetchColumn();

            // If this is a guest, store guest info in session so they can continue the chat
            if (!$is_authenticated) {
                $_SESSION['guest_chat_id'] = (int)$chat_id;
                $_SESSION['guest_name'] = $guest_name ?: 'Guest';
                $current_user_name = $_SESSION['guest_name'];
                $current_user_role = 'guest';
            }

            // Notify all staff members about the new chat request.
            $notification_message = "New chat request from {$current_user_name}.";
            $notification_link = "../Staff-dashboard/conversation.php?id={$chat_id}"; // Direct link to the new chat

            // Insert notifications for every user with the 'staff' role
            $notify_staff_sql = "INSERT INTO notifications (user_id, message, link, is_read) SELECT id, :message, :link, 0 FROM users WHERE role = 'staff'";
            $notify_stmt = $conn->prepare($notify_staff_sql);
            $notify_stmt->execute([':message' => $notification_message, ':link' => $notification_link]);

            $conn->commit();
            ob_clean(); // Clear any output before JSON
        echo json_encode(['success' => true, 'chat_id' => (int)$chat_id]);
        ob_end_flush();
        } catch (Exception $e) {
            if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
            ob_clean(); // Clear any output
            http_response_code(500);
            error_log('chat create error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to create chat session.']);
            ob_end_flush();
        }
        exit;
    }

    if ($action === 'send_message') {
        $chat_id = (int)$_POST['chat_id'];
        // Sanitize user-provided text message first to prevent XSS
        $message = htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8');
        $sender_role = in_array($_POST['sender_role'], ['user', 'staff']) ? $_POST['sender_role'] : 'user';
        // Determine sender id: if authenticated use user id, if guest ensure session has guest_chat_id
        if ($is_authenticated) {
            $sender_id = (int)($current_user_id);
        } else {
            // Ensure guest is using the same chat id they started
            if (!isset($_SESSION['guest_chat_id']) || (int)$_SESSION['guest_chat_id'] !== $chat_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Authentication required for live chat or invalid guest session.']);
                exit;
            }
            $sender_id = null; // allow NULL for guest sender_id
            $sender_role = 'guest';
        }
        $final_message = nl2br($message); // Apply line breaks to the sanitized message

        // Handle file upload
        if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 50 * 1024 * 1024; // 50MB

            $tmp_name = $_FILES['chat_file']['tmp_name'];
            $file_type = mime_content_type($tmp_name);
            $file_size = $_FILES['chat_file']['size'];

            if (!in_array($file_type, $allowed_types)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, DOC, DOCX, JPG, PNG are allowed.']);
                exit;
            }
            if ($file_size > $max_size) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 50MB.']);
                exit;
            }

            $original_name = basename($_FILES['chat_file']['name']);
            $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
            $unique_filename = uniqid('chat_' . $chat_id . '_', true) . '.' . $file_extension;

            if (move_uploaded_file($tmp_name, $upload_dir . $unique_filename)) {
                $file_url = '/onlinebizpermit/uploads/' . $unique_filename;
                $file_link = "<a href='" . htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener noreferrer'>" . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8') . "</a>";
                $final_message = !empty($final_message) ? $final_message . "<br><br>" . $file_link : $file_link;
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file.']);
                exit;
            }
        }

        try {
            $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, sender_id, sender_role, message, created_at) VALUES (:chat_id, :sender_id, :sender_role, :message, NOW())");
            $stmt->execute([':chat_id' => $chat_id, ':sender_id' => $sender_id, ':sender_role' => $sender_role, ':message' => $final_message]);

            // Log the chat message (only if audit logger is available)
            if ($audit_logger_available) {
                try {
                    $logger = AuditLogger::getInstance();
                    $has_file = isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK;
                    $logger->logChatMessage($chat_id, strlen($message), $sender_id, $sender_role, $has_file);
                } catch (Exception $e) {
                    error_log('Failed to log chat message: ' . $e->getMessage());
                    // Continue without logging - don't break the chat functionality
                }
            }

            ob_clean(); // Clear any output before JSON
            echo json_encode(['success' => true]);
            ob_end_flush();
        } catch (PDOException $e) {
            error_log('send_message error: ' . $e->getMessage());
            ob_clean(); // Clear any output
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send message. Please try again.']);
            ob_end_flush();
        }
        exit;
    }

    if ($action === 'get_messages') {
        $chat_id = (int)$_GET['chat_id'];
        $last_id = (int)$_GET['last_id'];

        try {
            // Fetch new messages with sender's name
            $stmt = $conn->prepare(
                "SELECT 
                    cm.id, 
                    cm.message, 
                    cm.sender_role, 
                    cm.created_at, 
                    u.name as sender_name 
                 FROM chat_messages cm
                 LEFT JOIN users u ON cm.sender_id = u.id
                 WHERE cm.chat_id = :chat_id AND cm.id > :last_id
                 ORDER BY cm.id ASC"
            );
            $stmt->execute([':chat_id' => $chat_id, ':last_id' => $last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch current chat status - handle missing columns gracefully
        try {
            $status_stmt = $conn->prepare("SELECT status, user_is_typing, staff_is_typing FROM live_chats WHERE id = :chat_id");
            $status_stmt->execute([':chat_id' => $chat_id]);
            $status_result = $status_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            // If columns don't exist, try without them
            try {
                $status_stmt = $conn->prepare("SELECT status FROM live_chats WHERE id = :chat_id");
                $status_stmt->execute([':chat_id' => $chat_id]);
                $status_result = $status_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $status_result['user_is_typing'] = false;
                $status_result['staff_is_typing'] = false;
            } catch (PDOException $e2) {
                // If even status column fails, provide defaults
                $status_result = ['status' => 'Unknown', 'user_is_typing' => false, 'staff_is_typing' => false];
            }
        }

        $status_result['status'] = ucfirst($status_result['status'] ?? 'Unknown');
        $status_result['user_is_typing'] = (bool)($status_result['user_is_typing'] ?? false);
        $status_result['staff_is_typing'] = (bool)($status_result['staff_is_typing'] ?? false);

            echo json_encode(['messages' => $messages, 'status' => $status_result]);
        } catch (PDOException $e) {
            error_log('get_messages error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['messages' => [], 'status' => ['error' => 'Failed to fetch messages']]);
        }
        exit;
    }

    if ($action === 'close_chat') {
        $chat_id = (int)$_POST['chat_id'];

        // Ensure only staff can close a chat
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permission denied. Only staff can close chats.']);
            exit;
        }

        try {
            $conn->beginTransaction();
            // Try to update with closed_at column first
            try {
                $stmt = $conn->prepare("UPDATE live_chats SET status = 'Closed', closed_at = NOW() WHERE id = :chat_id");
                $stmt->execute([':chat_id' => $chat_id]);
            } catch (PDOException $e) {
                // If closed_at column doesn't exist, update without it
                $stmt = $conn->prepare("UPDATE live_chats SET status = 'Closed' WHERE id = :chat_id");
                $stmt->execute([':chat_id' => $chat_id]);
            }

            // Notify the applicant that the chat has been closed by staff
            $notification_message = "Your live chat session (#{$chat_id}) has been closed by a staff member.";
            $notification_link = "applicant_conversation.php?id={$chat_id}";
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read) SELECT user_id, :message, :link, 0 FROM live_chats WHERE id = :chat_id");
            $notify_stmt->execute([':message' => $notification_message, ':link' => $notification_link, ':chat_id' => $chat_id]);

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            http_response_code(500);
            error_log('chat close error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to close chat.']);
        }
        exit;
    }

    if ($action === 'update_typing') {
        $chat_id = (int)$_POST['chat_id'];
        $is_typing = $_POST['is_typing'] === 'true' ? 1 : 0;
        $sender_role = $_POST['sender_role'] ?? '';

        try {
            if ($sender_role === 'user') {
                // Try with user_is_typing column first
                $stmt = $conn->prepare("UPDATE live_chats SET user_is_typing = :is_typing WHERE id = :chat_id");
                $stmt->execute([':is_typing' => $is_typing, ':chat_id' => $chat_id]);
            } elseif ($sender_role === 'staff') {
                // Try with staff_is_typing column first
                $stmt = $conn->prepare("UPDATE live_chats SET staff_is_typing = :is_typing WHERE id = :chat_id");
                $stmt->execute([':is_typing' => $is_typing, ':chat_id' => $chat_id]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid sender role.']);
                exit;
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            // If columns don't exist, silently succeed (typing indicators are optional)
            error_log('update_typing error (non-critical): ' . $e->getMessage());
            echo json_encode(['success' => true]); // Return success to avoid breaking the UI
        }
        exit;
    }

    if ($action === 'transfer_chat') {
        $chat_id = (int)$_POST['chat_id'];
        $new_staff_id = (int)$_POST['new_staff_id'];

        // Security: Ensure current user is staff
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        try {
            $conn->beginTransaction();

            // Get names for notification message
            $current_staff_name = $_SESSION['name'] ?? 'A staff member';
            $new_staff_stmt = $conn->prepare("SELECT name FROM users WHERE id = :id");
            $new_staff_stmt->execute([':id' => $new_staff_id]);
            $new_staff_name = $new_staff_stmt->fetchColumn() ?: 'another staff member';

            // Update the chat's assigned staff_id (if column exists)
            try {
                $update_stmt = $conn->prepare("UPDATE live_chats SET staff_id = :new_staff_id WHERE id = :chat_id");
                $update_stmt->execute([':new_staff_id' => $new_staff_id, ':chat_id' => $chat_id]);
            } catch (PDOException $e) {
                // If staff_id column doesn't exist, skip this update (staff assignment is optional)
                error_log('staff_id column not available for chat transfer: ' . $e->getMessage());
            }

            // Add a system message to the chat log
            $transfer_message = "Chat transferred from {$current_staff_name} to {$new_staff_name}.";
            $msg_stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, sender_role, message, created_at) VALUES (:chat_id, 'bot', :message, NOW())");
            $msg_stmt->execute([':chat_id' => $chat_id, ':message' => $transfer_message]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Chat transferred successfully.']);
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            http_response_code(500);
            error_log('chat transfer error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to transfer chat.']);
        }
        exit;
    }
}

// --- FAQ Bot Logic ---
if ($is_faq_bot_action) {
    // This block does NOT require a database connection.
    // By NOT connecting to the DB here, the FAQ bot can function even if the database is down.
    
    // Load FAQ data ONLY when it's a FAQ bot action.
    $faq_data_path = __DIR__ . DIRECTORY_SEPARATOR . 'faq-data.php';
    if (file_exists($faq_data_path)) {
        try {
            require_once $faq_data_path;
        } catch (Exception $e) {
            error_log('FAQ data file error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => 'FAQ system unavailable',
                'reply' => 'Sorry, I could not process your request. Please try again.',
                'choices' => null
            ]);
            exit;
        }
    } else {
        error_log('FAQ data file not found: ' . $faq_data_path);
        http_response_code(500);
        echo json_encode([
            'error' => 'FAQ data file not found',
            'reply' => 'Sorry, I could not process your request. Please try again.',
            'choices' => null
        ]);
        exit;
    }

    // Validate that the FAQ functions and data are available
    if (!isset($faqs) || !function_exists('getFaqById') || !function_exists('getFaqByKeywords')) {
        http_response_code(500);
        echo json_encode([
            'error' => 'FAQ system unavailable',
            'reply' => 'Sorry, I could not process your request. Please try again.',
            'choices' => null
        ]);
        exit;
    }

    // Validate request: Allow both GET (for initial load) and POST (for messages)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid request method.',
            'reply' => 'Sorry, something went wrong. Please try again.',
            'choices' => null
        ]);
        exit;
    }

    function findAnswer($userInput, $faqs) {
        $lowerCaseInput = strtolower(trim($userInput));
        if (empty($lowerCaseInput)) {
            return getFaqById('welcome', $faqs);
        }

        // First try to find by keywords
        $bestMatch = getFaqByKeywords($userInput, $faqs);
        
        if ($bestMatch) {
            return $bestMatch;
        }

        // If no match found, return fallback message
        return getFaqById('fallback', $faqs);
    }

    function getResponseByAction($action, $faqs) {
        $faq = getFaqById($action, $faqs);
        if ($faq) {
            return $faq;
        }
        
        // Fallback to welcome if action not found
        return getFaqById('welcome', $faqs);
    }

    try {
        $request_action = $_REQUEST['action'] ?? null;

        // Handle special action for live chat request
        if ($request_action === 'live_chat_request') {
            echo json_encode([
                'reply' => 'Please wait while I connect you to a staff member...',
                'choices' => [],
                'id' => 'live_chat_init',
                'action' => 'start_live_chat' // Special action for the frontend to recognize
            ]);
            exit;
        }

        // Handle different types of requests from either GET or POST
        if ($request_action) {
            // Handle choice/action selection
            $response = getResponseByAction($request_action, $faqs);
        } else {
            // Handle text message. Use null coalescing for initial parameter-less request.
            $response = findAnswer($_REQUEST['message'] ?? '', $faqs);
        }

        // Validate response
        if (!$response || !isset($response['answer'])) {
            // Fallback response
            $response = getFaqById('fallback', $faqs);
            if (!$response) {
                $response = [
                    'answer' => 'Sorry, I could not process your request. Please try again.',
                    'choices' => [
                        ['text' => 'Back to main menu', 'action' => 'welcome']
                    ],
                    'id' => 'error'
                ];
            }
        }

        // Prepare response
        $botResponse = [
            'reply' => $response['answer'],
            'choices' => $response['choices'] ?? null,
            'id' => $response['id']
        ];

        // The usleep function can be disabled on some free hosting providers, causing errors.
        // usleep(300000); // 0.3 seconds

        ob_clean(); // Clear any output before JSON
        echo json_encode($botResponse);
        ob_end_flush();
    } catch (Exception $e) {
        // Error handling
        ob_clean(); // Clear any output
        http_response_code(500);
        echo json_encode([
            'error' => 'Processing error: ' . $e->getMessage(), // More informative for debugging
            'reply' => 'Sorry, I could not process your request. Please try again.',
            'choices' => [
                ['text' => 'Back to main menu', 'action' => 'welcome']
            ],
            'id' => 'error'
        ]);
        ob_end_flush();
    }
    exit;
}

// If no valid action was found
ob_clean(); // Clear any output
http_response_code(400);
echo json_encode([
    'error' => 'Invalid action',
    'reply' => 'Sorry, something went wrong. Please try again.',
    'choices' => null
]);
ob_end_flush();
exit;
