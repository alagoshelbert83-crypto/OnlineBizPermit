<?php
/**
 * Migration Runner
 * Executes database migrations for the OnlineBizPermit system
 */

// Include database connection
require_once 'db.php';

echo "<h1>Database Migration Runner</h1>";
echo "<pre>";

// Check if migration file exists
$migration_file = 'db/migrations/2025-12-10_create_audit_logs_table.sql';

if (!file_exists($migration_file)) {
    echo "❌ Migration file not found: $migration_file\n";
    exit;
}

// Read migration file
$sql = file_get_contents($migration_file);

if (empty($sql)) {
    echo "❌ Migration file is empty\n";
    exit;
}

echo "📄 Found migration file: $migration_file\n";
echo "🔄 Executing migration...\n\n";

// Split SQL into individual statements (by semicolon)
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;

    try {
        $conn->exec($statement);
        echo "✅ Executed: " . substr($statement, 0, 60) . "...\n";
        $success_count++;
    } catch (PDOException $e) {
        echo "❌ Failed: " . substr($statement, 0, 60) . "...\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "\n📊 Migration Summary:\n";
echo "✅ Successful statements: $success_count\n";
echo "❌ Failed statements: $error_count\n";

if ($error_count === 0) {
    echo "\n🎉 Migration completed successfully!\n";
    echo "📋 The audit_logs table has been created and is ready to use.\n";
} else {
    echo "\n⚠️  Migration completed with errors. Please check the output above.\n";
}

echo "</pre>";
?>
