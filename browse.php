<?php
/**
 * Browse Page Controller
 * Archive System - Quezon City Public Library
 * 
 * Browse all archives with advanced filters
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

// --- Pagination & Filters ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Match collections page

$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = isset($_GET['category']) ? (is_array($_GET['category']) ? $_GET['category'] : [$_GET['category']]) : [];
$categoryFilter = array_filter($categoryFilter, fn($c) => $c !== '' && $c !== 'all'); // Remove empty and 'all'
$languageFilter = isset($_GET['language']) ? (is_array($_GET['language']) ? $_GET['language'] : [$_GET['language']]) : [];
$languageFilter = array_filter($languageFilter, fn($l) => $l !== '' && $l !== 'all'); // Remove empty and 'all'
$editionFilter = isset($_GET['edition']) ? (is_array($_GET['edition']) ? $_GET['edition'] : [$_GET['edition']]) : [];
$editionFilter = array_filter($editionFilter, fn($e) => $e !== '' && $e !== 'all'); // Remove empty and 'all'
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortFilter = $_GET['sort'] ?? 'newest';
$pubDateExpr = "STR_TO_DATE(CONCAT(n.publication_date, IF(LENGTH(n.publication_date)=7, '-01', '')), '%Y-%m-%d')";

// --- Fetch Categories with Counts ---
$catSql = "SELECT c.id, c.name, COUNT(n.id) as count 
           FROM categories c 
           LEFT JOIN newspapers n ON c.id = n.category_id AND n.deleted_at IS NULL
           GROUP BY c.id, c.name
           ORDER BY c.name ASC";
$categoriesWithCounts = $pdo->query($catSql)->fetchAll();

// Total documents overall
$totalDocsSql = "SELECT COUNT(id) FROM newspapers WHERE deleted_at IS NULL";
$totalCollectionsCount = $pdo->query($totalDocsSql)->fetchColumn();

// Get languages for filter
$langSql = "SELECT l.id, l.name FROM languages l 
            INNER JOIN newspapers n ON l.id = n.language_id AND n.deleted_at IS NULL
            GROUP BY l.id, l.name ORDER BY l.name ASC";
$languages = $pdo->query($langSql)->fetchAll();

// Get editions for filter with counts
$editionSql = "SELECT edition, COUNT(*) as count FROM newspapers 
               WHERE deleted_at IS NULL AND edition IS NOT NULL AND edition != ''
               GROUP BY edition
               ORDER BY edition ASC";
$editionsWithCounts = $pdo->query($editionSql)->fetchAll();

// Get min and max publication dates for the date range filter
$dateRangeSql = "SELECT MIN(STR_TO_DATE(CONCAT(publication_date, IF(LENGTH(publication_date)=7, '-01', '')), '%Y-%m-%d')) as min_date, MAX(STR_TO_DATE(CONCAT(publication_date, IF(LENGTH(publication_date)=7, '-01', '')), '%Y-%m-%d')) as max_date 
                 FROM newspapers WHERE deleted_at IS NULL AND publication_date IS NOT NULL";
$dateRange = $pdo->query($dateRangeSql)->fetch();
$minYear = $dateRange['min_date'] ? date('Y', strtotime($dateRange['min_date'])) : 1850;
$maxYear = $dateRange['max_date'] ? date('Y', strtotime($dateRange['max_date'])) : date('Y');

// --- Build WHERE Clause ---
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

if (!empty($categoryFilter)) {
    $placeholders = implode(',', array_fill(0, count($categoryFilter), '?'));
    $whereClause .= " AND n.category_id IN ($placeholders)";
    $params = array_merge($params, $categoryFilter);
}

if (!empty($languageFilter)) {
    $placeholders = implode(',', array_fill(0, count($languageFilter), '?'));
    $whereClause .= " AND n.language_id IN ($placeholders)";
    $params = array_merge($params, $languageFilter);
}

if (!empty($editionFilter)) {
    $placeholders = implode(',', array_fill(0, count($editionFilter), '?'));
    $whereClause .= " AND n.edition IN ($placeholders)";
    $params = array_merge($params, $editionFilter);
}

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
         OR DATE_FORMAT($pubDateExpr, '%Y')       LIKE ?
         OR DATE_FORMAT($pubDateExpr, '%M %Y')    LIKE ?
         OR DATE_FORMAT($pubDateExpr, '%M %d, %Y') LIKE ?
        )";
    $params = array_merge($params, array_fill(0, 11, $like));
}

if ($dateFrom) {
    $whereClause .= " AND $pubDateExpr >= STR_TO_DATE(?, '%Y-%m-%d')";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereClause .= " AND $pubDateExpr <= STR_TO_DATE(?, '%Y-%m-%d')";
    $params[] = $dateTo;
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

// Get pagination data
$pagination = getPagination($totalResults, $page, $limit);
$totalPages = ceil($totalResults / $limit);
$currentPage = $page;

// --- Fetch Documents ---
// Determine ORDER BY clause based on sort filter
$orderBy = "ORDER BY n.created_at DESC"; // Default: newest first
switch ($sortFilter) {
    case 'oldest':
        $orderBy = "ORDER BY n.created_at ASC";
        break;
    case 'a-z':
        $orderBy = "ORDER BY n.title ASC";
        break;
    case 'z-a':
        $orderBy = "ORDER BY n.title DESC";
        break;
    case 'newest':
    default:
        $orderBy = "ORDER BY n.created_at DESC";
        break;
}

$sql = "SELECT n.*, c.name as category_name, l.name as language_name
        FROM newspapers n 
        LEFT JOIN categories c ON n.category_id = c.id 
        LEFT JOIN languages l ON n.language_id = l.id
        $whereClause 
        $orderBy 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$documents = $stmt->fetchAll();

// --- Generate Filter Display Label with Remove Buttons ---
function generateFilterLabel($categoryFilter, $languageFilter, $editionFilter, $dateFrom, $dateTo, $categories, $languages, $searchQuery, $sortFilter) {
    $filters = [];
    
    // Helper function to build URL params
    $buildParams = function($cats, $langs, $editions) use ($searchQuery, $dateFrom, $dateTo, $sortFilter) {
        $params = [];
        if ($searchQuery) $params[] = 'q=' . urlencode($searchQuery);
        foreach ($cats as $c) $params[] = 'category[]=' . urlencode($c);
        foreach ($langs as $l) $params[] = 'language[]=' . urlencode($l);
        foreach ($editions as $e) $params[] = 'edition[]=' . urlencode($e);
        if ($dateFrom) $params[] = 'date_from=' . urlencode($dateFrom);
        if ($dateTo) $params[] = 'date_to=' . urlencode($dateTo);
        if ($sortFilter) $params[] = 'sort=' . urlencode($sortFilter);
        return '?' . implode('&', $params);
    };
    
    // Categories (multiple)
    if (!empty($categoryFilter)) {
        foreach ($categoryFilter as $catId) {
            $catName = 'Unknown Category';
            foreach ($categories as $cat) {
                if ($cat['id'] == $catId) {
                    $catName = htmlspecialchars($cat['name']);
                    break;
                }
            }
            $remainingCats = array_filter($categoryFilter, fn($c) => $c != $catId);
            $removeUrl = $buildParams($remainingCats, $languageFilter, $editionFilter);
            
            $filters[] = '<span class="filter-tag filter-category">
                <i class="bi bi-tag-fill me-1"></i>' . $catName . '
                <a href="' . $removeUrl . '" class="filter-remove" title="Remove filter">
                    <i class="bi bi-x"></i>
                </a>
            </span>';
        }
    }
    
    // Languages (multiple)
    if (!empty($languageFilter)) {
        foreach ($languageFilter as $langId) {
            $langName = 'Unknown Language';
            foreach ($languages as $lang) {
                if ($lang['id'] == $langId) {
                    $langName = htmlspecialchars($lang['name']);
                    break;
                }
            }
            $remainingLangs = array_filter($languageFilter, fn($l) => $l != $langId);
            $removeUrl = $buildParams($categoryFilter, $remainingLangs, $editionFilter);
            
            $filters[] = '<span class="filter-tag filter-language">
                <i class="bi bi-translate me-1"></i>' . $langName . '
                <a href="' . $removeUrl . '" class="filter-remove" title="Remove filter">
                    <i class="bi bi-x"></i>
                </a>
            </span>';
        }
    }
    
    // Editions (multiple)
    if (!empty($editionFilter)) {
        foreach ($editionFilter as $edition) {
            $remainingEditions = array_filter($editionFilter, fn($e) => $e != $edition);
            $removeUrl = $buildParams($categoryFilter, $languageFilter, $remainingEditions);
            
            $filters[] = '<span class="filter-tag filter-edition">
                <i class="bi bi-book me-1"></i>' . htmlspecialchars($edition) . ' Edition
                <a href="' . $removeUrl . '" class="filter-remove" title="Remove filter">
                    <i class="bi bi-x"></i>
                </a>
            </span>';
        }
    }
    
    // Date range
    if ($dateFrom || $dateTo) {
        $dateLabel = '';
        if ($dateFrom && $dateTo) {
            $dateLabel = htmlspecialchars($dateFrom) . ' - ' . htmlspecialchars($dateTo);
        } elseif ($dateFrom) {
            $dateLabel = 'From ' . htmlspecialchars($dateFrom);
        } else {
            $dateLabel = 'Until ' . htmlspecialchars($dateTo);
        }
        $removeUrl = $buildParams($categoryFilter, $languageFilter, $editionFilter);
        
        $filters[] = '<span class="filter-tag filter-date">
            <i class="bi bi-calendar3 me-1"></i>' . $dateLabel . '
            <a href="' . $removeUrl . '" class="filter-remove" title="Remove filter">
                <i class="bi bi-x"></i>
            </a>
        </span>';
    }
    
    return $filters;
}

$activeFilters = generateFilterLabel($categoryFilter, $languageFilter, $editionFilter, $dateFrom, $dateTo, $categoriesWithCounts, $languages, $searchQuery, $sortFilter);
$hasActiveFilters = !empty($activeFilters);

// Helper function to format numbers
function formatNumberShortcut($n) {
    if ($n < 1000) return $n;
    $number = floor($n / 100) / 10;
    return $number . 'k';
}

// Helper function to highlight search terms
function highlightSearch($text, $query) {
    if (!$query || empty(trim($query))) {
        return htmlspecialchars($text);
    }
    $safe = htmlspecialchars($text);
    $safeQ = preg_quote(htmlspecialchars($query), '/');
    return preg_replace('/(' . $safeQ . ')/iu', '<mark style="background: #FFF176; padding: 0 2px; border-radius: 2px;">$1</mark>', $safe);
}

// --- Include View ---
include __DIR__ . '/views/browse.php';
?>
