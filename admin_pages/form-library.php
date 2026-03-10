<?php
/**
 * Form Library Page
 * Archive System - Quezon City Public Library
 * 
 * Displays all form templates with filtering, search, and management actions
 */

require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect('dashboard.php?error=' . urlencode('Access denied'));
}

// Get all form templates with field counts
$stmt = $pdo->query("
    SELECT 
        ft.*,
        COUNT(ff.id) as field_count
    FROM form_templates ft
    LEFT JOIN form_fields ff ON ft.id = ff.form_id
    GROUP BY ft.id
    ORDER BY ft.updated_at DESC
");
$formTemplates = $stmt->fetchAll();

// Load view
include __DIR__ . '/../views/form-library.php';
