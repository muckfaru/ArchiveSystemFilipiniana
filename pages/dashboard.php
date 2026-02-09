<?php
/**
 * Dashboard Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get stats
$totalArchives = countArchives();
$totalIssues = countIssues();
$yearsCovered = getYearsCovered();
// Count categories that are used by uploaded newspapers
$totalCategories = countCategories();

// Get categories and languages for filters
$categories = getCategories();
$languages = getLanguages();

// Get recent newspapers
$recentNewspapers = getRecentNewspapers(8);

// Handle search
$searchQuery = $_GET['q'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$languageFilter = $_GET['language'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$searchResults = [];
if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo) {
    $sql = "SELECT n.*, c.name as category_name, l.name as language_name 
            FROM newspapers n 
            LEFT JOIN categories c ON n.category_id = c.id 
            LEFT JOIN languages l ON n.language_id = l.id 
            WHERE n.deleted_at IS NULL";
    $params = [];

    if ($searchQuery) {
        $sql .= " AND (n.title LIKE ? OR n.keywords LIKE ? OR n.description LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    if ($categoryFilter) {
        $sql .= " AND n.category_id = ?";
        $params[] = $categoryFilter;
    }

    if ($languageFilter) {
        $sql .= " AND n.language_id = ?";
        $params[] = $languageFilter;
    }

    if ($dateFrom) {
        $sql .= " AND n.publication_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $sql .= " AND n.publication_date <= ?";
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard -
        <?= APP_NAME ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Overview</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Archives</span>
                        <i class="bi bi-file-earmark-text stat-card-icon"></i>
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
                        <i class="bi bi-files stat-card-icon"></i>
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
                        <i class="bi bi-calendar-range stat-card-icon"></i>
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
                        <i class="bi bi-grid-3x3-gap stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value">
                        <?= $totalCategories ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Search & Filter -->
        <div class="search-filter-card">
            <div class="search-filter-title">
                <i class="bi bi-funnel"></i>
                Advanced Search & Filter
            </div>

            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" name="q"
                                placeholder="Search titles, headlines, keywords..."
                                value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Categories</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Language</label>
                        <select class="form-select" name="language">
                            <option value="">All Language</option>
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?= $lang['id'] ?>" <?= $languageFilter == $lang['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lang['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">DATE RANGE</label>
                        <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="search-btn w-100">
                            <i class="bi bi-search"></i>
                            Search
                        </button>
                    </div>
                </div>

                <!-- Recent Tags removed as requested -->
            </form>
        </div>

        <!-- Search Results (if any) -->
        <?php if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo): ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Search Results (<?= count($searchResults) ?> found)</h5>
                    <a href="<?= APP_URL ?>/pages/dashboard.php" class="btn btn-sm"
                        style="background: #f5f5f5; border: 1px solid #ddd; color: #666; border-radius: 6px;">
                        <i class="bi bi-x"></i> Clear Search
                    </a>
                </div>
                <?php if (empty($searchResults)): ?>
                    <div class="alert"
                        style="background: #FFF8F6; border: 1px solid #E6D5C9; color: #8B5A3C; border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-info-circle me-2"></i>No results found for your search criteria.</span>
                            <a href="<?= APP_URL ?>/pages/dashboard.php" class="text-decoration-none" style="color: #8B5A3C;">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($searchResults as $paper): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="newspaper-card">
                                    <?php if ($paper['thumbnail_path']): ?>
                                        <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
                                    <?php else: ?>
                                        <div class="newspaper-thumbnail bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="bi bi-newspaper text-white" style="font-size: 48px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="newspaper-info">
                                        <div class="newspaper-category">
                                            <?= $paper['category_name'] ?? 'Uncategorized' ?>
                                        </div>
                                        <h6 class="newspaper-title">
                                            <?= htmlspecialchars($paper['title']) ?>
                                        </h6>
                                        <div class="newspaper-date">
                                            <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : 'N/A' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Recent Activities -->
        <div class="recent-activities">
            <div class="recent-activities-header">
                <h2 class="recent-activities-title">Recent Activities</h2>
                <a href="<?= APP_URL ?>/pages/history.php" class="view-all-link">View all</a>
            </div>

            <?php if (empty($recentNewspapers)): ?>
                <div class="alert alert-info">No newspapers uploaded yet. <a href="<?= APP_URL ?>/pages/upload.php">Upload
                        your first newspaper</a>.</div>
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
                                data-category="<?= htmlspecialchars($paper['category_name'] ?? 'Uncategorized') ?>">
                                <?php if ($paper['thumbnail_path']): ?>
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
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
                                    <div class="newspaper-date">
                                        <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : date('d F Y', strtotime($paper['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

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
                            <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4"
                                style="min-height: 300px;">
                                <div class="preview-image-container"
                                    style="background: #1a1a1a; border-radius: 4px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
                                    <img id="previewImage" src="" alt="Preview"
                                        style="display: block; max-height: 280px; max-width: 100%; width: auto;">
                                    <div id="noPreviewIcon"
                                        style="display: none; padding: 60px; background: #333; text-align: center;">
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
                                    <h5 class="fw-bold mb-1" style="color: #1a1a1a; font-size: 18px;">File Preview</h5>
                                    <p id="previewCategory" class="text-muted mb-0" style="font-size: 12px;">Archive
                                        Management System</p>
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

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        let currentFileId = null;

        // Category color mapping
        const categoryColors = {
            'culture': { bg: '#FFF3E0', color: '#E65100' },
            'politics': { bg: '#E3F2FD', color: '#1565C0' },
            'sports': { bg: '#E8F5E9', color: '#2E7D32' },
            'business': { bg: '#FBE9E7', color: '#BF360C' },
            'news': { bg: '#F3E5F5', color: '#7B1FA2' },
            'entertainment': { bg: '#FCE4EC', color: '#C2185B' },
            'default': { bg: '#ECEFF1', color: '#546E7A' }
        };

        // File Preview Modal Handler
        const filePreviewModal = document.getElementById('filePreviewModal');
        if (filePreviewModal) {
            filePreviewModal.addEventListener('show.bs.modal', function (event) {
                // event.relatedTarget is the element that triggered the modal (the card)
                const card = event.relatedTarget;
                if (!card) return;

                // Get data from card
                const title = card.dataset.title;
                const thumbnail = card.dataset.thumbnail;
                const date = card.dataset.date;
                const edition = card.dataset.edition;
                const pages = card.dataset.pages;
                const format = card.dataset.format;
                const uploader = card.dataset.uploader;
                const tags = card.dataset.tags;
                const file = card.dataset.file;
                const id = card.dataset.id;

                // Store current file ID for delete
                currentFileId = id;

                // Update modal content - Safe checks
                const titleEl = document.getElementById('previewTitle');
                if (titleEl) titleEl.textContent = title;

                // Handle Image
                const previewImg = document.getElementById('previewImage');
                const noPreviewIcon = document.getElementById('noPreviewIcon');
                if (thumbnail) {
                    if (previewImg) {
                        previewImg.src = thumbnail;
                        previewImg.style.display = 'block';
                    }
                    if (noPreviewIcon) noPreviewIcon.style.display = 'none';
                } else {
                    if (previewImg) previewImg.style.display = 'none';
                    if (noPreviewIcon) noPreviewIcon.style.display = 'block';
                }

                // Update Metadata
                const dateEl = document.getElementById('metaDate');
                if (dateEl) dateEl.textContent = date || 'N/A';

                const editionEl = document.getElementById('metaEdition');
                if (editionEl) editionEl.textContent = edition || 'Standard';

                const pagesEl = document.getElementById('metaPages');
                if (pagesEl) pagesEl.textContent = pages ? pages + ' Pages' : 'N/A';

                const formatEl = document.getElementById('metaFormat');
                if (formatEl) formatEl.textContent = format || 'PDF';

                const uploaderEl = document.getElementById('metaUploader');
                if (uploaderEl) uploaderEl.textContent = uploader || 'Admin';

                // Update Category subtitle
                const categoryEl = document.getElementById('previewCategory');
                const category = card.dataset.category;
                if (categoryEl) categoryEl.textContent = category || 'Uncategorized';

                // Update Tags
                const tagsContainer = document.getElementById('metaTags');
                if (tagsContainer) {
                    tagsContainer.innerHTML = '';
                    if (tags) {
                        const tagList = tags.split(',').filter(t => t.trim());
                        tagList.forEach(tag => {
                            const tagEl = document.createElement('span');
                            tagEl.className = 'badge rounded-pill bg-light text-dark border';
                            tagEl.style.fontWeight = '500';
                            tagEl.textContent = tag.trim();
                            tagsContainer.appendChild(tagEl);
                        });
                    }
                    if (tagsContainer.children.length === 0) {
                        tagsContainer.innerHTML = '<span class="text-muted small">No tags</span>';
                    }
                }

                // Update Links
                const readNowBtn = document.getElementById('readNowBtn');
                if (readNowBtn) readNowBtn.href = '<?= APP_URL ?>/pages/reader.php?id=' + id;

                const editBtn = document.getElementById('editBtn');
                if (editBtn) editBtn.href = '<?= APP_URL ?>/pages/upload.php?edit=' + id;

                // Bind delete button
                const deleteBtn = document.getElementById('deleteBtn');
                if (deleteBtn) {
                    deleteBtn.onclick = function () {
                        showDeleteConfirmation();
                    };
                }
            });
        }

        // Show delete confirmation (handles modal nesting)
        function showDeleteConfirmation() {
            // Close the preview modal first
            const previewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('filePreviewModal'));
            if (previewModal) {
                previewModal.hide();
            }

            // Wait for preview modal to close, then show delete modal
            setTimeout(() => {
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                deleteModal.show();
            }, 300);
        }

        // Delete confirmation handler
        document.getElementById('confirmDeleteBtn')?.addEventListener('click', function () {
            if (currentFileId) {
                // Create and submit form for delete
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?= APP_URL ?>/pages/trash.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'item_id';
                idInput.value = currentFileId;

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'move_to_trash';

                form.appendChild(idInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Show upload success modal if redirected from upload
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'upload') {
                const modal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
                modal.show();

                // Clean up URL
                history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>