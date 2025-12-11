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
  <div class="sidebar-top">
    <div class="sidebar-header">
      <div class="logo-icon">
        <i class="fas fa-leaf"></i>
      </div>
      <span class="logo-text">OnlineBizPermit</span>
    </div>
    
    <nav class="sidebar-nav">
      <a href="home.php" class="btn-nav <?= ($current_page === 'home') ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="applicant_dashboard.php" class="btn-nav <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
        <i class="fas fa-folder-open"></i>
        <span>My Applications</span>
      </a>
      <a href="submit_application.php" class="btn-nav <?= ($current_page === 'submit-application') ? 'active' : '' ?>">
        <i class="fas fa-file-alt"></i>
        <span>New Application</span>
      </a>
      <a href="applicant_notifications.php" class="btn-nav <?= ($current_page === 'notifications') ? 'active' : '' ?>">
        <i class="fas fa-bell"></i>
        <span>Notifications</span>
        <?php if ($unread_notifications_count > 0): ?>
          <span class="notification-badge"><?= $unread_notifications_count ?></span>
        <?php endif; ?>
      </a>
      <a href="applicant_reports.php" class="btn-nav <?= ($current_page === 'reports') ? 'active' : '' ?>">
        <i class="fas fa-chart-pie"></i>
        <span>My Reports</span>
      </a>
      <a href="applicant_audit_logs.php" class="btn-nav <?= ($current_page === 'audit_logs') ? 'active' : '' ?>">
        <i class="fas fa-history"></i>
        <span>My Activity</span>
      </a>
      
      <hr class="sidebar-divider">
      
      <a href="applicant_faq.php?action=start_chat" id="startLiveChatBtn" class="btn-nav <?= ($current_page === 'live_chat') ? 'active' : '' ?>">
        <i class="fas fa-headset"></i>
        <span>Live Chat</span>
      </a>
      <a href="about.php" class="btn-nav <?= ($current_page === 'about') ? 'active' : '' ?>">
        <i class="fas fa-info-circle"></i>
        <span>About Us</span>
      </a>
      <a href="applicant_faq.php" class="btn-nav <?= ($current_page === 'faq') ? 'active' : '' ?>">
        <i class="fas fa-question-circle"></i>
        <span>FAQ Assistant</span>
      </a>
      <a href="applicant_feedback.php" class="btn-nav <?= ($current_page === 'feedback') ? 'active' : '' ?>">
        <i class="fas fa-comment-dots"></i>
        <span>Feedback</span>
      </a>
    </nav>
  </div>
  
  <div class="sidebar-bottom">
    <div class="user-profile-section">
      <div class="user-avatar">
        <?php if ($current_user_picture): ?>
          <img src="../uploads/<?= htmlspecialchars($current_user_picture) ?>" alt="<?= htmlspecialchars($current_user_name) ?>">
        <?php else: ?>
          <span><?= strtoupper(substr($current_user_name, 0, 1)) ?></span>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($current_user_name) ?></span>
        <span class="user-role">Applicant</span>
      </div>
    </div>
    
    <div class="sidebar-actions">
      <a href="applicant-settings.php" class="btn-nav <?= ($current_page === 'settings') ? 'active' : '' ?>">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
      </a>
      <a href="logout.php" class="btn-nav logout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
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
    .sidebar { 
        width: 80px; 
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); 
        padding: 0; 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between; 
        color: #e2e8f0; 
        flex-shrink: 0; 
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        overflow: hidden; 
        position: fixed; 
        height: 100vh; 
        z-index: 1000;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
    }
    .sidebar:hover { width: 280px; }
    
    .sidebar-top {
        flex: 1;
        overflow-y: auto;
        padding: 24px 12px;
    }
    
    .sidebar-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 16px 12px;
        background: rgba(0, 0, 0, 0.2);
    }
    
    .sidebar-header { 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        margin-bottom: 32px; 
        padding: 12px;
        border-radius: 12px;
        background: rgba(74, 105, 189, 0.1);
        transition: all 0.3s ease;
    }
    .sidebar:hover .sidebar-header { 
        justify-content: flex-start; 
        padding-left: 16px;
        background: rgba(74, 105, 189, 0.2);
    }
    
    .logo-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #4a69bd, #3c5aa6);
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(74, 105, 189, 0.4);
        transition: transform 0.3s ease;
    }
    .sidebar:hover .logo-icon {
        transform: rotate(-5deg) scale(1.05);
    }
    
    .logo-icon i { 
        font-size: 1.5rem; 
        color: #fff;
    }
    
    .logo-text { 
        font-size: 1.1rem; 
        font-weight: 700; 
        color: #fff; 
        white-space: nowrap; 
        opacity: 0; 
        transition: opacity 0.3s ease 0.1s; 
        margin-left: 12px;
        letter-spacing: -0.5px;
    }
    .sidebar:hover .logo-text { opacity: 1; }
    
    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .btn-nav { 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        padding: 12px 16px; 
        margin-bottom: 4px; 
        border-radius: 10px; 
        text-decoration: none; 
        background: transparent; 
        color: #94a3b8; 
        font-weight: 500; 
        font-size: 14px;
        transition: all 0.2s ease; 
        position: relative;
        overflow: hidden;
    }
    
    .btn-nav::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%) scaleY(0);
        width: 3px;
        height: 0;
        background: linear-gradient(180deg, #4a69bd, #3c5aa6);
        border-radius: 0 3px 3px 0;
        transition: all 0.2s ease;
    }
    
    .btn-nav i { 
        min-width: 24px; 
        text-align: center; 
        font-size: 1.2em; 
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }
    
    .btn-nav span { 
        white-space: nowrap; 
        opacity: 0; 
        max-width: 0; 
        overflow: hidden; 
        transition: opacity 0.2s ease 0.1s, max-width 0.3s ease 0.1s, margin-left 0.2s ease 0.1s; 
        position: relative;
        margin-left: 0;
    }
    
    .sidebar:hover .btn-nav { 
        justify-content: flex-start; 
        padding-left: 16px;
    }
    .sidebar:hover .btn-nav span { 
        opacity: 1; 
        max-width: 200px; 
        margin-left: 12px; 
    }
    
    .btn-nav:hover { 
        background: rgba(255, 255, 255, 0.08); 
        color: #fff;
        transform: translateX(4px);
    }
    .btn-nav:hover::before {
        height: 60%;
        transform: translateY(-50%) scaleY(1);
    }
    .btn-nav:hover i {
        transform: scale(1.1);
    }
    
    .btn-nav.active { 
        background: linear-gradient(90deg, rgba(74, 105, 189, 0.2), rgba(60, 90, 166, 0.15)); 
        color: #fff; 
        box-shadow: 0 2px 8px rgba(74, 105, 189, 0.3);
        border-left: 3px solid #4a69bd;
    }
    .btn-nav.active::before {
        height: 60%;
        transform: translateY(-50%) scaleY(1);
    }
    .btn-nav.active i {
        color: #60a5fa;
    }
    
    .btn-nav.logout { 
        color: #f87171;
        margin-top: 8px;
    }
    .btn-nav.logout:hover { 
        background: rgba(248, 113, 113, 0.2); 
        color: #fff;
    }
    
    .notification-badge { 
        background: linear-gradient(135deg, #ef4444, #dc2626); 
        color: white; 
        border-radius: 10px; 
        padding: 2px 8px; 
        font-size: 11px; 
        font-weight: 700; 
        margin-left: auto;
        min-width: 20px;
        text-align: center;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .sidebar:not(:hover) .notification-badge { 
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 0;
    }
    
    .sidebar-divider { 
        border: none; 
        height: 1px; 
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        margin: 16px 0; 
    }
    
    .user-profile-section {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 12px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.05);
        margin-bottom: 12px;
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(10px);
    }
    .sidebar:hover .user-profile-section {
        opacity: 1;
        transform: translateY(0);
        justify-content: flex-start;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4a69bd, #3c5aa6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .user-info {
        margin-left: 12px;
        display: flex;
        flex-direction: column;
        opacity: 0;
        max-width: 0;
        overflow: hidden;
        transition: opacity 0.3s ease 0.1s, max-width 0.3s ease 0.1s, margin-left 0.3s ease 0.1s;
    }
    .sidebar:hover .user-info {
        opacity: 1;
        max-width: 150px;
        margin-left: 12px;
    }
    
    .user-name {
        font-weight: 600;
        font-size: 14px;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .user-role {
        font-size: 12px;
        color: #94a3b8;
        white-space: nowrap;
    }
    
    .sidebar-actions {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .main { 
        margin-left: 80px; 
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    }
    
    /* Scrollbar styling for sidebar */
    .sidebar-top::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar-top::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar-top::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 2px;
    }
    .sidebar-top::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }
</style>
