<?php
/**
 * Public File Serve Endpoint
 * Archive System - Quezon City Public Library
 * No login required — serves uploaded files publicly.
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

$file = $_GET['file'] ?? '';
$file = str_replace(['..', '\\'], '', $file); // Sanitize path traversal

if (!$file) {
    http_response_code(400);
    exit;
}

$filePath = realpath(__DIR__ . '/' . $file);

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Security: only serve files inside uploads/
$uploadsDir = realpath(__DIR__ . '/uploads');
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
    case 'gif':
        $contentType = 'image/gif';
        break;
    case 'webp':
        $contentType = 'image/webp';
        break;
}

$filesize = filesize($filePath);
$offset = 0;
$length = $filesize;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
        $offset = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $filesize - 1;
        if ($offset >= $filesize || $end >= $filesize || $offset > $end) {
            http_response_code(416);
            header("Content-Range: bytes */$filesize");
            exit;
        }
        $length = $end - $offset + 1;
        http_response_code(206);
        header("Content-Range: bytes $offset-$end/$filesize");
    }
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=86400');

while (ob_get_level())
    ob_end_clean();
$fp = fopen($filePath, 'rb');
fseek($fp, $offset);
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
