<?php
/**
 * Admin Login Page
 * Archive System - Quezon City Public Library
 *
 * This is the Admin Login page.
 * The public reader landing page is at public.php (no login required).
 * The actual login logic is handled by backend/api/auth/login.php
 * and the view is in views/login.php
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';

// If already logged in, go to dashboard
if (isLoggedIn()) {
    redirect(route_url('dashboard'));
}

// Include the login view
require_once __DIR__ . '/../views/login.php';
?>
