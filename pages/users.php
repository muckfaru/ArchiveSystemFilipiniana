<?php
/**
 * Users Management Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get alert message
$alert = getAlert();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter) {
    $whereClause .= " AND role = ?";
    $params[] = $roleFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $whereClause");
$countStmt->execute($params);
$totalUsers = $countStmt->fetch()['total'];

// Get pagination data
$pagination = getPagination($totalUsers, $page, $limit);

// Sort order
$orderBy = $sortBy === 'oldest' ? 'created_at ASC' : 'created_at DESC';

// Get users
$stmt = $pdo->prepare("SELECT * FROM users $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $pagination['offset'];
$stmt->execute($params);
$users = $stmt->fetchAll();

// Stats
$totalAdmins = countTotalAdmins();
$activeAdmins = countActiveAdmins();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = sanitize($_POST['username']);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        // Check if username or email exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);

        if ($checkStmt->fetch()) {
            showAlert('danger', 'Username or email already exists.');
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([$username, $hashedPassword, $fullName, $email, $role]);

            logActivity($currentUser['id'], 'create_user', $username);
            showAlert('success', 'User created successfully.');
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'edit') {
        $userId = intval($_POST['user_id']);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'];

        $updateStmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $updateStmt->execute([$fullName, $email, $role, $status, $userId]);

        logActivity($currentUser['id'], 'edit_user', $fullName);
        showAlert('success', 'User updated successfully.');
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'delete') {
        $userId = intval($_POST['user_id']);

        // Prevent self-deletion
        if ($userId === $currentUser['id']) {
            showAlert('danger', 'You cannot delete your own account.');
        } else {
            $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $deletedUser = $userStmt->fetch();

            $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->execute([$userId]);

            logActivity($currentUser['id'], 'delete_user', $deletedUser['username']);
            showAlert('success', 'User deleted successfully.');
        }
        redirect($_SERVER['PHP_SELF']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users -
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
        <div class="page-header"
            style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 25px;">
            <div>
                <h1 class="page-title"
                    style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Playfair Display', Georgia, serif;">
                    Users</h1>
                <p class="page-subtitle" style="color: #888; margin: 0;">Create, edit, and manage system user accounts
                </p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#createUserModal"
                    style="background: #4C3939; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 500;">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert">
                <?= $alert['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <span class="stat-card-title">Total Admins</span>
                    <div class="stat-card-value"><?= $totalAdmins ?>
                    </div>
                    <div class="stat-card-icon-wrapper">
                        <i class="bi bi-people stat-card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <span class="stat-card-title">Active Accounts</span>
                    <div class="stat-card-value"><?= $activeAdmins ?>
                    </div>
                    <div class="stat-card-icon-wrapper success">
                        <i class="bi bi-broadcast stat-card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

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
                            <input type="text" class="form-control" name="search" placeholder="Search users ..."
                                value="<?= htmlspecialchars($search) ?>"
                                style="background: transparent; border: none; padding: 10px 10px 10px 45px; box-shadow: none; font-size: 14px;">
                        </form>
                    </div>
                </div>

                <!-- Filters -->
                <div class="col-md-7 d-flex gap-3 justify-content-end">
                    <div>
                        <label class="form-label small mb-1 fw-bold text-muted"
                            style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">Status</label>
                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()"
                            style="width: 140px; background: #fff; border: 1px solid #D7D3CE; font-size: 13px; padding: 8px 12px; border-radius: 6px;">
                            <option value="">All</option>
                            <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active
                            </option>
                            <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                Inactive</option>
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

        <!-- Users Table -->
        <div class="table-container"
            style="background: #EBE8E4; border: 1px solid #D7D3CE; border-radius: 12px; overflow: hidden;">
            <table class="table mb-0">
                <thead>
                    <tr style="border-bottom: 2px solid #D7D3CE;">
                        <th class="py-3 ps-4 text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none; width: 30%;">
                            Username</th>
                        <th class="py-3 text-center text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none; width: 15%;">
                            Role</th>
                        <th class="py-3 text-center text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none; width: 15%;">
                            Status</th>
                        <th class="py-3 text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none; width: 25%;">
                            Last Login</th>
                        <th class="py-3 pe-4 text-end text-uppercase text-muted"
                            style="font-size: 11px; font-weight: 800; background: #EBE8E4; border-bottom: none; width: 15%;">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 bg-white">
                                <span class="text-muted">No users found.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr style="border-bottom: 1px solid #EBE8E4;">
                                <td class="py-3 ps-4" style="font-size: 13px; color: #333;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar"
                                            style="width: 32px; height: 32px; background: #EBE8E4; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-person-fill" style="color: #8B7355;"></i>
                                        </div>
                                        <span class="fw-medium"><?= htmlspecialchars($user['username']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-center" style="font-size: 13px; color: #333;">
                                    <?= ($user['role'] === 'super_admin') ? 'Admin' : 'Admin' ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php
                                    $statusColor = $user['status'] === 'active' ? '#198754' : '#DC3545';
                                    ?>
                                    <span class="fw-bold" style="font-size: 12px; color: <?= $statusColor ?>;">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3" style="font-size: 12px; color: #333;">
                                    <?= $user['last_login'] ? date('Y-m-d h:i A', strtotime($user['last_login'])) : 'Never' ?>
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button type="button" class="btn btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#editUserModal" data-user='<?= json_encode($user) ?>'
                                            style="background: #f5f5f5; border: 1px solid #ddd; padding: 6px 10px; border-radius: 6px;">
                                            <i class="bi bi-pencil" style="font-size: 12px; color: #666;"></i>
                                        </button>
                                        <?php if ($user['id'] !== $currentUser['id']): ?>
                                            <form method="POST" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm"
                                                    style="background: #FFF5F5; border: 1px solid #FFCDD2; padding: 6px 10px; border-radius: 6px;">
                                                    <i class="bi bi-trash" style="font-size: 12px; color: #DC3545;"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
                        <?= ($pagination['offset'] + 1) ?>-<?= min($pagination['offset'] + $limit, $totalUsers) ?> of
                        <?= $totalUsers ?> users
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

                    <a href="?page=<?= min($pagination['total_pages'], $page + 1) ?>&limit=<?= $limit ?>"
                        class="text-decoration-none text-dark d-flex align-items-center small fw-bold <?= !$pagination['has_next'] ? 'text-muted pe-none' : '' ?>">
                        Next <i class="bi bi-chevron-right small ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius: 16px; border: none; box-shadow: 0 25px 50px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div>
                        <h4 class="modal-title"
                            style="color: #2C1810; font-size: 26px; font-weight: 600; font-family: 'Playfair Display', Georgia, serif;">
                            Create New Account</h4>
                        <p style="color: #888; font-size: 14px; margin: 0;">Add a new user to the Archive System</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity: 0.5;"></button>
                </div>
                <form method="POST" id="createAccountForm">
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Full
                                Name</label>
                            <input type="text" class="form-control" name="full_name" placeholder="Enter Full name"
                                required
                                style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Email
                                Address</label>
                            <input type="email" class="form-control" name="email" placeholder="Enter Email Address"
                                required
                                style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label"
                                    style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Username</label>
                                <input type="text" class="form-control" name="username" placeholder="Username" required
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label"
                                    style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Role</label>
                                <select class="form-select" name="role" required
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                                    <option value="" selected disabled>Select Role</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 position-relative">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" name="password" id="createPassword" required
                                    minlength="6"
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 45px 14px 16px; border-radius: 8px; font-size: 14px;">
                                <i class="bi bi-eye-slash position-absolute" id="toggleCreatePassword"
                                    style="right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px;"></i>
                            </div>
                        </div>

                        <div class="mb-4 position-relative">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Confirm
                                Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword"
                                    required minlength="6"
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 45px 14px 16px; border-radius: 8px; font-size: 14px;">
                                <i class="bi bi-eye-slash position-absolute" id="toggleConfirmPassword"
                                    style="right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal"
                            style="color: #888; font-weight: 600; font-size: 14px;">CANCEL</button>
                        <button type="submit" class="btn px-4 py-2"
                            style="background-color: #4C3939; color: white; border-radius: 8px; font-weight: 600; font-size: 14px;">Create
                            Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius: 16px; border: none; box-shadow: 0 25px 50px rgba(0,0,0,0.25);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h4 class="modal-title"
                        style="color: #2C1810; font-size: 24px; font-weight: 500; font-family: 'Playfair Display', Georgia, serif; font-style: italic;">
                        Edit User</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity: 0.5;"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="editUserId">

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 12px; font-weight: 600; color: #333;">Username</label>
                            <input type="text" class="form-control" id="editUsername" disabled
                                style="background: linear-gradient(135deg, #e8d5c4 0%, #dcc9b8 100%); border: none; padding: 14px 16px; border-radius: 8px; font-size: 14px; color: #666;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: #333;">Full
                                Name</label>
                            <input type="text" class="form-control" name="full_name" id="editFullName" required
                                style="background: white; border: 1px solid #e0e0e0; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 12px; font-weight: 600; color: #333;">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required
                                style="background: white; border: 1px solid #e0e0e0; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label"
                                    style="font-size: 12px; font-weight: 600; color: #333;">Role</label>
                                <select class="form-select" name="role" id="editRole" required
                                    style="background: white; border: 1px solid #e0e0e0; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label"
                                    style="font-size: 12px; font-weight: 600; color: #333;">Status</label>
                                <select class="form-select" name="status" id="editStatus" required
                                    style="background: white; border: 1px solid #e0e0e0; padding: 14px 16px; border-radius: 8px; font-size: 14px;">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0 justify-content-center gap-3">
                        <button type="button" class="btn px-4 py-2" data-bs-dismiss="modal"
                            style="background: white; border: 1px solid #ddd; color: #333; border-radius: 8px; font-weight: 500; font-size: 14px;">Cancel</button>
                        <button type="submit" class="btn px-4 py-2"
                            style="background-color: #4C3939; color: white; border-radius: 8px; font-weight: 600; font-size: 14px;">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // Password toggle for Create Account modal
        document.getElementById('toggleCreatePassword')?.addEventListener('click', function () {
            const input = document.getElementById('createPassword');
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.remove('bi-eye-slash');
                this.classList.add('bi-eye');
            } else {
                input.type = 'password';
                this.classList.remove('bi-eye');
                this.classList.add('bi-eye-slash');
            }
        });

        document.getElementById('toggleConfirmPassword')?.addEventListener('click', function () {
            const input = document.getElementById('confirmPassword');
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.remove('bi-eye-slash');
                this.classList.add('bi-eye');
            } else {
                input.type = 'password';
                this.classList.remove('bi-eye');
                this.classList.add('bi-eye-slash');
            }
        });

        // Legacy toggle function for other modals
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Edit user modal
        document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const user = JSON.parse(button.dataset.user);

            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editFullName').value = user.full_name;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editStatus').value = user.status;
        });

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