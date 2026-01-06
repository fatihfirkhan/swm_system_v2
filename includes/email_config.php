<?php
/**
 * Email Configuration for WasteTrack System
 * 
 * IMPORTANT: Update the settings below with your actual email credentials
 * 
 * For Gmail:
 * 1. Enable 2-Factor Authentication on your Google Account
 * 2. Go to Google Account > Security > App Passwords
 * 3. Generate new App Password for "Mail"
 * 4. Use that password below
 */

// Email Settings
define('SMTP_HOST', 'smtp.gmail.com');          // SMTP server host
define('SMTP_PORT', 587);                        // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_SECURE', 'tls');                    // Encryption: 'tls' or 'ssl'
define('SMTP_AUTH', true);                       // Use SMTP authentication

// IMPORTANT: Change these to your actual email credentials
// ============================================================
// AWAK PERLU TUKAR 2 BARIS NI:
define('SMTP_USERNAME', 'your-email@gmail.com');   // <- Tukar ke Gmail awak
define('SMTP_PASSWORD', 'your-app-password');      // <- Tukar ke App Password
// ============================================================

// Sender Information
define('EMAIL_FROM_ADDRESS', 'noreply@wastetrack.me');
define('EMAIL_FROM_NAME', 'WasteTrack System');

// System Settings
define('SITE_NAME', 'WasteTrack');
define('SITE_URL', 'http://wastetrack.me');

/**
 * Alternative: Use local XAMPP mail (for testing only)
 * 
 * To enable mail in XAMPP:
 * 1. Open C:\xampp\php\php.ini
 * 2. Find [mail function] section
 * 3. Set: SMTP = localhost
 * 4. Set: smtp_port = 25
 * 5. Set: sendmail_from = your-email@domain.com
 * 
 * Or use Mailhog/Mailtrap for testing
 */

// Debug mode - set to true to log email details
define('EMAIL_DEBUG', true);
?>
