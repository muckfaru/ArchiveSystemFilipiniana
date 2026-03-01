<?php
/**
 * Public Landing Page Controller
 * Archive System - Quezon City Public Library
 * 
 * No login required. This is the default entry page for readers.
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

// Check if browse view is requested
$view = $_GET['view'] ?? 'home';

if ($view === 'browse') {
    // Redirect to browse.php
    header('Location: ' . APP_URL . '/browse.php');
    exit;
}

// --- Pagination & Filters ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // 4 columns x 3 rows

$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

// --- Fetch Categories for filter ---
$catSql = "SELECT c.id, c.name FROM categories c 
           INNER JOIN newspapers n ON c.id = n.category_id AND n.deleted_at IS NULL
           GROUP BY c.id, c.name ORDER BY c.name ASC";
$categories = $pdo->query($catSql)->fetchAll();

// --- Build WHERE Clause ---
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

if ($searchQuery) {
    $like = "%$searchQuery%";
    $whereClause .= "
        AND (
            n.title           LIKE ?
         OR n.keywords        LIKE ?
         OR n.description     LIKE ?
         OR n.publisher       LIKE ?
         OR n.edition         LIKE ?
         OR n.volume_issue    LIKE ?
         OR c.name            LIKE ?
         OR l.name            LIKE ?
         OR DATE_FORMAT(n.publication_date, '%Y')       LIKE ?
         OR DATE_FORMAT(n.publication_date, '%M %Y')    LIKE ?
         OR DATE_FORMAT(n.publication_date, '%M %d, %Y') LIKE ?
        )";
    $params = array_merge($params, array_fill(0, 11, $like));
}

if ($categoryFilter && $categoryFilter !== 'all') {
    $whereClause .= " AND n.category_id = ?";
    $params[] = $categoryFilter;
}

// --- Count total for pagination ---
$countSql = "SELECT COUNT(*) as total
             FROM newspapers n
             LEFT JOIN categories c ON n.category_id = c.id
             LEFT JOIN languages l ON n.language_id = l.id
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetch()['total'];
$totalPages = max(1, ceil($totalResults / $limit));
$currentPage = max(1, min($page, $totalPages));
$offset = ($currentPage - 1) * $limit;

// --- Fetch Documents ---
$sql = "SELECT n.*, c.name as category_name, l.name as language_name
        FROM newspapers n 
        LEFT JOIN categories c ON n.category_id = c.id 
        LEFT JOIN languages l ON n.language_id = l.id
        $whereClause 
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$documents = $stmt->fetchAll();

// --- Include View ---
include __DIR__ . '/views/public.php';
?>