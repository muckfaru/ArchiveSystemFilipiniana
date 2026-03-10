<?php
/**
 * Form Templates API
 * Archive System - Quezon City Public Library
 * 
 * CRUD operations for form templates
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createFormTemplate($pdo, $currentUser, $input);
            break;
        case 'update':
            updateFormTemplate($pdo, $currentUser, $input);
            break;
        case 'delete':
            deleteFormTemplate($pdo, $currentUser, $input);
            break;
        case 'set_active':
            setActiveFormTemplate($pdo, $currentUser, $input);
            break;
        case 'duplicate':
            duplicateFormTemplate($pdo, $currentUser, $input);
            break;
        case 'archive':
            archiveFormTemplate($pdo, $currentUser, $input);
            break;
        case 'list':
            listFormTemplates($pdo);
            break;
        case 'get':
            getFormTemplate($pdo, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createFormTemplate($pdo, $currentUser, $input) {
    $name = sanitize($input['name']);
    $description = sanitize($input['description'] ?? '');
    $status = $input['status']; // 'draft' or 'active'
    $fields = $input['fields'];
    
    if (empty($name)) {
        throw new Exception('Form name is required');
    }
    
    if (empty($fields)) {
        throw new Exception('At least one field is required');
    }
    
    $pdo->beginTransaction();
    
    try {
        // If status is active, deactivate other forms
        if ($status === 'active') {
            $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1");
        }
        
        // Insert form template
        $stmt = $pdo->prepare("
            INSERT INTO form_templates (name, description, status, is_active)
            VALUES (?, ?, ?, ?)
        ");
        $isActive = ($status === 'active') ? 1 : 0;
        $stmt->execute([$name, $description, $status, $isActive]);
        
        $formId = $pdo->lastInsertId();
        
        // Insert form fields
        $fieldStmt = $pdo->prepare("
            INSERT INTO form_fields 
            (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($fields as $index => $field) {
            $fieldStmt->execute([
                $formId,
                $field['field_label'],
                $field['field_type'],
                $field['field_options'],
                $field['is_required'],
                $index,
                $field['help_text'] ?? null
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Created form template: $name");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Form template created successfully',
            'form_id' => $formId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    $name = sanitize($input['name']);
    $description = sanitize($input['description'] ?? '');
    $status = $input['status'];
    $fields = $input['fields'];
    
    if (empty($name)) {
        throw new Exception('Form name is required');
    }
    
    if (empty($fields)) {
        throw new Exception('At least one field is required');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Check if form exists
        $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $existingForm = $stmt->fetch();
        
        if (!$existingForm) {
            throw new Exception('Form template not found');
        }
        
        // If status is active, deactivate other forms
        if ($status === 'active') {
            $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1 AND id != $formId");
        }
        
        // Update form template
        $stmt = $pdo->prepare("
            UPDATE form_templates 
            SET name = ?, description = ?, status = ?, is_active = ?
            WHERE id = ?
        ");
        $isActive = ($status === 'active') ? 1 : 0;
        $stmt->execute([$name, $description, $status, $isActive, $formId]);
        
        // Delete existing fields
        $stmt = $pdo->prepare("DELETE FROM form_fields WHERE form_id = ?");
        $stmt->execute([$formId]);
        
        // Insert updated fields
        $fieldStmt = $pdo->prepare("
            INSERT INTO form_fields 
            (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($fields as $index => $field) {
            $fieldStmt->execute([
                $formId,
                $field['field_label'],
                $field['field_type'],
                $field['field_options'],
                $field['is_required'],
                $index,
                $field['help_text'] ?? null
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Updated form template: $name");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Form template updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Get form name for logging
        $stmt = $pdo->prepare("SELECT name FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch();
        
        if (!$form) {
            throw new Exception('Form template not found');
        }
        
        // Check if form has associated values
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM custom_metadata_values WHERE form_id = ?");
        $stmt->execute([$formId]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0 && !isset($input['confirm'])) {
            echo json_encode([
                'success' => false,
                'message' => "This form has $count associated values. Are you sure?",
                'requires_confirmation' => true,
                'affected_count' => $count
            ]);
            return;
        }
        
        // Delete form template (CASCADE will delete form_fields)
        $stmt = $pdo->prepare("DELETE FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Deleted form template: {$form['name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Form template deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function setActiveFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Deactivate all forms
        $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1");
        
        // Activate selected form
        $stmt = $pdo->prepare("UPDATE form_templates SET is_active = 1, status = 'active' WHERE id = ?");
        $stmt->execute([$formId]);
        
        $pdo->commit();
        
        // Log activity
        $stmt = $pdo->prepare("SELECT name FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $formName = $stmt->fetch()['name'];
        logActivity($currentUser['id'], 'custom_metadata_update', "Set active form: $formName");
        
        echo json_encode([
            'success' => true,
            'message' => 'Form template activated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicateFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Get original form
        $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $originalForm = $stmt->fetch();
        
        if (!$originalForm) {
            throw new Exception('Form template not found');
        }
        
        // Create duplicate
        $newName = $originalForm['name'] . ' (Copy)';
        $stmt = $pdo->prepare("
            INSERT INTO form_templates (name, description, status, is_active)
            VALUES (?, ?, 'draft', 0)
        ");
        $stmt->execute([$newName, $originalForm['description']]);
        
        $newFormId = $pdo->lastInsertId();
        
        // Copy fields
        $stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY display_order");
        $stmt->execute([$formId]);
        $fields = $stmt->fetchAll();
        
        $fieldStmt = $pdo->prepare("
            INSERT INTO form_fields 
            (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($fields as $field) {
            $fieldStmt->execute([
                $newFormId,
                $field['field_label'],
                $field['field_type'],
                $field['field_options'],
                $field['is_required'],
                $field['display_order'],
                $field['help_text']
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Duplicated form template: {$originalForm['name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Form template duplicated successfully',
            'form_id' => $newFormId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function archiveFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $stmt = $pdo->prepare("UPDATE form_templates SET status = 'archived', is_active = 0 WHERE id = ?");
    $stmt->execute([$formId]);
    
    // Log activity
    $stmt = $pdo->prepare("SELECT name FROM form_templates WHERE id = ?");
    $stmt->execute([$formId]);
    $formName = $stmt->fetch()['name'];
    logActivity($currentUser['id'], 'custom_metadata_update', "Archived form template: $formName");
    
    echo json_encode([
        'success' => true,
        'message' => 'Form template archived successfully'
    ]);
}

function listFormTemplates($pdo) {
    $stmt = $pdo->query("
        SELECT 
            ft.*,
            COUNT(ff.id) as field_count
        FROM form_templates ft
        LEFT JOIN form_fields ff ON ft.id = ff.form_id
        GROUP BY ft.id
        ORDER BY ft.updated_at DESC
    ");
    $templates = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

function getFormTemplate($pdo, $input) {
    $formId = intval($input['form_id']);
    
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
    $stmt->execute([$formId]);
    $template = $stmt->fetch();
    
    if (!$template) {
        throw new Exception('Form template not found');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY display_order");
    $stmt->execute([$formId]);
    $fields = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'template' => $template,
        'fields' => $fields
    ]);
}
