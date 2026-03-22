<?php
/**
 * Entry Point
 * Archive System - Quezon City Public Library
 *
 * Clean URL home route.
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

// Redirect logged-in users to the dashboard
if (isLoggedIn()) {
    redirect(route_url('dashboard'));
}

// Render the public landing controller directly to avoid a rewrite loop on "/"
require_once __DIR__ . '/user_pages/public.php';
?>
