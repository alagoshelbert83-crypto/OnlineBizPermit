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

// Check if this is already a full URL (from cloud storage like Vercel Blob)
// Also check for URLs that might have been URL-encoded
$decoded_file = urldecode($file);
if (filter_var($file, FILTER_VALIDATE_URL) || filter_var($decoded_file, FILTER_VALIDATE_URL)) {
    // Use the decoded version if it's a valid URL
    $url = filter_var($decoded_file, FILTER_VALIDATE_URL) ? $decoded_file : $file;
    // Redirect to the cloud storage URL
    header('Location: ' . $url);
    exit;
}

// If the record holds a data URL (e.g. "data:image/png;base64,..." stored in DB), serve it directly
if (strpos($file, 'data:') === 0) {
    // Expected format: data:[<mediatype>][;base64],<data>
    if (preg_match('#^data:([^;]+);base64,(.*)$#s', $file, $m)) {
        $mime = $m[1];
        $b64 = $m[2];
        $data = base64_decode($b64);
        if ($data === false) {
            http_response_code(400);
            header('Content-Type: text/plain');
            error_log("Invalid data URL provided to view_file.php");
            die('Invalid data URL');
        }
        // Serve decoded data with correct headers
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($data));
        header('Content-Disposition: inline; filename="file"');
        header('Cache-Control: public, max-age=3600');
        // Output the binary data and exit
        echo $data;
        exit;
    } else {
        http_response_code(400);
        header('Content-Type: text/plain');
        error_log("Unsupported data URL format passed to view_file.php");
        die('Unsupported data URL format');
    }
}

// Remove any leading path prefixes that might be in the database (e.g., "uploads/", "/uploads/")
$file = preg_replace('#^[/\\\\]*(uploads[/\\\\]+)?#i', '', $file);
// Normalize path separators to forward slashes
$file = str_replace('\\', '/', $file);
// Remove any directory traversal attempts (../ or ..\)
$file = str_replace(['../', '..\\'], '', $file);
// Remove any leading slashes
$file = ltrim($file, '/\\');

$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$file_path = $upload_dir . str_replace('/', DIRECTORY_SEPARATOR, $file);

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
            $filename_only = basename($file);
            $pattern = preg_quote(pathinfo($filename_only, PATHINFO_FILENAME), '/') . '.*' . preg_quote('.' . pathinfo($filename_only, PATHINFO_EXTENSION), '/');
            // Search recursively using RecursiveDirectoryIterator
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $path) {
                if ($path->isFile() && preg_match('/' . $pattern . '/i', $path->getFilename())) {
                    $found_file = str_replace($upload_dir, '', $path->getPathname());
                    $file_path = $path->getPathname();
                    error_log("File not found as '$file', but found similar: '$found_file'");
                    break;
                }
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
                
                // Count total files in uploads directory
                $all_files = glob($upload_dir . '*');
                $file_count = is_array($all_files) ? count($all_files) : 0;
                error_log("  Total files in uploads directory: " . $file_count);
            }
            
            // Check if this might be a cloud storage issue
            // If file_path in DB is a URL, it should have been handled earlier
            if (filter_var($original_request, FILTER_VALIDATE_URL)) {
                error_log("  WARNING: File path appears to be a URL but wasn't handled as cloud storage");
            }
            
            // Return a proper "file not found" image for images
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                // Clear any previous output
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                http_response_code(200); // Use 200 so browser displays it instead of showing error
                header('Content-Type: image/svg+xml; charset=utf-8');
                header('Content-Disposition: inline; filename="not-found.svg"');
                header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                
                // Output a simple SVG error image with more helpful message
                $filename_display = htmlspecialchars(basename($file), ENT_XML1, 'UTF-8');
                echo '<?xml version="1.0" encoding="UTF-8"?>
<svg width="400" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect width="400" height="200" fill="#f8f9fa"/>
  <rect x="10" y="10" width="380" height="180" fill="#fff" stroke="#dee2e6" stroke-width="2" rx="8"/>
  <text x="50%" y="35%" font-family="Arial, sans-serif" font-size="16" font-weight="bold" fill="#dc3545" text-anchor="middle" dominant-baseline="middle">File Not Found</text>
  <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="12" fill="#6c757d" text-anchor="middle" dominant-baseline="middle">' . $filename_display . '</text>
  <text x="50%" y="65%" font-family="Arial, sans-serif" font-size="11" fill="#999" text-anchor="middle" dominant-baseline="middle">This file may have been lost due to server restart</text>
</svg>';
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

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers for inline display (not download)
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff'); // Prevent MIME type sniffing

// Disable output buffering and serve file
if (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;
