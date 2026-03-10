<?php
/**
 * Form Library View
 * Archive System - Quezon City Public Library
 */

$pageTitle = "Custom Metadata";
include __DIR__ . '/layouts/header.php';
?>

<link rel="stylesheet" href="../assets/css/admin_pages/form-library.css">

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4" style="margin-top: 0 !important; padding-top: 0 !important;">
        <div>
            <h1 class="fw-bold m-0" style="font-size: 32px; color: #000; font-family: 'Poppins', sans-serif;">Custom
                Metadata</h1>
            <div class="text-dark" style="font-size: 14px;">Manage your custom metadata structures for newspaper
                ingestion.</div>
        </div>
        <button class="btn btn-primary" id="createFormBtn">
            <i class="bi bi-plus-circle"></i> Create New Form
        </button>
    </div>

    <!-- Search and Filter Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" id="formSearchInput" placeholder="Search forms by name...">
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs" id="formFilterTabs">
            <button class="filter-tab active" data-filter="all">All Forms</button>
            <button class="filter-tab" data-filter="active">Active</button>
            <button class="filter-tab" data-filter="draft">Drafts</button>
            <button class="filter-tab" data-filter="archived">Archived</button>
        </div>
    </div>

    <!-- Form Templates Table -->
    <div class="table-container">
        <table class="forms-table" id="formTemplatesTable">
            <thead>
                <tr>
                    <th width="5%"></th>
                    <th width="35%">FORM NAME & DESCRIPTION</th>
                    <th width="15%">STATUS</th>
                    <th width="10%">FIELDS</th>
                    <th width="20%">LAST MODIFIED</th>
                    <th width="15%">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($formTemplates)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                            <p class="text-muted mt-3">No forms found. Create your first form template to get started.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($formTemplates as $template): ?>
                        <tr class="form-template-row" data-status="<?= htmlspecialchars($template['status']) ?>"
                            data-name="<?= htmlspecialchars(strtolower($template['name'])) ?>"
                            data-description="<?= htmlspecialchars(strtolower($template['description'] ?? '')) ?>">
                            <td class="text-center">
                                <i class="bi bi-file-earmark-text form-icon"></i>
                            </td>
                            <td>
                                <div class="form-name"><?= htmlspecialchars($template['name']) ?></div>
                                <div class="form-description">
                                    <?= htmlspecialchars($template['description'] ?? 'No description') ?></div>
                            </td>
                            <td>
                                <?php if ($template['is_active']): ?>
                                    <span class="status-badge status-active">ACTIVE</span>
                                <?php elseif ($template['status'] === 'draft'): ?>
                                    <span class="status-badge status-draft">DRAFT</span>
                                <?php else: ?>
                                    <span class="status-badge status-archived">ARCHIVED</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $template['field_count'] ?></td>
                            <td>
                                <div class="last-modified-date"><?= date('M j, Y', strtotime($template['updated_at'])) ?></div>
                                <div class="last-modified-by">by System Admin</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action edit-form" data-form-id="<?= $template['id'] ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-action preview-form" data-form-id="<?= $template['id'] ?>"
                                        title="Preview">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn-action duplicate-form" data-form-id="<?= $template['id'] ?>"
                                        title="Duplicate">
                                        <i class="bi bi-files"></i>
                                    </button>
                                    <div class="dropdown d-inline">
                                        <button class="btn-action" data-bs-toggle="dropdown" title="More">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if (!$template['is_active']): ?>
                                                <li>
                                                    <a class="dropdown-item set-active-form" href="#"
                                                        data-form-id="<?= $template['id'] ?>">
                                                        <i class="bi bi-check-circle"></i> Set as Active
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($template['status'] !== 'archived'): ?>
                                                <li>
                                                    <a class="dropdown-item archive-form" href="#"
                                                        data-form-id="<?= $template['id'] ?>">
                                                        <i class="bi bi-archive"></i> Archive
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger delete-form" href="#"
                                                    data-form-id="<?= $template['id'] ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Empty State (when no forms match filter) -->
        <div class="text-center py-5 d-none" id="noFormsMessage">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
            <p class="text-muted mt-3">No forms found</p>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (!empty($formTemplates)): ?>
        <div class="pagination-container mt-4">
            <div class="pagination-info">
                Showing 1 to <?= min(10, count($formTemplates)) ?> of <?= count($formTemplates) ?> forms
            </div>
            <div class="pagination-controls">
                <button class="page-btn" disabled><i class="bi bi-chevron-left"></i></button>
                <button class="page-btn active">1</button>
                <button class="page-btn">2</button>
                <button class="page-btn">3</button>
                <button class="page-btn"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Form Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewModalBody">
                <!-- Preview content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin_pages/form-library.js"></script>

<?php include __DIR__ . '/layouts/footer.php'; ?>