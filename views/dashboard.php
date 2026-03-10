<!-- Top Search Bar & Date/Time Section -->
<div class="dashboard-top-section position-sticky top-0" style="z-index: 1020;">
    <div class="row align-items-center justify-content-between g-3">
        <!-- Search and Filter Form -->
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="m-0" id="searchFilterForm">
                <div class="d-flex align-items-center gap-3">
                    <div class="top-search-pill d-flex align-items-center flex-grow-1 position-relative p-1">
                        <input type="text" class="form-control border-0 bg-transparent shadow-none px-3" name="q"
                            id="searchInput" placeholder="Search digital archives..."
                            value="<?= htmlspecialchars($searchQuery) ?>">
                        <?php if ($searchQuery): ?>
                            <button type="button" class="btn-clear-search" onclick="clearSearch()"
                                style="position: absolute; right: 15px; background: none; border: none; color: #9CA3AF; padding: 4px 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.2s;">
                                <i class="bi bi-x-lg" style="font-size: 14px;"></i>
                            </button>
                        <?php endif; ?>
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
                <div id="currentDate" class="fw-bold text-dark mb-0" style="font-size: 15px; letter-spacing: 0.2px;">
                    Monday, 21 October 2024</div>
                <div id="currentTime" class="text-muted" style="font-size: 14px;">14:32:05 PM</div>
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
                    <i class="bi bi-file-earmark-text-fill stat-card-icon" style="color: #3A9AFF; font-size: 1.2rem;"></i>
                </div>
                <div class="stat-card-value">
                    <?= number_format($totalArchives) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Views</span>
                    <i class="bi bi-eye stat-card-icon" style="color: #3A9AFF; font-size: 1.2rem;"></i>
                </div>
                <div class="stat-card-value">
                    <?= number_format($totalViews) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Years Covered</span>
                    <i class="bi bi-calendar-range-fill stat-card-icon" style="color: #3A9AFF; font-size: 1.2rem;"></i>
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
                    <i class="bi bi-grid-3x3-gap-fill stat-card-icon" style="color: #3A9AFF; font-size: 1.2rem;"></i>
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
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark" style="font-weight: 700 !important;">Search Results</h4>
                <?php if ($searchQuery): ?>
                    <p class="text-muted mb-0" style="font-size: 14px;">
                        Showing results for "<span class="fw-semibold text-dark"><?= htmlspecialchars($searchQuery) ?></span>"
                    </p>
                <?php elseif ($categoryFilter): ?>
                    <p class="text-muted mb-0" style="font-size: 14px;">
                        Filtered by category: <span class="fw-semibold text-dark"><?php
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $categoryFilter) {
                                echo htmlspecialchars($cat['name']);
                                break;
                            }
                        }
                        ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-uppercase fw-bold text-muted" style="font-size: 11px; letter-spacing: 1px;">
                    <?= count($searchResults) ?> Documents Found
                </span>
                <a href="<?= APP_URL ?>/admin_pages/dashboard.php" class="text-uppercase fw-bold text-decoration-none"
                    style="color: #3A9AFF; font-size: 11px; letter-spacing: 1px;">
                    Clear Search
                </a>
            </div>
        </div>
        <?php if (empty($searchResults)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-search" style="font-size: 40px; color: #ddd;"></i>
                </div>
                <h5 class="fw-bold text-secondary">No Results Found</h5>
                <p class="text-muted small">We couldn't find any documents matching your criteria.</p>
                <a href="<?= APP_URL ?>/admin_pages/dashboard.php" class="btn btn-outline-secondary btn-sm mt-2 rounded-pill px-4">
                    Clear Filters
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($searchResults as $paper): ?>
                    <?php
                        $meta = $paper['custom_metadata'] ?? [];
                        $pubDate = getMetadataValueByLabel($meta, ['publication date', 'date published', 'date issued']);
                        $publicationShort = $pubDate ? formatPublicationDate($pubDate, false) : 'N/A';
                        $publicationLong = $pubDate ? strtoupper(formatPublicationDate($pubDate, true)) : strtoupper(date('F j, Y', strtotime($paper['created_at'])));
                    ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-file-card" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= htmlspecialchars($publicationShort) ?>"
                            data-edition="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'edition', 'Standard')) ?>"
                            data-pages="<?= htmlspecialchars(getMetadataValueByLabel($meta, ['pages', 'page count'], 'N/A')) ?>"
                            data-format="<?= strtoupper($paper['file_type'] ?? 'PDF') ?>"
                            data-uploader="<?= htmlspecialchars($paper['uploader_name'] ?? 'Admin') ?>"
                            data-tags="<?= htmlspecialchars(getMetadataValueByLabel($meta, ['keywords', 'tags'])) ?>"
                            data-file="<?= APP_URL . '/' . $paper['file_path'] ?>"
                            data-category="<?= htmlspecialchars(getCategoryFromMetadata($meta)) ?>"
                            data-publisher="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'publisher', 'N/A')) ?>"
                            data-description="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'description')) ?>"
                            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                            data-image-paths="<?= htmlspecialchars($paper['image_paths'] ?? '[]') ?>"
                            data-volume="<?= htmlspecialchars(getMetadataValueByLabel($meta, ['volume', 'issue', 'volume/issue'])) ?>"
                            data-language="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'language')) ?>">

                            <!-- Thumbnail with category badge -->
                            <div class="dashboard-thumb-wrap">
                                <?php if ($paper['thumbnail_path']): ?>
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="dashboard-file-thumbnail"
                                        alt="<?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>">
                                <?php else: ?>
                                    <div class="dashboard-file-thumbnail-placeholder">
                                        <i class="bi bi-newspaper"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Category badge on thumbnail -->
                                <?php 
                                    $searchCatVal = getCategoryFromMetadata($paper['custom_metadata'] ?? []);
                                    $searchCatClass = 'dashboard-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $searchCatVal));
                                    if ($searchCatVal && strtolower($searchCatVal) !== 'uncategorized'): 
                                ?>
                                <span class="dashboard-thumb-badge <?= htmlspecialchars($searchCatClass) ?>">
                                    <?= htmlspecialchars($searchCatVal) ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Card info -->
                            <div class="dashboard-card-info">
                                <div class="dashboard-card-type-badge">
                                    <?= strtoupper($paper['file_type'] ?? 'DOCUMENT') ?>
                                </div>
                                <div class="dashboard-card-title">
                                    <?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>
                                </div>

                                <!-- INTEGRATION: Display custom metadata using display configuration -->
                                <?= renderCardMetadata($paper['custom_metadata'] ?? []) ?>
                            </div>

                            <!-- Admin action buttons (shown on hover) -->
                            <div class="dashboard-card-actions">
                                <button class="btn btn-edit"
                                    onclick="event.stopPropagation(); window.location.href='<?= APP_URL ?>/admin_pages/upload.php?edit=<?= $paper['id'] ?>'">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-delete" onclick="event.stopPropagation(); deleteFile(<?= $paper['id'] ?>)">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
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
        <div class="recent-activities-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <h2 class="recent-activities-title mb-0">Recent Activities</h2>
                <span id="selectedFilesCount" class="badge bg-primary"
                    style="display: none; font-size: 12px; padding: 6px 12px; border-radius: 20px;">
                    <i class="bi bi-check-circle"></i>
                    <span id="selectedCount">0</span> selected
                </span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if (!empty($recentNewspapers)): ?>
                    <div class="form-check m-0">
                        <input class="form-check-input shadow-none" type="checkbox" id="dashboardSelectAll"
                            style="cursor: pointer; width: 16px; height: 16px; margin-top: 2px;">
                        <label class="form-check-label text-muted fw-semibold" for="dashboardSelectAll"
                            style="font-size: 13px; cursor: pointer; user-select: none;">Select All</label>
                    </div>
                    <button type="button" id="dashboardBulkDeleteBtn"
                        class="btn btn-sm btn-danger d-none d-flex align-items-center gap-1"
                        style="border-radius: 6px; padding: 4px 10px;">
                        <i class="bi bi-trash3"></i> Delete
                    </button>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/user_pages/collections.php" class="view-all-link m-0">View all</a>
            </div>
        </div>

        <?php if (empty($recentNewspapers)): ?>
            <div class="empty-state-container">
                <div class="empty-state-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <h5 class="empty-state-title">No Archives Yet</h5>
                <p class="empty-state-text">Start building your repository by uploading documents.</p>
                <a href="<?= APP_URL ?>/admin_pages/upload.php" class="btn btn-primary empty-state-btn">
                    <i class="bi bi-plus-lg me-2"></i>Upload Now
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($recentNewspapers as $paper): ?>
                    <?php
                        $meta = $paper['custom_metadata'] ?? [];
                        $pubDate = getMetadataValueByLabel($meta, ['publication date', 'date published', 'date issued']);
                        $publicationShort = $pubDate ? formatPublicationDate($pubDate, false) : 'N/A';
                        $publicationLong = $pubDate ? strtoupper(formatPublicationDate($pubDate, true)) : strtoupper(date('F j, Y', strtotime($paper['created_at'])));
                    ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-file-card" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= htmlspecialchars($publicationShort) ?>"
                            data-edition="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'edition', 'Standard')) ?>"
                            data-pages="<?= htmlspecialchars(getMetadataValueByLabel($meta, ['pages', 'page count'], 'N/A')) ?>"
                            data-format="<?= strtoupper($paper['file_type'] ?? 'PDF') ?>"
                            data-uploader="<?= htmlspecialchars($paper['uploader_name'] ?? 'Admin') ?>"
                            data-tags="<?= htmlspecialchars(getMetadataValueByLabel($meta, ['keywords', 'tags'])) ?>"
                            data-file="<?= APP_URL . '/' . $paper['file_path'] ?>"
                            data-category="<?= htmlspecialchars(getCategoryFromMetadata($meta)) ?>"
                            data-publisher="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'publisher', 'N/A')) ?>"
                            data-description="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'description')) ?>"
                            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                            data-image-paths="<?= htmlspecialchars($paper['image_paths'] ?? '[]') ?>"
                            data-volume="<?= htmlspecialchars(getMetadataValueByLabel($meta, ['volume', 'issue', 'volume/issue'])) ?>"
                            data-language="<?= htmlspecialchars(getMetadataValueByLabel($meta, 'language')) ?>">

                            <!-- Thumbnail with category badge -->
                            <div class="dashboard-thumb-wrap">
                                <!-- Checkbox for multi-select -->
                                <input class="form-check-input dashboard-item-checkbox shadow-none bg-white" type="checkbox"
                                    value="<?= $paper['id'] ?>" onclick="event.stopPropagation();">

                                <?php if ($paper['thumbnail_path']): ?>
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="dashboard-file-thumbnail"
                                        alt="<?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>">
                                <?php else: ?>
                                    <div class="dashboard-file-thumbnail-placeholder">
                                        <i class="bi bi-newspaper"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Category badge on thumbnail -->
                                <?php 
                                    $categoryVal = getCategoryFromMetadata($paper['custom_metadata'] ?? []);
                                    $catClass = 'dashboard-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $categoryVal));
                                    if ($categoryVal && strtolower($categoryVal) !== 'uncategorized'): 
                                ?>
                                <span class="dashboard-thumb-badge <?= htmlspecialchars($catClass) ?>">
                                    <?= htmlspecialchars($categoryVal) ?>
                                </span>
                                <?php endif; ?>

                                <!-- NEW badge for recent uploads -->
                                <?php if (strtotime($paper['created_at']) > strtotime('-24 hours')): ?>
                                    <div class="position-absolute bottom-0 start-0 m-2 badge bg-success shadow-sm"
                                        style="font-size: 10px; z-index: 2;">
                                        NEW
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card info -->
                            <div class="dashboard-card-info">
                                <div class="dashboard-card-type-badge">
                                    <?= strtoupper($paper['file_type'] ?? 'DOCUMENT') ?>
                                </div>
                                <div class="dashboard-card-title">
                                    <?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>
                                </div>

                                <!-- INTEGRATION: Display custom metadata using display configuration -->
                                <?= renderCardMetadata($paper['custom_metadata'] ?? []) ?>
                            </div>

                            <!-- Admin action buttons (shown on hover) -->
                            <div class="dashboard-card-actions">
                                <button class="btn btn-edit"
                                    onclick="event.stopPropagation(); window.location.href='<?= APP_URL ?>/admin_pages/upload.php?edit=<?= $paper['id'] ?>'">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-delete" onclick="event.stopPropagation(); deleteFile(<?= $paper['id'] ?>)">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- File Preview Modal (Admin) -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content public-modal-content">
            <div class="modal-body p-0">
                <div class="public-modal">
                    <!-- Left: Image + Action Buttons -->
                    <div class="public-modal-left">
                        <div class="public-modal-img-container">
                            <img id="photoViewerImg" src="" class="public-modal-img" alt="File Preview"
                                style="display: none;">
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

                    <!-- Right: Metadata -->
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

                        <!-- Dynamic metadata rows rendered by JS -->
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

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-standard">
        <div class="modal-content modal-minimalist">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-icon icon-danger">
                    <i class="bi bi-trash3"></i>
                </div>
                <h5 class="modal-title">Confirm Bulk Delete</h5>
            </div>
            <div class="modal-body">
                <p id="bulkDeleteMessage">Are you sure you want to move selected items to trash? This action can be
                    undone from the Trash page.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmBulkDeleteBtn">
                    <i class="bi bi-trash3 me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Success Modal -->
<div class="modal fade" id="uploadSuccessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm-standard">
        <div class="modal-content modal-sm-minimalist">
            <div class="modal-icon icon-success">
                <i class="bi bi-check-lg"></i>
            </div>
            <h5 class="modal-title">Upload Complete!</h5>
            <div class="modal-body">
                <p>Your document has been successfully added to the archive.</p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
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