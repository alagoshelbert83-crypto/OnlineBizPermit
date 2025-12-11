<?php
// Page-specific variables
$page_title = 'Application Management';
$current_page = 'applications';

// Include Header
require_once __DIR__ . '/admin_header.php';

$message = '';

// --- Handle Application Status Update ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $applicationId = (int)$_GET['id'];
    $action = $_GET['action'];
    $new_status = '';

    if ($action === 'approve') {
        $new_status = 'approved';
    } elseif ($action === 'reject') {
        $new_status = 'rejected';
    }

    if (!empty($new_status)) {
        $conn->beginTransaction();
        try {
            // Update status
            $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
            if (!$stmt->execute([$new_status, $applicationId])) {
                throw new Exception("Failed to update application status.");
            }

            // Get user details for notification
            $userStmt = $conn->prepare("SELECT a.user_id, a.business_name, u.name as applicant_name, u.email as applicant_email FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
            $userStmt->execute([$applicationId]);
            $appData = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($appData) {
                $status_text = ($new_status === 'approved') ? 'approved' : 'rejected';
                $notificationMessage = "Your application for '{$appData['business_name']}' has been {$status_text}.";
                $link = "../Applicant-dashboard/view_my_application.php?id={$applicationId}";
                
                $notifyStmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                $notifyStmt->execute([$appData['user_id'], $notificationMessage, $link]);
            }
            
            $conn->commit();
            $message = '<div class="message success">Application status updated successfully. Applicant has been notified.</div>';

        } catch (Exception $e) {
            $conn->rollBack();
            $message = '<div class="message error">Failed to update application status: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- Filters and Search ---
$filter = $_GET['filter'] ?? 'all'; // Default to 'all'
$search_term = trim($_GET['search'] ?? '');

// --- Fetch counts for tabs ---
$counts_sql = "SELECT
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN renewal_status = 'expiring_soon' THEN 1 ELSE 0 END) as expiring,
    SUM(CASE WHEN renewal_status = 'expired' THEN 1 ELSE 0 END) as expired
    FROM applications";
$counts_result = $conn->query($counts_sql)->fetch(PDO::FETCH_ASSOC);

$where_clauses = [];
$params = [];
$types = "";

// Build WHERE clause based on filter and set page heading
$page_heading = 'All Applications';
switch ($filter) {
    case 'expired':
        $where_clauses[] = "a.renewal_status = 'expired'";
        $page_heading = 'Expired Applications';
        break;
    case 'expiring':
        $where_clauses[] = "a.renewal_status = 'expiring_soon'";
        $page_heading = 'Expiring Soon';
        break;
    case 'rejected':
        $where_clauses[] = "a.status = 'rejected'";
        $page_heading = 'Rejected Applications';
        break;
    case 'pending':
        $where_clauses[] = "a.status = 'pending'";
        $page_heading = 'Pending Applications';
        break;
    case 'all':
    default:
        // No specific status filter for 'all'
        break;
}

// Add search term to WHERE clause
if (!empty($search_term)) {
    $where_clauses[] = "(a.business_name LIKE ? OR u.name LIKE ?)";
    $like_term = "%" . $search_term . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "ss";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}


// --- Fetch Applications ---
$applications = [];
$sql = "SELECT a.id, a.business_name, a.business_address, a.submitted_at, a.status, a.renewal_date, a.renewal_status, u.id as user_id, u.name as applicant_name, u.email as applicant_email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        $where_sql
        ORDER BY a.submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include Sidebar
require_once __DIR__ . '/admin_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-alt" style="color: var(--accent-color);"></i>
                    <?= htmlspecialchars($page_heading) ?>
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Manage and review all business permit applications
                </p>
            </div>
        </div>
        <div class="header-right">
            <form action="pending_applications.php" method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by business or applicant..." value="<?= htmlspecialchars($search_term) ?>">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </header>

    <?php if (!empty($message)) echo $message; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=all" class="tab-item <?= ($filter === 'all') ? 'active' : '' ?>">All Applications</a>
        <a href="?filter=pending" class="tab-item <?= ($filter === 'pending') ? 'active' : '' ?>">Pending <span class="count-badge"><?= $counts_result['pending'] ?? 0 ?></span></a>
        <a href="?filter=rejected" class="tab-item <?= ($filter === 'rejected') ? 'active' : '' ?>">Rejected <span class="count-badge"><?= $counts_result['rejected'] ?? 0 ?></span></a>
        <a href="?filter=expiring" class="tab-item <?= ($filter === 'expiring') ? 'active' : '' ?>">Expiring Soon <span class="count-badge"><?= $counts_result['expiring'] ?? 0 ?></span></a>
        <a href="?filter=expired" class="tab-item <?= ($filter === 'expired') ? 'active' : '' ?>">Expired <span class="count-badge"><?= $counts_result['expired'] ?? 0 ?></span></a>
    </div>

    <div class="app-cards-grid">
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Applications Found</h3>
                <p>There are no applications matching the current filter.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <div class="app-card">
                    <div class="card-header">
                        <div class="business-info">
                            <strong><?= htmlspecialchars($app['business_name']) ?></strong>
                            <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($app['business_address'] ?? 'No address') ?></small>
                        </div>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $app['status'])) ?>">
                            <?= htmlspecialchars(ucfirst($app['status'])) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="applicant-info">
                            <div class="user-avatar" style="background-color: #<?= substr(md5($app['applicant_name']), 0, 6) ?>;">
                                <span><?= strtoupper(substr($app['applicant_name'], 0, 1)) ?></span>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($app['applicant_name']) ?></strong>
                                <small><?= htmlspecialchars($app['applicant_email']) ?></small>
                            </div>
                        </div>
                        <div class="date-info">
                            <strong>Submitted:</strong>
                            <span><?= date('M d, Y', strtotime($app['submitted_at'])) ?></span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="view_application.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View</a>
                        <?php if ($app['status'] === 'pending'): ?>
                        <button data-action="approve" data-appid="<?= $app['id'] ?>" class="btn btn-sm btn-success confirm-action-btn"><i class="fas fa-check"></i> Approve</button>
                        <button data-action="reject" data-appid="<?= $app['id'] ?>" class="btn btn-sm btn-danger confirm-action-btn"><i class="fas fa-times"></i> Reject</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalMessage">Are you sure you want to proceed?</p>
        <div class="modal-actions">
            <button id="modalCancelBtn" class="btn btn-secondary">Cancel</button>
            <a href="#" id="modalConfirmBtn" class="btn btn-primary">Confirm</a>
        </div>
    </div>
</div>

<script>
    // This JS is mostly the same, but I'll update the button classes in the modal
    const confirmationModal = document.getElementById('confirmationModal');
    if (confirmationModal) {
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        const closeBtn = confirmationModal.querySelector('.close-btn');

        document.querySelectorAll('.confirm-action-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const action = this.dataset.action;
                const appId = this.dataset.appid;
                const url = `applications.php?action=${action}&id=${appId}&filter=<?= $filter ?>`;

                if (action === 'approve') {
                    modalTitle.textContent = 'Confirm Approval';
                    modalMessage.textContent = 'Are you sure you want to approve this application? The applicant will be notified.';
                    modalConfirmBtn.className = 'btn btn-success';
                    modalConfirmBtn.textContent = 'Approve';
                } else if (action === 'reject') {
                    modalTitle.textContent = 'Confirm Rejection';
                    modalMessage.textContent = 'Are you sure you want to reject this application? The applicant will be notified.';
                    modalConfirmBtn.className = 'btn btn-danger';
                    modalConfirmBtn.textContent = 'Reject';
                }

                modalConfirmBtn.href = url;
                confirmationModal.style.display = 'block';
            });
        });

        const closeModal = () => { confirmationModal.style.display = 'none'; };
        closeBtn.onclick = closeModal;
        modalCancelBtn.onclick = closeModal;
        window.addEventListener('click', (event) => { if (event.target == confirmationModal) { closeModal(); } });
    }
</script>

<style>
/* Professional Admin Design System */
/* Sidebar styles copied from Staff dashboard for consistency */
:root {
    --sidebar-bg: #232a3b;
    --sidebar-text: #d0d2d6;
    --sidebar-hover-bg: #3c4b64;
    --sidebar-active-bg: #4a69bd; /* Primary color */
    --sidebar-active-text: #fff;

    --admin-bg: #f1f5f9;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --primary: #4a69bd;
    --danger: #ef4444;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --border-radius: 0.75rem;
}

/* Sidebar Styles */
.sidebar { width: 80px; background: var(--sidebar-bg); padding: 25px 10px; display: flex; flex-direction: column; justify-content: space-between; color: var(--sidebar-text); flex-shrink: 0; transition: width 0.3s ease; overflow-x: hidden; position: fixed; height: 100%; z-index: 100; }
.sidebar:hover { width: 240px; }
.sidebar h2 { margin-bottom: 35px; position: relative; height: 24px; display: flex; align-items: center; }
.sidebar h2 span { font-size: 18px; font-weight: 700; letter-spacing: 1px; color: #fff; white-space: nowrap; opacity: 0; transition: opacity 0.2s ease 0.1s; margin-left: 52px; }
.sidebar h2::before { content: '\f1ad'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 24px; color: #fff; position: absolute; left: 50%; transform: translateX(-50%); transition: left 0.3s ease; }
.sidebar:hover h2 span { opacity: 1; }
.sidebar:hover h2::before { left: 28px; }
.btn-nav { display: flex; align-items: center; justify-content: center; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; background: transparent; color: var(--sidebar-text); font-weight: 600; transition: all 0.2s ease; position: relative; }
.btn-nav i { min-width: 20px; text-align: center; font-size: 1.1em; flex-shrink: 0; }
.btn-nav span { white-space: nowrap; opacity: 0; max-width: 0; overflow: hidden; transition: opacity 0.1s ease, max-width 0.2s ease 0.1s, margin-left 0.2s ease 0.1s; }
.sidebar:hover .btn-nav { justify-content: flex-start; }
.sidebar:hover .btn-nav span { opacity: 1; max-width: 150px; margin-left: 12px; }
.btn-nav:hover { background: var(--sidebar-hover-bg); color: #fff; }
.btn-nav.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-text); }
.btn-nav.logout { margin-top: 20px; color: #e74c3c; }
.btn-nav.logout:hover { background: #e74c3c; color: #fff; }
.notification-badge { background-color: var(--danger); color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; font-weight: bold; position: absolute; top: 8px; right: 12px; transition: opacity 0.2s, transform 0.2s; transform: scale(1); }
.sidebar:not(:hover) .notification-badge { transform: scale(0.8) translate(8px, -8px); }

/* Main content adjustments for fixed sidebar */
.main { margin-left: 80px; transition: margin-left 0.3s ease; padding: 1.5rem; }

/* Header and Filters */
.header-right { display: flex; align-items: center; gap: 1rem; }
.search-form { position: relative; }
.search-form input { padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.875rem; background-color: var(--card-bg); }
.search-form input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2); outline: none; }
.search-form button { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer; }

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}
.tab-item {
    padding: 0.75rem 1.25rem;
    text-decoration: none;
    color: var(--text-secondary);
    font-weight: 600;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
}
.tab-item:hover {
    background-color: var(--admin-bg);
    color: var(--text-primary);
}
.tab-item.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.count-badge {
    background-color: var(--border-color);
    color: var(--text-secondary);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    margin-left: 8px;
}
.tab-item.active .count-badge {
    background-color: var(--primary);
    color: white;
}

/* Application Cards Grid */
.app-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}
.app-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
}
.app-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}
.card-header {
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--border-color);
}
.business-info strong { display: block; font-size: 1.1rem; color: var(--text-primary); }
.business-info small { color: var(--text-secondary); font-size: 0.875rem; }
.business-info small i { margin-right: 4px; }

.card-body { padding: 1.25rem; flex-grow: 1; display: flex; flex-direction: column; gap: 1rem; }
.applicant-info { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
.applicant-info strong { font-weight: 600; color: var(--text-primary); }
.applicant-info small { color: var(--text-secondary); font-size: 0.875rem; }
.date-info { font-size: 0.875rem; color: var(--text-secondary); }
.date-info strong { color: var(--text-primary); }

.card-footer {
    padding: 1rem 1.25rem;
    background-color: #f8fafc;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Empty State */
.empty-state { text-align: center; padding: 4rem 1rem; color: var(--text-secondary); grid-column: 1 / -1; }
.empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #cbd5e1; }
.empty-state h3 { font-size: 1.25rem; color: var(--text-primary); }

/* Modal Styles */
.modal-content { max-width: 450px; }
.modal-content p { margin-bottom: 1.5rem; color: var(--text-secondary); line-height: 1.6; }
.modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; }
</style>

<?php
// Include Footer
require_once __DIR__ . '/admin_footer.php';
?>