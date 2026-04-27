<?php
/**
 * Dashboard API Endpoint
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/auth.php'; // Ensure user is logged in

header('Content-Type: application/json');

function dashboardChartScope(): ?int {
    $currentUser = getCurrentUser();
    return (($currentUser['role'] ?? 'admin') === 'super_admin')
        ? null
        : intval($currentUser['id'] ?? 0);
}

function dashboardChartConfig(string $range): array {
    if ($range === '7') {
        return ['unit' => 'day', 'interval' => 6, 'date_format' => '%Y-%m-%d', 'php_format' => 'M j'];
    }

    if ($range === 'year') {
        return ['unit' => 'month', 'interval' => 11, 'date_format' => '%Y-%m', 'php_format' => 'M'];
    }

    if ($range === 'all') {
        return ['unit' => 'year', 'interval' => null, 'date_format' => '%Y', 'php_format' => 'Y'];
    }

    return ['unit' => 'day', 'interval' => 29, 'date_format' => '%Y-%m-%d', 'php_format' => 'M j'];
}

function dashboardChartPeriods(PDO $pdo, string $range, string $dateColumn, string $table, string $join = '', string $where = '1=1', array $params = []): array {
    $config = dashboardChartConfig($range);
    $today = new DateTime('today');
    $periods = [];

    if ($config['unit'] === 'year') {
        $sql = "SELECT MIN(YEAR($dateColumn)) AS min_year, MAX(YEAR($dateColumn)) AS max_year FROM $table $join WHERE $where";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $minYear = intval($row['min_year'] ?? date('Y'));
        $maxYear = intval($row['max_year'] ?? date('Y'));
        if ($minYear <= 0) {
            $minYear = intval(date('Y'));
        }
        if ($maxYear < $minYear) {
            $maxYear = $minYear;
        }
        for ($year = $minYear; $year <= $maxYear; $year++) {
            $key = (string) $year;
            $periods[$key] = ['key' => $key, 'label' => $key];
        }
        return $periods;
    }

    if ($config['unit'] === 'month') {
        $start = (clone $today)->modify('first day of this month')->modify('-' . $config['interval'] . ' months');
        for ($i = 0; $i <= $config['interval']; $i++) {
            $date = (clone $start)->modify("+$i months");
            $key = $date->format('Y-m');
            $periods[$key] = ['key' => $key, 'label' => $date->format($config['php_format'])];
        }
        return $periods;
    }

    $start = (clone $today)->modify('-' . $config['interval'] . ' days');
    for ($i = 0; $i <= $config['interval']; $i++) {
        $date = (clone $start)->modify("+$i days");
        $key = $date->format('Y-m-d');
        $periods[$key] = ['key' => $key, 'label' => $date->format($config['php_format'])];
    }
    return $periods;
}

function dashboardChartDateCondition(string $range, string $dateColumn): string {
    if ($range === '7') {
        return "$dateColumn >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    }

    if ($range === 'year') {
        return "$dateColumn >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')";
    }

    if ($range === 'all') {
        return '1=1';
    }

    return "$dateColumn >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
}

function dashboardChartPeriodExpression(string $range, string $dateColumn): string {
    $config = dashboardChartConfig($range);
    return "DATE_FORMAT($dateColumn, '" . $config['date_format'] . "')";
}

function getDashboardUploadChart(PDO $pdo, string $range, string $fileType, ?int $uploadedBy): array {
    $fileTypes = [
        'pdf' => ['label' => 'PDF', 'color' => '#3A9AFF'],
        'mobi' => ['label' => 'MOBI', 'color' => '#10B981'],
        'images' => ['label' => 'Images', 'color' => '#F59E0B'],
        'other' => ['label' => 'Other', 'color' => '#94A3B8'],
    ];
    $selectedTypes = $fileType === 'all' ? array_keys($fileTypes) : [$fileType];

    $where = ['n.deleted_at IS NULL', dashboardChartDateCondition($range, 'n.created_at')];
    $params = [];

    if ($uploadedBy !== null) {
        $where[] = 'n.uploaded_by = ?';
        $params[] = $uploadedBy;
    }

    if ($fileType === 'pdf' || $fileType === 'mobi') {
        $where[] = 'LOWER(n.file_type) = ? AND COALESCE(n.is_bulk_image, 0) = 0';
        $params[] = $fileType;
    } elseif ($fileType === 'images') {
        $where[] = 'COALESCE(n.is_bulk_image, 0) = 1';
    }

    $whereSql = implode(' AND ', $where);
    $periodExpr = dashboardChartPeriodExpression($range, 'n.created_at');
    $periods = dashboardChartPeriods($pdo, $range, 'n.created_at', 'newspapers n', '', $whereSql, $params);

    $series = [];
    foreach ($selectedTypes as $type) {
        $series[$type] = [
            'key' => $type,
            'label' => $fileTypes[$type]['label'] ?? ucfirst($type),
            'color' => $fileTypes[$type]['color'] ?? '#94A3B8',
            'values' => array_fill_keys(array_keys($periods), 0),
        ];
    }

    $sql = "
        SELECT
            $periodExpr AS period_key,
            CASE
                WHEN COALESCE(n.is_bulk_image, 0) = 1 THEN 'images'
                WHEN LOWER(COALESCE(n.file_type, '')) IN ('pdf', 'mobi') THEN LOWER(n.file_type)
                ELSE 'other'
            END AS type_key,
            COUNT(*) AS total
        FROM newspapers n
        WHERE $whereSql
        GROUP BY period_key, type_key
        ORDER BY period_key ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $periodKey = (string) ($row['period_key'] ?? '');
        $typeKey = (string) ($row['type_key'] ?? 'other');
        if (!isset($periods[$periodKey]) || !isset($series[$typeKey])) {
            continue;
        }
        $series[$typeKey]['values'][$periodKey] = intval($row['total'] ?? 0);
    }

    return [
        'labels' => array_values(array_column($periods, 'label')),
        'series' => array_map(function ($item) {
            $item['values'] = array_values($item['values']);
            return $item;
        }, array_values($series)),
    ];
}

function getDashboardViewsChart(PDO $pdo, string $range, ?int $uploadedBy): array {
    require_once __DIR__ . '/../core/analytics.php';
    ensureNewspaperViewsTable($pdo);

    $where = ['n.deleted_at IS NULL', dashboardChartDateCondition($range, 'v.view_date')];
    $params = [];

    if ($uploadedBy !== null) {
        $where[] = 'n.uploaded_by = ?';
        $params[] = $uploadedBy;
    }

    $whereSql = implode(' AND ', $where);
    $periodExpr = dashboardChartPeriodExpression($range, 'v.view_date');
    $periods = dashboardChartPeriods(
        $pdo,
        $range,
        'v.view_date',
        'newspaper_views v',
        'INNER JOIN newspapers n ON n.id = v.newspaper_id',
        $whereSql,
        $params
    );
    $values = array_fill_keys(array_keys($periods), 0);

    $sql = "
        SELECT $periodExpr AS period_key, COUNT(v.id) AS total
        FROM newspaper_views v
        INNER JOIN newspapers n ON n.id = v.newspaper_id
        WHERE $whereSql
        GROUP BY period_key
        ORDER BY period_key ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $periodKey = (string) ($row['period_key'] ?? '');
        if (isset($values[$periodKey])) {
            $values[$periodKey] = intval($row['total'] ?? 0);
        }
    }

    return [
        'labels' => array_values(array_column($periods, 'label')),
        'series' => [[
            'key' => 'views',
            'label' => 'Views',
            'color' => '#3A9AFF',
            'values' => array_values($values),
        ]],
    ];
}

// Handle Dashboard Charts (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'charts') {
    try {
        $range = $_GET['range'] ?? '30';
        $range = in_array($range, ['7', '30', 'year', 'all'], true) ? $range : '30';
        $fileType = $_GET['file_type'] ?? 'all';
        $fileType = in_array($fileType, ['all', 'pdf', 'mobi', 'images'], true) ? $fileType : 'all';
        $uploadedBy = dashboardChartScope();

        echo json_encode([
            'success' => true,
            'uploads' => getDashboardUploadChart($pdo, $range, $fileType, $uploadedBy),
            'views' => getDashboardViewsChart($pdo, $range, $uploadedBy),
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Refresh Rankings (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'refresh_rankings') {
    try {
        require_once __DIR__ . '/../core/analytics.php';
        $currentUser = getCurrentUser();
        $dashboardUploaderId = (($currentUser['role'] ?? 'admin') === 'super_admin')
            ? null
            : intval($currentUser['id'] ?? 0);
        $period = $_GET['period'] ?? 'all';
        $topReads = getTopReadNewspapers($pdo, $period, $dashboardUploaderId);
        $topReads = array_filter($topReads, function($read) {
            return intval($read['view_count']) > 0;
        });
        $top5 = array_slice(array_values($topReads), 0, 5);
        
        echo json_encode(['success' => true, 'data' => $top5]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Move to Trash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_to_trash') {
    try {
        $currentUser = getCurrentUser();

        // Handle Bulk Delete
        if (isset($_POST['item_ids']) && is_array($_POST['item_ids'])) {
            $successCount = 0;
            $ids = array_map('intval', $_POST['item_ids']);
            foreach ($ids as $id) {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE newspapers SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
                    if ($stmt->execute([$currentUser['id'], $id])) {
                        // Log activity
                        $titleStmt = $pdo->prepare("SELECT title FROM newspapers WHERE id = ?");
                        $titleStmt->execute([$id]);
                        $item = $titleStmt->fetch();
                        logActivity($currentUser['id'], 'delete', $item['title'] ?? "Archive", $id);
                        $successCount++;
                    }
                }
            }
            echo json_encode(['success' => true, 'count' => $successCount]);
            exit;
        }

        // Handle Single Delete
        $id = intval($_POST['item_id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE newspapers SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $success = $stmt->execute([$currentUser['id'], $id]);

            if ($success) {
                // Log activity
                $stmt = $pdo->prepare("SELECT title FROM newspapers WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                logActivity($currentUser['id'], 'delete', $item['title'] ?? "Archive", $id);

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database update failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Default response if no action matched
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
