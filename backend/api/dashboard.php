<?php
/**
 * Dashboard API Endpoint
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/auth.php'; // Ensure user is logged in

header('Content-Type: application/json');

// Handle Move to Trash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_to_trash') {
    try {
        $id = intval($_POST['item_id'] ?? 0);

        // CSRF check would be good here, but for now relying on auth

        if ($id > 0) {
            $currentUser = getCurrentUser();

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
