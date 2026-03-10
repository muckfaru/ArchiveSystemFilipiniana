<?php
/**
 * Collections Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// --- Pagination & Filters ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

$categoryFilter = $_GET['category'] ?? ''; // Category name or ID, let's use ID for consistency but UI shows name. Let's use ID for querying. Wait, the mockup has names.
// It's better to filter by ID but display names. We'll fetch categories first.

$formatFilters = isset($_GET['format']) ? (array) $_GET['format'] : []; // Array of formats like 'pdf', 'mobi', 'images'
$sortBy = $_GET['sort'] ?? 'newest';
$searchQuery = $_GET['q'] ?? '';

// --- Fetch Categories with Counts ---
// Get categories from custom metadata "Category" field
$catSql = "SELECT DISTINCT cmv.field_value as name, COUNT(DISTINCT n.id) as count 
           FROM custom_metadata_values cmv
           INNER JOIN form_fields cmf ON cmv.field_id = cmf.id
           INNER JOIN newspapers n ON cmv.file_id = n.id
           WHERE cmf.field_label = 'Category' AND n.deleted_at IS NULL
           GROUP BY cmv.field_value
           ORDER BY cmv.field_value ASC";
$categoriesWithCounts = $pdo->query($catSql)->fetchAll();

// Total documents overall
$totalDocsSql = "SELECT COUNT(id) FROM newspapers WHERE deleted_at IS NULL";
$totalCollectionsCount = $pdo->query($totalDocsSql)->fetchColumn();

// --- Build Main Query ---
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

if ($categoryFilter) {
    if ($categoryFilter !== 'all') {
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM custom_metadata_values cmv2
            INNER JOIN form_fields cmf2 ON cmv2.field_id = cmf2.id
            WHERE cmv2.file_id = n.id 
            AND cmf2.field_label = 'Category' 
            AND cmv2.field_value = ?
        )";
        $params[] = $categoryFilter;
    }
}

if ($searchQuery) {
    $whereClause .= " AND (n.title LIKE ? OR EXISTS (
        SELECT 1 FROM custom_metadata_values cmv3
        WHERE cmv3.file_id = n.id AND cmv3.field_value LIKE ?
    ))";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if (!empty($formatFilters)) {
    // Determine condition based on formats checked
    $formatConditions = [];
    foreach ($formatFilters as $fmt) {
        $fmt = strtolower($fmt);
        if ($fmt === 'pdf') {
            $formatConditions[] = "n.file_type = 'pdf'";
        } elseif ($fmt === 'mobi') {
            $formatConditions[] = "n.file_type = 'mobi'";
        } elseif ($fmt === 'images') {
            $formatConditions[] = "n.is_bulk_image = 1";
        }
    }
    if (!empty($formatConditions)) {
        $whereClause .= " AND (" . implode(" OR ", $formatConditions) . ")";
    }
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM newspapers n $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetch()['total'];

// Get pagination data
$pagination = getPagination($totalResults, $page, $limit);
$totalPages = ceil($totalResults / $limit);

// Sort order
if ($sortBy === 'oldest') {
    $orderBy = 'n.created_at ASC';
} elseif ($sortBy === 'a-z') {
    $orderBy = 'n.title ASC';
} elseif ($sortBy === 'z-a') {
    $orderBy = 'n.title DESC';
} else {
    $orderBy = 'n.created_at DESC';
}

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'collections_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Title', 'Category', 'Publication Date', 'Edition', 'Pages', 'File Type', 'Keywords']);

    $exportSql = "SELECT DISTINCT n.*
                  FROM newspapers n 
                  $whereClause ORDER BY $orderBy";

    $stmt = $pdo->prepare($exportSql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        // Get custom metadata for this row
        $customMeta = getFileMetadataForDisplay($pdo, $row['id'], 'card');
        $category = getCategoryFromMetadata($customMeta);
        $pubDate = getMetadataValueByLabel($customMeta, ['Publication Date', 'Date']);
        $edition = getMetadataValueByLabel($customMeta, ['Edition']);
        $pageCount = getMetadataValueByLabel($customMeta, ['Page Count', 'Pages']);
        $keywords = getMetadataValueByLabel($customMeta, ['Keywords', 'Tags']);
        
        fputcsv($output, [
            $row['title'],
            $category,
            $pubDate ? date('Y-m-d', strtotime($pubDate)) : 'N/A',
            $edition,
            $pageCount,
            $row['file_type'],
            $keywords
        ]);
    }

    fclose($output);
    exit;
}

// Fetch actual documents for current page
$sql = "SELECT DISTINCT n.*
        FROM newspapers n 
        $whereClause 
        ORDER BY $orderBy 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$documents = $stmt->fetchAll();

// Apply title overrides from custom metadata "Title" field
applyTitleOverrides($documents, $pdo);

// Load custom metadata for all documents
if (!empty($documents)) {
    $fileIds = array_column($documents, 'id');
    foreach ($documents as &$doc) {
        $doc['custom_metadata'] = getFileMetadataForDisplay($pdo, $doc['id'], 'card');
        // Build label-indexed lookup for easy access in views
        $ml = [];
        foreach ($doc['custom_metadata'] as $m) {
            $ml[strtolower(trim($m['field_label'] ?? ''))] = $m['field_value'] ?? '';
        }
        $doc['metadata_by_label'] = $ml;
    }
    unset($doc);
}

// --- Display Output ---
$pageTitle = 'Collections';
$pageCss = ['dashboard.css', 'collections.css'];

include __DIR__ . '/../views/layouts/header.php';
?>

<!-- Top Search Bar & Date/Time Section -->
<div class="dashboard-top-section position-sticky top-0" style="z-index: 1020;">
    <div class="row align-items-center justify-content-between g-3">
        <!-- Search and Filter Form -->
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="m-0" id="searchFilterForm">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                <?php foreach ($formatFilters as $fmt): ?>
                    <input type="hidden" name="format[]" value="<?= htmlspecialchars($fmt) ?>">
                <?php endforeach; ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="top-search-pill d-flex align-items-center flex-grow-1 position-relative p-1">
                        <input type="text" class="form-control border-0 bg-transparent shadow-none px-3" name="q"
                            placeholder="Search digital archives..." value="<?= htmlspecialchars($searchQuery) ?>">
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

<!-- Collections Layout -->
<div class="collections-container d-flex flex-column flex-md-row">

    <!-- Sidebar Filters -->
    <aside class="collections-sidebar p-4 bg-white border-end"
        style="min-width: 260px; min-height: calc(100vh - 70px);">

        <!-- Categories Section -->
        <h6 class="sidebar-heading mb-3"
            style="font-size: 11px; font-weight: 700; color: #9CA3AF; letter-spacing: 1.5px; text-transform: uppercase;">
            CATEGORIES
        </h6>

        <ul class="nav flex-column mb-4 category-list">
            <!-- All Collections Base Item -->
            <li class="nav-item mb-1">
                <?php $isAllActive = empty($categoryFilter) || $categoryFilter === 'all'; ?>
                <a href="?category=all&sort=<?= $sortBy ?>&limit=<?= $limit ?>&q=<?= urlencode($searchQuery) ?>"
                    class="nav-link d-flex justify-content-between align-items-center rounded-pill py-2 px-3 <?= $isAllActive ? 'fw-bold' : 'fw-semibold text-secondary' ?>"
                    style="<?= $isAllActive ? 'background-color: #EBF5FF; color: #3A9AFF !important;' : 'font-size: 14px; color: #4B5563;' ?>">
                    <span>All Collections</span>
                    <?php if ($isAllActive): ?>
                        <span class="badge rounded-pill"
                            style="background-color: #D0E8FF; color: #3A9AFF; font-weight: 600;">
                            <?= formatNumberShortcut($totalCollectionsCount) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted" style="font-size: 13px; font-weight: 500;">
                            <?= formatNumberShortcut($totalCollectionsCount) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <?php foreach ($categoriesWithCounts as $cat): ?>
                <?php $isActive = $categoryFilter == $cat['name']; ?>
                <li class="nav-item mb-1">
                    <a href="?category=<?= urlencode($cat['name']) ?>&sort=<?= $sortBy ?>&limit=<?= $limit ?>&q=<?= urlencode($searchQuery) ?>"
                        class="nav-link d-flex justify-content-between align-items-center rounded-pill py-2 px-3 <?= $isActive ? 'fw-bold' : 'fw-semibold text-secondary' ?>"
                        style="<?= $isActive ? 'background-color: #EBF5FF; color: #3A9AFF !important;' : 'font-size: 14px; color: #4B5563;' ?>">
                        <span>
                            <?= htmlspecialchars($cat['name']) ?>
                        </span>
                        <?php if ($isActive): ?>
                            <span class="badge rounded-pill"
                                style="background-color: #D0E8FF; color: #3A9AFF; font-weight: 600;">
                                <?= formatNumberShortcut($cat['count']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 13px; font-weight: 500;">
                                <?= formatNumberShortcut($cat['count']) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Format Section -->
        <h6 class="sidebar-heading mt-4 mb-3"
            style="font-size: 11px; font-weight: 700; color: #9CA3AF; letter-spacing: 1.5px; text-transform: uppercase;">
            FORMAT
        </h6>

        <form action="" method="GET" id="formatFilterForm">
            <!-- Preserve other GET params -->
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
            <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
            <input type="hidden" name="limit" value="<?= htmlspecialchars($limit) ?>">

            <div class="form-check mb-2">
                <input class="form-check-input border-secondary shadow-none" type="checkbox" name="format[]"
                    value="mobi" id="formatMobi" <?= in_array('mobi', $formatFilters) ? 'checked' : '' ?>
                    onchange="this.form.submit()">
                <label class="form-check-label text-dark fw-medium" for="formatMobi">
                    .MOBI
                </label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input border-secondary shadow-none" type="checkbox" name="format[]" value="pdf"
                    id="formatPdf" <?= in_array('pdf', $formatFilters) ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label text-dark fw-medium" for="formatPdf">
                    PDF
                </label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input border-secondary shadow-none" type="checkbox" name="format[]"
                    value="images" id="formatImages" <?= in_array('images', $formatFilters) ? 'checked' : '' ?>
                    onchange="this.form.submit()">
                <label class="form-check-label text-dark fw-medium" for="formatImages">
                    IMAGES
                </label>
            </div>
        </form>
    </aside>

    <!-- Main Results Column -->
    <main class="collections-content flex-grow-1 p-4 bg-white">

        <!-- Top Action Bar -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-light">

            <!-- Result count -->
            <div class="text-secondary fw-medium" style="font-size: 14px;">
                <?php if ($searchQuery): ?>
                    Showing results for "<strong><?= htmlspecialchars($searchQuery) ?></strong>" - 
                    <?= $totalResults > 0 ? ($pagination['offset'] + 1) : 0 ?>-<?= min($pagination['offset'] + $limit, $totalResults) ?> of
                    <?= number_format($totalResults) ?> results
                <?php else: ?>
                    Showing
                    <?= $totalResults > 0 ? ($pagination['offset'] + 1) : 0 ?>-
                    <?= min($pagination['offset'] + $limit, $totalResults) ?> of
                    <?= number_format($totalResults) ?> results
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Sort Dropdown -->
                <div class="d-flex align-items-center border-start border-light ps-3">
                    <span class="text-dark fw-bold me-2" style="font-size: 14px;">Sort by:</span>
                    <form action="" method="GET" id="sortForm" class="m-0">
                        <!-- Preserve other GET params -->
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                        <?php foreach ($formatFilters as $fmt): ?>
                            <input type="hidden" name="format[]" value="<?= htmlspecialchars($fmt) ?>">
                        <?php endforeach; ?>

                        <select name="sort" class="form-select border-0 text-secondary shadow-none fw-medium"
                            style="font-size: 14px; cursor: pointer; background-color: transparent; min-width: 130px; padding: 0 28px 0 4px;"
                            onchange="this.form.submit()">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="a-z" <?= $sortBy === 'a-z' ? 'selected' : '' ?>>A-Z</option>
                            <option value="z-a" <?= $sortBy === 'z-a' ? 'selected' : '' ?>>Z-A</option>
                        </select>
                    </form>
                </div>

                <!-- Export CSV Button -->
                <?php
                // Build query string for export
                $exportParams = $_GET;
                $exportParams['export'] = 'csv';
                $exportUrl = '?' . http_build_query($exportParams);
                ?>
                <a href="<?= $exportUrl ?>" class="btn text-white rounded-3 px-3 py-2 fw-medium ms-2"
                    style="background-color: #3A9AFF; font-size: 13px;">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Export
                </a>

            </div>
        </div>

        <!-- Document Grid -->
        <?php if (empty($documents)): ?>
            <div class="text-center py-5">
                <i class="bi bi-folder-x text-muted border p-4 rounded-circle" style="font-size: 3rem;"></i>
                <h5 class="mt-4 fw-bold text-secondary">No Documents Found</h5>
                <p class="text-muted small">Try adjusting your filters or category selection.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($documents as $paper): ?>
                    <?php
                        $ml = $paper['metadata_by_label'] ?? [];
                        $pubDate = $ml['publication date'] ?? $ml['publication_date'] ?? $ml['date'] ?? '';
                        $publicationShort = $pubDate ? formatPublicationDate($pubDate, false) : 'N/A';
                    ?>
                    <!-- Newspaper Card Component identical to dashboard -->
                    <div class="col-md-6 col-lg-3">
                        <div class="newspaper-card" style="cursor: pointer;" data-bs-toggle="modal"
                            data-bs-target="#filePreviewModal" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= htmlspecialchars($publicationShort) ?>"
                            data-edition="<?= htmlspecialchars($ml['edition'] ?? 'Standard') ?>"
                            data-pages="<?= $ml['page count'] ?? $ml['pages'] ?? $ml['page_count'] ?? 'N/A' ?>"
                            data-format="<?= strtoupper($paper['file_type'] ?? 'PDF') ?>"
                            data-uploader="<?= htmlspecialchars($paper['uploader_name'] ?? 'Admin') ?>"
                            data-tags="<?= htmlspecialchars($ml['keywords'] ?? $ml['tags'] ?? '') ?>"
                            data-file="<?= APP_URL . '/' . $paper['file_path'] ?>"
                            data-category="<?= htmlspecialchars(getCategoryFromMetadata($paper['custom_metadata'] ?? [])) ?>"
                            data-publisher="<?= htmlspecialchars($ml['publisher'] ?? 'N/A') ?>"
                            data-description="<?= htmlspecialchars($ml['description'] ?? '') ?>"
                            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                            data-image-paths="<?= htmlspecialchars($paper['image_paths'] ?? '[]') ?>"
                            data-volume="<?= htmlspecialchars($ml['volume/issue'] ?? $ml['volume_issue'] ?? $ml['volume'] ?? '') ?>"
                            data-language="<?= htmlspecialchars($ml['language'] ?? '') ?>">

                            <?php if ($paper['thumbnail_path']): ?>
                                <div class="position-relative">
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
                                    <?php if (!empty($paper['is_bulk_image'])): ?>
                                        <div class="position-absolute top-0 end-0 m-2 badge shadow-sm"
                                            style="font-size: 10px; background-color: #3A9AFF; color: white;">
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
                                <div class="newspaper-category <?= strtolower(getCategoryFromMetadata($paper['custom_metadata'] ?? [])) ?>">
                                    <?= strtoupper(getCategoryFromMetadata($paper['custom_metadata'] ?? [])) ?>
                                </div>
                                <h6 class="newspaper-title">
                                    <?= htmlspecialchars(!empty($paper['title']) ? $paper['title'] : $paper['file_name']) ?>
                                </h6>
                                <div class="newspaper-date mt-auto text-muted" style="font-size: 12px; font-weight: 500;">
                                    <?= $pubDate ? date('d F Y', strtotime($pubDate)) : date('d F Y', strtotime($paper['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Component Matching Mockup -->
            <?php
            function getColPaginationUrl($page, $paramsArr)
            {
                $paramsArr['page'] = $page;
                unset($paramsArr['export']);
                return '?' . http_build_query($paramsArr);
            }
            ?>
            <div class="d-flex justify-content-center align-items-center gap-2 mt-5 mb-3">

                <a href="<?= getColPaginationUrl(max(1, $page - 1), $_GET) ?>"
                    class="btn btn-outline-light text-secondary border d-flex align-items-center justify-content-center p-0 <?= !$pagination['has_prev'] ? 'disabled opacity-50' : '' ?>"
                    style="width: 40px; height: 40px; border-radius: 8px;">
                    <i class="bi bi-chevron-left"></i>
                </a>

                <?php
                $startPage = max(1, $page - 1);
                $endPage = min($totalPages, $page + 1);

                for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <a href="<?= getColPaginationUrl($i, $_GET) ?>"
                        class="btn d-flex align-items-center justify-content-center p-0 fw-bold border"
                        style="width: 40px; height: 40px; border-radius: 8px; <?= $page == $i ? 'background-color: #3A9AFF; color: white; border-color: #3A9AFF !important;' : 'background-color: white; color: #3A9AFF; border-color: #EAEAEF !important;' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($totalPages > $endPage): ?>
                    <span class="text-secondary px-2 fw-medium">...</span>
                    <a href="<?= getColPaginationUrl($totalPages, $_GET) ?>"
                        class="btn bg-white border d-flex align-items-center justify-content-center p-0 fw-bold"
                        style="width: 40px; height: 40px; border-radius: 8px; color: #3A9AFF; border-color: #EAEAEF !important;">
                        <?= $totalPages ?>
                    </a>
                <?php endif; ?>

                <a href="<?= getColPaginationUrl(min($totalPages, $page + 1), $_GET) ?>"
                    class="btn btn-outline-light text-secondary border d-flex align-items-center justify-content-center p-0 <?= !$pagination['has_next'] ? 'disabled opacity-50' : '' ?>"
                    style="width: 40px; height: 40px; border-radius: 8px;">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php
// Helper function to format 1200 as 1.2k, etc.
function formatNumberShortcut($n)
{
    if ($n < 1000)
        return $n;
    $number = floor($n / 100) / 10;
    return $number . 'k';
}
?>

<!-- File Preview Modal (Admin - Collections Page) -->
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

                        <div class="public-modal-meta-row">
                            <span class="public-modal-meta-label"><i class="bi bi-calendar3"></i> Publication
                                Date</span>
                            <span id="metaDate" class="public-modal-meta-value">—</span>
                        </div>

                        <div class="public-modal-meta-row">
                            <span class="public-modal-meta-label"><i class="bi bi-building"></i> Publisher</span>
                            <span id="metaPublisher" class="public-modal-meta-value">—</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowLanguage">
                            <span class="public-modal-meta-label"><i class="bi bi-translate"></i> Language</span>
                            <span id="metaLanguage" class="public-modal-meta-value">—</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowPages">
                            <span class="public-modal-meta-label"><i class="bi bi-book"></i> Pages</span>
                            <span id="metaPages" class="public-modal-meta-value">—</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowVolume">
                            <span class="public-modal-meta-label"><i class="bi bi-layers"></i> Volume / Issue</span>
                            <span id="metaVolume" class="public-modal-meta-value">—</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowEdition">
                            <span class="public-modal-meta-label"><i class="bi bi-sun"></i> Edition</span>
                            <span id="metaEdition" class="public-modal-meta-value">—</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowFormat">
                            <span class="public-modal-meta-label"><i class="bi bi-file-earmark"></i> Format</span>
                            <span id="metaFormat" class="public-format-badge">PDF</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowUploader">
                            <span class="public-modal-meta-label"><i class="bi bi-person"></i> Uploaded by</span>
                            <span id="metaUploader" class="public-modal-meta-value">Admin</span>
                        </div>

                        <div class="public-modal-meta-row" id="modalRowKeywords">
                            <span class="public-modal-meta-label"><i class="bi bi-tags"></i> Keywords</span>
                            <div id="metaTags" class="public-modal-keywords-wrap"></div>
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
                <p class="mb-0 text-muted">Are you sure you want to move this item to trash? This action can be undone
                    from the Trash page.</p>
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

<?php
// Ensure the dashboard JS handles modals on this page as well
echo '<script src="' . APP_URL . '/assets/js/admin_pages/dashboard.js"></script>';

include __DIR__ . '/../views/layouts/footer.php';
?>