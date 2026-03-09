<?php
/**
 * Migration 006: Sync Category Field Options with Categories Table
 * 
 * This migration ensures that the Category field in the active form template
 * has all categories from the categories table as options.
 * 
 * Issue: The Category field options were hardcoded and didn't include all categories,
 * causing files to show "UNCATEGORIZED" on public pages.
 */

require_once __DIR__ . '/../core/config.php';

function runMigration()
{
    global $pdo;

    try {
        $pdo->beginTransaction();

        echo "Starting Migration 006: Sync Category Field Options...\n";

        // Get all categories from categories table
        $stmt = $pdo->query("SELECT name FROM categories ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($categories)) {
            echo "Warning: No categories found in categories table. Skipping migration.\n";
            $pdo->rollBack();
            return;
        }

        echo "Found " . count($categories) . " categories: " . implode(', ', $categories) . "\n";

        // Get all Category fields in all form templates
        $stmt = $pdo->query("SELECT ff.id, ff.form_id, ft.name as form_name, ft.is_active, ff.field_options
                             FROM form_fields ff
                             JOIN form_templates ft ON ff.form_id = ft.id
                             WHERE ff.field_label = 'Category'");
        $categoryFields = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($categoryFields)) {
            echo "Warning: No Category fields found in form_fields table. Skipping migration.\n";
            $pdo->rollBack();
            return;
        }

        echo "Found " . count($categoryFields) . " Category field(s) to update:\n";

        // Update each Category field with all categories
        $categoryOptionsJson = json_encode($categories);
        $updateStmt = $pdo->prepare("UPDATE form_fields SET field_options = ? WHERE id = ?");

        foreach ($categoryFields as $field) {
            $oldOptions = $field['field_options'];
            $updateStmt->execute([$categoryOptionsJson, $field['id']]);
            
            $activeStatus = $field['is_active'] ? '(ACTIVE)' : '';
            echo "  - Updated Category field in form '{$field['form_name']}' {$activeStatus}\n";
            echo "    Old options: {$oldOptions}\n";
            echo "    New options: {$categoryOptionsJson}\n";
        }

        $pdo->commit();
        echo "\n✓ Migration 006 completed successfully!\n";
        echo "  All Category fields now have options synced with categories table.\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Migration 006 failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Run migration if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    runMigration();
}
