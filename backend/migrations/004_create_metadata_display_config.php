<?php
/**
 * Migration: Create metadata_display_config table
 * 
 * This migration creates the metadata_display_config table which stores
 * display configuration for custom metadata fields, controlling visibility
 * and display order in file cards and preview modals.
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.6
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Check if table already exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'metadata_display_config'");
        if ($stmt->rowCount() > 0) {
            echo "Migration already applied - metadata_display_config table exists.\n";
            $pdo->rollBack();
            return;
        }
        
        echo "Creating metadata_display_config table...\n";
        
        // Create metadata_display_config table
        $pdo->exec("
            CREATE TABLE metadata_display_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                field_id INT NOT NULL,
                show_on_card TINYINT(1) DEFAULT 1 COMMENT 'Show field on file cards',
                show_in_modal TINYINT(1) DEFAULT 1 COMMENT 'Show field in preview modals',
                card_display_order INT DEFAULT 0 COMMENT 'Display order on file cards (ascending)',
                modal_display_order INT DEFAULT 0 COMMENT 'Display order in preview modals (ascending)',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (field_id) REFERENCES custom_metadata_fields(id) ON DELETE CASCADE,
                UNIQUE KEY unique_field (field_id),
                INDEX idx_card_visibility (show_on_card, card_display_order),
                INDEX idx_modal_visibility (show_in_modal, modal_display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "Table created successfully.\n";
        
        // Create default configurations for existing custom metadata fields
        echo "Creating default configurations for existing fields...\n";
        
        $pdo->exec("
            INSERT INTO metadata_display_config (field_id, show_on_card, show_in_modal, card_display_order, modal_display_order)
            SELECT id, 1, 1, id, id
            FROM custom_metadata_fields
            WHERE is_enabled = 1
        ");
        
        $affectedRows = $pdo->query("SELECT COUNT(*) FROM metadata_display_config")->fetchColumn();
        echo "Created default configurations for {$affectedRows} field(s).\n";
        
        $pdo->commit();
        echo "✅ Migration completed successfully.\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        throw $e;
    }
}

function rollbackMigration($pdo) {
    try {
        $pdo->beginTransaction();
        
        echo "Rolling back migration...\n";
        
        // Drop table if exists
        $pdo->exec("DROP TABLE IF EXISTS metadata_display_config");
        
        $pdo->commit();
        echo "✅ Rollback completed successfully.\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Rollback failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Run migration if executed directly from CLI
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'up';
    
    if ($action === 'up') {
        runMigration($pdo);
    } elseif ($action === 'down') {
        rollbackMigration($pdo);
    } else {
        echo "Usage: php 004_create_metadata_display_config.php [up|down]\n";
        echo "  up   - Run the migration (default)\n";
        echo "  down - Rollback the migration\n";
    }
}
