<?php
/**
 * Converts a datetime string to a relative time string.
 *
 * @param string $datetime The datetime string.
 * @return string The relative time string.
 */
function time_ago(string $datetime): string {
    try {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        if ($diff->y >= 1) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m >= 1) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        $weeks = floor($diff->d / 7);
        if ($weeks >= 1) return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        if ($diff->d >= 1) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h >= 1) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i >= 1) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        if ($diff->s >= 1) return $diff->s . ' second' . ($diff->s > 1 ? 's' : '') . ' ago';
        return 'just now';
    } catch (Exception $e) {
        return 'a moment ago'; // Fallback for invalid datetime string
    }
}

/**
 * Retrieves a setting value from the database.
 *
 * @param PDO $conn The database connection.
 * @param string $key The key of the setting to retrieve.
 * @param mixed $default The default value to return if the key is not found.
 * @return mixed The value of the setting or the default value.
 */
function get_setting(PDO $conn, string $key, $default = '') {
    // This function can be optimized by caching settings in a static variable.
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Updates or creates a setting in the database.
 *
 * @param PDO $conn The database connection.
 * @param string $key The key of the setting.
 * @param string $value The new value for the setting.
 * @return bool True on success, false on failure.
 */
function update_setting(PDO $conn, string $key, string $value): bool {
    // PostgreSQL uses ON CONFLICT instead of ON DUPLICATE KEY UPDATE
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?");
    if (!$stmt) return false;
    return $stmt->execute([$key, $value, $value]);
}
