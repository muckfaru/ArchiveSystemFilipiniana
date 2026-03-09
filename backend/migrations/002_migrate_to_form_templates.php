<?php
/**
 * Migration: Migrate to Form Templates System
 * Archive System - Quezon City Public Library
 * 
 * This migration transforms the custom metadata system from individual
 * field management to template-based form management.
 * 
 * Usage: php backend/migrations/002_migrate_to_form_templates.php
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        echo "Starting migration: Migrate to Form Templates System\n";
        echo "====================================================\n\n";
        
        $pdo->beginTransaction();
        
        // Check if migration already applied
        $stmt = $pdo->query("SHOW TABLES LIKE 'form_templates'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Migration already applied. Tables exist.\n";
            return;
        }
        
        // Step 1: Create form_templates table
        echo "Step 1: Creating form_templates table...\n";
        $pdo->exec("
            CREATE TABLE form_templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL COMMENT 'User-facing form template name',
                description TEXT DEFAULT NULL COMMENT 'Optional description of form purpose',
                status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
                is_active TINYINT(1) DEFAULT 0 COMMENT 'Only one form can be active at a time',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores form template definitions'
        ");
        echo "✓ form_templates table created\n\n";
        
        // Step 2: Create form_fields table
        echo "Step 2: Creating form_fields table...\n";
        $pdo->exec("
            CREATE TABLE form_fields (
                id INT PRIMARY KEY AUTO_INCREMENT,
                form_id INT NOT NULL COMMENT 'References form_templates.id',
                field_label VARCHAR(255) NOT NULL COMMENT 'Display label shown to users',
                field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio') NOT NULL,
                field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio options',
                is_required TINYINT(1) DEFAULT 0 COMMENT '1 = required field, 0 = optional',
                display_order INT DEFAULT 0 COMMENT 'Sort order for display on forms',
                help_text TEXT DEFAULT NULL COMMENT 'Optional help text displayed near field',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE CASCADE,
                INDEX idx_form_order (form_id, display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores fields within form templates'
        ");
        echo "✓ form_fields table created\n\n";
        
        // Step 3: Check if custom_metadata_fields has data
        echo "Step 3: Checking for existing custom metadata fields...\n";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_metadata_fields");
        $fieldCount = $stmt->fetch()['count'];
        echo "Found $fieldCount existing custom metadata fields\n\n";
        
        $defaultFormId = null;
        $fieldMapping = []; // Map old field_id to new field_id
        
        if ($fieldCount > 0) {
            // Step 4: Create default form template
            echo "Step 4: Creating default form template...\n";
            $pdo->exec("
                INSERT INTO form_templates (name, description, status, is_active)
                VALUES (
                    'Default Metadata Form',
                    'Migrated from existing custom metadata fields',
                    'active',
                    1
                )
            ");
            $defaultFormId = $pdo->lastInsertId();
            echo "✓ Default form template created (ID: $defaultFormId)\n\n";
            
            // Step 5: Migrate fields to form_fields
            echo "Step 5: Migrating fields to form_fields table...\n";
            $stmt = $pdo->query("
                SELECT * FROM custom_metadata_fields 
                WHERE is_enabled = 1 
                ORDER BY display_order ASC
            ");
            $existingFields = $stmt->fetchAll();
            
            $insertStmt = $pdo->prepare("
                INSERT INTO form_fields 
                (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
                VALUES (?, ?, ?, ?, ?, ?, NULL)
            ");
            
            foreach ($existingFields as $field) {
                $insertStmt->execute([
                    $defaultFormId,
                    $field['field_label'],
                    $field['field_type'],
                    $field['field_options'],
                    $field['is_required'],
                    $field['display_order']
                ]);
                
                $newFieldId = $pdo->lastInsertId();
                $fieldMapping[$field['id']] = $newFieldId;
            }
            
            echo "✓ Migrated " . count($existingFields) . " fields\n\n";
        } else {
            echo "No existing fields to migrate\n\n";
        }
        
        // Step 6: Add form_id column to custom_metadata_values
        echo "Step 6: Adding form_id column to custom_metadata_values...\n";
        $pdo->exec("
            ALTER TABLE custom_metadata_values
            ADD COLUMN form_id INT DEFAULT NULL COMMENT 'References form_templates.id' AFTER file_id
        ");
        echo "✓ Column added\n\n";
        
        // Step 7: Add foreign key constraint
        echo "Step 7: Adding foreign key constraint...\n";
        $pdo->exec("
            ALTER TABLE custom_metadata_values
            ADD FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE SET NULL
        ");
        echo "✓ Foreign key added\n\n";
        
        // Step 8: Add index
        echo "Step 8: Adding index on form_id...\n";
        $pdo->exec("
            ALTER TABLE custom_metadata_values
            ADD INDEX idx_form_id (form_id)
        ");
        echo "✓ Index added\n\n";
        
        // Step 9: Update existing custom_metadata_values
        if ($defaultFormId && $fieldCount > 0) {
            echo "Step 9: Updating existing custom_metadata_values...\n";
            
            // Update form_id for all existing values
            $pdo->exec("
                UPDATE custom_metadata_values
                SET form_id = $defaultFormId
                WHERE form_id IS NULL
            ");
            
            // Update field_id mapping
            foreach ($fieldMapping as $oldFieldId => $newFieldId) {
                $stmt = $pdo->prepare("
                    UPDATE custom_metadata_values
                    SET field_id = ?
                    WHERE field_id = ?
                ");
                $stmt->execute([$newFieldId, $oldFieldId]);
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_metadata_values WHERE form_id = $defaultFormId");
            $updatedCount = $stmt->fetch()['count'];
            echo "✓ Updated $updatedCount metadata values\n\n";
        } else {
            echo "Step 9: No existing values to update\n\n";
        }
        
        $pdo->commit();
        
        echo "====================================================\n";
        echo "✓ Migration completed successfully!\n\n";
        echo "Summary:\n";
        echo "  - form_templates table created\n";
        echo "  - form_fields table created\n";
        echo "  - custom_metadata_values table modified\n";
        if ($defaultFormId) {
            echo "  - Default form template created (ID: $defaultFormId)\n";
            echo "  - $fieldCount fields migrated\n";
        }
        echo "\nNext steps:\n";
        echo "  1. Review the default form template in the Form Library\n";
        echo "  2. Create additional form templates as needed\n";
        echo "  3. The old custom_metadata_fields table is preserved for reference\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

function rollbackMigration($pdo) {
    try {
        echo "Starting rollback: Revert Form Templates Migration\n";
        echo "====================================================\n\n";
        
        $pdo->beginTransaction();
        
        echo "WARNING: This will delete all form templates and revert to individual field management!\n";
        echo "Existing custom_metadata_values will be preserved but form_id references will be lost.\n\n";
        
        // Remove form_id column from custom_metadata_values
        echo "Removing form_id column from custom_metadata_values...\n";
        
        // Get the foreign key name dynamically
        $stmt = $pdo->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'custom_metadata_values' 
            AND COLUMN_NAME = 'form_id' 
            AND REFERENCED_TABLE_NAME = 'form_templates'
        ");
        $fkName = $stmt->fetch();
        
        if ($fkName) {
            $pdo->exec("ALTER TABLE custom_metadata_values DROP FOREIGN KEY " . $fkName['CONSTRAINT_NAME']);
        }
        
        $pdo->exec("ALTER TABLE custom_metadata_values DROP INDEX idx_form_id");
        $pdo->exec("ALTER TABLE custom_metadata_values DROP COLUMN form_id");
        echo "✓ Column removed\n\n";
        
        // Drop form_fields table
        echo "Dropping form_fields table...\n";
        $pdo->exec("DROP TABLE IF EXISTS form_fields");
        echo "✓ form_fields table dropped\n\n";
        
        // Drop form_templates table
        echo "Dropping form_templates table...\n";
        $pdo->exec("DROP TABLE IF EXISTS form_templates");
        echo "✓ form_templates table dropped\n\n";
        
        $pdo->commit();
        
        echo "====================================================\n";
        echo "✓ Rollback completed successfully!\n\n";
        echo "Note: custom_metadata_fields table was preserved.\n";
        echo "You may need to re-enable fields manually.\n\n";
        
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
        echo "WARNING: This will delete all form templates!\n";
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
        echo "Usage: php 002_migrate_to_form_templates.php [up|down]\n";
        echo "  up   - Run migration (default)\n";
        echo "  down - Rollback migration\n";
    }
} else {
    // Web interface (for testing only - should be disabled in production)
    echo "<pre>";
    runMigration($pdo);
    echo "</pre>";
}
