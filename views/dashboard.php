<!-- Top Search Bar & Date/Time Section -->
<div class="dashboard-top-section position-sticky top-0" style="z-index: 1020;">
    <div class="row align-items-center justify-content-between g-3">
        <!-- Search and Filter Form -->
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="m-0" id="searchFilterForm">
                <div class="d-flex align-items-center gap-3">
                    <div class="top-search-pill d-flex align-items-center flex-grow-1 position-relative p-1">
                        <input type="text" class="form-control border-0 bg-transparent shadow-none px-3" name="q"
                            placeholder="Search digital archives..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>

                    <div class="filter-dropdown-container">
                        <select class="form-select border-0 shadow-none px-4 fw-medium text-dark" name="category"
                            style="background-color: #F1F5F9; border-radius: 8px; height: 44px; width: auto; min-width: 140px; font-size: 13px; cursor: pointer;"
                            onchange="document.getElementById('searchFilterForm').submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Current Date and Time -->
        <div class="col-md-5 col-lg-4 text-end d-flex justify-content-end align-items-center gap-3">
            <div class="current-datetime-display d-flex flex-column text-end pe-4"
                style="border-right: 1px solid #E0E0E0;">
                <div id="currentDate" class="fw-bold text-dark mb-0" style="font-size: 12px; letter-spacing: 0.2px;">
                    Monday, 21 October 2024</div>
                <div id="currentTime" class="text-muted" style="font-size: 11px;">14:32:05 PM</div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($searchQuery) && empty($categoryFilter) && empty($languageFilter) && empty($dateFrom) && empty($dateTo)): ?>
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Archives</span>
                    <i class="bi bi-file-earmark-text-fill stat-card-icon" style="color: #4C3939; font-size: 1.2rem;"></i>
                </div>
                <div class="stat-card-value">
                    <?= number_format($totalArchives) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Issues Count</span>
                    <i class="bi bi-files-alt stat-card-icon" style="color: #4C3939; font-size: 1.2rem;"></i>
                </div>
                <div class="stat-card-value">
                    <?= number_format($totalIssues) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Years Covered</span>
                    <i class="bi bi-calendar-range-fill stat-card-icon" style="color: #4C3939; font-size: 1.2rem;"></i>
                </div>
                <div class="stat-card-value">
                    <?= $yearsCovered ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Categories</span>
                    <i class="bi bi-grid-3x3-gap-fill stat-card-icon" style="color: #4C3939; font-size: 1.2rem;"></i>
                </div>
                <div class="stat-card-value">
                    <?= $totalCategories ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Search Results (if any) -->
<?php if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo): ?>
    <div class="mb-4">
        <!-- New Mockup Header for Search Results -->
        <div class="search-results-banner p-3 rounded-3 mb-4 d-flex justify-content-between align-items-center"
            style="background-color: #F5F6F8; border: 1px solid #EBEBEB;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle-fill" style="color: #4C3939;"></i>
                <span class="text-secondary fw-medium" style="font-size: 13px;">Showing results for
                    <strong>"<?= htmlspecialchars($searchQuery ?: 'Selected Filters') ?>"</strong></span>
            </div>
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-link p-0 text-uppercase fw-bold text-decoration-none"
                style="color: #4C3939; font-size: 11px; letter-spacing: 1px;">
                Clear Search
            </a>
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <h4 class="fw-bold mb-0 text-dark">Search Results</h4>
            <span class="text-uppercase fw-bold text-muted" style="font-size: 11px; letter-spacing: 1px;">
                <?= count($searchResults) ?> Documents Found
            </span>
        </div>
        <?php if (empty($searchResults)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-search" style="font-size: 40px; color: #ddd;"></i>
                </div>
                <h5 class="fw-bold text-secondary">No Results Found</h5>
                <p class="text-muted small">We couldn't find any documents matching your criteria.</p>
                <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm mt-2 rounded-pill px-4">
                    Clear Filters
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($searchResults as $paper): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="newspaper-card" style="cursor: pointer;" data-bs-toggle="modal"
                            data-bs-target="#filePreviewModal" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars($paper['title']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= $paper['publication_date'] ? date('M Y', strtotime($paper['publication_date'])) : 'N/A' ?>"
                            data-edition="<?= htmlspecialchars($paper['edition'] ?? 'Standard') ?>"
                            data-pages="<?= $paper['page_count'] ?? 'N/A' ?>"
                            data-format="<?= strtoupper($paper['file_type'] ?? 'PDF') ?>"
                            data-uploader="<?= htmlspecialchars($paper['uploader_name'] ?? 'Admin') ?>"
                            data-tags="<?= htmlspecialchars($paper['keywords'] ?? '') ?>"
                            data-file="<?= APP_URL . '/' . $paper['file_path'] ?>"
                            data-category="<?= htmlspecialchars($paper['category_name'] ?? 'Uncategorized') ?>"
                            data-publisher="<?= htmlspecialchars($paper['publisher'] ?? 'N/A') ?>"
                            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                            data-image-paths="<?= htmlspecialchars($paper['image_paths'] ?? '[]') ?>">
                            <?php if ($paper['thumbnail_path']): ?>
                                <div class="position-relative">
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
                                    <?php if (!empty($paper['is_bulk_image'])): ?>
                                        <div class="position-absolute top-0 end-0 m-2 badge shadow-sm"
                                            style="font-size: 10px; background-color: #4C3939; color: white;">
                                            <i class="bi bi-images"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="newspaper-thumbnail bg-secondary d-flex align-items-center justify-content-center">
                                    <i class="bi bi-newspaper text-white" style="font-size: 48px;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="newspaper-info">
                                <div class="newspaper-category <?= strtolower($paper['category_name'] ?? '') ?>">
                                    <?= strtoupper($paper['category_name'] ?? 'UNCATEGORIZED') ?>
                                </div>
                                <h6 class="newspaper-title">
                                    <?= htmlspecialchars($paper['title']) ?>
                                </h6>
                                <div class="newspaper-date mb-2">
                                    <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : 'N/A' ?>
                                </div>
                                <?php if (!empty($paper['keywords'])): ?>
                                    <div class="mt-auto pt-2 d-flex gap-1 flex-wrap">
                                        <?php
                                        $tags = array_filter(array_map('trim', explode(',', $paper['keywords'])));
                                        foreach (array_slice($tags, 0, 3) as $tag):
                                            ?>
                                            <span class="badge rounded-pill bg-light text-secondary border px-2 py-1"
                                                style="font-size: 10px;">
                                                <?= htmlspecialchars($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($tags) > 3): ?>
                                            <span class="badge rounded-pill bg-light text-secondary border px-2 py-1"
                                                style="font-size: 10px;">
                                                +<?= count($tags) - 3 ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($searchQuery) && empty($categoryFilter) && empty($languageFilter) && empty($dateFrom) && empty($dateTo)): ?>
    <!-- Recent Activities -->
    <div class="recent-activities">
        <div class="recent-activities-header">
            <h2 class="recent-activities-title">Recent Activities</h2>
            <a href="<?= APP_URL ?>/pages/collections.php" class="view-all-link">View all</a>
        </div>

        <?php if (empty($recentNewspapers)): ?>
            <div class="text-center py-5 bg-light rounded-4 border border-dashed">
                <div class="mb-3">
                    <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center shadow-sm"
                        style="width: 60px; height: 60px;">
                        <i class="bi bi-cloud-upload text-secondary" style="font-size: 24px;"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-secondary">No Archives Yet</h5>
                <p class="text-muted small mb-3">Start building your repository by uploading documents.</p>
                <a href="<?= APP_URL ?>/pages/upload.php" class="btn btn-primary rounded-pill px-4"
                    style="background: #4C3939; border: none;">
                    <i class="bi bi-plus-lg me-2"></i>Upload Now
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($recentNewspapers as $paper): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="newspaper-card" style="cursor: pointer;" data-bs-toggle="modal"
                            data-bs-target="#filePreviewModal" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars($paper['title']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= $paper['publication_date'] ? date('M Y', strtotime($paper['publication_date'])) : 'N/A' ?>"
                            data-edition="<?= htmlspecialchars($paper['edition'] ?? 'Standard') ?>"
                            data-pages="<?= $paper['page_count'] ?? 'N/A' ?>"
                            data-format="<?= strtoupper($paper['file_type'] ?? 'PDF') ?>"
                            data-uploader="<?= htmlspecialchars($paper['uploader_name'] ?? 'Admin') ?>"
                            data-tags="<?= htmlspecialchars($paper['keywords'] ?? '') ?>"
                            data-file="<?= APP_URL . '/' . $paper['file_path'] ?>"
                            data-category="<?= htmlspecialchars($paper['category_name'] ?? 'Uncategorized') ?>"
                            data-publisher="<?= htmlspecialchars($paper['publisher'] ?? 'N/A') ?>"
                            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                            data-image-paths="<?= htmlspecialchars($paper['image_paths'] ?? '[]') ?>">
                            <?php if ($paper['thumbnail_path']): ?>
                                <div class="position-relative">
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
                                    <?php if (!empty($paper['is_bulk_image'])): ?>
                                        <div class="position-absolute top-0 end-0 m-2 badge shadow-sm"
                                            style="font-size: 10px; background-color: #4C3939; color: white;">
                                            <i class="bi bi-images"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="newspaper-thumbnail bg-secondary d-flex align-items-center justify-content-center">
                                    <i class="bi bi-newspaper text-white" style="font-size: 48px;"></i>
                                </div>
                            <?php endif; ?>

                            <?php if (strtotime($paper['created_at']) > strtotime('-24 hours')): ?>
                                <div class="position-absolute top-0 start-0 m-2 badge bg-success shadow-sm"
                                    style="font-size: 10px; z-index: 2;">
                                    NEW
                                </div>
                            <?php endif; ?>

                            <div class="newspaper-info">
                                <div class="newspaper-category <?= strtolower($paper['category_name'] ?? '') ?>">
                                    <?= strtoupper($paper['category_name'] ?? 'UNCATEGORIZED') ?>
                                </div>
                                <h6 class="newspaper-title">
                                    <?= htmlspecialchars($paper['title']) ?>
                                </h6>
                                <div class="newspaper-date mb-2">
                                    <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : date('d F Y', strtotime($paper['created_at'])) ?>
                                </div>
                                <?php if (!empty($paper['keywords'])): ?>
                                    <div class="mt-auto pt-2 d-flex gap-1 flex-wrap">
                                        <?php
                                        $tags = array_filter(array_map('trim', explode(',', $paper['keywords'])));
                                        foreach (array_slice($tags, 0, 3) as $tag):
                                            ?>
                                            <span class="badge rounded-pill bg-light text-secondary border px-2 py-1"
                                                style="font-size: 10px;">
                                                <?= htmlspecialchars($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($tags) > 3): ?>
                                            <span class="badge rounded-pill bg-light text-secondary border px-2 py-1"
                                                style="font-size: 10px;">
                                                +<?= count($tags) - 3 ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- File Preview Modal -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content"
            style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 25px 50px rgba(0,0,0,0.15);">
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left: Preview Image & Actions -->
                    <div class="col-md-6 d-flex flex-column" style="background: #2C2C2C;">
                        <!-- Image Container -->
                        <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 position-relative"
                            style="min-height: 300px;">
                            <div class="photo-viewer-container position-relative">
                                <img id="photoViewerImg" src="" class="w-100 rounded"
                                    style="max-height:480px; object-fit:contain; display: block;">
                                <div id="noPreviewIcon" style="display: none; padding: 60px; text-align: center;">
                                    <i class="bi bi-file-earmark-text" style="font-size: 60px; color: #666;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="p-3 d-flex gap-2" style="background: #2C2C2C;">
                            <a href="#" id="readNowBtn"
                                class="btn flex-grow-1 d-flex align-items-center justify-content-center gap-2"
                                style="background: #4A3B32; color: white; border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 500;">
                                <i class="bi bi-book-half"></i> Read Now
                            </a>
                            <a href="#" id="editBtn"
                                class="btn flex-grow-1 d-flex align-items-center justify-content-center gap-2"
                                style="background: #fff; color: #333; border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 500;">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <button type="button" id="deleteBtn"
                                class="btn flex-grow-1 d-flex align-items-center justify-content-center gap-2"
                                style="background: #FFEBEE; color: #C62828; border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 500; border: none;">
                                <i class="bi bi-trash3"></i> Delete
                            </button>
                        </div>
                    </div>

                    <!-- Right: Details -->
                    <div class="col-md-6 bg-white p-4 position-relative">
                        <button type="button" class="btn-close position-absolute" data-bs-dismiss="modal"
                            style="right: 15px; top: 15px; opacity: 0.5;"></button>

                        <div class="d-flex flex-column h-100">
                            <!-- Header -->
                            <div class="mb-4">
                                <h5 id="previewTitle" class="fw-bold mb-1" style="color: #1a1a1a; font-size: 18px;">
                                    File Preview</h5>
                                <div id="previewCategory" class="newspaper-category mb-0" style="font-size: 12px;">
                                    ARCHIVE MANAGEMENT SYSTEM
                                </div>
                            </div>

                            <!-- Metadata -->
                            <div class="flex-grow-1">
                                <p class="text-uppercase text-muted fw-bold mb-3"
                                    style="font-size: 10px; letter-spacing: 1.5px;">Metadata Details</p>

                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-calendar3 me-2"></i>Publication Date
                                        </span>
                                        <span id="metaDate" class="fw-bold"
                                            style="color: #333; font-size: 13px;">-</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-globe me-2"></i>Edition
                                        </span>
                                        <span id="metaEdition" class="fw-bold"
                                            style="color: #333; font-size: 13px;">-</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-building me-2"></i>Publisher
                                        </span>
                                        <span id="metaPublisher" class="fw-bold"
                                            style="color: #333; font-size: 13px;">-</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-file-text me-2"></i>Page Count
                                        </span>
                                        <span id="metaPages" class="fw-bold"
                                            style="color: #333; font-size: 13px;">-</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-file-earmark me-2"></i>Format
                                        </span>
                                        <span id="metaFormat" class="badge"
                                            style="background: #FFEBEE; color: #D32F2F; font-size: 11px; padding: 4px 10px; font-weight: 600;">PDF</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-person me-2"></i>Uploaded by
                                        </span>
                                        <div class="d-flex align-items-center bg-light rounded-pill px-2 py-1">
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-1"
                                                style="width: 18px; height: 18px; font-size: 9px;">A</div>
                                            <span id="metaUploader" class="fw-medium"
                                                style="color: #333; font-size: 12px;">Admin</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div class="mt-4">
                                    <p class="text-uppercase text-muted fw-bold mb-2"
                                        style="font-size: 10px; letter-spacing: 1.5px;">Tags</p>
                                    <div id="metaTags" class="d-flex gap-2 flex-wrap">
                                        <!-- Tags populated via JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0 text-muted">Are you sure you want to move this item to trash? This action can be
                    undone from the Trash page.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"
                    style="border-radius: 8px; font-weight: 500;">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"
                    style="border-radius: 8px; font-weight: 500; background: #D32F2F; border: none;">
                    <i class="bi bi-trash3 me-2"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Success Modal -->
<div class="modal fade" id="uploadSuccessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                        style="width: 64px; height: 64px;">
                        <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-2">Upload Complete!</h5>
                <p class="text-muted small mb-4">Your document has been successfully added to the archive.</p>
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Success Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                        style="width: 64px; height: 64px;">
                        <i class="bi bi-trash3 text-danger" style="font-size: 32px;"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-2">Item Deleted</h5>
                <p class="text-muted small mb-4">The item has been moved to trash successfully.</p>
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>