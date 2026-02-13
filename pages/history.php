<?php
/**
 * History / Activity Logs Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

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
        JOIN users u ON a.user_id = u.id 
        $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $pagination['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['User', 'Action', 'Title', 'Timestamp']);

    // Get all logs for export
    $exportStmt = $pdo->query("SELECT a.*, u.username FROM activity_logs a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC");
    while ($row = $exportStmt->fetch()) {
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
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold m-0" style="font-size: 24px; color: #212529;">History Logs</h1>
                <div class="text-muted small">Monitor and review all system activities and administrative actions</div>
            </div>
            <a href="?export=csv" class="btn btn-primary px-4 py-2"
                style="background-color: #4C3939; border-color: #4C3939; font-weight: 500;">
                <i class="bi bi-download me-2"></i>Export CSV
            </a>
        </div>



        <!-- Search & Filter Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 p-2">
            <div class="card-body p-2">
                <form method="GET" class="row g-3 align-items-center w-100 m-0">
                    <!-- Search -->
                    <div class="col-md-5 ps-0">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute text-muted"
                                style="left: 15px; top: 50%; transform: translateY(-50%); z-index: 5;"></i>
                            <input type="text" class="form-control border-0 bg-light rounded-pill ps-5 py-2"
                                name="search" id="searchInput" placeholder="Search logs by title, user, or action..."
                                value="<?= htmlspecialchars($search) ?>" style="font-size: 14px; padding-right: 80px;">

                            <?php if (!empty($search) || !empty($categoryFilter) || $sortBy !== 'newest'): ?>
                                <a href="history.php"
                                    class="position-absolute d-flex align-items-center justify-content-center text-muted text-decoration-none"
                                    style="right: 45px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; z-index: 10;"
                                    title="Reset Filters">
                                    <i class="bi bi-x-circle-fill"></i>
                                </a>
                            <?php endif; ?>

                            <button type="submit"
                                class="btn position-absolute end-0 top-0 bottom-0 m-1 rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 38px; height: 38px; background-color: #4C3939; color: white; border: none;">
                                <i class="bi bi-search" style="font-size: 14px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="col-md-7 d-flex justify-content-end align-items-center gap-2 pe-0">
                        <select class="form-select border-0 bg-light rounded-pill py-2 ps-3 pe-5 shadow-none"
                            name="category" onchange="this.form.submit()"
                            style="width: auto; font-size: 13px; font-weight: 600; cursor: pointer; background-position: right 1rem center;">
                            <option value="">All Action Types</option>
                            <option value="create_user" <?= $categoryFilter === 'create_user' ? 'selected' : '' ?>>Create
                                User</option>
                            <option value="edit_user" <?= $categoryFilter === 'edit_user' ? 'selected' : '' ?>>Update User
                            </option>
                            <option value="delete_user" <?= $categoryFilter === 'delete_user' ? 'selected' : '' ?>>Delete
                                User</option>
                            <option value="upload" <?= $categoryFilter === 'upload' ? 'selected' : '' ?>>Create File
                            </option>
                            <option value="delete" <?= $categoryFilter === 'delete' ? 'selected' : '' ?>>Delete File
                            </option>
                            <option value="edit" <?= $categoryFilter === 'edit' ? 'selected' : '' ?>>Update File</option>
                            <option value="login" <?= $categoryFilter === 'login' ? 'selected' : '' ?>>Login</option>
                            <option value="logout" <?= $categoryFilter === 'logout' ? 'selected' : '' ?>>Logout</option>
                        </select>

                        <select class="form-select border-0 bg-light rounded-pill py-2 ps-3 pe-5 shadow-none"
                            name="sort" onchange="this.form.submit()"
                            style="width: auto; font-size: 13px; font-weight: 600; cursor: pointer; background-position: right 1rem center;">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- History Table -->
        <div class="table-container">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 py-3" style="width: 80px;">ID</th>
                        <th class="py-3 ps-5" style="width: 20%;">USER</th>
                        <th class="text-center py-3" style="width: 15%;">ACTION</th>
                        <th class="py-3">TITLE</th>
                        <th class="text-end pe-4 py-3" style="width: 20%;">TIMESTAMP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 bg-white">
                                <span class="text-muted">No activity logs found.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 text-muted fw-bold">#<?= $log['id'] ?></td>
                                <td class="ps-5">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 14px;">
                                                <?= htmlspecialchars($log['username']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $actionRaw = strtolower($log['action']);
                                    $actionLabel = 'UPDATE'; // Default
                                    $badgeClass = 'badge-warning'; // Default
                            
                                    if (strpos($actionRaw, 'create') !== false || strpos($actionRaw, 'upload') !== false || strpos($actionRaw, 'restore') !== false) {
                                        $actionLabel = 'CREATE';
                                        $badgeClass = 'badge-active';
                                    } elseif (strpos($actionRaw, 'delete') !== false || strpos($actionRaw, 'permanent') !== false) {
                                        $actionLabel = 'DELETE';
                                        $badgeClass = 'badge-danger';
                                    } elseif (strpos($actionRaw, 'login') !== false) {
                                        $actionLabel = 'LOGIN';
                                        $badgeClass = 'badge-info';
                                    } elseif (strpos($actionRaw, 'logout') !== false) {
                                        $actionLabel = 'LOGOUT';
                                        $badgeClass = 'badge-info';
                                    }
                                    ?>
                                    <span class="badge-pill <?= $badgeClass ?> text-uppercase"
                                        style="font-size: 11px; letter-spacing: 0.5px; padding: 6px 16px;">
                                        <?= $actionLabel ?>
                                    </span>
                                </td>
                                <td class="fw-medium text-dark">
                                    <div class="text-truncate" style="max-width: 300px;">
                                        <span class="text-dark"><?= htmlspecialchars($log['target_title'] ?? '-') ?></span>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="text-dark fw-medium" style="font-size: 13px;">
                                        <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                    </span>
                                    <span class="text-muted ms-2" style="font-size: 13px;">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-4 py-3 d-flex justify-content-between align-items-center bg-white"
                style="border-top: 1px solid #f0f0f0;">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Rows per page:</span>
                    <select class="form-select form-select-sm border-0 bg-light rounded-2 py-1 pe-4" id="rowsPerPage"
                        style="width: 60px; font-weight: 500; cursor: pointer; background-position: right 0.5rem center;">
                        <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                        <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    </select>
                    <span class="text-muted small ms-2">
                        Showing
                        <?= ($pagination['offset'] + 1) ?>-<?= min($pagination['offset'] + $limit, $totalLogs) ?> of
                        <?= $totalLogs ?> actions
                    </span>
                </div>

                <div class="pagination-circular">
                    <?php
                    // Helper function to build pagination URL with existing filters
                    function getPaginationUrl($page, $limit)
                    {
                        $params = $_GET;
                        $params['page'] = $page;
                        $params['limit'] = $limit;
                        return '?' . http_build_query($params);
                    }
                    ?>

                    <a href="<?= getPaginationUrl(max(1, $page - 1), $limit) ?>"
                        class="page-link-square <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>

                    <?php
                    $totalPages = ceil($totalLogs / $limit);
                    for ($i = 1; $i <= min(5, $totalPages); $i++):
                        ?>
                        <a href="<?= getPaginationUrl($i, $limit) ?>"
                            class="page-link-square <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($totalPages > 5): ?>
                        <span class="text-muted px-1 small">...</span>
                        <a href="<?= getPaginationUrl($totalPages, $limit) ?>"
                            class="page-link-square"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <a href="<?= getPaginationUrl(min($totalPages, $page + 1), $limit) ?>"
                        class="page-link-square <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
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