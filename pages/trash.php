<?php
/**
 * Trash Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get alert message
$alert = getAlert();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$whereClause = "WHERE n.deleted_at IS NOT NULL";
$params = [];

if ($search) {
    $whereClause .= " AND n.title LIKE ?";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $whereClause .= " AND n.category_id = ?";
    $params[] = $categoryFilter;
}

if ($dateFilter) {
    $whereClause .= " AND DATE(n.deleted_at) = ?";
    $params[] = $dateFilter;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM newspapers n $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];

// Get pagination data
$pagination = getPagination($totalItems, $page, $limit);

// Sort order
$orderBy = $sortBy === 'oldest' ? 'n.deleted_at ASC' : 'n.deleted_at DESC';

// Get trashed items
$sql = "SELECT n.*, u.username as deleted_by_name 
        FROM newspapers n 
        LEFT JOIN users u ON n.deleted_by = u.id 
        $whereClause 
        ORDER BY $orderBy 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $pagination['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trashedItems = $stmt->fetchAll();

// Get categories for filter
$categories = getCategories();

// Calculate days until auto-delete for each item
$autoDeleteDays = intval(getSetting('auto_delete_days', 30));

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'restore') {
        $itemId = intval($_POST['item_id']);

        // Get item title before restore
        $stmt = $pdo->prepare("SELECT title FROM newspapers WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        // Restore item
        $stmt = $pdo->prepare("UPDATE newspapers SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $stmt->execute([$itemId]);

        logActivity($currentUser['id'], 'restore', $item['title']);
        showAlert('success', 'Item restored successfully.');
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'move_to_trash') {
        $itemId = intval($_POST['item_id']);

        // Get item title before moving to trash
        $stmt = $pdo->prepare("SELECT title FROM newspapers WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item) {
            // Soft delete - move to trash
            $stmt = $pdo->prepare("UPDATE newspapers SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $stmt->execute([$currentUser['id'], $itemId]);

            logActivity($currentUser['id'], 'delete', $item['title']);
            showAlert('success', 'Item moved to trash.');
        }
        redirect(APP_URL . '/pages/trash.php');
    }

    if ($action === 'permanent_delete') {
        $itemId = intval($_POST['item_id']);

        // Get item info before delete
        $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item) {
            // Delete physical files
            $filePath = UPLOAD_PATH . '../' . $item['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            if ($item['thumbnail_path']) {
                $thumbPath = UPLOAD_PATH . '../' . $item['thumbnail_path'];
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
            }

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM newspapers WHERE id = ?");
            $stmt->execute([$itemId]);

            logActivity($currentUser['id'], 'permanent_delete', $item['title']);
            showAlert('success', 'Item permanently deleted.');
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'restore_all') {
        $stmt = $pdo->query("UPDATE newspapers SET deleted_at = NULL, deleted_by = NULL WHERE deleted_at IS NOT NULL");
        logActivity($currentUser['id'], 'restore', 'All items');
        showAlert('success', 'All items restored successfully.');
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'empty_trash') {
        // Get all trashed items
        $stmt = $pdo->query("SELECT * FROM newspapers WHERE deleted_at IS NOT NULL");
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            // Delete physical files
            $filePath = UPLOAD_PATH . '../' . $item['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            if ($item['thumbnail_path']) {
                $thumbPath = UPLOAD_PATH . '../' . $item['thumbnail_path'];
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
            }
        }

        // Delete all from database
        $pdo->query("DELETE FROM newspapers WHERE deleted_at IS NOT NULL");

        logActivity($currentUser['id'], 'permanent_delete', 'All trash items');
        showAlert('success', 'Trash emptied successfully.');
        redirect($_SERVER['PHP_SELF']);
    }
}

// Auto-delete old items (older than 30 days)
$stmt = $pdo->prepare("SELECT * FROM newspapers WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
$stmt->execute([$autoDeleteDays]);
$oldItems = $stmt->fetchAll();

foreach ($oldItems as $item) {
    // Delete physical files
    $filePath = UPLOAD_PATH . '../' . $item['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    if ($item['thumbnail_path']) {
        $thumbPath = UPLOAD_PATH . '../' . $item['thumbnail_path'];
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
    }

    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM newspapers WHERE id = ?");
    $stmt->execute([$item['id']]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash -
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
                <h1 class="page-title">Trash</h1>
                <p class="page-subtitle">Recover or permanently remove archived content</p>
            </div>
        </div>

        <!-- Alert Banner -->
        <div class="alert-banner warning">
            <i class="bi bi-exclamation-triangle alert-icon"></i>
            <div>
                <div class="alert-title">Items in trash are automatically deleted after
                    <?= $autoDeleteDays ?> days
                </div>
                <div class="alert-text">Restore important files or permanently delete items to free up space.</div>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert">
                <?= $alert['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="row mb-4">
            <div class="col-md-5">
                <form method="GET" class="d-flex gap-2">
                    <div class="search-input-wrapper flex-grow-1">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" name="search" placeholder="Search deleted items..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2 justify-content-end">
                    <select class="form-select" style="width: auto;" onchange="location.href='?category='+this.value">
                        <option value="">CATEGORIES</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" class="form-control" style="width: auto;" value="<?= $dateFilter ?>"
                        onchange="location.href='?date='+this.value">
                    <select class="form-select" style="width: auto;" onchange="location.href='?sort='+this.value">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Trash Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 100px;">ID</th>
                        <th>TITLE</th>
                        <th>DELETED BY</th>
                        <th>DATE</th>
                        <th>SIZE</th>
                        <th style="width: 120px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trashedItems)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="bi bi-trash text-muted" style="font-size: 48px;"></i>
                                <p class="text-muted mt-2">Trash is empty</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trashedItems as $index => $item): ?>
                            <tr>
                                <td style="color: #888; font-weight: 500;">
                                    <?= $item['id'] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($item['title']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($item['deleted_by_name'] ?? 'Unknown') ?>
                                </td>
                                <td>
                                    <?= formatDate($item['deleted_at']) ?>
                                </td>
                                <td>
                                    <?= formatFileSize($item['file_size']) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm"
                                                style="background: #E8F5E9; color: #2E7D32; border-radius: 6px; padding: 6px 10px;"
                                                title="Restore">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('This action cannot be undone. Are you sure?')">
                                            <input type="hidden" name="action" value="permanent_delete">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm"
                                                style="background: #FFEBEE; color: #C62828; border-radius: 6px; padding: 6px 10px;"
                                                title="Delete Permanently">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <div class="d-flex align-items-center gap-3">
                    <span class="pagination-info">Rows per page</span>
                    <select class="form-select form-select-sm" style="width: auto;" id="rowsPerPage">
                        <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                        <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    </select>
                    <span class="pagination-info">Showing
                        <?= count($trashedItems) ?> of
                        <?= $totalItems ?> items
                    </span>
                </div>

                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= min(5, $pagination['total_pages']); $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Action Buttons -->
        <?php if (!empty($trashedItems)): ?>
            <div class="d-flex justify-content-end gap-3 mt-4">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="restore_all">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Restore All
                    </button>
                </form>
                <form method="POST" class="d-inline"
                    onsubmit="return confirm('This will permanently delete all items. This action cannot be undone. Are you sure?')">
                    <input type="hidden" name="action" value="empty_trash">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-2"></i>Empty Trash
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // Rows per page
        document.getElementById('rowsPerPage').addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', this.value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });
    </script>
</body>

</html>