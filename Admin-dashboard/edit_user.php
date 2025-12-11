<?php
// Page-specific variables
$page_title = 'Edit User';
$current_page = 'user_management';

// Include Header
require_once __DIR__ . '/admin_header.php';

// This page should be accessible only by admins
if ($current_user_role !== 'admin') {
    echo "<div class='main'><div class='message error'>You do not have permission to access this page.</div></div>";
    require_once __DIR__ . '/admin_footer.php';
    exit;
}

$message = '';
$user = null;

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: user_management.php');
    exit;
}

// Fetch user details
$stmt = $conn->prepare("SELECT id, name, email, role, is_approved, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: user_management.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = trim($_POST['password']);

    if (empty($name) || empty($email) || empty($role)) {
        $message = '<div class="message error">Name, email, and role are required.</div>';
    } else {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            $message = '<div class="message error">An account with this email already exists.</div>';
        } else {
            // Auto-approve staff and admin roles, keep current approval status for regular users unless changing to staff/admin
            $current_role = $user['role'];
            $new_is_approved = $user['is_approved'];
            
            // If changing to staff or admin, auto-approve
            if (in_array($role, ['staff', 'admin'])) {
                $new_is_approved = 1;
            } elseif ($role === 'user' && in_array($current_role, ['staff', 'admin'])) {
                // If changing from staff/admin to user, keep approval status (or set to pending if not approved)
                // This allows flexibility - admin can decide later
            }
            
            // Update user with or without password
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $message = '<div class="message error">Password must be at least 8 characters long.</div>';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ?, is_approved = ? WHERE id = ?");
                    $message = $updateStmt->execute([$name, $email, $role, $hashedPassword, $new_is_approved, $user_id]) ? '<div class="message success">User updated successfully.' . (in_array($role, ['staff', 'admin']) && $new_is_approved == 1 ? ' Staff/Admin users are automatically approved.' : '') . '</div>' : '<div class="message error">Failed to update user.</div>';
                }
            } else {
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, is_approved = ? WHERE id = ?");
                $message = $updateStmt->execute([$name, $email, $role, $new_is_approved, $user_id]) ? '<div class="message success">User updated successfully.' . (in_array($role, ['staff', 'admin']) && $new_is_approved == 1 ? ' Staff/Admin users are automatically approved.' : '') . '</div>' : '<div class="message error">Failed to update user.</div>';
            }
            
            // Refresh user data after update
            if (strpos($message, 'success') !== false) {
                $stmt = $conn->prepare("SELECT id, name, email, role, is_approved, created_at FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}

// Include Sidebar
require_once __DIR__ . '/admin_sidebar.php';
?>

<!-- Main Content -->
    <div class="main">
      <header class="header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <button id="hamburger"><i class="fas fa-bars"></i></button>
            <h1>Edit User</h1>
        </div>
        <div class="header-actions">
            <a href="user_management.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Users</a>
        </div>
      </header>

    <?php if ($message) echo $message; ?>

      <div class="form-container">
        <form action="edit_user.php?id=<?= $user_id ?>" method="POST" class="user-form">
          <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
            
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>
            
          <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                    <option value="user" <?= ($user['role'] === 'user') ? 'selected' : '' ?>>Applicant</option>
                    <option value="staff" <?= ($user['role'] === 'staff') ? 'selected' : '' ?>>Staff</option>
                    <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
            
            <div class="form-group">
                <label for="password">New Password (leave blank to keep current password)</label>
                <input type="password" id="password" name="password" placeholder="Enter new password">
                <small>Password must be at least 8 characters long. Leave blank to keep current password.</small>
            </div>
            
            <div class="form-group">
                <label>Account Status</label>
                <?php 
                $is_approved = (int)$user['is_approved'];
                $statusText = $is_approved == 1 ? 'Approved' : ($is_approved == 2 ? 'Rejected' : 'Pending');
                $statusClass = $is_approved == 1 ? 'status-active' : ($is_approved == 2 ? 'status-rejected' : 'status-pending');
                ?>
                <div style="padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; background: #f8f9fa;">
                    <span class="status-badge <?= $statusClass ?>" style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                        <?= $statusText ?>
                    </span>
                    <?php if (in_array($user['role'], ['staff', 'admin']) && $is_approved != 1): ?>
                        <small style="display: block; margin-top: 8px; color: #f59e0b;">
                            <i class="fas fa-exclamation-triangle"></i> This <?= $user['role'] ?> user is pending approval. 
                            <a href="user_management.php?action=approve&id=<?= $user_id ?>">Approve now</a>
                        </small>
                    <?php elseif (in_array($user['role'], ['staff', 'admin'])): ?>
                        <small style="display: block; margin-top: 8px; color: #10b981;">
                            <i class="fas fa-check-circle"></i> Staff/Admin users are automatically approved.
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
          <div class="form-group">
                <label>Account Created</label>
                <input type="text" value="<?= date('M d, Y \a\t g:i A', strtotime($user['created_at'])) ?>" readonly>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
                <a href="user_management.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
          </div>
        </form>
      </div>
    </div>

<style>
/* Professional Admin Design System */
:root {
    --sidebar-bg: #232a3b;
    --sidebar-text: #d0d2d6;
    --sidebar-hover-bg: #3c4b64;
    --sidebar-active-bg: #4a69bd; /* Primary color */
    --sidebar-active-text: #fff;

    --admin-bg: #f1f5f9;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --primary: #4a69bd;
    --primary-hover: #3e5aa2;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --border-radius: 0.75rem;
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

.main { margin-left: 80px; transition: margin-left 0.3s ease; }

.form-container {
    max-width: 600px;
    margin: 20px auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.user-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select {
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
}

.form-group small {
    color: #666;
    font-size: 12px;
    margin-top: 4px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.btn-primary {
    background: #007bff;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    text-decoration: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    transition: background-color 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
    text-decoration: none;
}

@media (max-width: 768px) {
    .form-container {
        margin: 10px;
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php
// Include Footer
require_once __DIR__ . '/admin_footer.php';
?>