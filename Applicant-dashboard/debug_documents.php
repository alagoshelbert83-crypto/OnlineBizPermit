<?php
/**
 * Debug script to view document records in the database
 * Run this to see what's actually stored for documents
 */
require_once __DIR__ . '/db.php';

// Get application ID from query string, or show all
$app_id = isset($_GET['app_id']) ? (int)$_GET['app_id'] : null;

echo "<!DOCTYPE html><html><head><title>Document Debug</title><style>
body { font-family: monospace; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
tr:nth-child(even) { background-color: #f9f9f9; }
</style></head><body>";
echo "<h1>Documents Table Debug</h1>";

try {
    // Check if document_type column exists
    $check_col = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'documents' AND column_name = 'document_type'");
    $has_doc_type = $check_col->rowCount() > 0;
    
    echo "<p><strong>document_type column exists:</strong> " . ($has_doc_type ? "YES" : "NO") . "</p>";
    
    if (!$has_doc_type) {
        echo "<p style='color: red;'><strong>ERROR:</strong> The document_type column does not exist in the documents table!</p>";
        echo "<p>You need to run the migration to add this column.</p>";
    }
    
    // Query documents
    if ($app_id) {
        $stmt = $conn->prepare("SELECT id, application_id, document_name, file_path, " . ($has_doc_type ? "document_type, " : "") . "upload_date FROM documents WHERE application_id = ? ORDER BY id");
        $stmt->execute([$app_id]);
    } else {
        $stmt = $conn->query("SELECT id, application_id, document_name, file_path, " . ($has_doc_type ? "document_type, " : "") . "upload_date FROM documents ORDER BY id DESC LIMIT 20");
    }
    
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Documents" . ($app_id ? " for Application #{$app_id}" : " (Last 20)") . "</h2>";
    echo "<p>Total records: " . count($documents) . "</p>";
    
    if (empty($documents)) {
        echo "<p>No documents found.</p>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Application ID</th>";
        echo "<th>Document Name</th>";
        echo "<th>File Path</th>";
        if ($has_doc_type) {
            echo "<th>Document Type</th>";
        }
        echo "<th>Upload Date</th>";
        echo "</tr>";
        
        foreach ($documents as $doc) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($doc['id']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['application_id']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['document_name']) . "</td>";
            echo "<td style='max-width: 400px; word-break: break-all;'>" . htmlspecialchars($doc['file_path']) . "</td>";
            if ($has_doc_type) {
                $doc_type = $doc['document_type'] ?? 'NULL';
                $color = (empty($doc_type) || $doc_type === 'NULL' || strtolower($doc_type) === 'other') ? 'red' : 'green';
                echo "<td style='color: {$color}; font-weight: bold;'>" . htmlspecialchars($doc_type) . "</td>";
            }
            echo "<td>" . htmlspecialchars($doc['upload_date']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Show file path pattern analysis
    if (!empty($documents)) {
        echo "<h2>File Path Pattern Analysis</h2>";
        echo "<table>";
        echo "<tr><th>File Path</th><th>Extracted Type (from path)</th><th>Stored Type</th></tr>";
        foreach ($documents as $doc) {
            $file_path = $doc['file_path'] ?? '';
            $stored_type = ($has_doc_type && isset($doc['document_type'])) ? $doc['document_type'] : 'N/A';
            
            // Try to extract from pattern: doc_{app_id}_{document_type}_{uniqid}.ext
            $extracted_type = 'NONE';
            if (preg_match('/doc_\d+_([a-z_]+)_[a-z0-9.]+\.(jpg|jpeg|png|gif|pdf)/i', strtolower($file_path), $matches)) {
                $extracted_type = $matches[1];
            }
            
            $match_color = ($extracted_type !== 'NONE' && ($stored_type === 'N/A' || empty($stored_type) || strtolower($stored_type) === 'other')) ? 'orange' : 'black';
            
            echo "<tr>";
            echo "<td style='max-width: 300px; word-break: break-all;'>" . htmlspecialchars($file_path) . "</td>";
            echo "<td style='color: {$match_color};'>" . htmlspecialchars($extracted_type) . "</td>";
            echo "<td>" . htmlspecialchars($stored_type) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>

