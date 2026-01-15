<?php
/**
 * Approval email template renderer.
 * Edit this file to customize the approval email message.
 * You can also set a custom payment instruction text in settings (key: 'payment_instructions').
 */

function renderApprovalEmail(PDO $conn, array $appData, int $applicationId, float $assessed_total = 0, string $fee_rows_html = ''): string {
    $applicant_name = htmlspecialchars($appData['applicant_name'] ?? 'Applicant');
    $business_name = htmlspecialchars($appData['business_name'] ?? 'your business');

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $upload_link = "{$protocol}://{$host}/Applicant-dashboard/edit_application.php?id={$applicationId}";
    $view_link = "{$protocol}://{$host}/Applicant-dashboard/view_my_application.php?id={$applicationId}";

    // Load custom payment instructions from settings (if available)
    $payment_instructions = '';
    if (function_exists('get_setting')) {
        $payment_instructions = get_setting($conn, 'payment_instructions', '');
    }

    if (empty(trim($payment_instructions))) {
        $payment_instructions = "<p>Please pay the assessed fees at the Municipal Treasurer's Office or at authorized partner banks. If paying by bank transfer, include your Application ID in the reference and upload the official receipt or proof of transfer on your application page for verification.</p>";
    }

    $assessed_html = '';
    if (!empty($fee_rows_html)) {
        $assessed_html = "<div style='margin:20px 0;'><h4 style='margin:6px 0 10px 0;color:#1e3a8a;'>Assessed Fees</h4><table style='width:100%;border-collapse:collapse;border:1px solid #f1f1f1;border-radius:6px;overflow:hidden;'><tbody>{$fee_rows_html}<tr><td style='padding:8px;border-top:2px solid #eee;'><strong>Total</strong></td><td style='padding:8px;border-top:2px solid #eee;text-align:right;'><strong>₱ " . number_format($assessed_total,2) . "</strong></td></tr></tbody></table></div>";
    }

    $body = "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background-color: #ffffff;'>
            <h2 style='color: #1e3a8a; margin-top: 0;'>Official Approval Notice</h2>
            <p>Dear {$applicant_name},</p>
            <p>We are pleased to inform you that your application for <strong>{$business_name}</strong> (Application ID: <strong>#{$applicationId}</strong>) has been <strong style='color: #10b981;'>formally approved</strong> by the municipal licensing office.</p>
            <div style='background-color: #f8f9fa; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                <p style='margin:0;'><strong>Important:</strong> This approval is subject to payment of assessed permit fees. Please follow the payment instructions below to complete the process.</p>
            </div>

            {$assessed_html}

            <div style='margin-top:12px;'>
                <h4 style='margin:6px 0 8px 0;'>Payment Instructions</h4>
                {$payment_instructions}
            </div>

            <div style='text-align:center;margin-top:18px;'>
                <a href='" . htmlspecialchars($upload_link) . "' style='background-color:#4a69bd;color:#fff;padding:10px 16px;border-radius:5px;text-decoration:none;font-weight:600;'>Upload Official Receipt</a>
            </div>

            <div style='background:#f8fafc;padding:12px;border-radius:6px;margin-top:18px;'>
                <p style='margin:0;'><strong>Next steps:</strong> Once payment proof is uploaded, our staff will verify the payment and you will be notified when your official permit is ready for release.</p>
            </div>

            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . htmlspecialchars($view_link) . "' style='background-color: #4a69bd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>View My Application</a>
            </p>

            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 0.9em; color: #777; margin-bottom: 0;'>Sincerely,<br><strong>The OnlineBizPermit Team</strong></p>
        </div>
    </div>";

    return $body;
}
