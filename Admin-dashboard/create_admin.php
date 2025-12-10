<?php
require 'db.php';

// Set a default timezone to avoid potential warnings
date_default_timezone_set('UTC');

$adminEmail = "admin@example.com";

// --- Security First: Check if an admin already exists ---
try {
    $checkSql = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
    $row = $conn->query($checkSql)->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "<h1>⚠️ Action Not Needed</h1>";
        echo "<p>An admin account already exists. For security, this script will not create another one.</p>";
        echo "<p>If you need to reset the admin password, please do so directly in the database via your DB admin tool.</p>";
        echo "<p style='color:red; font-weight:bold;'>Please delete this file (<code>create_admin.php</code>) now.</p>";
        exit;
    }

    // --- If no admin exists, proceed to create one ---
    $adminPass  = password_hash("admin123", PASSWORD_DEFAULT);
    $adminName  = "Super Admin";

    $sql = "INSERT INTO users (name, email, password, role, is_approved) VALUES (?, ?, ?, 'admin', 1)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare admin insert statement.');
    }

    if ($stmt->execute([$adminName, $adminEmail, $adminPass])) {
        echo "<h1>✅ Admin Account Created!</h1>";
        echo "<p>Your administrator account has been successfully set up.</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($adminEmail) . "</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<hr>";
        echo "<p style='color:red; font-weight:bold;'>IMPORTANT: For security reasons, please delete this file (<code>create_admin.php</code>) from your server immediately.</p>";
        echo '<a href="admin_login.php">Go to Login Page</a>';
    } else {
        $err = $stmt->errorInfo();
        $errorMessage = (strpos($err[2] ?? '', 'duplicate') !== false)
            ? "An account with the email '" . htmlspecialchars($adminEmail) . "' already exists."
            : "Could not create admin account: " . htmlspecialchars($err[2] ?? 'Unknown error');
        echo "<h1>❌ Error</h1><p>{$errorMessage}</p>";
    }
} catch (Exception $e) {
    echo "<h1>❌ Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
