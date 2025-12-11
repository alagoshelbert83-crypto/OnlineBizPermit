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
$migration_file = 'db/migrations/2025-12-11_alter_documents_table.sql';

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

// Split SQL into individual statements.
// This is more robust than a simple explode(';') as it handles semicolons inside functions or strings.
// It splits by semicolons that are at the end of a line or followed by whitespace and a new line.
$statements = preg_split('/;\s*(\r\n|\n|\r|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY);
$statements = array_filter(array_map('trim', $statements));


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
    echo "📋 The database schema has been updated.\n";
} else {
    echo "\n⚠️  Migration completed with errors. Please check the output above.\n";
}

echo "</pre>";
?>
