<?php
/**
 * Form Library View
 * Archive System - Quezon City Public Library
 */

$pageTitle = "Custom Metadata";
include __DIR__ . '/layouts/header.php';
?>

<style>
    /* ── Search bar ── (mirrors history.php) */
    .search-bar-custom {
        background: #fff;
        border-radius: 50px;
        padding: 4px 4px 4px 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,.03);
        display: flex;
        align-items: center;
        width: 100%;
    }
    .search-input-custom {
        border: none;
        background: transparent;
        font-size: 14px;
        color: #666;
        width: 100%;
        padding: 8px;
    }
    .search-input-custom:focus { outline: none; }
    .search-btn-custom {
        background: #3A9AFF;
        color: #fff;
        border: none;
        width: 40px; height: 40px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s;
        flex-shrink: 0;
    }
    .search-btn-custom:hover { background: #2d87ef; }

    /* ── Filter pills ── */
    .filter-pill {
        background: #fff;
        border: none;
        border-radius: 50px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #4B5563;
        display: flex; align-items: center; gap: 8px;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,.02);
        transition: all .2s;
        white-space: nowrap;
        text-decoration: none;
    }
    .filter-pill:hover { background: #F9FAFB; transform: translateY(-1px); color: #4B5563; }

    /* ── Table ── */
    .metadata-table th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #9CA3AF;
        border-bottom: none;
        padding: 20px 24px;
        letter-spacing: .5px;
    }
    .metadata-table td {
        vertical-align: middle;
        padding: 20px 24px;
        border-bottom: 1px solid #F3F4F6;
        color: #374151;
        font-size: 14px;
        background: #fff;
    }
    .metadata-table tr:first-child td {
        border-top: 1px solid #F3F4F6;
    }
    .metadata-table tr:last-child td { border-bottom: none; }

    /* ── Status badges ── */
    .action-badge {
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .5px;
        display: inline-block;
    }
    .badge-active   { background: #DCFCE7; color: #16A34A; }
    .badge-draft    { background: #FEF3C7; color: #D97706; }
    .badge-archived { background: #F3F4F6; color: #4B5563; }

    /* ── Pagination ── */
    .pagination-circle {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 500;
        color: #6B7280;
        text-decoration: none;
        transition: all .2s;
        border: none;
        background: transparent;
        cursor: pointer;
    }
    .pagination-circle:hover { background: #F3F4F6; color: #374151; }
    .pagination-circle.active { background: #3A9AFF; color: #fff; }
    .pagination-circle.disabled { opacity: .5; pointer-events: none; }

    .rows-per-page-select {
        background-color: #F3F4F6;
        border: none;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
    }

    /* ── Misc ── */
    .form-name-cell { font-weight: 600; color: #111827; font-size: 14px; }
    .form-desc-cell { font-size: 12px; color: #6B7280; margin-top: 2px; }
    .field-count-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px;
        border-radius: 50%;
        background: #EFF6FF;
        color: #2563EB;
        font-size: 12px; font-weight: 700;
    }
    .action-icon-btn {
        background: none; border: none;
        color: #6B7280; font-size: 16px;
        padding: 4px 6px; border-radius: 6px;
        transition: background .15s, color .15s;
        cursor: pointer;
    }
    .action-icon-btn:hover { background: #F3F4F6; color: #374151; }
    .action-icon-btn.text-danger:hover { background: #FEE2E2; color: #DC2626; }

    mark.pub-hl {
        background: #FEF08A; color: inherit;
        padding: 0 2px; border-radius: 2px;
    }

    /* Action Buttons (Icon only) */
    .action-icon-btn {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s;
        border: none;
        background: transparent;
        cursor: pointer;
        padding: 0;
    }
    .btn-edit-custom {
        background: #EEF2FF;
        color: #3A9AFF;
    }
    .btn-edit-custom:hover {
        background: #3A9AFF;
        color: #fff;
    }
    .btn-delete-custom {
        background: #FEE2E2;
        color: #DC2626;
    }
    /* Toggle Switch Custom (Enlarged) */
    .form-switch .form-check-input {
        width: 3.2em !important;
        height: 1.6em !important;
        cursor: pointer;
        margin-top: 0.25em;
        background-color: #e5e7eb;
        border-color: #d1d5db;
        transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s;
    }
    .form-switch .form-check-input:checked {
        background-color: #3A9AFF !important;
        border-color: #3A9AFF !important;
    }
    .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(58, 154, 255, 0.25);
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Custom Metadata</h1>
            <p class="page-subtitle mb-0">Manage metadata form templates for newspaper archiving.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center gap-2" id="createFormBtn"
                style="border-radius:8px; font-weight:500;">
            <i class="bi bi-plus-circle"></i> Create New Form
        </button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mb-4">
        <!-- Search -->
        <div class="flex-grow-1">
            <form method="GET" class="search-bar-custom" id="searchForm">
                <i class="bi bi-search text-muted fs-5 ms-1"></i>
                <input type="text" class="search-input-custom" name="search" id="searchInput"
                       placeholder="Search forms by name or description..."
                       value="<?= htmlspecialchars($search) ?>">
                <?php if ($statusFilter): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <?php endif; ?>
                <?php if ($limit != 10): ?>
                    <input type="hidden" name="limit" value="<?= $limit ?>">
                <?php endif; ?>
                <button class="search-btn-custom" type="submit">
                    <i class="bi bi-search" style="font-size:14px;"></i>
                </button>
            </form>
        </div>

        <!-- Status filter dropdown -->
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <?php
                    $statusLabels = [''=>'All Statuses','active'=>'Active','draft'=>'Draft','archived'=>'Archived'];
                    echo $statusLabels[$statusFilter] ?? 'All Statuses';
                    ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm" style="font-size:13px;">
                    <?php foreach ($statusLabels as $val => $label): ?>
                        <li>
                            <a class="dropdown-item <?= $statusFilter === $val ? 'active' : '' ?>"
                               href="?status=<?= urlencode($val) ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">
                                <?= $label ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table metadata-table mb-0 w-100" id="formTemplatesTable">
                <thead>
                    <tr>
                        <th class="ps-4" style="min-width:280px;">Form Name &amp; Description</th>
                        <th class="text-center" style="width:100px;">Active</th>
                        <th class="text-center" style="width:100px;">Fields</th>
                        <th style="width:210px;">Last Modified</th>
                        <th class="text-end pe-4" style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($formTemplates)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                <span class="text-muted">
                                    <?= $search || $statusFilter ? 'No forms match your filters.' : 'No form templates yet. Create your first one!' ?>
                                </span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($formTemplates as $template): ?>
                            <?php
                                // Status badge
                                if ($template['is_active']) {
                                    $badgeCls = 'badge-active';
                                    $badgeLbl = 'ACTIVE';
                                } elseif ($template['status'] === 'draft') {
                                    $badgeCls = 'badge-draft';
                                    $badgeLbl = 'DRAFT';
                                } else {
                                    $badgeCls = 'badge-archived';
                                    $badgeLbl = 'ARCHIVED';
                                }

                                // Modifier display
                                $modifierDisplay = $template['modifier_username']
                                    ? htmlspecialchars($template['modifier_username'])
                                    : 'System';

                                // Highlight search term
                                $hn = htmlspecialchars($template['name']);
                                $hd = htmlspecialchars($template['description'] ?? '');
                                if ($search) {
                                    $sq = preg_quote(htmlspecialchars($search), '/');
                                    $hn = preg_replace("/($sq)/i", '<mark class="pub-hl">$1</mark>', $hn);
                                    $hd = preg_replace("/($sq)/i", '<mark class="pub-hl">$1</mark>', $hd);
                                }
                            ?>
                            <tr class="form-template-row"
                                data-status="<?= htmlspecialchars($template['status']) ?>"
                                data-name="<?= htmlspecialchars(strtolower($template['name'])) ?>">

                                <!-- Name & description -->
                                <td class="ps-4">
                                    <div class="form-name-cell"><?= $hn ?></div>
                                    <div class="form-desc-cell"><?= $hd ?: '<em>No description</em>' ?></div>
                                </td>

                                <!-- Active toggle -->
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input set-active-toggle" type="checkbox" 
                                               role="switch" data-form-id="<?= $template['id'] ?>"
                                               <?= $template['is_active'] ? 'checked disabled' : '' ?>>
                                    </div>
                                </td>

                                <!-- Field count -->
                                <td class="text-center">
                                    <span class="field-count-badge"><?= intval($template['field_count']) ?></span>
                                </td>

                                <!-- Last modified -->
                                <td>
                                    <div style="font-size:13px; color:#374151; font-weight:500;">
                                        <?= date('d M Y, H:i', strtotime($template['updated_at'])) ?>
                                    </div>
                                    <div style="font-size:12px; color:#6B7280; margin-top:2px;">
                                        by <strong><?= $modifierDisplay ?></strong>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button class="action-icon-btn btn-edit-custom edit-form"
                                                data-form-id="<?= $template['id'] ?>" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="action-icon-btn btn-delete-custom delete-form"
                                                data-form-id="<?= $template['id'] ?>" title="Delete">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="px-4 py-4 d-flex justify-content-between align-items-center border-top bg-white">
            <!-- Rows per page + info -->
            <div class="d-flex align-items-center gap-3">
                <span class="text-secondary small fw-medium">Rows per page:</span>
                <select class="rows-per-page-select" id="rowsPerPage">
                    <?php foreach ([5, 10, 25] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-secondary small ms-2">
                    Showing
                    <?= $totalForms > 0 ? ($offset + 1) : 0 ?>–<?= min($offset + $limit, $totalForms) ?>
                    of <?= $totalForms ?> forms
                </span>
            </div>

            <!-- Page buttons -->
            <div class="d-flex align-items-center gap-1">
                <?php
                    $buildUrl = function($p) use ($search, $statusFilter, $limit) {
                        return '?' . http_build_query(array_filter([
                            'page'   => $p,
                            'limit'  => $limit,
                            'search' => $search,
                            'status' => $statusFilter,
                        ], fn($v) => $v !== '' && $v !== null));
                    };
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);
                ?>
                <a href="<?= $buildUrl(max(1, $page - 1)) ?>"
                   class="pagination-circle <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?= $buildUrl($i) ?>"
                       class="pagination-circle <?= $page == $i ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($totalPages > $endPage): ?>
                    <span class="text-muted small px-1">…</span>
                    <a href="<?= $buildUrl($totalPages) ?>" class="pagination-circle"><?= $totalPages ?></a>
                <?php endif; ?>

                <a href="<?= $buildUrl(min($totalPages, $page + 1)) ?>"
                   class="pagination-circle <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px; border:none;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Form Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewModalBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal"
                        style="border-radius:8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Active Modal -->
<div class="modal fade" id="confirmActiveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="border-radius: 32px; border: none; padding: 24px;">
            <div class="modal-body text-center p-0">
                <div class="mb-4 d-flex justify-content-center">
                    <div style="width: 80px; height: 80px; background: #EEF2FF; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle-fill" style="font-size: 40px; color: #3A9AFF;"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2">Set as Active?</h4>
                <p class="text-secondary mb-4">This will deactivate the current active form template. Are you sure you want to proceed?</p>
                <div class="d-flex gap-3">
                    <button type="button" class="btn btn-light flex-grow-1 py-2 fw-semibold" 
                            data-bs-dismiss="modal" style="border-radius: 12px; background: #F3F4F6; border: none;">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary flex-grow-1 py-2 fw-semibold" 
                            id="confirmActiveBtn" style="border-radius: 12px; background: #3A9AFF; border: none;">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="border-radius: 32px; border: none; padding: 24px;">
            <div class="modal-body text-center p-0">
                <div class="mb-4 d-flex justify-content-center">
                    <div style="width: 80px; height: 80px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 40px; color: #DC2626;"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2">Delete Form?</h4>
                <p class="text-secondary mb-4">This action cannot be undone. All associated metadata values will also be affected. Are you sure?</p>
                <div class="d-flex gap-3">
                    <button type="button" class="btn btn-light flex-grow-1 py-2 fw-semibold" 
                            data-bs-dismiss="modal" style="border-radius: 12px; background: #F3F4F6; border: none;">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger flex-grow-1 py-2 fw-semibold" 
                            id="confirmDeleteBtn" style="border-radius: 12px; background: #DC2626; border: none;">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Rows-per-page change
    document.getElementById('rowsPerPage').addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('limit', this.value);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    });

    // Live search debounce
    let debounceTimer;
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => document.getElementById('searchForm').submit(), 500);
    });
    if (searchInput.value) {
        searchInput.focus();
        const len = searchInput.value.length;
        searchInput.setSelectionRange(len, len);
    }
</script>

<script src="<?= APP_URL ?>/assets/js/admin_pages/form-library.js"></script>

<?php include __DIR__ . '/layouts/footer.php'; ?>