<?php
/**
 * Entry Point
 * Archive System - Quezon City Public Library
 *
 * The default entry point always shows the public reader page.
 * Admin login is at login.php
 * Admin dashboard is at dashboard.php
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

// Redirect logged-in users to the dashboard
if (isLoggedIn()) {
    redirect(APP_URL . '/admin_pages/dashboard.php');
}

// Always redirect to the public reader landing page if not logged in
redirect(APP_URL . '/user_pages/public.php');
?>