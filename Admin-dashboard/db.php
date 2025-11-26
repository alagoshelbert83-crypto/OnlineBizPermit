<?php
/**
 * Database Connection for Admin Dashboard - PostgreSQL (Supabase)
 */

// --- Database Configuration for Supabase ---
// IMPORTANT: These values are set via environment variables in Vercel

$host = getenv('DB_HOST') ?: 'your-supabase-host.supabase.co';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') ?: 'your-supabase-password';
$dbname = getenv('DB_NAME') ?: 'postgres';
$port = 5432;

// --- Establish PostgreSQL Connection ---
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$pass";
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Use a more generic error in production
    $error_message = "Database connection failed.";
    error_log("Database connection error: " . $e->getMessage());
    die($error_message);
}
?>
