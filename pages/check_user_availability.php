<?php
require_once __DIR__ . '/../backend/core/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$response = ['status' => 'success'];
$excludeId = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $excludeId]);
    if ($stmt->fetch()) {
        $response['email_status'] = 'taken';
    } else {
        $response['email_status'] = 'available';
    }
}

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $excludeId]);
    if ($stmt->fetch()) {
        $response['username_status'] = 'taken';
    } else {
        $response['username_status'] = 'available';
    }
}

echo json_encode($response);
