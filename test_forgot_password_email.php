<?php
/**
 * Test script to debug forgot password email sending
 * Access this file directly in your browser to test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config_mail.php';
require_once __DIR__ . '/Staff-dashboard/email_functions.php';

echo "<h2>Email Configuration Test</h2>";
echo "<pre>";

// Check configuration
echo "=== Configuration Check ===\n";
echo "MAIL_SMTP_ENABLED: " . (defined('MAIL_SMTP_ENABLED') ? (MAIL_SMTP_ENABLED ? 'true' : 'false') : 'NOT SET') . "\n";
echo "MAIL_SMTP_HOST: " . (defined('MAIL_SMTP_HOST') ? MAIL_SMTP_HOST : 'NOT SET') . "\n";
echo "MAIL_SMTP_PORT: " . (defined('MAIL_SMTP_PORT') ? MAIL_SMTP_PORT : 'NOT SET') . "\n";
echo "MAIL_SMTP_SECURE: " . (defined('MAIL_SMTP_SECURE') ? MAIL_SMTP_SECURE : 'NOT SET') . "\n";
echo "MAIL_SMTP_USERNAME: " . (defined('MAIL_SMTP_USERNAME') ? MAIL_SMTP_USERNAME : 'NOT SET') . "\n";
echo "MAIL_SMTP_PASSWORD: " . (defined('MAIL_SMTP_PASSWORD') ? (strlen(MAIL_SMTP_PASSWORD) > 0 ? 'SET (' . strlen(MAIL_SMTP_PASSWORD) . ' chars)' : 'EMPTY') : 'NOT SET') . "\n";
echo "MAIL_FROM_EMAIL: " . (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'NOT SET') . "\n";
echo "MAIL_FROM_NAME: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'NOT SET') . "\n";
echo "MAIL_SMTP_DEBUG: " . (defined('MAIL_SMTP_DEBUG') ? MAIL_SMTP_DEBUG : 'NOT SET') . "\n\n";

// Check environment variables
echo "=== Environment Variables ===\n";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
echo "SMTP_PORT: " . (getenv('SMTP_PORT') ?: 'NOT SET') . "\n";
echo "SMTP_USER: " . (getenv('SMTP_USER') ?: 'NOT SET') . "\n";
echo "SMTP_PASS: " . (getenv('SMTP_PASS') ? (strlen(getenv('SMTP_PASS')) > 0 ? 'SET (' . strlen(getenv('SMTP_PASS')) . ' chars)' : 'EMPTY') : 'NOT SET') . "\n";
echo "MAIL_FROM_EMAIL: " . (getenv('MAIL_FROM_EMAIL') ?: 'NOT SET') . "\n\n";

// Test email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        echo "<div style='color: red;'>Please enter a valid email address.</div>\n";
    } else {
        echo "=== Attempting to Send Test Email ===\n";
        echo "To: $test_email\n";
        echo "From: " . MAIL_FROM_EMAIL . "\n\n";
        
        $test_subject = "Test Password Reset Email - OnlineBizPermit";
        $test_body = "<html><body><h2>Test Email</h2><p>This is a test email to verify Gmail SMTP configuration.</p></body></html>";
        
        ob_start();
        $result = sendApplicationEmail($test_email, 'Test User', $test_subject, $test_body);
        $output = ob_get_clean();
        
        echo $output;
        
        if ($result) {
            echo "<div style='color: green; font-weight: bold;'>✓ Email sent successfully! Check your inbox.</div>\n";
        } else {
            echo "<div style='color: red; font-weight: bold;'>✗ Email sending failed. Check error logs above.</div>\n";
        }
    }
}

echo "</pre>";

?>

<form method="POST" style="margin-top: 20px;">
    <label>Test Email Address: <input type="email" name="test_email" required placeholder="your-email@gmail.com"></label>
    <button type="submit">Send Test Email</button>
</form>

<p><strong>Note:</strong> After testing, set MAIL_SMTP_DEBUG back to 0 in config_mail.php for production.</p>
