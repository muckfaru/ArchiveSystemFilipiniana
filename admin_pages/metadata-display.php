<?php
/**
 * Metadata Display Configuration Page Controller
 * Archive System - Quezon City Public Library
 *
 * Syncs with form_fields (via form_templates) and lets admin toggle which
 * fields appear on Basic Viewing (file cards) and Detailed Modal views.
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect('dashboard.php?error=' . urlencode('Access denied'));
}

// Ensure metadata_display_config table exists with the correct schema (form_field_id)
// If the old schema (field_id) exists from the previous version, drop and recreate it.
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'metadata_display_config'")->rowCount() > 0;

    if ($tableExists) {
        // Check if it has the old column name 'field_id' instead of 'form_field_id'
        $cols = $pdo->query("SHOW COLUMNS FROM metadata_display_config LIKE 'form_field_id'")->rowCount();
        if ($cols === 0) {
            // Old schema — drop and recreate
            $pdo->exec("DROP TABLE IF EXISTS metadata_display_config");
            $tableExists = false;
        }
    }

    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE metadata_display_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                form_field_id INT NOT NULL,
                show_on_card TINYINT(1) DEFAULT 1,
                show_in_modal TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_form_field (form_field_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (Exception $e) {
    // Ignore silently — non-critical
}

// Load all form fields from all active/draft form templates + their display config
$fields = [];
try {
    // First try to get fields from the active form template
    $stmt = $pdo->query("
        SELECT
            ff.id,
            ff.field_label,
            ff.field_type,
            ff.is_required,
            ff.display_order,
            ff.help_text,
            ft.name AS form_name,
            ft.is_active,
            COALESCE(mdc.show_on_card, 1)   AS show_on_card,
            COALESCE(mdc.show_in_modal, 1)  AS show_in_modal
        FROM form_fields ff
        JOIN form_templates ft ON ff.form_id = ft.id
        LEFT JOIN metadata_display_config mdc ON ff.id = mdc.form_field_id
        WHERE ft.is_active = 1
        ORDER BY ff.display_order ASC, ff.id ASC
    ");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fields = [];
}

// For live preview — get a sample file
$sampleFile = null;
$sampleMeta = [];
try {
    $sampleStmt = $pdo->query("
        SELECT n.id, n.title, n.thumbnail_path, n.file_type, n.file_size, n.created_at
        FROM newspapers n
        WHERE n.deleted_at IS NULL
        ORDER BY n.created_at DESC
        LIMIT 1
    ");
    $sampleFile = $sampleStmt->fetch(PDO::FETCH_ASSOC);

    if ($sampleFile) {
        // Load custom metadata values for preview (keyed by field_id)
        $metaStmt = $pdo->prepare("
            SELECT cmv.field_id, cmv.field_value,
                   ff.field_label, ff.field_type
            FROM custom_metadata_values cmv
            JOIN form_fields ff ON cmv.field_id = ff.id
            WHERE cmv.file_id = ?
            ORDER BY ff.display_order ASC
        ");
        $metaStmt->execute([$sampleFile['id']]);
        $sampleMeta = $metaStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $sampleFile = null;
}

$pageTitle = 'Metadata Display';
$pageCss = ['metadata-display.css'];

include __DIR__ . '/../views/metadata-display.php';
