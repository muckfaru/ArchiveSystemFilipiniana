<?php
/**
 * File Metadata API
 * Returns metadata for a file with display configuration applied
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$fileId = $_GET['id'] ?? null;
$context = $_GET['context'] ?? 'modal'; // 'card' or 'modal'

if (!$fileId || !is_numeric($fileId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
    exit;
}

if (!in_array($context, ['card', 'modal'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid context']);
    exit;
}

try {
    // Get file basic info
    $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    
    // Get metadata with display configuration applied
    $metadata = getFileMetadataForDisplay($pdo, $fileId, $context);
    
    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $file['id'],
            'title' => $file['title'],
            'thumbnail_path' => $file['thumbnail_path'],
            'file_path' => $file['file_path'],
            'file_type' => $file['file_type'],
            'is_bulk_image' => $file['is_bulk_image'],
            'image_paths' => $file['image_paths']
        ],
        'metadata' => $metadata
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
