<?php
$current_page = 'dashboard';
require_once './staff_header.php';
// --- Fetch data for KPIs ---
$kpi_sql = "SELECT
                COUNT(*) as total_applications,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM applications";
$kpi_stmt = $conn->query($kpi_sql);
$kpis = $kpi_stmt ? $kpi_stmt->fetch(PDO::FETCH_ASSOC) : [];

// --- Fetch recent applications ---
$recent_apps_sql = "SELECT a.id, a.business_name, u.name as applicant_name, a.status, a.submitted_at
                    FROM applications a
                    JOIN users u ON a.user_id = u.id
                    ORDER BY a.submitted_at DESC
                    LIMIT 5";
$recent_apps_stmt = $conn->query($recent_apps_sql);
$recent_applications = $recent_apps_stmt ? $recent_apps_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// --- Fetch data for monthly trend chart ---
$monthly_labels = [];
$monthly_counts = [];
$counts_by_month = [];
for ($i = 5; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i month"));
    $monthly_labels[] = date('M Y', strtotime($month_key));
    $counts_by_month[$month_key] = 0;
}

$monthly_sql = "SELECT to_char(submitted_at, 'YYYY-MM') AS month, COUNT(id) AS count
        FROM applications
        WHERE submitted_at >= (current_date - INTERVAL '6 months')
        GROUP BY month
        ORDER BY month ASC";
$monthly_stmt = $conn->query($monthly_sql);
if ($monthly_stmt) {
  while ($row = $monthly_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($counts_by_month[$row['month']])) {
      $counts_by_month[$row['month']] = (int)$row['count'];
    }
  }
}
$monthly_counts = array_values($counts_by_month);

?>
  <style> /* Additional styles for this page */
    :root { /* Keep variables for consistency */
        --primary-color: #4a69bd;
        --secondary-color: #3c4b64;
        --bg-color: #f0f2f5;
        --card-bg-color: #ffffff;
        --text-color: #343a40;
        --text-secondary-color: #6c757d;
        --border-color: #dee2e6;
        --shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        --border-radius: 12px;
    }
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }

    /* Main Content */
    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .main-header h1 { font-size: 28px; font-weight: 700; color: var(--secondary-color); }

    /* KPI Cards */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .kpi-card { background: var(--card-bg-color); padding: 25px; border-radius: var(--border-radius); box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; transition: transform 0.2s; }
    .kpi-card:hover { transform: translateY(-5px); }
    .kpi-card .icon { 
        font-size: 2.5rem; 
        width: 80px; 
        height: 80px; 
        min-width: 80px; 
        min-height: 80px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 50%; 
        color: #fff; 
        flex-shrink: 0;
    }
    .kpi-card .details h3 { font-size: 2rem; font-weight: 700; }
    .kpi-card .details p { font-size: 0.9rem; color: var(--text-secondary-color); font-weight: 600; text-transform: uppercase; }
    .kpi-card.total .icon { background: #6f42c1; }
    .kpi-card.approved .icon { background: #28a745; }
    .kpi-card.pending .icon { background: #ffc107; }
    .kpi-card.rejected .icon { background: #dc3545; }

    /* Dashboard Content Grid */
    .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .main-chart-container, .recent-activity-container {
        background: var(--card-bg-color);
        padding: 20px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        height: 400px; /* Fixed height */
        display: flex;
        flex-direction: column;
    }
    .main-chart-container h2, .recent-activity-container h2 {
        font-size: 1.2rem;
        margin-bottom: 20px;
        font-weight: 600;
        flex-shrink: 0;
    }
    .chart-wrapper {
        position: relative;
        flex-grow: 1;
    }
    .activity-list {
        overflow-y: auto; /* Make the list scrollable if it exceeds the height */
        flex-grow: 1;
    }

    /* Recent Activity */
    .activity-list .activity-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
    .activity-list .activity-item:last-child { border-bottom: none; }
    .activity-item .activity-icon { font-size: 1.5rem; color: var(--text-secondary-color); }
    .activity-item .activity-details p { margin: 0; font-weight: 500; }
    .activity-item .activity-details span { font-size: 0.85rem; color: var(--text-secondary-color); }
    .status-badge { padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; text-align: center; }
    .status-approved { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .status-pending { background: rgba(255, 193, 7, 0.1); color: #d9a400; }
    .status-rejected { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .dashboard-grid { grid-template-columns: 1fr; }
        .kpi-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    }

    @media (max-width: 768px) {
        .main { padding: 15px; }
        .main-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        .main-header h1 { font-size: 24px; }
        .kpi-grid { grid-template-columns: 1fr; gap: 15px; }
        .kpi-card { padding: 20px; }
        .kpi-card .icon { font-size: 2rem; width: 70px; height: 70px; min-width: 70px; min-height: 70px; }
        .kpi-card .details h3 { font-size: 1.8rem; }
        .dashboard-grid { gap: 15px; }
        .main-chart-container, .recent-activity-container { padding: 15px; height: 350px; }
        .activity-list .activity-item { padding: 10px 0; gap: 10px; }
        .activity-item .activity-icon { font-size: 1.2rem; }
        .activity-item .activity-details p { font-size: 0.9rem; }
        .activity-item .activity-details span { font-size: 0.8rem; }
    }

    @media (max-width: 480px) {
        .main { padding: 10px; }
        .main-header h1 { font-size: 20px; }
        .kpi-card { padding: 15px; }
        .kpi-card .icon { font-size: 1.8rem; width: 60px; height: 60px; min-width: 60px; min-height: 60px; }
        .kpi-card .details h3 { font-size: 1.6rem; }
        .main-chart-container, .recent-activity-container { height: 300px; padding: 12px; }
        .activity-list .activity-item { flex-direction: column; align-items: flex-start; gap: 8px; }
        .activity-item .activity-details { width: 100%; }
        .activity-item .activity-details p { margin-bottom: 4px; }
    }
  </style>

<?php
require_once './staff_sidebar.php';
?>
    <!-- Main Content -->
    <div class="main">
      <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-tachometer-alt" style="color: var(--accent-color);"></i>
                    Welcome, <?= htmlspecialchars($userName) ?>!
                    <span id="update-indicator" style="font-size: 0.6rem; color: #28a745; opacity: 0; transition: opacity 0.3s; margin-left: 10px;">
                      <i class="fas fa-circle"></i> Live
                    </span>
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Here's an overview of your dashboard and pending tasks
                </p>
            </div>
        </div>
      </header>
      
      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card total">
          <div class="icon"><i class="fas fa-folder-open"></i></div>
          <div class="details">
            <h3><?= $kpis['total_applications'] ?? 0 ?></h3>
            <p>Total Applications</p>
          </div>
        </div>
        <div class="kpi-card approved">
          <div class="icon"><i class="fas fa-check-circle"></i></div>
          <div class="details">
            <h3><?= $kpis['approved_count'] ?? 0 ?></h3>
            <p>Approved</p>
          </div>
        </div>
        <div class="kpi-card pending">
          <div class="icon"><i class="fas fa-clock"></i></div>
          <div class="details">
            <h3><?= $kpis['pending_count'] ?? 0 ?></h3>
            <p>Pending</p>
          </div>
        </div>
        <div class="kpi-card rejected">
          <div class="icon"><i class="fas fa-times-circle"></i></div>
          <div class="details">
            <h3><?= $kpis['rejected_count'] ?? 0 ?></h3>
            <p>Rejected</p>
          </div>
        </div>
      </div>

      <!-- Dashboard Content Grid -->
      <div class="dashboard-grid">
        <div class="main-chart-container">
          <h2>Application Trends (Last 6 Months)</h2>
          <div class="chart-wrapper">
            <canvas id="monthlyTrendChart"></canvas>
          </div>
        </div>
        <div class="recent-activity-container">
          <h2>Recent Applications</h2>
          <div class="activity-list">
            <?php if (empty($recent_applications)): ?>
              <p>No recent applications.</p>
            <?php else: ?>
              <?php foreach ($recent_applications as $app): ?>
                <div class="activity-item">
                  <div class="activity-icon"><i class="fas fa-file-alt"></i></div>
                  <div class="activity-details">
                    <p><?= htmlspecialchars($app['business_name']) ?></p>
                    <span><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $app['status'])) ?>"><?= htmlspecialchars(ucfirst($app['status'])) ?></span> &bull; <?= date('M d', strtotime($app['submitted_at'])) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  <script>
    // Real-time Dashboard Updates
    let monthlyChart = null;
    let updateInterval = null;
    const UPDATE_INTERVAL_MS = 5000; // Update every 5 seconds

    // Initialize chart
    function initChart(labels, data) {
      const ctx = document.getElementById('monthlyTrendChart');
      if (monthlyChart) {
        monthlyChart.destroy();
      }
      monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Applications',
                data: data,
                backgroundColor: 'rgba(74, 105, 189, 0.1)',
                borderColor: '#4a69bd',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
      });
    }

    // Update KPI cards
    function updateKPIs(kpis) {
      const kpiElements = {
        'total_applications': document.querySelector('.kpi-card.total .details h3'),
        'approved_count': document.querySelector('.kpi-card.approved .details h3'),
        'pending_count': document.querySelector('.kpi-card.pending .details h3'),
        'rejected_count': document.querySelector('.kpi-card.rejected .details h3')
      };

      if (kpiElements['total_applications']) {
        animateValue(kpiElements['total_applications'], parseInt(kpiElements['total_applications'].textContent) || 0, kpis.total_applications);
      }
      if (kpiElements['approved_count']) {
        animateValue(kpiElements['approved_count'], parseInt(kpiElements['approved_count'].textContent) || 0, kpis.approved_count);
      }
      if (kpiElements['pending_count']) {
        animateValue(kpiElements['pending_count'], parseInt(kpiElements['pending_count'].textContent) || 0, kpis.pending_count);
      }
      if (kpiElements['rejected_count']) {
        animateValue(kpiElements['rejected_count'], parseInt(kpiElements['rejected_count'].textContent) || 0, kpis.rejected_count);
      }
    }

    // Animate number changes
    function animateValue(element, start, end) {
      if (start === end) return;
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

    // Update recent applications list
    function updateRecentApplications(applications) {
      const container = document.querySelector('.activity-list');
      if (!container) return;

      if (applications.length === 0) {
        container.innerHTML = '<p>No recent applications.</p>';
        return;
      }

      container.innerHTML = applications.map(app => `
        <div class="activity-item">
          <div class="activity-icon"><i class="fas fa-file-alt"></i></div>
          <div class="activity-details">
            <p>${escapeHtml(app.business_name)}</p>
            <span><span class="status-badge status-${app.status.toLowerCase().replace(' ', '-')}">${escapeHtml(app.status.charAt(0).toUpperCase() + app.status.slice(1))}</span> &bull; ${app.formatted_date}</span>
          </div>
        </div>
      `).join('');
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

          // Update KPIs
          if (data.kpis) {
            updateKPIs(data.kpis);
          }

          // Update recent applications
          if (data.recent_applications) {
            updateRecentApplications(data.recent_applications);
          }

          // Update chart
          if (data.chart && data.chart.labels && data.chart.data) {
            if (!monthlyChart) {
              initChart(data.chart.labels, data.chart.data);
            } else {
              monthlyChart.data.labels = data.chart.labels;
              monthlyChart.data.datasets[0].data = data.chart.data;
              monthlyChart.update('none'); // 'none' mode for smooth updates
            }
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
      // Initialize chart with initial data
      initChart(<?= json_encode($monthly_labels) ?>, <?= json_encode($monthly_counts) ?>);

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

<?php require_once './staff_footer.php'; ?>
