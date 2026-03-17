<?php
/**
 * Login API Endpoint
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/functions.php';

header('Content-Type: application/json');

// Redirect if already logged in (shouldn't happen for API usually, but good check)
if (isLoggedIn()) {
    echo json_encode(['status' => 'success', 'message' => 'Already logged in', 'redirect' => APP_URL . '/admin_pages/dashboard.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input or POST data
$input = json_decode(file_get_contents('php://input'), true);
$username = sanitize($input['username'] ?? $_POST['username'] ?? '');
$password = $input['password'] ?? $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please enter both username and password.']);
    exit;
}

// Check user credentials
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    // Login successful
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['LAST_ACTIVITY'] = time();

    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Log activity
    logActivity($user['id'], 'login', $user['username']);

    echo json_encode(['status' => 'success', 'message' => 'Login successful', 'redirect' => APP_URL . '/admin_pages/dashboard.php']);
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
}
