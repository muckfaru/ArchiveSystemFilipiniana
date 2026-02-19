<?php
/**
 * Check Duplicate File API
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_duplicate') {
    try {
        $title = trim($_POST['title'] ?? '');
        $fileName = trim($_POST['file_name'] ?? '');

        if (empty($title) && empty($fileName)) {
            echo json_encode(['success' => false, 'message' => 'Title or filename required']);
            exit;
        }

        // Check by title first
        $duplicates = [];

        if (!empty($title)) {
            $stmt = $pdo->prepare("
                SELECT id, title, file_path, created_at 
                FROM newspapers 
                WHERE deleted_at IS NULL 
                AND LOWER(title) = LOWER(?)
                LIMIT 5
            ");
            $stmt->execute([$title]);
            $titleMatches = $stmt->fetchAll();

            if ($titleMatches) {
                $duplicates['title_matches'] = $titleMatches;
            }
        }

        // Check by filename
        if (!empty($fileName)) {
            $stmt = $pdo->prepare("
                SELECT id, title, file_path, created_at 
                FROM newspapers 
                WHERE deleted_at IS NULL 
                AND file_name = ?
                LIMIT 5
            ");
            $stmt->execute([$fileName]);
            $fileMatches = $stmt->fetchAll();

            if ($fileMatches) {
                $duplicates['file_matches'] = $fileMatches;
            }
        }

        if (empty($duplicates)) {
            echo json_encode([
                'success' => true,
                'has_duplicates' => false,
                'message' => 'No duplicates found'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_duplicates' => true,
                'duplicates' => $duplicates,
                'count' => count($duplicates['title_matches'] ?? []) + count($duplicates['file_matches'] ?? [])
            ]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
