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
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <div class="text-muted small mb-1 fw-bold" style="font-size: 11px; letter-spacing: 1px;">ADMIN <i
                        class="bi bi-chevron-right mx-1" style="font-size: 10px;"></i> AUDIT TRAIL</div>
                <h1 class="page-title m-0" style="font-size: 28px; font-weight: 700; color: #4C3939;">System History
                    Logs</h1>
            </div>
            <div class="page-actions">
                <a href="?export=csv" class="btn"
                    style="background: #4C3939; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 500; font-size: 14px;">
                    <i class="bi bi-download me-2"></i>Export CSV
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
        <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <!-- Search -->
                    <div class="col-md-5">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute text-muted"
                                style="top: 50%; left: 15px; transform: translateY(-50%); font-size: 14px;"></i>
                            <form method="GET" class="w-100">
                                <input type="text" class="form-control" name="search" placeholder="Search logs..."
                                    value="<?= htmlspecialchars($search) ?>"
                                    style="background: #fff; border: 1px solid #dee2e6; padding: 10px 10px 10px 40px; border-radius: 6px; font-size: 14px; box-shadow: none;">
                            </form>
                        </div>
                    </div>

                    <!-- Filter Dropdown & Sort -->
                    <div class="col-md-7 d-flex align-items-center justify-content-end gap-3">

                        <!-- Action Filter -->
                        <div class="dropdown">
                            <form method="GET" id="categoryForm" class="d-inline">
                                <?php if ($search): ?><input type="hidden" name="search"
                                        value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                                <select class="form-select" name="category" onchange="this.form.submit()"
                                    style="background: #fff; border: 1px solid #dee2e6; padding: 10px 36px 10px 16px; border-radius: 6px; font-size: 14px; min-width: 160px; cursor: pointer;">
                                    <option value="">All Action Types</option>
                                    <option value="create_user" <?= $categoryFilter === 'create_user' ? 'selected' : '' ?>>
                                        Create User</option>
                                    <option value="edit_user" <?= $categoryFilter === 'edit_user' ? 'selected' : '' ?>>
                                        Update User</option>
                                    <option value="delete_user" <?= $categoryFilter === 'delete_user' ? 'selected' : '' ?>>
                                        Delete User</option>
                                    <option value="upload" <?= $categoryFilter === 'upload' ? 'selected' : '' ?>>Create
                                        File</option>
                                    <option value="delete" <?= $categoryFilter === 'delete' ? 'selected' : '' ?>>Delete
                                        File</option>
                                    <option value="edit" <?= $categoryFilter === 'edit' ? 'selected' : '' ?>>Update File
                                    </option>
                                    <option value="login" <?= $categoryFilter === 'login' ? 'selected' : '' ?>>Login
                                    </option>
                                    <option value="logout" <?= $categoryFilter === 'logout' ? 'selected' : '' ?>>Logout
                                    </option>
                                </select>
                            </form>
                        </div>

                        <!-- Sort By -->
                        <div class="d-flex align-items-center">
                            <span class="text-uppercase text-muted me-2 fw-bold" style="font-size: 11px;">Sort
                                By:</span>
                            <form method="GET" id="sortForm" class="d-inline">
                                <?php if ($search): ?><input type="hidden" name="search"
                                        value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                                <?php if ($categoryFilter): ?><input type="hidden" name="category"
                                        value="<?= htmlspecialchars($categoryFilter) ?>">
                                <?php endif; ?>
                                <select class="form-select border-0 bg-transparent fw-bold p-0 pe-4" name="sort"
                                    onchange="this.form.submit()"
                                    style="font-size: 14px; color: #333; cursor: pointer; background-position: right center; width: auto;">
                                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First
                                    </option>
                                    <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First
                                    </option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="table-container bg-white shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <table class="table mb-0 align-middle">
                <thead style="background-color: #4C3939 !important; color: #fff;">
                    <tr>
                        <th class="py-3 ps-4 text-uppercase fw-bold text-start" style="font-size: 11px; letter-spacing: 1px; width: 10%; color: #fff !important; border: none;">ID</th>
                        <th class="py-3 text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 1px; width: 25%; color: #fff !important; border: none;">User</th>
                        <th class="py-3 text-center text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 1px; width: 15%; color: #fff !important; border: none;">Action</th>
                        <th class="py-3 text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 1px; width: 30%; color: #fff !important; border: none;">Title</th>
                        <th class="py-3 pe-4 text-end text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 1px; width: 20%; color: #fff !important; border: none;">Timestamp</th>
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
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td class="py-4 ps-4 text-start" style="font-size: 13px; font-weight: 500; color: #aaa;">
                                    #<?= $log['id'] ?>
                                </td>
                                <td class="py-4">
                                    <div class="d-flex align-items-center">
                                        <?php
                                        // Initials
                                        $initials = strtoupper(substr($log['username'], 0, 2));
                                        if (!empty($log['full_name'])) {
                                            $parts = explode(' ', $log['full_name']);
                                            if (count($parts) >= 2) {
                                                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
                                            } else {
                                                $initials = strtoupper(substr($log['full_name'], 0, 2));
                                            }
                                        }
                                        ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                                            style="width: 36px; height: 36px; background-color: #f2f2f2; color: #333; font-weight: 600; font-size: 13px;">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #333; font-size: 14px;">
                                                <?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                            <div
                                                style="font-size: 10px; color: #999; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">
                                                <?= htmlspecialchars($log['role']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 text-center">
                                    <?php
                                    $actionRaw = strtolower($log['action']);
                                    $actionLabel = 'UPDATE'; // Default
                                    $badgeStyle = 'background-color: #FFF8E1; color: #F57F17; border: 1px solid #FFECB3;'; // Yellow/Orange default
                            
                                    if (strpos($actionRaw, 'create') !== false || strpos($actionRaw, 'upload') !== false || strpos($actionRaw, 'restore') !== false) {
                                        $actionLabel = 'CREATE';
                                        $badgeStyle = 'background-color: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9;'; // Green
                                    } elseif (strpos($actionRaw, 'delete') !== false || strpos($actionRaw, 'permanent') !== false) {
                                        $actionLabel = 'DELETE';
                                        $badgeStyle = 'background-color: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2;'; // Red
                                    } elseif (strpos($actionRaw, 'login') !== false || strpos($actionRaw, 'logout') !== false) {
                                        $actionLabel = 'LOGIN'; // Or LOGOUT
                                        if (strpos($actionRaw, 'logout') !== false)
                                            $actionLabel = 'LOGOUT';
                                        $badgeStyle = 'background-color: #E3F2FD; color: #1565C0; border: 1px solid #BBDEFB;'; // Blue
                                    }

                                    // Override label for specific cases if needed, but keeping it simple as per mockup
                                    ?>
                                    <span class="badge rounded-pill px-3 py-2 fw-bold"
                                        style="<?= $badgeStyle ?> font-size: 11px; letter-spacing: 0.5px; min-width: 80px;">
                                        <?= $actionLabel ?>
                                    </span>
                                </td>
                                <td class="py-4">
                                    <div class="text-truncate" style="max-width: 250px; font-weight: 500; font-size: 13px; color: #333;">
                                        <?= htmlspecialchars($log['target_title'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="py-4 text-end pe-4">
                                    <span style="color: #333; font-weight: 600; font-size: 13px; margin-right: 8px;">
                                        <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                    </span>
                                    <span style="color: #aaa; font-size: 13px;">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-4 py-3 d-flex justify-content-between align-items-center"
                style="background: #fff; border-top: 1px solid #f0f0f0;">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">ROWS PER
                            PAGE:</span>
                        <select class="form-select form-select-sm border-0 bg-light" id="rowsPerPage"
                            style="width: auto; font-size: 12px; font-weight: 600; cursor: pointer; padding-right: 24px;">
                            <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                        </select>
                    </div>
                    <span class="text-secondary small" style="font-size: 12px;">
                        Showing <span
                            class="fw-bold text-dark"><?= ($pagination['offset'] + 1) ?>-<?= min($pagination['offset'] + $limit, $totalLogs) ?></span>
                        of <span class="fw-bold text-dark"><?= $totalLogs ?></span> actions
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>"
                        class="btn btn-sm btn-link text-decoration-none text-secondary d-flex align-items-center gap-1 <?= !$pagination['has_prev'] ? 'disabled' : '' ?>"
                        style="font-size: 12px; font-weight: 600;">
                        <i class="bi bi-chevron-left" style="font-size: 10px;"></i> Previous
                    </a>

                    <div class="d-flex gap-1 mx-2">
                        <!-- First Page -->
                        <?php if ($page > 3): ?>
                            <a href="?page=1&limit=<?= $limit ?>" class="btn btn-sm text-secondary"
                                style="font-size: 12px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">1</a>
                            <span class="text-muted d-flex align-items-end pb-1" style="font-size: 10px;">...</span>
                        <?php endif; ?>

                        <!-- Page Numbers (Simplified logic for now) -->
                        <span class="btn btn-sm"
                            style="background: #4C3939; color: #fff; font-size: 12px; font-weight: 600; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                            <?= $page ?>
                        </span>

                        <?php if ($pagination['has_next']): ?>
                            <!-- Just showing next page number for simplicity if exists, full pagination logic would be more complex -->
                            <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>" class="btn btn-sm text-secondary"
                                style="font-size: 12px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;"><?= $page + 1 ?></a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages - 1): ?>
                            <span class="text-muted d-flex align-items-end pb-1" style="font-size: 10px;">...</span>
                            <a href="?page=<?= $totalPages ?>&limit=<?= $limit ?>" class="btn btn-sm text-secondary"
                                style="font-size: 12px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;"><?= $totalPages ?></a>
                        <?php endif; ?>
                    </div>

                    <a href="?page=<?= min($totalPages, $page + 1) ?>&limit=<?= $limit ?>"
                        class="btn btn-sm btn-link text-decoration-none text-secondary d-flex align-items-center gap-1 <?= !$pagination['has_next'] ? 'disabled' : '' ?>"
                        style="font-size: 12px; font-weight: 600;">
                        Next <i class="bi bi-chevron-right" style="font-size: 10px;"></i>
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