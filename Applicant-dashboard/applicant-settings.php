<?php
// Page-specific variables
$page_title = 'Settings';
$current_page = 'settings';

// Include Header
require_once __DIR__ . '/applicant_header.php';

$profile_message = '';
$password_message = '';
$notifications_message = '';
$picture_message = '';
$delete_message = '';

// --- Fetch fresh user data ---
// Try to read optional columns, but fall back if the DB schema doesn't include them.
try {
    $stmt = $conn->prepare("SELECT name, email, phone, email_notifications_enabled, profile_picture_path FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log("Failed to fetch user info for settings (optional columns may be missing): " . $e->getMessage());
    // Fallback: query only core columns
    try {
        $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        // Provide sensible defaults for optional fields
        $user_info['email_notifications_enabled'] = $user_info['email_notifications_enabled'] ?? 1;
        $user_info['profile_picture_path'] = $user_info['profile_picture_path'] ?? null;
    } catch (PDOException $e2) {
        error_log("Fallback failed fetching user info: " . $e2->getMessage());
        $user_info = [];
    }
}

// Set defaults if not present
$current_user_name = $user_info['name'] ?? 'Applicant';
$current_user_email = $user_info['email'] ?? '';
$current_user_phone = $user_info['phone'] ?? '';
$email_notifications_enabled = (bool)($user_info['email_notifications_enabled'] ?? true);
$profile_picture_path = $user_info['profile_picture_path'] ?? null;

// --- Handle Profile Picture Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowed_types)) {
            $picture_message = '<div class="message error">Invalid file type. Only JPG, PNG, and GIF are allowed.</div>';
        } elseif ($file['size'] > $max_size) {
            $picture_message = '<div class="message error">File is too large. Maximum size is 2MB.</div>';
        } else {
            $upload_dir = dirname(__DIR__) . '/uploads/profile_pictures/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0775, true); }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $current_user_id . '_' . time() . '.' . $extension;
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                if ($profile_picture_path && file_exists($upload_dir . $profile_picture_path)) { @unlink($upload_dir . $profile_picture_path); }
                try {
                    $stmt = $conn->prepare("UPDATE users SET profile_picture_path = ? WHERE id = ?");
                    if ($stmt->execute([$new_filename, $current_user_id])) {
                        $picture_message = '<div class="message success">Profile picture updated successfully.</div>';
                        $profile_picture_path = $new_filename;
                    } else {
                        $picture_message = '<div class="message error">Failed to update database.</div>';
                        @unlink($upload_dir . $new_filename);
                    }
                } catch (PDOException $e) {
                    error_log("Failed to update profile_picture_path: " . $e->getMessage());
                    $picture_message = '<div class="message error">Failed to update database (profile picture field may be missing).</div>';
                    @unlink($upload_dir . $new_filename);
                }
            } else {
                $picture_message = '<div class="message error">Failed to upload file.</div>';
            }
        }
    } else {
        $picture_message = '<div class="message error">Please select a file to upload.</div>';
    }
}


// --- Handle Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);

    if (empty($name)) {
        $profile_message = '<div class="message error">Name cannot be empty.</div>';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$name, $phone, $current_user_id])) {
            $profile_message = '<div class="message success">Profile updated successfully.</div>';
            // Update session/page variables to reflect the change immediately
            $_SESSION['user_name'] = $name;
            $current_user_name = $name;
            $current_user_phone = $phone;
        } else {
            $profile_message = '<div class="message error">Failed to update profile.</div>';
        }
        // PDO doesn't require explicit statement close
    }
}

// --- Handle Password Change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current password hash
    try {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user_password_hash = $stmt->fetch(PDO::FETCH_ASSOC)['password'] ?? '';
    } catch (PDOException $e) {
        error_log("Failed to fetch user password hash: " . $e->getMessage());
        $user_password_hash = '';
    }

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_message = '<div class="message error">All password fields are required.</div>';
    } elseif (!password_verify($current_password, $user_password_hash)) {
        $password_message = '<div class="message error">Your current password is incorrect.</div>';
    } elseif (strlen($new_password) < 8) {
        $password_message = '<div class="message error">New password must be at least 8 characters long.</div>';
    } elseif ($new_password !== $confirm_password) {
        $password_message = '<div class="message error">The new passwords do not match.</div>';
    } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $current_user_id])) {
            $password_message = '<div class="message success">Password updated successfully.</div>';
        } else {
            $password_message = '<div class="message error">Failed to update password.</div>';
        }
        // PDO statement closed automatically
    }
}

// --- Handle Notification Settings Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("UPDATE users SET email_notifications_enabled = ? WHERE id = ?");
        if ($stmt->execute([$email_notifications, $current_user_id])) {
            $notifications_message = '<div class="message success">Notification settings updated.</div>';
            $email_notifications_enabled = (bool)$email_notifications;
        } else {
            $notifications_message = '<div class="message error">Failed to update notification settings.</div>';
        }
    } catch (PDOException $e) {
        error_log("Failed to update notification setting: " . $e->getMessage());
        $notifications_message = '<div class="message error">Failed to update notification settings (field may be missing).</div>';
    }
}

// --- Handle Account Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    // IMPORTANT: A real implementation would require password confirmation and might soft-delete the user.
    $delete_message = '<div class="message info">Account deletion is a placeholder. In a real app, this would require password confirmation and would deactivate your account.</div>';
}

// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-cog" style="color: var(--accent-color);"></i>
                    Settings
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Manage your account settings and preferences
                </p>
            </div>
        </div>
    </header>

    <div class="settings-grid">
        <!-- Left Column -->
        <div class="settings-column">
            <!-- Profile Card -->
            <div class="settings-card">
                <h3><i class="fas fa-user-edit"></i> My Profile</h3>
                <?php if ($profile_message) echo $profile_message; ?>
                <form action="applicant-settings.php" method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($current_user_name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?= htmlspecialchars($current_user_email) ?>" readonly disabled>
                        <small>Email address cannot be changed.</small>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($current_user_phone) ?>" placeholder="e.g., 09123456789">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Security Card -->
            <div class="settings-card">
                <h3><i class="fas fa-shield-alt"></i> Change Password</h3>
                <?php if ($password_message) echo $password_message; ?>
                <form action="applicant-settings.php" method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter a new password" required>
                        <small>Must be at least 8 characters long.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm the new password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Right Column -->
        <div class="settings-column">
            <!-- Profile Picture Card -->
            <div class="settings-card">
                <h3><i class="fas fa-camera-retro"></i> Profile Picture</h3>
                <?php if ($picture_message) echo $picture_message; ?>
                <form action="applicant-settings.php" method="POST" enctype="multipart/form-data" id="pictureForm">
                    <div class="profile-picture-wrapper">
                        <div class="picture-preview" id="picturePreview">
                            <?php
                            $picture_url = '../uploads/profile_pictures/' . ($profile_picture_path ?? '');
                            if ($profile_picture_path && file_exists($picture_url)): ?>
                                <img src="<?= htmlspecialchars($picture_url) ?>?t=<?= time() ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-placeholder" style="background-color: #<?= substr(md5($current_user_name), 0, 6) ?>;">
                                    <span><?= strtoupper(substr($current_user_name, 0, 1)) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="upload-controls">
                            <label for="profile_picture_input" class="btn btn-secondary">Choose Image</label>
                            <input type="file" id="profile_picture_input" name="profile_picture" accept="image/png, image/jpeg, image/gif" style="display: none;">
                            <p class="file-name-display" id="fileNameDisplay">No file chosen</p>
                            <small>JPG, PNG, or GIF. Max size of 2MB.</small>
                        </div>
                    </div>
                    <button type="submit" name="update_picture" class="btn btn-primary" style="margin-top: 1.5rem; width: 100%;">Upload Picture</button>
                </form>
            </div>

            <!-- Notifications Card -->
            <div class="settings-card">
                <h3><i class="fas fa-bell"></i> Notifications</h3>
                <?php if ($notifications_message) echo $notifications_message; ?>
                <form action="applicant-settings.php" method="POST">
                    <div class="form-group">
                        <label>Email Notifications</label>
                        <label class="switch">
                            <input type="checkbox" name="email_notifications" value="1" <?= $email_notifications_enabled ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                        </label>
                        <small>Receive email updates about your application status and other important news.</small>
                    </div>
                    <button type="submit" name="update_notifications" class="btn btn-primary">Save Preferences</button>
                </form>
            </div>

            <!-- Danger Zone Card -->
            <div class="settings-card danger-zone">
                <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                <?php if ($delete_message) echo $delete_message; ?>
                <p>Deleting your account is a permanent action and cannot be undone. All your applications and data will be removed.</p>
                <form action="applicant-settings.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action is irreversible.');">
                    <button type="submit" name="delete_account" class="btn btn-danger">Delete My Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.settings-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 992px) {
    .settings-grid {
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
.form-group input[type="tel"],
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

/* Toggle Switch for Notifications */
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
    background-color: white;
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

/* Danger Zone */
.danger-zone {
    border-color: var(--danger);
    border-left: 4px solid var(--danger);
}
.danger-zone h3 {
    color: var(--danger);
    border-bottom-color: #fee2e2;
}
.danger-zone p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}
.btn-danger {
    background-color: var(--danger);
    color: white;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    border: none;
    padding: 0.75rem 1.5rem;
}
.btn-danger:hover {
    background-color: #dc2626;
}

/* Profile Picture Upload */
.profile-picture-wrapper {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.picture-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    background-color: #e9ecef;
    flex-shrink: 0;
}
.picture-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 700;
    color: white;
}
.upload-controls {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.file-name-display {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-style: italic;
    margin: 0;
}
</style>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('profile_picture_input');
    const picturePreview = document.getElementById('picturePreview');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const originalContent = picturePreview.innerHTML;

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) { // 2MB
                    alert('File is too large. Please select a file smaller than 2MB.');
                    this.value = ''; // Reset file input
                    picturePreview.innerHTML = originalContent;
                    fileNameDisplay.textContent = 'No file chosen';
                    return;
                }
                if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                    alert('Invalid file type. Please select a JPG, PNG, or GIF.');
                    this.value = ''; // Reset file input
                    picturePreview.innerHTML = originalContent;
                    fileNameDisplay.textContent = 'No file chosen';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    picturePreview.innerHTML = `<img src="${e.target.result}" alt="New Profile Picture Preview">`;
                }
                reader.readAsDataURL(file);
                fileNameDisplay.textContent = file.name;
            }
        });
    }
});
</script>