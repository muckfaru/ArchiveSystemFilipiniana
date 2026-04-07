<?php
/**
 * Migration: Remove Hardcoded Metadata Columns
 * Archive System - Quezon City Public Library
 * 
 * This migration removes hardcoded metadata columns from the newspapers table
 * and migrates existing data to the custom_metadata_values table.
 * 
 * IMPORTANT: Run this AFTER running 001_create_custom_metadata_tables.php
 * 
 * Usage: php backend/migrations/003_remove_hardcoded_metadata.php
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        echo "Starting migration: Remove Hardcoded Metadata Columns\n";
        echo "======================================================\n\n";
        
        $pdo->beginTransaction();
        
        // Check if custom_metadata_fields table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'custom_metadata_fields'");
        if ($stmt->rowCount() === 0) {
            throw new Exception("custom_metadata_fields table does not exist. Please run 001_create_custom_metadata_tables.php first.");
        }
        
        // Check if hardcoded columns still exist
        $stmt = $pdo->query("SHOW COLUMNS FROM newspapers LIKE 'publication_date'");
        if ($stmt->rowCount() === 0) {
            echo "✓ Migration already applied. Hardcoded columns removed.\n";
            return;
        }
        
        echo "Step 1: Creating default custom metadata fields...\n";
        
        // Create default custom metadata fields for common newspaper metadata
        $defaultFields = [
            ['field_name' => 'publication_date', 'field_label' => 'Publication Date', 'field_type' => 'date', 'display_order' => 1],
            ['field_name' => 'edition', 'field_label' => 'Edition', 'field_type' => 'text', 'display_order' => 2],
            ['field_name' => 'category', 'field_label' => 'Category', 'field_type' => 'select', 'display_order' => 3],
            ['field_name' => 'language', 'field_label' => 'Language', 'field_type' => 'select', 'display_order' => 4],
            ['field_name' => 'page_count', 'field_label' => 'Page Count', 'field_type' => 'number', 'display_order' => 5],
            ['field_name' => 'keywords', 'field_label' => 'Keywords', 'field_type' => 'textarea', 'display_order' => 6],
            ['field_name' => 'publisher', 'field_label' => 'Publisher', 'field_type' => 'text', 'display_order' => 7],
            ['field_name' => 'volume_issue', 'field_label' => 'Volume/Issue', 'field_type' => 'text', 'display_order' => 8],
            ['field_name' => 'description', 'field_label' => 'Description', 'field_type' => 'textarea', 'display_order' => 9],
        ];
        
        $fieldIdMap = [];
        
        $hasCategoriesTable = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;
        $hasLanguagesTable = $pdo->query("SHOW TABLES LIKE 'languages'")->rowCount() > 0;

        foreach ($defaultFields as $field) {
            // Check if field already exists
            $stmt = $pdo->prepare("SELECT id FROM custom_metadata_fields WHERE field_name = ?");
            $stmt->execute([$field['field_name']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $fieldIdMap[$field['field_name']] = $existing['id'];
                echo "  ✓ Field '{$field['field_label']}' already exists (ID: {$existing['id']})\n";
            } else {
                // Get field options for select fields
                $fieldOptions = null;
                if ($field['field_name'] === 'category' && $hasCategoriesTable) {
                    $categories = $pdo->query("SELECT name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                    $fieldOptions = json_encode($categories);
                } elseif ($field['field_name'] === 'language' && $hasLanguagesTable) {
                    $languages = $pdo->query("SELECT name FROM languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                    $fieldOptions = json_encode($languages);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO custom_metadata_fields 
                    (field_name, field_label, field_type, field_options, display_order, is_enabled, is_required)
                    VALUES (?, ?, ?, ?, ?, 1, 0)
                ");
                $stmt->execute([
                    $field['field_name'],
                    $field['field_label'],
                    $field['field_type'],
                    $fieldOptions,
                    $field['display_order']
                ]);
                
                $fieldIdMap[$field['field_name']] = $pdo->lastInsertId();
                echo "  ✓ Created field '{$field['field_label']}' (ID: {$fieldIdMap[$field['field_name']]})\n";
            }
        }
        
        echo "\n";
        echo "Step 2: Migrating existing newspaper metadata...\n";
        
        // Get all newspapers with their hardcoded metadata
        $newspapers = $pdo->query("
            SELECT 
                id, 
                publication_date, 
                edition, 
                category_id, 
                language_id, 
                page_count, 
                keywords, 
                publisher, 
                volume_issue, 
                description
            FROM newspapers
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $migratedCount = 0;
        
        foreach ($newspapers as $newspaper) {
            $fileId = $newspaper['id'];
            
            // Migrate each field
            $migrations = [
                'publication_date' => $newspaper['publication_date'],
                'edition' => $newspaper['edition'],
                'page_count' => $newspaper['page_count'],
                'keywords' => $newspaper['keywords'],
                'publisher' => $newspaper['publisher'],
                'volume_issue' => $newspaper['volume_issue'],
                'description' => $newspaper['description'],
            ];
            
            // Handle category (convert ID to name)
            if ($newspaper['category_id'] && $hasCategoriesTable) {
                $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                $stmt->execute([$newspaper['category_id']]);
                $category = $stmt->fetchColumn();
                $migrations['category'] = $category;
            }
            
            // Handle language (convert ID to name)
            if ($newspaper['language_id'] && $hasLanguagesTable) {
                $stmt = $pdo->prepare("SELECT name FROM languages WHERE id = ?");
                $stmt->execute([$newspaper['language_id']]);
                $language = $stmt->fetchColumn();
                $migrations['language'] = $language;
            }
            
            // Insert metadata values
            foreach ($migrations as $fieldName => $value) {
                if ($value !== null && $value !== '') {
                    $fieldId = $fieldIdMap[$fieldName];
                    
                    // Check if value already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM custom_metadata_values 
                        WHERE file_id = ? AND field_id = ?
                    ");
                    $stmt->execute([$fileId, $fieldId]);
                    
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("
                            INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$fileId, $fieldId, $value]);
                    }
                }
            }
            
            $migratedCount++;
        }
        
        echo "  ✓ Migrated metadata for {$migratedCount} newspapers\n\n";
        
        echo "Step 3: Removing hardcoded columns from newspapers table...\n";
        
        // Drop foreign key constraints first
        $pdo->exec("ALTER TABLE newspapers DROP FOREIGN KEY newspapers_ibfk_1");
        $pdo->exec("ALTER TABLE newspapers DROP FOREIGN KEY newspapers_ibfk_2");
        
        // Remove hardcoded metadata columns
        $columnsToRemove = [
            'publication_date',
            'edition',
            'category_id',
            'language_id',
            'page_count',
            'keywords',
            'publisher',
            'volume_issue',
            'description'
        ];
        
        foreach ($columnsToRemove as $column) {
            $pdo->exec("ALTER TABLE newspapers DROP COLUMN {$column}");
            echo "  ✓ Removed column: {$column}\n";
        }
        
        echo "\n";
        
        $pdo->commit();
        
        echo "======================================================\n";
        echo "✓ Migration completed successfully!\n\n";
        echo "Summary:\n";
        echo "  - Created " . count($defaultFields) . " default custom metadata fields\n";
        echo "  - Migrated metadata for {$migratedCount} newspapers\n";
        echo "  - Removed " . count($columnsToRemove) . " hardcoded columns\n\n";
        echo "Next steps:\n";
        echo "  1. Test the upload page to ensure custom metadata works\n";
        echo "  2. Test the dashboard to ensure file preview shows custom metadata\n";
        echo "  3. Test the public page to ensure files display correctly\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

// Command-line interface
if (php_sapi_name() === 'cli') {
    echo "\n";
    echo "WARNING: This migration will:\n";
    echo "  1. Create default custom metadata fields\n";
    echo "  2. Migrate existing newspaper metadata to custom_metadata_values\n";
    echo "  3. Remove hardcoded columns from newspapers table\n\n";
    echo "Make sure you have a database backup before proceeding!\n\n";
    echo "Do you want to continue? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if ($line === 'yes') {
        runMigration($pdo);
    } else {
        echo "Migration cancelled.\n";
    }
} else {
    echo "<pre>";
    echo "This migration can only be run from the command line.\n";
    echo "Usage: php backend/migrations/003_remove_hardcoded_metadata.php\n";
    echo "</pre>";
}
