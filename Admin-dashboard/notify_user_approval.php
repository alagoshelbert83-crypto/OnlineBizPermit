<?php
// This file handles sending notifications to users when their account is approved/rejected
// You can integrate this with email services or other notification systems

function sendApprovalNotification($userEmail, $userName, $status, $conn = null) {
    // For now, we'll just log the notification
    // In a production environment, you would integrate with email services like PHPMailer, SendGrid, etc.
    
    $message = "User {$userName} ({$userEmail}) account has been {$status}";
    
    // Log to database if connection is provided
    if ($conn) {
        try {
            $stmt = $conn->prepare("INSERT INTO notifications (type, recipient_email, message, created_at) VALUES (?, ?, ?, NOW())");
            $notificationType = $status === 'approved' ? 'account_approved' : 'account_rejected';
            $stmt->execute([$notificationType, $userEmail, $message]);
        } catch (Exception $e) {
            // Table might not exist, continue without error
            error_log('notify_user_approval DB insert failed: ' . $e->getMessage());
        }
    }
    
    // Log to file for debugging
    error_log("NOTIFICATION: " . $message);
    
    // TODO: Integrate with email service
    // Example with PHPMailer:
    /*
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'your-smtp-host';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email';
        $mail->Password = 'your-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('noreply@onlinebizpermit.com', 'OnlineBizPermit');
        $mail->addAddress($userEmail, $userName);
        
        $mail->isHTML(true);
        $mail->Subject = $status === 'approved' ? 'Account Approved' : 'Account Status Update';
        
        if ($status === 'approved') {
            $mail->Body = "
                <h2>Account Approved!</h2>
                <p>Dear {$userName},</p>
                <p>Your account has been approved and you can now access the applicant dashboard.</p>
                <p><a href='http://your-domain/Applicant-dashboard/login.php'>Login to your account</a></p>
                <p>Best regards,<br>OnlineBizPermit Team</p>
            ";
        } else {
            $mail->Body = "
                <h2>Account Status Update</h2>
                <p>Dear {$userName},</p>
                <p>Unfortunately, your account has been rejected. Please contact support for more information.</p>
                <p>Best regards,<br>OnlineBizPermit Team</p>
            ";
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
    */
    
    return true; // Placeholder return
}

// Create notifications table if it doesn't exist
function createNotificationsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL
    )";
    
    try {
        $conn->query($sql);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create notifications table: " . $e->getMessage());
        return false;
    }
}
?>
