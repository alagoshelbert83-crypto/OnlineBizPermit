<?php
$page_title = 'Live Chat';
$current_page = 'live_chats';

require_once './staff_header.php';

$chat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$chat_session = null;
$error_message = '';
$staff_id = $_SESSION['user_id'];

if ($chat_id > 0) {
    // Fetch chat session details
    $stmt = $conn->prepare(
        "SELECT lc.*, u.name as applicant_name
         FROM live_chats lc
         LEFT JOIN users u ON lc.user_id = u.id
         WHERE lc.id = :chat_id"
    );
    $stmt->execute([':chat_id' => $chat_id]);
    $chat_session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($chat_session) {
        // If chat is 'Pending', assign it to the current staff member and set status to 'Active'
        if (($chat_session['status'] ?? '') === 'Pending') {
            $update_stmt = $conn->prepare("UPDATE live_chats SET staff_id = :staff_id, status = 'Active' WHERE id = :chat_id");
            $update_stmt->execute([':staff_id' => $staff_id, ':chat_id' => $chat_id]);
            // Refresh chat session data locally
            $chat_session['status'] = 'Active';
            $chat_session['staff_id'] = $staff_id;
        }
    } else {
        $error_message = 'Chat session not found.';
    }
} else {
    $error_message = 'No chat ID provided.';
}

// --- Fetch existing messages for this chat ---
$existing_messages = [];
$last_message_id = 0;
if ($chat_id > 0) {
    $msg_stmt = $conn->prepare(
        "SELECT cm.*, 
         COALESCE(u.name, 
             CASE 
                 WHEN cm.sender_role = 'staff' THEN 'Staff'
                 WHEN cm.sender_role = 'guest' THEN 'Guest'
                 ELSE 'User'
             END
         ) as sender_name 
         FROM chat_messages cm
         LEFT JOIN users u ON cm.sender_id = u.id
         WHERE cm.chat_id = :chat_id ORDER BY cm.id ASC"
    );
    $msg_stmt->execute([':chat_id' => $chat_id]);
    $existing_messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
    $msg_stmt = null;
    
    // Get the last message ID for polling
    if (!empty($existing_messages)) {
        $last_message_id = (int)end($existing_messages)['id'];
    }
}

require_once './staff_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <?php if ($error_message): ?>
        <div class="message error"><?= $error_message ?></div>
    <?php else: ?>
        <div class="chat-wrapper">
            <div class="chat-header">
                <a href="staff_conversations.php" class="btn-back" title="Back to Chats"><i class="fas fa-arrow-left"></i></a>
                <div class="avatar user-avatar" style="background-color: #<?= substr(md5($chat_session['applicant_name']), 0, 6) ?>;">
                    <?= strtoupper(substr($chat_session['applicant_name'], 0, 1)) ?>
                </div>
                <div class="header-info">
                    <h2><?= htmlspecialchars($chat_session['applicant_name'] ?? 'Applicant') ?></h2>
                    <p id="chatStatusText">Status: <span id="chatStatus"><?= htmlspecialchars(ucfirst($chat_session['status'])) ?></span></p>
                </div>
                <div class="header-actions"></div>
            </div>
            <div id="chatWindow" class="chat-window" aria-live="polite">
                <div class="msg bot">
                    <div class="bubble">
                        <p>You are connected to the chat with <?= htmlspecialchars($chat_session['applicant_name']) ?>.</p>
                    </div>
                </div>
                <!-- Inject existing messages here -->
                <?php foreach ($existing_messages as $msg): ?>
                    <div class="msg <?= htmlspecialchars($msg['sender_role']) === 'staff' ? 'staff' : ($msg['sender_role'] === 'guest' ? 'user' : 'user') ?>" data-message-id="<?= (int)$msg['id'] ?>">
                        <div class="avatar <?= htmlspecialchars($msg['sender_role']) ?>-avatar">
                            <?= strtoupper(substr($msg['sender_name'] ?? ($msg['sender_role'] === 'staff' ? 'Staff' : 'User'), 0, 1)) ?>
                        </div>
                        <div class="msg-content">
                            <div class="sender-name"><?= htmlspecialchars($msg['sender_name'] ?? ($msg['sender_role'] === 'staff' ? 'Staff' : 'User')) ?></div>
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
                <button type="submit" class="btn" title="Send Message" <?= $chat_session['status'] === 'Closed' ? 'disabled' : '' ?>><i class="fas fa-paper-plane"></i></button>
            </form>
            <div id="filePreview" class="file-preview" style="display: none;"></div>
        </div>
    <?php endif; ?>
</div>

<style>
    :root {
        --staff-chat-primary: #28a745;
        --staff-chat-secondary: #218838;
        --staff-chat-accent: #4a69bd;
        --staff-chat-light: #f8fafc;
        --staff-chat-border: #e5e9f2;
        --staff-chat-text: #2d3748;
        --staff-chat-text-muted: #718096;
        --staff-chat-shadow: 0 10px 40px rgba(40, 167, 69, 0.1);
        --staff-chat-radius: 20px;
        --staff-chat-radius-sm: 16px;
    }

    .main {
        padding: 20px;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        min-height: 100vh;
    }

    .chat-wrapper {
        max-width: 950px;
        margin: auto;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: var(--staff-chat-radius);
        box-shadow: var(--staff-chat-shadow);
        border: 1px solid rgba(255, 255, 255, 0.2);
        flex-direction: column;
        height: calc(100vh - 140px);
        min-height: 650px;
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
        background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23e8f5e8" fill-opacity="0.03"><circle cx="30" cy="30" r="2"/><circle cx="10" cy="10" r="1.5"/><circle cx="50" cy="20" r="1"/><circle cx="20" cy="50" r="1.5"/><circle cx="40" cy="40" r="1"/></g></g></svg>');
        pointer-events: none;
    }

    .chat-header {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px 25px;
        border-bottom: 1px solid var(--staff-chat-border);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
    }

    .btn-back {
        background: rgba(248, 249, 250, 0.8);
        border: 1px solid var(--staff-chat-border);
        color: var(--staff-chat-text-muted);
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-back:hover {
        background: var(--staff-chat-accent);
        color: white;
        transform: scale(1.05);
    }

    .chat-header .avatar {
        width: 52px;
        height: 52px;
        border: 3px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .header-info h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--staff-chat-text);
        margin: 0 0 6px 0;
    }

    .header-info p {
        margin: 0;
        font-size: 0.95rem;
        color: var(--staff-chat-text-muted);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--staff-chat-primary);
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    .header-actions {
        margin-left: auto;
        display: flex;
        gap: 10px;
    }

    .header-actions .btn-danger {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: var(--staff-chat-radius);
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white;
        box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
    }

    .header-actions .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
    }

    .header-actions .btn-danger i {
        transition: transform 0.3s ease;
    }

    .header-actions .btn-danger:hover i {
        transform: rotate(90deg);
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
        background: rgba(40, 167, 69, 0.3);
        border-radius: 3px;
    }

    .chat-window::-webkit-scrollbar-thumb:hover {
        background: rgba(40, 167, 69, 0.5);
    }

    .msg {
        display: flex;
        margin-bottom: 22px;
        max-width: 85%;
        align-items: flex-end;
        gap: 12px;
        animation: slideInStaff 0.4s ease-out;
    }

    @keyframes slideInStaff {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .msg.staff {
        margin-left: auto;
        flex-direction: row-reverse;
    }

    .msg.user {
        margin-right: auto;
    }

    .avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
        flex-shrink: 0;
        border: 2px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        transition: transform 0.2s ease;
    }

    .avatar:hover {
        transform: scale(1.08);
    }

    .msg-content {
        display: flex;
        flex-direction: column;
        max-width: calc(100% - 56px);
    }

    .sender-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--staff-chat-text-muted);
        margin-bottom: 4px;
        opacity: 0.8;
    }

    .msg.staff .sender-name {
        align-self: flex-end;
    }

    .bubble {
        padding: 14px 20px;
        border-radius: var(--staff-chat-radius);
        line-height: 1.6;
        white-space: pre-wrap;
        word-wrap: break-word;
        position: relative;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
    }

    .bubble:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }

    .timestamp {
        font-size: 0.75rem;
        color: var(--staff-chat-text-muted);
        margin-top: 6px;
        opacity: 0.7;
    }

    .msg.staff .timestamp {
        align-self: flex-end;
    }

    /* Message bubble styles */
    .msg.bot .bubble {
        background: linear-gradient(135deg, #e9ecef, #f8f9fa);
        color: var(--staff-chat-text);
        text-align: center;
        border: 1px solid var(--staff-chat-border);
        box-shadow: none;
    }

    .msg.bot {
        max-width: 100%;
        justify-content: center;
    }

    .msg.staff .bubble {
        background: linear-gradient(135deg, var(--staff-chat-primary), var(--staff-chat-secondary));
        color: #fff;
        border-bottom-right-radius: 6px;
    }

    .msg.user .bubble {
        background: linear-gradient(135deg, var(--staff-chat-accent), #3c5aa6);
        color: #fff;
        border-bottom-left-radius: 6px;
    }

    /* Enhanced Typing Indicator */
    .msg.typing .bubble {
        background: rgba(248, 249, 250, 0.9);
        border: 1px solid var(--staff-chat-border);
        padding: 12px 16px;
        display: flex;
        gap: 6px;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        animation: fadeInStaff 0.3s ease-out;
    }

    .msg.typing .avatar {
        width: 32px;
        height: 32px;
    }

    .typing-dot {
        width: 7px;
        height: 7px;
        background-color: var(--staff-chat-text-muted);
        border-radius: 50%;
        animation: typing-blink-staff 1.4s infinite both;
    }

    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typing-blink-staff {
        0%, 60%, 100% { opacity: 0.3; transform: scale(0.8); }
        30% { opacity: 1; transform: scale(1.2); }
    }

    @keyframes fadeInStaff {
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
        border-top: 1px solid var(--staff-chat-border);
        padding: 20px 25px;
        align-items: flex-end;
    }

    .btn-file-upload {
        cursor: pointer;
        color: var(--staff-chat-text-muted);
        font-size: 1.4rem;
        padding: 12px;
        border-radius: 50%;
        transition: all 0.2s ease;
        background: rgba(248, 249, 250, 0.8);
        border: 1px solid var(--staff-chat-border);
    }

    .btn-file-upload:hover {
        color: var(--staff-chat-primary);
        background: rgba(40, 167, 69, 0.1);
        transform: scale(1.05);
    }

    .chat-input input[type="text"] {
        flex: 1;
        padding: 16px 20px;
        border: 2px solid var(--staff-chat-border);
        border-radius: var(--staff-chat-radius);
        font-size: 1rem;
        background: rgba(255, 255, 255, 0.9);
        transition: all 0.3s ease;
        outline: none;
    }

    .chat-input input[type="text"]:focus {
        border-color: var(--staff-chat-primary);
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15);
        background: #fff;
    }

    .chat-input .btn {
        padding: 16px 22px;
        border-radius: var(--staff-chat-radius);
        background: linear-gradient(135deg, var(--staff-chat-primary), var(--staff-chat-secondary));
        color: white;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chat-input .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
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
        border-top: 1px solid var(--staff-chat-border);
        font-size: 0.9rem;
        color: var(--staff-chat-text);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .file-preview span {
        font-style: italic;
        color: var(--staff-chat-text-muted);
    }

    .file-preview button {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
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
        border-radius: var(--staff-chat-radius-sm);
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
    .avatar.user-avatar { background: linear-gradient(135deg, var(--staff-chat-accent), #3c5aa6); color: white; }
    .avatar.staff-avatar { background: linear-gradient(135deg, var(--staff-chat-primary), var(--staff-chat-secondary)); color: white; }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main { padding: 15px; }
        .chat-wrapper { height: calc(100vh - 120px); min-height: 550px; }
        .chat-header { padding: 16px 20px; }
        .chat-header .avatar { width: 46px; height: 46px; }
        .header-info h2 { font-size: 1.3rem; }
        .chat-window { padding: 20px; }
        .msg { max-width: 90%; margin-bottom: 18px; }
        .chat-input { padding: 16px 20px; }
        .chat-input input[type="text"] { font-size: 0.95rem; }
    }

    @media (max-width: 480px) {
        .main { padding: 10px; }
        .chat-wrapper { border-radius: var(--staff-chat-radius-sm); }
        .chat-header { padding: 14px 16px; gap: 10px; }
        .chat-header .avatar { width: 42px; height: 42px; }
        .header-info h2 { font-size: 1.2rem; }
        .header-info p { font-size: 0.9rem; }
        .chat-window { padding: 16px; }
        .msg { max-width: 95%; gap: 8px; }
        .avatar { width: 38px; height: 38px; }
        .bubble { padding: 12px 16px; font-size: 0.95rem; }
        .chat-input { padding: 14px 16px; gap: 8px; }
        .chat-input input[type="text"] { padding: 14px 16px; }
        .chat-input .btn { padding: 14px 18px; font-size: 0.9rem; }
        .header-actions .btn-danger { padding: 8px 12px; font-size: 0.9rem; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatWindow = document.getElementById('chatWindow');
    if (!chatWindow) return;

    const chatForm = document.getElementById('chatForm'), userInput = document.getElementById('userInput');
    const chatIdElement = document.getElementById('chatId');
    if (!chatIdElement) {
        console.error('Chat ID element not found');
        return;
    }
    const chatId = chatIdElement.value;
    if (!chatId || chatId === '0') {
        console.error('No valid chat ID provided');
        return;
    }
    const chatStatusSpan = document.getElementById('chatStatus'), chatStatusText = document.getElementById('chatStatusText');
    const fileInput = document.getElementById('fileInput'), filePreview = document.getElementById('filePreview');
    let lastMessageId = <?= $last_message_id ?>; // Start polling from the last message loaded by PHP
    let typingTimeout;
    let isSending = false; // Prevent multiple simultaneous sends
    let lastSendTime = 0; // Track last send time to prevent spam
    const MIN_SEND_INTERVAL = 1000; // Minimum 1 second between sends
    const addedMessageIds = new Set(); // Track message IDs to prevent duplicates

    // Initialize with existing message IDs from PHP-rendered messages
    document.querySelectorAll('[data-message-id]').forEach(el => {
        const msgId = parseInt(el.getAttribute('data-message-id'));
        if (msgId) addedMessageIds.add(msgId);
    });

    // Prevent multiple form submissions by removing/re-adding event listener
    let submitHandler = null;

    function scrollToBottom() { chatWindow.scrollTop = chatWindow.scrollHeight; }

    function addMessage(sender, text, senderName = null, timestamp = null, messageId = null) {
        // Prevent duplicate messages by checking message ID
        if (messageId && addedMessageIds.has(messageId)) {
            return; // Message already added, skip
        }
        
        // If messageId is provided, mark it as added
        if (messageId) {
            addedMessageIds.add(messageId);
        }
        
        removeTypingIndicator(); // Remove any existing typing indicators
        const wrapper = document.createElement('div');
        wrapper.className = 'msg ' + (sender === 'staff' ? 'staff' : (sender === 'user' ? 'user' : 'bot'));
        
        // Add data attribute to track message ID
        if (messageId) {
            wrapper.setAttribute('data-message-id', messageId);
        }

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
            nameSpan.textContent = senderName || (sender === 'staff' ? 'You' : 'Applicant');

            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.innerHTML = text; // Use innerHTML to render links

            const timeSpan = document.createElement('div');
            timeSpan.className = 'timestamp';
            timeSpan.textContent = timestamp ? new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

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
        if (chatStatusSpan) {
            chatStatusSpan.textContent = 'Closed';
            chatStatusText.style.color = '#dc3545';
        }
    }

    function showTypingIndicator() {
        if (document.querySelector('.msg.typing')) return; // Already showing
        const wrapper = document.createElement('div');
        wrapper.className = 'msg typing';
        const avatarColor = '<?= substr(md5($chat_session['applicant_name'] ?? 'user'), 0, 6) ?>';
        const avatarInitial = '<?= strtoupper(substr($chat_session['applicant_name'] ?? 'A', 0, 1)) ?>';
        wrapper.innerHTML = `
            <div class="avatar user-avatar" style="background-color: #${avatarColor}">
                ${avatarInitial}
            </div>
            <div class="bubble">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        `;
        chatWindow.appendChild(wrapper);
        scrollToBottom();
    }

    function removeTypingIndicator() {
        const indicator = document.querySelector('.msg.typing');
        if (indicator) indicator.remove();
    }

    async function fetchMessages() {
        if (!chatId || chatId === '0') {
            console.error('Cannot fetch messages: no valid chat ID');
            return;
        }
        try {
            // Note: The API endpoint is in the Applicant-dashboard folder.
            const response = await fetch(`../Applicant-dashboard/chatbot_api.php?action=get_messages&chat_id=${chatId}&last_id=${lastMessageId}`);
            const data = await response.json();

            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    addMessage(msg.sender_role, msg.message, msg.sender_name, msg.created_at, msg.id);
                    // Update lastMessageId to the highest ID to prevent fetching old messages
                    if (msg.id > lastMessageId) {
                        lastMessageId = msg.id;
                    }
                });
            }
            if (data.status) {
                // Handle typing indicator
                data.status.user_is_typing ? showTypingIndicator() : removeTypingIndicator();

                // Handle chat status (Open/Closed)
                if (chatStatusSpan.textContent !== data.status.status && data.status.status === 'Closed') {
                    chatStatusSpan.textContent = data.status.status;
                    addMessage('bot', 'This chat session is now closed.');
                    disableChatInput();
                }
            }
        } catch (error) { console.error('Error fetching messages:', error); }
    }

    async function updateTypingStatus(isTyping) {
        if (!chatId || chatId === '0') {
            return; // Don't update typing status if no valid chat ID
        }
        const formData = new FormData();
        formData.append('action', 'update_typing');
        formData.append('chat_id', chatId);
        formData.append('is_typing', isTyping);
        formData.append('sender_role', 'staff');
        try { await fetch('../Applicant-dashboard/chatbot_api.php', { method: 'POST', body: formData }); }
        catch (error) { console.error('Error updating typing status:', error); }
    }

    userInput.addEventListener('input', () => {
        clearTimeout(typingTimeout);
        updateTypingStatus(true);
        typingTimeout = setTimeout(() => {
            updateTypingStatus(false);
        }, 2000); // Considered "not typing" after 2 seconds
    });

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

        clearTimeout(typingTimeout);
        updateTypingStatus(false);

        const formData = new FormData();
        formData.append('action', 'send_message');
        const currentChatId = document.getElementById('chatId')?.value || chatId;
        if (!currentChatId || currentChatId === '0') {
            addMessage('bot', 'Error: No chat ID available. Please refresh the page and try again.');
            isSending = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            userInput.disabled = false;
            return;
        }
        formData.append('chat_id', currentChatId);
        formData.append('message', message);
        formData.append('sender_role', 'staff');
        formData.append('sender_id', '<?= $staff_id ?>'); // Add sender_id for the API
        if (fileInput.files[0]) {
            formData.append('chat_file', fileInput.files[0]);
        }

        try {
            const response = await fetch('../Applicant-dashboard/chatbot_api.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                // The message will be added via the fetchMessages poll to ensure it has the correct timestamp and content from the server
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
        
        chatForm.addEventListener('submit', submitHandler);
    }

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

<?php require_once './staff_footer.php'; ?>
