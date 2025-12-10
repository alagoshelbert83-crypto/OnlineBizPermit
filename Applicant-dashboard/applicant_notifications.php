<?php
// Page-specific variables
$page_title = 'My Notifications';
$current_page = 'notifications';

// Include Header
require_once __DIR__ . '/applicant_header.php';

// --- Fetch notifications for the logged-in user ---
$notifications = [];
try {
    $stmt = $conn->prepare("SELECT id, message, link, created_at, is_read 
                         FROM notifications 
                         WHERE user_id = ? 
                         ORDER BY created_at DESC");
    $stmt->execute([$current_user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch applicant notifications: " . $e->getMessage());
    $notifications = [];
}

// --- Mark all notifications as read for this user ---
// When the user visits this page, all their notifications are marked as read.
$update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
try {
    $update_stmt->execute([$current_user_id]);
} catch (PDOException $e) {
    error_log("Failed to mark notifications as read for user {$current_user_id}: " . $e->getMessage());
}


// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <h1>My Notifications</h1>
    </header>

    <div class="notification-container">
        <ul class="notification-list">
            <?php if (empty($notifications)): ?>
                <li class="notification-item no-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>You have no notifications yet.</p>
                </li>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <li class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                    <div class="notification-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="notification-content">
                        <?php if (!empty($notification['link'])): ?>
                            <a href="<?= htmlspecialchars($notification['link']) ?>" class="notification-link">
                                <p><?= htmlspecialchars($notification['message']) ?></p>
                            </a>
                        <?php else: ?>
                            <p><?= htmlspecialchars($notification['message']) ?></p>
                        <?php endif; ?>
                        <span class="time" title="<?= htmlspecialchars($notification['created_at']) ?>">
                            <?= htmlspecialchars(date('M d, Y H:i', strtotime($notification['created_at']))) ?>
                        </span>
                    </div>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Custom Styles for Notifications Page -->
<style>
    .notification-container {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
    }
    .notification-list { list-style: none; padding: 0; margin: 0; }
    .notification-item { display: flex; align-items: flex-start; gap: 20px; padding: 20px; border-bottom: 1px solid #f0f3f9; }
    .notification-item:last-child { border-bottom: none; }
    .notification-item.unread { background-color: #f0f3f9; }
    .notification-icon i { font-size: 24px; color: #4a69bd; margin-top: 5px; }
    .notification-content p { margin: 0 0 5px 0; font-weight: 500; color: #343a40; line-height: 1.5; }
    .notification-content .time { font-size: 13px; color: #95a5a6; }
    .notification-link { text-decoration: none; color: inherit; }
    .notification-link:hover p { color: #4a69bd; }
    .no-notifications {
        text-align: center;
        padding: 50px 20px;
        color: #6c757d;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    .no-notifications i { font-size: 48px; color: #ced4da; }
    .no-notifications p { font-size: 1.1rem; font-weight: 600; }
</style>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>

```

Your applicant dashboard now has a fully functional notification system!

<!--
[PROMPT_SUGGESTION]Can you add a "Mark as Read" button for each notification instead of marking all as read?[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]How can I add real-time notifications using JavaScript?[/PROMPT_SUGGESTION]
->
