<?php
/**
 * Dashboard API Endpoint
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/auth.php'; // Ensure user is logged in

header('Content-Type: application/json');

// Handle Refresh Rankings (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'refresh_rankings') {
    try {
        require_once __DIR__ . '/../core/analytics.php';
        $period = $_GET['period'] ?? 'all';
        $topReads = getTopReadNewspapers($pdo, $period);
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
