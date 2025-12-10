<?php
session_start();
require './db.php';
// We need the mail configuration for email sending to work.
require_once __DIR__ . '/../config_mail.php';
require_once './email_functions.php'; // Include email functions, which depend on the config

// ✅ Only staff can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'No application ID provided.'];
    header("Location: applicants.php");
    exit;
}

$application_id = (int)$_GET['id'];

// Use a transaction to ensure all operations succeed or fail together
$conn->beginTransaction();

try {
    // 1. Fetch application and user details
    $stmt = $conn->prepare(
        "SELECT a.user_id, a.business_name, u.name as applicant_name, u.email as applicant_email 
         FROM applications a 
         JOIN users u ON a.user_id = u.id 
         WHERE a.id = :id"
    );
    $stmt->execute([':id' => $application_id]);
    $app_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app_data) {
        throw new Exception("Application not found.");
    }

    // 2. Update the application status to 'complete' and mark as released
    $update_stmt = $conn->prepare(
        "UPDATE applications SET status = 'complete', permit_released_at = NOW(), updated_at = NOW() WHERE id = :id"
    );
    $update_stmt->execute([':id' => $application_id]);

    // 3. Create a notification for the applicant
    $business_name_safe = htmlspecialchars($app_data['business_name']);
    $notification_message = "Congratulations! Your business permit for '{$business_name_safe}' has been released. You can now view and download it from your dashboard.";
    $link = "../Applicant-dashboard/view_my_application.php?id={$application_id}";

    $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)");
    $notify_stmt->execute([':user_id' => $app_data['user_id'], ':message' => $notification_message, ':link' => $link]);

    // 4. Send an email notification
    try {
        // Determine protocol and host for absolute URL in email
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $absolute_link = "{$protocol}://{$host}/onlinebizpermit/Applicant-dashboard/view_my_application.php?id={$application_id}";

        $email_subject = "Your Business Permit for '{$business_name_safe}' is Ready!";
        
        $email_body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'>
                <h2 style='color: #28a745;'>Permit Released: '{$business_name_safe}'</h2>
                <p>Dear " . htmlspecialchars($app_data['applicant_name']) . ",</p>
                <p>" . $notification_message . "</p>
                <p>Please click the button below to access your application and download the permit:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . htmlspecialchars($absolute_link) . "' style='background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>View and Download Permit</a>
                </p>
                <p>We recommend keeping a digital and physical copy of your permit for your records.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 0.9em; color: #777;'>Thank you for using our service.<br><strong>The OnlineBizPermit Team</strong></p>
            </div>
        </div>";
        
        // Capture any debug output from PHPMailer
        ob_start();
        sendApplicationEmail($app_data['applicant_email'], $app_data['applicant_name'], $email_subject, $email_body);
        $debug_output = ob_get_clean(); // Capture and discard debug output

    } catch (Exception $e) {
        // Log email error but don't stop the process. The permit is still released.
        error_log("Email sending failed for released permit on application ID {$application_id}: " . $e->getMessage());
    }

    $conn->commit();

    $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => "Permit for application #{$application_id} has been released and the applicant has been notified."
    ];
} catch (Exception $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "Failed to release permit: " . $e->getMessage()];
    error_log("Failed to release permit for application ID {$application_id}: " . $e->getMessage());
}

header("Location: applicants.php");
exit;
?>