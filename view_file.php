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

// Store original request for logging
$original_request = $file;

// Remove any path prefixes that might be in the database (e.g., "uploads/", "/uploads/")
$file = str_replace(['uploads/', '/uploads/', '\\uploads\\', 'uploads\\'], '', $file);
$file = basename($file); // Remove any directory traversal attempts - get just the filename

$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$file_path = $upload_dir . $file;

// Check if uploads directory exists
if (!is_dir($upload_dir)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    error_log("Uploads directory does not exist: " . $upload_dir);
    die('Server configuration error: Uploads directory not found');
}

// Check if file exists
if (!file_exists($file_path)) {
    // Try URL decoding in case the filename was double-encoded
    $decoded_file = urldecode($file);
    $decoded_path = $upload_dir . $decoded_file;
    
    if ($decoded_file !== $file && file_exists($decoded_path)) {
        $file_path = $decoded_path;
        $file = $decoded_file;
    } else {
        // Try to find files with similar names (in case of minor variations)
        $found_file = null;
        if (is_dir($upload_dir)) {
            $pattern = preg_quote(pathinfo($file, PATHINFO_FILENAME), '/') . '.*' . preg_quote('.' . pathinfo($file, PATHINFO_EXTENSION), '/');
            $files = glob($upload_dir . $pattern);
            if (!empty($files)) {
                $found_file = basename($files[0]);
                $file_path = $files[0];
                error_log("File not found as '$file', but found similar: '$found_file'");
            }
        }
        
        // If still not found, log detailed information for debugging
        if (!$found_file) {
            error_log("File not found details:");
            error_log("  Original request: " . $original_request);
            error_log("  Processed filename: " . $file);
            error_log("  Expected path: " . $file_path);
            error_log("  Upload dir exists: " . (is_dir($upload_dir) ? 'Yes' : 'No'));
            error_log("  Upload dir path: " . $upload_dir);
            
            // Try to list files in uploads directory for debugging (first 10 files)
            if (is_dir($upload_dir)) {
                $files_in_dir = array_slice(scandir($upload_dir), 0, 12);
                error_log("  Files in uploads directory: " . implode(', ', $files_in_dir));
            }
            
            // Return a transparent 1x1 PNG for images to prevent broken image icons
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                http_response_code(200);
                header('Content-Type: image/png');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                // Output a transparent 1x1 PNG
                echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
                exit;
            }
            
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html>
            <head><title>File Not Found</title></head>
            <body>
                <h1>File Not Found</h1>
                <p>The requested file could not be found on the server.</p>
                <p><small>Requested file: <?= htmlspecialchars($file) ?></small></p>
                <p><small>If this file was recently uploaded, it may not have been saved properly. Please contact support or re-upload the document.</small></p>
            </body>
            </html>
            <?php
            exit;
        }
    }
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

