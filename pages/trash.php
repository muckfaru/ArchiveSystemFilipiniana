<?php
/**
 * Trash Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get alert message
// Get alert message
// $alert = getAlert();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? ''; // Keep for files if needed, or ignore
$dateFilter = $_GET['date'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Base WHERE clauses
$fileWhere = "n.deleted_at IS NOT NULL";
$userWhere = "u.deleted_at IS NOT NULL";
$params = [];

// Apply Search
if ($search) {
    $fileWhere .= " AND n.title LIKE ?";
    $userWhere .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; // For file title
    $params[] = "%$search%"; // For username
    $params[] = "%$search%"; // For email
}

// Apply Category (Files only)
if ($categoryFilter) {
    $fileWhere .= " AND n.category_id = ?";
    $userWhere .= " AND 1=0"; // Users don't have categories
    $params[] = $categoryFilter;
}

// Apply Date
if ($dateFilter) {
    $fileWhere .= " AND DATE(n.deleted_at) = ?";
    $userWhere .= " AND DATE(u.deleted_at) = ?";
    $params[] = $dateFilter;
    $params[] = $dateFilter;
}

// --- Get Total Count for Pagination ---
$countParams = $params;
$countSql = "SELECT SUM(cnt) as total FROM (
    SELECT COUNT(*) as cnt FROM newspapers n WHERE $fileWhere
    UNION ALL
    SELECT COUNT(*) as cnt FROM users u WHERE $userWhere
) as combined_count";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = $countStmt->fetch()['total'] ?? 0;

// Pagination Calculation
$pagination = getPagination($totalItems, $page, $limit);
$offset = $pagination['offset'];

// --- Main Query ---
$orderBy = $sortBy === 'oldest' ? 'deleted_at ASC' : 'deleted_at DESC';

$sql = "
(SELECT 
    n.id, 
    n.title, 
    n.deleted_by, 
    u.username as deleted_by_name, 
    n.deleted_at, 
    n.file_size, 
    'file' as type,
    NULL as email
FROM newspapers n
LEFT JOIN users u ON n.deleted_by = u.id
WHERE $fileWhere)
UNION ALL
(SELECT 
    u.id, 
    u.username as title, 
    u.deleted_by, 
    deleter.username as deleted_by_name, 
    u.deleted_at, 
    NULL as file_size, 
    'user' as type,
    u.email as email
FROM users u
LEFT JOIN users deleter ON u.deleted_by = deleter.id
WHERE $userWhere)
ORDER BY $orderBy
LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trashedItems = $stmt->fetchAll();

// Get categories for filter (for file filtering)
$categories = getCategories();

// Calculate days until auto-delete
$autoDeleteDays = intval(getSetting('auto_delete_days', 30));

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    // --- RESTORE ---
    if ($action === 'restore') {
        if ($type === 'file') {
            $stmt = $pdo->prepare("SELECT title FROM newspapers WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            $stmt = $pdo->prepare("UPDATE newspapers SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($currentUser['id'], 'restore', "File: " . ($item['title'] ?? $id));
        } elseif ($type === 'user') {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($currentUser['id'], 'restore_user', "User: " . ($user['username'] ?? $id));
        }
        redirect($_SERVER['PHP_SELF'] . '?success=restored');
    }

    // --- PERMANENT DELETE ---
    if ($action === 'delete') {
        if ($type === 'file') {
            $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if ($item) {
                // Delete physical files
                if (file_exists(UPLOAD_PATH . '../' . $item['file_path']))
                    unlink(UPLOAD_PATH . '../' . $item['file_path']);
                if ($item['thumbnail_path'] && file_exists(UPLOAD_PATH . '../' . $item['thumbnail_path']))
                    unlink(UPLOAD_PATH . '../' . $item['thumbnail_path']);

                $pdo->prepare("DELETE FROM newspapers WHERE id = ?")->execute([$id]);
                logActivity($currentUser['id'], 'permanent_delete', $item['title']);
            }
        } elseif ($type === 'user') {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if ($user) {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                logActivity($currentUser['id'], 'permanent_delete_user', $user['username']);
            }
        }
        redirect($_SERVER['PHP_SELF'] . '?success=deleted');
    }

    // --- RESTORE ALL ---
    if ($action === 'restore_all') {
        $pdo->query("UPDATE newspapers SET deleted_at = NULL, deleted_by = NULL WHERE deleted_at IS NOT NULL");
        $pdo->query("UPDATE users SET deleted_at = NULL, deleted_by = NULL WHERE deleted_at IS NOT NULL");
        logActivity($currentUser['id'], 'restore_all', 'Restored all items');
        redirect($_SERVER['PHP_SELF'] . '?success=restored_all');
    }

    // --- EMPTY TRASH ---
    if ($action === 'empty_trash') {
        // Delete all files physically
        $stmt = $pdo->query("SELECT * FROM newspapers WHERE deleted_at IS NOT NULL");
        $files = $stmt->fetchAll();
        foreach ($files as $file) {
            if (file_exists(UPLOAD_PATH . '../' . $file['file_path']))
                unlink(UPLOAD_PATH . '../' . $file['file_path']);
            if ($file['thumbnail_path'] && file_exists(UPLOAD_PATH . '../' . $file['thumbnail_path']))
                unlink(UPLOAD_PATH . '../' . $file['thumbnail_path']);
        }

        $pdo->query("DELETE FROM newspapers WHERE deleted_at IS NOT NULL");
        $pdo->query("DELETE FROM users WHERE deleted_at IS NOT NULL");
        logActivity($currentUser['id'], 'empty_trash', 'Emptied all trash');
        redirect($_SERVER['PHP_SELF'] . '?success=emptied');
    }
}

// Auto-delete old items
$stmt = $pdo->prepare("SELECT * FROM newspapers WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
$stmt->execute([$autoDeleteDays]);
$oldItems = $stmt->fetchAll();
foreach ($oldItems as $item) {
    if (file_exists(UPLOAD_PATH . '../' . $item['file_path']))
        unlink(UPLOAD_PATH . '../' . $item['file_path']);
    if ($item['thumbnail_path'] && file_exists(UPLOAD_PATH . '../' . $item['thumbnail_path']))
        unlink(UPLOAD_PATH . '../' . $item['thumbnail_path']);
    $pdo->prepare("DELETE FROM newspapers WHERE id = ?")->execute([$item['id']]);
}
$pdo->prepare("DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$autoDeleteDays]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash - <?= APP_NAME ?></title>

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
        <div class="mb-4">
            <h1 class="fw-bold m-0" style="font-size: 24px; color: #3D2D2D;">Trash</h1>
            <div class="text-muted" style="font-size: 14px;">Recover or permanently remove archived content</div>
        </div>

        <!-- Auto-Delete Info Card -->
        <div class="card border-0 mb-4 bg-light shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px; color: #ff9800;">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 14px;">Auto-Deletion Policy</div>
                        <div class="text-muted small">Items in trash are automatically permanently deleted after
                            <?= $autoDeleteDays ?> days.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts replaced by Modals -->

        <!-- Search & Filter Bar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 p-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <!-- Search -->
                <div class="input-group input-group-attached flex-grow-1" style="max-width: 500px;">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <form method="GET" class="d-flex flex-grow-1" id="searchForm">
                        <input type="text" class="form-control border-start-0 py-2" name="search"
                            placeholder="Search deleted items..." value="<?= htmlspecialchars($search) ?>"
                            style="font-size: 14px;">
                        <button class="btn btn-primary px-3 rounded-end-pill" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>

                <!-- Filters -->
                <div class="d-flex gap-2">
                    <select class="form-select bg-light border-0 py-2 fw-medium" name="category"
                        onchange="window.location.href='?category='+this.value"
                        style="width: auto; font-size: 13px; border-radius: 8px;">
                        <option value="">Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="date" class="form-control bg-light border-0 py-2 fw-medium" name="date"
                        value="<?= htmlspecialchars($dateFilter) ?>" onchange="window.location.href='?date='+this.value"
                        style="width: auto; font-size: 13px; border-radius: 8px;">

                    <select class="form-select bg-light border-0 py-2 fw-medium" name="sort"
                        onchange="window.location.href='?sort='+this.value"
                        style="width: auto; font-size: 13px; border-radius: 8px;">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Unified Trash Table -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary text-uppercase"
                                style="font-size: 11px; font-weight: 700; width: 60px;">ID</th>
                            <th class="py-3 text-secondary text-uppercase" style="font-size: 11px; font-weight: 700;">
                                TITLE</th>
                            <th class="py-3 text-secondary text-uppercase" style="font-size: 11px; font-weight: 700;">
                                DELETED BY</th>
                            <th class="py-3 text-secondary text-uppercase" style="font-size: 11px; font-weight: 700;">
                                DATE</th>
                            <th class="py-3 text-secondary text-uppercase" style="font-size: 11px; font-weight: 700;">
                                SIZE</th>
                            <th class="text-end pe-4 py-3 text-secondary text-uppercase"
                                style="font-size: 11px; font-weight: 700; width: 120px;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trashedItems)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center">
                                        <div class="bg-light rounded-circle p-3 mb-3">
                                            <i class="bi bi-trash text-muted" style="font-size: 24px;"></i>
                                        </div>
                                        <h6 class="fw-bold text-secondary">Trash is empty</h6>
                                        <p class="text-muted small mb-0">No deleted items found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trashedItems as $item): ?>
                                <tr>
                                    <td class="ps-4 text-secondary fw-medium" style="font-size: 14px;">
                                        <?= $item['id'] ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($item['type'] === 'file'): ?>
                                                <div class="rounded p-1 bg-light text-primary">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark" style="font-size: 14px; line-height: 1.2;">
                                                        <?= htmlspecialchars($item['title']) ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="rounded p-1 bg-light text-success">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark" style="font-size: 14px; line-height: 1.2;">
                                                        <?= htmlspecialchars($item['title']) ?>
                                                    </div>
                                                    <?php if (!empty($item['email'])): ?>
                                                        <div class="text-muted small" style="font-size: 11px;">
                                                            <?= htmlspecialchars($item['email']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-secondary" style="font-size: 14px;">
                                        <?= htmlspecialchars($item['deleted_by_name'] ?? 'Unknown') ?>
                                    </td>
                                    <td class="text-secondary" style="font-size: 14px;">
                                        <?= date('Y-m-d h:i A', strtotime($item['deleted_at'])) ?>
                                    </td>
                                    <td class="text-secondary" style="font-size: 14px;">
                                        <?= $item['type'] === 'file' ? formatFileSize($item['file_size'] ?? 0) : '-' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-link p-0 text-success"
                                                onclick="showRestoreModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'])) ?>', '<?= $item['type'] ?>')"
                                                title="Restore"
                                                style="width: 24px; height: 24px; border-radius: 4px; background-color: rgba(25, 135, 84, 0.1); display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                <i class="bi bi-arrow-counterclockwise" style="font-size: 14px;"></i>
                                            </button>
                                            <button type="button" class="btn btn-link p-0 text-danger"
                                                onclick="showDeleteModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'])) ?>', '<?= $item['type'] ?>')"
                                                title="Delete Permanently"
                                                style="width: 24px; height: 24px; border-radius: 4px; background-color: rgba(220, 53, 69, 0.1); display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                <i class="bi bi-trash-fill" style="font-size: 14px;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-4 py-3 d-flex justify-content-between align-items-center border-top">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary fw-bold" style="font-size: 11px;">ROWS:</span>
                        <select class="form-select form-select-sm border bg-light py-1 ps-2 pe-4"
                            onchange="window.location.href='?limit='+this.value+'&page=1'"
                            style="width: auto; cursor: pointer;">
                            <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                        </select>
                        <span class="text-muted small ms-2">Showing <?= count($trashedItems) ?> item(s)</span>
                    </div>
                    <div class="pagination-circular">
                        <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>"
                            class="page-link-circle <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <span class="mx-2 small text-muted">Page <?= $page ?></span>
                        <a href="?page=<?= min(max(1, ceil($totalItems / $limit)), $page + 1) ?>&limit=<?= $limit ?>"
                            class="page-link-circle <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Buttons -->
        <?php if (!empty($trashedItems)): ?>
            <div class="d-flex justify-content-end gap-3 mt-4">
                <button type="button" class="btn btn-success rounded-pill px-4 py-2 fw-medium shadow-sm border-0"
                    onclick="showRestoreAllModal()" style="background-color: #10B981;">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Restore All
                </button>
                <button type="button" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-medium shadow-sm bg-white"
                    onclick="showEmptyTrashModal()">
                    <i class="bi bi-trash-fill me-2"></i>Empty Trash
                </button>
            </div>
        <?php endif; ?>
    </main>

    <!-- Generic Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2">Success</h5>
                    <p class="text-muted small mb-4" id="successMessage">Operation completed successfully.</p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-success">
                        <i class="bi bi-arrow-counterclockwise display-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Restore Item?</h5>
                    <p class="text-muted small mb-4">Are you sure you want to restore "<span
                            id="restoreItemTitle"></span>"?</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" id="restoreItemId">
                        <input type="hidden" name="type" id="restoreItemType">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4"
                                style="background-color: #2E7D32;">Yes, Restore</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-exclamation-circle display-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Permanently?</h5>
                    <p class="text-muted small mb-4">This action cannot be undone. Are you sure you want to delete
                        "<span id="deleteItemTitle"></span>"?</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteItemId">
                        <input type="hidden" name="type" id="deleteItemType">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Yes, Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore All Modal -->
    <div class="modal fade" id="restoreAllModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-success">
                        <i class="bi bi-collection display-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Restore All Items?</h5>
                    <p class="text-muted small mb-4">This will restore all items currently in the trash.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="restore_all">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4"
                                style="background-color: #2E7D32;">Restore All</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Empty Trash Modal -->
    <div class="modal fade" id="emptyTrashModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-trash display-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Empty Trash?</h5>
                    <p class="text-muted small mb-4">This will permanently delete ALL items in the trash. This action
                        cannot be undone.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="empty_trash">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Yes, Empty Trash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRestoreModal(id, title, type) {
            document.getElementById('restoreItemId').value = id;
            document.getElementById('restoreItemType').value = type;
            document.getElementById('restoreItemTitle').textContent = title;
            new bootstrap.Modal(document.getElementById('restoreModal')).show();
        }

        function showDeleteModal(id, title, type) {
            document.getElementById('deleteItemId').value = id;
            document.getElementById('deleteItemType').value = type;
            document.getElementById('deleteItemTitle').textContent = title;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function showRestoreAllModal() {
            new bootstrap.Modal(document.getElementById('restoreAllModal')).show();
        }

        function showEmptyTrashModal() {
            new bootstrap.Modal(document.getElementById('emptyTrashModal')).show();
        }

        // Check for success params
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');

            if (success) {
                const modal = new bootstrap.Modal(document.getElementById('successModal'));
                const msgEl = document.getElementById('successMessage');

                switch (success) {
                    case 'restored': msgEl.textContent = 'Item restored successfully.'; break;
                    case 'deleted': msgEl.textContent = 'Item permanently deleted.'; break;
                    case 'restored_all': msgEl.textContent = 'All items restored successfully.'; break;
                    case 'emptied': msgEl.textContent = 'Trash emptied successfully.'; break;
                }

                modal.show();

                // Clean URL
                const newUrl = window.location.pathname + window.location.search.replace(/[\?&]success=[^&]+/, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</body>

</html>