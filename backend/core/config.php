<?php
/**
 * Database Configuration
 * Archive System - Quezon City Public Library
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'archive_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Constants
define('APP_NAME', 'Quezon City Public Library - Archive System');
define('APP_URL', 'http://localhost/qcpl/ArchiveSystemFilipiniana');
define('APP_VERSION', '1.0.0');

// File upload settings
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf', 'mobi', 'epub', 'txt', 'jpg', 'jpeg', 'png', 'tiff', 'tif']);

// Calibre ebook-convert path (for MOBI to EPUB conversion)
// Update this path to match your Calibre installation
// Default Windows path: C:\Program Files\Calibre2\ebook-convert.exe
// Default Mac path: /Applications/calibre.app/Contents/MacOS/ebook-convert
define('CALIBRE_CONVERT_PATH', 'C:\\xampp\\htdocs\\CalibrePortable\\Calibre\\ebook-convert.exe');

// Email SMTP Settings (Gmail)
// To use Gmail SMTP, you need to:
// 1. Enable 2-Step Verification in your Google Account
// 2. Generate an App Password at https://myaccount.google.com/apppasswords
// 3. Replace the values below with your Gmail and App Password
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);  // Changed from 587 to 465 (SSL)
define('SMTP_USERNAME', 'archivesystemfilipiniana@gmail.com');  // Replace with your Gmail
define('SMTP_PASSWORD', 'folx ljgj qnub zjxn');      // Replace with your App Password
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

// Set timezone
date_default_timezone_set('Asia/Manila');
