<?php
$page_title = 'Live Conversations';
$current_page = 'live_chats'; // Keep this for the sidebar active state

require_once './staff_header.php';

// Fetch all live chat sessions
$chats = [];
$sql = "SELECT lc.id, lc.status, lc.created_at, u.name as applicant_name, s.name as staff_name
        FROM live_chats lc
        JOIN users u ON lc.user_id = u.id
        LEFT JOIN users s ON lc.staff_id = s.id
        ORDER BY 
            CASE lc.status
                WHEN 'Pending' THEN 1
                WHEN 'Active' THEN 2
                ELSE 3
            END,
            lc.created_at DESC";

try {
    $result = $conn->query($sql);
    if ($result) {
        $chats = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Fallback: if staff_id column doesn't exist, query without it
    error_log('staff_conversastion.php error: ' . $e->getMessage());
    $fallback_sql = "SELECT lc.id, lc.status, lc.created_at, u.name as applicant_name, NULL as staff_name
                     FROM live_chats lc
                     JOIN users u ON lc.user_id = u.id
                     ORDER BY 
                        CASE lc.status
                            WHEN 'Pending' THEN 1
                            WHEN 'Active' THEN 2
                            ELSE 3
                        END,
                        lc.created_at DESC";
    try {
        $result = $conn->query($fallback_sql);
        if ($result) {
            $chats = $result->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $fe) {
        error_log('staff_conversastion.php fallback error: ' . $fe->getMessage());
        $chats = [];
    }
}

require_once './staff_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-headset" style="color: var(--accent-color);"></i>
                    Live Chat Management
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Review and respond to applicant chat requests
                </p>
            </div>
        </div>
    </header>

    <?php if (empty($chats)): ?>
        <div class="empty-state">
            <i class="fas fa-comments"></i>
            <h3>No Live Chats</h3>
            <p>There are currently no active or pending chat sessions.</p>
        </div>
    <?php else: ?>
        <div class="chat-cards-grid">
            <?php foreach ($chats as $chat): ?>
                <div class="chat-card status-<?= strtolower($chat['status']) ?>">
                    <div class="card-header">
                        <div class="applicant-info">
                            <div class="user-avatar" style="background-color: #<?= substr(md5($chat['applicant_name']), 0, 6) ?>;">
                                <span><?= strtoupper(substr($chat['applicant_name'], 0, 1)) ?></span>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($chat['applicant_name']) ?></strong>
                                <small>Chat ID: #<?= $chat['id'] ?></small>
                            </div>
                        </div>
                        <span class="status-badge status-<?= strtolower($chat['status']) ?>">
                            <?= htmlspecialchars(ucfirst($chat['status'])) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="chat-meta">
                            <div>
                                <i class="fas fa-user-tie"></i>
                                <strong>Assigned to:</strong>
                                <span><?= htmlspecialchars($chat['staff_name'] ?? 'Unassigned') ?></span>
                            </div>
                            <div>
                                <i class="fas fa-clock"></i>
                                <strong>Requested:</strong>
                                <span><?= date('M d, Y, h:i A', strtotime($chat['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="conversation.php?id=<?= $chat['id'] ?>" class="btn-open-chat">
                            <i class="fas fa-sign-in-alt"></i> 
                            <?= $chat['status'] === 'Pending' ? 'Accept Chat' : 'Open Chat' ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .main-header p { color: var(--text-secondary-color); margin-top: 5px; font-size: 1rem; }
    .chat-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; }
    .chat-card { background: var(--card-bg-color); border-radius: var(--border-radius); box-shadow: var(--shadow); display: flex; flex-direction: column; border-left: 4px solid transparent; transition: all 0.3s ease; }
    .chat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .card-header { padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); }
    .applicant-info { display: flex; align-items: center; gap: 0.75rem; }
    .user-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
    .applicant-info strong { font-weight: 600; color: var(--text-color); }
    .applicant-info small { color: var(--text-secondary-color); font-size: 0.85rem; }
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-badge.status-pending { background-color: #fff3cd; color: #856404; }
    .status-badge.status-active { background-color: #d4edda; color: #155724; }
    .status-badge.status-closed { background-color: #e9ecef; color: #6c757d; }
    .card-body { padding: 1.25rem; flex-grow: 1; }
    .chat-meta { display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary-color); }
    .chat-meta div { display: flex; align-items: center; gap: 0.5rem; }
    .chat-meta i { color: var(--primary-color); }
    .chat-meta strong { color: var(--text-color); font-weight: 600; }
    .card-footer { padding: 1rem 1.25rem; background-color: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; }
    .btn-open-chat { background: var(--primary-color); color: white; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; }
    .btn-open-chat:hover { background: #3b5699; transform: translateY(-2px); }
    .chat-card.status-pending { border-left-color: #ffc107; animation: pulse-border 2s infinite; }
    .chat-card.status-active { border-left-color: #28a745; }
    .chat-card.status-closed { border-left-color: #6c757d; opacity: 0.7; }
    .empty-state { text-align: center; padding: 4rem 1rem; background: var(--card-bg-color); border-radius: var(--border-radius); color: var(--text-secondary-color); }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #ced4da; }
    .empty-state h3 { font-size: 1.25rem; color: var(--text-color); }
    @keyframes pulse-border {
        0% { border-left-color: #ffc107; }
        50% { border-left-color: #ffeaa7; }
        100% { border-left-color: #ffc107; }
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .chat-cards-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; }
    }

    @media (max-width: 768px) {
        .main { padding: 15px; }
        .header { margin-bottom: 20px; }
        .header h1 { font-size: 24px; }
        .header p { font-size: 14px; }
        .chat-cards-grid { grid-template-columns: 1fr; gap: 1rem; }
        .chat-card { margin-bottom: 1rem; }
        .card-header { padding: 1rem; flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        .applicant-info { width: 100%; justify-content: space-between; }
        .status-badge { align-self: flex-end; }
        .card-body { padding: 1rem; }
        .chat-meta div { font-size: 0.85rem; }
        .card-footer { padding: 0.75rem 1rem; }
        .btn-open-chat { width: 100%; justify-content: center; }
        .empty-state { padding: 3rem 1rem; }
        .empty-state i { font-size: 2.5rem; }
    }

    @media (max-width: 480px) {
        .main { padding: 10px; }
        .header h1 { font-size: 20px; }
        .header p { font-size: 13px; }
        .chat-card { border-left-width: 3px; }
        .card-header { padding: 0.75rem; }
        .applicant-info { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        .user-avatar { width: 35px; height: 35px; }
        .applicant-info strong { font-size: 0.9rem; }
        .applicant-info small { font-size: 0.8rem; }
        .status-badge { font-size: 0.7rem; padding: 0.2rem 0.6rem; }
        .card-body { padding: 0.75rem; }
        .chat-meta { gap: 0.5rem; }
        .card-footer { padding: 0.5rem 0.75rem; }
        .btn-open-chat { padding: 0.5rem 1rem; font-size: 0.9rem; }
    }
</style>

<?php require_once './staff_footer.php'; ?>
