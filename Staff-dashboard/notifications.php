<?php
$current_page = 'notifications';

// Handle marking notification as read/unread BEFORE including header
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $notification_id = (int)$_GET['id'];

    // Need to include DB connection first for the action
    require_once './db.php';

    // Start session for authentication
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Basic auth check
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
        header("Location: login.php");
        exit;
    }

    $staff_id = $_SESSION['user_id'];

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

require_once './staff_header.php'; // Handles session, DB, and auth

$staff_id = $_SESSION['user_id']; // staff_header.php ensures this is set

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
    .notification-card { 
        background: var(--card-bg-color); 
        border-radius: var(--border-radius); 
        box-shadow: var(--shadow); 
        margin-bottom: 15px; 
        display: flex; 
        align-items: center; 
        padding: 20px; 
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }
    .notification-card.unread { 
        background: #e9eef9; 
        border-left: 4px solid var(--primary-color); 
    }
    .notification-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }
    .notification-card.unread:hover {
        background: #dde4f0;
    }
    .notification-icon { font-size: 1.8rem; color: var(--primary-color); margin-right: 20px; flex-shrink: 0; }
    .notification-content { flex-grow: 1; }
    .notification-content p { margin: 0; font-weight: 500; color: var(--text-color); }
    .notification-content .time { font-size: 0.85rem; color: var(--text-secondary-color); margin-top: 4px; }
    .notification-actions { 
        display: flex; 
        gap: 10px; 
        flex-shrink: 0;
    }
    .btn-action { 
        background: none; 
        border: 1px solid var(--border-color); 
        color: var(--text-secondary-color); 
        width: 36px; 
        height: 36px; 
        border-radius: 8px; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        transition: all 0.2s ease;
        text-decoration: none;
        z-index: 10;
        position: relative;
    }
    .btn-action:hover { 
        background: var(--primary-color); 
        color: #fff; 
        border-color: var(--primary-color);
        transform: scale(1.1);
    }
    .notification-link {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
    }
  </style>

    <!-- Main Content -->
    <div class="main">
      <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-bell" style="color: var(--accent-color);"></i>
                    Notifications
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Stay updated with system notifications and alerts
                </p>
            </div>
        </div>
      </header>
      <div class="notifications-container">
        <?php if (empty($notifications)): ?>
          <div class="notification-card"><p>You have no notifications.</p></div>
        <?php else: ?>
          <?php foreach ($notifications as $notification): ?>
            <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?>" 
                 data-notification-id="<?= $notification['id'] ?>"
                 data-is-read="<?= $notification['is_read'] ? '1' : '0' ?>"
                 data-link="<?= !empty($notification['link']) ? htmlspecialchars($notification['link']) : '' ?>">
              <div class="notification-icon"><i class="fas fa-info-circle"></i></div>
              <div class="notification-content">
                <p><?= htmlspecialchars($notification['message']) ?></p>
                <div class="time"><?= date('M d, Y, g:i a', strtotime($notification['created_at'])) ?></div>
              </div>
              <div class="notification-actions">
                <a href="?action=toggle_read&id=<?= $notification['id'] ?>" 
                   class="btn-action" 
                   title="<?= $notification['is_read'] ? 'Mark as Unread' : 'Mark as Read' ?>"
                   onclick="event.stopPropagation();">
                  <i class="fas <?= $notification['is_read'] ? 'fa-envelope-open' : 'fa-envelope' ?>"></i>
                </a>
                <?php if (!empty($notification['link'])): ?>
                  <a href="<?= htmlspecialchars($notification['link']) ?>" 
                     class="btn-action" 
                     title="View Details"
                     onclick="event.stopPropagation();">
                    <i class="fas fa-arrow-right"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationCards = document.querySelectorAll('.notification-card');
    
    notificationCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on action buttons
            if (e.target.closest('.btn-action')) {
                return;
            }
            
            const notificationId = this.dataset.notificationId;
            const isRead = this.dataset.isRead === '1';
            const link = this.dataset.link;
            
            // Mark as read if unread
            if (!isRead) {
                // Update UI immediately for better UX
                this.classList.remove('unread');
                this.dataset.isRead = '1';
                
                // Update the envelope icon
                const envelopeIcon = this.querySelector('.fa-envelope');
                if (envelopeIcon) {
                    envelopeIcon.classList.remove('fa-envelope');
                    envelopeIcon.classList.add('fa-envelope-open');
                }
                
                // Mark as read in database (async, don't wait)
                fetch(`?action=toggle_read&id=${notificationId}`)
                    .catch(err => console.error('Failed to mark notification as read:', err));
            }
            
            // Navigate to link if available
            if (link) {
                window.location.href = link;
            }
        });
    });
});
</script>

<?php require_once './staff_footer.php'; ?>
