<?php
/**
 * AJAX Duplicate Check Endpoint
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check if request is AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$title = sanitize($_POST['title'] ?? '');
$fileName = sanitize($_POST['file_name'] ?? '');

if (empty($title) && empty($fileName)) {
    echo json_encode(['error' => 'Title or filename required']);
    exit;
}

$isDuplicate = false;
$duplicateType = '';

// Check for duplicate title
if (!empty($title)) {
    $stmt = $pdo->prepare("SELECT id FROM newspapers WHERE title = ? AND deleted_at IS NULL");
    $stmt->execute([$title]);
    if ($stmt->fetch()) {
        $isDuplicate = true;
        $duplicateType = 'title';
    }
}

// Check for duplicate file name
if (!empty($fileName) && !$isDuplicate) {
    if (checkDuplicateFile($fileName)) {
        $isDuplicate = true;
        $duplicateType = 'filename';
    }
}

echo json_encode([
    'success' => true,
    'is_duplicate' => $isDuplicate,
    'duplicate_type' => $duplicateType,
    'message' => $isDuplicate
        ? ($duplicateType === 'title' ? 'A document with this title already exists.' : 'A file with this name already exists.')
        : 'No duplication found. Ready for upload!'
]);
