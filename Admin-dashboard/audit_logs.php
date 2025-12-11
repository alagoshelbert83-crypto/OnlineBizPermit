<?php
$page_title = 'Audit Logs';
$current_page = 'audit_logs';

require_once './admin_header.php';

// Get filter parameters
$action_filter = $_GET['action'] ?? '';
$user_role_filter = $_GET['user_role'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($action_filter)) {
    $conditions[] = "action = ?";
    $params[] = $action_filter;
}

if (!empty($user_role_filter)) {
    $conditions[] = "user_role = ?";
    $params[] = $user_role_filter;
}

if (!empty($date_from)) {
    $conditions[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $conditions[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($search)) {
    $conditions[] = "(description ILIKE ? OR ip_address::text ILIKE ? OR user_agent ILIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM audit_logs $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get audit logs with user names
$sql = "
    SELECT
        al.*,
        u.name as user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_clause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter dropdown
$actions_sql = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$actions_stmt = $conn->query($actions_sql);
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

require_once './admin_sidebar.php';
?>

<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-history" style="color: var(--accent-color);"></i>
                    Audit Logs
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Track user activities across the system
                </p>
            </div>
        </div>
    </header>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="action">Action:</label>
                    <select name="action" id="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="user_role">User Role:</label>
                    <select name="user_role" id="user_role">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $user_role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $user_role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="applicant" <?= $user_role_filter === 'applicant' ? 'selected' : '' ?>>Applicant</option>
                        <option value="guest" <?= $user_role_filter === 'guest' ? 'selected' : '' ?>>Guest</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">From Date:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search logs...">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="audit_logs.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <?php
        // Get statistics
        $stats_sql = "
            SELECT
                COUNT(*) as total_logs,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(CASE WHEN created_at >= CURRENT_DATE - INTERVAL '24 hours' THEN 1 END) as logs_today,
                COUNT(CASE WHEN action = 'failed_login' THEN 1 END) as failed_logins
            FROM audit_logs
        ";
        $stats_stmt = $conn->query($stats_sql);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_logs']) ?></h3>
                <p>Total Logs</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['unique_users']) ?></h3>
                <p>Active Users</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['logs_today']) ?></h3>
                <p>Logs Today</p>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['failed_logins']) ?></h3>
                <p>Failed Logins</p>
            </div>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="table-container">
        <div class="table-header">
            <h2>Audit Logs (<?= number_format($total_records) ?> total)</h2>
            <div class="table-actions">
                <button onclick="exportLogs()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audit_logs)): ?>
                        <tr>
                            <td colspan="7" class="no-data">No audit logs found matching your criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($audit_logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="timestamp">
                                        <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                                        <small><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <strong><?= htmlspecialchars($log['user_name']) ?></strong>
                                        <?php if ($log['user_id']): ?>
                                            <br><small>ID: <?= $log['user_id'] ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <em>System</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= strtolower($log['user_role']) ?>">
                                        <?= ucfirst(htmlspecialchars($log['user_role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="action-badge action-<?= strtolower(str_replace(['_', ' '], '-', $log['action'])) ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="description">
                                        <?= htmlspecialchars($log['description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="showLogDetails(<?= $log['id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-link">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-link">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Audit Log Details</h3>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body" id="logDetailsContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<style>
/* Additional styles for audit logs page */
:root {
    --card-bg: #ffffff;
    --bg-color: #f8fafc;
}

.filters-card {
    background: var(--card-bg);
    padding: 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
}

.filters-form {
    width: 100%;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--card-bg);
    padding: 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card.warning {
    border-left: 4px solid #ffc107;
}

.stat-card.warning .stat-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.stat-content p {
    margin: 5px 0 0 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

.table-container {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h2 {
    margin: 0;
    color: var(--text-primary);
}

.table-actions {
    display: flex;
    gap: 10px;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-color);
    font-weight: 600;
    color: var(--text-primary);
    position: sticky;
    top: 0;
}

.data-table tr:hover {
    background: rgba(var(--primary-color-rgb, 74, 105, 189), 0.05);
}

.timestamp {
    font-size: 0.85rem;
    color: var(--text-primary);
}

.timestamp small {
    color: var(--text-secondary);
}

.role-badge,
.action-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
    visibility: visible;
    opacity: 1;
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

.role-admin { background: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; }
.role-staff { background: rgba(40, 167, 69, 0.1) !important; color: #28a745 !important; }
.role-applicant { background: rgba(74, 105, 189, 0.1) !important; color: #4a69bd !important; }
.role-user { background: rgba(74, 105, 189, 0.1) !important; color: #4a69bd !important; }
.role-guest { background: rgba(108, 117, 125, 0.1) !important; color: #6c757d !important; }

.action-login { background: rgba(40, 167, 69, 0.1) !important; color: #28a745 !important; }
.action-logout { background: rgba(108, 117, 125, 0.1) !important; color: #6c757d !important; }
.action-failed-login { background: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; }
.action-view-application { background: rgba(74, 105, 189, 0.1) !important; color: #4a69bd !important; }
.action-send-chat-message { background: rgba(23, 162, 184, 0.1) !important; color: #17a2b8 !important; }

.description {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
    font-style: italic;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid var(--border-color);
}

.page-link {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s;
}

.page-link:hover,
.page-link.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.page-dots {
    padding: 0 5px;
    color: var(--text-secondary);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    visibility: visible;
    opacity: 1;
}

.modal-content {
    background: var(--card-bg, #ffffff);
    margin: 5% auto;
    padding: 0;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 600px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    visibility: visible;
    opacity: 1;
    display: block;
    position: relative;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.modal-close {
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
}

.modal-close:hover {
    color: var(--text-primary);
}

.modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
    visibility: visible;
    opacity: 1;
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) transparent;
}

.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background-color: var(--text-muted);
}

/* Log Details Styles */
.log-details {
    display: flex;
    flex-direction: column;
    gap: 20px;
    visibility: visible;
    opacity: 1;
}

.detail-section {
    background: var(--bg-color, #f8fafc);
    padding: 20px;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    visibility: visible;
    opacity: 1;
    display: block;
}

.detail-section h4 {
    margin: 0 0 15px 0;
    color: var(--text-primary, #1e293b);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    visibility: visible;
    opacity: 1;
    font-weight: 600;
}

.detail-section h4 i {
    color: var(--primary-color, #1e293b);
    visibility: visible;
    opacity: 1;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
    visibility: visible;
    opacity: 1;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: var(--text-primary);
    font-size: 1rem;
    word-break: break-word;
}

.detail-value code {
    background: rgba(59, 130, 246, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--primary-color);
    font-family: 'Courier New', monospace;
}

.metadata-section {
    margin-top: 10px;
}

.metadata-section h4 {
    margin: 0 0 15px 0;
    color: var(--text-primary);
    font-size: 1rem;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}

.metadata-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.metadata-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 10px;
    background: rgba(59, 130, 246, 0.05);
    border-radius: 6px;
    border-left: 3px solid var(--primary-color);
}

.metadata-key {
    font-weight: 600;
    color: var(--text-secondary, #475569);
    font-size: 0.85rem;
    visibility: visible;
    opacity: 1;
}

.metadata-value {
    color: var(--text-primary, #1e293b);
    font-size: 0.95rem;
    word-break: break-word;
    visibility: visible;
    opacity: 1;
}

/* Responsive adjustments for modal */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }

    .detail-grid,
    .metadata-grid {
        grid-template-columns: 1fr;
    }
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .filters-card {
        padding: 15px;
    }

    .filter-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-content h3 {
        font-size: 1.5rem;
    }

    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .data-table th,
    .data-table td {
        padding: 8px 10px;
        font-size: 0.85rem;
    }

    .description {
        max-width: 150px;
    }

    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('logDetailsModal');
    const modalClose = document.querySelector('.modal-close');

    modalClose.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
});

function showLogDetails(logId) {
    const modal = document.getElementById('logDetailsModal');
    const content = document.getElementById('logDetailsContent');

    // Reset modal title
    const modalTitle = document.querySelector('#logDetailsModal .modal-header h3');
    if (modalTitle) {
        modalTitle.textContent = 'Audit Log Details';
    }

    // Show loading state
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 15px;"></i>
            <p><strong>Loading log details...</strong></p>
        </div>
    `;
    modal.style.display = 'block';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';

    // Fetch log details via AJAX
    fetch(`get_log_details.php?id=${logId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch log details');
            }
            return response.json();
        })
        .then(data => {
            // Update modal title with log ID
            const modalTitle = document.querySelector('#logDetailsModal .modal-header h3');
            if (modalTitle) {
                modalTitle.textContent = `Audit Log Details #${data.id}`;
            }

            // Format the date
            const date = new Date(data.created_at);
            const formattedDate = date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZoneName: 'short'
            });

            // Parse user agent for better display
            const userAgent = data.user_agent !== 'N/A' ? data.user_agent : 'Not available';
            
            // Format metadata
            let metadataHtml = '';
            if (data.metadata && Object.keys(data.metadata).length > 0) {
                metadataHtml = '<div class="metadata-section"><h4><i class="fas fa-info-circle"></i> Additional Information</h4><div class="metadata-grid">';
                for (const [key, value] of Object.entries(data.metadata)) {
                    const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    metadataHtml += `
                        <div class="metadata-item">
                            <span class="metadata-key">${formattedKey}:</span>
                            <span class="metadata-value">${typeof value === 'object' ? JSON.stringify(value, null, 2) : value}</span>
                        </div>
                    `;
                }
                metadataHtml += '</div></div>';
            }

            // Build the HTML content
            content.innerHTML = `
                <div class="log-details">
                    <div class="detail-section">
                        <h4><i class="fas fa-user"></i> User Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Name:</span>
                                <span class="detail-value">${data.user.name || 'System'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value">${data.user.email || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">User ID:</span>
                                <span class="detail-value">${data.user.id || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Role:</span>
                                <span class="detail-value"><span class="role-badge role-${data.user.role.toLowerCase()}">${data.user.role.charAt(0).toUpperCase() + data.user.role.slice(1)}</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-tasks"></i> Action Details</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Action:</span>
                                <span class="detail-value"><span class="action-badge action-${data.action.toLowerCase().replace(/[_\s]/g, '-')}">${data.action.replace(/_/g, ' ')}</span></span>
                            </div>
                            <div class="detail-item full-width">
                                <span class="detail-label">Description:</span>
                                <span class="detail-value">${data.description || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-clock"></i> Timestamp</h4>
                        <div class="detail-grid">
                            <div class="detail-item full-width">
                                <span class="detail-label">Date & Time:</span>
                                <span class="detail-value">${formattedDate}</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-network-wired"></i> Network Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">IP Address:</span>
                                <span class="detail-value"><code>${data.ip_address}</code></span>
                            </div>
                            <div class="detail-item full-width">
                                <span class="detail-label">User Agent:</span>
                                <span class="detail-value"><code style="word-break: break-all; font-size: 0.85rem;">${userAgent}</code></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Session ID:</span>
                                <span class="detail-value"><code style="font-size: 0.85rem;">${data.session_id}</code></span>
                            </div>
                        </div>
                    </div>

                    ${metadataHtml}
                </div>
            `;
        })
        .catch(error => {
            console.error('Error:', error);
            // Reset modal title
            const modalTitle = document.querySelector('#logDetailsModal .modal-header h3');
            if (modalTitle) {
                modalTitle.textContent = 'Audit Log Details';
            }
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--error);">
                    <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p><strong>Error loading log details</strong></p>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">${error.message}</p>
                    <button onclick="this.closest('.modal').style.display='none'" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        });
}

function exportLogs() {
    // Create a form to submit the current filters for export
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_audit_logs.php';

    // Copy current filter values
    const inputs = document.querySelectorAll('.filters-form input, .filters-form select');
    inputs.forEach(input => {
        if (input.value) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = input.name;
            hiddenInput.value = input.value;
            form.appendChild(hiddenInput);
        }
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php require_once './admin_footer.php'; ?>
