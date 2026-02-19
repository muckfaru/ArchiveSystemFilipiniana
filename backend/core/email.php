<?php
/**
 * Simple Email Helper using PHPMailer
 * Archive System - Quezon City Public Library
 * 
 * This is a simplified wrapper for PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Send an email using SMTP
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body content
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Sender
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);

        // Recipient
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

/**
 * Generate a secure random token
 * 
 * @param int $length Token length
 * @return string
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Create a password reset token and store in database
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return string|false Token on success, false on failure
 */
function createPasswordResetToken($pdo, $userId)
{
    $token = generateToken();
    // Use MySQL's NOW() + INTERVAL for consistent timezone handling
    // Token valid for 24 hours

    // Invalidate any existing tokens for this user
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->execute([$userId]);

    // Create new token with expiration set by MySQL
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
    if ($stmt->execute([$userId, $token])) {
        return $token;
    }
    return false;
}

/**
 * Validate a password reset token
 * 
 * @param PDO $pdo Database connection
 * @param string $token Token to validate
 * @return array|false User data if valid, false if invalid
 */
function validatePasswordResetToken($pdo, $token)
{
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id, u.email, u.full_name, u.username
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Mark a password reset token as used
 * 
 * @param PDO $pdo Database connection
 * @param string $token Token to mark as used
 * @return bool
 */
function markTokenAsUsed($pdo, $token)
{
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    return $stmt->execute([$token]);
}

/**
 * Send password reset email
 * 
 * @param string $email Recipient email
 * @param string $name Recipient name
 * @param string $resetLink Password reset link
 * @return array Result of email send
 */
function sendPasswordResetEmail($email, $name, $resetLink)
{
    $subject = "Password Reset - " . APP_NAME;

    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background-color: #4C3939; padding: 20px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>Password Reset</h1>
        </div>
        <div style='padding: 30px; background-color: #f9f9f9;'>
            <p>Hello <strong>$name</strong>,</p>
            <p>We received a request to reset your password. Click the button below to set a new password:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background-color: #4C3939; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; display: inline-block;'>Reset Password</a>
            </div>
            <p style='color: #666; font-size: 14px;'>This link will expire in 24 hours.</p>
            <p style='color: #666; font-size: 14px;'>If you didn't request this, you can safely ignore this email.</p>
        </div>
        <div style='padding: 20px; text-align: center; color: #999; font-size: 12px;'>
            <p>" . APP_NAME . "</p>
        </div>
    </div>
    ";

    return sendEmail($email, $subject, $body);
}
