<?php
/**
 * Diagnostic script to check application table schema and identify issues
 */

require_once __DIR__ . '/Applicant-dashboard/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Application Table Schema Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4a69bd; color: white; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Application Table Schema Diagnostic</h1>

<?php
if (!$conn) {
    echo "<div class='section error'><h2>❌ Database Connection Failed</h2></div>";
    exit;
}

echo "<div class='section success'><h2>✅ Database Connected</h2></div>";

// Check applications table structure
echo "<div class='section'><h2>1. Applications Table Structure</h2>";
try {
    $stmt = $conn->query("
        SELECT 
            column_name, 
            data_type, 
            character_maximum_length,
            is_nullable,
            column_default
        FROM information_schema.columns 
        WHERE table_name = 'applications' 
        ORDER BY ordinal_position
    ");
    
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "<p class='error'>❌ Applications table does not exist!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Data Type</th><th>Max Length</th><th>Nullable</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['column_name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['character_maximum_length'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($col['is_nullable']) . "</td>";
            echo "<td>" . htmlspecialchars($col['column_default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for required columns
        $required_columns = ['id', 'user_id', 'business_name', 'business_address', 'type_of_business', 'status', 'form_details', 'submitted_at'];
        $existing_columns = array_column($columns, 'column_name');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (!empty($missing_columns)) {
            echo "<p class='error'>❌ Missing required columns: " . implode(', ', $missing_columns) . "</p>";
        } else {
            echo "<p class='success'>✅ All required columns exist</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error checking applications table: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Check documents table structure
echo "<div class='section'><h2>2. Documents Table Structure</h2>";
try {
    $stmt = $conn->query("
        SELECT 
            column_name, 
            data_type, 
            character_maximum_length,
            is_nullable,
            column_default
        FROM information_schema.columns 
        WHERE table_name = 'documents' 
        ORDER BY ordinal_position
    ");
    
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "<p class='error'>❌ Documents table does not exist!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Data Type</th><th>Max Length</th><th>Nullable</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['column_name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['character_maximum_length'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($col['is_nullable']) . "</td>";
            echo "<td>" . htmlspecialchars($col['column_default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error checking documents table: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Check foreign key constraints
echo "<div class='section'><h2>3. Foreign Key Constraints</h2>";
try {
    $stmt = $conn->query("
        SELECT
            tc.table_name, 
            kcu.column_name, 
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name,
            rc.delete_rule
        FROM information_schema.table_constraints AS tc 
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
        JOIN information_schema.referential_constraints AS rc
          ON rc.constraint_name = tc.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY' 
          AND (tc.table_name = 'applications' OR tc.table_name = 'documents')
        ORDER BY tc.table_name, kcu.column_name
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($constraints)) {
        echo "<p class='warning'>⚠️ No foreign key constraints found (this might be okay)</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Table</th><th>Column</th><th>References Table</th><th>References Column</th><th>Delete Rule</th></tr>";
        foreach ($constraints as $fk) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fk['table_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['foreign_table_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['foreign_column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['delete_rule']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error checking foreign keys: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test INSERT query syntax
echo "<div class='section'><h2>4. Test INSERT Query</h2>";
try {
    // Test if we can prepare the statement
    $test_user_id = 1; // Test with a dummy ID
    $test_business_name = 'Test Business';
    $test_business_address = 'Test Address';
    $test_type_of_business = 'Test Type';
    $test_form_details = json_encode(['test' => 'data']);
    
    $stmt = $conn->prepare("
        INSERT INTO applications (user_id, business_name, business_address, type_of_business, status, form_details, submitted_at)
        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");
    
    if ($stmt) {
        echo "<p class='success'>✅ INSERT statement prepared successfully</p>";
        echo "<pre>INSERT INTO applications (user_id, business_name, business_address, type_of_business, status, form_details, submitted_at)
VALUES (?, ?, ?, ?, 'pending', ?, NOW())</pre>";
        
        // Check if user_id exists (foreign key constraint)
        $user_check = $conn->query("SELECT id FROM users LIMIT 1");
        $user_exists = $user_check->fetch(PDO::FETCH_ASSOC);
        if (!$user_exists) {
            echo "<p class='warning'>⚠️ No users found in users table - foreign key constraint will fail</p>";
        } else {
            echo "<p class='success'>✅ Users table has data</p>";
        }
    } else {
        $errorInfo = $conn->errorInfo();
        echo "<p class='error'>❌ Failed to prepare INSERT statement</p>";
        echo "<pre>" . print_r($errorInfo, true) . "</pre>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error testing INSERT: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>SQL State: " . htmlspecialchars($e->getCode()) . "</pre>";
}
echo "</div>";

// Check current user session
echo "<div class='section'><h2>5. Current Session Info</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "\n";
echo "User Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET') . "\n";
echo "</pre>";
echo "</div>";

// Check recent error logs (if accessible)
echo "<div class='section'><h2>6. Recent Application Attempts</h2>";
try {
    $stmt = $conn->query("
        SELECT id, user_id, business_name, status, submitted_at 
        FROM applications 
        ORDER BY submitted_at DESC 
        LIMIT 5
    ");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recent)) {
        echo "<p class='warning'>⚠️ No applications found in database</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>User ID</th><th>Business Name</th><th>Status</th><th>Submitted At</th></tr>";
        foreach ($recent as $app) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($app['id']) . "</td>";
            echo "<td>" . htmlspecialchars($app['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($app['business_name']) . "</td>";
            echo "<td>" . htmlspecialchars($app['status']) . "</td>";
            echo "<td>" . htmlspecialchars($app['submitted_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error fetching recent applications: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

?>

</body>
</html>

