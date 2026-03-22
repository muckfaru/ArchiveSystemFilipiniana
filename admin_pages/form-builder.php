<?php
/**
 * Form Builder Page
 * Archive System - Quezon City Public Library
 * 
 * Visual drag-and-drop interface for creating and editing form templates
 */

require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect(route_url('dashboard', ['error' => 'Access denied']));
}

$editMode = false;
$formTemplate = null;
$formFields = [];

// Check if editing existing form
if (isset($_GET['id'])) {
    $formId = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
    $stmt->execute([$formId]);
    $formTemplate = $stmt->fetch();
    
    if ($formTemplate) {
        $editMode = true;
        
        // Load form fields
        $stmt = $pdo->prepare("
            SELECT * FROM form_fields 
            WHERE form_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$formId]);
        $formFields = $stmt->fetchAll();
    }
}

// Load view
include __DIR__ . '/../views/form-builder.php';
