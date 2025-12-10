<?php
require './db.php';


// Security check: allow logged-in applicants or guests who started a chat (guest_chat_id in session)
$chat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$chat_session = null;
$error_message = '';
$is_authenticated = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;

// If authenticated user doesn't have chat_id in URL, try to find their active chat
if ($is_authenticated && $chat_id === 0) {
    try {
        $stmt = $conn->prepare("SELECT id FROM live_chats WHERE user_id = ? AND status IN ('Active', 'Pending') ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$current_user_id]);
        $active_chat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($active_chat) {
            $chat_id = (int)$active_chat['id'];
            // Redirect to the chat with proper ID
            header("Location: applicant_conversation.php?id=" . $chat_id);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error finding active chat: " . $e->getMessage());
    }
}

// If not authenticated and no guest session, redirect to login
if (!$is_authenticated && (!isset($_SESSION['guest_chat_id']) || (int)$_SESSION['guest_chat_id'] !== $chat_id)) {
    header("Location: ../login.php");
    exit;
}

if ($chat_id > 0) {
    // Fetch chat session and verify ownership
    try {
        if ($is_authenticated) {
            $stmt = $conn->prepare(
                "SELECT lc.*, u.name as applicant_name 
                 FROM live_chats lc
                 JOIN users u ON lc.user_id = u.id
                 WHERE lc.id = ? AND lc.user_id = ?"
            );
            $stmt->execute([$chat_id, $current_user_id]);
            $chat_session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            // Guest: chat should exist and user_id is NULL, retrieve chat session
            $stmt = $conn->prepare("SELECT * FROM live_chats WHERE id = ? AND user_id IS NULL");
            $stmt->execute([$chat_id]);
            $chat_session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($chat_session) {
                // Set applicant_name from session guest_name if available
                $chat_session['applicant_name'] = $_SESSION['guest_name'] ?? 'Guest';
            }
        }
        // If chat is 'Pending', applicant side simply displays it; only staff change status.
    } catch (PDOException $e) {
        error_log("Failed to fetch chat session: " . $e->getMessage());
        $chat_session = null;
    }
}

if (!$chat_session) {
    $error_message = 'Chat session not found or access denied.';
}

// --- Fetch existing messages for this chat ---
$existing_messages = [];
$last_message_id = 0;
if ($chat_id > 0) {
    // Use LEFT JOIN so guest messages with NULL sender_id still return
    $msg_stmt = $conn->prepare(
        "SELECT cm.*, u.name as sender_name 
         FROM chat_messages cm
         LEFT JOIN users u ON cm.sender_id = u.id
         WHERE cm.chat_id = ? ORDER BY cm.id ASC"
    );
    try {
        $msg_stmt->execute([$chat_id]);
        $existing_messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to fetch chat messages: " . $e->getMessage());
        $existing_messages = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Chat Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="applicant_style.css">
    <style>
        :root {
            --chat-primary: #4a69bd;
            --chat-secondary: #3c5aa6;
            --chat-success: #28a745;
            --chat-light: #f8fafc;
            --chat-border: #e5e9f2;
            --chat-text: #2d3748;
            --chat-text-muted: #718096;
            --chat-shadow: 0 10px 40px rgba(74, 105, 189, 0.1);
            --chat-radius: 20px;
            --chat-radius-sm: 16px;
        }

        .main {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .chat-wrapper {
            max-width: 900px;
            margin: auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--chat-radius);
            box-shadow: var(--chat-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px);
            min-height: 600px;
            position: relative;
            overflow: hidden;
        }

        .chat-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23f0f4f9" fill-opacity="0.03"><circle cx="30" cy="30" r="2"/><circle cx="10" cy="10" r="1.5"/><circle cx="50" cy="20" r="1"/><circle cx="20" cy="50" r="1.5"/><circle cx="40" cy="40" r="1"/></g></g></svg>');
            pointer-events: none;
        }

        .chat-header {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px 25px;
            border-bottom: 1px solid var(--chat-border);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .chat-header .avatar {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .header-info h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--chat-text);
            margin: 0 0 4px 0;
        }

        .header-info p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--chat-text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--chat-success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .chat-window {
            flex-grow: 1;
            padding: 25px 30px;
            overflow-y: auto;
            scroll-behavior: smooth;
            position: relative;
            z-index: 1;
        }

        .chat-window::-webkit-scrollbar {
            width: 6px;
        }

        .chat-window::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .chat-window::-webkit-scrollbar-thumb {
            background: rgba(74, 105, 189, 0.3);
            border-radius: 3px;
        }

        .chat-window::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 105, 189, 0.5);
        }

        .msg {
            display: flex;
            margin-bottom: 20px;
            max-width: 85%;
            align-items: flex-end;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .msg.user {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
        }

        .msg-content {
            display: flex;
            flex-direction: column;
            max-width: calc(100% - 54px);
        }

        .sender-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--chat-text-muted);
            margin-bottom: 4px;
            opacity: 0.8;
        }

        .msg.user .sender-name {
            align-self: flex-end;
        }

        .bubble {
            padding: 12px 18px;
            border-radius: var(--chat-radius);
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
        }

        .bubble:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .timestamp {
            font-size: 0.7rem;
            color: var(--chat-text-muted);
            margin-top: 6px;
            opacity: 0.7;
        }

        .msg.user .timestamp {
            align-self: flex-end;
        }

        /* Message bubble styles */
        .msg.bot .bubble {
            background: linear-gradient(135deg, #e9ecef, #f8f9fa);
            color: var(--chat-text);
            text-align: center;
            border: 1px solid var(--chat-border);
            box-shadow: none;
        }

        .msg.bot {
            max-width: 100%;
            justify-content: center;
        }

        .msg.user .bubble {
            background: linear-gradient(135deg, var(--chat-primary), var(--chat-secondary));
            color: #fff;
            border-bottom-right-radius: 6px;
        }

        .msg.staff .bubble {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: var(--chat-text);
            border-bottom-left-radius: 6px;
            border: 1px solid var(--chat-border);
        }

        /* Enhanced Typing Indicator */
        .typing-indicator {
            padding: 15px 25px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--chat-radius);
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            animation: fadeIn 0.3s ease-out;
        }

        .typing-indicator .avatar {
            width: 32px;
            height: 32px;
        }

        .typing-indicator .bubble {
            background: rgba(248, 249, 250, 0.8);
            border: 1px solid var(--chat-border);
            padding: 8px 12px;
            box-shadow: none;
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            background-color: var(--chat-text-muted);
            border-radius: 50%;
            animation: typing-blink 1.4s infinite both;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing-blink {
            0%, 60%, 100% { opacity: 0.3; transform: scale(0.8); }
            30% { opacity: 1; transform: scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced Input Area */
        .chat-input {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--chat-border);
            padding: 20px 25px;
            align-items: flex-end;
        }

        .btn-file-upload {
            cursor: pointer;
            color: var(--chat-text-muted);
            font-size: 1.3rem;
            padding: 12px;
            border-radius: 50%;
            transition: all 0.2s ease;
            background: rgba(248, 249, 250, 0.8);
            border: 1px solid var(--chat-border);
        }

        .btn-file-upload:hover {
            color: var(--chat-primary);
            background: rgba(74, 105, 189, 0.1);
            transform: scale(1.05);
        }

        .chat-input input[type="text"] {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid var(--chat-border);
            border-radius: var(--chat-radius);
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            outline: none;
        }

        .chat-input input[type="text"]:focus {
            border-color: var(--chat-primary);
            box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.15);
            background: #fff;
        }

        .chat-input .btn {
            padding: 14px 20px;
            border-radius: var(--chat-radius);
            background: linear-gradient(135deg, var(--chat-primary), var(--chat-secondary));
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(74, 105, 189, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-input .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(74, 105, 189, 0.4);
        }

        .chat-input .btn:active {
            transform: translateY(0);
        }

        .chat-input .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* File Preview Enhancement */
        .file-preview {
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.95);
            border-top: 1px solid var(--chat-border);
            font-size: 0.9rem;
            color: var(--chat-text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .file-preview span {
            font-style: italic;
            color: var(--chat-text-muted);
        }

        .file-preview button {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }

        .file-preview button:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        /* Error Message Enhancement */
        .message.error {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #c53030;
            padding: 16px 20px;
            border-radius: var(--chat-radius-sm);
            border: 1px solid #feb2b2;
            margin: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.error::before {
            content: '⚠️';
            font-size: 1.2rem;
        }

        /* Allow HTML in bubbles */
        .bubble { white-space: normal; }
        .bubble a {
            color: inherit;
            font-weight: 600;
            text-decoration: underline;
            transition: opacity 0.2s ease;
        }
        .bubble a:hover { opacity: 0.8; }

        /* Avatar colors */
        .avatar.user-avatar { background: linear-gradient(135deg, var(--chat-primary), var(--chat-secondary)); color: white; }
        .avatar.staff-avatar { background: linear-gradient(135deg, var(--chat-success), #218838); color: white; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main { padding: 15px; }
            .chat-wrapper { height: calc(100vh - 100px); min-height: 500px; }
            .chat-header { padding: 15px 20px; }
            .chat-header .avatar { width: 45px; height: 45px; }
            .header-info h1 { font-size: 1.2rem; }
            .chat-window { padding: 20px; }
            .msg { max-width: 90%; margin-bottom: 16px; }
            .chat-input { padding: 15px 20px; }
            .chat-input input[type="text"] { font-size: 0.95rem; }
        }

        @media (max-width: 480px) {
            .main { padding: 10px; }
            .chat-wrapper { border-radius: var(--chat-radius-sm); }
            .chat-header { padding: 12px 16px; gap: 10px; }
            .chat-header .avatar { width: 40px; height: 40px; }
            .header-info h1 { font-size: 1.1rem; }
            .header-info p { font-size: 0.85rem; }
            .chat-window { padding: 15px; }
            .msg { max-width: 95%; gap: 8px; }
            .avatar { width: 36px; height: 36px; }
            .bubble { padding: 10px 14px; font-size: 0.95rem; }
            .chat-input { padding: 12px 16px; gap: 8px; }
            .chat-input input[type="text"] { padding: 12px 14px; }
            .chat-input .btn { padding: 12px 16px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once './applicant_sidebar.php'; ?>

    <div class="main">
        <header class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="applicant_dashboard.php" class="btn" style="padding: 8px 12px;"><i class="fas fa-arrow-left"></i> Back</a>
                <h1>Live Chat Support</h1>
            </div>
        </header>

        <?php if ($error_message): ?>
            <p class="message error"><?= $error_message ?></p>
        <?php else: ?>
            <div class="chat-wrapper">
                <div id="chatWindow" class="chat-window" aria-live="polite">
                    <div class="msg bot">
                        <div class="bubble">
                            <p>You are connected to live chat support. A staff member will be with you shortly.</p>
                            <p><strong>Status:</strong> <span id="chatStatus"><?= htmlspecialchars(ucfirst($chat_session['status'])) ?></span></p>
                        </div>
                    </div>
                    <!-- Inject existing messages here -->
                    <?php foreach ($existing_messages as $msg): ?>
                        <?php 
                            $last_message_id = $msg['id']; // Track the last message ID
                            // Determine a safe sender name for messages. For guest messages sender_name may be null.
                            $sender_name = $msg['sender_name'] ?? ($_SESSION['guest_name'] ?? ($chat_session['applicant_name'] ?? 'Guest'));
                            $sender_role = htmlspecialchars($msg['sender_role']) === 'user' ? 'user' : 'staff';
                            $avatar_initial = strtoupper(substr($sender_name, 0, 1));
                        ?>
                        <div class="msg <?= $sender_role ?>">
                            <div class="avatar <?= $sender_role ?>-avatar"><?= $avatar_initial ?></div>
                            <div class="msg-content">
                                <div class="sender-name"><?= htmlspecialchars($sender_name) ?></div>
                                <div class="bubble"><?= $msg['message'] // Message is pre-sanitized in the API ?></div>
                                <div class="timestamp"><?= date('h:i A', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div id="initial-load-complete" style="display:none;">
                    </div>
                </div>
                <form id="chatForm" class="chat-input" autocomplete="off">
                    <input type="hidden" id="chatId" value="<?= $chat_id ?>">
                    <label for="fileInput" class="btn-file-upload" title="Attach File">
                        <i class="fas fa-paperclip"></i>
                    </label>
                    <input type="file" id="fileInput" style="display: none;" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <input type="text" id="userInput" placeholder="Type your message..." required <?= $chat_session['status'] === 'Closed' ? 'disabled' : '' ?>>
                    <button type="submit" class="btn" <?= $chat_session['status'] === 'Closed' ? 'disabled' : '' ?>><i class="fas fa-paper-plane"></i></button>
                </form>
                <div id="filePreview" class="file-preview" style="display: none;"></div>
                <div id="typingIndicator" class="typing-indicator" style="display: none;"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatWindow = document.getElementById('chatWindow');
    if (!chatWindow) return;

    const chatForm = document.getElementById('chatForm'), userInput = document.getElementById('userInput');
    const chatId = document.getElementById('chatId').value, chatStatusSpan = document.getElementById('chatStatus');
    const fileInput = document.getElementById('fileInput'), filePreview = document.getElementById('filePreview');
    let lastMessageId = <?= (int)$last_message_id ?>; // Start polling from the last message loaded by PHP
    let typingTimeout;
    let isSending = false; // Prevent multiple simultaneous sends
    let lastSendTime = 0; // Track last send time to prevent spam
    const MIN_SEND_INTERVAL = 1000; // Minimum 1 second between sends

    function scrollToBottom() { chatWindow.scrollTop = chatWindow.scrollHeight; }

    function addMessage(sender, text, senderName = null, timestamp = null) {
        const wrapper = document.createElement('div');
        wrapper.className = 'msg ' + (sender === 'user' ? 'user' : (sender === 'staff' ? 'staff' : 'bot'));

        if (sender === 'bot') {
            wrapper.innerHTML = `<div class="bubble">${text}</div>`;
        } else {
            const avatar = document.createElement('div');
            avatar.className = `avatar ${sender}-avatar`;
            avatar.textContent = (senderName || 'U').charAt(0).toUpperCase();

            const msgContent = document.createElement('div');
            msgContent.className = 'msg-content';

            const nameSpan = document.createElement('div');
            nameSpan.className = 'sender-name';
            nameSpan.textContent = senderName || (sender === 'user' ? 'You' : 'Staff');

            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.innerHTML = text; // Use innerHTML to render links

            const timeSpan = document.createElement('div');
            timeSpan.className = 'timestamp';
            timeSpan.textContent = 'now';

            msgContent.append(nameSpan, bubble, timeSpan);
            wrapper.append(avatar, msgContent);
        }

        chatWindow.appendChild(wrapper);
        scrollToBottom();
    }

    function disableChatInput() {
        userInput.disabled = true;
        chatForm.querySelector('button').disabled = true;
        userInput.placeholder = "This chat has been closed.";
    }

    async function fetchMessages() {
        try {
            const response = await fetch(`./chatbot_api.php?action=get_messages&chat_id=${chatId}&last_id=${lastMessageId}`);
            const data = await response.json();
            const typingIndicator = document.getElementById('typingIndicator');

            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    addMessage(msg.sender_role, msg.message, msg.sender_name, msg.created_at);
                    lastMessageId = msg.id;
                });
            } 

            if (data.status) {
                // Handle typing indicator
                if (data.status.staff_is_typing) {
                    typingIndicator.style.display = 'block';
                } else {
                    typingIndicator.style.display = 'none';
                }
                // Handle chat status (Open/Closed)
                if (chatStatusSpan.textContent !== data.status.status && data.status.status === 'Closed') {
                    chatStatusSpan.textContent = data.status.status;
                    addMessage('bot', 'This chat has been closed by staff.');
                    disableChatInput();
                }
            }
        } catch (error) { console.error('Error fetching messages:', error); }
    }

    async function updateTypingStatus(isTyping) {
        const formData = new FormData();
        formData.append('action', 'update_typing');
        formData.append('chat_id', chatId);
        formData.append('is_typing', isTyping);
        formData.append('sender_role', 'user');
        try { await fetch('./chatbot_api.php', { method: 'POST', body: formData }); }
        catch (error) { console.error('Error updating typing status:', error); }
    }

    userInput.addEventListener('input', () => {
        clearTimeout(typingTimeout);
        updateTypingStatus(true);
        typingTimeout = setTimeout(() => {
            updateTypingStatus(false);
        }, 2000); // User is considered "not typing" after 2 seconds of inactivity
    });


    // Prevent multiple form submissions by removing/re-adding event listener
    let submitHandler = null;

    function setupSubmitHandler() {
        if (submitHandler) {
            chatForm.removeEventListener('submit', submitHandler);
        }

        submitHandler = async function(e) {
            e.preventDefault();
            e.stopPropagation();

            const message = userInput.value.trim();
            if (!message && !fileInput.files[0]) return;

            // Prevent multiple simultaneous sends
            if (isSending) return;

            // Prevent spam by enforcing minimum time between sends
            const now = Date.now();
            if (now - lastSendTime < MIN_SEND_INTERVAL) {
                addMessage('bot', 'Please wait a moment before sending another message.');
                return;
            }

            isSending = true;
            lastSendTime = now;

            // Disable form to prevent spam
            const submitBtn = chatForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            userInput.disabled = true;

            const formData = new FormData();

            clearTimeout(typingTimeout);
            updateTypingStatus(false);

            formData.append('action', 'send_message');
            formData.append('chat_id', chatId);
            formData.append('message', message);
            formData.append('sender_role', 'user');
            formData.append('sender_id', '<?= $current_user_id ?? '' ?>'); // Add sender_id for the API
            if (fileInput.files[0]) {
                formData.append('chat_file', fileInput.files[0]);
            }

            try {
                const response = await fetch('./chatbot_api.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    // The message will be added via the fetchMessages poll
                    userInput.value = '';
                    fileInput.value = ''; // Clear file input
                    filePreview.style.display = 'none';
                    filePreview.innerHTML = '';
                } else {
                    addMessage('bot', 'Error: ' + (data.error || 'Could not send message.'));
                }
            } catch (error) {
                console.error('Error sending message:', error);
                addMessage('bot', 'Error: Could not send message.');
            } finally {
                // Re-enable form and reset sending flag
                isSending = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                userInput.disabled = false;
                userInput.focus();
            }
        };

    // Set up the initial submit handler
    setupSubmitHandler();

    fileInput.addEventListener('change', function() {
        if (this.files[0]) {
            filePreview.innerHTML = `<span>Selected: ${this.files[0].name}</span><button type="button" id="removeFileBtn">&times;</button>`;
            filePreview.style.display = 'flex';

            document.getElementById('removeFileBtn').addEventListener('click', () => {
                fileInput.value = '';
                filePreview.style.display = 'none';
                filePreview.innerHTML = '';
            });
        }
    });

    fetchMessages();
    setInterval(fetchMessages, 1500); // Reduced polling time for faster updates
});
</script>
</body>
</html>
