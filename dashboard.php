<?php
/**
 * Dashboard Page Controller
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/backend/core/auth.php';
require_once __DIR__ . '/backend/core/functions.php';

// Get stats
$totalArchives = countArchives();
$totalIssues = countIssues();
$yearsCovered = getYearsCovered();
// Count categories that are used by uploaded newspapers
$totalCategories = countCategories();

// Get categories and languages for filters
$categories = getCategories();
$languages = getLanguages();

// Get recent newspapers
$recentNewspapers = getRecentNewspapers(8);

// Get search
$searchQuery = $_GET['q'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$languageFilter = $_GET['language'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$pubDateExpr = "STR_TO_DATE(CONCAT(n.publication_date, IF(LENGTH(n.publication_date)=7, '-01', '')), '%Y-%m-%d')";

$searchResults = [];
if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo) {
    $sql = "SELECT n.*, c.name as category_name, l.name as language_name 
            FROM newspapers n 
            LEFT JOIN categories c ON n.category_id = c.id 
            LEFT JOIN languages l ON n.language_id = l.id 
            WHERE n.deleted_at IS NULL";
    $params = [];

    if ($searchQuery) {
        $sql .= " AND (n.title LIKE ? OR n.keywords LIKE ? OR n.description LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    if ($categoryFilter) {
        $sql .= " AND n.category_id = ?";
        $params[] = $categoryFilter;
    }

    if ($languageFilter) {
        $sql .= " AND n.language_id = ?";
        $params[] = $languageFilter;
    }

    if ($dateFrom) {
        $sql .= " AND $pubDateExpr >= STR_TO_DATE(?, '%Y-%m-%d')";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $sql .= " AND $pubDateExpr <= STR_TO_DATE(?, '%Y-%m-%d')";
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();
}

$pageTitle = 'Dashboard';
$pageCss = ['dashboard.css'];

// Include Layouts and View
include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/dashboard.php';

// Include Dashboard Specific JS
echo '<script src="' . APP_URL . '/assets/js/pages/dashboard.js"></script>';

include __DIR__ . '/views/layouts/footer.php';
?>