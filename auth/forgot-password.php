<?php
/**
 * Forgot Password Controller
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';
require_once __DIR__ . '/../backend/core/email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(route_url('dashboard'));
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

function forgotPasswordWantsJson(): bool
{
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($acceptHeader, 'application/json') !== false
        || strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
}

function forgotPasswordRespondJson(bool $success, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

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
            // Log for debugging
            error_log("Password reset requested for user: {$user['email']} (ID: {$user['id']})");
            
            // Create reset token
            $token = createPasswordResetToken($pdo, $user['id']);

            if ($token) {
                // Generate reset link
                $resetLink = route_url('reset-password', ['token' => $token]);
                
                // Log the reset link for debugging
                error_log("Reset link generated: $resetLink");

                // Send email
                $result = sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);

                if ($result['success']) {
                    error_log("Password reset email sent successfully to: {$user['email']}");
                    $showSuccessModal = true;
                } else {
                    // Log the error for debugging
                    error_log("Password reset email failed for {$user['email']}: " . $result['message']);
                    $showErrorModal = true;
                    $errorMessage = 'Failed to send email. Error: ' . $result['message'];
                }
            } else {
                error_log("Failed to create reset token for user ID: {$user['id']}");
                $showErrorModal = true;
                $errorMessage = 'An error occurred creating reset token. Please try again.';
            }
        } else {
            // Log for debugging - email not found
            error_log("Password reset requested for non-existent email: $email");
            // For security, show success even if email doesn't exist
            $showSuccessModal = true;
        }
    }

    if (forgotPasswordWantsJson()) {
        if ($showErrorModal) {
            forgotPasswordRespondJson(false, $errorMessage, 400);
        }

        forgotPasswordRespondJson(true, 'If that email is registered, a reset link has been sent.');
    }
}

// Load View
include __DIR__ . '/../views/forgot-password.php';
