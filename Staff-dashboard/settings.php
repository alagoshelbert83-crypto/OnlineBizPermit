<?php
$current_page = 'settings';
require_once './staff_header.php'; // Handles session, DB, and auth

// The DB connection is now open from staff_header.php
$userId = $_SESSION['user_id'];
$profile_message = '';
$password_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $profile_message = '<div class="message error">Please provide a valid name and email.</div>';
    } else {
      try {
        $stmt = $conn->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id");
        if ($stmt->execute([':name' => $name, ':email' => $email, ':id' => $userId])) {
          $profile_message = '<div class="message success">Profile updated successfully.</div>';
        } else {
          $profile_message = '<div class="message error">Could not update profile.</div>';
        }
      } catch (PDOException $e) {
        error_log('settings.php update_profile error: ' . $e->getMessage());
        $profile_message = '<div class="message error">An error occurred while updating profile.</div>';
      }
    }
  } elseif (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
      $password_message = '<div class="message error">All password fields are required.</div>';
    } elseif (strlen($new_password) < 8) {
      $password_message = '<div class="message error">New password must be at least 8 characters long.</div>';
    } elseif ($new_password !== $confirm_password) {
      $password_message = '<div class="message error">New passwords do not match.</div>';
    } else {
      try {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['password'])) {
          $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
          $update_stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
          if ($update_stmt->execute([':password' => $hashed_password, ':id' => $userId])) {
            $password_message = '<div class="message success">Password changed successfully.</div>';
          } else {
            $password_message = '<div class="message error">Could not change password.</div>';
          }
        } else {
          $password_message = '<div class="message error">Incorrect current password.</div>';
        }
      } catch (PDOException $e) {
        error_log('settings.php change_password error: ' . $e->getMessage());
        $password_message = '<div class="message error">An error occurred while changing password.</div>';
      }
    }
  }
}

        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

require_once './staff_sidebar.php';
?>
  <style>
    /* Main Content */
    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .main-header h1 { font-size: 28px; font-weight: 700; color: var(--secondary-color); }

    /* Settings */
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
    .settings-card { background: var(--card-bg-color); padding: 30px; border-radius: var(--border-radius); box-shadow: var(--shadow); }
    .settings-card h3 { font-size: 1.5rem; font-weight: 600; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; color: var(--text-secondary-color); margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; transition: all 0.2s ease; }
    .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2); }
    .btn-submit { background: var(--primary-color); color: #fff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s ease; }
    .btn-submit:hover { background: #3b559d; transform: translateY(-2px); }
    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; border: 1px solid transparent; }
    .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

    @media (max-width: 992px) { .settings-grid { grid-template-columns: 1fr; } }
  </style>

    <!-- Main Content -->
    <div class="main">
      <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-cog" style="color: var(--accent-color);"></i>
                    Account Settings
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Manage your account settings and preferences
                </p>
            </div>
        </div>
      </header>
      <div class="settings-grid">
        <div class="settings-card">
          <h3>Profile Information</h3>
          <?= $profile_message ?>
          <form action="settings.php" method="POST">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>
            <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
          </form>
        </div>
        <div class="settings-card">
          <h3>Change Password</h3>
          <?= $password_message ?>
          <form action="settings.php" method="POST">
            <div class="form-group">
              <label for="current_password">Current Password</label>
              <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
              <label for="new_password">New Password</label>
              <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn-submit">Change Password</button>
          </form>
        </div>
      </div>
    </div>

<?php require_once './staff_footer.php'; ?>