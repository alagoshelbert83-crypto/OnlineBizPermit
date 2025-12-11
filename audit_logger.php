<?php
/**
 * Audit Logger Class
 * Tracks user activities across admin, applicant, and staff dashboards
 */

class AuditLogger {
    private static $instance = null;
    private $conn = null;

    private function __construct() {
        // Get database connection
        $db_path = __DIR__ . '/db.php';
        if (file_exists($db_path)) {
            require_once $db_path;
            $this->conn = $conn ?? null;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a user activity
     *
     * @param string $action The action performed (e.g., 'login', 'view_application')
     * @param string $description Human-readable description
     * @param array $metadata Additional structured data
     * @param int|null $user_id User ID (null for guests)
     * @param string|null $user_role User role ('admin', 'staff', 'applicant', 'guest')
     * @return bool Success status
     */
    public function log($action, $description = '', $metadata = [], $user_id = null, $user_role = null) {
        if (!$this->conn) {
            error_log("AuditLogger: Database connection not available");
            return false;
        }

        // Auto-detect user info if not provided
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }

        if ($user_role === null) {
            $user_role = $_SESSION['role'] ?? 'guest';
        }

        // Get client information
        // X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2)
        // Extract the first IP (original client) and validate it
        $ip_address = null;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($forwarded_ips[0]); // Get first IP (original client)
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip_address = trim($_SERVER['HTTP_X_REAL_IP']);
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP address format (IPv4 or IPv6)
        if ($ip_address && !filter_var($ip_address, FILTER_VALIDATE_IP)) {
            error_log("AuditLogger: Invalid IP address format: {$ip_address}");
            $ip_address = null; // Set to null if invalid
        }
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $session_id = session_id();

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs
                (user_id, user_role, action, description, ip_address, user_agent, session_id, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $user_id,
                $user_role,
                $action,
                $description,
                $ip_address,
                $user_agent,
                $session_id,
                json_encode($metadata)
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("AuditLogger error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log user login activity
     */
    public function logLogin($user_id, $user_role, $method = 'password') {
        return $this->log(
            'login',
            "User logged in using {$method} authentication",
            ['login_method' => $method],
            $user_id,
            $user_role
        );
    }

    /**
     * Log user logout activity
     */
    public function logLogout($user_id, $user_role) {
        return $this->log(
            'logout',
            'User logged out',
            [],
            $user_id,
            $user_role
        );
    }

    /**
     * Log application viewing
     */
    public function logViewApplication($application_id, $user_id, $user_role) {
        return $this->log(
            'view_application',
            "Viewed application details",
            ['application_id' => $application_id],
            $user_id,
            $user_role
        );
    }

    /**
     * Log application status change
     */
    public function logApplicationStatusChange($application_id, $old_status, $new_status, $user_id, $user_role, $reason = '') {
        return $this->log(
            'change_application_status',
            "Changed application status from '{$old_status}' to '{$new_status}'" . ($reason ? ": {$reason}" : ''),
            [
                'application_id' => $application_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'reason' => $reason
            ],
            $user_id,
            $user_role
        );
    }

    /**
     * Log chat message
     */
    public function logChatMessage($chat_id, $message_length, $user_id, $user_role, $has_file = false) {
        return $this->log(
            'send_chat_message',
            "Sent chat message" . ($has_file ? ' with file attachment' : ''),
            [
                'chat_id' => $chat_id,
                'message_length' => $message_length,
                'has_file' => $has_file
            ],
            $user_id,
            $user_role
        );
    }

    /**
     * Log page access
     */
    public function logPageAccess($page_name, $user_id, $user_role) {
        return $this->log(
            'page_access',
            "Accessed page: {$page_name}",
            ['page' => $page_name],
            $user_id,
            $user_role
        );
    }

    /**
     * Log file upload
     */
    public function logFileUpload($filename, $filesize, $filetype, $user_id, $user_role, $context = '') {
        return $this->log(
            'file_upload',
            "Uploaded file: {$filename}" . ($context ? " ({$context})" : ''),
            [
                'filename' => $filename,
                'filesize' => $filesize,
                'filetype' => $filetype,
                'context' => $context
            ],
            $user_id,
            $user_role
        );
    }

    /**
     * Log password change
     */
    public function logPasswordChange($user_id, $user_role) {
        return $this->log(
            'password_change',
            'Changed password',
            [],
            $user_id,
            $user_role
        );
    }

    /**
     * Log failed login attempt
     */
    public function logFailedLogin($username, $reason = 'invalid_credentials') {
        return $this->log(
            'failed_login',
            "Failed login attempt for user: {$username}",
            [
                'username' => $username,
                'reason' => $reason
            ],
            null,
            'guest'
        );
    }

    /**
     * Get recent audit logs for a user
     */
    public function getUserLogs($user_id, $limit = 50) {
        if (!$this->conn) return [];

        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM audit_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("AuditLogger getUserLogs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get audit logs by action type
     */
    public function getLogsByAction($action, $limit = 100) {
        if (!$this->conn) return [];

        try {
            $stmt = $this->conn->prepare("
                SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.action = ?
                ORDER BY al.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$action, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("AuditLogger getLogsByAction error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent audit logs with user info
     */
    public function getRecentLogs($limit = 100) {
        if (!$this->conn) return [];

        try {
            $stmt = $this->conn->prepare("
                SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("AuditLogger getRecentLogs error: " . $e->getMessage());
            return [];
        }
    }
}

// Global function for easy logging
function audit_log($action, $description = '', $metadata = []) {
    $logger = AuditLogger::getInstance();
    return $logger->log($action, $description, $metadata);
}
?>
