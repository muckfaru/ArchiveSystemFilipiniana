<?php
/**
 * Helper Functions
 * Archive System - Quezon City Public Library
 */

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user data
 */
function getCurrentUser()
{
    global $pdo;
    if (!isLoggedIn())
        return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Redirect to a URL
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * Log activity
 */
function logActivity($userId, $action, $targetTitle = null, $referenceId = null)
{
    global $pdo;
    $logTitle = $targetTitle;
    if ($referenceId) {
        $logTitle .= " (ID: $referenceId)";
    }
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, target_title) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $logTitle]);
}

/**
 * Format file size
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d h:i A')
{
    return date($format, strtotime($date));
}

/**
 * Sanitize input
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate random string
 */
function generateRandomString($length = 10)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get setting value
 */
function getSetting($key, $default = null)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}

/**
 * Update setting value
 */
function updateSetting($key, $value)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
    return $stmt->execute([$value, $key]);
}

/**
 * Get all categories
 */
function getCategories()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get all languages (prioritize English, Filipino, Tagalog first)
 */
function getLanguages()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT * FROM languages 
        ORDER BY 
            CASE 
                WHEN name = 'English' THEN 1
                WHEN name = 'Filipino' THEN 2
                WHEN name = 'Tagalog' THEN 3
                ELSE 4
            END,
            name ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Count total archives (not deleted)
 */
function countArchives()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM newspapers WHERE deleted_at IS NULL");
    return $stmt->fetch()['total'];
}

/**
 * Count total issues (pages)
 */
function countIssues()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COALESCE(SUM(page_count), 0) as total FROM newspapers WHERE deleted_at IS NULL");
    return $stmt->fetch()['total'];
}

/**
 * Get years covered
 */
function getYearsCovered()
{
    global $pdo;
    $stmt = $pdo->query("SELECT MIN(YEAR(publication_date)) as min_year, MAX(YEAR(publication_date)) as max_year FROM newspapers WHERE deleted_at IS NULL AND publication_date IS NOT NULL");
    $result = $stmt->fetch();
    if ($result['min_year'] && $result['max_year']) {
        return $result['min_year'] . '-' . $result['max_year'];
    }
    return 'N/A';
}

/**
 * Count categories that are actually used by newspapers
 */
function countCategories()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT category_id) as total 
        FROM newspapers 
        WHERE deleted_at IS NULL AND category_id IS NOT NULL
    ");
    return $stmt->fetch()['total'];
}

/**
 * Count active admins
 */
function countActiveAdmins()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    return $stmt->fetch()['total'];
}

/**
 * Count total admins
 */
function countTotalAdmins()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    return $stmt->fetch()['total'];
}

/**
 * Get recent newspapers
 */
function getRecentNewspapers($limit = 10)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT n.*, c.name as category_name 
        FROM newspapers n 
        LEFT JOIN categories c ON n.category_id = c.id 
        WHERE n.deleted_at IS NULL 
        ORDER BY n.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Check if file already exists (duplicate check)
 */
function checkDuplicateFile($fileName)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM newspapers WHERE file_name = ? AND deleted_at IS NULL");
    $stmt->execute([$fileName]);
    return $stmt->fetch() ? true : false;
}

/**
 * Get pagination data
 */
function getPagination($totalItems, $currentPage, $itemsPerPage)
{
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;

    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Display alert message
 */
function showAlert($type, $message)
{
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear alert message
 */
function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}
