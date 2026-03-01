<?php
/**
 * Stats API Endpoint
 * Archive System - Quezon City Public Library
 * Returns live archive statistics as JSON.
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $archives = (int) countArchives();
    $issues = (int) countIssues();
    $years = getYearsCovered();
    $categories = (int) countCategories();

    echo json_encode([
        'success' => true,
        'archives' => $archives,
        'issues' => $issues,
        'years' => $years,
        'categories' => $categories,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
