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
                <div
                    style="background: white; border-radius: 16px; padding: 20px 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative;">
                    <span style="font-size: 13px; color: #888;">Total Admins</span>
                    <div style="font-size: 36px; font-weight: 600; color: #333; margin-top: 5px;"><?= $totalAdmins ?>
                    </div>
                    <div
                        style="position: absolute; top: 20px; right: 20px; width: 40px; height: 40px; background: #f5f5f5; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people" style="font-size: 18px; color: #666;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div
                    style="background: white; border-radius: 16px; padding: 20px 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative;">
                    <span style="font-size: 13px; color: #888;">Active Accounts</span>
                    <div style="font-size: 36px; font-weight: 600; color: #333; margin-top: 5px;"><?= $activeAdmins ?>
                    </div>
                    <div
                        style="position: absolute; top: 20px; right: 20px; width: 40px; height: 40px; background: #e8f5e9; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-broadcast" style="font-size: 18px; color: #4caf50;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div
            style="background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 20px;">
            <form method="GET" style="display: flex; align-items: center; gap: 15px;">
                <div style="flex: 1; position: relative;">
                    <i class="bi bi-search"
                        style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                    <input type="text" class="form-control" name="search"
                        placeholder="Search by username, email or role..." value="<?= htmlspecialchars($search) ?>"
                        style="padding: 12px 15px 12px 42px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                </div>
                <button type="submit" class="btn"
                    style="background: #4C3939; color: white; padding: 12px 25px; border-radius: 8px; font-weight: 500;">Search</button>
                <select class="form-select" name="sort" onchange="this.form.submit()"
                    style="width: auto; padding: 12px 35px 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest ↓</option>
                    <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest ↑</option>
                </select>
            </form>
        </div>

        <!-- Users Table -->
        <div style="background: white; border-radius: 16px; padding: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden;">
            <table class="table" style="margin: 0;">
                <thead>
                    <tr style="background: #fafafa;">
                        <th style="padding: 15px 20px; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Username</th>
                        <th style="padding: 15px 20px; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Role</th>
                        <th style="padding: 15px 20px; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Status</th>
                        <th style="padding: 15px 20px; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Last Login</th>
                        <th style="padding: 15px 20px; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border: none; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4" style="border: none;">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 15px 20px; border: none;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 32px; height: 32px; background: #C08B5C; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-person-fill" style="color: white; font-size: 14px;"></i>
                                        </div>
                                        <span style="font-weight: 500; color: #333;"><?= htmlspecialchars($user['username']) ?></span>
                                    </div>
                                </td>
                                <td style="padding: 15px 20px; border: none; color: #666;">
                                    <?= ($user['role'] === 'super_admin') ? 'Admin' : 'Admin' ?>
                                </td>
                                <td style="padding: 15px 20px; border: none;">
                                    <span style="display: inline-flex; align-items: center; gap: 5px; color: <?= $user['status'] === 'active' ? '#4caf50' : '#999' ?>; font-size: 13px;">
                                        <span style="width: 6px; height: 6px; background: <?= $user['status'] === 'active' ? '#4caf50' : '#999' ?>; border-radius: 50%;"></span>
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px 20px; border: none; color: #666; font-size: 13px;">
                                    <?= $user['last_login'] ? formatDate($user['last_login']) : 'Never' ?>
                                </td>
                                <td style="padding: 15px 20px; border: none; text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="btn" data-bs-toggle="modal"
                                            data-bs-target="#editUserModal" data-user='<?= json_encode($user) ?>'
                                            style="width: 36px; height: 36px; background: #f5f5f5; border: none; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-pencil" style="color: #666;"></i>
                                        </button>
                                        <?php if ($user['id'] !== $currentUser['id']): ?>
                                            <form method="POST" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn"
                                                    style="width: 36px; height: 36px; background: #fff5f5; border: none; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="bi bi-trash" style="color: #dc3545;"></i>
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
            <div class="pagination-wrapper">
                <div class="d-flex align-items-center gap-3">
                    <span class="pagination-info">Rows per page</span>
                    <select class="form-select form-select-sm" style="width: auto;" id="rowsPerPage">
                        <option value="4" <?= $limit === 4 ? 'selected' : '' ?>>4</option>
                        <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    </select>
                    <span class="pagination-info">Showing
                        <?= count($users) ?> of
                        <?= $totalUsers ?> users
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