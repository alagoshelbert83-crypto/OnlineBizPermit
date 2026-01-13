<?php
// Page variables
$page_title = 'Payment Records';
$current_page = 'payment_records';

require_once __DIR__ . '/staff_header.php'; // Handles session and auth

// Build search / filter / pagination parameters
$payments = [];
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(CAST(p.application_id AS TEXT) ILIKE ? OR u.name ILIKE ? OR p.or_number ILIKE ? OR a.business_name ILIKE ? )";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status_filter !== '') {
    $where[] = "p.status = ?";
    $params[] = $status_filter;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
try {
    // Count total
    $count_sql = "SELECT COUNT(*) FROM payments p LEFT JOIN applications a ON p.application_id = a.id LEFT JOIN users u ON a.user_id = u.id LEFT JOIN documents d ON p.document_id = d.id " . $where_sql;
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, (int)ceil($total / $per_page));
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT p.id, p.application_id, p.amount, p.or_number, p.status, p.created_at, p.verified_at, p.verified_by, p.uploaded_by,
        a.business_name, u.name as applicant_name, d.file_path, d.document_name
        FROM payments p
        LEFT JOIN applications a ON p.application_id = a.id
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN documents d ON p.document_id = d.id
        " . $where_sql . "
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $execParams = $params;
    $execParams[] = $per_page;
    $execParams[] = $offset;
    $stmt->execute($execParams);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Failed to fetch payments: ' . $e->getMessage());
    $payments = [];
    $total = 0; $total_pages = 1;
}

require_once __DIR__ . '/staff_sidebar.php';
?>

<div class="main">
    <header class="main-header">
        <h1>Payment Records</h1>
        <p class="muted">List of uploaded receipts and their verification status.</p>
    </header>

    <div class="card">
        <form method="GET" class="filter-form" style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
            <input type="text" name="search" placeholder="Search application ID, applicant or OR#" value="<?= htmlspecialchars($search) ?>" style="padding:8px;border:1px solid #e6e9ef;border-radius:6px;">
            <select name="status" style="padding:8px;border:1px solid #e6e9ef;border-radius:6px;">
                <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Any status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="verified" <?= $status_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select name="per_page" style="padding:8px;border:1px solid #e6e9ef;border-radius:6px;">
                <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
            </select>
            <button type="submit" class="btn" style="padding:8px 12px;">Filter</button>
        </form>

        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <p>No payment records found.</p>
            </div>
        <?php else: ?>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Amount</th>
                        <th>OR #</th>
                        <th>Status</th>
                        <th>Uploaded At</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['id']) ?></td>
                        <td><a href="view_application.php?id=<?= htmlspecialchars($p['application_id']) ?>">#<?= htmlspecialchars($p['application_id']) ?> <?= htmlspecialchars($p['business_name']) ?></a></td>
                        <td><?= htmlspecialchars($p['applicant_name']) ?></td>
                        <td><?= $p['amount'] !== null ? '₱ ' . number_format((float)$p['amount'],2) : '-' ?></td>
                        <td><?= htmlspecialchars($p['or_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(ucfirst($p['status'])) ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($p['created_at']))) ?></td>
                        <td>
                            <?php if (!empty($p['file_path']) && file_exists(__DIR__ . '/../uploads/' . $p['file_path'])): ?>
                                <a href="../uploads/<?= rawurlencode($p['file_path']) ?>" target="_blank" rel="noopener">View</a>
                            <?php elseif (!empty($p['file_path'])): ?>
                                <a href="../uploads/<?= rawurlencode($p['file_path']) ?>" target="_blank" rel="noopener">View (may be missing)</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" style="margin-top:14px;">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $p]))) ?>" class="btn" style="padding:6px 10px;margin-right:4px;<?= $p == $page ? 'background:#4a69bd;color:#fff;' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.payments-table { width:100%; border-collapse: collapse; }
.payments-table th, .payments-table td { padding: 10px; border-bottom: 1px solid #e6e9ef; text-align:left; }
.payments-table th { background: #f8fafc; }
.card { background:#fff; padding:20px; border-radius:8px; box-shadow:0 6px 20px rgba(25,25,25,0.04); }
.empty-state { padding: 30px; text-align:center; color:#6b7280; }
.muted { color:#6b7280; }
</style>