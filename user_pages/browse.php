<?php
/**
 * Browse Page Controller
 * Archive System - Quezon City Public Library
 * 
 * Browse all archives with advanced filters
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';

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

// --- Fetch Categories with Counts ---
$catSql = "SELECT cmv.field_value as id, cmv.field_value as name, COUNT(DISTINCT n.id) as count 
           FROM custom_metadata_values cmv
           INNER JOIN custom_metadata_fields cmf ON cmv.field_id = cmf.id
           LEFT JOIN newspapers n ON cmv.file_id = n.id AND n.deleted_at IS NULL
           WHERE cmf.field_label = 'Category'
           GROUP BY cmv.field_value
           ORDER BY cmv.field_value ASC";
$categoriesWithCounts = $pdo->query($catSql)->fetchAll();

// Total documents overall
$totalDocsSql = "SELECT COUNT(id) FROM newspapers WHERE deleted_at IS NULL";
$totalCollectionsCount = $pdo->query($totalDocsSql)->fetchColumn();

// Get languages for filter
$langSql = "SELECT cmv.field_value as id, cmv.field_value as name 
            FROM custom_metadata_values cmv
            INNER JOIN custom_metadata_fields cmf ON cmv.field_id = cmf.id
            INNER JOIN newspapers n ON cmv.file_id = n.id AND n.deleted_at IS NULL
            WHERE cmf.field_label = 'Language'
            GROUP BY cmv.field_value 
            ORDER BY cmv.field_value ASC";
$languages = $pdo->query($langSql)->fetchAll();

// Get editions for filter with counts
$editionSql = "SELECT cmv.field_value as edition, COUNT(DISTINCT n.id) as count 
               FROM custom_metadata_values cmv
               INNER JOIN custom_metadata_fields cmf ON cmv.field_id = cmf.id
               INNER JOIN newspapers n ON cmv.file_id = n.id
               WHERE cmf.field_label = 'Edition' AND n.deleted_at IS NULL 
               AND cmv.field_value IS NOT NULL AND cmv.field_value != ''
               GROUP BY cmv.field_value
               ORDER BY cmv.field_value ASC";
$editionsWithCounts = $pdo->query($editionSql)->fetchAll();

// Get min and max publication dates for the date range filter
$dateRangeSql = "SELECT 
                 MIN(STR_TO_DATE(cmv.field_value, '%Y-%m-%d')) as min_date, 
                 MAX(STR_TO_DATE(cmv.field_value, '%Y-%m-%d')) as max_date 
                 FROM custom_metadata_values cmv
                 INNER JOIN custom_metadata_fields cmf ON cmv.field_id = cmf.id
                 INNER JOIN newspapers n ON cmv.file_id = n.id
                 WHERE (cmf.field_label = 'Publication Date' OR cmf.field_label = 'Date Issued') 
                 AND n.deleted_at IS NULL 
                 AND cmv.field_value IS NOT NULL";
$dateRange = $pdo->query($dateRangeSql)->fetch();
$minYear = $dateRange['min_date'] ? date('Y', strtotime($dateRange['min_date'])) : 1850;
$maxYear = $dateRange['max_date'] ? date('Y', strtotime($dateRange['max_date'])) : date('Y');

// --- Build WHERE Clause ---
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

if (!empty($categoryFilter)) {
    $placeholders = implode(',', array_fill(0, count($categoryFilter), '?'));
    $whereClause .= " AND EXISTS (
        SELECT 1 FROM custom_metadata_values cmv2
        INNER JOIN custom_metadata_fields cmf2 ON cmv2.field_id = cmf2.id
        WHERE cmv2.file_id = n.id 
        AND cmf2.field_label = 'Category' 
        AND cmv2.field_value IN ($placeholders)
    )";
    $params = array_merge($params, $categoryFilter);
}

if (!empty($languageFilter)) {
    $placeholders = implode(',', array_fill(0, count($languageFilter), '?'));
    $whereClause .= " AND EXISTS (
        SELECT 1 FROM custom_metadata_values cmv2
        INNER JOIN custom_metadata_fields cmf2 ON cmv2.field_id = cmf2.id
        WHERE cmv2.file_id = n.id 
        AND cmf2.field_label = 'Language' 
        AND cmv2.field_value IN ($placeholders)
    )";
    $params = array_merge($params, $languageFilter);
}

if (!empty($editionFilter)) {
    $placeholders = implode(',', array_fill(0, count($editionFilter), '?'));
    $whereClause .= " AND EXISTS (
        SELECT 1 FROM custom_metadata_values cmv2
        INNER JOIN custom_metadata_fields cmf2 ON cmv2.field_id = cmf2.id
        WHERE cmv2.file_id = n.id 
        AND cmf2.field_label = 'Edition' 
        AND cmv2.field_value IN ($placeholders)
    )";
    $params = array_merge($params, $editionFilter);
}

if ($searchQuery) {
    $like = "%$searchQuery%";
    $whereClause .= "
        AND (
            n.title LIKE ?
         OR EXISTS (
                SELECT 1 FROM custom_metadata_values cmv2
                WHERE cmv2.file_id = n.id AND cmv2.field_value LIKE ?
            )
        )";
    $params[] = $like;
    $params[] = $like;
}

if ($dateFrom) {
    $whereClause .= " AND EXISTS (
        SELECT 1 FROM custom_metadata_values cmv2
        INNER JOIN custom_metadata_fields cmf2 ON cmv2.field_id = cmf2.id
        WHERE cmv2.file_id = n.id 
        AND (cmf2.field_label = 'Publication Date' OR cmf2.field_label = 'Date Issued') 
        AND STR_TO_DATE(cmv2.field_value, '%Y-%m-%d') >= STR_TO_DATE(?, '%Y-%m-%d')
    )";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereClause .= " AND EXISTS (
        SELECT 1 FROM custom_metadata_values cmv2
        INNER JOIN custom_metadata_fields cmf2 ON cmv2.field_id = cmf2.id
        WHERE cmv2.file_id = n.id 
        AND (cmf2.field_label = 'Publication Date' OR cmf2.field_label = 'Date Issued') 
        AND STR_TO_DATE(cmv2.field_value, '%Y-%m-%d') <= STR_TO_DATE(?, '%Y-%m-%d')
    )";
    $params[] = $dateTo;
}

// --- Count total for pagination ---
$countSql = "SELECT COUNT(DISTINCT n.id) as total
             FROM newspapers n
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

$sql = "SELECT DISTINCT n.*
        FROM newspapers n 
        $whereClause 
        $orderBy 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$documents = $stmt->fetchAll();

// Fetch custom metadata for all documents
if (!empty($documents)) {
    $fileIds = array_column($documents, 'id');
    
    // Get visible fields for modal display
    $modalFields = getVisibleFields($pdo, 'modal');
    
    $customMetadataByFile = getCustomMetadataValuesForFiles($fileIds);

    // Attach custom metadata to each document
    foreach ($documents as &$doc) {
        $doc['custom_metadata'] = $customMetadataByFile[$doc['id']] ?? [];
        
        // Filter metadata for modal display
        $doc['modal_metadata'] = [];
        foreach ($modalFields as $field) {
            if (isset($doc['custom_metadata'][$field['field_id']])) {
                // Convert field label to snake_case for icon mapping
                $fieldName = strtolower(str_replace([' ', '/'], ['_', '_'], $field['field_label']));
                $doc['modal_metadata'][] = [
                    'label' => $field['field_label'],
                    'value' => $doc['custom_metadata'][$field['field_id']],
                    'field_name' => $fieldName
                ];
            }
        }
    }
    unset($doc);
}

// --- Generate Filter Display Label with Remove Buttons ---
function generateFilterLabel($categoryFilter, $languageFilter, $editionFilter, $dateFrom, $dateTo, $categories, $languages, $searchQuery, $sortFilter)
{
    $filters = [];

    // Helper function to build URL params
    $buildParams = function ($cats, $langs, $editions) use ($searchQuery, $dateFrom, $dateTo, $sortFilter) {
        $params = [];
        if ($searchQuery)
            $params[] = 'q=' . urlencode($searchQuery);
        foreach ($cats as $c)
            $params[] = 'category[]=' . urlencode($c);
        foreach ($langs as $l)
            $params[] = 'language[]=' . urlencode($l);
        foreach ($editions as $e)
            $params[] = 'edition[]=' . urlencode($e);
        if ($dateFrom)
            $params[] = 'date_from=' . urlencode($dateFrom);
        if ($dateTo)
            $params[] = 'date_to=' . urlencode($dateTo);
        if ($sortFilter)
            $params[] = 'sort=' . urlencode($sortFilter);
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
function formatNumberShortcut($n)
{
    if ($n < 1000)
        return $n;
    $number = floor($n / 100) / 10;
    return $number . 'k';
}

// Helper function to highlight search terms
function highlightSearch($text, $query)
{
    if (!$query || empty(trim($query))) {
        return htmlspecialchars($text);
    }
    $safe = htmlspecialchars($text);
    $safeQ = preg_quote(htmlspecialchars($query), '/');
    return preg_replace('/(' . $safeQ . ')/iu', '<mark style="background: #FFF176; padding: 0 2px; border-radius: 2px;">$1</mark>', $safe);
}

// --- Include View ---
include __DIR__ . '/../views/browse.php';
?>