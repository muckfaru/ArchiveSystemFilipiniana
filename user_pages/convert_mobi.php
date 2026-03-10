<?php
/**
 * AJAX Endpoint for MOBI to EPUB Conversion (Public)
 * Archive System - Quezon City Public Library
 * No authentication required - public users can trigger conversion.
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';
require_once __DIR__ . '/../backend/core/calibre.php';

header('Content-Type: application/json');

// Get file ID from request
$fileId = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;

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
        'epub_url' => '../serve_file.php?file=' . urlencode($epubRelativePath)
    ]);
    exit;
}

// Check if Calibre is available
if (!isCalibreAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Calibre ebook-convert is not available.']);
    exit;
}

// Perform conversion
$result = convertMobiToEpub($filePath);

if ($result['success']) {
    $epubRelativePath = preg_replace('/\.mobi$/i', '.epub', $file['file_path']);
    echo json_encode([
        'success' => true,
        'already_exists' => false,
        'epub_url' => '../serve_file.php?file=' . urlencode($epubRelativePath)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
