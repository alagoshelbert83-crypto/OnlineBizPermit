<?php
/**
 * Test script to verify application insertion works
 * This will help identify the exact error
 */

require_once __DIR__ . '/Applicant-dashboard/db.php';

// Start session to get user info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Application Insert</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; }
        .success { color: green; background: #e6ffe6; padding: 10px; border-radius: 5px; }
        .info { background: #e6f3ff; padding: 10px; border-radius: 5px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test Application Insert</h1>

<?php
if (!$conn) {
    echo "<div class='error'><h2>❌ Database Connection Failed</h2></div>";
    exit;
}

echo "<div class='success'><h2>✅ Database Connected</h2></div>";

// Check session
$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    echo "<div class='error'><h2>❌ No user logged in. Please log in first.</h2></div>";
    exit;
}

echo "<div class='info'><h2>Current User Info</h2>";
echo "<pre>";
echo "User ID: " . $current_user_id . "\n";
echo "User Name: " . ($_SESSION['name'] ?? 'Not set') . "\n";
echo "User Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
echo "</pre></div>";

// Verify user exists in database
try {
    $user_check = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $user_check->execute([$current_user_id]);
    $user = $user_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<div class='error'><h2>❌ User ID {$current_user_id} does not exist in users table!</h2></div>";
        echo "<p>This will cause a foreign key constraint violation.</p>";
        exit;
    }
    
    echo "<div class='success'><h2>✅ User exists in database</h2>";
    echo "<pre>";
    echo "ID: " . $user['id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "</pre></div>";
} catch (PDOException $e) {
    echo "<div class='error'><h2>❌ Error checking user: " . htmlspecialchars($e->getMessage()) . "</h2></div>";
    exit;
}

// Test the INSERT statement
echo "<div class='section'><h2>Testing INSERT Statement</h2>";

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Test data
    $test_business_name = 'Test Business ' . date('Y-m-d H:i:s');
    $test_business_address = 'Test Address';
    $test_type_of_business = 'Test Type';
    $test_form_details = json_encode(['test' => 'data', 'timestamp' => time()]);
    
    echo "<pre>";
    echo "Test Data:\n";
    echo "User ID: {$current_user_id}\n";
    echo "Business Name: {$test_business_name}\n";
    echo "Business Address: {$test_business_address}\n";
    echo "Type of Business: {$test_type_of_business}\n";
    echo "Form Details Length: " . strlen($test_form_details) . " bytes\n";
    echo "</pre>";
    
    // Prepare and execute
    $stmt = $conn->prepare(
        "INSERT INTO applications (user_id, business_name, business_address, type_of_business, status, form_details, submitted_at)
         VALUES (?, ?, ?, ?, 'pending', ?, NOW())"
    );
    
    if (!$stmt) {
        $errorInfo = $conn->errorInfo();
        throw new Exception('Failed to prepare statement: ' . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    echo "<p>✅ Statement prepared successfully</p>";
    
    // Execute
    $execute_result = $stmt->execute([
        $current_user_id, 
        $test_business_name, 
        $test_business_address, 
        $test_type_of_business, 
        $test_form_details
    ]);
    
    if (!$execute_result) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Failed to execute: ' . ($errorInfo[2] ?? 'Unknown error') . ' (SQL State: ' . ($errorInfo[0] ?? 'N/A') . ')');
    }
    
    // Get inserted ID
    $app_id = $conn->lastInsertId();
    
    // Rollback (we don't want to actually insert test data)
    $conn->rollBack();
    
    echo "<div class='success'>";
    echo "<h2>✅ INSERT Test Successful!</h2>";
    echo "<p>The INSERT statement works correctly. The test record was rolled back.</p>";
    echo "<p>Would have inserted Application ID: {$app_id}</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "<div class='error'>";
    echo "<h2>❌ INSERT Test Failed</h2>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>SQL State:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
    
    $errorInfo = $e->errorInfo ?? $conn->errorInfo();
    if ($errorInfo) {
        echo "<pre>";
        echo "Full Error Info:\n";
        print_r($errorInfo);
        echo "</pre>";
    }
    
    // Provide specific guidance based on error
    $error_msg = $e->getMessage();
    $sql_state = $e->getCode();
    
    echo "<h3>Possible Solutions:</h3><ul>";
    
    if (strpos($error_msg, 'column') !== false && strpos($error_msg, 'does not exist') !== false) {
        echo "<li><strong>Missing Column:</strong> One of the required columns doesn't exist. Run the fix_applications_table.sql script in Neon SQL Editor.</li>";
    }
    
    if (strpos($error_msg, 'foreign key') !== false || strpos($error_msg, 'violates foreign key') !== false) {
        echo "<li><strong>Foreign Key Error:</strong> The user_id doesn't exist in users table. Check that user ID {$current_user_id} exists.</li>";
    }
    
    if ($sql_state == '25P02' || strpos($error_msg, 'current transaction is aborted') !== false) {
        echo "<li><strong>Transaction Error:</strong> The connection is in a bad transaction state. This might be a connection pooling issue with Neon.</li>";
    }
    
    if (strpos($error_msg, 'null value') !== false || strpos($error_msg, 'not null') !== false) {
        echo "<li><strong>NOT NULL Constraint:</strong> One of the required fields is NULL. Check that all required fields are being set.</li>";
    }
    
    echo "</ul></div>";
}

echo "</div>";

?>

</body>
</html>

