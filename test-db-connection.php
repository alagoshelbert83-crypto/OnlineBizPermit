<?php
/**
 * Diagnostic file to test database connection and environment variables
 * Access this at: https://onlinebizpermit.onrender.com/test-db-connection.php
 */

header('Content-Type: text/plain');

echo "=== DATABASE CONNECTION DIAGNOSTICS ===\n\n";

// Test 1: Check environment variables
echo "1. Environment Variables:\n";
echo "   DATABASE_POSTGRES_URL: " . (getenv('DATABASE_POSTGRES_URL') ? 'SET' : 'NOT SET') . "\n";
echo "   DATABASE_URL: " . (getenv('DATABASE_URL') ? 'SET' : 'NOT SET') . "\n";
echo "   DATABASE_PGHOST: " . (getenv('DATABASE_PGHOST') ? 'SET (' . getenv('DATABASE_PGHOST') . ')' : 'NOT SET') . "\n";
echo "   DATABASE_POSTGRES_HOST: " . (getenv('DATABASE_POSTGRES_HOST') ? 'SET (' . getenv('DATABASE_POSTGRES_HOST') . ')' : 'NOT SET') . "\n";
echo "   DATABASE_PGUSER: " . (getenv('DATABASE_PGUSER') ? 'SET' : 'NOT SET') . "\n";
echo "   DATABASE_POSTGRES_USER: " . (getenv('DATABASE_POSTGRES_USER') ? 'SET' : 'NOT SET') . "\n";
echo "   DATABASE_PGDATABASE: " . (getenv('DATABASE_PGDATABASE') ? 'SET (' . getenv('DATABASE_PGDATABASE') . ')' : 'NOT SET') . "\n";
echo "   DATABASE_POSTGRES_DATABASE: " . (getenv('DATABASE_POSTGRES_DATABASE') ? 'SET (' . getenv('DATABASE_POSTGRES_DATABASE') . ')' : 'NOT SET') . "\n\n";

// Test 2: Try to get connection string
$postgresUrl = getenv('DATABASE_POSTGRES_URL') ?: getenv('DATABASE_URL') ?: getenv('POSTGRES_URL');
echo "2. Connection URL: " . ($postgresUrl ? "FOUND (length: " . strlen($postgresUrl) . ")" : "NOT FOUND") . "\n\n";

// Test 3: Parse connection string
if ($postgresUrl) {
    echo "3. Parsed Connection Details:\n";
    $parsedUrl = parse_url($postgresUrl);
    echo "   Host: " . ($parsedUrl['host'] ?? 'NOT FOUND') . "\n";
    echo "   Port: " . ($parsedUrl['port'] ?? '5432 (default)') . "\n";
    echo "   Database: " . (isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : 'NOT FOUND') . "\n";
    echo "   User: " . ($parsedUrl['user'] ?? 'NOT FOUND') . "\n";
    echo "   Password: " . (isset($parsedUrl['pass']) ? 'SET (hidden)' : 'NOT SET') . "\n";
    echo "   Query: " . ($parsedUrl['query'] ?? 'NONE') . "\n\n";
    
    // Extract connection details
    $host = $parsedUrl['host'] ?? 'localhost';
    $port = $parsedUrl['port'] ?? 5432;
    $dbname = ltrim($parsedUrl['path'] ?? '/postgres', '/');
    $user = $parsedUrl['user'] ?? 'postgres';
    $pass = $parsedUrl['pass'] ?? '';
    
    // Build DSN with SSL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    echo "4. Connection String (DSN):\n";
    echo "   $dsn\n\n";
    
    // Test 4: Try to connect with SSL
    echo "5. Testing Connection:\n";
    
    // Check if SSL mode is in query string
    $queryParams = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }
    
    // Build DSN with SSL mode
    if (isset($queryParams['sslmode'])) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=" . $queryParams['sslmode'];
    } else {
        // Default to require SSL for Neon
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    }
    
    echo "   DSN: $dsn\n";
    
    try {
        $conn = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        echo "   ✅ Connection successful!\n";
        echo "   PostgreSQL Version: " . $conn->query('SELECT version()')->fetchColumn() . "\n";
        
        // Test query
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'");
        $tableCount = $stmt->fetchColumn();
        echo "   Tables in database: $tableCount\n";
        
        // Test if users table exists
        $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
        $usersTableExists = $stmt->fetchColumn();
        echo "   Users table exists: " . ($usersTableExists > 0 ? 'YES' : 'NO') . "\n";
        
    } catch(PDOException $e) {
        echo "   ❌ Connection failed!\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   Code: " . $e->getCode() . "\n";
    }
} else {
    // Fallback to individual variables
    echo "3. Using Individual Environment Variables:\n";
    $host = getenv('DATABASE_PGHOST') ?: getenv('DATABASE_POSTGRES_HOST') ?: 'localhost';
    $user = getenv('DATABASE_PGUSER') ?: getenv('DATABASE_POSTGRES_USER') ?: 'postgres';
    $pass = getenv('DATABASE_PGPASSWORD') ?: getenv('DATABASE_POSTGRES_PASSWORD') ?: '';
    $dbname = getenv('DATABASE_PGDATABASE') ?: getenv('DATABASE_POSTGRES_DATABASE') ?: 'postgres';
    $port = getenv('DB_PORT') ?: 5432;
    
    echo "   Host: $host\n";
    echo "   User: $user\n";
    echo "   Database: $dbname\n";
    echo "   Port: $port\n\n";
    
    echo "4. Testing Connection:\n";
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        $conn = new PDO($dsn, $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "   ✅ Connection successful!\n";
    } catch(PDOException $e) {
        echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
    }
}

echo "\n=== END DIAGNOSTICS ===\n";
?>

