<?php
/**
 * Form Library Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect(route_url('dashboard', ['error' => 'Access denied']));
}

// Filters & pagination
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page         = max(1, intval($_GET['page'] ?? 1));
$limit        = max(1, intval($_GET['limit'] ?? 10));
$offset       = ($page - 1) * $limit;

$where  = '1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (ft.name LIKE ? OR ft.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $where   .= ' AND ft.status = ?';
    $params[] = $statusFilter;
}

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM form_templates ft WHERE $where");
$countStmt->execute($params);
$totalForms = $countStmt->fetch()['total'];
$totalPages = max(1, ceil($totalForms / $limit));

// Get templates with field counts + modifier username
$stmt = $pdo->prepare("
    SELECT 
        ft.*,
        COUNT(ff.id)  AS field_count,
        u.username    AS modifier_username,
        u.full_name   AS modifier_full_name
    FROM form_templates ft
    LEFT JOIN form_fields ff ON ft.id = ff.form_id
    LEFT JOIN users u ON ft.modified_by = u.id
    WHERE $where
    GROUP BY ft.id
    ORDER BY ft.updated_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$formTemplates = $stmt->fetchAll();

// Load view
include __DIR__ . '/../views/form-library.php';
