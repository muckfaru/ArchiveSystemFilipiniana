<?php
/**
 * Trash Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';

// Get alert message
// Get alert message
// $alert = getAlert();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
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

// Apply Type Filter
if ($typeFilter === 'file') {
    $userWhere .= " AND 1=0"; // Exclude users
} elseif ($typeFilter === 'user') {
    $fileWhere .= " AND 1=0"; // Exclude files
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

// Get categories for filter (Removed as requested: Files and Users only)
// $categories = getCategories();

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
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Poppins', sans-serif;
        }

        .search-bar-custom {
            background: #fff;
            border-radius: 50px;
            padding: 4px 4px 4px 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            width: 100%;
        }

        .search-input-custom {
            border: none;
            background: transparent;
            font-size: 14px;
            color: #666;
            width: 100%;
            padding: 8px;
        }

        .search-input-custom:focus {
            outline: none;
        }

        .search-btn-custom {
            background: #4C3939;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .search-btn-custom:hover {
            background: #3A2B2B;
        }

        .filter-pill {
            background: #fff;
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #4B5563;
            display: flex;
            align-items: center;
            gap: 8px;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            overflow: visible;
            /* Ensure dropdowns are not clipped */
        }



        .clear-date-icon {
            cursor: pointer;
            transition: all 0.2s;
            opacity: 0.6;
        }

        .clear-date-icon:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .filter-pill:hover {
            background: #F9FAFB;
            transform: translateY(-1px);
            color: #4B5563;
        }

        .trash-table th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #9CA3AF;
            border-bottom: none;
            padding: 20px 24px;
            letter-spacing: 0.5px;
        }

        .trash-table td {
            vertical-align: middle;
            padding: 20px 24px;
            border-bottom: 1px solid #F3F4F6;
            color: #374151;
            font-size: 14px;
            background: #fff;
        }

        .trash-table tr:first-child td {
            border-top: 1px solid #F3F4F6;
        }

        .trash-table tr:last-child td {
            border-bottom: none;
        }

        .trash-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: none;
            background: transparent;
        }

        .trash-action-btn.restore {
            color: #3B82F6;
        }

        .trash-action-btn.restore:hover {
            background: #EFF6FF;
        }

        .trash-action-btn.delete {
            color: #EF4444;
        }

        .trash-action-btn.delete:hover {
            background: #FEF2F2;
        }

        .pagination-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 500;
            color: #6B7280;
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination-circle:hover {
            background: #F3F4F6;
            color: #374151;
        }

        .pagination-circle.active {
            background: #4C3939;
            color: #fff;
        }

        .pagination-circle.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .rows-per-page-select {
            background-color: #F3F4F6;
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
        }

        .btn-restore-all {
            background-color: #2563EB;
            color: white;
            border: none;
        }

        .btn-restore-all:hover {
            background-color: #1D4ED8;
        }

        .btn-empty-trash {
            background-color: #DC2626;
            color: white;
            border: none;
        }

        .btn-empty-trash:hover {
            background-color: #B91C1C;
        }
    </style>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../views/layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="mb-4">
            <h1 class="fw-bold m-0" style="font-size: 32px; color: #3D2D2D; font-family: 'Poppins', sans-serif;">
                Trash</h1>
            <div class="text-muted" style="font-size: 14px;">Recover or permanently remove content</div>
        </div>

        <!-- Auto-Delete Info Card -->
        <div class="card border-0 mb-4 bg-warning-subtle shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px; color: #b91c1c;">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 14px;">Auto-Deletion Policy</div>
                        <div class="text-muted small">Items in trash are automatically permanently deleted after
                            <?= $autoDeleteDays ?> days.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts replaced by Modals -->

        <!-- Search & Filter Bar -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mb-4">
            <!-- Search Bar -->
            <div class="flex-grow-1">
                <form method="GET" id="searchForm" class="search-bar-custom">
                    <i class="bi bi-search text-muted fs-5 ms-1"></i>
                    <input type="text" class="search-input-custom" name="search"
                        placeholder="Search deleted documents..." value="<?= htmlspecialchars($search) ?>">
                    <button class="search-btn-custom" type="submit">
                        <i class="bi bi-search" style="font-size: 14px;"></i>
                    </button>
                    <!-- Preserving other filters in search -->
                    <?php if ($typeFilter): ?><input type="hidden" name="type"
                            value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
                    <?php if ($dateFilter): ?><input type="hidden" name="date"
                            value="<?= htmlspecialchars($dateFilter) ?>"><?php endif; ?>
                    <?php if ($sortBy): ?><input type="hidden" name="sort"
                            value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
                    <?php if ($limit !== 4): ?><input type="hidden" name="limit"
                            value="<?= htmlspecialchars($limit) ?>"><?php endif; ?>
                </form>
            </div>

            <!-- Filters -->
            <div class="d-flex gap-2">
                <!-- Type Filter Dropdown (Replaces Categories) -->
                <div class="dropdown">
                    <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $typeFilter === 'file' ? 'Files' : ($typeFilter === 'user' ? 'Users' : 'All Types') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm"
                        style="font-size: 13px; z-index: 1055;">
                        <li><a class="dropdown-item type-filter-item <?= empty($typeFilter) ? 'active' : '' ?>" href="#"
                                data-value="">All Types</a></li>
                        <li><a class="dropdown-item type-filter-item <?= $typeFilter === 'file' ? 'active' : '' ?>"
                                href="#" data-value="file">Files Only</a></li>
                        <li><a class="dropdown-item type-filter-item <?= $typeFilter === 'user' ? 'active' : '' ?>"
                                href="#" data-value="user">Users Only</a></li>
                    </ul>
                </div>

                <!-- Date Filter -->
                <div class="dropdown">
                    <button class="filter-pill <?= $dateFilter ? 'text-primary' : 'dropdown-toggle' ?>" type="button"
                        data-bs-toggle="dropdown">
                        <?= $dateFilter ? date('M d, Y', strtotime($dateFilter)) : 'Date' ?>
                        <?php if ($dateFilter): ?>
                            <i class="bi bi-x-lg ms-2 text-danger clear-date-icon" role="button"
                                style="font-size: 12px;"></i>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end border-0 shadow-sm p-3" style="width: 250px;">
                        <label class="form-label small text-muted">Filter by Date</label>
                        <input type="date" class="form-control form-control-sm mb-2" id="dateFilterInput"
                            value="<?= htmlspecialchars($dateFilter) ?>">
                        <!-- Removed clear button inside dropdown as requested -->
                    </div>
                </div>

                <!-- Sort By -->
                <div class="dropdown">
                    <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $sortBy === 'oldest' ? 'Oldest First' : 'Newest First' ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm"
                        style="font-size: 13px; z-index: 1055;">
                        <li><a class="dropdown-item sort-filter-item <?= $sortBy === 'newest' ? 'active' : '' ?>"
                                href="#" data-value="newest">Newest First</a></li>
                        <li><a class="dropdown-item sort-filter-item <?= $sortBy === 'oldest' ? 'active' : '' ?>"
                                href="#" data-value="oldest">Oldest First</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Unified Trash Table -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-0">
                <table class="table trash-table mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4" style="width: 5%;">ID</th>
                            <th style="width: 35%;">TITLE</th>
                            <th style="width: 20%;">DELETED BY</th>
                            <th style="width: 20%;">DATE</th>
                            <th style="width: 10%;">SIZE</th>
                            <th class="text-end pe-4" style="width: 10%;">ACTIONS</th>
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
                                    <td class="ps-4 text-muted" style="font-family: monospace;">
                                        #<?= $item['id'] ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if ($item['type'] === 'file'): ?>
                                                <i class="bi bi-file-earmark-text text-secondary opacity-75 fs-5"></i>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($item['title']) ?>
                                                </div>
                                            <?php else: ?>
                                                <i class="bi bi-person text-secondary opacity-75 fs-5"></i>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($item['title']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($item['deleted_by_name'] ?? 'Unknown') ?>
                                        </div>
                                    </td>
                                    <td class="text-secondary">
                                        <?= date('M d, Y, H:i', strtotime($item['deleted_at'])) ?>
                                    </td>
                                    <td class="text-secondary">
                                        <?= $item['type'] === 'file' ? formatFileSize($item['file_size'] ?? 0) : '-' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="trash-action-btn restore"
                                                onclick="showRestoreModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'])) ?>', '<?= $item['type'] ?>')"
                                                title="Restore">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <button type="button" class="trash-action-btn delete"
                                                onclick="showDeleteModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'])) ?>', '<?= $item['type'] ?>')"
                                                title="Delete Permanently">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-4 py-4 d-flex justify-content-between align-items-center">
                    <!-- Limit Selector and Showing Text -->
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-secondary small fw-medium">Rows per page:</span>
                        <select class="rows-per-page-select" id="rowsPerPage">
                            <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                        </select>

                        <span class="text-secondary small ms-2">
                            Showing <?= ($offset + 1) ?>-<?= min($offset + $limit, $totalItems) ?> of <?= $totalItems ?>
                            documents
                        </span>
                    </div>

                    <!-- Circular Pagination Controls -->
                    <div class="d-flex align-items-center gap-1">
                        <!-- Previous Page -->
                        <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>"
                            class="pagination-circle <?= $page <= 1 ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min(ceil($totalItems / $limit), $page + 2);

                        for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <a href="?page=<?= $i ?>&limit=<?= $limit ?>"
                                class="pagination-circle <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if (ceil($totalItems / $limit) > $endPage): ?>
                            <span class="text-muted small px-1">...</span>
                            <a href="?page=<?= ceil($totalItems / $limit) ?>&limit=<?= $limit ?>" class="pagination-circle">
                                <?= ceil($totalItems / $limit) ?>
                            </a>
                        <?php endif; ?>

                        <!-- Next Page -->
                        <a href="?page=<?= min(max(1, ceil($totalItems / $limit)), $page + 1) ?>&limit=<?= $limit ?>"
                            class="pagination-circle <?= $page >= ceil($totalItems / $limit) ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Buttons -->
        <?php if (!empty($trashedItems)): ?>
            <div class="d-flex justify-content-end gap-3 mt-4">
                <button type="button" class="btn btn-restore-all rounded-3 px-4 py-2 fw-medium shadow-sm"
                    onclick="showRestoreAllModal()">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Restore All
                </button>
                <button type="button" class="btn btn-empty-trash rounded-3 px-4 py-2 fw-medium shadow-sm"
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
                    <div class="mb-3 text-primary">
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
                            <button type="submit" class="btn btn-primary rounded-pill px-4"
                                style="background-color: #3B82F6; border-color: #3B82F6;">Yes, Restore</button>
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
                    <div class="mb-3 text-primary">
                        <i class="bi bi-collection display-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Restore All Items?</h5>
                    <p class="text-muted small mb-4">This will restore all items currently in the trash.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="restore_all">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4"
                                style="background-color: #2563EB; border-color: #2563EB;">Restore All</button>
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

    <?php include __DIR__ . '/../views/layouts/footer.php'; ?>

    <!-- Custom Scripts -->
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

        // Live Search & Filters
        document.addEventListener('DOMContentLoaded', function () {
            // Helper to update filter
            function updateFilter(name, value) {
                const form = document.getElementById('searchForm');
                let input = form.querySelector(`input[name="${name}"]`);
                if (input) {
                    input.value = value;
                    form.submit();
                } else {
                    const newInput = document.createElement('input');
                    newInput.type = 'hidden';
                    newInput.name = name;
                    newInput.value = value;
                    form.appendChild(newInput);
                    form.submit();
                }
            }

            // Type Items
            document.querySelectorAll('.type-filter-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    updateFilter('type', this.dataset.value);
                });
            });

            // Sort Items
            document.querySelectorAll('.sort-filter-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    updateFilter('sort', this.dataset.value);
                });
            });

            // Clear Date Icon
            document.querySelectorAll('.clear-date-icon').forEach(icon => {
                icon.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent dropdown toggle
                    updateFilter('date', '');
                });
            });

            // Rows per page
            const rowsPerPage = document.getElementById('rowsPerPage');
            if (rowsPerPage) {
                rowsPerPage.addEventListener('change', function () {
                    updateFilter('limit', this.value);
                });
            }

            // Date Filter
            const dateInput = document.getElementById('dateFilterInput');
            if (dateInput) {
                dateInput.addEventListener('change', function () {
                    updateFilter('date', this.value);
                });
            }

            // Live Search
            let debounceTimer;
            const searchInput = document.getElementById('searchForm').querySelector('input[name="search"]');
            const searchForm = document.getElementById('searchForm');

            if (searchInput && searchForm) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        searchForm.submit();
                    }, 600); // 600ms debounce
                });

                // Focus search input if it has value (after reload)
                if (searchInput.value) {
                    searchInput.focus();
                    const len = searchInput.value.length;
                    searchInput.setSelectionRange(len, len);
                }
            }
        });

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