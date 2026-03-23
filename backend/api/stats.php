<?php
/**
 * Stats API Endpoint
 * Archive System - Quezon City Public Library
 * Returns live archive statistics as JSON.
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/analytics.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $dashboardUploaderId = (($currentUser['role'] ?? 'admin') === 'super_admin')
        ? null
        : intval($currentUser['id'] ?? 0);

    $archives = (int) countArchives($dashboardUploaderId);
    $views = (int) getAggregateViews($pdo, $dashboardUploaderId);
    $years = getYearsCovered($dashboardUploaderId);
    $categories = (int) countCategories($dashboardUploaderId);

    echo json_encode([
        'success' => true,
        'archives' => $archives,
        'views' => $views,
        'years' => $years,
        'categories' => $categories,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
