<?php
// Page-specific variables
$page_title = 'Settings';
$current_page = 'settings';

// Include Header
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/functions.php'; // Need this for get/update_setting

$profile_message = '';
$password_message = '';
$system_message = '';

// --- Handle Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    if (empty($name)) {
        $profile_message = '<div class="message error">Name cannot be empty.</div>';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        if ($stmt->execute([$name, $current_user_id])) {
            $profile_message = '<div class="message success">Profile updated successfully.</div>';
            // Update session/page variables to reflect the change immediately
            $_SESSION['name'] = $name;
            $current_user_name = $name;
        } else {
            $profile_message = '<div class="message error">Failed to update profile.</div>';
        }
        header("Location: admin_settings.php"); exit; // Refresh to show updated info and clear POST
    }
}

// --- Handle Password Change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_message = '<div class="message error">All password fields are required.</div>';
    } elseif ($new_password !== $confirm_password) {
        $password_message = '<div class="message error">Passwords do not match.</div>';
    } elseif (strlen($new_password) < 8) {
        $password_message = '<div class="message error">Password must be at least 8 characters long.</div>';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['password'])) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update_stmt->execute([$hashedPassword, $current_user_id])) {
                $password_message = '<div class="message success">Password changed successfully.</div>';
            } else {
                $password_message = '<div class="message error">Could not change password.</div>';
            }
        } else {
            $password_message = '<div class="message error">Incorrect current password.</div>';
        }
    }
}

// --- Handle System Settings Update (Admin Only) ---
if ($current_user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system_settings'])) {
    $settings_to_update = [
        'site_title' => $_POST['site_title'] ?? 'OnlineBizPermit',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'email_notifications_enabled' => isset($_POST['email_notifications_enabled']) ? '1' : '0',
        'payment_instructions' => $_POST['payment_instructions'] ?? ''
    ];

    $all_ok = true;
    foreach ($settings_to_update as $key => $value) {
        if (!update_setting($conn, $key, $value)) {
            $all_ok = false;
        }
    }

    if ($all_ok) {
        $system_message = '<div class="message success">System settings updated successfully.</div>';
    } else {
        $system_message = '<div class="message error">Failed to update one or more system settings.</div>';
    }
}

// --- Fetch System Settings (Admin Only) ---
if ($current_user_role === 'admin') {
    $site_title = get_setting($conn, 'site_title', 'OnlineBizPermit');
    $maintenance_mode = (bool)get_setting($conn, 'maintenance_mode', '0');
    $email_notifications_enabled = (bool)get_setting($conn, 'email_notifications_enabled', '1'); // Default to true
}

// Fetch current user's info for the form fields
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Include Sidebar
require_once __DIR__ . '/admin_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <button id="hamburger" aria-label="Open Menu"><i class="fas fa-bars"></i></button>
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-cog" style="color: var(--accent-color);"></i>
                Settings
            </h1>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                Manage your profile and system settings
            </p>
        </div>
    </header>

    <div class="settings-grid">
        <div class="settings-column">
            <!-- Profile Card -->
            <div class="settings-card">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <?php if ($profile_message) echo $profile_message; ?>
                <form action="settings.php" method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($current_user_name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?= htmlspecialchars($user_info['email'] ?? '') ?>" readonly disabled>
                        <small>Email address cannot be changed.</small>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Security Card -->
            <div class="settings-card">
                <h3><i class="fas fa-shield-alt"></i> Change Password</h3>
                <?php if ($password_message) echo $password_message; ?>
                <form action="settings.php" method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter a new password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm the new password">
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <?php if ($current_user_role === 'admin'): ?>
        <div class="settings-column">
            <!-- System Settings Card -->
            <div class="settings-card">
                <h3><i class="fas fa-cogs"></i> System Settings</h3>
                <?php if ($system_message) echo $system_message; ?>
                <form action="settings.php" method="POST">
                    <div class="form-group">
                        <label for="site_title">Site Title</label>
                        <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($site_title) ?>">
                        <small>The name of the application, displayed in the browser tab and headers.</small>
                    </div>
                    <div class="form-group">
                        <label>Maintenance Mode</label>
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" value="1" <?= $maintenance_mode ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                        <small>When enabled, only admins and staff can access the site.</small>
                    </div>
                    <div class="form-group">
                        <label>Enable Email Notifications</label>
                        <label class="switch">
                            <input type="checkbox" name="email_notifications_enabled" value="1" <?= $email_notifications_enabled ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                        <small>A master switch to turn all outgoing system emails on or off.</small>
                    </div>

                    <div class="form-group">
                        <label for="payment_instructions">Default Payment Instructions (used in approval emails and applicant view)</label>
                        <textarea id="payment_instructions" name="payment_instructions" rows="5" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;"><?= htmlspecialchars(get_setting($conn, 'payment_instructions', '')) ?></textarea>
                        <small>Use HTML for formatting (e.g., lists, bold). Keep instructions concise and include bank or MTO details if needed.</small>
                        <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                            <input type="number" id="preview_application_id" name="preview_application_id" placeholder="Application ID (optional)" style="width:160px;padding:8px;border:1px solid #ddd;border-radius:4px;">
                            <button type="button" id="preview_email_btn" class="btn btn-secondary">Preview Approval Email</button>
                            <small style="margin-left:8px;color:#666">Leave blank to preview with sample data.</small>
                        </div>
                    </div>

                    <button type="submit" name="update_system_settings" class="btn btn-primary">Save System Settings</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* From: settings.php */
/* Sidebar styles copied from Staff dashboard for consistency */
:root {
    --sidebar-bg: #232a3b;
    --sidebar-text: #d0d2d6;
    --sidebar-hover-bg: #3c4b64;
    --sidebar-active-bg: #4a69bd; /* Primary color */
    --sidebar-active-text: #fff;
    --danger: #ef4444;
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
.main { margin-left: 80px; transition: margin-left 0.3s ease; }

.settings-grid {
    display: grid;
    /* Default to a single column layout for mobile first */
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

/* This media query applies only to screens 992px or wider */
@media (min-width: 992px) {
    .settings-grid {
        /* Switch to a two-column layout on larger screens */
        grid-template-columns: 1fr 1fr;
    }
}

.settings-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.settings-card {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    padding: 1.5rem;
}
.settings-card h3 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    font-size: 1.25rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}
.form-group { margin-bottom: 1.25rem; }
.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}
.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    font-size: 1rem;
    background-color: #f8fafc;
}
.form-group input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2);
    outline: none;
    background-color: var(--card-bg);
}
.form-group input[disabled] {
    background-color: #e9ecef;
    cursor: not-allowed;
}
.form-group small {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
    display: block;
}
.btn {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
}

/* Toggle Switch for Maintenance Mode */
.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
}
.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: #0e8388;;
    transition: .4s;
}
input:checked + .slider {
    background-color: var(--primary);
}
input:focus + .slider {
    box-shadow: 0 0 1px var(--primary);
}
input:checked + .slider:before {
    transform: translateX(26px);
}
.slider.round {
    border-radius: 34px;
}
.slider.round:before {
    border-radius: 50%;
}

/* --- Responsive Sidebar CSS --- */
/* Hide hamburger on desktop by default */
@media (min-width: 769px) {
    #hamburger { display: none; }
}

/* Styles for mobile view */
@media (max-width: 768px) {
    .header { display: flex; align-items: center; gap: 1rem; }
    #hamburger {
        display: block;
        background: none; border: none; font-size: 1.5rem;
        cursor: pointer; color: var(--text-primary); z-index: 1001;
    }

    .sidebar {
        position: fixed;
        left: -250px;
        top: 0;
        height: 100vh;
        width: 240px;
        z-index: 1000;
        transition: left 0.3s ease;
        box-shadow: 0 0 20px rgba(0,0,0,0.2);
    }

    .sidebar.active-mobile {
        left: 0;
    }

    .sidebar.active-mobile + .main::before {
        content: '';
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.4); z-index: 999;
    }

    /* Disable hover effect on mobile and enable text visibility when active */
    .sidebar:hover { width: 80px; }
    .sidebar.active-mobile:hover { width: 240px; }
    .sidebar.active-mobile .btn-nav span, .sidebar.active-mobile h2 span { opacity: 1; }
    .sidebar.active-mobile .btn-nav { justify-content: flex-start; }
    .sidebar.active-mobile h2::before { left: 28px; }
}
</style>

<!-- Preview Modal -->
<div id="emailPreviewModal" class="modal" style="display:none; position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);align-items:center;justify-content:center;z-index:2000;">
    <div class="modal-content" style="background:#fff;max-width:850px;width:95%;max-height:90%;overflow:auto;padding:20px;border-radius:6px;position:relative;">
        <button id="closePreviewModal" style="position:absolute;top:10px;right:12px;background:#eee;border:0;padding:6px 10px;border-radius:4px;cursor:pointer;">Close</button>
        <div id="emailPreviewInner">Loading...</div>
    </div>
</div>

<script>
(function(){
    const btn = document.getElementById('preview_email_btn');
    const input = document.getElementById('preview_application_id');
    const modal = document.getElementById('emailPreviewModal');
    const inner = document.getElementById('emailPreviewInner');
    const closeBtn = document.getElementById('closePreviewModal');

    if (btn) {
        btn.addEventListener('click', function(){
            const id = input ? input.value.trim() : '';
            const url = 'preview_approval_email.php' + (id ? '?application_id=' + encodeURIComponent(id) : '');
            btn.disabled = true; btn.textContent = 'Loading...';
            fetch(url, {credentials: 'same-origin'})
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false; btn.textContent = 'Preview Approval Email';
                    if (!data || data.error) {
                        alert(data && data.error ? data.error : 'Could not load preview');
                        return;
                    }
                    inner.innerHTML = data.html || '<em>No preview available</em>';
                    modal.style.display = 'flex';
                }).catch(err => {
                    btn.disabled = false; btn.textContent = 'Preview Approval Email';
                    alert('Preview failed: ' + (err.message || err));
                });
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', function(){ modal.style.display = 'none'; });

    // Close when clicking outside content
    window.addEventListener('click', function(e){ if (e.target === modal) modal.style.display = 'none'; });
})();
</script>

<?php
// Include Footer
require_once __DIR__ . '/admin_footer.php';
?>