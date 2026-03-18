<?php
/**
 * Report Page View
 * Archive System - Quezon City Public Library
 */

include __DIR__ . '/layouts/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Report</h1>
            <p class="page-subtitle mb-0">View analytics and reading statistics for archived files.</p>
        </div>
        <div>
            <button id="exportCsvBtn" class="btn btn-primary d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500;">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card mb-4" style="border-radius: 16px;">
        <div class="card-body p-4">
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
                        <option value="today">Today</option>
                        <option value="weekly">This Week</option>
                        <option value="monthly">This Month</option>
                        <option value="yearly">This Year</option>
                    </select>
                </div>
                
                <div class="col-md-5 col-sm-12" id="customDateRange">
                    <div class="row gx-2">
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold" style="font-size:12px;">START DATE</label>
                            <input type="date" id="reportStartDate" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted fw-semibold" style="font-size:12px;">END DATE</label>
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
                <thead>
                    <tr>
                        <th class="ps-4 py-3 text-uppercase text-secondary" style="width: 80px; text-align: center;">Rank</th>
                        <th class="py-3 text-uppercase text-secondary" style="width: 100px;">Thumbnail</th>
                        <th class="py-3 text-uppercase text-secondary">Title</th>
                        <th class="py-3 text-uppercase text-secondary" style="width: 15%">Publication Type</th>
                        <th class="text-end pe-4 py-3 text-uppercase text-secondary" style="width: 15%">Total Views</th>
                    </tr>
                </thead>
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

<!-- Export CSV Confirmation Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 8px 32px rgba(0,0,0,0.12);">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(25,135,84,0.1);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-file-earmark-spreadsheet text-success fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" id="exportModalLabel">Export to CSV</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <p id="exportModalMessage" class="text-secondary mb-0" style="line-height:1.6;"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                <button type="button" id="confirmExportBtn" class="btn btn-success px-4 d-flex align-items-center gap-2" style="border-radius:8px;">
                    <i class="bi bi-download"></i> Download CSV
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layouts/footer.php'; ?>
