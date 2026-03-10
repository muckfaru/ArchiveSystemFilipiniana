<?php
/**
 * Dashboard Page Controller
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Get stats
$totalArchives = countArchives();
$totalIssues = countIssues();
$yearsCovered = getYearsCovered();
// Count categories that are used by uploaded newspapers
$totalCategories = countCategories();

// Get total views from newspaper_views table
try {
    $stmt = $pdo->query("SELECT SUM(view_count) as total FROM newspaper_views");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalViews = $result['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Failed to get total views: " . $e->getMessage());
    $totalViews = 0;
}

// Get categories and languages for filters
$categories = getCategories();
$languages = getLanguages();

// Get recent newspapers
$recentNewspapers = getRecentNewspapers(8);

// INTEGRATION: Load display configuration for file cards
$cardFields = getVisibleFields($pdo, 'card');

// INTEGRATION: Attach display-configured metadata to recent newspapers
if (!empty($recentNewspapers)) {
    $fileIds = array_column($recentNewspapers, 'id');
    foreach ($recentNewspapers as &$newspaper) {
        $newspaper['custom_metadata'] = getFileMetadataForDisplay($pdo, $newspaper['id'], 'card');
    }
}

// Get search
$searchQuery = $_GET['q'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$languageFilter = $_GET['language'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$searchResults = [];
if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo) {
    $sql = "SELECT DISTINCT n.*
            FROM newspapers n 
            LEFT JOIN custom_metadata_values cmv ON n.id = cmv.file_id
            WHERE n.deleted_at IS NULL";
    $params = [];

    if ($searchQuery) {
        $sql .= " AND (n.title LIKE ? OR cmv.field_value LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    if ($categoryFilter) {
        // Search in custom metadata for category field
        $sql .= " AND EXISTS (
            SELECT 1 FROM custom_metadata_values cmv2 
            INNER JOIN form_fields cmf ON cmv2.field_id = cmf.id
            WHERE cmv2.file_id = n.id 
            AND cmf.field_label = 'Category' 
            AND cmv2.field_value = ?
        )";
        $params[] = $categoryFilter;
    }

    if ($languageFilter) {
        // Search in custom metadata for language field
        $sql .= " AND EXISTS (
            SELECT 1 FROM custom_metadata_values cmv2 
            INNER JOIN form_fields cmf ON cmv2.field_id = cmf.id
            WHERE cmv2.file_id = n.id 
            AND cmf.field_label = 'Language' 
            AND cmv2.field_value = ?
        )";
        $params[] = $languageFilter;
    }

    if ($dateFrom) {
        // Search in custom metadata for publication_date field
        $sql .= " AND EXISTS (
            SELECT 1 FROM custom_metadata_values cmv2 
            INNER JOIN form_fields cmf ON cmv2.field_id = cmf.id
            WHERE cmv2.file_id = n.id 
            AND (cmf.field_label = 'Publication Date' OR cmf.field_label = 'Date Issued') 
            AND STR_TO_DATE(cmv2.field_value, '%Y-%m-%d') >= STR_TO_DATE(?, '%Y-%m-%d')
        )";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        // Search in custom metadata for publication_date field
        $sql .= " AND EXISTS (
            SELECT 1 FROM custom_metadata_values cmv2 
            INNER JOIN form_fields cmf ON cmv2.field_id = cmf.id
            WHERE cmv2.file_id = n.id 
            AND (cmf.field_label = 'Publication Date' OR cmf.field_label = 'Date Issued') 
            AND STR_TO_DATE(cmv2.field_value, '%Y-%m-%d') <= STR_TO_DATE(?, '%Y-%m-%d')
        )";
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();

    // INTEGRATION: Attach display-configured metadata to search results
    if (!empty($searchResults)) {
        $fileIds = array_column($searchResults, 'id');
        foreach ($searchResults as &$result) {
            $result['custom_metadata'] = getFileMetadataForDisplay($pdo, $result['id'], 'card');
        }
    }
}

$pageTitle = 'Dashboard';
$pageCss = ['dashboard.css'];

// Include Layouts and View
include __DIR__ . '/../views/layouts/header.php';
include __DIR__ . '/../views/dashboard.php';

// Include Dashboard Specific JS
echo '<script src="' . APP_URL . '/assets/js/admin_pages/dashboard.js"></script>';

include __DIR__ . '/../views/layouts/footer.php';
?>