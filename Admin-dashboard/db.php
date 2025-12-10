 <?php
/**
 * Database Connection for Admin Dashboard - PostgreSQL (Neon)
 */

// Allow skipping DB connection during build/deploy by setting SKIP_DB_CONNECT=1
if (getenv('SKIP_DB_CONNECT') === '1') {
    // Set a null $conn to avoid undefined variable errors in build scripts
    $conn = null;
    return;
}

// --- Database Configuration for Neon ---
// IMPORTANT: Vercel automatically provides POSTGRES_URL when Neon database is connected
// This connection string includes all connection details

// Try Neon connection string first (provided by Vercel)
// Neon/Vercel provides DATABASE_POSTGRES_URL or DATABASE_URL
$postgresUrl = getenv('DATABASE_POSTGRES_URL') ?: getenv('DATABASE_URL') ?: getenv('POSTGRES_URL');

$queryParams = null;

if ($postgresUrl) {
    // Parse the connection URL
    $parsedUrl = parse_url($postgresUrl);
    
    // Extract connection details from URL
    $host = $parsedUrl['host'] ?? 'localhost';
    $port = $parsedUrl['port'] ?? 5432;
    $dbname = ltrim($parsedUrl['path'] ?? '/postgres', '/');
    $user = $parsedUrl['user'] ?? 'postgres';
    $pass = $parsedUrl['pass'] ?? '';
    
    // For SSL connections (Neon requires SSL)
    // Check if SSL mode is already in the query string
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }
} else {
    // Fallback to individual environment variables (Neon provides these too)
    $host = getenv('DATABASE_PGHOST') ?: getenv('DATABASE_POSTGRES_HOST') ?: getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DATABASE_PGUSER') ?: getenv('DATABASE_POSTGRES_USER') ?: getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DATABASE_PGPASSWORD') ?: getenv('DATABASE_POSTGRES_PASSWORD') ?: getenv('DB_PASS') ?: '';
    $dbname = getenv('DATABASE_PGDATABASE') ?: getenv('DATABASE_POSTGRES_DATABASE') ?: getenv('DB_NAME') ?: 'postgres';
    $port = getenv('DB_PORT') ?: 5432;
}

// --- Establish PostgreSQL Connection ---
try {
    // Build DSN - SSL mode goes as a separate parameter, not in query string
    if (isset($queryParams) && isset($queryParams['sslmode'])) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=" . $queryParams['sslmode'];
    } else {
        // Always require SSL for Neon
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    }
    
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_PERSISTENT => false
    ]);
} catch(PDOException $e) {
    // Use a more generic error in production, but log detailed error
    $error_message = "Database connection failed.";
    error_log("Database connection error: " . $e->getMessage());
    error_log("Attempted DSN: " . (isset($dsn) ? $dsn : 'DSN not set'));
    error_log("Host: $host, Port: $port, Database: $dbname, User: $user");
    // Don't output anything - just log and die silently to prevent header issues
    http_response_code(500);
    if (php_sapi_name() !== 'cli') {
        die($error_message);
    } else {
        die($error_message . "\n");
    }
}

// Include custom session handler for serverless compatibility
require_once __DIR__ . '/../session_handler.php';
?>
