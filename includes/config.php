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

// Application settings
define('APP_NAME', 'Quezon City Public Library');
define('APP_URL', 'http://localhost/AchiveSystemFilipiniana');
define('APP_VERSION', '1.0.0');

// File upload settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf', 'mobi', 'epub', 'txt', 'jpg', 'jpeg', 'png', 'tiff', 'tif']);

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
