<?php
// This file centralizes the sidebar navigation for the applicant dashboard.

// Set a default if the current page isn't specified in the including file.
$current_page = $current_page ?? 'dashboard';

// Fetch unread notification count for the notification badge.
$unread_notifications_count = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
  try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->execute([$_SESSION['user_id']]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications_count = (int)($count_result['unread_count'] ?? 0);
  } catch (PDOException $e) {
    error_log("Error fetching applicant unread notifications: " . $e->getMessage());
    $unread_notifications_count = 0;
  }
}

?>
<div class="sidebar">
  <div>
    <div class="sidebar-header">
      <i class="fas fa-leaf"></i>
      <span>OnlineBizPermit</span>
    </div>
    <a href="home.php" class="btn-nav <?= ($current_page === 'home') ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Home</span></a>
    <a href="applicant_dashboard.php" class="btn-nav <?= ($current_page === 'dashboard') ? 'active' : '' ?>"><i class="fas fa-folder-open"></i><span>My Applications</span></a>
    <a href="submit_application.php" class="btn-nav <?= ($current_page === 'submit-application') ? 'active' : '' ?>"><i class="fas fa-file-alt"></i><span>New Application</span></a>
    <a href="applicant_notifications.php" class="btn-nav <?= ($current_page === 'notifications') ? 'active' : '' ?>">
        <i class="fas fa-bell"></i><span>Notifications <?php if ($unread_notifications_count > 0): ?><span class="notification-count"><?= $unread_notifications_count ?></span><?php endif; ?></span>
    </a>
    <a href="applicant_reports.php" class="btn-nav <?= ($current_page === 'reports') ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i><span>My Reports</span></a>
    <a href="applicant_audit_logs.php" class="btn-nav <?= ($current_page === 'audit_logs') ? 'active' : '' ?>"><i class="fas fa-history"></i><span>My Activity</span></a>
    <hr class="sidebar-divider">
    <a href="applicant_faq.php?action=start_chat" id="startLiveChatBtn" class="btn-nav <?= ($current_page === 'live_chat') ? 'active' : '' ?>"><i class="fas fa-headset"></i><span>Live Chat</span></a>
    <a href="about.php" class="btn-nav <?= ($current_page === 'about') ? 'active' : '' ?>"><i class="fas fa-info-circle"></i><span>About Us</span></a>
    <a href="applicant_faq.php" class="btn-nav <?= ($current_page === 'faq') ? 'active' : '' ?>"><i class="fas fa-question-circle"></i><span>FAQ Assistant</span></a>
    <a href="applicant_feedback.php" class="btn-nav <?= ($current_page === 'feedback') ? 'active' : '' ?>"><i class="fas fa-comment-dots"></i><span>Feedback</span></a>
  </div>
  <div>
    <a href="applicant-settings.php" class="btn-nav <?= ($current_page === 'settings') ? 'active' : '' ?>"><i class="fas fa-cog"></i><span>Settings</span></a>
    <a href="logout.php" class="btn-nav logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // The live chat button is now a direct link, so the complex JS is no longer needed here.
    // It has been moved to applicant_faq.php to handle the chat creation.
});
</script>

<style>
    /* --- Enhanced Sidebar Styles --- */
    .sidebar { width: 80px; background: #1e293b; padding: 20px 10px; display: flex; flex-direction: column; justify-content: space-between; color: #e2e8f0; flex-shrink: 0; transition: width 0.3s ease; overflow-x: hidden; position: fixed; height: 100%; z-index: 1000; }
    .sidebar:hover { width: 240px; }
    .sidebar-header { display: flex; align-items: center; justify-content: center; margin-bottom: 30px; height: 40px; }
    .sidebar-header i { font-size: 2rem; color: #34d399; transition: transform 0.3s ease; }
    .sidebar-header span { font-size: 1.25rem; font-weight: 700; color: #fff; white-space: nowrap; opacity: 0; transition: opacity 0.2s ease 0.1s; margin-left: 12px; }
    .sidebar:hover .sidebar-header { justify-content: flex-start; padding-left: 10px; }
    .sidebar:hover .sidebar-header i { transform: rotate(-15deg); }
    .sidebar:hover .sidebar-header span { opacity: 1; }
    .btn-nav { display: flex; align-items: center; justify-content: center; padding: 14px 15px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; background: transparent; color: #94a3b8; font-weight: 600; transition: all 0.2s ease; position: relative; }
    .btn-nav i { min-width: 20px; text-align: center; font-size: 1.2em; flex-shrink: 0; }
    .btn-nav span { white-space: nowrap; opacity: 0; max-width: 0; overflow: hidden; transition: opacity 0.1s ease, max-width 0.2s ease 0.1s, margin-left 0.2s ease 0.1s; position: relative; }
    .sidebar:hover .btn-nav { justify-content: flex-start; }
    .sidebar:hover .btn-nav span { opacity: 1; max-width: 150px; margin-left: 12px; }
    .btn-nav:hover { background: #334155; color: #fff; }
    .btn-nav.active { background: linear-gradient(90deg, #4a69bd, #3c5aa6); color: #fff; box-shadow: 0 4px 10px rgba(74, 105, 189, 0.3); }
    .btn-nav.logout { margin-top: 20px; color: #e74c3c; }
    .btn-nav.logout:hover { background: #e74c3c; color: #fff; }
    .notification-count { background-color: #ef4444; color: white; border-radius: 6px; padding: 1px 6px; font-size: 11px; margin-left: 8px; font-weight: 700; position: absolute; top: 10px; right: -15px; }
    .sidebar:not(:hover) .notification-count { top: 5px; right: 5px; }
    .sidebar-divider { border: none; height: 1px; background-color: #334155; margin: 20px 0; }
    .main { margin-left: 80px; transition: margin-left 0.3s ease; }
</style>
