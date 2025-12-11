<?php
$page_title = 'My Activity';
$current_page = 'audit_logs';

require_once './staff_header.php';

// Get filter parameters
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($action_filter)) {
    $conditions[] = "action = ?";
    $params[] = $action_filter;
}

if (!empty($date_from)) {
    $conditions[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $conditions[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

// Always filter by current user
$conditions[] = "user_id = ?";
$params[] = $_SESSION['user_id'];

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM audit_logs $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get audit logs
$sql = "
    SELECT * FROM audit_logs
    $where_clause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter dropdown
$actions_sql = "SELECT DISTINCT action FROM audit_logs WHERE user_id = ? ORDER BY action";
$actions_stmt = $conn->prepare($actions_sql);
$actions_stmt->execute([$_SESSION['user_id']]);
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

require_once './staff_sidebar.php';
?>

<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-history" style="color: var(--accent-color);"></i>
                    My Activity Log
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Track your recent activities and interactions
                </p>
            </div>
        </div>
    </header>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="action">Activity Type:</label>
                    <select name="action" id="action">
                        <option value="">All Activities</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars(str_replace('_', ' ', $action)) ?>
                            </option>
                        <?php endforeach; ?>
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

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="staff_audit_logs.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <?php
        // Get statistics for current user
        $stats_sql = "
            SELECT
                COUNT(*) as total_activities,
                COUNT(CASE WHEN created_at >= CURRENT_DATE - INTERVAL '7 days' THEN 1 END) as this_week,
                COUNT(CASE WHEN action = 'send_chat_message' THEN 1 END) as chat_messages,
                COUNT(CASE WHEN action = 'change_application_status' THEN 1 END) as status_changes
            FROM audit_logs
            WHERE user_id = ?
        ";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->execute([$_SESSION['user_id']]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_activities']) ?></h3>
                <p>Total Activities</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['this_week']) ?></h3>
                <p>This Week</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['chat_messages']) ?></h3>
                <p>Chat Messages</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['status_changes']) ?></h3>
                <p>Status Changes</p>
            </div>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="table-container">
        <div class="table-header">
            <h2>Recent Activities (<?= number_format($total_records) ?> total)</h2>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Activity</th>
                        <th>Description</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audit_logs)): ?>
                        <tr>
                            <td colspan="4" class="no-data">No activities found matching your criteria.</td>
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
                                    <?php if (!empty($log['metadata'])): ?>
                                        <button class="btn btn-sm btn-info" onclick="showDetails(<?= $log['id'] ?>)">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    <?php else: ?>
                                        <em>No additional details</em>
                                    <?php endif; ?>
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

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Activity Details</h3>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body" id="detailsContent">
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

.action-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.action-login { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.action-logout { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
.action-send-chat-message { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
.action-change-application-status { background: rgba(255, 193, 7, 0.1); color: #d9a400; }

.description {
    max-width: 400px;
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
    const modal = document.getElementById('detailsModal');
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

function showDetails(logId) {
    // In a real implementation, you would fetch the log details via AJAX
    // For now, we'll show a placeholder
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');

    content.innerHTML = `
        <p><strong>Loading activity details...</strong></p>
        <p>This feature shows complete information about your activity including timestamps, IP addresses, and additional metadata.</p>
    `;

    modal.style.display = 'block';
}
</script>

<?php require_once './staff_footer.php'; ?>
