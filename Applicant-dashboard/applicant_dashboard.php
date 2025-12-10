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
        <div>
            <h1>My Applications</h1>
            <p>Welcome back, <?= htmlspecialchars($current_user_name) ?>! Here's an overview of your application history.</p>
        </div>
        <div class="header-actions">
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

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>
