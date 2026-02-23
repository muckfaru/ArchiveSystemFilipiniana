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
// First, get all categories and count how many documents they have
$catSql = "SELECT c.id, c.name, COUNT(n.id) as count 
           FROM categories c 
           LEFT JOIN newspapers n ON c.id = n.category_id AND n.deleted_at IS NULL
           GROUP BY c.id, c.name
           ORDER BY c.name ASC";
$categoriesWithCounts = $pdo->query($catSql)->fetchAll();

// Total documents overall
$totalDocsSql = "SELECT COUNT(id) FROM newspapers WHERE deleted_at IS NULL";
$totalCollectionsCount = $pdo->query($totalDocsSql)->fetchColumn();

// --- Build Main Query ---
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

if ($categoryFilter) {
    if ($categoryFilter !== 'all') {
        $whereClause .= " AND n.category_id = ?";
        $params[] = $categoryFilter;
    }
}

if ($searchQuery) {
    $whereClause .= " AND (n.title LIKE ? OR n.keywords LIKE ? OR n.description LIKE ?)";
    $params[] = "%$searchQuery%";
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
    $orderBy = 'n.publication_date ASC';
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

    $exportSql = "SELECT n.*, c.name as category_name 
                  FROM newspapers n 
                  LEFT JOIN categories c ON n.category_id = c.id 
                  $whereClause ORDER BY $orderBy";

    $stmt = $pdo->prepare($exportSql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['title'],
            $row['category_name'] ?? 'Uncategorized',
            $row['publication_date'] ? date('Y-m-d', strtotime($row['publication_date'])) : 'N/A',
            $row['edition'],
            $row['page_count'],
            $row['file_type'],
            $row['keywords']
        ]);
    }

    fclose($output);
    exit;
}

// Fetch actual documents for current page
$sql = "SELECT n.*, c.name as category_name, l.name as language_name 
        FROM newspapers n 
        LEFT JOIN categories c ON n.category_id = c.id 
        LEFT JOIN languages l ON n.language_id = l.id 
        $whereClause 
        ORDER BY $orderBy 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$documents = $stmt->fetchAll();

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

                    <div class="filter-dropdown-container">
                        <select class="form-select border-0 shadow-none px-4 fw-medium text-dark" name="category"
                            style="background-color: #F1F5F9; border-radius: 8px; height: 44px; width: auto; min-width: 140px; font-size: 13px; cursor: pointer;"
                            onchange="document.getElementById('searchFilterForm').submit()">
                            <option value="all">All Categories</option>
                            <?php foreach ($categoriesWithCounts as $cat): ?>
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
                    style="<?= $isAllActive ? 'background-color: #E6E0DB; color: #4C3939 !important;' : 'font-size: 14px; color: #4B5563;' ?>">
                    <span>All Collections</span>
                    <?php if ($isAllActive): ?>
                        <span class="badge rounded-pill"
                            style="background-color: #D2C8C1; color: #4C3939; font-weight: 600;">
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
                <?php $isActive = $categoryFilter == $cat['id']; ?>
                <li class="nav-item mb-1">
                    <a href="?category=<?= $cat['id'] ?>&sort=<?= $sortBy ?>&limit=<?= $limit ?>&q=<?= urlencode($searchQuery) ?>"
                        class="nav-link d-flex justify-content-between align-items-center rounded-pill py-2 px-3 <?= $isActive ? 'fw-bold' : 'fw-semibold text-secondary' ?>"
                        style="<?= $isActive ? 'background-color: #E6E0DB; color: #4C3939 !important;' : 'font-size: 14px; color: #4B5563;' ?>">
                        <span>
                            <?= htmlspecialchars($cat['name']) ?>
                        </span>
                        <?php if ($isActive): ?>
                            <span class="badge rounded-pill"
                                style="background-color: #D2C8C1; color: #4C3939; font-weight: 600;">
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
                Showing
                <?= $totalResults > 0 ? ($pagination['offset'] + 1) : 0 ?>-
                <?= min($pagination['offset'] + $limit, $totalResults) ?> of
                <?= number_format($totalResults) ?> results
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

                        <select name="sort" class="form-select border-0 text-secondary p-0 shadow-none fw-medium"
                            style="font-size: 14px; cursor: pointer; background-color: transparent;"
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
                    style="background-color: #4C3939; font-size: 13px;">
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
                    <!-- Newspaper Card Component identical to dashboard -->
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
                                <div class="newspaper-date mt-auto text-muted" style="font-size: 12px; font-weight: 500;">
                                    <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : date('d F Y', strtotime($paper['created_at'])) ?>
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
                        style="width: 40px; height: 40px; border-radius: 8px; <?= $page == $i ? 'background-color: #4C3939; color: white; border-color: #4C3939 !important;' : 'background-color: white; color: #4C3939; border-color: #EAEAEF !important;' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($totalPages > $endPage): ?>
                    <span class="text-secondary px-2 fw-medium">...</span>
                    <a href="<?= getColPaginationUrl($totalPages, $_GET) ?>"
                        class="btn bg-white border d-flex align-items-center justify-content-center p-0 fw-bold"
                        style="width: 40px; height: 40px; border-radius: 8px; color: #4C3939; border-color: #EAEAEF !important;">
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

// Ensure the dashboard JS handles modals on this page as well
echo '<script src="' . APP_URL . '/assets/js/pages/dashboard.js"></script>';

include __DIR__ . '/../views/layouts/footer.php';
?>