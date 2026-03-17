<?php
/**
 * Public Landing Page Controller (PressReader-style Catalog)
 * Archive System - Quezon City Public Library
 * 
 * No login required. This is the default entry page for readers.
 * Shows horizontal shelf rows grouped by "Publication Type" metadata field.
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/admin_pages/dashboard.php');
}

// Check if browse view is requested
$view = $_GET['view'] ?? 'home';

if ($view === 'browse') {
    header('Location: ' . APP_URL . '/user_pages/browse.php');
    exit;
}

// --- Search & Filter ---
$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

// Date range inputs (expected format: mm/dd/yyyy)
$fromInput = trim($_GET['from'] ?? '');
$toInput   = trim($_GET['to'] ?? '');

function normalizeUsDateToSql(?string $value): ?string {
    if (!$value) return null;
    $dt = DateTime::createFromFormat('m/d/Y', $value);
    if (!$dt) return null;
    return $dt->format('Y-m-d');
}

$fromDateSql = normalizeUsDateToSql($fromInput);
$toDateSql   = normalizeUsDateToSql($toInput);

// --- Helper: attach metadata arrays to a list of documents ---
function attachMetadataToDocs(&$docs, $pdo, $cardFields, $modalFields) {
    if (empty($docs)) return;
    $fileIds = array_column($docs, 'id');
    $cardMetadata = getFilesMetadataForDisplay($pdo, $fileIds, 'card');
    $modalMetadata = getFilesMetadataForDisplay($pdo, $fileIds, 'modal');

    foreach ($docs as &$doc) {
        // custom_metadata is used by helper functions like getCategoryFromMetadata
        // we'll combine them but prioritize modal metadata for a fuller list
        $doc['custom_metadata'] = $modalMetadata[$doc['id']] ?? [];
        
        $doc['display_metadata'] = [];
        foreach ($cardMetadata[$doc['id']] ?? [] as $meta) {
            $doc['display_metadata'][] = [
                'label' => $meta['field_label'],
                'value' => $meta['field_value']
            ];
        }

        $doc['modal_metadata'] = [];
        foreach ($modalMetadata[$doc['id']] ?? [] as $meta) {
            if (strtolower(trim($meta['field_label'])) === 'title') continue;
            $fieldName = strtolower(str_replace([' ', '/'], ['_', '_'], $meta['field_label']));
            $doc['modal_metadata'][] = [
                'label'      => $meta['field_label'],
                'value'      => $meta['field_value'],
                'field_name' => $fieldName,
                'field_type' => $meta['field_type']
            ];
        }
    }
    unset($doc);
}

// --- Determine mode: search vs catalog ---
$isSearchMode = (
    $searchQuery !== '' ||
    ($categoryFilter !== '' && $categoryFilter !== 'all') ||
    $fromDateSql !== null ||
    $toDateSql !== null
);

// UI helper flags (non-breaking; for view rendering only)
$uiMode = $isSearchMode ? 'search' : 'catalog';
$uiHasResults = false;
$uiEmptyMessage = '';

// Get card and modal fields once
$cardFields = getVisibleFields($pdo, 'card');
$modalFields = getVisibleFields($pdo, 'modal');

if ($isSearchMode) {
    // ── SEARCH / FILTER MODE: flat paginated grid (like before) ──
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 12;

    $whereClause = "WHERE n.deleted_at IS NULL";
    $params = [];

    if ($searchQuery) {
        $like = "%$searchQuery%";
        $whereClause .= " AND (
            n.title LIKE ?
         OR EXISTS (
                SELECT 1 FROM custom_metadata_values cmv2
                WHERE cmv2.file_id = n.id AND cmv2.field_value LIKE ?
            )
        )";
        $params[] = $like;
        $params[] = $like;
    }

    if ($categoryFilter && $categoryFilter !== 'all') {
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM custom_metadata_values cmv2
            INNER JOIN form_fields cmf2 ON cmv2.field_id = cmf2.id
            WHERE cmv2.file_id = n.id 
            AND cmf2.field_label = 'Category' 
            AND cmv2.field_value = ?
        )";
        $params[] = $categoryFilter;
    }

    if ($fromDateSql !== null) {
        $whereClause .= " AND DATE(n.created_at) >= ?";
        $params[] = $fromDateSql;
    }

    if ($toDateSql !== null) {
        $whereClause .= " AND DATE(n.created_at) <= ?";
        $params[] = $toDateSql;
    }

    $countSql = "SELECT COUNT(DISTINCT n.id) as total FROM newspapers n $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalResults = $countStmt->fetch()['total'];
    $totalPages = max(1, ceil($totalResults / $limit));
    $currentPage = max(1, min($page, $totalPages));
    $offset = ($currentPage - 1) * $limit;

    $sql = "SELECT DISTINCT n.* FROM newspapers n $whereClause ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
    $queryParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $documents = $stmt->fetchAll();

    applyTitleOverrides($documents, $pdo);
    attachMetadataToDocs($documents, $pdo, $cardFields, $modalFields);

    $catalogShelves = []; // empty — search mode uses $documents

    // UI state
    $uiHasResults = !empty($documents);
    if (!$uiHasResults) {
        $uiEmptyMessage = 'No archive items matched your search/filter.';
    }
} else {
    // ── CATALOG MODE: group by Publication Type ──
    $documents = [];
    $totalResults = 0;
    $totalPages = 1;
    $currentPage = 1;

    // Get all distinct Publication Type values
    $pubTypeSql = "SELECT DISTINCT cmv.field_value as pub_type
                   FROM custom_metadata_values cmv
                   INNER JOIN form_fields ff ON cmv.field_id = ff.id
                   INNER JOIN newspapers n ON cmv.file_id = n.id AND n.deleted_at IS NULL
                   WHERE ff.field_label = 'Publication Type'
                   AND cmv.field_value IS NOT NULL AND cmv.field_value != ''
                   ORDER BY cmv.field_value ASC";
    $pubTypes = $pdo->query($pubTypeSql)->fetchAll(PDO::FETCH_COLUMN);

    $catalogShelves = [];

    foreach ($pubTypes as $pubType) {
        $shelfSql = "SELECT DISTINCT n.*
                     FROM newspapers n
                     INNER JOIN custom_metadata_values cmv ON cmv.file_id = n.id
                     INNER JOIN form_fields ff ON cmv.field_id = ff.id
                     WHERE n.deleted_at IS NULL
                     AND ff.field_label = 'Publication Type'
                     AND cmv.field_value = ?
                     ORDER BY n.created_at DESC
                     LIMIT 15";
        $shelfStmt = $pdo->prepare($shelfSql);
        $shelfStmt->execute([$pubType]);
        $shelfDocs = $shelfStmt->fetchAll();

        if (empty($shelfDocs)) continue;

        applyTitleOverrides($shelfDocs, $pdo);

        $countSql = "SELECT COUNT(DISTINCT n.id) as total
                     FROM newspapers n
                     INNER JOIN custom_metadata_values cmv ON cmv.file_id = n.id
                     INNER JOIN form_fields ff ON cmv.field_id = ff.id
                     WHERE n.deleted_at IS NULL
                     AND ff.field_label = 'Publication Type'
                     AND cmv.field_value = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$pubType]);
        $shelfTotal = $countStmt->fetch()['total'];

        attachMetadataToDocs($shelfDocs, $pdo, $cardFields, $modalFields);

        $catalogShelves[] = [
            'type'  => $pubType,
            'docs'  => $shelfDocs,
            'total' => $shelfTotal
        ];
    }

    // --- Fallback: no Publication Type field yet → show all files in one shelf ---
    if (empty($catalogShelves)) {
        $fallbackSql = "SELECT DISTINCT n.* FROM newspapers n WHERE n.deleted_at IS NULL ORDER BY n.created_at DESC LIMIT 20";
        $fallbackDocs = $pdo->query($fallbackSql)->fetchAll();

        if (!empty($fallbackDocs)) {
            applyTitleOverrides($fallbackDocs, $pdo);
            attachMetadataToDocs($fallbackDocs, $pdo, $cardFields, $modalFields);

            $totalFallback = $pdo->query("SELECT COUNT(id) FROM newspapers WHERE deleted_at IS NULL")->fetchColumn();
            $catalogShelves[] = [
                'type'  => 'All Archives',
                'docs'  => $fallbackDocs,
                'total' => $totalFallback
            ];
        }
    }

    // UI state
    $uiHasResults = !empty($catalogShelves);
    if (!$uiHasResults) {
        $uiEmptyMessage = 'No archive items are available yet.';
    }
}

// --- Fetch Categories for filter dropdown ---
$catSql = "SELECT DISTINCT cmv.field_value as id, cmv.field_value as name 
           FROM custom_metadata_values cmv
           INNER JOIN form_fields cmf ON cmv.field_id = cmf.id
           INNER JOIN newspapers n ON cmv.file_id = n.id
           WHERE cmf.field_label = 'Category' AND n.deleted_at IS NULL
           ORDER BY cmv.field_value ASC";
$categories = $pdo->query($catSql)->fetchAll();

// Expose UI payload for view-level enhancements (no logic changes)
$uiState = [
    'mode' => $uiMode,
    'has_results' => $uiHasResults,
    'empty_message' => $uiEmptyMessage,
    'filters' => [
        'from' => $fromInput, // keep mm/dd/yyyy for UI
        'to'   => $toInput    // keep mm/dd/yyyy for UI
    ]
];

// --- Include View ---
include __DIR__ . '/../views/public.php';
?>