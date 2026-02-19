<?php
/**
 * Login Page Controller
 * Archive System - Quezon City Public Library
 * 
 * This file acts as the controller for the login page.
 * It handles the initial page load and authentication checks.
 * The actual login logic is handled by backend/api/auth/login.php
 * and the view is in views/login.php
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

// Include the view
require_once __DIR__ . '/views/login.php';
?>