<?php
/**
 * Check Password API - Used by settings page change-password modal
 * Returns JSON { match: true/false }
 */
require_once __DIR__ . '/../../../backend/core/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['password'])) {
    echo json_encode(['match' => false]);
    exit;
}

$inputPassword = $_POST['password'];
$isMatch = password_verify($inputPassword, $currentUser['password']);

echo json_encode(['match' => $isMatch]);
