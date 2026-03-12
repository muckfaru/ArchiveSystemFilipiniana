<?php
/**
 * Database Configuration (TEMPLATE)
 * Archive System - Quezon City Public Library
 * 
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to config.php: copy config.example.php config.php
 * 2. Update the values below to match your local environment
 * 3. NEVER commit config.php to Git (it's in .gitignore)
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'archive_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Constants
// Update APP_URL to match your local XAMPP path
define('APP_NAME', 'Quezon City Public Library - Archive System');
define('APP_URL', 'http://localhost/qcpl/ArchiveSystemFilipiniana');
define('APP_VERSION', '1.0.0');

// File upload settings
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf', 'mobi', 'epub', 'txt', 'jpg', 'jpeg', 'png', 'tiff', 'tif']);

// Calibre ebook-convert path (for MOBI to EPUB conversion)
// Update this path to match your Calibre installation, or leave empty if not installed
// Windows examples:
//   C:\Program Files\Calibre2\ebook-convert.exe
//   C:\xampp\htdocs\CalibrePortable\Calibre\ebook-convert.exe
// Mac: /Applications/calibre.app/Contents/MacOS/ebook-convert
// Linux: /usr/bin/ebook-convert
// Leave empty string '' if Calibre is not installed (MOBI files will offer download instead)
define('CALIBRE_CONVERT_PATH', '');

// Email SMTP Settings (Gmail)
// To use Gmail SMTP, you need to:
// 1. Enable 2-Step Verification in your Google Account
// 2. Generate an App Password at https://myaccount.google.com/apppasswords
// 3. Replace the values below with your Gmail and App Password
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', '');  // Your Gmail address
define('SMTP_PASSWORD', '');  // Your Gmail App Password
define('SMTP_FROM_NAME', 'Archive System Filipiniana');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
