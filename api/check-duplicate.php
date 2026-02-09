<?php
/**
 * Check Duplicate File API
 * Archive System - Quezon City Public Library
 * 
 * Checks if a file with the same name already exists in the system
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get filename from request
$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? '';

if (empty($filename)) {
    echo json_encode([
        'duplicate' => false,
        'message' => 'No filename provided'
    ]);
    exit;
}

// Check for existing file with same name
$stmt = $pdo->prepare("
    SELECT id, title, file_name, created_at 
    FROM newspapers 
    WHERE file_name = ? AND is_deleted = 0
    LIMIT 1
");
$stmt->execute([$filename]);
$existing = $stmt->fetch();

if ($existing) {
    echo json_encode([
        'duplicate' => true,
        'message' => 'A file with this name already exists',
        'existing_file' => [
            'id' => $existing['id'],
            'title' => $existing['title'],
            'uploaded_at' => date('M d, Y', strtotime($existing['created_at']))
        ]
    ]);
} else {
    echo json_encode([
        'duplicate' => false,
        'message' => 'File is unique'
    ]);
}
