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

// Forgot password abuse protection
const FORGOT_PASSWORD_IP_LIMIT = 10; // max requests per IP in window
const FORGOT_PASSWORD_IP_WINDOW_SECONDS = 3600; // 1 hour
const FORGOT_PASSWORD_EMAIL_LIMIT = 3; // max requests per email in window
const FORGOT_PASSWORD_EMAIL_WINDOW_SECONDS = 1800; // 30 minutes

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

function ensureForgotPasswordRateLimitTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        email_hash CHAR(64) NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_reset_attempts_ip_created (ip_address, created_at),
        INDEX idx_password_reset_attempts_email_created (email_hash, created_at),
        INDEX idx_password_reset_attempts_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getClientIpAddress(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function hashForgotPasswordEmail(string $email): string
{
    return hash('sha256', strtolower(trim($email)));
}

function getForgotPasswordRateLimitStatus(PDO $pdo, string $ipAddress, ?string $emailHash): array
{
    $now = time();
    $ipWindowStart = date('Y-m-d H:i:s', $now - FORGOT_PASSWORD_IP_WINDOW_SECONDS);

    $ipStmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE ip_address = ? AND created_at >= ?");
    $ipStmt->execute([$ipAddress, $ipWindowStart]);
    $ipCount = (int) $ipStmt->fetchColumn();

    if ($ipCount >= FORGOT_PASSWORD_IP_LIMIT) {
        $ipOldestStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(MIN(created_at)) FROM password_reset_attempts WHERE ip_address = ? AND created_at >= ?");
        $ipOldestStmt->execute([$ipAddress, $ipWindowStart]);
        $oldestTs = (int) $ipOldestStmt->fetchColumn();
        $retryAfter = max(1, FORGOT_PASSWORD_IP_WINDOW_SECONDS - max(0, $now - $oldestTs));

        return [
            'blocked' => true,
            'retry_after' => $retryAfter,
            'message' => 'Too many password reset requests. Please try again later.'
        ];
    }

    if ($emailHash !== null && $emailHash !== '') {
        $emailWindowStart = date('Y-m-d H:i:s', $now - FORGOT_PASSWORD_EMAIL_WINDOW_SECONDS);
        $emailStmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE email_hash = ? AND created_at >= ?");
        $emailStmt->execute([$emailHash, $emailWindowStart]);
        $emailCount = (int) $emailStmt->fetchColumn();

        if ($emailCount >= FORGOT_PASSWORD_EMAIL_LIMIT) {
            $emailOldestStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(MIN(created_at)) FROM password_reset_attempts WHERE email_hash = ? AND created_at >= ?");
            $emailOldestStmt->execute([$emailHash, $emailWindowStart]);
            $oldestTs = (int) $emailOldestStmt->fetchColumn();
            $retryAfter = max(1, FORGOT_PASSWORD_EMAIL_WINDOW_SECONDS - max(0, $now - $oldestTs));

            return [
                'blocked' => true,
                'retry_after' => $retryAfter,
                'message' => 'Too many password reset requests for this account. Please try again later.'
            ];
        }
    }

    return [
        'blocked' => false,
        'retry_after' => 0,
        'message' => ''
    ];
}

function recordForgotPasswordAttempt(PDO $pdo, string $ipAddress, ?string $emailHash): void
{
    $stmt = $pdo->prepare("INSERT INTO password_reset_attempts (email_hash, ip_address, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$emailHash, $ipAddress]);

    // Keep table compact by removing old records past the max window.
    $maxWindow = max(FORGOT_PASSWORD_IP_WINDOW_SECONDS, FORGOT_PASSWORD_EMAIL_WINDOW_SECONDS) * 2;
    $cutoff = date('Y-m-d H:i:s', time() - $maxWindow);
    $cleanupStmt = $pdo->prepare("DELETE FROM password_reset_attempts WHERE created_at < ?");
    $cleanupStmt->execute([$cutoff]);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    try {
        ensureForgotPasswordRateLimitTable($pdo);
    } catch (PDOException $e) {
        error_log('Failed to ensure password_reset_attempts table: ' . $e->getMessage());
    }

    $clientIp = getClientIpAddress();
    $emailHash = filter_var($email, FILTER_VALIDATE_EMAIL) ? hashForgotPasswordEmail($email) : null;

    try {
        $rateLimit = getForgotPasswordRateLimitStatus($pdo, $clientIp, $emailHash);
        if (!empty($rateLimit['blocked'])) {
            $showErrorModal = true;
            $errorMessage = $rateLimit['message'];

            if (forgotPasswordWantsJson()) {
                header('Retry-After: ' . (int) ($rateLimit['retry_after'] ?? 60));
                forgotPasswordRespondJson(false, $errorMessage, 429);
            }
        }
    } catch (PDOException $e) {
        // Fail open to avoid blocking legitimate resets if limiter storage is unavailable.
        error_log('Forgot password rate-limit check failed: ' . $e->getMessage());
    }

    if (!$showErrorModal) {
        try {
            recordForgotPasswordAttempt($pdo, $clientIp, $emailHash);
        } catch (PDOException $e) {
            error_log('Forgot password attempt logging failed: ' . $e->getMessage());
        }
    }

    if ($showErrorModal) {
        // Rate-limited; response handling continues below.
    } elseif (empty($email)) {
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
