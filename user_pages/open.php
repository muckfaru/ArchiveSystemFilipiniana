<?php
/**
 * Public document open compatibility route.
 *
 * Older public links may still hit /open. Keep it as a lightweight redirect
 * to the reader so those links continue to work without an extra database
 * lookup, analytics write, or file-type dispatch delay.
 */

require_once __DIR__ . '/../backend/core/config.php';

$rawId = trim((string) ($_GET['id'] ?? ''));

if ($rawId === '') {
    header('Location: ' . route_url('home'));
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE && function_exists('session_write_close')) {
    session_write_close();
}

header('Location: ' . route_url('reader', ['id' => $rawId]));
exit;
