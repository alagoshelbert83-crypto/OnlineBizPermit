<?php
/**
 * Secure file viewer for uploaded documents
 * This file serves uploaded files with proper headers and security checks
 */

require_once __DIR__ . '/db.php';

// Get file path from query parameter
$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    die('File parameter is required');
}

// Remove any path prefixes that might be in the database (e.g., "uploads/", "/uploads/")
$file = str_replace(['uploads/', '/uploads/', '\\uploads\\'], '', $file);
$file = basename($file); // Remove any directory traversal attempts
$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$file_path = $upload_dir . $file;

// Check if uploads directory exists
if (!is_dir($upload_dir)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    error_log("Uploads directory does not exist: " . $upload_dir);
    die('Server configuration error');
}

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>File Not Found</title></head>
    <body>
        <h1>File Not Found</h1>
        <p>The requested file could not be found.</p>
        <p><small>Requested: <?= htmlspecialchars($file) ?></small></p>
    </body>
    </html>
    <?php
    error_log("File not found: " . $file_path . " (requested file: " . ($_GET['file'] ?? '') . ")");
    exit;
}

// Security check: ensure file is within uploads directory (compatible with PHP < 8.0)
$real_file_path = realpath($file_path);
$real_upload_dir = realpath($upload_dir);
if ($real_file_path === false || $real_upload_dir === false || strpos($real_file_path, $real_upload_dir) !== 0) {
    http_response_code(403);
    header('Content-Type: text/plain');
    error_log("Security check failed for file: " . $file_path);
    die('Access denied');
}

// Get file extension and determine MIME type
$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Cache-Control: public, max-age=3600');

// Disable output buffering and serve file
if (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;

