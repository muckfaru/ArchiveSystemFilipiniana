<?php
require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';

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
// Allow tools directory for converted files if needed, but primarily uploads
// Also allow hidden .epub conversions which might be side-by-side with original
// Just strict check on realpath to be inside root or specific allowed dirs needed?
// The previous check was: if ($uploadsDir === false || stripos($filePath, $uploadsDir) !== 0)
// This is safe enough.

if ($uploadsDir === false || stripos($filePath, $uploadsDir) !== 0) {
    // Extra check: allow if it's a converted file in the same dir?
    // Current system puts all files in uploads, so this is fine.
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
        $contentType = 'application/x-cbz';
        break;
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
            exit;
        }

        $length = $end - $offset + 1;
        http_response_code(206); // Partial Content
        header("Content-Range: bytes $offset-$end/$filesize");
    }
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');

// Clear buffers
while (ob_get_level())
    ob_end_clean();

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
