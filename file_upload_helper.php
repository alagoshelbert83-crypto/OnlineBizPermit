<?php
/**
 * File Upload Helper for Serverless Environment
 * Supports local storage and cloud storage (Vercel Blob)
 */

class FileUploadHelper {
    private $storage_type;
    private $local_dir;
    private $blob_token;
    private $blob_url;

    public function __construct() {
        $this->storage_type = getenv('STORAGE_TYPE') ?: 'local';
        $this->local_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $this->blob_token = getenv('BLOB_READ_WRITE_TOKEN');
        $this->blob_url = getenv('VERCEL_BLOB_URL') ?: 'https://blob.vercel-storage.com';
    }

    public function uploadFile($tmp_path, $filename, $mime_type = null) {
        if ($this->storage_type === 'blob') {
            return $this->uploadToBlob($tmp_path, $filename, $mime_type);
        } else {
            return $this->uploadToLocal($tmp_path, $filename);
        }
    }

    private function uploadToLocal($tmp_path, $filename) {
        $upload_dir = $this->local_dir;

        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $filepath = $upload_dir . $filename;
        if (move_uploaded_file($tmp_path, $filepath)) {
            return $filename; // Return relative path
        }
        return false;
    }

    private function uploadToBlob($tmp_path, $filename, $mime_type = null) {
        if (!$this->blob_token) {
            // Fallback to local if no token
            error_log("Vercel Blob: No token found, falling back to local storage");
            return $this->uploadToLocal($tmp_path, $filename);
        }

        // Vercel Blob API endpoint - try PUT method first
        $url = 'https://blob.vercel-storage.com/' . urlencode($filename);
        $file_content = file_get_contents($tmp_path);
        
        if ($file_content === false) {
            error_log("Vercel Blob: Failed to read file content from $tmp_path");
            return false;
        }

        // Try PUT request (simpler, more standard)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->blob_token,
            'Content-Type: ' . ($mime_type ?: 'application/octet-stream'),
            'x-content-type: ' . ($mime_type ?: 'application/octet-stream'),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // If PUT fails, try POST with multipart/form-data
        if ($http_code !== 200 && $http_code !== 201) {
            error_log("Vercel Blob: PUT failed with HTTP $http_code, trying POST method");
            
            $url = 'https://blob.vercel-storage.com/put';
            $boundary = uniqid();
            $delimiter = '-------------' . $boundary;
            
            $post_data = '';
            $post_data .= '--' . $delimiter . "\r\n";
            $post_data .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
            $post_data .= 'Content-Type: ' . ($mime_type ?: 'application/octet-stream') . "\r\n\r\n";
            $post_data .= $file_content . "\r\n";
            $post_data .= '--' . $delimiter . '--';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->blob_token,
                'Content-Type: multipart/form-data; boundary=' . $delimiter,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
        }

        if ($curl_error) {
            error_log("Vercel Blob CURL Error: " . $curl_error);
            return false;
        }

        if ($http_code === 200 || $http_code === 201) {
            $data = json_decode($response, true);
            if (isset($data['url'])) {
                error_log("Vercel Blob: Successfully uploaded $filename, URL: " . $data['url']);
                return $data['url'];
            } elseif (filter_var($response, FILTER_VALIDATE_URL)) {
                // Sometimes the response is just the URL string
                error_log("Vercel Blob: Successfully uploaded $filename, URL: " . $response);
                return $response;
            } else {
                error_log("Vercel Blob: Upload succeeded but no URL in response: " . $response);
                // Try to construct URL from filename
                $blob_url = 'https://' . parse_url($this->blob_url, PHP_URL_HOST) . '/' . urlencode($filename);
                error_log("Vercel Blob: Attempting constructed URL: " . $blob_url);
                return $blob_url;
            }
        } else {
            error_log("Vercel Blob: Upload failed with HTTP $http_code. Response: " . $response);
            return false;
        }
    }

    public function getFileUrl($filename) {
        if ($this->storage_type === 'blob' && filter_var($filename, FILTER_VALIDATE_URL)) {
            return $filename; // Already a full URL
        } else {
            // Return local path relative to web root
            return '/uploads/' . $filename;
        }
    }

    public function deleteFile($filename) {
        if ($this->storage_type === 'blob' && filter_var($filename, FILTER_VALIDATE_URL)) {
            // For Blob, we could implement delete via API
            // For now, just return true
            return true;
        } else {
            $filepath = $this->local_dir . $filename;
            if (file_exists($filepath)) {
                return unlink($filepath);
            }
            return true;
        }
    }
}

// Global helper instance
$upload_helper = new FileUploadHelper();
?>
