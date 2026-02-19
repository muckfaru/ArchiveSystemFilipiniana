<?php
/**
 * Reset Password Controller
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';
require_once __DIR__ . '/backend/core/email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

$token = $_GET['token'] ?? '';
$showSuccessModal = false;
$showErrorModal = false;
$errorMessage = '';
$validToken = false;
$tokenData = null;

// Validate token
if (!empty($token)) {
    $tokenData = validatePasswordResetToken($pdo, $token);
    if ($tokenData) {
        $validToken = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $showErrorModal = true;
        $errorMessage = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $showErrorModal = true;
        $errorMessage = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $showErrorModal = true;
        $errorMessage = 'Passwords do not match.';
    } else {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

        if ($stmt->execute([$hashedPassword, $tokenData['user_id']])) {
            // Mark token as used
            markTokenAsUsed($pdo, $token);

            // Log activity
            logActivity($tokenData['user_id'], 'password_reset', 'Password reset via email');

            $showSuccessModal = true;
            $validToken = false;
        } else {
            $showErrorModal = true;
            $errorMessage = 'An error occurred. Please try again.';
        }
    }
}

// Load View
include __DIR__ . '/views/reset-password.php';