<?php
/**
 * Report API Endpoint
 * Archive System - Quezon City Public Library
 *
 * Handles fetching most read files with filtering, sorting, and pagination.
 */

ob_start();

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/analytics.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        ensureNewspaperViewsTable($pdo);

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $period = isset($_GET['period']) ? $_GET['period'] : 'all';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;

        $params = [];
        $viewWhereClause = "1=1";

        // Filtering by period on the views
        switch ($period) {
            case 'today':
                $viewWhereClause = "DATE(v.view_date) = CURDATE()";
                break;
            case 'weekly':
                $viewWhereClause = "v.view_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'monthly':
                $viewWhereClause = "v.view_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case 'yearly':
                $viewWhereClause = "v.view_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            case 'custom':
                if (!empty($startDate) && !empty($endDate)) {
                    $viewWhereClause = "DATE(v.view_date) >= ? AND DATE(v.view_date) <= ?";
                    $params[] = $startDate;
                    $params[] = $endDate;
                }
                break;
            // 'all' doesn't need additional filtering
        }

        $searchWhereClause = "n.deleted_at IS NULL";
        $searchParams = [];
        
        if (!empty($search)) {
            $searchWhereClause .= " AND (n.title LIKE ? OR n.file_type LIKE ?)";
            $searchParams[] = "%{$search}%";
            $searchParams[] = "%{$search}%";
        }

        // We only want to count VIEWS that match the period. So we need to LEFT JOIN with conditions.
        // Or better, INNER JOIN to only show files that HAVE views in that period?
        // Usually, "Most Read Files" implies we only show files with views > 0 in that period.
        
        $queryBase = "
            FROM newspapers n
            INNER JOIN newspaper_views v ON n.id = v.newspaper_id AND $viewWhereClause
            WHERE $searchWhereClause
        ";
        
        $mergedParams = array_merge($params, $searchParams);

        // Get total count of distinct newspapers that have views
        $countQuery = "SELECT COUNT(DISTINCT n.id) as total " . $queryBase;
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($mergedParams);
        $totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
        $totalRecords = $totalRow ? intval($totalRow['total']) : 0;

        $isExport = isset($_GET['export']) && $_GET['export'] === 'csv';

        if (!$isExport) {
            ob_end_clean();
            header('Content-Type: application/json');
        }

        // Fetch data
        $dataQuery = "
            SELECT 
                n.id, 
                n.title, 
                n.thumbnail_path, 
                n.file_type,
                COUNT(v.id) as view_count
            $queryBase
            GROUP BY n.id
            ORDER BY view_count DESC, n.title ASC
        ";

        if ($isExport) {
            $dataParams = $mergedParams;
        } else {
            $dataQuery .= " LIMIT ? OFFSET ?";
            $dataParams = array_merge($mergedParams, [$limit, $offset]);
        }
        
        // Disable emulated prepares to allow LIMIT/OFFSET as parameters
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        $stmtData = $pdo->prepare($dataQuery);
        $stmtData->execute($dataParams);
        $files = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        // Format data and calculate ranks based on offset
        $currentRank = $offset + 1;
        
        // Fetch specific Publication Type for these newspapers directly from custom metadata
        $pubTypes = [];
        $publicationDates = [];
        if (!empty($files)) {
            $fileIds = array_column($files, 'id');
            $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
            $ptsStmt = $pdo->prepare("
                SELECT cmv.file_id, cmv.field_value, LOWER(ff.field_label) AS field_label
                FROM custom_metadata_values cmv
                INNER JOIN form_fields ff ON cmv.field_id = ff.id
                WHERE cmv.file_id IN ($placeholders) 
                  AND (
                      LOWER(ff.field_label) = 'publication type'
                      OR LOWER(ff.field_label) = 'category'
                      OR LOWER(ff.field_label) = 'publication date'
                      OR LOWER(ff.field_label) = 'date published'
                      OR LOWER(ff.field_label) = 'date issued'
                      OR LOWER(ff.field_label) = 'date'
                  )
            ");
            $ptsStmt->execute($fileIds);
            while ($row = $ptsStmt->fetch(PDO::FETCH_ASSOC)) {
                if (
                    in_array($row['field_label'], ['publication type', 'category'], true)
                    && !isset($pubTypes[$row['file_id']])
                ) {
                    $pubTypes[$row['file_id']] = $row['field_value'];
                }

                if (
                    in_array($row['field_label'], ['publication date', 'date published', 'date issued', 'date'], true)
                    && !isset($publicationDates[$row['file_id']])
                ) {
                    $publicationDates[$row['file_id']] = $row['field_value'];
                }
            }
        }

        foreach ($files as &$file) {
            $file['rank'] = $currentRank++;
            $file['view_count'] = intval($file['view_count']);
            $file['thumbnail_url'] = !empty($file['thumbnail_path'])
                ? APP_URL . '/' . ltrim($file['thumbnail_path'], '/')
                : '';
            $file['publication_type'] = $pubTypes[$file['id']] ?? ('Document');
            $file['publication_date'] = !empty($publicationDates[$file['id']])
                ? formatPublicationDate($publicationDates[$file['id']], true)
                : '';
            
            // Just return empty custom_metadata to not break JS
            $file['custom_metadata'] = [];
        }

        if ($isExport) {
            ob_end_clean();
            $filename = 'most_read_files_report_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Build a human-readable period label for the activity log
            $periodLabel = 'All Time';
            switch ($period) {
                case 'today':   $periodLabel = 'Today (' . date('M d, Y') . ')'; break;
                case 'weekly':  $periodLabel = 'This Week (' . date('M d', strtotime('-6 days')) . ' – ' . date('M d, Y') . ')'; break;
                case 'monthly': $periodLabel = 'This Month (' . date('F Y') . ')'; break;
                case 'yearly':  $periodLabel = 'This Year (' . date('Y') . ')'; break;
                case 'custom':
                    if (!empty($startDate) && !empty($endDate)) {
                        $periodLabel = 'Custom Range (' . date('M d, Y', strtotime($startDate)) . ' – ' . date('M d, Y', strtotime($endDate)) . ')';
                    }
                    break;
            }

            // Log the export action to activity logs
            if (isset($currentUser['id'])) {
                logActivity($currentUser['id'], 'export_report', 'Most Read Files – ' . $periodLabel);
            }

            $output = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens it correctly
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Rank', 'Title', 'Publication Type', 'Total Views']);
            
            foreach ($files as $file) {
                fputcsv($output, [
                    $file['rank'],
                    $file['title'],
                    $file['publication_type'],
                    $file['view_count']
                ]);
            }
            fclose($output);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => $files,
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalRecords / $limit)
        ]);
        
    } catch (Exception $e) {
        error_log("Report API Error: " . $e->getMessage());
        if (!$isExport) {
            ob_end_clean();
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching reports.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
exit;
