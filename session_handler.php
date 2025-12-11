<?php
/**
 * Custom Session Handler for Serverless Environment
 * Stores sessions in database since file-based sessions don't persist
 */

// Suppress deprecation warnings for gc() return type
// This is a known PHP 8.1+ compatibility issue
if (PHP_VERSION_ID >= 80100) {
    error_reporting(error_reporting() & ~E_DEPRECATED);
}

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $conn;
    private $table = 'user_sessions';

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function open($savePath, $sessionName): bool {
        // Create sessions table if it doesn't exist (PostgreSQL syntax)
        // CRITICAL: This must NOT be in a transaction - session operations are independent
        try {
            // Ensure we're not in a transaction before creating table
            if ($this->conn->inTransaction()) {
                error_log('WARNING: Session handler open() called during active transaction');
                // Don't rollback - let the calling code handle it
                return false;
            }
            
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                session_id VARCHAR(255) PRIMARY KEY,
                session_data TEXT,
                session_expires TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->conn->exec($sql);
            return true;
        } catch (Exception $e) {
            error_log("Session handler open() error: " . $e->getMessage());
            return false;
        }
    }

    public function close(): bool {
        return true;
    }

    public function read($sessionId): string {
        try {
            // CRITICAL: Session operations must NOT be in a transaction
            // If we're in a transaction, we can't read sessions (this is a design issue)
            if ($this->conn->inTransaction()) {
                error_log("WARNING: Session read() called during active transaction for session_id={$sessionId}");
                // Return empty to avoid transaction pollution
                return '';
            }
            
            $stmt = $this->conn->prepare("SELECT session_data FROM {$this->table} WHERE session_id = :session_id AND session_expires > NOW()");
            $stmt->execute(['session_id' => $sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                // Update expires to extend the session
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $update_stmt = $this->conn->prepare("UPDATE {$this->table} SET session_expires = :expires WHERE session_id = :session_id");
                $update_stmt->execute(['expires' => $expires, 'session_id' => $sessionId]);
                return $result['session_data'];
            }
            return '';
        } catch (Exception $e) {
            error_log("Session read error for session_id={$sessionId}: " . $e->getMessage());
            // Don't let session errors break the application
            return '';
        }
    }

    public function write($sessionId, $data): bool {
        // Set session to expire in 24 hours
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        try {
            // CRITICAL: Session operations must NOT be in a transaction
            // If we're in a transaction, we can't write sessions (this would cause transaction pollution)
            if ($this->conn->inTransaction()) {
                error_log("WARNING: Session write() called during active transaction for session_id={$sessionId}");
                // Don't write - this prevents transaction pollution
                // The session will be written on the next request when there's no transaction
                return false;
            }
            
            $stmt = $this->conn->prepare("INSERT INTO {$this->table} (session_id, session_data, session_expires) VALUES (:session_id, :session_data, :session_expires) ON CONFLICT (session_id) DO UPDATE SET session_data = EXCLUDED.session_data, session_expires = EXCLUDED.session_expires");
            $ok = $stmt->execute([
                'session_id' => $sessionId,
                'session_data' => $data,
                'session_expires' => $expires
            ]);
            if (!$ok) {
                $err = $stmt->errorInfo();
                error_log("Session write failed for session_id={$sessionId}: " . json_encode($err));
            }
            return (bool)$ok;
        } catch (Exception $e) {
            error_log("Session write exception for session_id={$sessionId}: " . $e->getMessage());
            // Don't let session errors break the application
            return false;
        }
    }

    public function destroy($sessionId): bool {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE session_id = :session_id");
        return $stmt->execute(['session_id' => $sessionId]);
    }

    /**
     * Garbage collection - delete expired sessions
     * @param int $maxLifetime Maximum session lifetime in seconds
     * @return int|false Number of deleted sessions or false on failure
     */
    #[\ReturnTypeWillChange]
    public function gc($maxLifetime) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE session_expires < NOW()");
            if ($stmt->execute()) {
                // Return number of deleted rows, or 0 if none
                $count = $stmt->rowCount();
                return $count;
            }
            return false;
        } catch (Exception $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize custom session handler if database connection exists
// IMPORTANT: Must be called BEFORE session_start()
// Suppress warnings if session is already active (shouldn't happen if called correctly)
if (isset($conn) && $conn instanceof PDO) {
    // Only set handler if session is not already active
    if (session_status() === PHP_SESSION_NONE) {
        try {
            $sessionHandler = new DatabaseSessionHandler($conn);
            @session_set_save_handler($sessionHandler, true);
        } catch (Exception $e) {
            // If handler cannot be set, log error but don't die
            error_log("Could not set session handler: " . $e->getMessage());
        }
    }
}
?>
