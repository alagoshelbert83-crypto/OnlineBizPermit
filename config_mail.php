<?php
/**
 * PHPMailer Configuration for Brevo (formerly Sendinblue)
 *
 * Make sure you have:
 * 1. Verified your sender email in Brevo (e.g., alagoshelbert83@gmail.com).
 * 2. Generated an SMTP key in Brevo Dashboard → SMTP & API → SMTP → Generate New SMTP Key.
 */

// --- Main Email Switch ---
define('MAIL_SMTP_ENABLED', true);

// --- Application URL ---
define('APP_BASE_URL', 'https://onlinebizpermit.vercel.app');

// --- SMTP Debugging ---
// 0 = off (recommended for production)
// 2 = detailed debug info (use only for testing)
define('MAIL_SMTP_DEBUG', 0);

// --- Brevo SMTP Server Settings ---
// Check environment variables first (Vercel uses SMTP_HOST, SMTP_PORT, etc.)
// Fallback to MAIL_SMTP_* if not found, then to defaults
define('MAIL_SMTP_HOST', getenv('SMTP_HOST') ?: getenv('MAIL_SMTP_HOST') ?: 'smtp-relay.brevo.com');
define('MAIL_SMTP_PORT', (int)(getenv('SMTP_PORT') ?: getenv('MAIL_SMTP_PORT') ?: 587));
define('MAIL_SMTP_SECURE', getenv('SMTP_SECURE') ?: getenv('MAIL_SMTP_SECURE') ?: 'tls');

// --- SMTP Authentication ---
define('MAIL_SMTP_AUTH', true);

// IMPORTANT:
// Use your Brevo **account login email** (the one you sign in with),
// and your **SMTP key** from Brevo → SMTP & API.
// Check Vercel environment variables first (SMTP_USER, SMTP_PASS)
// Then check MAIL_SMTP_* variables, then fallback to defaults
define('MAIL_SMTP_USERNAME', getenv('SMTP_USER') ?: getenv('MAIL_SMTP_USERNAME') ?: '9a9125001@smtp-brevo.com');
define('MAIL_SMTP_PASSWORD', getenv('SMTP_PASS') ?: getenv('MAIL_SMTP_PASSWORD') ?: 'YOUR_SMTP_PASSWORD_HERE');

// --- Sender Information ---
define('MAIL_FROM_EMAIL', 'alagoshelbert83@gmail.com'); // verified Brevo sender
define('MAIL_FROM_NAME', 'OnlineBizPermit Support');
?>
