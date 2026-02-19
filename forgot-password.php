<?php
/**
 * Forgot Password Controller
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';
require_once __DIR__ . '/backend/core/email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

// Create password_resets table if not exists (Lazy migration check)
// ideally this should be in a separate migration script, but keeping here for "no function break" as per previous logic
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table might already exist, continue
}

$showSuccessModal = false;
$showErrorModal = false;
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $showErrorModal = true;
        $errorMessage = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $showErrorModal = true;
        $errorMessage = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Create reset token
            $token = createPasswordResetToken($pdo, $user['id']);

            if ($token) {
                // Generate reset link
                $resetLink = APP_URL . '/reset-password.php?token=' . $token;

                // Send email
                $result = sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);

                if ($result['success']) {
                    $showSuccessModal = true;
                } else {
                    $showErrorModal = true;
                    $errorMessage = 'Failed to send email. Please try again later.';
                }
            } else {
                $showErrorModal = true;
                $errorMessage = 'An error occurred. Please try again.';
            }
        } else {
            // For security, show success even if email doesn't exist
            $showSuccessModal = true;
        }
    }
}

// Load View
include __DIR__ . '/views/forgot-password.php';