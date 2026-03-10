<?php
/**
 * Migration: Create Custom Metadata Tables
 * Archive System - Quezon City Public Library
 * 
 * This migration creates the database schema for the custom metadata system:
 * - custom_metadata_fields: Stores field definitions
 * - custom_metadata_values: Stores user-entered custom metadata values
 * 
 * Usage: php backend/migrations/001_create_custom_metadata_tables.php
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        echo "Starting migration: Create Custom Metadata Tables\n";
        echo "================================================\n\n";
        
        $pdo->beginTransaction();
        
        // Check if tables already exist (idempotency)
        $stmt = $pdo->query("SHOW TABLES LIKE 'custom_metadata_fields'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Migration already applied. Tables exist.\n";
            return;
        }
        
        echo "Creating custom_metadata_fields table...\n";
        
        // Create custom_metadata_fields table
        $pdo->exec("
            CREATE TABLE custom_metadata_fields (
                id INT PRIMARY KEY AUTO_INCREMENT,
                field_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Internal identifier (e.g., author_name)',
                field_label VARCHAR(255) NOT NULL COMMENT 'Display label shown to users',
                field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio') NOT NULL,
                field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio options',
                is_required TINYINT(1) DEFAULT 0 COMMENT '1 = required field, 0 = optional',
                is_enabled TINYINT(1) DEFAULT 1 COMMENT '1 = active, 0 = disabled/soft-deleted',
                display_order INT DEFAULT 0 COMMENT 'Sort order for display on forms',
                validation_rules TEXT DEFAULT NULL COMMENT 'JSON object with validation config (regex, min/max)',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_enabled_order (is_enabled, display_order),
                INDEX idx_field_name (field_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores custom metadata field definitions'
        ");
        
        echo "✓ custom_metadata_fields table created\n\n";
        
        echo "Creating custom_metadata_values table...\n";
        
        // Create custom_metadata_values table
        $pdo->exec("
            CREATE TABLE custom_metadata_values (
                id INT PRIMARY KEY AUTO_INCREMENT,
                file_id INT NOT NULL COMMENT 'References newspapers.id',
                field_id INT DEFAULT NULL COMMENT 'References custom_metadata_fields.id',
                field_value TEXT DEFAULT NULL COMMENT 'User-entered value for this field',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (file_id) REFERENCES newspapers(id) ON DELETE CASCADE,
                FOREIGN KEY (field_id) REFERENCES custom_metadata_fields(id) ON DELETE SET NULL,
                INDEX idx_file_id (file_id),
                INDEX idx_field_id (field_id),
                UNIQUE KEY unique_file_field (file_id, field_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores custom metadata values for each file'
        ");
        
        echo "✓ custom_metadata_values table created\n\n";
        
        echo "Updating activity_logs enum...\n";
        
        // Update activity_logs enum to include custom_metadata_update
        $pdo->exec("
            ALTER TABLE activity_logs 
            MODIFY action ENUM(
                'create_user', 
                'edit_user', 
                'delete_user', 
                'upload', 
                'edit', 
                'delete', 
                'restore', 
                'permanent_delete', 
                'login', 
                'logout', 
                'settings_update',
                'custom_metadata_update'
            ) NOT NULL
        ");
        
        echo "✓ activity_logs enum updated\n\n";
        
        $pdo->commit();
        
        echo "================================================\n";
        echo "✓ Migration completed successfully!\n\n";
        echo "Tables created:\n";
        echo "  - custom_metadata_fields\n";
        echo "  - custom_metadata_values\n";
        echo "  - activity_logs (updated)\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

function rollbackMigration($pdo) {
    try {
        echo "Starting rollback: Drop Custom Metadata Tables\n";
        echo "================================================\n\n";
        
        $pdo->beginTransaction();
        
        echo "Dropping custom_metadata_values table...\n";
        $pdo->exec("DROP TABLE IF EXISTS custom_metadata_values");
        echo "✓ custom_metadata_values table dropped\n\n";
        
        echo "Dropping custom_metadata_fields table...\n";
        $pdo->exec("DROP TABLE IF EXISTS custom_metadata_fields");
        echo "✓ custom_metadata_fields table dropped\n\n";
        
        echo "Reverting activity_logs enum...\n";
        $pdo->exec("
            ALTER TABLE activity_logs 
            MODIFY action ENUM(
                'create_user', 
                'edit_user', 
                'delete_user', 
                'upload', 
                'edit', 
                'delete', 
                'restore', 
                'permanent_delete', 
                'login', 
                'logout', 
                'settings_update'
            ) NOT NULL
        ");
        echo "✓ activity_logs enum reverted\n\n";
        
        $pdo->commit();
        
        echo "================================================\n";
        echo "✓ Rollback completed successfully!\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Rollback failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Command-line interface
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'up';
    
    if ($command === 'up') {
        runMigration($pdo);
    } elseif ($command === 'down') {
        echo "WARNING: This will delete all custom metadata fields and values!\n";
        echo "Are you sure you want to rollback? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if ($line === 'yes') {
            rollbackMigration($pdo);
        } else {
            echo "Rollback cancelled.\n";
        }
    } else {
        echo "Usage: php 001_create_custom_metadata_tables.php [up|down]\n";
        echo "  up   - Run migration (default)\n";
        echo "  down - Rollback migration\n";
    }
} else {
    // Web interface (for testing only - should be disabled in production)
    echo "<pre>";
    runMigration($pdo);
    echo "</pre>";
}
