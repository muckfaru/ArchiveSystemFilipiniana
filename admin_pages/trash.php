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
$fileParams = [];
$userParams = [];

// Apply Search
if ($search) {
    $fileWhere .= " AND n.title LIKE ?";
    $fileParams[] = "%$search%";
    
    $userWhere .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $userParams[] = "%$search%";
    $userParams[] = "%$search%";
}

// Apply Type Filter
if ($typeFilter === 'file') {
    $userWhere .= " AND 1=0";
} elseif ($typeFilter === 'user') {
    $fileWhere .= " AND 1=0";
}

// Apply Date
if ($dateFilter) {
    $fileWhere .= " AND DATE(n.deleted_at) = ?";
    $fileParams[] = $dateFilter;
    
    $userWhere .= " AND DATE(u.deleted_at) = ?";
    $userParams[] = $dateFilter;
}

// Merge params for UNION query
$params = array_merge($fileParams, $userParams);

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
    NULL as email,
    n.thumbnail_path as photo
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
    u.email as email,
    u.profile_photo as photo
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

    // --- RESTORE SELECTED ---
    if ($action === 'restore_selected') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        $restoredCount = 0;

        foreach ($items as $item) {
            $itemId = intval($item['id']);
            $itemType = $item['type'];

            if ($itemType === 'file') {
                $stmt = $pdo->prepare("UPDATE newspapers SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
                $stmt->execute([$itemId]);
                $restoredCount++;
            } elseif ($itemType === 'user') {
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
                $stmt->execute([$itemId]);
                $restoredCount++;
            }
        }

        logActivity($currentUser['id'], 'restore_selected', "Restored $restoredCount items");
        redirect($_SERVER['PHP_SELF'] . '?success=restored_selected&count=' . $restoredCount);
    }

    // --- DELETE SELECTED (Permanent) ---
    if ($action === 'delete_selected') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        $deletedCount = 0;

        foreach ($items as $item) {
            $itemId = intval($item['id']);
            $itemType = $item['type'];

            if ($itemType === 'file') {
                $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ?");
                $stmt->execute([$itemId]);
                $file = $stmt->fetch();
                if ($file) {
                    if (file_exists(UPLOAD_PATH . '../' . $file['file_path'])) unlink(UPLOAD_PATH . '../' . $file['file_path']);
                    if ($file['thumbnail_path'] && file_exists(UPLOAD_PATH . '../' . $file['thumbnail_path'])) unlink(UPLOAD_PATH . '../' . $file['thumbnail_path']);
                    $pdo->prepare("DELETE FROM newspapers WHERE id = ?")->execute([$itemId]);
                    $deletedCount++;
                }
            } elseif ($itemType === 'user') {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$itemId]);
                $deletedCount++;
            }
        }

        logActivity($currentUser['id'], 'permanent_delete_selected', "Permanently deleted $deletedCount items");
        redirect($_SERVER['PHP_SELF'] . '?success=deleted_selected&count=' . $deletedCount);
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
    <link href="<?= APP_URL ?>/assets/css/admin_pages/trash.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
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
            background: #3A9AFF;
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
            background: #2d87ef;
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
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
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
            background: #3A9AFF;
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

        mark.pub-hl {
            background: #FEF08A;
            color: inherit;
            padding: 0 2px;
            border-radius: 2px;
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
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../views/layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="mb-4">
            <h1 class="fw-bold m-0" style="font-size: 24px; color: #212529; font-family: 'Poppins', sans-serif;">
                Trash Repository</h1>
            <div class="text-muted small">Recover or permanently remove deleted files and user accounts</div>
        </div>

        <!-- Auto-Delete Info Card -->
        <div class="card border-0 mb-4 bg-warning-subtle shadow-sm" style="border-radius: 12px; border: 1px solid rgba(185, 28, 28, 0.1) !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px; color: #b91c1c; border: 1px solid rgba(185, 28, 28, 0.1);">
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

        <!-- Search & Filter Bar -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mb-4">
            <!-- Search Bar -->
            <div class="flex-grow-1">
                <form method="GET" id="searchForm" class="search-bar-custom">
                    <i class="bi bi-search text-muted fs-5 ms-1"></i>
                    <input type="text" class="search-input-custom" name="search" id="searchInput"
                        placeholder="Search deleted documents or users..." value="<?= htmlspecialchars($search) ?>">
                    <button class="search-btn-custom" type="submit">
                        <i class="bi bi-search" style="font-size: 14px;"></i>
                    </button>
                    <!-- Keep hidden inputs for filters -->
                    <?php if ($typeFilter): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
                    <?php if ($sortBy): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
                    <?php if ($limit !== 4): ?><input type="hidden" name="limit" value="<?= htmlspecialchars($limit) ?>"><?php endif; ?>
                </form>
            </div>

            <!-- Filters -->
            <div class="d-flex gap-2">
                <!-- Type Filter Dropdown -->
                <div class="dropdown">
                    <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $typeFilter === 'file' ? 'Files' : ($typeFilter === 'user' ? 'Users' : 'All Types') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm" style="font-size: 13px;">
                        <li><a class="dropdown-item type-filter-item <?= empty($typeFilter) ? 'active' : '' ?>" href="?type=&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">All Types</a></li>
                        <li><a class="dropdown-item type-filter-item <?= $typeFilter === 'file' ? 'active' : '' ?>" href="?type=file&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Files Only</a></li>
                        <li><a class="dropdown-item type-filter-item <?= $typeFilter === 'user' ? 'active' : '' ?>" href="?type=user&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Users Only</a></li>
                    </ul>
                </div>

                <!-- Sort By -->
                <div class="dropdown">
                    <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $sortBy === 'oldest' ? 'Oldest First' : 'Newest First' ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm" style="font-size: 13px;">
                        <li><a class="dropdown-item <?= $sortBy === 'newest' ? 'active' : '' ?>" href="?sort=newest&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>&limit=<?= $limit ?>">Newest First</a></li>
                        <li><a class="dropdown-item <?= $sortBy === 'oldest' ? 'active' : '' ?>" href="?sort=oldest&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>&limit=<?= $limit ?>">Oldest First</a></li>
                    </ul>
                </div>
                
                <!-- Action Buttons: Restore All & Empty -->
                <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-medium shadow-sm d-flex align-items-center gap-2" style="font-size: 13px;" onclick="showRestoreAllModal()">
                    <i class="bi bi-arrow-counterclockwise"></i> Restore All
                </button>
                <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-medium shadow-sm d-flex align-items-center gap-2" style="font-size: 13px;" onclick="showEmptyTrashModal()">
                    <i class="bi bi-trash-fill"></i> Empty
                </button>
            </div>
        </div>

        <!-- Unified Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table trash-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th class="ps-4 py-3">
                                <input type="checkbox" id="selectAll" class="form-check-input" style="cursor: pointer;">
                            </th>
                            <th class="py-3 text-uppercase text-secondary" style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">ID</th>
                            <th class="py-3 text-uppercase text-secondary" style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Item Name</th>
                            <th class="py-3 text-uppercase text-secondary" style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Deleted By</th>
                            <th class="py-3 text-uppercase text-secondary" style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Date Deleted</th>
                            <th class="py-3 text-uppercase text-secondary" style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Size</th>
                            <th class="text-end pe-4 py-3 text-uppercase text-secondary" style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trashedItems)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center text-muted py-4">
                                        <i class="bi bi-trash fs-1 mb-3 opacity-25"></i>
                                        <h6 class="fw-bold">Trash is empty</h6>
                                        <p class="small">No deleted items to display.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trashedItems as $item): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <input type="checkbox" class="form-check-input item-checkbox shadow-none"
                                            data-id="<?= $item['id'] ?>" data-type="<?= $item['type'] ?>"
                                            style="cursor: pointer;">
                                    </td>
                                    <td class="py-3 text-muted" style="font-family: monospace; font-size: 14px; font-weight: 500;">
                                        #<?= $item['id'] ?>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="trash-type-icon d-flex align-items-center justify-content-center rounded-3 bg-light overflow-hidden" style="width: 36px; height: 36px; color: #6B7280;">
                                                <?php if (!empty($item['photo'])): ?>
                                                    <img src="<?= APP_URL ?>/<?= htmlspecialchars($item['photo']) ?>" alt="Thumbnail" class="trash-item-photo" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="bi <?= $item['type'] === 'file' ? 'bi-file-earmark-text' : 'bi-person' ?> fs-5"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="trash-item-name">
                                                    <?php
                                                    $title = !empty($item['title']) ? $item['title'] : ($item['email'] ?? 'Unnamed');
                                                    if ($search) {
                                                        echo preg_replace('/(' . preg_quote(htmlspecialchars($search), '/') . ')/i', '<mark class="pub-hl">$1</mark>', htmlspecialchars($title));
                                                    } else {
                                                        echo htmlspecialchars($title);
                                                    }
                                                    ?>
                                                </div>
                                                <div class="trash-item-meta">
                                                    <?= strtoupper($item['type']) ?>
                                                    <?php if ($item['type'] === 'user' && !empty($item['email'])): ?>
                                                        • <?= htmlspecialchars($item['email']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-dark" style="font-size: 14px;"><?= htmlspecialchars($item['deleted_by_name'] ?? 'Unknown Admin') ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-muted" style="font-size: 13px;">
                                        <?= date('d M Y, H:i', strtotime($item['deleted_at'])) ?>
                                    </td>
                                    <td class="py-3 text-muted" style="font-size: 13px;">
                                        <?= $item['type'] === 'file' ? formatFileSize($item['file_size'] ?? 0) : '—' ?>
                                    </td>
                                    <td class="text-end pe-4 py-3">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn-trash-action btn-restore"
                                                onclick="showRestoreModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'] ?? ($item['email'] ?? ''))) ?>', '<?= $item['type'] ?>')"
                                                title="Restore">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <button type="button" class="btn-trash-action btn-delete-perm"
                                                onclick="showDeleteModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'] ?? ($item['email'] ?? ''))) ?>', '<?= $item['type'] ?>')"
                                                title="Delete Permanently">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-4 d-flex justify-content-between align-items-center border-top">
                <div class="d-flex align-items-center gap-3">
                    <span class="text-secondary small fw-medium">Rows per page:</span>
                    <select class="rows-per-page-select" id="rowsPerPage">
                        <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                        <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    </select>
                    <span class="text-secondary small ms-2">
                        Showing <?= ($offset + 1) ?>-<?= min($offset + $limit, $totalItems) ?> of <?= $totalItems ?> items
                    </span>
                </div>

                <div class="d-flex align-items-center gap-1">
                    <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>&type=<?= $typeFilter ?>&sort=<?= $sortBy ?>&search=<?= urlencode($search) ?>"
                        class="pagination-circle <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>

                    <?php
                    $totalPages = ceil($totalItems / $limit);
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?= $i ?>&limit=<?= $limit ?>&type=<?= $typeFilter ?>&sort=<?= $sortBy ?>&search=<?= urlencode($search) ?>"
                            class="pagination-circle <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <a href="?page=<?= min($totalPages, $page + 1) ?>&limit=<?= $limit ?>&type=<?= $typeFilter ?>&sort=<?= $sortBy ?>&search=<?= urlencode($search) ?>"
                        class="pagination-circle <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Selection Banner -->
        <div id="selectionBanner" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 bg-dark text-white rounded-pill px-4 py-3 shadow-lg d-flex align-items-center gap-4" style="display: none !important; z-index: 1050; min-width: 400px; border: 1px solid rgba(255,255,255,0.1);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-circle p-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-check-lg"></i>
                </span>
                <span class="fw-semibold"><span id="selectedDisplayCount">0</span> items selected</span>
            </div>
            <div class="vr opacity-25" style="height: 24px;"></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium" onclick="showRestoreSelectedModal()">
                    Restore
                </button>
                <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 fw-medium" onclick="showDeleteSelectedModal()">
                    Delete
                </button>
                <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-medium" id="cancelSelection">
                    Cancel
                </button>
            </div>
        </div>

    </main>

    <!-- Modals (Redesigned) -->

    <!-- Generic Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-standard">
            <div class="modal-content modal-minimalist shadow-lg border-0">
                <div class="modal-header border-0 pb-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-4 shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div id="modalIconHeader" class="modal-icon mx-auto">
                        <i id="modalIcon" class="bi"></i>
                    </div>
                </div>
                <div class="modal-body text-center px-4">
                    <h5 class="modal-title fw-bold mb-3" id="modalTitle">Confirm Action</h5>
                    <p class="text-muted" id="modalMessage"></p>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4 pt-0 gap-3">
                    <button type="button" class="btn btn-cancel-minimal" data-bs-dismiss="modal">Cancel</button>
                    <form id="modalForm" method="POST" class="d-inline">
                        <input type="hidden" name="action" id="modalAction">
                        <input type="hidden" name="id" id="modalItemId">
                        <input type="hidden" name="type" id="modalItemType">
                        <input type="hidden" name="items" id="modalItemsJson">
                        <button type="submit" id="modalSubmitBtn" class="btn">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm-standard">
            <div class="modal-content modal-minimalist shadow-lg border-0">
                <div class="modal-body text-center p-4">
                    <div class="modal-icon icon-success mx-auto mb-3">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Success!</h5>
                    <p class="text-muted small mb-4" id="successMessage">Operation completed successfully.</p>
                    <button type="button" class="btn btn-dark rounded-pill px-4 w-100" data-bs-dismiss="modal">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../views/layouts/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Rows per page
            const rowsSelect = document.getElementById('rowsPerPage');
            if (rowsSelect) {
                rowsSelect.addEventListener('change', function() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('limit', this.value);
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                });
            }

            // Selection Logic
            const selectAll = document.getElementById('selectAll');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const selectionBanner = document.getElementById('selectionBanner');
            const selectedDisplayCount = document.getElementById('selectedDisplayCount');
            const cancelSelection = document.getElementById('cancelSelection');

            function updateSelectionUI() {
                const checked = document.querySelectorAll('.item-checkbox:checked');
                const count = checked.length;
                
                if (count > 0) {
                    selectedDisplayCount.textContent = count;
                    selectionBanner.style.setProperty('display', 'flex', 'important');
                } else {
                    selectionBanner.style.setProperty('display', 'none', 'important');
                }
                
                if (selectAll) {
                    const allChecked = Array.from(itemCheckboxes).every(checkbox => checkbox.checked);
                    const someChecked = Array.from(itemCheckboxes).some(checkbox => checkbox.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    itemCheckboxes.forEach(cb => cb.checked = this.checked);
                    updateSelectionUI();
                });
            }

            itemCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectionUI);
            });

            if (cancelSelection) {
                cancelSelection.addEventListener('click', function() {
                    itemCheckboxes.forEach(cb => cb.checked = false);
                    if (selectAll) {
                        selectAll.checked = false;
                        selectAll.indeterminate = false;
                    }
                    updateSelectionUI();
                });
            }

            // Modals Logic
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalAction = document.getElementById('modalAction');
            const modalItemId = document.getElementById('modalItemId');
            const modalItemType = document.getElementById('modalItemType');
            const modalSubmitBtn = document.getElementById('modalSubmitBtn');
            const modalIcon = document.getElementById('modalIcon');
            const modalIconHeader = document.getElementById('modalIconHeader');

            window.showRestoreModal = function(id, title, type) {
                modalTitle.textContent = 'Restore Item?';
                modalMessage.innerHTML = `Are you sure you want to restore <strong>"${title}"</strong>? It will be moved back to the active repository.`;
                modalAction.value = 'restore';
                modalItemId.value = id;
                modalItemType.value = type;
                modalSubmitBtn.className = 'btn btn-restore-confirm px-4 w-100';
                modalSubmitBtn.textContent = 'Restore Item';
                modalIcon.className = 'bi bi-arrow-clockwise';
                modalIconHeader.className = 'modal-icon icon-primary mx-auto';
                confirmModal.show();
            };

            window.showDeleteModal = function(id, title, type) {
                modalTitle.textContent = 'Permanent Delete?';
                modalMessage.innerHTML = `Confirm permanent deletion of <strong>"${title}"</strong>? This action <span class="text-danger fw-bold">CANNOT</span> be undone.`;
                modalAction.value = 'delete';
                modalItemId.value = id;
                modalItemType.value = type;
                modalSubmitBtn.className = 'btn btn-delete-confirm px-4 w-100';
                modalSubmitBtn.textContent = 'Delete Permanently';
                modalIcon.className = 'bi bi-trash3';
                modalIconHeader.className = 'modal-icon icon-danger mx-auto';
                confirmModal.show();
            };

            window.showRestoreAllModal = function() {
                modalTitle.textContent = 'Restore Everything?';
                modalMessage.innerHTML = 'Are you sure you want to restore <span class="fw-bold">ALL</span> items in the trash? They will be returned to their original locations.';
                modalAction.value = 'restore_all';
                modalItemId.value = '';
                modalItemType.value = '';
                modalSubmitBtn.className = 'btn btn-restore-confirm px-4 w-100';
                modalSubmitBtn.textContent = 'Restore All';
                modalIcon.className = 'bi bi-arrow-clockwise';
                modalIconHeader.className = 'modal-icon icon-primary mx-auto';
                confirmModal.show();
            };

            window.showEmptyTrashModal = function() {
                modalTitle.textContent = 'Empty Trash?';
                modalMessage.innerHTML = 'Are you sure you want to permanently delete <span class="fw-bold">ALL</span> items in the trash? This action <span class="text-danger fw-bold">CANNOT</span> be undone.';
                modalAction.value = 'empty_trash';
                modalItemId.value = '';
                modalItemType.value = '';
                modalSubmitBtn.className = 'btn btn-delete-confirm px-4 w-100';
                modalSubmitBtn.textContent = 'Empty Trash Now';
                modalIcon.className = 'bi bi-trash3';
                modalIconHeader.className = 'modal-icon icon-danger mx-auto';
                confirmModal.show();
            };

            window.showRestoreSelectedModal = function() {
                const checked = document.querySelectorAll('.item-checkbox:checked');
                const items = Array.from(checked).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type }));
                
                modalTitle.textContent = 'Restore Selected?';
                modalMessage.innerHTML = `Are you sure you want to restore the <span class="fw-bold">${items.length}</span> selected items?`;
                modalAction.value = 'restore_selected';
                document.getElementById('modalItemsJson').value = JSON.stringify(items);
                modalSubmitBtn.className = 'btn btn-restore-confirm px-4 w-100';
                modalSubmitBtn.textContent = 'Restore Selected';
                modalIcon.className = 'bi bi-arrow-clockwise';
                modalIconHeader.className = 'modal-icon icon-primary mx-auto';
                confirmModal.show();
            };

            window.showDeleteSelectedModal = function() {
                const checked = document.querySelectorAll('.item-checkbox:checked');
                const items = Array.from(checked).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type }));
                
                modalTitle.textContent = 'Delete Selected?';
                modalMessage.innerHTML = `Confirm permanent deletion of the <span class="fw-bold">${items.length}</span> selected items? This action <span class="text-danger fw-bold">CANNOT</span> be undone.`;
                modalAction.value = 'delete_selected';
                document.getElementById('modalItemsJson').value = JSON.stringify(items);
                modalSubmitBtn.className = 'btn btn-delete-confirm px-4 w-100';
                modalSubmitBtn.textContent = 'Delete Permanently';
                modalIcon.className = 'bi bi-trash3';
                modalIconHeader.className = 'modal-icon icon-danger mx-auto';
                confirmModal.show();
            };

            // Success handling via URL params
            const successParam = new URLSearchParams(window.location.search).get('success');
            if (successParam) {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                const successMsg = document.getElementById('successMessage');
                
                const messages = {
                    'restored': 'The item has been restored successfully.',
                    'deleted': 'The item was permanently removed.',
                    'restored_all': 'All items have been restored.',
                    'emptied': 'Trash repository has been cleared.',
                    'restored_selected': 'Selected items have been restored.',
                    'deleted_selected': 'Selected items were permanently deleted.'
                };
                
                if (messages[successParam]) {
                    successMsg.textContent = messages[successParam];
                    successModal.show();
                    // Clean URL
                    const url = new URL(window.location.href);
                    url.searchParams.delete('success');
                    url.searchParams.delete('count');
                    window.history.replaceState({}, document.title, url.toString());
                }
            }

            // Live Search with Debounce
            let searchTimer;
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        this.closest('form').submit();
                    }, 600);
                });
                
                if (searchInput.value) {
                    searchInput.focus();
                    const len = searchInput.value.length;
                    searchInput.setSelectionRange(len, len);
                }
            }

            // Filter helpers
            window.updateFilter = function(name, value) {
                const url = new URL(window.location.href);
                url.searchParams.set(name, value);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            };
        });
    </script>
</body>

</html>

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

    <!-- Restore Selected Modal -->
    <div class="modal fade" id="restoreSelectedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-primary">
                        <i class="bi bi-check2-square display-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Restore Selected Items?</h5>
                    <p class="text-muted small mb-4">This will restore <span id="modalSelectedCount">0</span> selected
                        item(s).</p>
                    <form method="POST" id="restoreSelectedForm">
                        <input type="hidden" name="action" value="restore_selected">
                        <input type="hidden" name="items" id="selectedItemsInput">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4"
                                style="background-color: #3A9AFF; border-color: #3A9AFF;">Restore Selected</button>
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

        function showRestoreSelectedModal() {
            const selectedItems = getSelectedItems();
            document.getElementById('modalSelectedCount').textContent = selectedItems.length;
            document.getElementById('selectedItemsInput').value = JSON.stringify(selectedItems);
            new bootstrap.Modal(document.getElementById('restoreSelectedModal')).show();
        }

        function getSelectedItems() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            const items = [];
            checkboxes.forEach(cb => {
                items.push({
                    id: cb.dataset.id,
                    type: cb.dataset.type
                });
            });
            return items;
        }

        function updateRestoreSelectedButton() {
            const selectedItems = getSelectedItems();
            const btn = document.getElementById('restoreSelectedBtn');
            const countSpan = document.getElementById('selectedCount');

            if (selectedItems.length > 0) {
                btn.style.display = 'block';
                countSpan.textContent = selectedItems.length;
            } else {
                btn.style.display = 'none';
            }
        }

        // Live Search & Filters
        document.addEventListener('DOMContentLoaded', function () {
            // Select All checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    itemCheckboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateRestoreSelectedButton();
                });
            }

            // Individual checkboxes
            itemCheckboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    // Update select all checkbox state
                    const allChecked = Array.from(itemCheckboxes).every(checkbox => checkbox.checked);
                    const someChecked = Array.from(itemCheckboxes).some(checkbox => checkbox.checked);

                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                    }

                    updateRestoreSelectedButton();
                });
            });

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
                    case 'restored_selected':
                        const count = urlParams.get('count') || '0';
                        msgEl.textContent = `${count} item(s) restored successfully.`;
                        break;
                }

                modal.show();

                // Clean URL
                const newUrl = window.location.pathname + window.location.search.replace(/[\?&]success=[^&]+/, '').replace(/[\?&]count=[^&]+/, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</body>

</html>