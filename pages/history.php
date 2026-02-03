<?php
/**
 * History / Activity Logs Page
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
$roleFilter = $_GET['role'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (u.username LIKE ? OR a.target_title LIKE ?)";
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

// Sort order
$orderBy = $sortBy === 'oldest' ? 'a.created_at ASC' : 'a.created_at DESC';

// Get logs
$sql = "SELECT a.*, u.username, u.role FROM activity_logs a 
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
        <div class="page-header">
            <div>
                <h1 class="page-title">History</h1>
                <p class="page-subtitle">Audit trails for administrative actions and system events</p>
            </div>
            <div class="page-actions">
                <a href="?export=csv" class="btn btn-primary">
                    <i class="bi bi-download me-2"></i>Export to CSV
                </a>
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
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <div class="search-input-wrapper flex-grow-1">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" name="search" placeholder="Search deleted items..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 justify-content-end">
                    <select class="form-select" style="width: auto;" onchange="location.href='?category='+this.value">
                        <option value="">Categories</option>
                        <option value="create_user" <?= $categoryFilter === 'create_user' ? 'selected' : '' ?>>Create User
                        </option>
                        <option value="edit_user" <?= $categoryFilter === 'edit_user' ? 'selected' : '' ?>>Edit User
                        </option>
                        <option value="delete_user" <?= $categoryFilter === 'delete_user' ? 'selected' : '' ?>>Delete User
                        </option>
                        <option value="upload" <?= $categoryFilter === 'upload' ? 'selected' : '' ?>>Upload</option>
                        <option value="edit" <?= $categoryFilter === 'edit' ? 'selected' : '' ?>>Edit</option>
                        <option value="delete" <?= $categoryFilter === 'delete' ? 'selected' : '' ?>>Delete</option>
                        <option value="restore" <?= $categoryFilter === 'restore' ? 'selected' : '' ?>>Restore</option>
                        <option value="login" <?= $categoryFilter === 'login' ? 'selected' : '' ?>>Login</option>
                        <option value="logout" <?= $categoryFilter === 'logout' ? 'selected' : '' ?>>Logout</option>
                    </select>
                    <select class="form-select" style="width: auto;" onchange="location.href='?role='+this.value">
                        <option value="">Role</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : '' ?>>Super Admin
                        </option>
                    </select>
                    <select class="form-select" style="width: auto;" onchange="location.href='?sort='+this.value">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>USER</th>
                        <th>ACTION</th>
                        <th>TITLE</th>
                        <th>TIMESTAMP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">No activity logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($log['username']) ?>
                                </td>
                                <td>
                                    <span class="action-badge <?= getActionClass($log['action']) ?>">
                                        <?= ucwords(str_replace('_', ' ', $log['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['target_title'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= formatDate($log['created_at']) ?>
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
                        <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                    </select>
                    <span class="pagination-info">Showing
                        <?= ($pagination['offset'] + 1) ?>-
                        <?= min($pagination['offset'] + $limit, $totalLogs) ?> of
                        <?= $totalLogs ?> actions
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