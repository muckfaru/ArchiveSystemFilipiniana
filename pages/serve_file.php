<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth check
if (!isset($currentUser)) {
    http_response_code(403);
    exit;
}

$file = $_GET['file'] ?? '';
$file = str_replace(['..', '\\'], '', $file); // Basic sanitization

if (!$file) {
    http_response_code(400);
    exit;
}

$filePath = realpath(__DIR__ . '/../' . $file);

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Security: Ensure file is within uploads directory
$uploadsDir = realpath(__DIR__ . '/../uploads');
if ($uploadsDir === false || stripos($filePath, $uploadsDir) !== 0) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = 'application/octet-stream';

switch ($ext) {
    case 'pdf':
        $contentType = 'application/pdf';
        break;
    case 'epub':
        $contentType = 'application/epub+zip';
        break;
    case 'mobi':
        $contentType = 'application/x-mobipocket-ebook';
        break;
    case 'jpg':
    case 'jpeg':
        $contentType = 'image/jpeg';
        break;
    case 'png':
        $contentType = 'image/png';
        break;
    case 'cbz':
        $contentType = 'application/x-cbz'; // or application/zip
        break;
}

// Headers
header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400'); // Cache for 1 day

// Output file
readfile($filePath);
exit;
