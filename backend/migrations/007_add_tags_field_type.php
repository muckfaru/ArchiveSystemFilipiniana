<?php
/**
 * Migration 007: Add 'tags' to field_type ENUM
 * 
 * Adds 'tags' as a valid field_type for both custom_metadata_fields and form_fields tables.
 */

require_once __DIR__ . '/../core/config.php';

try {
    // Add 'tags' to custom_metadata_fields.field_type ENUM
    $pdo->exec("
        ALTER TABLE custom_metadata_fields 
        MODIFY COLUMN field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio', 'tags') NOT NULL
    ");
    echo "✓ Updated custom_metadata_fields.field_type ENUM to include 'tags'\n";

    // Add 'tags' to form_fields.field_type ENUM
    $pdo->exec("
        ALTER TABLE form_fields 
        MODIFY COLUMN field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio', 'tags') NOT NULL
    ");
    echo "✓ Updated form_fields.field_type ENUM to include 'tags'\n";

    echo "\n✅ Migration 007 completed successfully!\n";

} catch (PDOException $e) {
    echo "✗ Migration 007 failed: " . $e->getMessage() . "\n";
    exit(1);
}
