<?php
/**
 * Report Page View
 * Archive System - Quezon City Public Library
 */

include __DIR__ . '/layouts/header.php';
?>

<div class="container-fluid report-page">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle mb-0">Track uploaded files by date and generate exports for reporting.</p>
        </div>
        <div class="report-header-actions">
            <div class="current-datetime-display d-flex flex-column text-end pe-4">
                <div id="currentDate" class="fw-bold text-dark mb-0 report-current-date">Monday, 21 October 2024</div>
                <div id="currentTime" class="text-muted report-current-time">14:32:05 PM</div>
            </div>
            <button id="exportPdfBtn" class="btn btn-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500;">
                <i class="bi bi-printer"></i> Export PDF
            </button>
        </div>
    </div>

    <div class="report-tabs-wrap mb-3">
        <div class="report-tabs" role="tablist" aria-label="Report views">
            <button type="button" id="reportTabMostViewed" class="report-tab-btn active" data-report-type="most_viewed" aria-selected="true">
                Most Viewed File
            </button>
            <button type="button" id="reportTabFileSummary" class="report-tab-btn" data-report-type="file_summary" aria-selected="false">
                File Summary
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card mb-4" style="border-radius: 16px;">
        <div class="card-body p-4">
            <div id="reportDateActions" class="report-date-actions d-none">
                <button type="button" id="clearReportDatesBtn" class="btn btn-link report-clear-dates-btn">
                    <i class="bi bi-x-circle"></i>
                    <span>Clear Dates</span>
                </button>
            </div>
            <div class="row gx-3 gy-3 align-items-end">
                <div class="col-md-4 col-sm-12">
                    <label class="form-label text-muted fw-semibold" style="font-size:12px;">SEARCH</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="reportSearch" class="form-control border-start-0 ps-0" placeholder="Search title or type...">
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted fw-semibold" style="font-size:12px;">PERIOD</label>
                    <select id="reportPeriod" class="form-select">
                        <option value="all">All Time</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">This Week</option>
                        <option value="monthly">This Month</option>
                        <option value="yearly">This Year</option>
                    </select>
                </div>

                <div class="col-md-5 col-sm-12" id="customDateRange">
                    <div class="row gx-2">
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold" style="font-size:12px;">DATE RANGE START</label>
                            <input type="date" id="reportStartDate" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold" style="font-size:12px;">DATE RANGE END</label>
                            <input type="date" id="reportEndDate" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table report-table mb-0 w-100">
                <thead id="reportTableHead"></thead>
                <tbody id="reportTableBody">
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="px-4 py-4 d-flex justify-content-between align-items-center border-top bg-white">
            <!-- Limit Selector and Showing Text -->
            <div class="d-flex align-items-center gap-3">
                <span class="text-secondary small fw-medium">Rows per page:</span>
                <select id="reportLimit" class="rows-per-page-select">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span id="reportPaginationInfo" class="text-secondary small ms-2">Showing 0-0 of 0</span>
            </div>
            
            <!-- Circular Pagination Controls -->
            <div class="d-flex align-items-center gap-1" id="reportPaginationControls">
                <!-- Generated by JS -->
            </div>
        </div>
    </div>

</div>

<!-- Pass PHP Variables to JS -->
<script>
    const APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/admin_pages/report.js?v=<?= time() ?>"></script>

<!-- File Preview Modal -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content public-modal-content">
            <div class="modal-body p-0">
                <div class="public-modal">
                    <div class="public-modal-left">
                        <div class="public-modal-img-container">
                            <img id="photoViewerImg" src="" class="public-modal-img" alt="File Preview" style="display: none;">
                            <div id="noPreviewIcon" class="public-modal-no-img" style="display: none;">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>No preview available</span>
                            </div>
                        </div>
                        <div class="public-modal-actions">
                            <a id="readNowBtn" href="#" target="_blank" class="public-read-btn">
                                <i class="bi bi-book-half"></i> Read Full Document
                            </a>
                            <div class="admin-action-buttons">
                                <a href="#" id="editBtn" class="admin-action-btn admin-edit-btn">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" id="deleteBtn" class="admin-action-btn admin-delete-btn">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="public-modal-right">
                        <button type="button" class="public-modal-close" data-bs-dismiss="modal" title="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>

                        <span id="previewCategory" class="public-modal-category-badge">CATEGORY</span>
                        <h2 id="previewTitle" class="public-modal-title">File Title</h2>

                        <div id="metaDescriptionWrap" class="public-modal-description-wrap" style="display: none;">
                            <p id="metaDescription" class="public-modal-description"></p>
                        </div>

                        <p class="public-modal-meta-section-title">Document Details</p>
                        <div id="reportPreviewMetaRows"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-standard">
        <div class="modal-content modal-minimalist">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-icon icon-danger">
                    <i class="bi bi-trash3"></i>
                </div>
                <h5 class="modal-title">Confirm Delete</h5>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to move this item to trash? This action can be undone from the Trash page.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="bi bi-trash3 me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Success Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm-standard">
        <div class="modal-content modal-sm-minimalist">
            <div class="modal-icon icon-success">
                <i class="bi bi-check-lg"></i>
            </div>
            <h5 class="modal-title">Item Deleted</h5>
            <div class="modal-body">
                <p>The item has been moved to trash successfully.</p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Export CSV Confirmation Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 8px 32px rgba(0,0,0,0.12);">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(37,99,235,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-printer text-primary fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" id="exportModalLabel">Export Report</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <p id="exportModalMessage" class="text-secondary mb-0" style="line-height:1.6;"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                <button type="button" id="confirmExportBtn" class="btn btn-primary px-4 d-flex align-items-center gap-2" style="border-radius:8px;">
                    <i class="bi bi-download"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layouts/footer.php'; ?>
