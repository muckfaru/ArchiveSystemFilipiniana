<?php
/**
 * Report API Endpoint
 * Archive System - Quezon City Public Library
 *
 * Handles upload-based reporting with period filters, pagination, and export.
 */

ob_start();

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $exportFormat = isset($_GET['export']) ? trim((string) $_GET['export']) : '';
    $isExportRequest = in_array($exportFormat, ['csv', 'print'], true);

    try {
        $reportType = isset($_GET['report_type']) ? trim($_GET['report_type']) : 'most_viewed';
        if (!in_array($reportType, ['most_viewed', 'file_summary'], true)) {
            $reportType = 'most_viewed';
        }

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $period = isset($_GET['period']) ? trim($_GET['period']) : 'all';
        $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
        $publicationType = isset($_GET['publication_type']) ? trim($_GET['publication_type']) : '';
        $category = isset($_GET['category']) ? trim($_GET['category']) : '';
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;

        $baseParams = [];
        $baseWhereClause = 'n.deleted_at IS NULL';

        $currentUserRole = $currentUser['role'] ?? 'admin';
        if ($currentUserRole !== 'super_admin') {
            $baseWhereClause .= ' AND n.uploaded_by = ?';
            $baseParams[] = intval($currentUser['id'] ?? 0);
        }

        $params = $baseParams;
        $whereClause = $baseWhereClause;

        if ($reportType === 'most_viewed') {
            $whereClause .= '
                AND EXISTS (
                    SELECT 1
                    FROM newspaper_views nv
                    WHERE nv.newspaper_id = n.id
                )
            ';

            switch ($period) {
                case 'daily':
                    $whereClause .= ' AND DATE(n.created_at) = CURDATE()';
                    break;
                case 'weekly':
                    $whereClause .= ' AND YEARWEEK(n.created_at, 1) = YEARWEEK(CURDATE(), 1)';
                    break;
                case 'monthly':
                    $whereClause .= ' AND YEAR(n.created_at) = YEAR(CURDATE()) AND MONTH(n.created_at) = MONTH(CURDATE())';
                    break;
                case 'yearly':
                    $whereClause .= ' AND YEAR(n.created_at) = YEAR(CURDATE())';
                    break;
                default:
                    $period = 'all';
                    break;
            }

            if (!empty($startDate)) {
                $whereClause .= ' AND DATE(n.created_at) >= ?';
                $params[] = $startDate;
            }

            if (!empty($endDate)) {
                $whereClause .= ' AND DATE(n.created_at) <= ?';
                $params[] = $endDate;
            }
        } else {
            $period = 'all';

            if (!empty($startDate)) {
                $whereClause .= ' AND DATE(n.created_at) >= ?';
                $params[] = $startDate;
            }

            if (!empty($endDate)) {
                $whereClause .= ' AND DATE(n.created_at) <= ?';
                $params[] = $endDate;
            }

            if ($publicationType !== '') {
                $whereClause .= "
                    AND EXISTS (
                        SELECT 1
                        FROM custom_metadata_values cmv_pt
                        INNER JOIN form_fields ff_pt ON cmv_pt.field_id = ff_pt.id
                        WHERE cmv_pt.file_id = n.id
                          AND LOWER(TRIM(ff_pt.field_label)) IN ('publication type', 'publication_type', 'type')
                          AND cmv_pt.field_value = ?
                    )
                ";
                $params[] = $publicationType;
            }

            if ($category !== '') {
                $whereClause .= "
                    AND EXISTS (
                        SELECT 1
                        FROM custom_metadata_values cmv_cat
                        INNER JOIN form_fields ff_cat ON cmv_cat.field_id = ff_cat.id
                        WHERE cmv_cat.file_id = n.id
                          AND LOWER(TRIM(ff_cat.field_label)) = 'category'
                          AND cmv_cat.field_value = ?
                    )
                ";
                $params[] = $category;
            }
        }

        if (!empty($search)) {
            $whereClause .= ' AND (n.title LIKE ? OR n.file_name LIKE ? OR n.file_type LIKE ? OR u.full_name LIKE ?)';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        $queryBase = "
            FROM newspapers n
            LEFT JOIN users u ON n.uploaded_by = u.id
            WHERE $whereClause
        ";

        $countQuery = 'SELECT COUNT(*) AS total ' . $queryBase;
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalRecords = intval(($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

        $summaryQuery = "
            SELECT
                COUNT(*) AS total_uploads,
                COALESCE(SUM(n.file_size), 0) AS total_size,
                COUNT(DISTINCT n.uploaded_by) AS unique_uploaders
            $queryBase
        ";
        $stmtSummary = $pdo->prepare($summaryQuery);
        $stmtSummary->execute($params);
        $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: [
            'total_uploads' => 0,
            'total_size' => 0,
            'unique_uploaders' => 0
        ];

        $filterOptions = [
            'publication_types' => [],
            'categories' => []
        ];

        $publicationTypeOptionsQuery = "
            SELECT DISTINCT TRIM(cmv.field_value) AS option_value
            FROM custom_metadata_values cmv
            INNER JOIN form_fields ff ON cmv.field_id = ff.id
            INNER JOIN newspapers n ON n.id = cmv.file_id
            WHERE $baseWhereClause
              AND LOWER(TRIM(ff.field_label)) IN ('publication type', 'publication_type', 'type')
              AND cmv.field_value IS NOT NULL
              AND TRIM(cmv.field_value) <> ''
            ORDER BY option_value ASC
        ";
        $stmtPublicationTypeOptions = $pdo->prepare($publicationTypeOptionsQuery);
        $stmtPublicationTypeOptions->execute($baseParams);
        $filterOptions['publication_types'] = array_values(array_map(
            static fn(array $row): string => (string) $row['option_value'],
            $stmtPublicationTypeOptions->fetchAll(PDO::FETCH_ASSOC)
        ));

        $categoryOptionsQuery = "
            SELECT DISTINCT TRIM(cmv.field_value) AS option_value
            FROM custom_metadata_values cmv
            INNER JOIN form_fields ff ON cmv.field_id = ff.id
            INNER JOIN newspapers n ON n.id = cmv.file_id
            WHERE $baseWhereClause
              AND LOWER(TRIM(ff.field_label)) = 'category'
              AND cmv.field_value IS NOT NULL
              AND TRIM(cmv.field_value) <> ''
            ORDER BY option_value ASC
        ";
        $stmtCategoryOptions = $pdo->prepare($categoryOptionsQuery);
        $stmtCategoryOptions->execute($baseParams);
        $filterOptions['categories'] = array_values(array_map(
            static fn(array $row): string => (string) $row['option_value'],
            $stmtCategoryOptions->fetchAll(PDO::FETCH_ASSOC)
        ));

        $orderByClause = $reportType === 'file_summary'
            ? 'n.created_at DESC, n.id DESC'
            : 'total_views DESC, n.created_at DESC, n.id DESC';

        $dataQuery = "
            SELECT
                n.id,
                n.title,
                n.file_name,
                n.thumbnail_path,
                n.file_type,
                n.file_size,
                n.created_at,
                n.uploaded_by,
                COALESCE(u.full_name, 'Unknown User') AS uploader_name,
                COALESCE(vtot.total_views, 0) AS total_views
            FROM newspapers n
            LEFT JOIN users u ON n.uploaded_by = u.id
            LEFT JOIN (
                SELECT newspaper_id, COUNT(*) AS total_views
                FROM newspaper_views
                GROUP BY newspaper_id
            ) vtot ON vtot.newspaper_id = n.id
            WHERE $whereClause
            ORDER BY $orderByClause
        ";

        $dataParams = $params;
        if (!$isExportRequest) {
            $dataQuery .= ' LIMIT ? OFFSET ?';
            $dataParams = array_merge($dataParams, [$limit, $offset]);
        }

        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $stmtData = $pdo->prepare($dataQuery);
        $stmtData->execute($dataParams);
        $files = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        $pubTypes = [];
        $categories = [];
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
                  )
            ");
            $ptsStmt->execute($fileIds);

            while ($row = $ptsStmt->fetch(PDO::FETCH_ASSOC)) {
                $fieldLabel = strtolower(trim((string) ($row['field_label'] ?? '')));
                if ($fieldLabel === 'publication type' && !isset($pubTypes[$row['file_id']])) {
                    $pubTypes[$row['file_id']] = $row['field_value'];
                }
                if ($fieldLabel === 'category' && !isset($categories[$row['file_id']])) {
                    $categories[$row['file_id']] = $row['field_value'];
                }
            }
        }

        $currentRank = $offset + 1;
        foreach ($files as &$file) {
            $file['rank'] = $currentRank++;
            $file['file_size'] = intval($file['file_size'] ?? 0);
            $file['total_views'] = intval($file['total_views'] ?? 0);
            $file['thumbnail_url'] = !empty($file['thumbnail_path'])
                ? APP_URL . '/' . ltrim($file['thumbnail_path'], '/')
                : '';
            $file['publication_type'] = $pubTypes[$file['id']] ?? '';
            $file['category'] = $categories[$file['id']] ?? '';
            $file['custom_metadata'] = [];
        }
        unset($file);

        $mostViewedQuery = "
            SELECT
                n.id,
                n.title,
                COALESCE(COUNT(v.id), 0) AS total_views
            FROM newspapers n
            LEFT JOIN users u ON n.uploaded_by = u.id
            LEFT JOIN newspaper_views v ON v.newspaper_id = n.id
            WHERE $whereClause
            GROUP BY n.id, n.title
            ORDER BY total_views DESC, n.created_at DESC
            LIMIT 1
        ";
        $stmtMostViewed = $pdo->prepare($mostViewedQuery);
        $stmtMostViewed->execute($params);
        $mostViewed = $stmtMostViewed->fetch(PDO::FETCH_ASSOC) ?: null;

        $periodLabel = 'All Time';
        switch ($period) {
            case 'daily':
                $periodLabel = 'Daily (' . date('M d, Y') . ')';
                break;
            case 'weekly':
                $weekStart = date('M d, Y', strtotime('monday this week'));
                $weekEnd = date('M d, Y', strtotime('sunday this week'));
                $periodLabel = 'Weekly (' . $weekStart . ' - ' . $weekEnd . ')';
                break;
            case 'monthly':
                $periodLabel = 'Monthly (' . date('F Y') . ')';
                break;
            case 'yearly':
                $periodLabel = 'Yearly (' . date('Y') . ')';
                break;
        }

        if (!empty($startDate) && !empty($endDate)) {
            $periodLabel .= ' | Date Range (' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
        } elseif (!empty($startDate)) {
            $periodLabel .= ' | Date Range (From ' . date('M d, Y', strtotime($startDate)) . ')';
        } elseif (!empty($endDate)) {
            $periodLabel .= ' | Date Range (Until ' . date('M d, Y', strtotime($endDate)) . ')';
        }

        if ($isExportRequest) {
            ob_end_clean();

            if (isset($currentUser['id'])) {
                $formatLabel = $exportFormat === 'print' ? 'PDF/Print' : 'CSV';
                $reportTypeLabel = $reportType === 'file_summary' ? 'File Summary' : 'Most Viewed File';
                logActivity($currentUser['id'], 'export_report', $reportTypeLabel . ' Report - ' . $periodLabel . ' - ' . $formatLabel);
            }

            if ($exportFormat === 'csv') {
                $filenamePrefix = $reportType === 'file_summary' ? 'file_summary_report_' : 'most_viewed_file_report_';
                $filename = $filenamePrefix . date('Y-m-d') . '.csv';
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');

                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                $reportTitle = $reportType === 'file_summary' ? 'File Summary Report' : 'Most Viewed File Report';
                fputcsv($output, [$reportTitle]);
                fputcsv($output, ['Period', $periodLabel]);
                fputcsv($output, ['Generated At', date('M d, Y h:i A')]);
                fputcsv($output, ['Total Uploads', intval($summary['total_uploads'] ?? 0)]);
                fputcsv($output, ['Total Size (Bytes)', intval($summary['total_size'] ?? 0)]);
                fputcsv($output, ['Unique Uploaders', intval($summary['unique_uploaders'] ?? 0)]);
                fputcsv($output, ['Most Viewed File', $mostViewed['title'] ?? 'N/A']);
                fputcsv($output, ['Most Viewed Count', intval($mostViewed['total_views'] ?? 0)]);
                fputcsv($output, []);

                if ($reportType === 'file_summary') {
                    fputcsv($output, ['No', 'Title', 'Publication Type', 'Category', 'Uploader', 'File Type', 'File Size (Bytes)', 'Uploaded At']);
                } else {
                    fputcsv($output, ['No', 'Title', 'Publication Type', 'Category', 'Total Views']);
                }

                $index = 1;
                foreach ($files as $file) {
                    if ($reportType === 'file_summary') {
                        fputcsv($output, [
                            $index++,
                            $file['title'] ?? '',
                            $file['publication_type'] ?? '',
                            $file['category'] ?? '',
                            $file['uploader_name'] ?? 'Unknown User',
                            strtoupper($file['file_type'] ?? ''),
                            intval($file['file_size'] ?? 0),
                            $file['created_at'] ?? ''
                        ]);
                    } else {
                        fputcsv($output, [
                            $index++,
                            $file['title'] ?? '',
                            $file['publication_type'] ?? '',
                            $file['category'] ?? '',
                            intval($file['total_views'] ?? 0)
                        ]);
                    }
                }

                fclose($output);
                exit;
            }

            header('Content-Type: text/html; charset=utf-8');
            $safePeriod = htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8');
            $safeReportTitle = 'Report Export';
            $safeBodyReportTitle = $reportType === 'file_summary' ? 'File Summary Report' : 'Most Viewed File Report';
            $safeTotalUploads = number_format(intval($summary['total_uploads'] ?? 0));

            echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($safeReportTitle, ENT_QUOTES, 'UTF-8') . '</title>';
            echo '<style>body{font-family:Arial,sans-serif;padding:24px;color:#1f2937}h1{margin:0 0 8px}p{margin:4px 0}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #d1d5db;padding:8px;font-size:12px;text-align:left}th{background:#f3f4f6}.summary{display:flex;gap:16px;margin-top:12px;flex-wrap:wrap}.box{border:1px solid #e5e7eb;padding:10px 12px;border-radius:8px;background:#f9fafb}</style>';
            echo '</head><body>';
            echo '<h1>' . htmlspecialchars($safeBodyReportTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
            echo '<p><strong>Period:</strong> ' . $safePeriod . '</p>';
            echo '<div class="summary">';
            echo '<div class="box"><strong>Total Uploads:</strong> ' . $safeTotalUploads . '</div>';
            echo '</div>';

            if ($reportType === 'file_summary') {
                echo '<table><thead><tr><th>No</th><th>Title</th><th>Publication Type</th><th>Category</th><th>Uploader</th><th>File Type</th><th>File Size (Bytes)</th><th>Uploaded At</th></tr></thead><tbody>';
            } else {
                echo '<table><thead><tr><th>No</th><th>Title</th><th>Publication Type</th><th>Category</th><th>Total Views</th></tr></thead><tbody>';
            }

            $index = 1;
            foreach ($files as $file) {
                $title = htmlspecialchars((string) ($file['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                $pubType = htmlspecialchars((string) ($file['publication_type'] ?? ''), ENT_QUOTES, 'UTF-8');
                $category = htmlspecialchars((string) ($file['category'] ?? ''), ENT_QUOTES, 'UTF-8');
                $uploader = htmlspecialchars((string) ($file['uploader_name'] ?? 'Unknown User'), ENT_QUOTES, 'UTF-8');
                $fileType = htmlspecialchars(strtoupper((string) ($file['file_type'] ?? '')), ENT_QUOTES, 'UTF-8');
                $fileSize = number_format(intval($file['file_size'] ?? 0));
                $createdAt = htmlspecialchars((string) ($file['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                echo '<tr>';
                if ($reportType === 'file_summary') {
                    echo '<td>' . $index++ . '</td><td>' . $title . '</td><td>' . $pubType . '</td><td>' . $category . '</td><td>' . $uploader . '</td><td>' . $fileType . '</td><td>' . $fileSize . '</td><td>' . $createdAt . '</td>';
                } else {
                    echo '<td>' . $index++ . '</td><td>' . $title . '</td><td>' . $pubType . '</td><td>' . $category . '</td><td>' . number_format(intval($file['total_views'] ?? 0)) . '</td>';
                }
                echo '</tr>';
            }

            if (empty($files)) {
                $emptyColspan = $reportType === 'file_summary' ? 8 : 5;
                echo '<tr><td colspan="' . $emptyColspan . '" style="text-align:center">No records found for selected filters.</td></tr>';
            }

            echo '</tbody></table>';
            echo '<script>(function(){window.onload=function(){window.print();};})();</script>';
            echo '</body></html>';
            exit;
        }

        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $files,
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalRecords / max(1, $limit)),
            'report_type' => $reportType,
            'filter_options' => $filterOptions,
            'summary' => [
                'total_uploads' => intval($summary['total_uploads'] ?? 0),
                'total_size' => intval($summary['total_size'] ?? 0),
                'unique_uploaders' => intval($summary['unique_uploaders'] ?? 0),
                'most_viewed_title' => $mostViewed['title'] ?? 'N/A',
                'most_viewed_views' => intval($mostViewed['total_views'] ?? 0),
                'period_label' => $periodLabel
            ]
        ]);

    } catch (Exception $e) {
        error_log('Report API Error: ' . $e->getMessage());
        if (!$isExportRequest) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'An error occurred while fetching reports.']);
        } else {
            ob_end_clean();
            header('Content-Type: text/plain; charset=utf-8');
            echo 'An error occurred while generating the report export.';
        }
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
exit;
