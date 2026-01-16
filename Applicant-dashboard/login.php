<?php
require_once 'db.php';
require_once '../audit_logger.php';
require_once '../Admin-dashboard/functions.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Maintenance Mode Check: Block applicants if maintenance mode is enabled
$maintenance_mode = false;
try {
    $maintenance_mode = (bool)get_setting($conn, 'maintenance_mode', '0');
} catch (Exception $e) {
    // If settings table doesn't exist or there's a DB error, assume maintenance mode is off
    error_log('Maintenance mode check failed: ' . $e->getMessage());
    $maintenance_mode = false;
}
if ($maintenance_mode) {
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

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'user') {
        header("Location: home.php");
        exit;
    } else {
        // User has wrong role for applicant dashboard - destroy session and show login
        session_destroy();
        // Don't redirect - just let them see the login form
    }
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, name, password, role, is_approved FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Check if the user is an applicant (role = 'user')
                    if ($user['role'] === 'user') {
                        // Check if user is approved
                        $is_approved = (int)$user['is_approved'];

                        if ($is_approved === 0) {
                            $error_message = "Your account is pending admin approval. Please wait for approval before logging in.";
                        } elseif ($is_approved === 1) {
                            // Ensure session is active before regenerating the ID
                            if (session_status() !== PHP_SESSION_ACTIVE) {
                                session_start();
                            }
                            // Regenerate session ID to prevent session fixation attacks (guarded)
                            if (function_exists('session_regenerate_id')) {
                                @session_regenerate_id(true);
                            }

                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['role'] = $user['role'];

                            // Log successful login
                            $logger = AuditLogger::getInstance();
                            $logger->logLogin($user['id'], $user['role']);

                            // Redirect to the page they were trying to access, or home
                            if (isset($_SESSION['redirect_after_login'])) {
                                $redirect_url = $_SESSION['redirect_after_login'];
                                unset($_SESSION['redirect_after_login']);
                                header("Location: " . $redirect_url);
                            } else {
                                header("Location: home.php");
                            }
                            exit;
                        } else {
                            $error_message = "Your account has been rejected. Please contact support for more information.";
                        }
                    } else {
                        $error_message = "This login is for applicants only. Please use the appropriate login portal.";
                    }
                } else {
                    $error_message = "Invalid email or password.";
                    // Log failed login attempt
                    $logger = AuditLogger::getInstance();
                    $logger->logFailedLogin($email, 'invalid_password');
                }
            } else {
                $error_message = "Invalid email or password.";
                // Log failed login attempt
                $logger = AuditLogger::getInstance();
                $logger->logFailedLogin($email, 'user_not_found');
            }
        } catch(PDOException $e) {
            $error_message = "Database error occurred. Please try again.";
            error_log("Login database error: " . $e->getMessage());
        }
    }
} else {
    // Check for success messages from registration or password reset
    if (isset($_GET['status'])) {
        switch ($_GET['status']) {
            case 'registered':
                $success_message = "Registration successful! Please log in with your credentials.";
                break;
            case 'pending':
                $success_message = "Registration successful! Your account is pending admin approval. You will be notified once approved.";
                break;
            case 'reset_success':
                $success_message = "Password reset successful! Please log in with your new password.";
                break;
            case 'logout':
                $success_message = "You have been logged out successfully.";
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Login - OnlineBizPermit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="auth_style.css">
    <!-- Firebase SDK -->
    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js';
        import { getAuth } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-auth.js';
        import { getFirestore } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore.js';
        import { getStorage } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-storage.js';
        import { getAnalytics } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-analytics.js';

        // Firebase Configuration
        const firebaseConfig = {
            apiKey: "AIzaSyDPZY7B1BKzNrJRTulWFa0P0t28qlMDzig",
            authDomain: "onlinebizpermit.firebaseapp.com",
            projectId: "onlinebizpermit",
            storageBucket: "onlinebizpermit.firebasestorage.app",
            messagingSenderId: "37215767726",
            appId: "1:37215767726:web:44e68cd75b2628b438b13f",
            measurementId: "G-7RJHQKV7SC"
        };

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const db = getFirestore(app);
        const storage = getStorage(app);
        const analytics = getAnalytics(app);

        // Make services available globally
        window.firebaseAuth = auth;
        window.firebaseDb = db;
        window.firebaseStorage = storage;
        window.firebaseAnalytics = analytics;
    </script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-building"></i>
                    <h1>OnlineBizPermit</h1>
                </div>
                <h2>Applicant Portal</h2>
                <p>Sign in to manage your business permit applications</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <div class="password-input">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($email ?? '') ?>"
                            required
                            autocomplete="email"
                            placeholder="Enter your email address"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="password-input">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" value="1">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>

                                <button type="submit" class="btn btn-primary" id="appLoginBtn">
                                        <span id="appLoginBtnText">
                                            <i class="fas fa-sign-in-alt" id="appLoginIcon"></i>
                                            <span id="appLoginText">Sign In</span>
                                        </span>
                                        <span id="appLoginSpinner" style="display:none;">
                                                <svg width="20" height="20" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg">
                                                    <circle cx="25" cy="25" r="20" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="4"/>
                                                    <circle cx="25" cy="25" r="20" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                                                        <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416;0 31.416" repeatCount="indefinite"/>
                                                        <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416;-31.416" repeatCount="indefinite"/>
                                                    </circle>
                                                </svg>
                                        </span>
                                        <div id="appLoginProgress" class="login-progress" style="display:none;"></div>
                                </button>
                                <script>
                                        (function(){
                                                const form = document.querySelector('form.auth-form');
                                                const btn = document.getElementById('appLoginBtn');
                                                const spinner = document.getElementById('appLoginSpinner');
                                                const txt = document.getElementById('appLoginText');
                                                const progress = document.getElementById('appLoginProgress');
                                                
                                                if (form && btn) {
                                                    form.addEventListener('submit', function(e){
                                                        btn.disabled = true;
                                                        if (spinner) {
                                                            spinner.style.display = 'flex';
                                                            spinner.style.alignItems = 'center';
                                                            spinner.style.justifyContent = 'center';
                                                            spinner.style.gap = '8px';
                                                        }
                                                        if (progress) progress.style.display = 'block';
                                                        
                                                        // Ensure button content is centered
                                                        btn.style.justifyContent = 'center';
                                                        btn.style.alignItems = 'center';
                                                        
                                                        // Add pulsing effect
                                                        btn.style.animation = 'pulse 2s ease-in-out infinite';
                                                    });
                                                }
                                        })();
                                        
                                        // Add pulse animation
                                        const style = document.createElement('style');
                                        style.textContent = `
                                            @keyframes pulse {
                                                0%, 100% { box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3); }
                                                50% { box-shadow: 0 4px 25px rgba(30, 64, 255, 0.6); }
                                            }
                                            .login-progress {
                                                position: absolute;
                                                bottom: 0;
                                                left: 0;
                                                height: 3px;
                                                background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.8), rgba(255,255,255,0.3));
                                                width: 0%;
                                                animation: progressAnimation 2s ease-in-out infinite;
                                            }
                                            @keyframes progressAnimation {
                                                0% { width: 0%; left: 0%; }
                                                50% { width: 70%; left: 15%; }
                                                100% { width: 0%; left: 100%; }
                                            }
                                            #appLoginBtn {
                                                position: relative;
                                                overflow: hidden;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                gap: 8px;
                                            }
                                            #appLoginIcon {
                                                transition: transform 0.3s ease;
                                            }
                                            #appLoginBtn:disabled #appLoginIcon {
                                                transform: scale(0);
                                            }
                                            #appLoginSpinner {
                                                display: none;
                                                align-items: center;
                                                justify-content: center;
                                            }
                                            #appLoginBtn:disabled {
                                                justify-content: center;
                                                align-items: center;
                                            }
                                            #appLoginBtn:disabled #appLoginSpinner {
                                                display: flex;
                                            }
                                            #appLoginBtn:disabled #appLoginBtnText {
                                                display: none;
                                            }
                                            #appLoginBtn:disabled #appLoginSpinner {
                                                display: flex;
                                                align-items: center;
                                                gap: 8px;
                                            }
                                            #appLoginBtn:disabled #appLoginSpinner::after {
                                                content: 'Signing in...';
                                                color: #fff;
                                                font-weight: 600;
                                            }
                                            #appLoginBtn:disabled .login-progress {
                                                display: block;
                                            }
                                        `;
                                        document.head.appendChild(style);
                                </script>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Sign Up here</a></p>
                <div class="divider">

                </div>

            </div>
        </div>

        <div class="auth-info">
            <div class="info-content">
                <h3>Welcome to OnlineBizPermit</h3>
                <p>Your one-stop solution for business permit applications and management.</p>

                <div class="features">
                    <div class="feature">
                        <i class="fas fa-file-alt"></i>
                        <div>
                            <h4>Easy Application</h4>
                            <p>Submit your business permit application online with our streamlined process.</p>
                        </div>
                    </div>

                    <div class="feature">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <h4>Track Progress</h4>
                            <p>Monitor your application status in real-time and receive instant updates.</p>
                        </div>
                    </div>

                    <div class="feature">
                        <i class="fas fa-headset"></i>
                        <div>
                            <h4>24/7 Support</h4>
                            <p>Get help anytime with our intelligent FAQ bot and support team.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 300);
            });
        }, 5000);

        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return;
            }

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }

            // Firebase Analytics: Track login attempt
            if (window.firebaseAnalytics) {
                firebase.analytics().logEvent('login_attempt', {
                    method: 'email_password'
                });
            }
        });

        // Firebase Analytics: Track page view
        if (window.firebaseAnalytics) {
            firebase.analytics().logEvent('page_view', {
                page_title: 'Applicant Login',
                page_location: window.location.href
            });
        }
    </script>
</body>
</html>
