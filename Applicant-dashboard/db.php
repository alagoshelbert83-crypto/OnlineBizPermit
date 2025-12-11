<?php
/**
 * Database Connection for Applicant Dashboard - PostgreSQL (Neon)
 * FIXED: Handles transaction cleanup and prevents transaction state pollution
 */

// Allow skipping DB connection during build/deploy by setting SKIP_DB_CONNECT=1
if (getenv('SKIP_DB_CONNECT') === '1') {
    $conn = null;
    return;
}

// --- Database Configuration for Neon ---
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
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }
} else {
    // Fallback to individual environment variables
    $host = getenv('DATABASE_PGHOST') ?: getenv('DATABASE_POSTGRES_HOST') ?: getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DATABASE_PGUSER') ?: getenv('DATABASE_POSTGRES_USER') ?: getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DATABASE_PGPASSWORD') ?: getenv('DATABASE_POSTGRES_PASSWORD') ?: getenv('DB_PASS') ?: '';
    $dbname = getenv('DATABASE_PGDATABASE') ?: getenv('DATABASE_POSTGRES_DATABASE') ?: getenv('DB_NAME') ?: 'postgres';
    $port = getenv('DB_PORT') ?: 5432;
}

// --- Establish PostgreSQL Connection ---
try {
    // Build DSN
    if (isset($queryParams) && isset($queryParams['sslmode'])) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=" . $queryParams['sslmode'];
    } else {
        // Always require SSL for Neon
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    }
    
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
        // CRITICAL: Disable persistent connections to avoid transaction pollution
        PDO::ATTR_PERSISTENT => false
    ]);
    
    // CRITICAL FIX: Clean up any uncommitted transactions from previous failed requests
    // This prevents "current transaction is aborted" errors
    if ($conn->inTransaction()) {
        try {
            $conn->rollBack();
            error_log('WARNING: Rolled back uncommitted transaction at connection start');
        } catch (PDOException $e) {
            // If rollback fails, the connection is in a bad state - log but don't die
            error_log('WARNING: Could not rollback existing transaction: ' . $e->getMessage());
        }
    }
    
} catch(PDOException $e) {
    $error_message = "Database connection failed.";
    error_log("Database connection error: " . $e->getMessage());
    error_log("Attempted DSN: " . (isset($dsn) ? $dsn : 'DSN not set'));
    error_log("Host: $host, Port: $port, Database: $dbname, User: $user");
    http_response_code(500);
    if (php_sapi_name() !== 'cli') {
        die($error_message);
    } else {
        die($error_message . "\n");
    }
}

// Set consistent session cookie parameters before sessions start
// Check for HTTPS behind proxies (like Render) - check multiple indicators
$secureFlag = ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
// Use empty domain (null) so browser uses current domain automatically - works better on Render
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', // Empty domain works better on Render/proxy environments
        'secure' => $secureFlag,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    session_set_cookie_params(0, '/', '', $secureFlag, true);
}

// Include custom session handler for serverless compatibility
try {
    require_once __DIR__ . '/../session_handler.php';
} catch (Exception $e) {
    error_log('Session handler error: ' . $e->getMessage());
    // If session handler fails, ensure connection is clean
    if (isset($conn) && $conn && $conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_e) {
            error_log('Failed to rollback after session handler error: ' . $rollback_e->getMessage());
        }
    }
}

// Include file upload helper for cloud storage
try {
    require_once __DIR__ . '/../file_upload_helper.php';
} catch (Exception $e) {
    error_log('File upload helper error: ' . $e->getMessage());
    // If file upload helper fails, ensure connection is clean
    if (isset($conn) && $conn && $conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_e) {
            error_log('Failed to rollback after file upload helper error: ' . $rollback_e->getMessage());
        }
    }
}
?>
