<?php
/**
 * Migration 008: Fix custom_metadata_values foreign key
 * 
 * Problem: The field_id foreign key referenced custom_metadata_fields (which is empty)
 * instead of form_fields (which holds the actual field definitions).
 * This caused ALL metadata inserts to fail silently due to FK constraint violation.
 * 
 * Solution: Drop the old FK and add a new one referencing form_fields.
 */

require_once __DIR__ . '/../core/config.php';

try {
    // Check if old FK still exists
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'custom_metadata_values' 
          AND CONSTRAINT_NAME = 'custom_metadata_values_ibfk_2'
    ");
    
    if ($stmt->fetch()) {
        $pdo->exec("ALTER TABLE custom_metadata_values DROP FOREIGN KEY custom_metadata_values_ibfk_2");
        echo "Dropped old FK custom_metadata_values_ibfk_2 (referenced custom_metadata_fields)\n";
    }

    // Check if new FK already exists
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'custom_metadata_values' 
          AND CONSTRAINT_NAME = 'fk_cmv_form_field'
    ");
    
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE custom_metadata_values 
            ADD CONSTRAINT fk_cmv_form_field 
            FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE SET NULL
        ");
        echo "Added new FK fk_cmv_form_field (references form_fields)\n";
    }

    echo "Migration 008 completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration 008 error: " . $e->getMessage() . "\n";
}
