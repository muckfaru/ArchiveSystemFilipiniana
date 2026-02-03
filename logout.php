<?php
/**
 * Logout Handler
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Log activity before destroying session
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user) {
        logActivity($user['id'], 'logout', $user['username']);
    }
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: " . APP_URL . "/index.php");
exit;
