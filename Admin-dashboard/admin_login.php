<?php
// db.php must be included first to set up session handler
require_once 'db.php';
// Start session AFTER db.php includes session_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    if (in_array($_SESSION['role'], ['admin', 'staff'])) {
        header("Location: ./admin_dashboard.php");
        exit;
    } else {
        // User is logged in but not admin/staff - clear session and show login
        session_unset();
        session_destroy();
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
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password'])) {
                // Check if the user is an admin or staff
                if (in_array($user['role'], ['admin', 'staff'])) {
                    // Ensure session is active before regenerating ID
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }
                    // Regenerate session ID to prevent session fixation attacks (safely)
                    if (function_exists('session_regenerate_id')) {
                        @session_regenerate_id(true);
                    }
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'] ?? 'User';
                    header("Location: ./admin_dashboard.php");
                    exit;
                } else {
                    $error_message = "You do not have permission to access this area.";
                }
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
    }
} else {
    if (isset($_GET['status']) && $_GET['status'] === 'logout') {
        $success_message = "You have been logged out successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - OnlineBizPermit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #c0392b; /* Admin-themed red */
            --primary-hover-color: #a93226;
            --body-bg: #f8f9fa;
            --form-bg: #ffffff;
            --input-border-color: #ced4da;
            --text-color: #212529;
            --text-muted: #6c757d;
            --error-color: #842029;
            --error-bg: #f8d7da;
            --success-color: #0f5132;
            --success-bg: #d1e7dd;
            --border-radius-lg: 1rem;
            --border-radius-md: 0.5rem;
            --font-family: 'Inter', sans-serif;
            --shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: var(--font-family);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: var(--body-bg);
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            display: flex;
            background: var(--form-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 900px;
            max-width: 100%;
        }
        .image {
            flex: 1;
            display: none; /* Hidden on small screens */
            background: #343a40;
        }
        .image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.7;
        }
        .form-box {
            flex: 1;
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        h2 { margin: 0 0 10px; font-size: 24px; text-align: center; }
        .form-subtitle { font-size: 16px; color: var(--text-muted); text-align: center; margin: 0 0 30px; }
        form { display: flex; flex-direction: column; gap: 20px; width: 100%; }
        .input-group { display: flex; flex-direction: column; gap: 5px; }
        .input-group label { font-weight: 600; font-size: 14px; }
        input { padding: 12px 15px; border: 1px solid var(--input-border-color); border-radius: var(--border-radius-md); font-size: 16px; transition: all 0.2s ease; box-sizing: border-box; width: 100%; }
        input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.25); outline: none; }
        button[type="submit"] { padding: 12px; background-color: var(--primary-color); border: none; color: white; border-radius: var(--border-radius-md); font-weight: 600; font-size: 16px; cursor: pointer; transition: background-color 0.2s ease; }
        button[type="submit"]:hover { background-color: var(--primary-hover-color); }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius-md); font-size: 14px; text-align: center; border: 1px solid transparent; }
        .message.success { color: var(--success-color); background-color: var(--success-bg); border-color: var(--success-border-color); }
        .message.error { color: var(--error-color); background-color: var(--error-bg); border-color: var(--error-border-color); }
        .password-wrapper { position: relative; display: flex; align-items: center; }
        .password-wrapper input { padding-right: 50px; }
        .toggle-password { position: absolute; right: 1px; top: 1px; bottom: 1px; width: 45px; cursor: pointer; background: transparent; border: none; display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
        .toggle-password:hover { background-color: #e9ecef; border-radius: 0 var(--border-radius-md) var(--border-radius-md) 0; }
        .links-container { text-align: center; margin-top: 20px; font-size: 14px; }
        .links-container a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .links-container a:hover { text-decoration: underline; color: var(--primary-hover-color); }
        @media (min-width: 768px) { .image { display: block; } }
        @media (max-width: 767px) { .form-box { padding: 30px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="image">
            <img src="logo.png" alt="Login">
        </div>
        <div class="form-box">
            <h2>Admin Portal</h2>
            <p class="form-subtitle">Login to manage the system.</p>

            <?php if (!empty($success_message)): ?>
                <p class='message success'><?= htmlspecialchars($success_message) ?></p>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <p class='message error'><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>

            <form action="admin_login.php" method="POST" novalidate>
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="username" required value="<?= htmlspecialchars($email ?? '') ?>">
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" aria-label="Show password">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                                <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                            </svg>
                            <svg class="icon-eye-slash" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="display: none;">
                                <path d="m10.79 12.912-1.614-1.615a3.5 3.5 0 0 1-4.474-4.474l-2.06-2.06C.938 6.278 0 8 0 8s3 5.5 8 5.5a7.029 7.029 0 0 0 2.79-.588zM5.21 3.088A7.028 7.028 0 0 1 8 2.5c5 0 8 5.5 8 5.5s-.939 1.721-2.641 3.238l-2.062-2.062a3.5 3.5 0 0 0-4.474-4.474L5.21 3.089z"/>
                                <path d="M5.525 7.646a2.5 2.5 0 0 0 2.829 2.829l-2.83-2.829zm4.95.708-2.829-2.83a2.5 2.5 0 0 1 2.829 2.829zm3.171 6-12-12 .708-.708 12 12-.708.708z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span id="loginBtnText">Sign In</span>
                    <span id="loginSpinner" style="display:none; margin-left:8px;">
                        <svg width="18" height="18" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" stroke="#fff">
                          <g fill="none" fill-rule="evenodd">
                            <g transform="translate(1 1)" stroke-width="2">
                              <circle stroke-opacity=".5" cx="22" cy="22" r="6"></circle>
                              <path d="M28 22c0-3.314-2.686-6-6-6">
                                <animateTransform attributeName="transform" type="rotate" from="0 22 22" to="360 22 22" dur="0.9s" repeatCount="indefinite" />
                              </path>
                            </g>
                          </g>
                        </svg>
                    </span>
                </button>
            </form>

            <script>
                (function(){
                    const form = document.querySelector('form[action="admin_login.php"]');
                    const btn = document.getElementById('loginBtn');
                    const spinner = document.getElementById('loginSpinner');
                    const btnText = document.getElementById('loginBtnText');
                    form.addEventListener('submit', function(){
                        btn.disabled = true;
                        btnText.textContent = 'Signing in...';
                        spinner.style.display = 'inline-block';
                    });
                })();
            </script>
            <div class="links-container">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>
        </div>
    </div>
        <script>
        // Defensive toggle-password handler for admin login
        (function () {
            try {
                const toggles = document.querySelectorAll('.toggle-password');
                if (!toggles || toggles.length === 0) return;

                toggles.forEach(toggle => {
                    toggle.addEventListener('click', () => {
                        try {
                            let passwordInput = document.getElementById('password');
                            if (!passwordInput) {
                                const wrapper = toggle.closest('.password-wrapper');
                                passwordInput = wrapper ? wrapper.querySelector('input[type="password"], input[type="text"]') : null;
                            }
                            if (!passwordInput) return;

                            const isPassword = passwordInput.type === 'password';
                            passwordInput.type = isPassword ? 'text' : 'password';

                            const eyeIcon = toggle.querySelector('.icon-eye');
                            const eyeSlashIcon = toggle.querySelector('.icon-eye-slash');
                            if (eyeIcon && eyeSlashIcon) {
                                eyeIcon.style.display = isPassword ? 'none' : 'block';
                                eyeSlashIcon.style.display = isPassword ? 'block' : 'none';
                            }

                            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                        } catch (innerErr) {
                            console.warn('Admin toggle handler inner error:', innerErr);
                        }
                    });
                });
            } catch (err) {
                console.warn('Init admin toggle-password failed:', err);
            }
        })();
        </script>
</body>
</html>
