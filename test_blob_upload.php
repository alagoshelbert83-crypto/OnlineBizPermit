<?php
/**
 * Test script for Vercel Blob Storage
 * Run this to verify your Vercel Blob configuration
 */

require_once __DIR__ . '/file_upload_helper.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vercel Blob Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Vercel Blob Storage Configuration Test</h1>
        
        <?php
        global $upload_helper;
        
        // Check configuration
        echo '<h2>Configuration</h2>';
        echo '<div class="info">';
        echo '<strong>Storage Type:</strong> ' . (getenv('STORAGE_TYPE') ?: 'local (not set)') . '<br>';
        echo '<strong>BLOB_READ_WRITE_TOKEN:</strong> ' . (getenv('BLOB_READ_WRITE_TOKEN') ? 'Set (' . substr(getenv('BLOB_READ_WRITE_TOKEN'), 0, 10) . '...)' : 'Not set') . '<br>';
        echo '<strong>VERCEL_BLOB_URL:</strong> ' . (getenv('VERCEL_BLOB_URL') ?: 'https://blob.vercel-storage.com (default)') . '<br>';
        echo '</div>';
        
        // Test file upload if form submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
            echo '<h2>Upload Test</h2>';
            
            if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['test_file']['tmp_name'];
                $original_name = $_FILES['test_file']['name'];
                $file_type = $_FILES['test_file']['type'];
                $file_size = $_FILES['test_file']['size'];
                
                $test_filename = 'test_' . time() . '_' . $original_name;
                
                echo '<div class="info">';
                echo '<strong>File:</strong> ' . htmlspecialchars($original_name) . '<br>';
                echo '<strong>Size:</strong> ' . number_format($file_size) . ' bytes<br>';
                echo '<strong>Type:</strong> ' . htmlspecialchars($file_type) . '<br>';
                echo '<strong>Test Filename:</strong> ' . htmlspecialchars($test_filename) . '<br>';
                echo '</div>';
                
                // Try to upload
                $result = $upload_helper->uploadFile($tmp_name, $test_filename, $file_type);
                
                if ($result) {
                    echo '<div class="success">';
                    echo '<strong>✅ Upload Successful!</strong><br>';
                    echo '<strong>Result:</strong> ' . htmlspecialchars($result) . '<br>';
                    
                    if (filter_var($result, FILTER_VALIDATE_URL)) {
                        echo '<strong>Type:</strong> Cloud Storage URL<br>';
                        echo '<a href="' . htmlspecialchars($result) . '" target="_blank">Test Link</a><br>';
                        echo '<img src="' . htmlspecialchars($result) . '" style="max-width: 300px; margin-top: 10px;" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';"><div style="display:none; color: #dc3545;">Image failed to load</div>';
                    } else {
                        echo '<strong>Type:</strong> Local filename<br>';
                        echo '<strong>Note:</strong> This is a local file path. If STORAGE_TYPE=blob, this should be a URL instead.';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="error">';
                    echo '<strong>❌ Upload Failed!</strong><br>';
                    echo 'Check the server error logs for details.';
                    echo '</div>';
                }
            } else {
                echo '<div class="error">';
                echo '<strong>File Upload Error:</strong> ' . $_FILES['test_file']['error'];
                echo '</div>';
            }
        }
        ?>
        
        <h2>Test Upload</h2>
        <form method="POST" enctype="multipart/form-data">
            <p>Select a test file to upload:</p>
            <input type="file" name="test_file" accept="image/*,.pdf" required>
            <br><br>
            <button type="submit">Upload Test File</button>
        </form>
        
        <h2>Environment Variables Check</h2>
        <p>Make sure these are set in your Render.com environment variables:</p>
        <pre>STORAGE_TYPE=blob
BLOB_READ_WRITE_TOKEN=your_vercel_blob_token_here</pre>
        
        <p><strong>To get your Vercel Blob token:</strong></p>
        <ol>
            <li>Go to <a href="https://vercel.com" target="_blank">Vercel Dashboard</a></li>
            <li>Navigate to your project → Settings → Environment Variables</li>
            <li>Create a Blob store if you haven't already</li>
            <li>Get the read/write token from the Blob store settings</li>
        </ol>
    </div>
</body>
</html>

