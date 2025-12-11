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
    die('File parameter is required');
}

// Security: Only allow files from uploads directory
$file = basename($file); // Remove any directory traversal attempts
$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$file_path = $upload_dir . $file;

// Check if file exists and is within uploads directory
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Security check: ensure file is within uploads directory (compatible with PHP < 8.0)
$real_file_path = realpath($file_path);
$real_upload_dir = realpath($upload_dir);
if ($real_file_path === false || $real_upload_dir === false || strpos($real_file_path, $real_upload_dir) !== 0) {
    http_response_code(403);
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

