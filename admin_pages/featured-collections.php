<?php
/**
 * Featured Collections Management
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

if (!in_array($currentUser['role'], ['super_admin', 'admin'], true)) {
    redirect(route_url('dashboard', ['error' => 'Access denied']));
}

function fc_redirect(array $query = []): void
{
    redirect(route_url('featured-collections', $query));
}

function fc_ensure_storage(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS featured_collections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_featured_collection_slug (slug),
            KEY idx_featured_collections_sort (is_active, sort_order, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS featured_collection_items (
            collection_id INT NOT NULL,
            file_id INT NOT NULL,
            item_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (collection_id, file_id),
            KEY idx_featured_collection_items_order (collection_id, item_order, file_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function fc_get_default_collection_id(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT id FROM featured_collections WHERE slug = ? LIMIT 1");
    $stmt->execute(['homepage-featured']);
    $collectionId = (int) $stmt->fetchColumn();

    if ($collectionId > 0) {
        return $collectionId;
    }

    $insert = $pdo->prepare("
        INSERT INTO featured_collections (name, slug, description, is_active, sort_order, created_by)
        VALUES (?, ?, ?, 1, 0, ?)
    ");
    $insert->execute([
        'Homepage Featured Collection',
        'homepage-featured',
        'System-managed featured files for the public archive.',
        $userId
    ]);

    return (int) $pdo->lastInsertId();
}

try {
    fc_ensure_storage($pdo);
    $featuredCollectionId = fc_get_default_collection_id($pdo, (int) $currentUser['id']);
} catch (Throwable $e) {
    die('Unable to initialize featured collections storage.');
}

$search = trim((string) ($_GET['search'] ?? ''));
$publicationTypeFilter = trim((string) ($_GET['publication_type'] ?? ''));
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 10);
$allowedLimits = [10, 25, 50];
if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$status = trim((string) ($_GET['status'] ?? ''));
$statusMessage = trim((string) ($_GET['message'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'toggle_featured') {
            $fileId = (int) ($_POST['file_id'] ?? 0);
            $makeFeatured = (int) ($_POST['make_featured'] ?? 0) === 1;

            if ($fileId <= 0) {
                throw new RuntimeException('Invalid file selection.');
            }

            $fileStmt = $pdo->prepare("
                SELECT id, title, file_name
                FROM newspapers
                WHERE id = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $fileStmt->execute([$fileId]);
            $file = $fileStmt->fetch();

            if (!$file) {
                throw new RuntimeException('File not found.');
            }

            $displayName = trim((string) ($file['title'] ?? '')) !== ''
                ? (string) $file['title']
                : (string) $file['file_name'];

            if ($makeFeatured) {
                $nextOrderStmt = $pdo->prepare("
                    SELECT COALESCE(MAX(item_order), 0) + 1
                    FROM featured_collection_items
                    WHERE collection_id = ?
                ");
                $nextOrderStmt->execute([$featuredCollectionId]);
                $nextOrder = (int) $nextOrderStmt->fetchColumn();

                $insertStmt = $pdo->prepare("
                    INSERT INTO featured_collection_items (collection_id, file_id, item_order)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE item_order = VALUES(item_order)
                ");
                $insertStmt->execute([$featuredCollectionId, $fileId, $nextOrder]);

                logActivity($currentUser['id'], 'settings_update', 'Added file to featured collection: ' . $displayName);
                $status = 'updated';
            } else {
                $deleteStmt = $pdo->prepare("
                    DELETE FROM featured_collection_items
                    WHERE collection_id = ? AND file_id = ?
                ");
                $deleteStmt->execute([$featuredCollectionId, $fileId]);

                logActivity($currentUser['id'], 'settings_update', 'Removed file from featured collection: ' . $displayName);
                $status = 'updated';
            }

            fc_redirect(array_filter([
                'search' => trim((string) ($_POST['return_search'] ?? '')),
                'publication_type' => trim((string) ($_POST['return_publication_type'] ?? '')),
                'category' => trim((string) ($_POST['return_category'] ?? '')),
                'limit' => (int) ($_POST['return_limit'] ?? $limit),
                'page' => max(1, (int) ($_POST['return_page'] ?? 1)),
                'status' => $status,
            ], static fn($value): bool => $value !== '' && $value !== null));
        }
    } catch (Throwable $e) {
        fc_redirect(array_filter([
            'search' => trim((string) ($_POST['return_search'] ?? $search)),
            'publication_type' => trim((string) ($_POST['return_publication_type'] ?? $publicationTypeFilter)),
            'category' => trim((string) ($_POST['return_category'] ?? $categoryFilter)),
            'limit' => (int) ($_POST['return_limit'] ?? $limit),
            'page' => max(1, (int) ($_POST['return_page'] ?? $page)),
            'status' => 'error',
            'message' => $e->getMessage(),
        ], static fn($value): bool => $value !== '' && $value !== null));
    }
}

$metadataFieldSql = "
    SELECT
        cmv.file_id,
        MAX(CASE
            WHEN LOWER(TRIM(ff.field_label)) = 'title' AND TRIM(COALESCE(cmv.field_value, '')) != ''
                THEN TRIM(cmv.field_value)
            ELSE NULL
        END) AS custom_title,
        MAX(CASE
            WHEN LOWER(TRIM(ff.field_label)) IN ('publication type', 'publication_type', 'type')
                AND TRIM(COALESCE(cmv.field_value, '')) != ''
                THEN TRIM(cmv.field_value)
            ELSE NULL
        END) AS publication_type,
        MAX(CASE
            WHEN LOWER(TRIM(ff.field_label)) IN ('category', 'categories')
                AND TRIM(COALESCE(cmv.field_value, '')) != ''
                THEN TRIM(cmv.field_value)
            ELSE NULL
        END) AS categories
    FROM custom_metadata_values cmv
    INNER JOIN form_fields ff ON ff.id = cmv.field_id
    GROUP BY cmv.file_id
";

$filterWhere = ["n.deleted_at IS NULL"];
$filterParams = [];

if ($search !== '') {
    $filterWhere[] = "(
        CONVERT(COALESCE(NULLIF(meta.custom_title, ''), NULLIF(n.title, ''), n.file_name) USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci
        OR CONVERT(n.file_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci
        OR CONVERT(COALESCE(meta.publication_type, '') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci
        OR CONVERT(COALESCE(meta.categories, '') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci
    )";
    $searchLike = '%' . $search . '%';
    $filterParams[] = $searchLike;
    $filterParams[] = $searchLike;
    $filterParams[] = $searchLike;
    $filterParams[] = $searchLike;
}

if ($publicationTypeFilter !== '') {
    $filterWhere[] = "CONVERT(COALESCE(meta.publication_type, 'Document') USING utf8mb4) COLLATE utf8mb4_general_ci = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci";
    $filterParams[] = $publicationTypeFilter;
}

if ($categoryFilter !== '') {
    $filterWhere[] = "CONVERT(COALESCE(meta.categories, 'Uncategorized') USING utf8mb4) COLLATE utf8mb4_general_ci = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci";
    $filterParams[] = $categoryFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $filterWhere);

$countSql = "
    SELECT COUNT(*) 
    FROM newspapers n
    LEFT JOIN ($metadataFieldSql) meta ON meta.file_id = n.id
    $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($filterParams);
$totalItems = (int) $countStmt->fetchColumn();

$pagination = getPagination($totalItems, $page, $limit);
$page = max(1, (int) ($pagination['current_page'] ?: 1));
$totalPages = max(1, (int) ceil(max(1, $totalItems) / $limit));

$filesSql = "
    SELECT
        n.id,
        n.title,
        n.file_name,
        n.file_type,
        n.thumbnail_path,
        n.created_at,
        COALESCE(NULLIF(meta.custom_title, ''), NULLIF(n.title, ''), n.file_name) AS display_title,
        COALESCE(NULLIF(meta.publication_type, ''), 'Document') AS publication_type,
        COALESCE(NULLIF(meta.categories, ''), 'Uncategorized') AS categories,
        CASE WHEN fci.file_id IS NULL THEN 0 ELSE 1 END AS is_featured
    FROM newspapers n
    LEFT JOIN ($metadataFieldSql) meta ON meta.file_id = n.id
    LEFT JOIN featured_collection_items fci
        ON fci.file_id = n.id
        AND fci.collection_id = ?
    $whereClause
    ORDER BY is_featured DESC, n.created_at DESC, n.id DESC
    LIMIT ? OFFSET ?
";
$filesParams = array_merge([$featuredCollectionId], $filterParams, [$limit, (int) $pagination['offset']]);
$filesStmt = $pdo->prepare($filesSql);
$filesStmt->execute($filesParams);
$files = $filesStmt->fetchAll();

$featuredCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM featured_collection_items
    WHERE collection_id = ?
");
$featuredCountStmt->execute([$featuredCollectionId]);
$featuredCount = (int) $featuredCountStmt->fetchColumn();

$publicationTypeOptionsStmt = $pdo->query("
    SELECT DISTINCT TRIM(cmv.field_value) AS value
    FROM custom_metadata_values cmv
    INNER JOIN form_fields ff ON ff.id = cmv.field_id
    WHERE LOWER(TRIM(ff.field_label)) IN ('publication type', 'publication_type', 'type')
        AND TRIM(COALESCE(cmv.field_value, '')) != ''
    ORDER BY value ASC
");
$publicationTypeOptions = $publicationTypeOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

$categoryOptionsStmt = $pdo->query("
    SELECT DISTINCT TRIM(cmv.field_value) AS value
    FROM custom_metadata_values cmv
    INNER JOIN form_fields ff ON ff.id = cmv.field_id
    WHERE LOWER(TRIM(ff.field_label)) IN ('category', 'categories')
        AND TRIM(COALESCE(cmv.field_value, '')) != ''
    ORDER BY value ASC
");
$categoryOptions = $categoryOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

$showingStart = $totalItems > 0 ? ((int) $pagination['offset']) + 1 : 0;
$showingEnd = min(((int) $pagination['offset']) + $limit, $totalItems);

$buildUrl = static function (int $targetPage) use ($search, $publicationTypeFilter, $categoryFilter, $limit): string {
    return '?' . http_build_query(array_filter([
        'page' => $targetPage,
        'limit' => $limit,
        'search' => $search,
        'publication_type' => $publicationTypeFilter,
        'category' => $categoryFilter,
    ], static fn($value): bool => $value !== '' && $value !== null));
};

$pageTitle = 'Featured Collections';
$pageCss = ['featured-collections.css'];

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="page-header admin-page-header fc-page-header">
    <div>
        <h1 class="page-title">Featured Collections</h1>
        <p class="page-subtitle">Manage which uploaded files appear in the featured collection.</p>
    </div>
    <div class="fc-page-header-actions">
        <span class="fc-status-pill">
            <i class="bi bi-stars"></i>
            <?= number_format($featuredCount) ?> featured file<?= $featuredCount === 1 ? '' : 's' ?>
        </span>
    </div>
</div>

<?php if ($status === 'error' && $statusMessage !== ''): ?>
    <div class="alert alert-danger fc-feedback-alert" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($statusMessage) ?></span>
    </div>
<?php endif; ?>

<section class="fc-table-card">
    <div class="fc-toolbar">
        <form method="GET" class="fc-toolbar-grid">
            <div class="fc-search">
                <i class="bi bi-search"></i>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search files by title, filename, publication type, or category..."
                    value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="fc-filter">
                <label for="fcPublicationType">Publication Type</label>
                <select id="fcPublicationType" name="publication_type" class="form-select">
                    <option value="">All publication types</option>
                    <?php foreach ($publicationTypeOptions as $option): ?>
                        <option value="<?= htmlspecialchars((string) $option) ?>" <?= $publicationTypeFilter === (string) $option ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fc-filter">
                <label for="fcCategory">Category</label>
                <select id="fcCategory" name="category" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categoryOptions as $option): ?>
                        <option value="<?= htmlspecialchars((string) $option) ?>" <?= $categoryFilter === (string) $option ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="limit" value="<?= $limit ?>">

            <div class="fc-toolbar-actions">
                <button type="submit" class="btn btn-primary fc-primary-btn">
                    <i class="bi bi-funnel"></i>
                    Apply
                </button>
                <?php if ($search !== '' || $publicationTypeFilter !== '' || $categoryFilter !== ''): ?>
                    <a href="<?= route_url('featured-collections') ?>" class="btn btn-light fc-secondary-btn">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table fc-table mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Publication Type</th>
                    <th>Categories</th>
                    <th class="fc-featured-col">Featured</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($files)): ?>
                    <tr>
                        <td colspan="4" class="fc-empty-state">
                            <i class="bi bi-inbox"></i>
                            <span><?= ($search !== '' || $publicationTypeFilter !== '' || $categoryFilter !== '') ? 'No files matched your filters.' : 'No uploaded files found.' ?></span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td>
                                <div class="fc-title-cell">
                                    <div class="fc-thumb-wrap">
                                        <?php if (!empty($file['thumbnail_path'])): ?>
                                            <img
                                                src="<?= APP_URL . '/' . htmlspecialchars((string) $file['thumbnail_path']) ?>"
                                                alt="<?= htmlspecialchars((string) $file['display_title']) ?>"
                                                class="fc-thumb">
                                        <?php else: ?>
                                            <div class="fc-thumb-placeholder">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fc-title-copy">
                                        <div class="fc-title-text"><?= htmlspecialchars((string) $file['display_title']) ?></div>
                                        <div class="fc-file-meta"><?= htmlspecialchars(strtoupper((string) $file['file_type'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fc-tag"><?= htmlspecialchars((string) $file['publication_type']) ?></span>
                            </td>
                            <td>
                                <span class="fc-tag fc-tag-muted"><?= htmlspecialchars((string) $file['categories']) ?></span>
                            </td>
                            <td class="fc-featured-col">
                                <form method="POST" class="fc-toggle-form">
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                                    <input type="hidden" name="make_featured" value="<?= (int) $file['is_featured'] === 1 ? 0 : 1 ?>">
                                    <input type="hidden" name="return_search" value="<?= htmlspecialchars($search) ?>">
                                    <input type="hidden" name="return_publication_type" value="<?= htmlspecialchars($publicationTypeFilter) ?>">
                                    <input type="hidden" name="return_category" value="<?= htmlspecialchars($categoryFilter) ?>">
                                    <input type="hidden" name="return_limit" value="<?= $limit ?>">
                                    <input type="hidden" name="return_page" value="<?= $page ?>">

                                    <label class="fc-switch">
                                        <input
                                            type="checkbox"
                                            <?= (int) $file['is_featured'] === 1 ? 'checked' : '' ?>
                                            onchange="this.form.submit()">
                                        <span class="fc-switch-slider"></span>
                                        <span class="visually-hidden">Toggle featured status</span>
                                    </label>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="fc-table-footer">
        <form method="GET" class="fc-rows-form">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="publication_type" value="<?= htmlspecialchars($publicationTypeFilter) ?>">
            <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">

            <span class="fc-footer-label">Rows per page:</span>
            <select name="limit" class="fc-rows-select" onchange="this.form.submit()">
                <?php foreach ($allowedLimits as $option): ?>
                    <option value="<?= $option ?>" <?= $limit === $option ? 'selected' : '' ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
            <span class="fc-footer-info">Showing <?= number_format($showingStart) ?>-<?= number_format($showingEnd) ?> of <?= number_format($totalItems) ?></span>
        </form>

        <nav class="fc-pagination" aria-label="Featured collections pagination">
            <a href="<?= $buildUrl(max(1, $page - 1)) ?>" class="fc-page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1):
            ?>
                <a href="<?= $buildUrl(1) ?>" class="fc-page-btn">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="fc-page-ellipsis">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?= $buildUrl($i) ?>" class="fc-page-btn <?= $i === $page ? 'is-active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="fc-page-ellipsis">...</span>
                <?php endif; ?>
                <a href="<?= $buildUrl($totalPages) ?>" class="fc-page-btn"><?= $totalPages ?></a>
            <?php endif; ?>

            <a href="<?= $buildUrl(min($totalPages, $page + 1)) ?>" class="fc-page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </nav>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($status !== '' && $status !== 'error'): ?>
    if (typeof showToast === 'function') {
        showToast('Featured collection updated successfully.', 'success');
    }

    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.delete('status');
    currentUrl.searchParams.delete('message');
    window.history.replaceState({}, document.title, currentUrl.toString());
    <?php endif; ?>

    const filterForm = document.querySelector('.fc-toolbar-grid');
    if (!filterForm) {
        return;
    }

    const searchInput = filterForm.querySelector('input[name="search"]');
    const selectInputs = filterForm.querySelectorAll('select');
    const submitButtons = filterForm.querySelectorAll('button[type="submit"]');
    let debounceTimer = null;

    const setSubmitState = function (disabled) {
        submitButtons.forEach(function (button) {
            button.disabled = disabled;
        });
    };

    const submitLive = function () {
        setSubmitState(true);
        filterForm.requestSubmit();
    };

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(function () {
                submitLive();
            }, 300);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.clearTimeout(debounceTimer);
                submitLive();
            }
        });
    }

    selectInputs.forEach(function (selectElement) {
        selectElement.addEventListener('change', function () {
            window.clearTimeout(debounceTimer);
            submitLive();
        });
    });
});
</script>

<?php include __DIR__ . '/../views/layouts/footer.php'; ?>
