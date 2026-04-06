<?php


// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'archive_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Constants
// Update APP_URL to match your local XAMPP path
define('APP_NAME', 'Quezon City Public Library - Archive System');
define('APP_URL', 'http://localhost/ArchiveSystemFilipiniana');
define('APP_VERSION', '1.0.0');

if (!function_exists('route_url')) {
    function route_url(string $name = 'home', array $query = []): string
    {
        static $routes = [
        'home' => '/',
        'browse' => '/browse',
        'collections' => '/collections',
        'reader' => '/read',
        'public-open' => '/open',
        'public-pdf-viewer' => '/pdf-viewer',
        'serve-file' => '/serve-file',
        'user-convert-mobi' => '/convert-mobi',
        'user-serve-cbz-image' => '/serve-cbz-image',
        'login' => '/login',
        'forgot-password' => '/forgot-password',
        'reset-password' => '/reset-password',
        'logout' => '/logout',
        'dashboard' => '/dashboard',
        'users' => '/users',
        'history' => '/history',
        'trash' => '/trash',
        'upload' => '/upload',
        'featured-collections' => '/featured-collections',
        'form-library' => '/form-library',
        'form-builder' => '/form-builder',
        'metadata-display' => '/metadata-display',
        'report' => '/report',
        'settings' => '/settings',
        'admin-reader' => '/admin/read',
        'admin-pdf-viewer' => '/admin/pdf-viewer',
        'admin-convert-mobi' => '/admin/convert-mobi',
        'admin-serve-cbz-image' => '/admin/serve-cbz-image',
        'admin-serve-file' => '/admin/serve-file',
        'worker' => '/admin/worker',
        'check-user-availability' => '/admin/check-user-availability',
        ];

        $path = $routes[$name] ?? '/' . ltrim($name, '/');
        $url = rtrim(APP_URL, '/') . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

// File upload settings
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf', 'mobi', 'epub', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'tiff', 'tif']);

// Calibre ebook-convert path (for MOBI to EPUB conversion)
// Update this path to match your Calibre installation, or leave empty if not installed
// Windows examples:
//   C:\Program Files\Calibre2\ebook-convert.exe
//   C:\xampp\htdocs\CalibrePortable\Calibre\ebook-convert.exe
// Mac: /Applications/calibre.app/Contents/MacOS/ebook-convert
// Linux: /usr/bin/ebook-convert
// Leave empty string '' if Calibre is not installed (MOBI files will offer download instead)
define('CALIBRE_CONVERT_PATH', 'C:\\xampp\\htdocs\\Calibre Portable\\Calibre\\ebook-convert.exe');

// Email SMTP Settings (Gmail)
// To use Gmail SMTP, you need to:
// 1. Enable 2-Step Verification in your Google Account
// 2. Generate an App Password at https://myaccount.google.com/apppasswords
// 3. Replace the values below with your Gmail and App Password
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'archivesystemfilipiniana@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'esth ctfj cuaq bswk'); // Your Gmail App Password
define('SMTP_FROM_NAME', 'Archive System Periodical');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}


?>
