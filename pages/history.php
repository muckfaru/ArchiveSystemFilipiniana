<?php
/**
 * History / Activity Logs Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';

// Get alert message
// $alert = getAlert();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (a.id LIKE ? OR u.username LIKE ? OR a.target_title LIKE ? OR a.action LIKE ? OR a.created_at LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $whereClause .= " AND a.action = ?";
    $params[] = $categoryFilter;
}

if ($roleFilter) {
    $whereClause .= " AND u.role = ?";
    $params[] = $roleFilter;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM activity_logs a JOIN users u ON a.user_id = u.id $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalLogs = $countStmt->fetch()['total'];

// Get pagination data
$pagination = getPagination($totalLogs, $page, $limit);
$totalPages = ceil($totalLogs / $limit);

// Sort order
$orderBy = $sortBy === 'oldest' ? 'a.created_at ASC' : 'a.created_at DESC';

// Get logs
$sql = "SELECT a.*, u.username, u.full_name, u.role FROM activity_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $pagination['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Handle CSV export
// Handle CSV export
// Handle CSV export
// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'activity_logs_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['User', 'Action', 'Title', 'Timestamp']);

    // Use the already built $whereClause
    // We need to clean the $params array because LIMIT and OFFSET were appended to it for the pagination query
    $exportParams = array_slice($params, 0, -2);

    $exportSql = "SELECT a.*, u.username FROM activity_logs a JOIN users u ON a.user_id = u.id $whereClause ORDER BY $orderBy";
    $stmt = $pdo->prepare($exportSql);
    $stmt->execute($exportParams);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['username'],
            ucwords(str_replace('_', ' ', $row['action'])),
            $row['target_title'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// Action labels and colors
function getActionClass($action)
{
    $classes = [
        'create_user' => 'action-create',
        'edit_user' => 'action-edit',
        'delete_user' => 'action-delete',
        'upload' => 'action-create',
        'edit' => 'action-edit',
        'delete' => 'action-delete',
        'restore' => 'action-create',
        'permanent_delete' => 'action-delete',
        'login' => 'action-edit',
        'logout' => 'action-edit',
        'settings_update' => 'action-edit'
    ];
    return $classes[$action] ?? '';
}

function getActionLabel($action)
{
    $labels = [
        'create_user' => 'Create User',
        'edit_user' => 'Edited',
        'delete_user' => 'Delete',
        'upload' => 'Create',
        'edit' => 'Edited',
        'delete' => 'Delete',
        'restore' => 'Create',
        'permanent_delete' => 'Delete',
        'login' => 'Login',
        'logout' => 'Logout',
        'settings_update' => 'Edited'
    ];
    return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History -
        <?= APP_NAME ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/pages/history.css" rel="stylesheet">
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

        .history-table th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #9CA3AF;
            border-bottom: none;
            padding: 20px 24px;
            letter-spacing: 0.5px;
        }

        .history-table td {
            vertical-align: middle;
            padding: 20px 24px;
            border-bottom: 1px solid #F3F4F6;
            color: #374151;
            font-size: 14px;
            background: #fff;
        }

        .history-table tr:first-child td {
            border-top: 1px solid #F3F4F6;
        }

        .history-table tr:last-child td {
            border-bottom: none;
        }

        .user-avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #4B5563;
            margin-right: 12px;
        }

        .action-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .badge-login {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .badge-logout {
            background: #F3F4F6;
            color: #4B5563;
        }

        .badge-create {
            background: #DCFCE7;
            color: #16A34A;
        }

        .badge-delete {
            background: #FEE2E2;
            color: #DC2626;
        }

        .badge-update {
            background: #FEF3C7;
            color: #D97706;
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
    </style>
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../views/layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold m-0" style="font-size: 24px; color: #212529;">History Logs</h1>
                <div class="text-muted small">Monitor and review all system activities and administrative actions</div>
            </div>
            <!-- Export Button -->
            <a href="?export=csv&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&role=<?= urlencode($roleFilter) ?>&sort=<?= urlencode($sortBy) ?>"
                class="btn btn-primary px-4 py-2"
                style="background-color: #4C3939; border-color: #4C3939; font-weight: 500;">
                <i class="bi bi-download me-2"></i>Export CSV
            </a>
        </div>



        <!-- Search & Filter Bar -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mb-4">
            <!-- Search Bar -->
            <div class="flex-grow-1">
                <form method="GET" id="searchForm" class="search-bar-custom">
                    <i class="bi bi-search text-muted fs-5 ms-1"></i>
                    <input type="text" class="search-input-custom" name="search" id="searchInput"
                        placeholder="Search logs by title, user, or action..." value="<?= htmlspecialchars($search) ?>">
                    <button class="search-btn-custom" type="submit">
                        <i class="bi bi-search" style="font-size: 14px;"></i>
                    </button>
                    <!-- Keep hidden inputs for filters -->
                    <?php if ($categoryFilter): ?><input type="hidden" name="category"
                            value="<?= htmlspecialchars($categoryFilter) ?>"><?php endif; ?>
                    <?php if ($roleFilter): ?><input type="hidden" name="role"
                            value="<?= htmlspecialchars($roleFilter) ?>"><?php endif; ?>
                    <?php if ($sortBy): ?><input type="hidden" name="sort"
                            value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
                    <?php if ($limit !== 4): ?><input type="hidden" name="limit"
                            value="<?= htmlspecialchars($limit) ?>"><?php endif; ?>
                </form>
            </div>

            <!-- Filters -->
            <div class="d-flex gap-2">
                <!-- Action Type Dropdown -->
                <div class="dropdown">
                    <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $categoryFilter ? getActionLabel($categoryFilter) : 'All Action Types' ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm" style="font-size: 13px;">
                        <li><a class="dropdown-item <?= empty($categoryFilter) ? 'active' : '' ?>"
                                href="?category=&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">All
                                Action Types</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'login' ? 'active' : '' ?>"
                                href="?category=login&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Login</a>
                        </li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'logout' ? 'active' : '' ?>"
                                href="?category=logout&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Logout</a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'create_user' ? 'active' : '' ?>"
                                href="?category=create_user&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Create
                                User</a></li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'edit_user' ? 'active' : '' ?>"
                                href="?category=edit_user&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Update
                                User</a></li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'delete_user' ? 'active' : '' ?>"
                                href="?category=delete_user&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Delete
                                User</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'upload' ? 'active' : '' ?>"
                                href="?category=upload&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Create
                                File</a></li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'edit' ? 'active' : '' ?>"
                                href="?category=edit&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Update
                                File</a></li>
                        <li><a class="dropdown-item <?= $categoryFilter === 'delete' ? 'active' : '' ?>"
                                href="?category=delete&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>&limit=<?= $limit ?>">Delete
                                File</a></li>
                    </ul>
                </div>

                <!-- Sort By -->
                <div class="dropdown">
                    <button class="filter-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $sortBy === 'oldest' ? 'Oldest First' : 'Newest First' ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm" style="font-size: 13px;">
                        <li><a class="dropdown-item <?= $sortBy === 'newest' ? 'active' : '' ?>"
                                href="?sort=newest&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&limit=<?= $limit ?>">Newest
                                First</a></li>
                        <li><a class="dropdown-item <?= $sortBy === 'oldest' ? 'active' : '' ?>"
                                href="?sort=oldest&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&limit=<?= $limit ?>">Oldest
                                First</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table history-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 20%;">USER</th>
                            <th class="text-center" style="width: 15%;">ACTION</th>
                            <th style="width: 40%;">TITLE</th>
                            <th class="text-end" style="width: 20%;">TIMESTAMP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <span class="text-muted">No activity logs found.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted fw-bold">#<?= $log['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($log['username'] ?? 'Unknown User') ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $actionRaw = strtolower($log['action']);
                                        $actionLabel = 'UPDATE';
                                        $badgeClass = 'badge-update';

                                        if (strpos($actionRaw, 'create') !== false || strpos($actionRaw, 'upload') !== false || strpos($actionRaw, 'restore') !== false) {
                                            $actionLabel = 'CREATE';
                                            $badgeClass = 'badge-create';
                                        } elseif (strpos($actionRaw, 'delete') !== false || strpos($actionRaw, 'permanent') !== false) {
                                            $actionLabel = 'DELETE';
                                            $badgeClass = 'badge-delete';
                                        } elseif (strpos($actionRaw, 'login') !== false) {
                                            $actionLabel = 'LOGIN';
                                            $badgeClass = 'badge-login';
                                        } elseif (strpos($actionRaw, 'logout') !== false) {
                                            $actionLabel = 'LOGOUT';
                                            $badgeClass = 'badge-logout';
                                        }
                                        ?>
                                        <span class="action-badge <?= $badgeClass ?>">
                                            <?= $actionLabel ?>
                                        </span>
                                    </td>
                                    <td class="fw-medium text-dark">
                                        <?= htmlspecialchars($log['target_title'] ?? '-') ?>
                                    </td>
                                    <td class="text-end text-muted">
                                        <?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-4 d-flex justify-content-between align-items-center border-top">
                <!-- Limit Selector and Showing Text -->
                <div class="d-flex align-items-center gap-3">
                    <span class="text-secondary small fw-medium">Rows per page:</span>
                    <select class="rows-per-page-select" id="rowsPerPage" style="cursor: pointer;">
                        <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                        <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    </select>

                    <span class="text-secondary small ms-2">
                        Showing
                        <?= ($pagination['offset'] + 1) ?>-<?= min($pagination['offset'] + $limit, $totalLogs) ?> of
                        <?= $totalLogs ?> actions
                    </span>
                </div>

                <!-- Circular Pagination Controls -->
                <div class="d-flex align-items-center gap-1">
                    <?php
                    // Helper function should be outside loop or at top, moving it here just in case but ideally at top of PHP block. 
                    // Since I cannot easily move it to line 1 without reading whole file, I will leave it here but ensure it is defined properly.
                    if (!function_exists('getPaginationUrl')) {
                        function getPaginationUrl($page, $limit)
                        {
                            $params = $_GET;
                            $params['page'] = $page;
                            $params['limit'] = $limit;
                            // Ensure export param is removed from pagination links
                            unset($params['export']);
                            return '?' . http_build_query($params);
                        }
                    }
                    ?>

                    <a href="<?= getPaginationUrl(max(1, $page - 1), $limit) ?>"
                        class="pagination-circle <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>

                    <?php
                    $totalPages = ceil($totalLogs / $limit);
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="<?= getPaginationUrl($i, $limit) ?>"
                            class="pagination-circle <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($totalPages > $endPage): ?>
                        <span class="text-muted small px-1">...</span>
                        <a href="<?= getPaginationUrl($totalPages, $limit) ?>"
                            class="pagination-circle"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <a href="<?= getPaginationUrl(min($totalPages, $page + 1), $limit) ?>"
                        class="pagination-circle <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>


    <?php include __DIR__ . '/../views/layouts/footer.php'; ?>

    <script>
        // Rows per page
        document.getElementById('rowsPerPage').addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', this.value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });

        // Live Search
        let debounceTimer;
        const searchInput = document.getElementById('searchInput');
        const form = searchInput.closest('form');

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                form.submit();
            }, 600); // 600ms debounce
        });

        // Focus search input if it has value (after reload)
        if (searchInput.value) {
            searchInput.focus();
            // Move cursor to end
            const len = searchInput.value.length;
            searchInput.setSelectionRange(len, len);
        }
    </script>
</body>

</html>