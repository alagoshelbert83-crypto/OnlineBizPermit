<?php
/**
 * Diagnostic script to check file status
 * Compares files in database with files on disk
 */

require_once __DIR__ . '/db.php';

// Check if user is authenticated (optional - you can remove this if you want)
// if (session_status() === PHP_SESSION_NONE) session_start();
// if (!isset($_SESSION['user_id'])) {
//     die('Unauthorized');
// }

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Status Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-exists { color: #28a745; font-weight: 600; }
        .status-missing { color: #dc3545; font-weight: 600; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-box { flex: 1; padding: 15px; background: #f8f9fa; border-radius: 6px; }
        .stat-box h3 { margin: 0 0 10px 0; color: #666; font-size: 14px; }
        .stat-box .number { font-size: 32px; font-weight: 700; color: #333; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📁 File Status Diagnostic</h1>
        
        <?php
        $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        
        // Get all documents from database
        try {
            $stmt = $conn->prepare("SELECT id, application_id, document_name, file_path, upload_date FROM documents ORDER BY upload_date DESC LIMIT 100");
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            die("Error fetching documents: " . $e->getMessage());
        }
        
        // Check file existence
        $total_files = count($documents);
        $existing_files = 0;
        $missing_files = 0;
        $url_files = 0;
        
        foreach ($documents as &$doc) {
            $file_path_db = $doc['file_path'];
            
            // Check if it's a URL (cloud storage)
            if (filter_var($file_path_db, FILTER_VALIDATE_URL)) {
                $doc['status'] = 'url';
                $doc['exists'] = true; // Assume URL files exist
                $url_files++;
                $existing_files++;
            } else {
                // Remove path prefixes
                $file = preg_replace('#^[/\\\\]*(uploads[/\\\\]+)?#i', '', $file_path_db);
                $file = str_replace('\\', '/', $file);
                $file = str_replace(['../', '..\\'], '', $file);
                $file = ltrim($file, '/\\');
                
                $full_path = $upload_dir . str_replace('/', DIRECTORY_SEPARATOR, $file);
                $exists = file_exists($full_path);
                
                $doc['status'] = $exists ? 'exists' : 'missing';
                $doc['exists'] = $exists;
                $doc['checked_path'] = $full_path;
                
                if ($exists) {
                    $existing_files++;
                } else {
                    $missing_files++;
                }
            }
        }
        
        // Display statistics
        ?>
        <div class="stats">
            <div class="stat-box">
                <h3>Total Files in Database</h3>
                <div class="number"><?= $total_files ?></div>
            </div>
            <div class="stat-box">
                <h3>Files Found on Server</h3>
                <div class="number" style="color: #28a745;"><?= $existing_files ?></div>
            </div>
            <div class="stat-box">
                <h3>Files Missing</h3>
                <div class="number" style="color: #dc3545;"><?= $missing_files ?></div>
            </div>
            <?php if ($url_files > 0): ?>
            <div class="stat-box">
                <h3>Cloud Storage URLs</h3>
                <div class="number" style="color: #007bff;"><?= $url_files ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($missing_files > 0): ?>
        <div class="warning">
            <strong>⚠️ Warning:</strong> <?= $missing_files ?> file(s) are in the database but don't exist on the server's file system.
            <br><br>
            <strong>Common causes on cloud hosting (like Render.com):</strong>
            <ul>
                <li>Files are stored in an ephemeral filesystem that gets wiped on server restart/redeploy</li>
                <li>Files were uploaded to a different server instance</li>
                <li>Files were deleted manually or by a cleanup process</li>
            </ul>
            <br>
            <strong>Solutions:</strong>
            <ul>
                <li>Use cloud storage (S3, Vercel Blob, etc.) instead of local filesystem</li>
                <li>Use a persistent volume/mount for the uploads directory</li>
                <li>Re-upload the missing files</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($url_files > 0): ?>
        <div class="info">
            <strong>ℹ️ Info:</strong> <?= $url_files ?> file(s) are stored in cloud storage (URLs). These should work correctly.
        </div>
        <?php endif; ?>
        
        <h2>File Details</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Application ID</th>
                    <th>Document Name</th>
                    <th>File Path (DB)</th>
                    <th>Status</th>
                    <th>Checked Path</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= htmlspecialchars($doc['id']) ?></td>
                    <td><?= htmlspecialchars($doc['application_id']) ?></td>
                    <td><?= htmlspecialchars($doc['document_name']) ?></td>
                    <td style="word-break: break-all; max-width: 300px;"><?= htmlspecialchars($doc['file_path']) ?></td>
                    <td>
                        <?php if ($doc['status'] === 'exists'): ?>
                            <span class="status-exists">✓ Exists</span>
                        <?php elseif ($doc['status'] === 'url'): ?>
                            <span style="color: #007bff; font-weight: 600;">🌐 Cloud URL</span>
                        <?php else: ?>
                            <span class="status-missing">✗ Missing</span>
                        <?php endif; ?>
                    </td>
                    <td style="word-break: break-all; max-width: 400px; font-size: 12px; color: #666;">
                        <?= htmlspecialchars($doc['checked_path'] ?? 'N/A') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Server Information</h2>
        <table>
            <tr>
                <th>Upload Directory</th>
                <td><?= htmlspecialchars($upload_dir) ?></td>
            </tr>
            <tr>
                <th>Directory Exists</th>
                <td><?= is_dir($upload_dir) ? '<span class="status-exists">Yes</span>' : '<span class="status-missing">No</span>' ?></td>
            </tr>
            <tr>
                <th>Directory Writable</th>
                <td><?= is_writable($upload_dir) ? '<span class="status-exists">Yes</span>' : '<span class="status-missing">No</span>' ?></td>
            </tr>
            <?php if (is_dir($upload_dir)): ?>
            <tr>
                <th>Files on Disk</th>
                <td>
                    <?php
                    $disk_files = glob($upload_dir . '*');
                    $disk_count = is_array($disk_files) ? count($disk_files) : 0;
                    echo $disk_count . ' file(s) found';
                    ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>

