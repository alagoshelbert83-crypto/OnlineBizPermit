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

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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
}

.role-admin { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.role-staff { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.role-applicant { background: rgba(74, 105, 189, 0.1); color: #4a69bd; }
.role-guest { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

.action-login { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.action-logout { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
.action-failed-login { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.action-view-application { background: rgba(74, 105, 189, 0.1); color: #4a69bd; }
.action-send-chat-message { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }

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
}

.modal-content {
    background: var(--card-bg);
    margin: 5% auto;
    padding: 0;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 600px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
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
    max-height: 400px;
    overflow-y: auto;
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
    // In a real implementation, you would fetch the log details via AJAX
    // For now, we'll show a placeholder
    const modal = document.getElementById('logDetailsModal');
    const content = document.getElementById('logDetailsContent');

    content.innerHTML = `
        <p><strong>Loading log details...</strong></p>
        <p>This feature would show complete log information including metadata, user agent, and session details.</p>
    `;

    modal.style.display = 'block';
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
