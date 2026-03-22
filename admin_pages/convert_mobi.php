<?php
/**
 * AJAX Endpoint for MOBI to EPUB Conversion
 * Archive System - Quezon City Public Library
 */

session_start();
require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/calibre.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get file ID from request
$fileId = $_GET['file_id'] ?? null;

if (!$fileId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File ID required']);
    exit;
}

// Get file from database
$stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

// Check if file is MOBI
if (strtolower($file['file_type']) !== 'mobi') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File is not MOBI format']);
    exit;
}

// Get full file path
$filePath = __DIR__ . '/../' . $file['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found on disk']);
    exit;
}

// Check if EPUB already exists
$existingEpub = getConvertedEpubPath($filePath);
if ($existingEpub) {
    $epubRelativePath = preg_replace('/\.mobi$/i', '.epub', $file['file_path']);
    echo json_encode([
        'success' => true,
        'already_exists' => true,
            'epub_url' => route_url('admin-serve-file', ['file' => $epubRelativePath])
    ]);
    exit;
}

// Perform conversion
$result = convertMobiToEpub($filePath);

if ($result['success']) {
    $epubRelativePath = preg_replace('/\.mobi$/i', '.epub', $file['file_path']);
    echo json_encode([
        'success' => true,
        'already_exists' => false,
            'epub_url' => route_url('admin-serve-file', ['file' => $epubRelativePath])
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
