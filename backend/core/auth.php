<?php
/**
 * Authentication Middleware
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(APP_URL . '/auth/login.php');
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
    // Session expired
    session_unset();
    session_destroy();
    redirect(APP_URL . '/auth/login.php?expired=1');
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

// Get current user
$currentUser = getCurrentUser();

// Check if user exists and is active
if (!$currentUser || $currentUser['status'] !== 'active') {
    session_destroy();
    redirect(APP_URL . '/auth/login.php');
}
