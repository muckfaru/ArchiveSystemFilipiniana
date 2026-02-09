<?php
// Start buffering early
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth check
if (!isset($currentUser)) {
    http_response_code(403);
    ob_end_clean();
    exit;
}

$file = $_GET['file'] ?? '';
$file = str_replace(['..', '\\'], '', $file);

$filePath = realpath(__DIR__ . '/../' . $file);

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);
    ob_end_clean();
    exit;
}

// Must be a PDF
if (mime_content_type($filePath) !== 'application/pdf') {
    http_response_code(403);
    ob_end_clean();
    exit;
}

// Clean EVERYTHING before output
while (ob_get_level()) {
    ob_end_clean();
}

// Headers for preview
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('Accept-Ranges: bytes');

// Stream file safely
$fp = fopen($filePath, 'rb');
fpassthru($fp);
fclose($fp);
exit;
