<?php
/**
 * PHPMailer Configuration for Gmail SMTP
 *
 * Make sure you have:
 * 1. A Gmail account to send emails from
 * 2. Enabled 2-Step Verification on your Google account
 * 3. Generated an App Password:
 *    - Go to Google Account → Security → 2-Step Verification → App passwords
 *    - Generate a new app password for "Mail"
 *    - Use this app password (not your regular Gmail password) in MAIL_SMTP_PASSWORD
 */

// --- Main Email Switch ---
define('MAIL_SMTP_ENABLED', true);

// --- Application URL ---
define('APP_BASE_URL', 'https://onlinebizpermit.vercel.app');

// --- SMTP Debugging ---
// 0 = off (recommended for production)
// 2 = detailed debug info (use only for testing)
define('MAIL_SMTP_DEBUG', 0); // Set to 0 for production, 2 for debugging

// --- Gmail SMTP Server Settings ---
// Check environment variables first (Vercel uses SMTP_HOST, SMTP_PORT, etc.)
// Fallback to MAIL_SMTP_* if not found, then to defaults
define('MAIL_SMTP_HOST', getenv('SMTP_HOST') ?: getenv('MAIL_SMTP_HOST') ?: 'smtp.gmail.com');
// Try port 2525 first (SendGrid alternative, often not blocked), then 587, then 465
define('MAIL_SMTP_PORT', (int)(getenv('SMTP_PORT') ?: getenv('MAIL_SMTP_PORT') ?: 2525));
define('MAIL_SMTP_SECURE', getenv('SMTP_SECURE') ?: getenv('MAIL_SMTP_SECURE') ?: 'tls');

// --- SMTP Authentication ---
define('MAIL_SMTP_AUTH', true);

// IMPORTANT:
// Use your Gmail address and an App Password (not your regular password).
// To generate an App Password:
// 1. Enable 2-Step Verification on your Google account
// 2. Go to Google Account → Security → 2-Step Verification → App passwords
// 3. Generate a new app password for "Mail"
// 4. Use that 16-character app password here
// Check Vercel environment variables first (SMTP_USER, SMTP_PASS)
// Then check MAIL_SMTP_* variables, then fallback to defaults
define('MAIL_SMTP_USERNAME', getenv('SMTP_USER') ?: getenv('MAIL_SMTP_USERNAME') ?: 'your-email@gmail.com');
define('MAIL_SMTP_PASSWORD', getenv('SMTP_PASS') ?: getenv('MAIL_SMTP_PASSWORD') ?: 'YOUR_APP_PASSWORD_HERE');

// --- Sender Information ---
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'your-email@gmail.com'); // Your Gmail address
define('MAIL_FROM_NAME', 'OnlineBizPermit Support');
?>
