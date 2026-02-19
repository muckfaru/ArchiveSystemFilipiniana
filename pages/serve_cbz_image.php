<?php
/**
 * Serve CBZ Image
 * Archive System - Quezon City Public Library
 * 
 * Serves a single image file from within a .cbz (zip) archive.
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';

// Validate inputs
$fileId = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
$imagePath = isset($_GET['image_path']) ? $_GET['image_path'] : '';

if (!$fileId || !$imagePath) {
    http_response_code(400);
    exit('Invalid request');
}

// Fetch file data to ensure it exists and user has access
$stmt = $pdo->prepare("SELECT file_path FROM newspapers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$cbzPath = __DIR__ . '/../' . $file['file_path'];

if (!file_exists($cbzPath)) {
    http_response_code(404);
    exit('Archive file not found on server');
}

// Open Zip Archive
$zip = new ZipArchive;
if ($zip->open($cbzPath) === TRUE) {
    // Check if image exists in zip
    $stat = $zip->statName($imagePath);

    if ($stat) {
        // Get image content
        $content = $zip->getFromName($imagePath);

        // Determine mime type based on extension
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $mime = 'image/jpeg'; // default
        if ($ext === 'png')
            $mime = 'image/png';
        if ($ext === 'gif')
            $mime = 'image/gif';
        if ($ext === 'webp')
            $mime = 'image/webp';

        // Serve content
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=86400'); // Cache for 1 day
        echo $content;
    } else {
        http_response_code(404);
        exit('Image not found in archive');
    }

    $zip->close();
} else {
    http_response_code(500);
    exit('Failed to open archive');
}
