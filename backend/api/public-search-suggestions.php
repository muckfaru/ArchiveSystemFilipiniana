<?php
/**
 * Public Search Suggestions API
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$query = trim((string) ($_GET['q'] ?? ''));
$limit = max(1, min(6, (int) ($_GET['limit'] ?? 6)));
$pageType = trim((string) ($_GET['page_type'] ?? 'public'));

if ($query === '') {
    echo json_encode([
        'success' => true,
        'query' => '',
        'publications' => [],
        'publication_types' => [],
    ]);
    exit;
}

try {
    $like = '%' . $query . '%';

    $metadataFieldSql = "
        SELECT
            cmv.file_id,
            MAX(CASE
                WHEN LOWER(TRIM(ff.field_label)) = 'title' AND TRIM(COALESCE(cmv.field_value, '')) != ''
                    THEN TRIM(cmv.field_value)
                ELSE NULL
            END) AS custom_title,
            MAX(CASE
                WHEN LOWER(TRIM(ff.field_label)) IN ('publication type', 'publication_type', 'type')
                    AND TRIM(COALESCE(cmv.field_value, '')) != ''
                    THEN TRIM(cmv.field_value)
                ELSE NULL
            END) AS publication_type,
            MAX(CASE
                WHEN LOWER(TRIM(ff.field_label)) IN ('publication date', 'date published', 'date issued', 'date')
                    AND TRIM(COALESCE(cmv.field_value, '')) != ''
                    THEN TRIM(cmv.field_value)
                ELSE NULL
            END) AS publication_date
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON ff.id = cmv.field_id
        GROUP BY cmv.file_id
    ";

    $publicationStmt = $pdo->prepare("
        SELECT
            n.id,
            n.title,
            n.thumbnail_path,
            n.file_type,
            COALESCE(NULLIF(meta.custom_title, ''), NULLIF(n.title, ''), n.file_name) AS display_title,
            COALESCE(NULLIF(meta.publication_type, ''), '') AS publication_type,
            COALESCE(NULLIF(meta.publication_date, ''), '') AS publication_date
        FROM newspapers n
        LEFT JOIN ($metadataFieldSql) meta ON meta.file_id = n.id
        WHERE n.deleted_at IS NULL
          AND (
                COALESCE(NULLIF(meta.custom_title, ''), NULLIF(n.title, ''), n.file_name) LIKE ?
             OR n.file_name LIKE ?
             OR COALESCE(meta.publication_type, '') LIKE ?
          )
        ORDER BY
            CASE
                WHEN COALESCE(NULLIF(meta.custom_title, ''), NULLIF(n.title, ''), n.file_name) LIKE ? THEN 0
                WHEN COALESCE(meta.publication_type, '') LIKE ? THEN 1
                ELSE 2
            END,
            n.created_at DESC
        LIMIT $limit
    ");
    $publicationStmt->execute([$like, $like, $like, $like, $like]);
    $publications = $publicationStmt->fetchAll();

    $publicationTypesStmt = $pdo->prepare("
        SELECT DISTINCT TRIM(cmv.field_value) AS publication_type
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON ff.id = cmv.field_id
        INNER JOIN newspapers n ON n.id = cmv.file_id AND n.deleted_at IS NULL
        WHERE LOWER(TRIM(ff.field_label)) IN ('publication type', 'publication_type', 'type')
          AND TRIM(COALESCE(cmv.field_value, '')) != ''
          AND TRIM(cmv.field_value) LIKE ?
        ORDER BY publication_type ASC
        LIMIT 5
    ");
    $publicationTypesStmt->execute([$like]);
    $publicationTypes = array_map(static function ($row) {
        return [
            'name' => $row['publication_type'],
        ];
    }, $publicationTypesStmt->fetchAll());

    $responsePublications = array_map(static function ($publication) use ($pageType) {
        $publicationDate = trim((string) ($publication['publication_date'] ?? ''));
        $formattedDate = $publicationDate !== '' ? formatPublicationDate($publicationDate) : '';

        return [
            'id' => url_encrypt((int) $publication['id']),
            'title' => $publication['display_title'],
            'publication_type' => $publication['publication_type'],
            'publication_date' => $formattedDate,
            'thumbnail' => !empty($publication['thumbnail_path']) ? APP_URL . '/' . ltrim($publication['thumbnail_path'], '/') : '',
            'url' => route_url('reader', ['id' => url_encrypt((int) $publication['id'])]),
        ];
    }, $publications);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'publications' => $responsePublications,
        'publication_types' => $publicationTypes,
        'see_all_url' => $pageType === 'browse'
            ? route_url('browse', ['q' => $query])
            : route_url('home', ['q' => $query]),
        'browse_publication_type_base_url' => route_url('browse'),
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load search suggestions.',
    ]);
}

exit;
