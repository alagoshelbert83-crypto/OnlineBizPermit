<?php
// Page-specific variables
$page_title = 'Home';
$current_page = 'home';

// Include Header
require_once __DIR__ . '/applicant_header.php';

// --- Fetch user's most recent application for the status widget ---
$recent_app = null;
try {
    $stmt = $conn->prepare("SELECT id, business_name, status, updated_at FROM applications WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$current_user_id]);
    $recent_app = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching recent application (updated_at may be missing): " . $e->getMessage());
    // Fallback to using submitted_at if updated_at is not available in the schema
    try {
        $stmt = $conn->prepare("SELECT id, business_name, status, submitted_at FROM applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
        $stmt->execute([$current_user_id]);
        $recent_app = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($recent_app && !isset($recent_app['updated_at'])) {
            $recent_app['updated_at'] = $recent_app['submitted_at'] ?? null;
        }
    } catch (PDOException $e2) {
        error_log("Fallback failed fetching recent application: " . $e2->getMessage());
        $recent_app = null;
    }
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
                    <i class="fas fa-home" style="color: var(--accent-color);"></i>
                    Welcome Home
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Welcome, <strong><?= htmlspecialchars($current_user_name ?? 'Applicant') ?></strong>! Here's a quick overview of your account and applications.
                </p>
            </div>
        </div>
    </header>

    <div class="home-grid">
        <!-- Quick Actions -->
        <a href="submit_application.php" class="action-card new-app" style="grid-column: 1 / -1;">
            <div class="action-icon"><i class="fas fa-file-alt"></i></div>
            <div class="action-text">
                <h3>Start New Application</h3>
                <p>Begin the process for a new business permit.</p>
            </div>
            <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
        </a>
        <a href="applicant_dashboard.php?filter=expiring" class="action-card renew-app">
            <div class="action-icon"><i class="fas fa-sync-alt"></i></div>
            <div class="action-text">
                <h3>Renew Existing Permit</h3>
                <p>Renew an expiring or expired business permit.</p>
            </div>
            <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
        </a>
        <a href="applicant_faq.php" class="action-card faq-app">
            <div class="action-icon"><i class="fas fa-question-circle"></i></div>
            <div class="action-text">
                <h3>FAQ Assistant</h3>
                <p>Get instant answers to common questions.</p>
            </div>
            <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
        </a>

        <!-- Latest Application Status -->
        <div class="info-grid">
            <div class="status-card">
                <h4>Latest Application Status</h4>
                <?php if ($recent_app): ?>
                    <?php
                    $status_class = strtolower(preg_replace('/[^a-z]/', '', $recent_app['status']));
                    $status_icon = 'fas fa-info-circle';
                    if (in_array($status_class, ['approved', 'complete'])) {
                        $status_icon = 'fas fa-check-circle';
                    } elseif ($status_class === 'pending') {
                        $status_icon = 'fas fa-hourglass-half';
                    } elseif ($status_class === 'rejected') {
                        $status_icon = 'fas fa-times-circle';
                    } elseif (in_array($status_class, ['review', 'forreview'])) {
                        $status_icon = 'fas fa-search';
                    }
                    ?>
                    <div class="status-content status-<?= $status_class ?>">
                        <div class="status-icon"><i class="<?= $status_icon ?>"></i></div>
                        <div class="status-details">
                            <span class="status-label"><?= htmlspecialchars(ucfirst($recent_app['status'])) ?></span>
                            <p class="status-business-name"><?= htmlspecialchars($recent_app['business_name']) ?></p>
                            <small>Last updated: <?= date('M d, Y', strtotime($recent_app['updated_at'])) ?></small>
                        </div>
                    </div>
                    <a href="view_my_application.php?id=<?= $recent_app['id'] ?>" class="status-link">View Details <i class="fas fa-arrow-right"></i></a>
                <?php else: ?>
                    <div class="status-content no-apps">
                        <div class="status-icon"><i class="fas fa-folder-open"></i></div>
                        <div class="status-details">
                            <p>You have no applications yet.</p>
                            <small>Start a new application to see its status here.</small>
                        </div>
                    </div>
                    <a href="submit_application.php" class="status-link">Start First Application <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    /* --- Enhanced Home Page Styles --- */
    .header h1 { font-size: 1.75rem; font-weight: 700; color: #f4f5f7ff; margin: 0; }
    .header p { color: #64748b; margin-top: 0.25rem; }
    .home-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-top: 1.5rem; }
    .info-grid { grid-column: 1 / -1; }
    .action-card { background: linear-gradient(135deg, #ffffff, #f8fafc); border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: all 0.3s ease; position: relative; }
    .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); border-color: #cbd5e1; }
    .action-icon { font-size: 1.5rem; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0; }
    .action-card.new-app { grid-column: 1 / -1; background: linear-gradient(135deg, #4a69bd, #3c5aa6); color: white; }
    .action-card.new-app .action-icon { background: rgba(255,255,255,0.15); }
    .action-card.new-app .action-text h3 { color: white; }
    .action-card.new-app .action-text p { color: #dbeafe; }
    .action-card.renew-app .action-icon { color: #ca8a04; background: #fef9c3; }
    .action-card.faq-app .action-icon { color: #4f46e5; background: #e0e7ff; }
    .action-text { flex-grow: 1; }
    .action-text h3 { margin: 0 0 0.25rem; font-size: 1.1rem; font-weight: 600; color: #1e293b; }
    .action-text p { margin: 0; color: #64748b; font-size: 0.9rem; }
    .action-arrow { font-size: 1rem; color: #94a3b8; transition: transform 0.3s ease; }
    .action-card:hover .action-arrow { transform: translateX(5px); }
    .action-card.new-app .action-arrow { color: #93c5fd; }
    .status-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
    .status-card h4 { padding: 1rem 1.5rem; margin: 0; font-size: 1.1rem; color: #1e293b; border-bottom: 1px solid #e2e8f0; }
    .status-content { display: flex; align-items: center; gap: 1.25rem; padding: 1.5rem; flex-grow: 1; }
    .status-icon { font-size: 2rem; width: 54px; height: 54px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0; }
    .status-details .status-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.25rem 0.75rem; border-radius: 99px; display: inline-block; margin-bottom: 0.5rem; }
    .status-details .status-business-name { font-size: 1.15rem; font-weight: 600; color: #1e293b; margin: 0; }
    .status-details small { color: #64748b; font-size: 0.875rem; }
    .status-approved .status-icon, .status-complete .status-icon { color: #16a34a; background: #dcfce7; }
    .status-approved .status-label, .status-complete .status-label { color: #15803d; background: #bbf7d0; }
    .status-pending .status-icon { color: #d97706; background: #fef3c7; }
    .status-pending .status-label { color: #b45309; background: #fde68a; }
    .status-rejected .status-icon { color: #dc2626; background: #fee2e2; }
    .status-rejected .status-label { color: #b91c1c; background: #fecaca; }
    .status-review .status-icon, .status-forreview .status-icon { color: #2563eb; background: #dbeafe; }
    .status-review .status-label, .status-forreview .status-label { color: #1d4ed8; background: #bfdbfe; }
    .no-apps .status-icon { color: #64748b; background: #f1f5f9; }
    .no-apps p { font-size: 1.15rem; font-weight: 600; color: #475569; margin: 0; }
    .status-link { display: block; text-align: center; padding: 1rem; background: #f8fafc; border-top: 1px solid #e2e8f0; text-decoration: none; color: #4a69bd; font-weight: 600; border-radius: 0 0 12px 12px; transition: background-color 0.2s ease; }
    .status-link:hover { background-color: #f1f5f9; }
    .status-link i { margin-left: 0.25rem; transition: margin-left 0.2s ease; }
    .status-link:hover i { margin-left: 0.5rem; }
    @media (max-width: 992px) { .home-grid { grid-template-columns: 1fr; } }
</style>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>