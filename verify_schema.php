<?php
/**
 * Schema Verification Script
 * Checks if recent migrations have been applied correctly
 */

header('Content-Type: text/plain');

echo "=== DATABASE SCHEMA VERIFICATION ===\n\n";

// Include database connection
require_once 'db.php';

try {
    echo "1. Checking database connection...\n";
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    echo "   ✅ Connection successful\n\n";

    echo "2. Checking tables existence...\n";

    // Check if applications table exists
    $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'applications'");
    $apps_exists = $stmt->fetchColumn() > 0;
    echo "   Applications table: " . ($apps_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";

    // Check if documents table exists
    $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'documents'");
    $docs_exists = $stmt->fetchColumn() > 0;
    echo "   Documents table: " . ($docs_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";

    // Check if users table exists
    $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
    $users_exists = $stmt->fetchColumn() > 0;
    echo "   Users table: " . ($users_exists ? '✅ EXISTS' : '❌ MISSING') . "\n\n";

    if ($docs_exists) {
        echo "3. Checking documents table schema...\n";

        // Check if id column exists (from 2025-12-11 migration)
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'documents' AND column_name = 'id'");
        $id_exists = $stmt->fetchColumn() > 0;
        echo "   ID column (SERIAL PRIMARY KEY): " . ($id_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";

        // Check if application_id column exists
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'documents' AND column_name = 'application_id'");
        $app_id_exists = $stmt->fetchColumn() > 0;
        echo "   Application_ID column: " . ($app_id_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";

        // Check if upload_date column exists
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'documents' AND column_name = 'upload_date'");
        $upload_date_exists = $stmt->fetchColumn() > 0;
        echo "   Upload_date column: " . ($upload_date_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";

        // Check if there's an index on application_id
        $stmt = $conn->query("SELECT COUNT(*) FROM pg_indexes WHERE tablename = 'documents' AND indexname = 'idx_documents_application_id'");
        $index_exists = $stmt->fetchColumn() > 0;
        echo "   Index on application_id: " . ($index_exists ? '✅ EXISTS' : '❌ MISSING') . "\n\n";
    }

    if ($apps_exists) {
        echo "4. Checking applications table schema...\n";

        // Check for updated_at column (from 2025-12-10 migration)
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'applications' AND column_name = 'updated_at'");
        $updated_at_exists = $stmt->fetchColumn() > 0;
        echo "   Updated_at column: " . ($updated_at_exists ? '✅ EXISTS' : '❌ MISSING') . "\n";

        // Check for permit_released_at column
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'applications' AND column_name = 'permit_released_at'");
        $permit_released_exists = $stmt->fetchColumn() > 0;
        echo "   Permit_released_at column: " . ($permit_released_exists ? '✅ EXISTS' : '❌ MISSING') . "\n\n";
    }

    echo "5. Testing transaction handling...\n";

    // Test basic transaction
    $conn->beginTransaction();
    $test_stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'");
    $test_stmt->execute();
    $table_count = $test_stmt->fetchColumn();
    $conn->commit();
    echo "   Basic transaction test: ✅ PASSED (Found $table_count tables)\n";

    // Test rollback
    $conn->beginTransaction();
    $conn->exec("SELECT 1"); // Simple query
    $conn->rollback();
    echo "   Transaction rollback test: ✅ PASSED\n\n";

    echo "6. Checking for potential transaction issues...\n";

    // Check if connection is in a transaction (should be false)
    $in_transaction = $conn->inTransaction();
    echo "   Connection in transaction: " . ($in_transaction ? '⚠️ YES (potential issue)' : '✅ NO') . "\n";

    // Test prepared statement execution
    $stmt = $conn->prepare("SELECT 1 as test_value");
    $result = $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Prepared statement test: " . ($result && $row['test_value'] == 1 ? '✅ PASSED' : '❌ FAILED') . "\n\n";

    echo "=== VERIFICATION COMPLETE ===\n";

    if ($id_exists && $app_id_exists && $upload_date_exists && $index_exists) {
        echo "✅ Recent migrations appear to be applied correctly\n";
    } else {
        echo "⚠️ Some recent migrations may not be applied. Check the migration status.\n";
    }

    if (!$in_transaction) {
        echo "✅ No transaction state issues detected\n";
    } else {
        echo "⚠️ Connection is in an unexpected transaction state\n";
    }

} catch (Exception $e) {
    echo "❌ VERIFICATION FAILED: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
?>
