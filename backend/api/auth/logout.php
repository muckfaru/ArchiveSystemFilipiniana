<?php
/**
 * Logout API Endpoint
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/functions.php';

header('Content-Type: application/json');

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user) {
        logActivity($user['id'], 'logout', $user['username']);
    }
}

// Destroy session
session_destroy();

// If called via AJAX, return JSON. If called directly or via script expecting redirect, 
// the caller should handle the location change. 
// But since this is an API, strictly JSON is better.
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully', 'redirect' => APP_URL . '/index.php']);
exit;
