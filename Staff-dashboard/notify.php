<?php
// Page-specific variables
$page_title = 'Send Notification';
$current_page = 'applicants'; // This page is accessed from applicants, so mark it as active

// Include header (handles session, DB, and auth)
require_once './staff_header.php';

// Include mail config and functions
require_once __DIR__ . '/../config_mail.php';
require_once './email_functions.php';

$application_id = null;
$app_data = null;
$flash_message = '';

// --- Get Application ID and Data ---
if (isset($_GET['application_id'])) {
    $application_id = (int)$_GET['application_id'];
} elseif (isset($_POST['application_id'])) {
    $application_id = (int)$_POST['application_id'];
}

if ($application_id) {
  $stmt = $conn->prepare("SELECT a.user_id, a.business_name, u.name as applicant_name, u.email as applicant_email FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = :id");
  $stmt->execute([':id' => $application_id]);
  $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$app_data) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Application not found.'];
    header("Location: applicants.php");
    exit;
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $subject = trim($_POST['subject']);
    $message_body = trim($_POST['message']);

    if (empty($subject) || empty($message_body)) {
        $flash_message = '<div class="message error">Subject and message cannot be empty.</div>';
    } else {
        $conn->beginTransaction();
        try {
            // 1. Create an in-app notification
            $link = "../Applicant-dashboard/view_my_application.php?id={$application_id}";
            $notification_message = "You have a new message from staff regarding your application for '" . htmlspecialchars($app_data['business_name']) . "'.";
            
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)");
            $notify_stmt->execute([':user_id' => $app_data['user_id'], ':message' => $notification_message, ':link' => $link]);

            // 2. Send an email notification
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $absolute_link = "{$protocol}://{$host}/onlinebizpermit/Applicant-dashboard/view_my_application.php?id={$application_id}";

            $email_body_html = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'>
                    <h2 style='color: #4a69bd;'>" . htmlspecialchars($subject) . "</h2>
                    <p>Dear " . htmlspecialchars($app_data['applicant_name']) . ",</p>
                    <p>You have received a message from our team regarding your application for <strong>" . htmlspecialchars($app_data['business_name']) . "</strong>.</p>
                    <div style='background-color: #f8f9fa; border-left: 4px solid #4a69bd; padding: 15px; margin: 20px 0;'>
                        " . nl2br(htmlspecialchars($message_body)) . "
                    </div>
                    <p>You can view your application and respond if necessary by clicking the button below:</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='" . htmlspecialchars($absolute_link) . "' style='background-color: #4a69bd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>View My Application</a>
                    </p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 0.9em; color: #777;'>Thank you for using our service.<br><strong>The OnlineBizPermit Team</strong></p>
                </div>
            </div>";

            // Capture any debug output from PHPMailer
            ob_start();
            sendApplicationEmail($app_data['applicant_email'], $app_data['applicant_name'], $subject, $email_body_html);
            $debug_output = ob_get_clean(); // Capture and discard debug output
            
            $conn->commit();

            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Notification sent successfully to ' . htmlspecialchars($app_data['applicant_name']) . '.'];
            header("Location: applicants.php");
            exit;

        } catch (Exception $e) {
          if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log("Notification sending failed for application ID {$application_id}: " . $e->getMessage());
            $flash_message = '<div class="message error">Failed to send notification. Please try again. Error: ' . $e->getMessage() . '</div>';
        }
    }
}

?>

<?php require_once './staff_sidebar.php'; ?>

<!-- Main Content -->
<div class="main">
  <header class="header">
    <div class="header-left">
      <div>
        <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
          <i class="fas fa-paper-plane" style="color: var(--accent-color);"></i>
          Send Notification
        </h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
          Send notification to <?= htmlspecialchars($app_data['applicant_name']) ?> for '<?= htmlspecialchars($app_data['business_name']) ?>'
        </p>
      </div>
    </div>
  </header>

      <?= $flash_message ?>

      <div class="form-container">
        <form action="notify.php" method="POST">
          <input type="hidden" name="application_id" value="<?= htmlspecialchars($application_id) ?>">
          
          <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" required value="Regarding your application for '<?= htmlspecialchars($app_data['business_name']) ?>'">
          </div>
          
          <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" required placeholder="Enter your message to the applicant..."></textarea>
          </div>
          
          <div class="form-actions">
            <a href="applicants.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" name="send_notification" class="btn">
              <i class="fas fa-paper-plane"></i> Send Notification
            </button>
          </div>
        </form>
      </div>
</div>

<style>
  .form-container { 
    background: var(--card-bg); 
    padding: 30px; 
    border-radius: 12px; 
    box-shadow: var(--shadow-sm); 
    max-width: 800px; 
    margin: 0 auto; 
    border: 1px solid var(--border-color);
  }
  .form-group { margin-bottom: 20px; }
  .form-group label { 
    display: block; 
    margin-bottom: 8px; 
    font-weight: 600; 
    color: var(--text-primary); 
  }
  .form-group input[type="text"], 
  .form-group textarea { 
    width: 100%; 
    padding: 12px; 
    border: 1px solid var(--border-color); 
    border-radius: 8px; 
    font-size: 14px; 
    transition: border-color 0.2s; 
    background: var(--card-bg);
    color: var(--text-primary);
  }
  .form-group input[type="text"]:focus, 
  .form-group textarea:focus { 
    outline: none; 
    border-color: var(--primary); 
    box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.1); 
  }
  .form-group textarea { 
    min-height: 150px; 
    resize: vertical; 
    font-family: inherit;
  }
  .form-actions { 
    display: flex; 
    justify-content: flex-end; 
    gap: 10px; 
    margin-top: 30px; 
  }
  .message { 
    padding: 15px 20px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    font-weight: 500; 
  }
  .message.success { 
    background: #d4edda; 
    color: #155724; 
    border: 1px solid #c3e6cb; 
  }
  .message.error { 
    background: #f8d7da; 
    color: #721c24; 
    border: 1px solid #f5c6cb; 
  }
</style>

<?php require_once './staff_footer.php'; ?>