<?php
// Start buffering early
ob_start();

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';

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

$filesize = filesize($filePath);
$offset = 0;
$length = $filesize;

// Handle Range Header
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
        $offset = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $filesize - 1;

        if ($offset >= $filesize || $end >= $filesize || $offset > $end) {
            http_response_code(416); // Range Not Satisfiable
            header("Content-Range: bytes */$filesize");
            ob_end_clean();
            exit;
        }

        $length = $end - $offset + 1;
        http_response_code(206); // Partial Content
        header("Content-Range: bytes $offset-$end/$filesize");
    }
}

// Headers for preview
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');

// Clean EVERYTHING before output
while (ob_get_level()) {
    ob_end_clean();
}

$fp = fopen($filePath, 'rb');
fseek($fp, $offset);

// Stream in chunks
$buffer = 8192;
$sent = 0;
while (!feof($fp) && $sent < $length) {
    $read = min($buffer, $length - $sent);
    echo fread($fp, $read);
    $sent += $read;
    flush();
}
fclose($fp);
exit;
