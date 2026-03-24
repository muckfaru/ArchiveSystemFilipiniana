<?php
/**
 * Migration 009: Sync Default Upload Form
 *
 * Aligns the active upload metadata form with the current team standard:
 * Title, Publication Type, Publication Date, Category, Language,
 * Keywords, and Description.
 *
 * This migration is idempotent and safe to re-run.
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo)
{
    $desiredTemplate = [
        'name' => 'Default Archive Form',
        'description' => 'Default metadata form for archive documents',
        'status' => 'active',
        'is_active' => 1,
    ];

    $desiredFields = [
        [
            'field_label' => 'Title',
            'field_type' => 'text',
            'field_options' => null,
            'is_required' => 1,
            'display_order' => 0,
            'help_text' => 'Document title',
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
        [
            'field_label' => 'Publication Type',
            'field_type' => 'select',
            'field_options' => json_encode(['Newspaper', 'Magazine']),
            'is_required' => 0,
            'display_order' => 1,
            'help_text' => null,
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
        [
            'field_label' => 'Publication Date',
            'field_type' => 'date',
            'field_options' => null,
            'is_required' => 0,
            'display_order' => 2,
            'help_text' => 'Date of publication (YYYY-MM-DD)',
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
        [
            'field_label' => 'Category',
            'field_type' => 'select',
            'field_options' => json_encode([
                'Politics',
                'Sports',
                'Business',
                'Culture',
                'Entertainment',
                'Technology',
                'Health',
                'Education',
                'Science',
                'Local News',
            ]),
            'is_required' => 0,
            'display_order' => 3,
            'help_text' => 'Document category',
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
        [
            'field_label' => 'Language',
            'field_type' => 'select',
            'field_options' => json_encode(['English', 'Filipino', 'Tagalog', 'Cebuano', 'Ilocano']),
            'is_required' => 0,
            'display_order' => 4,
            'help_text' => 'Document language',
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
        [
            'field_label' => 'Keywords',
            'field_type' => 'tags',
            'field_options' => null,
            'is_required' => 0,
            'display_order' => 5,
            'help_text' => 'Searchable keywords or tags',
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
        [
            'field_label' => 'Description',
            'field_type' => 'textarea',
            'field_options' => null,
            'is_required' => 0,
            'display_order' => 6,
            'help_text' => 'Brief description of the document',
            'show_on_card' => 1,
            'show_in_modal' => 1,
        ],
    ];

    try {
        echo "Starting Migration 009: Sync Default Upload Form\n";
        echo "=================================================\n";

        $pdo->beginTransaction();

        $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1");

        $templateStmt = $pdo->prepare("SELECT * FROM form_templates WHERE name = ? ORDER BY id ASC LIMIT 1");
        $templateStmt->execute([$desiredTemplate['name']]);
        $template = $templateStmt->fetch();

        if ($template) {
            $updateTemplateStmt = $pdo->prepare("
                UPDATE form_templates
                SET description = ?, status = ?, is_active = ?
                WHERE id = ?
            ");
            $updateTemplateStmt->execute([
                $desiredTemplate['description'],
                $desiredTemplate['status'],
                $desiredTemplate['is_active'],
                $template['id'],
            ]);

            $templateId = (int) $template['id'];
            echo "Using existing template ID {$templateId}: {$desiredTemplate['name']}\n";
        } else {
            $insertTemplateStmt = $pdo->prepare("
                INSERT INTO form_templates (name, description, status, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $insertTemplateStmt->execute([
                $desiredTemplate['name'],
                $desiredTemplate['description'],
                $desiredTemplate['status'],
                $desiredTemplate['is_active'],
            ]);

            $templateId = (int) $pdo->lastInsertId();
            echo "Created template ID {$templateId}: {$desiredTemplate['name']}\n";
        }

        $existingFieldsStmt = $pdo->prepare("
            SELECT id, field_label
            FROM form_fields
            WHERE form_id = ?
            ORDER BY id ASC
        ");
        $existingFieldsStmt->execute([$templateId]);
        $existingFields = $existingFieldsStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingByLabel = [];
        foreach ($existingFields as $field) {
            $existingByLabel[strtolower(trim($field['field_label']))] = (int) $field['id'];
        }

        $insertFieldStmt = $pdo->prepare("
            INSERT INTO form_fields (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $updateFieldStmt = $pdo->prepare("
            UPDATE form_fields
            SET field_label = ?, field_type = ?, field_options = ?, is_required = ?, display_order = ?, help_text = ?
            WHERE id = ? AND form_id = ?
        ");
        $upsertDisplayStmt = $pdo->prepare("
            INSERT INTO metadata_display_config (form_field_id, show_on_card, show_in_modal)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                show_on_card = VALUES(show_on_card),
                show_in_modal = VALUES(show_in_modal)
        ");

        $keepFieldIds = [];

        foreach ($desiredFields as $field) {
            $labelKey = strtolower(trim($field['field_label']));

            if (isset($existingByLabel[$labelKey])) {
                $fieldId = $existingByLabel[$labelKey];
                $updateFieldStmt->execute([
                    $field['field_label'],
                    $field['field_type'],
                    $field['field_options'],
                    $field['is_required'],
                    $field['display_order'],
                    $field['help_text'],
                    $fieldId,
                    $templateId,
                ]);
                echo "Updated field: {$field['field_label']}\n";
            } else {
                $insertFieldStmt->execute([
                    $templateId,
                    $field['field_label'],
                    $field['field_type'],
                    $field['field_options'],
                    $field['is_required'],
                    $field['display_order'],
                    $field['help_text'],
                ]);
                $fieldId = (int) $pdo->lastInsertId();
                echo "Inserted field: {$field['field_label']}\n";
            }

            $keepFieldIds[] = $fieldId;
            $upsertDisplayStmt->execute([
                $fieldId,
                $field['show_on_card'],
                $field['show_in_modal'],
            ]);
        }

        if (!empty($keepFieldIds)) {
            $placeholders = implode(',', array_fill(0, count($keepFieldIds), '?'));
            $deleteStmt = $pdo->prepare("DELETE FROM form_fields WHERE form_id = ? AND id NOT IN ({$placeholders})");
            $deleteStmt->execute(array_merge([$templateId], $keepFieldIds));
            echo "Removed fields not in the standard upload form.\n";
        }

        $pdo->commit();

        echo "=================================================\n";
        echo "Migration 009 completed successfully.\n";
        echo "Active upload form now matches the current team standard.\n";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo "Migration 009 failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    runMigration($pdo);
}
