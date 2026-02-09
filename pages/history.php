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
                <h1 class="page-title" style="font-weight: 700; color: #4C3939;">History</h1>
                <p class="text-muted small">Audit trails for administrative actions and system events</p>
            </div>
            <div class="page-actions">
                <a href="?export=csv" class="btn"
                    style="background-color: #4C3939; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 500;">
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

        <!-- Search & Filter Card -->
        <div class="mb-4" style="background: #F8F5F2; padding: 20px; border-radius: 12px; border: 1px solid #E6D5C9;">
            <div class="row align-items-end g-3">
                <!-- Search -->
                <div class="col-md-5">
                    <div class="search-input-wrapper position-relative"
                        style="background: #EBE8E4; border: 1px solid #D7D3CE; border-radius: 8px;">
                        <i class="bi bi-search position-absolute text-muted"
                            style="top: 50%; left: 15px; transform: translateY(-50%); font-size: 16px;"></i>
                        <form method="GET" class="w-100">
                            <input type="text" class="form-control" name="search" placeholder="Search deleted items ..."
                                value="<?= htmlspecialchars($search) ?>"
                                style="background: transparent; border: none; padding: 10px 10px 10px 45px; box-shadow: none; font-size: 14px;">
                        </form>
                    </div>
                </div>

                <!-- Filters -->
                <div class="col-md-7 d-flex gap-3 justify-content-end">
                    <div>
                        <label class="form-label small mb-1 fw-bold text-muted"
                            style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">Categories</label>
                        <select class="form-select form-select-sm" name="category" onchange="this.form.submit()"
                            style="width: 140px; background: #fff; border: 1px solid #D7D3CE; font-size: 13px; padding: 8px 12px; border-radius: 6px;">
                            <option value="">All</option>
                            <option value="create_user" <?= $categoryFilter === 'create_user' ? 'selected' : '' ?>>Create
                                User</option>
                            <option value="edit_user" <?= $categoryFilter === 'edit_user' ? 'selected' : '' ?>>Edit User
                            </option>
                            <option value="delete_user" <?= $categoryFilter === 'delete_user' ? 'selected' : '' ?>>Delete
                                User</option>
                            <option value="upload" <?= $categoryFilter === 'upload' ? 'selected' : '' ?>>Create</option>
                            <option value="delete" <?= $categoryFilter === 'delete' ? 'selected' : '' ?>>Delete</option>
                            <option value="edit" <?= $categoryFilter === 'edit' ? 'selected' : '' ?>>Edited</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1 fw-bold text-muted"
                            style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">Role</label>
                        <select class="form-select form-select-sm" name="role" onchange="this.form.submit()"
                            style="width: 120px; background: #fff; border: 1px solid #D7D3CE; font-size: 13px; padding: 8px 12px; border-radius: 6px;">
                            <option value="">User</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1 fw-bold text-muted"
                            style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">Sort By</label>
                        <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()"
                            style="width: 120px; background: #fff; border: 1px solid #D7D3CE; font-size: 13px; padding: 8px 12px; border-radius: 6px;">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="table-container"
            style="background: #EBE8E4; border: 1px solid #D7D3CE; border-radius: 12px; overflow: hidden;">
            <table class="table mb-0">
                <thead>
                    <tr style="border-bottom: 2px solid #D7D3CE;">
                        <th class="py-3 ps-4 text-center text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none; width: 60px;">
                            ID</th>
                        <th class="py-3 text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none;">User
                        </th>
                        <th class="py-3 text-center text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none;">Action
                        </th>
                        <th class="py-3 text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none;">Title
                        </th>
                        <th class="py-3 pe-4 text-end text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none;">
                            Timestampt</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 bg-white">
                                <span class="text-muted">No activity logs found.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr style="border-bottom: 1px solid #EBE8E4;">
                                <td class="py-3 ps-4 text-center" style="font-size: 13px; font-weight: 500; color: #333;">
                                    <?= $log['id'] ?>
                                </td>
                                <td class="py-3" style="font-size: 13px; color: #333;">
                                    <?= htmlspecialchars($log['username']) ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php
                                    $actionStr = getActionLabel($log['action']);
                                    $color = '#333';

                                    if (stripos($log['action'], 'delete') !== false) {
                                        $color = '#DC3545'; // Red
                                    } elseif (stripos($log['action'], 'create') !== false || stripos($log['action'], 'upload') !== false || stripos($log['action'], 'restore') !== false) {
                                        $color = '#198754'; // Green
                                    } elseif (stripos($log['action'], 'edit') !== false || stripos($log['action'], 'login') !== false || stripos($log['action'], 'logout') !== false) {
                                        $color = '#0D6EFD'; // Blue (or maybe black for login/out)
                                        if (stripos($log['action'], 'login') !== false || stripos($log['action'], 'logout') !== false)
                                            $color = '#333';
                                    }

                                    // Custom colors for specific actions if needed
                                    ?>
                                    <span class="fw-bold" style="font-size: 12px; color: <?= $color ?>;">
                                        <?= $actionStr ?>
                                    </span>
                                </td>
                                <td class="py-3" style="font-size: 13px; color: #333;">
                                    <?= htmlspecialchars($log['target_title'] ?? '-') ?>
                                </td>
                                <td class="py-3 pe-4 text-end" style="font-size: 12px; color: #333;">
                                    <?= date('Y-m-d h:i A', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-3 py-3 d-flex justify-content-between align-items-center"
                style="background: #EBE8E4; border-top: 1px solid #D7D3CE;">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Rows per page</span>
                    <select class="form-select form-select-sm" id="rowsPerPage"
                        style="width: 50px; background: #D7D3CE; border: none; font-size: 12px; height: 26px; padding: 2px 24px 2px 8px;">
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

                <div class="d-flex align-items-center gap-3">
                    <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>"
                        class="text-decoration-none text-dark d-flex align-items-center small fw-bold <?= !$pagination['has_prev'] ? 'text-muted pe-none' : '' ?>">
                        <i class="bi bi-chevron-left small me-1"></i> Previous
                    </a>

                    <div class="d-flex gap-1">
                        <span class="badge rounded-1 d-flex align-items-center justify-content-center"
                            style="width: 24px; height: 24px; font-weight: normal; background: #4C3939; font-size: 12px;">
                            <?= $page ?>
                        </span>
                        <?php if ($pagination['has_next']): ?>
                            <span class="d-flex align-items-center justify-content-center small text-muted"
                                style="width: 24px; height: 24px;">
                                <?= $page + 1 ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <a href="?page=<?= min($totalPages, $page + 1) ?>&limit=<?= $limit ?>"
                        class="text-decoration-none text-dark d-flex align-items-center small fw-bold <?= !$pagination['has_next'] ? 'text-muted pe-none' : '' ?>">
                        Next <i class="bi bi-chevron-right small ms-1"></i>
                    </a>
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