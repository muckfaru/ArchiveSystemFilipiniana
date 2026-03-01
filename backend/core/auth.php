<?php
/**
 * Authentication Middleware
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(APP_URL . '/login.php');
}

// Get current user
$currentUser = getCurrentUser();

// Check if user exists and is active
if (!$currentUser || $currentUser['status'] !== 'active') {
    session_destroy();
    redirect(APP_URL . '/login.php');
}
