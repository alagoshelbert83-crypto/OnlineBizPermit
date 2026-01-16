<?php
// db.php must be included first to set up session handler
require_once __DIR__ . '/db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check: Only allow users with the 'user' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    // Redirect to the main login page if not an applicant
    // Store the current URL to redirect back after login
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $_SESSION['redirect_after_login'] = $current_url;

    // Use absolute path to prevent redirect loops
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $login_url = $protocol . '://' . $host . '/Applicant-dashboard/login.php';

    header("Location: " . $login_url);
    exit;
}

// Maintenance Mode Check: Block applicants if maintenance mode is enabled
require_once __DIR__ . '/../Admin-dashboard/functions.php';
$maintenance_mode = (bool)get_setting($conn, 'maintenance_mode', '0');
if ($maintenance_mode && $_SESSION['role'] === 'user') {
    // Show maintenance page for applicants
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance Mode - OnlineBizPermit</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow-x: hidden;
            }

            .maintenance-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 24px;
                box-shadow: 0 25px 50px rgba(0,0,0,0.15);
                padding: 3rem;
                text-align: center;
                max-width: 500px;
                width: 90%;
                position: relative;
                border: 1px solid rgba(255, 255, 255, 0.2);
                animation: slideUp 0.8s ease-out;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .maintenance-icon {
                width: 120px;
                height: 120px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 2rem;
                position: relative;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
                animation: bounce 2s infinite;
            }

            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% {
                    transform: translateY(0);
                }
                40% {
                    transform: translateY(-10px);
                }
                60% {
                    transform: translateY(-5px);
                }
            }

            .maintenance-icon i {
                font-size: 3rem;
                color: white;
            }

            .status-badge {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                color: white;
                padding: 8px 16px;
                border-radius: 50px;
                font-size: 0.9rem;
                font-weight: 600;
                display: inline-block;
                margin-bottom: 1.5rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            }

            h1 {
                color: #1f2937;
                margin-bottom: 1rem;
                font-size: 2.5rem;
                font-weight: 700;
                line-height: 1.2;
            }

            .subtitle {
                color: #6b7280;
                font-size: 1.1rem;
                margin-bottom: 2rem;
                font-weight: 400;
            }

            p {
                color: #4b5563;
                line-height: 1.7;
                margin-bottom: 1.5rem;
                font-size: 1rem;
            }

            .progress-container {
                margin: 2rem 0;
                position: relative;
            }

            .progress-bar {
                width: 100%;
                height: 4px;
                background: #e5e7eb;
                border-radius: 2px;
                overflow: hidden;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                border-radius: 2px;
                animation: progress 2s ease-in-out infinite;
            }

            @keyframes progress {
                0% { width: 0%; }
                50% { width: 70%; }
                100% { width: 100%; }
            }

            .features {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                margin: 2rem 0;
            }

            .feature {
                padding: 1.5rem;
                background: rgba(255, 255, 255, 0.8);
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.5);
            }

            .feature i {
                font-size: 2rem;
                color: #667eea;
                margin-bottom: 1rem;
                display: block;
            }

            .feature h4 {
                color: #1f2937;
                margin-bottom: 0.5rem;
                font-size: 1.1rem;
                font-weight: 600;
            }

            .feature p {
                color: #6b7280;
                font-size: 0.9rem;
                line-height: 1.5;
            }

            .action-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 2rem;
            }

            .btn {
                padding: 12px 24px;
                border-radius: 50px;
                font-weight: 600;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                font-size: 0.95rem;
                border: none;
                cursor: pointer;
            }

            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            }

            .btn-secondary {
                background: rgba(255, 255, 255, 0.9);
                color: #4b5563;
                border: 1px solid #d1d5db;
            }

            .btn-secondary:hover {
                background: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }

            .contact-info {
                margin-top: 2rem;
                padding: 1.5rem;
                background: rgba(102, 126, 234, 0.1);
                border-radius: 12px;
                border-left: 4px solid #667eea;
            }

            .contact-info h4 {
                color: #1f2937;
                margin-bottom: 0.5rem;
                font-size: 1rem;
            }

            .contact-info p {
                color: #4b5563;
                font-size: 0.9rem;
                margin: 0;
            }

            /* Floating elements animation */
            .floating-shapes {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                overflow: hidden;
                pointer-events: none;
                z-index: -1;
            }

            .shape {
                position: absolute;
                opacity: 0.1;
                animation: float 6s ease-in-out infinite;
            }

            .shape:nth-child(1) {
                top: 10%;
                left: 10%;
                animation-delay: 0s;
            }

            .shape:nth-child(2) {
                top: 20%;
                right: 10%;
                animation-delay: 2s;
            }

            .shape:nth-child(3) {
                bottom: 20%;
                left: 15%;
                animation-delay: 4s;
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-20px) rotate(180deg); }
            }

            .shape.circle {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: #667eea;
            }

            .shape.square {
                width: 40px;
                height: 40px;
                background: #764ba2;
                border-radius: 8px;
            }

            .shape.triangle {
                width: 0;
                height: 0;
                border-left: 25px solid transparent;
                border-right: 25px solid transparent;
                border-bottom: 43px solid #f59e0b;
            }

            @media (max-width: 768px) {
                .maintenance-container {
                    padding: 2rem;
                    margin: 1rem;
                }

                h1 {
                    font-size: 2rem;
                }

                .features {
                    grid-template-columns: 1fr;
                    gap: 1rem;
                }

                .action-buttons {
                    flex-direction: column;
                }

                .btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>
    </head>
    <body>
        <div class="floating-shapes">
            <div class="shape circle"></div>
            <div class="shape square"></div>
            <div class="shape triangle"></div>
        </div>

        <div class="maintenance-container">
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>

            <div class="status-badge">Under Maintenance</div>

            <h1>We'll be back soon!</h1>
            <p class="subtitle">Our team is working hard to improve your experience</p>

            <p>We're currently performing scheduled maintenance to bring you an even better OnlineBizPermit platform. This won't take long!</p>

            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>

            <div class="features">
                <div class="feature">
                    <i class="fas fa-clock"></i>
                    <h4>Quick Return</h4>
                    <p>Expected back online within a few hours</p>
                </div>
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <h4>System Updates</h4>
                    <p>Improving security and performance</p>
                </div>
                <div class="feature">
                    <i class="fas fa-star"></i>
                    <h4>Better Experience</h4>
                    <p>New features coming your way</p>
                </div>
            </div>

            <div class="action-buttons">
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
                <button onclick="window.location.reload()" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Check Again
                </button>
            </div>

            <div class="contact-info">
                <h4><i class="fas fa-envelope"></i> Need urgent assistance?</h4>
                <p>Contact our support team at <strong>support@onlinebizpermit.com</strong> or call <strong>(02) 123-4567</strong></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch Current User Info (be defensive: some deployments may lack optional columns)
// CRITICAL: Ensure we're not in a transaction and rollback if any query fails
try {
    // If we're in a transaction, rollback first (shouldn't happen, but be safe)
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log('WARNING: Rolled back transaction before user info query in applicant_header.php');
    }
    
    $stmt = $conn->prepare("SELECT name, email, profile_picture_path FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_user_name = $user_info['name'] ?? 'Applicant';
    $current_user_picture = $user_info['profile_picture_path'] ?? null;
} catch(PDOException $e) {
    // CRITICAL: Rollback if we're in a failed transaction
    if ($conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_e) {
            error_log('Failed to rollback after user info query error: ' . $rollback_e->getMessage());
        }
    }
    
    error_log("Error fetching user info (profile_picture_path may be missing): " . $e->getMessage());
    // Fallback: try without profile_picture_path
    try {
        // Ensure we're not in a transaction for fallback query
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $current_user_name = $user_info['name'] ?? 'Applicant';
        $current_user_picture = null;
    } catch (PDOException $e2) {
        // CRITICAL: Rollback if fallback query also fails
        if ($conn->inTransaction()) {
            try {
                $conn->rollBack();
            } catch (Exception $rollback_e2) {
                error_log('Failed to rollback after fallback query error: ' . $rollback_e2->getMessage());
            }
        }
        error_log("Error fetching user info fallback: " . $e2->getMessage());
        $current_user_name = 'Applicant';
        $current_user_picture = null;
    }
}

// --- Fetch unread notification count for the applicant ---
$unread_notifications_count = 0;
try {
    // CRITICAL: Ensure we're not in a transaction
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log('WARNING: Rolled back transaction before notification count query in applicant_header.php');
    }
    
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->execute([$current_user_id]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications_count = $count_result['unread_count'] ?? 0;
} catch(PDOException $e) {
    // CRITICAL: Rollback if we're in a failed transaction
    if ($conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_e) {
            error_log('Failed to rollback after notification count query error: ' . $rollback_e->getMessage());
        }
    }
    error_log("Error fetching notification count: " . $e->getMessage());
    $unread_notifications_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> - OnlineBizPermit</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="applicant_style.css"> <!-- Main applicant styles -->
  <style>
    /* Topbar & brand styles */
    .topbar { background: #fff; border-bottom: 1px solid var(--border-color); padding: 8px 20px; }
    .header-brand { display: flex; align-items: center; gap: 12px; max-width: 1200px; margin: auto; }
    .site-logo { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 8px; }
    .brand-text { line-height: 1; }
    .brand-title { margin: 0; font-size: 1.05rem; color: var(--text-primary); }
    .brand-sub { color: var(--text-muted); font-size: 0.8rem; }
    .municipal-logo { width: 44px; height: 44px; object-fit: contain; margin-left: auto; display: block; }

    /* Global Responsive Styles for Applicant Dashboard */
    @media (max-width: 768px) {
        /* Header adjustments */
        .header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem;
            padding: 1rem 0;
        }
        .header-left, .header-right {
            width: 100%;
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        /* Table responsive */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            min-width: 600px;
        }
        table th, table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.85rem;
        }
        table td[data-label]::before {
            content: attr(data-label) ": ";
            font-weight: 600;
            display: inline-block;
            min-width: 120px;
        }
        
        /* Form responsive */
        .form-container {
            margin: 10px;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        
        /* Card/Grid responsive */
        .stats-container, .stat-grid, .kpi-grid, .dashboard-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem;
        }
        
        /* Chart responsive */
        .chart-container, .chart-wrapper {
            height: 300px !important;
        }
        
        /* Button groups */
        .action-buttons {
            flex-direction: column;
            gap: 0.5rem;
        }
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        /* Alert responsive */
        .renewal-alerts {
            flex-direction: column;
        }
        .alert {
            flex-direction: column;
            align-items: flex-start;
        }
        
        /* Chat section */
        .chat-section {
            margin: 1rem 0;
        }
        .chat-item {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        .chat-actions {
            width: 100%;
        }
        .chat-actions .btn,
        .chat-actions .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .main {
            padding: 10px !important;
        }
        .header h1 {
            font-size: 1.5rem !important;
        }
        table th, table td {
            padding: 0.5rem 0.25rem;
            font-size: 0.8rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        /* Stack table cells on very small screens */
        table, thead, tbody, th, td, tr {
            display: block;
        }
        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        tr {
            border: 1px solid #ccc;
            margin-bottom: 1rem;
            padding: 0.5rem;
        }
        td {
            border: none;
            position: relative;
            padding-left: 50% !important;
        }
        td::before {
            content: attr(data-label) ": ";
            position: absolute;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: 600;
        }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="header-brand">
      <a href="/" class="site-logo-link" aria-label="OnlineBizPermit home">
        <!-- Inline SVG site logo as fallback -->
        <svg class="site-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="44" height="44" role="img" aria-hidden="false">
          <rect width="48" height="48" rx="8" fill="#1e40af"></rect>
          <text x="24" y="30" text-anchor="middle" font-family="Inter, Arial" font-size="18" fill="#fff" font-weight="700">OBP</text>
        </svg>
      </a>

      <div class="brand-text">
        <h1 class="brand-title">OnlineBizPermit</h1>
        <small class="brand-sub">Municipality of San Miguel, Catanduanes</small>
      </div>

      <img src="/public/applicant/images/San Miguel.png" alt="Municipality of San Miguel Logo" class="municipal-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
    </div>
  </div>

  <div class="wrapper">
