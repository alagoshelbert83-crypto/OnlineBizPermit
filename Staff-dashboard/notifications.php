<?php
$current_page = 'notifications';
require_once './staff_header.php'; // Handles session, DB, and auth

$staff_id = $_SESSION['user_id']; // staff_header.php ensures this is set

// Handle marking notification as read/unread
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $notification_id = (int)$_GET['id'];
    if ($action === 'toggle_read') {
      try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = NOT is_read WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
        $stmt->execute([$notification_id, $staff_id]);
      } catch (PDOException $e) {
        error_log("Failed to toggle notification read state: " . $e->getMessage());
      }
      header("Location: notifications.php");
      exit;
    }
}

$notifications = [];
$sql = "SELECT id, message, link, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? OR user_id IS NULL
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
try {
  $stmt->execute([$staff_id]);
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log("Failed to fetch notifications: " . $e->getMessage());
  $notifications = [];
}

require_once './staff_sidebar.php';
?>
  <style>
    /* Main Content */
    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .main-header h1 { font-size: 28px; font-weight: 700; color: var(--secondary-color); }

    /* Notifications */
    .notifications-container { max-width: 800px; margin: 0 auto; }
    .notification-card { background: var(--card-bg-color); border-radius: var(--border-radius); box-shadow: var(--shadow); margin-bottom: 15px; display: flex; align-items: center; padding: 20px; transition: all 0.3s ease; }
    .notification-card.unread { background: #e9eef9; border-left: 4px solid var(--primary-color); }
    .notification-icon { font-size: 1.8rem; color: var(--primary-color); margin-right: 20px; }
    .notification-content { flex-grow: 1; }
    .notification-content p { margin: 0; font-weight: 500; }
    .notification-content .time { font-size: 0.85rem; color: var(--text-secondary-color); margin-top: 4px; }
    .notification-actions { display: flex; gap: 10px; }
    .btn-action { background: none; border: 1px solid var(--border-color); color: var(--text-secondary-color); width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
    .btn-action:hover { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
  </style>

    <!-- Main Content -->
    <div class="main">
      <div class="main-header"><h1>Notifications</h1></div>
      <div class="notifications-container">
        <?php if (empty($notifications)): ?>
          <div class="notification-card"><p>You have no notifications.</p></div>
        <?php else: ?>
          <?php foreach ($notifications as $notification): ?>
            <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?>">
              <div class="notification-icon"><i class="fas fa-info-circle"></i></div>
              <div class="notification-content">
                <p><?= htmlspecialchars($notification['message']) ?></p>
                <div class="time"><?= date('M d, Y, g:i a', strtotime($notification['created_at'])) ?></div>
              </div>
              <div class="notification-actions">
                <a href="?action=toggle_read&id=<?= $notification['id'] ?>" class="btn-action" title="<?= $notification['is_read'] ? 'Mark as Unread' : 'Mark as Read' ?>">
                  <i class="fas <?= $notification['is_read'] ? 'fa-envelope-open' : 'fa-envelope' ?>"></i>
                </a>
                <?php if (!empty($notification['link'])): ?>
                  <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn-action" title="View Details"><i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

<?php require_once './staff_footer.php'; ?>