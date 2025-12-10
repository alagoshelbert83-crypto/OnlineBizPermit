<?php
// Page-specific variables
$page_title = 'My Reports';
$current_page = 'reports';

// Include Header
require_once __DIR__ . '/applicant_header.php';

// --- Fetch report data for the logged-in user ---
$report_data = [
    'complete' => 0,
    'pending' => 0,
    'review' => 0,
    'rejected' => 0,
];

try {
    $stmt = $conn->prepare("SELECT status, COUNT(id) as count FROM applications WHERE user_id = ? GROUP BY status");
    $stmt->execute([$current_user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $status = strtolower($row['status']);
        $count = (int)$row['count'];

        if (in_array($status, ['approved', 'complete'])) {
            $report_data['complete'] += $count;
        } elseif (in_array($status, ['review', 'for review'])) {
            $report_data['review'] += $count;
        } elseif ($status === 'pending') {
            $report_data['pending'] += $count;
        } elseif ($status === 'rejected') {
            $report_data['rejected'] += $count;
        }
    }
} catch (PDOException $e) {
    error_log("Failed to fetch applicant report data: " . $e->getMessage());
}


// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <h1>My Reports</h1>
    </header>

    <div class="reports-container">
        <div class="chart-box">
            <h2>My Application Status Overview</h2>
            <div class="chart-canvas-wrapper">
                <?php if (array_sum($report_data) > 0): ?>
                    <canvas id="statusChart"></canvas>
                <?php else: ?>
                    <div class="no-data-placeholder">
                        <i class="fas fa-chart-pie"></i>
                        <p>No application data available to generate reports.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="chart-box">
            <h2>More Reports</h2>
            <div class="no-data-placeholder">
                <i class="fas fa-chart-line"></i>
                <p>More detailed reports will be available here in the future.</p>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom Styles for Reports Page -->
<style>
    .reports-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 30px;
    }
    .chart-box {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
        height: 450px;
        display: flex;
        flex-direction: column;
    }
    .chart-box h2 {
        margin: 0 0 20px 0;
        font-size: 1.2rem;
        color: #232a3b;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    .chart-canvas-wrapper {
        position: relative;
        flex-grow: 1;
    }
    .no-data-placeholder {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
        color: #95a5a6;
        text-align: center;
    }
    .no-data-placeholder i {
        font-size: 60px;
        margin-bottom: 20px;
        color: #ced4da;
    }
    .no-data-placeholder p {
        font-size: 1.1rem;
        font-weight: 600;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportData = <?= json_encode($report_data) ?>;
    const hasData = Object.values(reportData).some(v => v > 0);

    if (hasData && document.getElementById('statusChart')) {
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Complete', 'Pending', 'In Review', 'Rejected'],
                datasets: [{
                    label: 'Applications',
                    data: [reportData.complete, reportData.pending, reportData.review, reportData.rejected],
                    backgroundColor: ['#2ecc71', '#f39c12', '#3498db', '#e74c3c'],
                    borderColor: '#fff',
                    borderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { size: 14 } } } }
            }
        });
    }
});
</script>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>