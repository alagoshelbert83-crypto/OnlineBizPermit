<?php

header('Content-Type: application/json');

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
        require_once $db_path;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database configuration is missing.']);
        exit;
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
            echo json_encode(['success' => true, 'chat_id' => (int)$chat_id]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            http_response_code(500);
            error_log('chat create error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to create chat session.']);
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

        $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, sender_id, sender_role, message, created_at) VALUES (:chat_id, :sender_id, :sender_role, :message, NOW())");
        $stmt->execute([':chat_id' => $chat_id, ':sender_id' => $sender_id, ':sender_role' => $sender_role, ':message' => $final_message]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_messages') {
        $chat_id = (int)$_GET['chat_id'];
        $last_id = (int)$_GET['last_id'];

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

        // Fetch current chat status
        $status_stmt = $conn->prepare("SELECT status, user_is_typing, staff_is_typing FROM live_chats WHERE id = :chat_id");
        $status_stmt->execute([':chat_id' => $chat_id]);
        $status_result = $status_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $status_result['status'] = ucfirst($status_result['status'] ?? 'Unknown');
        $status_result['user_is_typing'] = (bool)($status_result['user_is_typing'] ?? false);
        $status_result['staff_is_typing'] = (bool)($status_result['staff_is_typing'] ?? false);

        echo json_encode(['messages' => $messages, 'status' => $status_result]);
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
            $stmt = $conn->prepare("UPDATE live_chats SET status = 'Closed', closed_at = NOW() WHERE id = :chat_id");
            $stmt->execute([':chat_id' => $chat_id]);

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

        if ($sender_role === 'user') {
            $stmt = $conn->prepare("UPDATE live_chats SET user_is_typing = :is_typing WHERE id = :chat_id");
        } elseif ($sender_role === 'staff') {
            $stmt = $conn->prepare("UPDATE live_chats SET staff_is_typing = :is_typing WHERE id = :chat_id");
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid sender role.']);
            exit;
        }
        $stmt->execute([':is_typing' => $is_typing, ':chat_id' => $chat_id]);
        echo json_encode(['success' => true]);
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

            // Update the chat's assigned staff_id
            $update_stmt = $conn->prepare("UPDATE live_chats SET staff_id = :new_staff_id WHERE id = :chat_id");
            $update_stmt->execute([':new_staff_id' => $new_staff_id, ':chat_id' => $chat_id]);

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
        require_once $faq_data_path;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'FAQ data file not found.']);
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

        echo json_encode($botResponse);
    } catch (Exception $e) {
        // Error handling
        http_response_code(500);
        echo json_encode([
            'error' => 'Processing error: ' . $e->getMessage(), // More informative for debugging
            'reply' => 'Sorry, I could not process your request. Please try again.',
            'choices' => [
                ['text' => 'Back to main menu', 'action' => 'welcome']
            ],
            'id' => 'error'
        ]);
    }
    exit;
}

// If no valid action was found
http_response_code(400);
echo json_encode([
    'error' => 'Invalid action',
    'reply' => 'Sorry, something went wrong. Please try again.',
    'choices' => null
]);
exit;
