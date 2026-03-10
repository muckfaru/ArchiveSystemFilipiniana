<?php
/**
 * Public Landing Page Controller
 * Archive System - Quezon City Public Library
 * 
 * No login required. This is the default entry page for readers.
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check if browse view is requested
$view = $_GET['view'] ?? 'home';

if ($view === 'browse') {
    // Redirect to browse.php
    header('Location: ' . APP_URL . '/user_pages/browse.php');
    exit;
}

// --- Pagination & Filters ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // 4 columns x 3 rows

$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

// --- Fetch Categories for filter ---
$catSql = "SELECT DISTINCT cmv.field_value as id, cmv.field_value as name 
           FROM custom_metadata_values cmv
           INNER JOIN form_fields cmf ON cmv.field_id = cmf.id
           INNER JOIN newspapers n ON cmv.file_id = n.id
           WHERE cmf.field_label = 'Category' AND n.deleted_at IS NULL
           ORDER BY cmv.field_value ASC";
$categories = $pdo->query($catSql)->fetchAll();

// --- Build WHERE Clause ---
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

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

// --- Count total for pagination ---
$countSql = "SELECT COUNT(DISTINCT n.id) as total
             FROM newspapers n
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetch()['total'];
$totalPages = max(1, ceil($totalResults / $limit));
$currentPage = max(1, min($page, $totalPages));
$offset = ($currentPage - 1) * $limit;

// --- Fetch Documents ---
$sql = "SELECT DISTINCT n.*
        FROM newspapers n 
        $whereClause 
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$documents = $stmt->fetchAll();

// Apply title overrides from custom metadata "Title" field
applyTitleOverrides($documents, $pdo);

// Fetch custom metadata for all documents
if (!empty($documents)) {
    $fileIds = array_column($documents, 'id');

    // Get visible fields for card and modal display
    $cardFields = getVisibleFields($pdo, 'card');
    $modalFields = getVisibleFields($pdo, 'modal');

    // Get custom metadata values for all files
    $customMetadata = getCustomMetadataValuesForFiles($fileIds);

    // Attach custom metadata to each document
    foreach ($documents as &$doc) {
        $rawMeta = $customMetadata[$doc['id']] ?? [];

        // Build rich metadata array with field_label and field_value
        // (needed by getCategoryFromMetadata and getMetadataValueByLabel)
        $doc['custom_metadata'] = [];
        foreach ($cardFields as $field) {
            if (isset($rawMeta[$field['field_id']])) {
                $doc['custom_metadata'][] = [
                    'field_id' => $field['field_id'],
                    'field_label' => $field['field_label'],
                    'field_value' => $rawMeta[$field['field_id']],
                    'field_type' => $field['field_type'] ?? 'text'
                ];
            }
        }
        // Also include modal-only fields that weren't in card fields
        $cardFieldIds = array_column($cardFields, 'field_id');
        foreach ($modalFields as $field) {
            if (isset($rawMeta[$field['field_id']]) && !in_array($field['field_id'], $cardFieldIds)) {
                $doc['custom_metadata'][] = [
                    'field_id' => $field['field_id'],
                    'field_label' => $field['field_label'],
                    'field_value' => $rawMeta[$field['field_id']],
                    'field_type' => $field['field_type'] ?? 'text'
                ];
            }
        }

        // Filter metadata for card display
        $doc['display_metadata'] = [];
        foreach ($cardFields as $field) {
            if (isset($rawMeta[$field['field_id']])) {
                $doc['display_metadata'][] = [
                    'label' => $field['field_label'],
                    'value' => $rawMeta[$field['field_id']]
                ];
            }
        }

        // Filter metadata for modal display – include ALL visible fields
        // so the modal always shows configured rows (with "—" for empty)
        $doc['modal_metadata'] = [];
        foreach ($modalFields as $field) {
            // Skip the Title field – already shown separately in the modal header
            if (strtolower(trim($field['field_label'])) === 'title') continue;

            $fieldName = strtolower(str_replace([' ', '/'], ['_', '_'], $field['field_label']));
            $doc['modal_metadata'][] = [
                'label'      => $field['field_label'],
                'value'      => $rawMeta[$field['field_id']] ?? '',
                'field_name' => $fieldName,
                'field_type' => $field['field_type'] ?? 'text'
            ];
        }
    }
}

// --- Include View ---
include __DIR__ . '/../views/public.php';
?>