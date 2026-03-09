<?php
/**
 * Metadata Display Configuration API
 * Archive System - Quezon City Public Library
 * Uses form_field_id (from form_fields table, not custom_metadata_fields)
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listConfig($pdo);
            break;
        case 'update':
            updateConfig($pdo, $currentUser, $input);
            break;
        case 'reset':
            resetConfig($pdo, $currentUser);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listConfig($pdo)
{
    $stmt = $pdo->query("
        SELECT ff.id, ff.field_label, ff.field_type,
               COALESCE(mdc.show_on_card,   1) AS show_on_card,
               COALESCE(mdc.show_in_modal,  1) AS show_in_modal
        FROM form_fields ff
        JOIN form_templates ft ON ff.form_id = ft.id
        LEFT JOIN metadata_display_config mdc ON ff.id = mdc.form_field_id
        WHERE ft.is_active = 1
        ORDER BY ff.display_order ASC
    ");
    echo json_encode(['success' => true, 'fields' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function updateConfig($pdo, $currentUser, $input)
{
    $configurations = $input['configurations'] ?? [];
    if (empty($configurations)) {
        echo json_encode(['success' => false, 'message' => 'No configurations provided']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO metadata_display_config (form_field_id, show_on_card, show_in_modal)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                show_on_card  = VALUES(show_on_card),
                show_in_modal = VALUES(show_in_modal)
        ");

        foreach ($configurations as $cfg) {
            $fieldId = intval($cfg['field_id'] ?? 0);
            $showCard = isset($cfg['show_on_card']) ? (int) (bool) $cfg['show_on_card'] : 1;
            $showModal = isset($cfg['show_in_modal']) ? (int) (bool) $cfg['show_in_modal'] : 1;
            if ($fieldId <= 0)
                continue;
            $stmt->execute([$fieldId, $showCard, $showModal]);
        }

        $pdo->commit();
        logActivity($currentUser['id'], 'custom_metadata_update', 'Updated metadata display configuration');
        echo json_encode(['success' => true, 'message' => 'Display configuration saved']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function resetConfig($pdo, $currentUser)
{
    $pdo->exec("TRUNCATE TABLE metadata_display_config");
    logActivity($currentUser['id'], 'custom_metadata_update', 'Reset all metadata display configurations');
    echo json_encode(['success' => true, 'message' => 'Configuration reset to defaults']);
}
