<?php
// Page-specific variables
$page_title = 'My Applications';
$current_page = 'dashboard';

// Include Header
require_once __DIR__ . '/applicant_header.php';

// --- Fetch applications for the logged-in user ---
$my_apps = [];
try {
    $stmt = $conn->prepare("SELECT id, business_name, status, submitted_at, business_address, type_of_business, 
                                   renewal_date, renewal_status, renewal_count
                             FROM applications 
                             WHERE user_id = ? 
                             ORDER BY submitted_at DESC");
    $stmt->execute([$current_user_id]);
    $my_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching applications: " . $e->getMessage());
    $my_apps = [];
}

// --- Fetch active live chats for the user ---
$active_chats = [];
try {
    $stmt = $conn->prepare("SELECT id, status, created_at FROM live_chats WHERE user_id = ? AND status IN ('Pending', 'Open') ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$current_user_id]);
    $active_chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching active chats: " . $e->getMessage());
    $active_chats = [];
}

// --- Fetch application statistics (Optimized) ---
$app_stats = [
    'total' => 0,
    'approved_complete' => 0,
    'pending' => 0,
    'rejected' => 0,
    'expiring_soon' => 0,
    'expired' => 0
];

try {
    $stmt = $conn->prepare("
        SELECT 
            status, 
            renewal_status,
            COUNT(id) as count 
        FROM applications 
        WHERE user_id = ? 
        GROUP BY status, renewal_status
    ");
    $stmt->execute([$current_user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $status = strtolower($row['status']);
        $renewal_status = $row['renewal_status'];
        $count = (int)$row['count'];
        
        $app_stats['total'] += $count;
        
        if (in_array($status, ['approved', 'complete'])) {
            $app_stats['approved_complete'] += $count;
            if ($renewal_status === 'expiring_soon') {
                $app_stats['expiring_soon'] += $count;
            } elseif ($renewal_status === 'expired') {
                $app_stats['expired'] += $count;
            }
        } elseif ($status === 'pending') {
            $app_stats['pending'] += $count;
        } elseif ($status === 'rejected') {
            $app_stats['rejected'] += $count;
        }
    }
} catch(PDOException $e) {
    error_log("Error fetching application stats: " . $e->getMessage());
}

// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-folder-open" style="color: var(--accent-color);"></i>
                    My Applications
                    <span id="update-indicator" style="font-size: 0.6rem; color: #28a745; opacity: 0; transition: opacity 0.3s; margin-left: 10px;">
                      <i class="fas fa-circle"></i> Live
                    </span>
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Welcome back, <strong><?= htmlspecialchars($current_user_name) ?></strong>! Here's an overview of your application history.
                </p>
            </div>
        </div>
        <div class="header-right">
            <a href="submit_application.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Application</a>
        </div>
    </header>

    <!-- Renewal Alerts -->
    <?php if ($app_stats['expiring_soon'] > 0 || $app_stats['expired'] > 0): ?>
    <div class="renewal-alerts">
        <?php if ($app_stats['expired'] > 0): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Expired Applications:</strong> You have <?= $app_stats['expired'] ?> expired application(s) that need immediate renewal.
            <a href="submit_application.php?type=renew" class="alert-link">Renew Now</a>
        </div>
        <?php endif; ?>
        
        <?php if ($app_stats['expiring_soon'] > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock"></i>
            <strong>Expiring Soon:</strong> You have <?= $app_stats['expiring_soon'] ?> application(s) expiring within 30 days.
            <a href="submit_application.php?type=renew" class="alert-link">Renew Now</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Application Statistics -->
    <?php if ($app_stats['total'] > 0): ?>
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= $app_stats['total'] ?></h3>
                <p>Total Applications</p>
            </div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $app_stats['approved_complete'] ?></h3>
                <p>Approved</p>
            </div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= $app_stats['pending'] ?></h3>
                <p>Pending</p>
            </div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $app_stats['rejected'] ?></h3>
                <p>Rejected</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        <div class="message success">Your application has been submitted successfully!</div>
    <?php endif; ?>

    <!-- Active Live Chats -->
    <?php if (!empty($active_chats)): ?>
    <div class="chat-section">
        <div class="section-header">
            <h3><i class="fas fa-comments"></i> Active Live Chats</h3>
            <p>Your ongoing conversations with support staff</p>
        </div>
        <div class="chat-list">
            <?php foreach ($active_chats as $chat): ?>
                <div class="chat-item">
                    <div class="chat-info">
                        <div class="chat-status">
                            <span class="status-badge status-<?= strtolower($chat['status']) ?>">
                                <i class="fas fa-<?= $chat['status'] === 'Open' ? 'check-circle' : 'clock' ?>"></i>
                                <?= htmlspecialchars($chat['status']) ?>
                            </span>
                        </div>
                        <div class="chat-date">
                            Started: <?= date('M d, Y H:i', strtotime($chat['created_at'])) ?>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <a href="applicant_conversation.php?id=<?= $chat['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-comments"></i> Continue Chat
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-container">
        <div class="table-header">
            <h3>Application History</h3>
            <p>Complete history of all your business permit applications</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Business Name</th>
                    <th>Type of Business</th>
                    <th>Status</th>
                    <th>Renewal Date</th>
                    <th>Date Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($my_apps)): ?>
                    <tr>
                        <td colspan="6" class="no-results-message">
                            <i class="fas fa-file-alt"></i>
                            <div>You have not submitted any applications yet.</div>
                            <p>Click "New Application" above to get started!</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($my_apps as $app): ?>
                        <tr>
                            <td data-label="Business Name">
                                <div class="app-name">
                                    <strong><?= htmlspecialchars($app['business_name']) ?></strong>
                                    <small><?= htmlspecialchars($app['business_address']) ?></small>
                                </div>
                            </td>
                            <td data-label="Type of Business"><?= htmlspecialchars($app['type_of_business']) ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?= strtolower(preg_replace('/[^a-z]/', '', $app['status'])) ?>">
                                    <i class="fas fa-<?= $app['status'] === 'approved' ? 'check' : ($app['status'] === 'pending' ? 'clock' : ($app['status'] === 'rejected' ? 'times' : 'file')) ?>"></i>
                                    <?= ucfirst(htmlspecialchars($app['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($app['renewal_date'] && in_array($app['status'], ['approved', 'complete'])): ?>
                                    <?php
                                    $renewal_date = new DateTime($app['renewal_date']);
                                    $today = new DateTime();
                                    $days_until_renewal = $today->diff($renewal_date)->days;
                                    $is_expired = $renewal_date < $today;
                                    $is_expiring_soon = $days_until_renewal <= 30 && !$is_expired;
                                    ?>
                                    <div class="renewal-info">
                                        <span class="renewal-date <?= $is_expired ? 'expired' : ($is_expiring_soon ? 'expiring-soon' : 'active') ?>">
                                            <?= date('M d, Y', strtotime($app['renewal_date'])) ?>
                                        </span>
                                        <?php if ($is_expired): ?>
                                            <small class="renewal-status expired">Expired</small>
                                        <?php elseif ($is_expiring_soon): ?>
                                            <small class="renewal-status expiring-soon">Expires in <?= $days_until_renewal ?> days</small>
                                        <?php else: ?>
                                            <small class="renewal-status active">Active</small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-renewal">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Date Submitted">
                                <span class="date-info">
                                    <?= date('M d, Y', strtotime($app['submitted_at'])) ?>
                                    <small><?= date('H:i', strtotime($app['submitted_at'])) ?></small>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="view_my_application.php?id=<?= $app['id'] ?>" class="btn action-btn btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="edit_application.php?id=<?= $app['id'] ?>" class="btn action-btn btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($app['renewal_date'] && in_array($app['status'], ['approved', 'complete'])): ?>
                                        <?php if ($is_expired || $is_expiring_soon): ?>
                                            <a href="submit_application.php?type=renew&original_id=<?= $app['id'] ?>" class="btn action-btn btn-renew">
                                                <i class="fas fa-sync-alt"></i> Renew
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Real-time Dashboard Updates for Applicant
    let updateInterval = null;
    const UPDATE_INTERVAL_MS = 5000; // Update every 5 seconds

    // Update statistics cards
    function updateStats(stats) {
      const statElements = {
        'total': document.querySelector('.stat-card:not(.approved):not(.pending):not(.rejected) .stat-content h3'),
        'approved_complete': document.querySelector('.stat-card.approved .stat-content h3'),
        'pending': document.querySelector('.stat-card.pending .stat-content h3'),
        'rejected': document.querySelector('.stat-card.rejected .stat-content h3')
      };

      // Find total stat card (first one without status class)
      const totalCard = document.querySelector('.stats-container .stat-card:first-child .stat-content h3');
      if (totalCard && stats.total !== undefined) {
        animateValue(totalCard, parseInt(totalCard.textContent) || 0, stats.total);
      }

      const approvedCard = document.querySelector('.stat-card.approved .stat-content h3');
      if (approvedCard && stats.approved_complete !== undefined) {
        animateValue(approvedCard, parseInt(approvedCard.textContent) || 0, stats.approved_complete);
      }

      const pendingCard = document.querySelector('.stat-card.pending .stat-content h3');
      if (pendingCard && stats.pending !== undefined) {
        animateValue(pendingCard, parseInt(pendingCard.textContent) || 0, stats.pending);
      }

      const rejectedCard = document.querySelector('.stat-card.rejected .stat-content h3');
      if (rejectedCard && stats.rejected !== undefined) {
        animateValue(rejectedCard, parseInt(rejectedCard.textContent) || 0, stats.rejected);
      }
    }

    // Animate number changes
    function animateValue(element, start, end) {
      if (!element || start === end) return;
      const duration = 500;
      const range = end - start;
      const increment = range / (duration / 16);
      let current = start;

      const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
          element.textContent = end;
          clearInterval(timer);
        } else {
          element.textContent = Math.floor(current);
        }
      }, 16);
    }

    // Update renewal alerts
    function updateRenewalAlerts(stats) {
      const alertsContainer = document.querySelector('.renewal-alerts');
      if (!alertsContainer) {
        // Create alerts container if it doesn't exist
        const header = document.querySelector('.header');
        if (header && (stats.expired > 0 || stats.expiring_soon > 0)) {
          const newAlerts = document.createElement('div');
          newAlerts.className = 'renewal-alerts';
          header.insertAdjacentElement('afterend', newAlerts);
          updateRenewalAlerts(stats);
          return;
        }
        return;
      }

      if (stats.expired === 0 && stats.expiring_soon === 0) {
        alertsContainer.style.display = 'none';
        return;
      }

      alertsContainer.style.display = 'block';
      alertsContainer.innerHTML = '';

      if (stats.expired > 0) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.innerHTML = `
          <i class="fas fa-exclamation-triangle"></i>
          <strong>Expired Applications:</strong> You have ${stats.expired} expired application(s) that need immediate renewal.
          <a href="submit_application.php?type=renew" class="alert-link">Renew Now</a>
        `;
        alertsContainer.appendChild(alert);
      }

      if (stats.expiring_soon > 0) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning';
        alert.innerHTML = `
          <i class="fas fa-clock"></i>
          <strong>Expiring Soon:</strong> You have ${stats.expiring_soon} application(s) expiring within 30 days.
          <a href="submit_application.php?type=renew" class="alert-link">Renew Now</a>
        `;
        alertsContainer.appendChild(alert);
      }
    }

    // Update applications table
    function updateApplicationsTable(applications) {
      const tbody = document.querySelector('.table-container tbody');
      if (!tbody) return;

      if (applications.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" class="no-results-message">
              <i class="fas fa-file-alt"></i>
              <div>You have not submitted any applications yet.</div>
              <p>Click "New Application" above to get started!</p>
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = applications.map(app => {
        const statusClass = app.status.toLowerCase().replace(/[^a-z]/g, '');
        const statusIcon = app.status === 'approved' ? 'check' : (app.status === 'pending' ? 'clock' : (app.status === 'rejected' ? 'times' : 'file'));
        
        let renewalHtml = '<span class="no-renewal">N/A</span>';
        if (app.renewal_info) {
          const renewalClass = app.renewal_info.is_expired ? 'expired' : (app.renewal_info.is_expiring_soon ? 'expiring-soon' : 'active');
          renewalHtml = `
            <div class="renewal-info">
              <span class="renewal-date ${renewalClass}">${app.renewal_info.formatted_date}</span>
              ${app.renewal_info.is_expired ? 
                '<small class="renewal-status expired">Expired</small>' : 
                (app.renewal_info.is_expiring_soon ? 
                  `<small class="renewal-status expiring-soon">Expires in ${app.renewal_info.days_until_renewal} days</small>` : 
                  '<small class="renewal-status active">Active</small>')}
            </div>
          `;
        }

        const renewButton = (app.renewal_info && (app.renewal_info.is_expired || app.renewal_info.is_expiring_soon)) ?
          `<a href="submit_application.php?type=renew&original_id=${app.id}" class="btn action-btn btn-renew">
            <i class="fas fa-sync-alt"></i> Renew
          </a>` : '';

        return `
          <tr>
            <td data-label="Business Name">
              <div class="app-name">
                <strong>${escapeHtml(app.business_name)}</strong>
                <small>${escapeHtml(app.business_address)}</small>
              </div>
            </td>
            <td data-label="Type of Business">${escapeHtml(app.type_of_business)}</td>
            <td data-label="Status">
              <span class="status-badge status-${statusClass}">
                <i class="fas fa-${statusIcon}"></i>
                ${escapeHtml(app.status.charAt(0).toUpperCase() + app.status.slice(1))}
              </span>
            </td>
            <td>${renewalHtml}</td>
            <td data-label="Date Submitted">
              <span class="date-info">
                ${app.formatted_submitted_date}
                <small>${app.formatted_submitted_time}</small>
              </span>
            </td>
            <td data-label="Actions">
              <div class="action-buttons">
                <a href="view_my_application.php?id=${app.id}" class="btn action-btn btn-view">
                  <i class="fas fa-eye"></i> View
                </a>
                <a href="edit_application.php?id=${app.id}" class="btn action-btn btn-edit">
                  <i class="fas fa-edit"></i> Edit
                </a>
                ${renewButton}
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    // Update active chats
    function updateActiveChats(chats) {
      const chatSection = document.querySelector('.chat-section');
      if (!chats || chats.length === 0) {
        if (chatSection) chatSection.style.display = 'none';
        return;
      }

      if (!chatSection) {
        const header = document.querySelector('.header');
        if (header) {
          const newSection = document.createElement('div');
          newSection.className = 'chat-section';
          header.insertAdjacentElement('afterend', newSection);
          updateActiveChats(chats);
          return;
        }
      }

      chatSection.style.display = 'block';
      const chatList = chatSection.querySelector('.chat-list') || document.createElement('div');
      chatList.className = 'chat-list';
      
      chatList.innerHTML = chats.map(chat => `
        <div class="chat-item">
          <div class="chat-info">
            <div class="chat-status">
              <span class="status-badge status-${chat.status.toLowerCase()}">
                <i class="fas fa-${chat.status === 'Open' ? 'check-circle' : 'clock'}"></i>
                ${escapeHtml(chat.status)}
              </span>
            </div>
            <div class="chat-date">
              Started: ${chat.formatted_date}
            </div>
          </div>
          <div class="chat-actions">
            <a href="applicant_conversation.php?id=${chat.id}" class="btn btn-primary">
              <i class="fas fa-comments"></i> Continue Chat
            </a>
          </div>
        </div>
      `).join('');

      if (!chatSection.querySelector('.chat-list')) {
        const sectionHeader = document.createElement('div');
        sectionHeader.className = 'section-header';
        sectionHeader.innerHTML = `
          <h3><i class="fas fa-comments"></i> Active Live Chats</h3>
          <p>Your ongoing conversations with support staff</p>
        `;
        chatSection.innerHTML = '';
        chatSection.appendChild(sectionHeader);
        chatSection.appendChild(chatList);
      }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Fetch and update dashboard data
    async function updateDashboard() {
      try {
        const response = await fetch('api_dashboard.php?t=' + Date.now());
        const result = await response.json();

        if (result.success && result.data) {
          const data = result.data;

          // Update statistics
          if (data.stats) {
            updateStats(data.stats);
            updateRenewalAlerts(data.stats);
          }

          // Update applications table
          if (data.applications) {
            updateApplicationsTable(data.applications);
          }

          // Update active chats
          if (data.active_chats) {
            updateActiveChats(data.active_chats);
          }

          // Show update indicator
          showUpdateIndicator();
        }
      } catch (error) {
        console.error('Error updating dashboard:', error);
      }
    }

    // Show subtle update indicator
    function showUpdateIndicator() {
      const indicator = document.getElementById('update-indicator');
      if (indicator) {
        indicator.style.opacity = '1';
        setTimeout(() => {
          indicator.style.opacity = '0';
        }, 1000);
      }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Start real-time updates
      updateInterval = setInterval(updateDashboard, UPDATE_INTERVAL_MS);

      // Update immediately on load
      updateDashboard();
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
      if (updateInterval) {
        clearInterval(updateInterval);
      }
    });
</script>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>
