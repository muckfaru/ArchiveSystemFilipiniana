<?php
/**
 * Users Management Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';

// Get alert message
$alert = getAlert();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$whereClause = "WHERE deleted_at IS NULL";
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
        $fullName = $username; // Use username as full name
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        // Check if username or email exists
        // Check if username exists
        $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUsername->execute([$username]);
        if ($checkUsername->fetch()) {
            redirect($_SERVER['PHP_SELF'] . '?error=username_exists&old_email=' . urlencode($email) . '&old_username=' . urlencode($username));
        }

        // Check if email exists
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            redirect($_SERVER['PHP_SELF'] . '?error=email_exists&old_email=' . urlencode($email) . '&old_username=' . urlencode($username));
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([$username, $hashedPassword, $fullName, $email, $role]);

            logActivity($currentUser['id'], 'create_user', $username);
            // showAlert('success', 'User created successfully.');
            redirect($_SERVER['PHP_SELF'] . '?created=true');
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'edit') {
        $userId = intval($_POST['user_id']);
        // $fullName = sanitize($_POST['full_name']); // Removed
        $username = sanitize($_POST['username']); // Added
        $email = sanitize($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'];

        // Check for duplicates (server-side)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $checkStmt->execute([$username, $email, $userId]);
        if ($checkStmt->fetch()) {
            redirect($_SERVER['PHP_SELF'] . '?error=duplicate_edit&user_id=' . $userId);
        }

        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $updateStmt->execute([$username, $email, $role, $status, $userId]);

        logActivity($currentUser['id'], 'edit_user', $username);
        // showAlert('success', 'User updated successfully.');
        redirect($_SERVER['PHP_SELF'] . '?updated=true');
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

            // Soft delete - move to trash
            $deleteStmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $deleteStmt->execute([$currentUser['id'], $userId]);

            logActivity($currentUser['id'], 'delete_user', $deletedUser['username']);
            // Redirect with success flag for modal
            redirect($_SERVER['PHP_SELF'] . '?deleted=true');
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
    <link href="<?= APP_URL ?>/assets/css/pages/users.css" rel="stylesheet">
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../views/layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold m-0" style="font-size: 24px; color: #212529;">Users</h1>
                <div class="text-muted small">Create, edit, and manage system user accounts</div>
            </div>
            <button type="button" class="btn btn-primary px-4 py-2" data-bs-toggle="modal"
                data-bs-target="#createUserModal"
                style="background-color: #3A9AFF; border-color: #3A9AFF; font-weight: 500;">
                <i class="bi bi-person-plus-fill me-2"></i>Create Account
            </button>
        </div>

        <!-- Alert -->
        <!-- Alerts are now handled via Modals -->

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase fw-bold text-muted small mb-1"
                                style="font-size: 11px; letter-spacing: 1px;">Total Admins</div>
                            <div class="fw-bold display-6 text-dark mb-0"><?= $totalAdmins ?></div>
                        </div>
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                            style="width: 56px; height: 56px;">
                            <i class="bi bi-shield-lock-fill fs-3" style="color: #5F6368;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase fw-bold text-muted small mb-1"
                                style="font-size: 11px; letter-spacing: 1px;">Active Accounts</div>
                            <div class="fw-bold display-6 text-dark mb-0"><?= $activeAdmins ?></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 56px; height: 56px; background-color: rgba(46, 125, 50, 0.1);">
                            <i class="bi bi-broadcast fs-3 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Section -->
        <div class="card mb-4 border-0 shadow-sm rounded-4">
            <div class="card-body p-2">
                <form method="GET" class="d-flex align-items-center gap-2">
                    <!-- Search Input -->
                    <div class="position-relative flex-grow-1">
                        <i class="bi bi-search position-absolute text-muted"
                            style="left: 15px; top: 50%; transform: translateY(-50%); z-index: 5;"></i>
                        <input type="text" class="form-control border-0 bg-light rounded-pill ps-5 py-2" name="search"
                            id="searchInput" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>"
                            style="font-size: 14px; padding-right: 50px;">

                        <?php if (!empty($search)): ?>
                            <a href="users.php"
                                class="position-absolute d-flex align-items-center justify-content-center text-muted text-decoration-none"
                                style="right: 50px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; z-index: 10;"
                                title="Reset Filters">
                                <i class="bi bi-x-circle-fill"></i>
                            </a>
                        <?php endif; ?>

                        <button type="submit"
                            class="btn position-absolute end-0 top-0 bottom-0 m-1 rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 38px; height: 38px; background-color: #3A9AFF; color: white; border: none;">
                            <i class="bi bi-search" style="font-size: 14px;"></i>
                        </button>
                    </div>

                    <!-- Sort Dropdown -->
                    <select class="form-select border-0 bg-light rounded-pill py-2 ps-3 pe-5 shadow-none" name="sort"
                        onchange="this.form.submit()"
                        style="width: auto; min-width: 120px; font-size: 13px; font-weight: 600; cursor: pointer; background-position: right 1rem center;">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 py-3 text-uppercase text-secondary"
                            style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Username</th>
                        <th class="py-3 text-uppercase text-secondary"
                            style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Email</th>
                        <th class="py-3 text-uppercase text-secondary"
                            style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Role</th>
                        <th class="py-3 text-uppercase text-secondary"
                            style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Status</th>
                        <th class="py-3 text-uppercase text-secondary"
                            style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Last Login</th>
                        <th class="text-end pe-4 py-3 text-uppercase text-secondary"
                            style="font-size: 11px; font-weight: 700; letter-spacing: 0.8px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 bg-white">
                                <span class="text-muted">No users found.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="text-dark fw-medium" style="font-size: 14px;">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="text-muted" style="font-size: 14px;">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="text-dark fw-medium" style="font-size: 14px;">
                                        <?= ($user['role'] === 'super_admin') ? 'Administrator' : ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <?php
                                    $isActive = $user['status'] === 'active';
                                    $statusClass = $isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                                    $dotClass = $isActive ? 'bg-success' : 'bg-secondary';
                                    ?>
                                    <span
                                        class="badge rounded-pill <?= $statusClass ?> px-3 py-2 fw-medium border-0 d-inline-flex align-items-center gap-2"
                                        style="font-size: 12px;">
                                        <span class="rounded-circle <?= $dotClass ?>" style="width: 6px; height: 6px;"></span>
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 text-muted" style="font-size: 13px;">
                                    <?= $user['last_login'] ? date('Y-m-d h:i A', strtotime($user['last_login'])) : 'Never' ?>
                                </td>
                                <td class="text-end pe-4 py-3">
                                    <button type="button" class="btn btn-link p-0 text-muted me-2" data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-user='<?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>'
                                        title="Edit">
                                        <i class="bi bi-pencil-fill" style="font-size: 14px;"></i>
                                    </button>
                                    <?php if ($user['id'] !== $currentUser['id']): ?>
                                        <button type="button" class="btn btn-link p-0 text-danger ms-2"
                                            onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                            title="Delete"
                                            style="width: 24px; height: 24px; border-radius: 50%; background-color: rgba(220, 53, 69, 0.1); display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                            <i class="bi bi-trash-fill" style="font-size: 14px;"></i>
                                        </button>
                                    <?php endif; ?>
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
                    <span class="text-uppercase fw-bold text-secondary"
                        style="font-size: 11px; letter-spacing: 0.5px;">Rows per page:</span>
                    <select class="form-select form-select-sm border-0 bg-light rounded-2 py-1 pe-4" id="rowsPerPage"
                        style="width: 60px; font-weight: 500; cursor: pointer; background-position: right 0.5rem center;">
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

                <div class="pagination-circular">
                    <?php
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
                    $totalPages = ceil($totalUsers / $limit);
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

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius: 16px; border: none; box-shadow: 0 25px 50px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div>
                        <h4 class="modal-title"
                            style="color: #2C1810; font-size: 26px; font-weight: 600; font-family: 'Poppins', sans-serif;">
                            Create New Account</h4>
                        <p style="color: #888; font-size: 14px; margin: 0;">Add a new user to the Archive System</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        style="opacity: 0.5; width: 1.2rem; height: 1.2rem; position: absolute; top: 24px; right: 24px;"></button>
                </div>
                <form method="POST" id="createAccountForm">
                    <div class="modal-body px-4 py-4">
                        <input type="hidden" name="action" value="create">

                        <!-- Error Alert -->
                        <div id="createAccountError"
                            class="alert alert-danger d-none py-2 px-3 mb-3 small fw-bold border-0 bg-danger-subtle text-danger"
                            style="border-radius: 8px;">
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase"
                                style="font-size: 11px; letter-spacing: 0.5px;">Email</label>
                            <input type="email" class="form-control py-2 px-3 rounded-3" name="email"
                                placeholder="email@example.com" required
                                style="font-size: 14px; border: 1px solid #dee2e6;">
                            <div id="emailError" class="text-danger small mt-1 d-none"
                                style="font-size: 11px; font-weight: 600;"></div>
                        </div>

                        <!-- Username & Role -->
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary text-uppercase"
                                    style="font-size: 11px; letter-spacing: 0.5px;">Username</label>
                                <input type="text" class="form-control py-2 px-3 rounded-3" name="username"
                                    placeholder="jdoe_admin" required
                                    style="font-size: 14px; border: 1px solid #dee2e6;">
                                <div id="usernameError" class="text-danger small mt-1 d-none"
                                    style="font-size: 11px; font-weight: 600;"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary text-uppercase"
                                    style="font-size: 11px; letter-spacing: 0.5px;">Role</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control py-2 px-3 rounded-3 text-dark" value="Admin"
                                        readonly
                                        style="font-size: 14px; color: #495057; border: 1px solid #dee2e6; background-color: #f8f9fa;">
                                    <i class="bi bi-lock-fill position-absolute text-secondary"
                                        style="right: 12px; top: 50%; transform: translateY(-50%); font-size: 14px;"></i>
                                    <input type="hidden" name="role" value="admin">
                                </div>
                            </div>
                        </div>

                        <!-- Password & Confirm Password -->
                        <div class="row g-3 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary text-uppercase"
                                    style="font-size: 11px; letter-spacing: 0.5px;">Password</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control py-2 px-3 rounded-3" name="password"
                                        id="createPassword" required minlength="6"
                                        style="font-size: 14px; padding-right: 35px !important; border: 1px solid #dee2e6;">
                                    <i class="bi bi-eye-slash position-absolute text-muted" id="toggleCreatePassword"
                                        style="right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 16px;"></i>
                                </div>
                                <div id="passwordLengthMessage" class="small mt-1" style="font-size: 11px; font-weight: 600; min-height: 17px;"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary text-uppercase"
                                    style="font-size: 11px; letter-spacing: 0.5px;">Confirm Password</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control py-2 px-3 rounded-3"
                                        name="confirm_password" id="confirmPassword" required minlength="6"
                                        style="font-size: 14px; padding-right: 35px !important; border: 1px solid #dee2e6;">
                                    <i class="bi bi-eye-slash position-absolute text-muted" id="toggleConfirmPassword"
                                        style="right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 16px;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Validation Message -->
                        <div class="d-flex justify-content-end">
                            <div id="passwordMatchMessage" style="font-size: 11px; font-weight: 600; min-height: 17px;">
                            </div>
                        </div>
                    </div>

                    <div
                        class="modal-footer border-0 px-4 pb-4 pt-0 d-flex justify-content-end align-items-center gap-3">
                        <button type="button"
                            class="btn btn-link text-decoration-none text-secondary fw-bold text-uppercase"
                            data-bs-dismiss="modal" style="font-size: 12px; letter-spacing: 1px;">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 text-uppercase fw-bold"
                            style="background-color: #3A9AFF; border-color: #3A9AFF; font-size: 12px; letter-spacing: 1px;">
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius: 16px; border: none; box-shadow: 0 25px 50px rgba(0,0,0,0.25); font-family: 'Poppins', sans-serif;">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h4 class="modal-title"
                        style="color: #2C1810; font-size: 24px; font-weight: 500; font-family: 'Poppins', sans-serif;">
                        Edit User</h4>
                    <p style="color: #888; font-size: 14px; margin: 0;">Update user account details and permissions</p>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity: 0.5;"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="editUserId">

                        <!-- Error Alert for Edit Modal (Removed as per request) -->

                        <div class="mb-3">
                            <label class="form-label text-uppercase text-secondary small fw-bold"
                                style="font-size: 11px; letter-spacing: 0.5px;">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required
                                style="background: white; border: 1px solid #dee2e6; padding: 10px 16px; border-radius: 8px; font-size: 14px;">
                            <div class="invalid-feedback custom-feedback" id="editEmailFeedback"
                                style="display: none; font-size: 11px; font-weight: 600;"></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label text-uppercase text-secondary small fw-bold"
                                    style="font-size: 11px; letter-spacing: 0.5px;">Username</label>
                                <input type="text" class="form-control" name="username" id="editUsername" required
                                    style="background: white; border: 1px solid #dee2e6; padding: 10px 16px; border-radius: 8px; font-size: 14px;">
                                <div class="invalid-feedback custom-feedback" id="editUsernameFeedback"
                                    style="display: none; font-size: 11px; font-weight: 600;"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-uppercase text-secondary small fw-bold"
                                    style="font-size: 11px; letter-spacing: 0.5px;">Role</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control py-2 px-3 rounded-3 text-dark"
                                        id="editRoleDisplay" value="Admin" readonly
                                        style="font-size: 14px; color: #495057; border: 1px solid #dee2e6; background-color: #f8f9fa;">
                                    <i class="bi bi-lock-fill position-absolute text-secondary"
                                        style="right: 12px; top: 50%; transform: translateY(-50%); font-size: 14px;"></i>
                                    <input type="hidden" name="role" id="editRole" value="admin">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-uppercase text-secondary small fw-bold"
                                style="font-size: 11px; letter-spacing: 0.5px;">Status</label>
                            <select class="form-select" name="status" id="editStatus" required
                                style="background: white; border: 1px solid #dee2e6; padding: 10px 16px; border-radius: 8px; font-size: 14px;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0 justify-content-center gap-3">
                        <button type="button" class="btn px-4 py-2" data-bs-dismiss="modal"
                            style="background: white; border: 1px solid #ddd; color: #333; border-radius: 8px; font-weight: 500; font-size: 14px;">Cancel</button>
                        <button type="button" class="btn px-4 py-2" id="editSaveBtn" onclick="showSaveConfirmation()"
                            style="background-color: #3A9AFF; color: white; border-radius: 8px; font-weight: 600; font-size: 14px;">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-trash-fill text-danger" style="font-size: 48px;"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete User?</h5>
                    <p class="text-muted small mb-4">Are you sure you want to delete <span id="deleteUserName"
                            class="fw-bold text-dark"></span>? This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" id="deleteUserForm">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" id="deleteUserId">
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Deleted Successfully Modal -->
    <div class="modal fade" id="userDeletedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2">User Deleted</h5>
                    <p class="text-muted small mb-4">The user has been successfully moved to trash.</p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Changes Confirmation Modal -->
    <div class="modal fade" id="saveChangesModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-question-circle-fill text-primary"
                            style="font-size: 48px; color: #3A9AFF !important;"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Save Changes?</h5>
                    <p class="text-muted small mb-4">Are you sure you want to update this user's information?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" id="confirmSaveBtn"
                            style="background-color: #3A9AFF; border-color: #3A9AFF;">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Created Successfully Modal -->
    <div class="modal fade" id="userCreatedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2">Account Created!</h5>
                    <p class="text-muted small mb-4">The new user account has been successfully created.</p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Updated Successfully Modal -->
    <div class="modal fade" id="userUpdatedModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2">Account Updated!</h5>
                    <p class="text-muted small mb-4">The user account details have been successfully updated.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal"
                        style="background-color: #3A9AFF; border-color: #3A9AFF;">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="globalErrorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2">Error</h5>
                    <p class="text-muted small mb-4" id="globalErrorMessage"><?= $alert ? $alert['message'] : '' ?></p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../views/layouts/footer.php'; ?>

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

        // Validation Helper Functions
        function showValidation(input, feedbackId, message, isValid) {
            const feedback = document.getElementById(feedbackId);
            if (!isValid) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = message;
                feedback.style.display = 'block';
                feedback.className = 'invalid-feedback custom-feedback text-danger';
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                feedback.style.display = 'none';
            }
        }

        function clearValidation(input, feedbackId) {
            input.classList.remove('is-invalid');
            input.classList.remove('is-valid');
            document.getElementById(feedbackId).style.display = 'none';
        }

        // Functions for Edit User Modal
        async function checkAvailability(email, username, userId) {
            const formData = new FormData();
            if (email) formData.append('email', email);
            if (username) formData.append('username', username);
            formData.append('exclude_id', userId);

            try {
                const response = await fetch('check_user_availability.php', {
                    method: 'POST',
                    body: formData
                });
                return await response.json();
            } catch (error) {
                console.error('Error checking availability:', error);
                return { status: 'error' };
            }
        }

        // Real-time Validation for Edit Modal
        const editEmailInput = document.getElementById('editEmail');
        const editUsernameInput = document.getElementById('editUsername');
        const confirmSaveBtn = document.getElementById('confirmSaveBtn'); // Modal confirmation button
        const editSaveBtn = document.getElementById('editSaveBtn');     // Edit Form button
        const editUserError = document.getElementById('editUserError');

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        async function validateEditForm() {
            const email = editEmailInput.value.trim();
            const username = editUsernameInput.value.trim();
            const userId = document.getElementById('editUserId').value;

            let isValid = true;
            let errorMessage = '';

            // 1. Basic Validation (Empty & Format)
            if (!email) {
                showValidation(editEmailInput, 'editEmailFeedback', 'Email is required.', false);
                isValid = false;
            } else if (!isValidEmail(email)) {
                showValidation(editEmailInput, 'editEmailFeedback', 'Invalid email format.', false);
                isValid = false;
            } else {
                showValidation(editEmailInput, 'editEmailFeedback', '', true);
            }

            if (!username) {
                showValidation(editUsernameInput, 'editUsernameFeedback', 'Username is required.', false);
                isValid = false;
            } else {
                showValidation(editUsernameInput, 'editUsernameFeedback', '', true);
            }

            // If basic checks failed
            if (!isValid) {
                editSaveBtn.disabled = true;
                return;
            }

            // 2. Duplicate Check (Async)
            editSaveBtn.disabled = true;

            const result = await checkAvailability(email, username, userId);

            if (result.email_status === 'taken') {
                showValidation(editEmailInput, 'editEmailFeedback', 'Email is already in use.', false);
                errorMessage = 'Email address is already in use by another user.';
                isValid = false;
            }

            if (result.username_status === 'taken') {
                showValidation(editUsernameInput, 'editUsernameFeedback', 'Username is already in use.', false);
                if (errorMessage) errorMessage = 'Both Email and Username are already in use.';
                else errorMessage = 'Username is already in use by another user.';
                isValid = false;
            }

            // Show/Hide Global Alert - REMOVED redundant top alert
            if (!isValid && errorMessage) {
                // Logic to show top alert removed
            } else {
                // Logic to hide top alert removed
                // Re-confirm valid status visuals
                if (result.email_status !== 'taken') showValidation(editEmailInput, 'editEmailFeedback', '', true);
                if (result.username_status !== 'taken') showValidation(editUsernameInput, 'editUsernameFeedback', '', true);
            }

            editSaveBtn.disabled = !isValid;
        }

        editEmailInput.addEventListener('blur', validateEditForm);
        editUsernameInput.addEventListener('blur', validateEditForm);

        const initialCheck = () => {
            if (!editEmailInput.value.trim() || !editUsernameInput.value.trim()) {
                editSaveBtn.disabled = true;
            }
            debounceValidation();
        };

        // Edit user modal - Reset state
        document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
            let button = event.relatedTarget;
            // Ensure we handle clicks on the icon inside the button
            if (!button.hasAttribute('data-user') && button.closest('[data-user]')) {
                button = button.closest('[data-user]');
            }

            if (!button || !button.dataset.user) {
                console.error('User data not found on edit button');
                return;
            }

            let user;
            try {
                user = JSON.parse(button.dataset.user);
            } catch (e) {
                console.error('Error parsing user data:', e);
                return;
            }

            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRole').value = user.role;
            // Update visible role display
            const roleDisplay = user.role === 'super_admin' ? 'Administrator' : user.role.charAt(0).toUpperCase() + user.role.slice(1);
            const roleDisplayInput = document.getElementById('editRoleDisplay');
            if (roleDisplayInput) roleDisplayInput.value = roleDisplay;

            document.getElementById('editStatus').value = user.status;

            // Reset validation state
            clearValidation(document.getElementById('editEmail'), 'editEmailFeedback');
            clearValidation(document.getElementById('editUsername'), 'editUsernameFeedback');

            document.getElementById('editSaveBtn').disabled = false;
        });

        let validatingTimeout;
        const debounceValidation = () => {
            clearTimeout(validatingTimeout);
            validatingTimeout = setTimeout(validateEditForm, 500);
        };
        editEmailInput.addEventListener('input', initialCheck);
        editUsernameInput.addEventListener('input', initialCheck);


        // Delete User Confirmation
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            deleteModal.show();
        }

        // Save Changes Confirmation
        function showSaveConfirmation() {
            // Hide edit modal
            var editModalEl = document.getElementById('editUserModal');
            var editModal = bootstrap.Modal.getInstance(editModalEl);
            editModal.hide();

            // Show confirmation modal
            var saveModal = new bootstrap.Modal(document.getElementById('saveChangesModal'));
            saveModal.show();
        }

        document.getElementById('confirmSaveBtn').addEventListener('click', function () {
            document.getElementById('editUserForm').submit();
        });

        // Show User Deleted Modal if URL has deleted=true
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('deleted') === 'true') {
            var deletedModal = new bootstrap.Modal(document.getElementById('userDeletedModal'));
            deletedModal.show();
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (urlParams.get('created') === 'true') {
            var createdModal = new bootstrap.Modal(document.getElementById('userCreatedModal'));
            createdModal.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (urlParams.get('updated') === 'true') {
            var updatedModal = new bootstrap.Modal(document.getElementById('userUpdatedModal'));
            updatedModal.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Handle Create Account Errors
        const errorParam = urlParams.get('error');
        if (errorParam === 'username_exists' || errorParam === 'email_exists') {
            const createModalElement = document.getElementById('createUserModal');
            const createModal = new bootstrap.Modal(createModalElement);
            createModal.show();

            // Repopulate fields using specific selectors for the create modal
            const usernameInput = createModalElement.querySelector('input[name="username"]');
            const emailInput = createModalElement.querySelector('input[name="email"]');

            if (usernameInput) usernameInput.value = urlParams.get('old_username') || '';
            if (emailInput) emailInput.value = urlParams.get('old_email') || '';
        }

        // Handle Edit Account Duplicate Error
        if (errorParam === 'duplicate_edit') {
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
            // Alert removed as per request to avoid redundancy
        }

        // Show error
        const errorContainer = document.getElementById('createAccountError');
        if (errorContainer && (errorParam === 'username_exists' || errorParam === 'email_exists')) {
            errorContainer.classList.remove('d-none');
            errorContainer.innerHTML = errorParam === 'username_exists'
                ? '<i class="bi bi-exclamation-circle-fill me-2"></i>Username is already taken'
                : '<i class="bi bi-exclamation-circle-fill me-2"></i>Email is already registered';
        }

        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);


        // Show Error Modal if PHP alert exists
        <?php if ($alert && $alert['type'] === 'danger'): ?>
            var errorModal = new bootstrap.Modal(document.getElementById('globalErrorModal'));
            errorModal.show();
        <?php endif; ?>

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
        const searchForm = searchInput.closest('form');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    searchForm.submit();
                }, 600); // 600ms debounce
            });

            // Focus search input if it has value (after reload)
            if (searchInput.value) {
                searchInput.focus();
                // Move cursor to end
                const len = searchInput.value.length;
                searchInput.setSelectionRange(len, len);
            }
        }
        // Password and Form Validation with Real-time Duplicate Check
        const createPassword = document.getElementById('createPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        const matchMessage = document.getElementById('passwordMatchMessage');
        const createAccountForm = document.getElementById('createAccountForm');

        // New elements for validation
        const createUsernameInput = document.querySelector('#createUserModal input[name="username"]');
        const createEmailInput = document.querySelector('#createUserModal input[name="email"]');
        const usernameErrorEl = document.getElementById('usernameError');
        const emailErrorEl = document.getElementById('emailError');
        const submitBtn = createAccountForm ? createAccountForm.querySelector('button[type="submit"]') : null;

        // Flags
        let isUsernameValid = true; // Assume true until checked
        let isEmailValid = true;

        function checkFormValidity() {
            if (!submitBtn) return;

            const password = createPassword.value;
            const confirm = confirmPassword.value;
            const isPasswordMatch = password.length >= 6 && password === confirm;

            // Check if errors are displayed
            const hasUsernameError = !usernameErrorEl.classList.contains('d-none');
            const hasEmailError = !emailErrorEl.classList.contains('d-none');
            const isPasswordLengthValid = password.length >= 6;

            if (hasUsernameError || hasEmailError || !isPasswordMatch || !isPasswordLengthValid) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }

        // Duplicate Check Function
        function checkDuplicate(type, value, errorEl) {
            if (!value) {
                errorEl.classList.add('d-none');
                checkFormValidity();
                return;
            }

            const formData = new FormData();
            formData.append('type', type);
            formData.append('value', value);

            fetch('<?= APP_URL ?>/ajax/check_duplicate_user.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        errorEl.textContent = type === 'username' ? 'Username is already taken.' : 'Email is already registered.';
                        errorEl.classList.remove('d-none');
                    } else {
                        errorEl.classList.add('d-none');
                    }
                    checkFormValidity();
                })
                .catch(error => console.error('Error:', error));
        }

        // Debounce Function
        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // Attach Duplicate Check Listeners
        if (createUsernameInput) {
            createUsernameInput.addEventListener('input', debounce(function () {
                checkDuplicate('username', this.value.trim(), usernameErrorEl);
            }, 500));
        }

        if (createEmailInput) {
            createEmailInput.addEventListener('input', debounce(function () {
                checkDuplicate('email', this.value.trim(), emailErrorEl);
            }, 500));
        }

        function checkPasswordMatch() {
            const password = createPassword.value;
            const confirm = confirmPassword.value;
            const lengthMessage = document.getElementById('passwordLengthMessage');

            // Check password length first
            if (password.length > 0 && password.length < 6) {
                lengthMessage.textContent = `${password.length}/6 characters (minimum 6 required)`;
                lengthMessage.className = 'small mt-1 text-danger';
            } else if (password.length >= 6) {
                lengthMessage.textContent = `${password.length} characters ✓`;
                lengthMessage.className = 'small mt-1 text-success';
            } else {
                lengthMessage.textContent = '';
                lengthMessage.className = 'small mt-1';
            }

            // Only check password match if password meets minimum length
            if (confirm === '') {
                matchMessage.textContent = '';
                matchMessage.className = 'mt-1';
                checkFormValidity();
                return;
            }

            // Only show match/mismatch if password is at least 6 characters
            if (password.length >= 6) {
                if (password === confirm) {
                    matchMessage.textContent = '✓ Passwords match';
                    matchMessage.className = 'mt-1 text-success';
                } else {
                    matchMessage.textContent = '✗ Passwords do not match';
                    matchMessage.className = 'mt-1 text-danger';
                }
            } else {
                // Don't show match message if password is too short
                matchMessage.textContent = '';
                matchMessage.className = 'mt-1';
            }
            
            checkFormValidity();
        }

        if (createPassword && confirmPassword) {
            createPassword.addEventListener('input', checkPasswordMatch);
            confirmPassword.addEventListener('input', checkPasswordMatch);
        }

        // Prevent submission if mismatch or password too short (optional extra safety)
        if (createAccountForm) {
            createAccountForm.addEventListener('submit', function (e) {
                const password = createPassword.value;
                const confirm = confirmPassword.value;
                
                if (password.length < 6) {
                    e.preventDefault();
                    const lengthMessage = document.getElementById('passwordLengthMessage');
                    lengthMessage.textContent = 'Password must be at least 6 characters';
                    lengthMessage.className = 'small mt-1 text-danger';
                    createPassword.focus();
                    return;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    matchMessage.textContent = 'Passwords do not match';
                    matchMessage.className = 'mt-1 text-danger';
                    confirmPassword.focus();
                }
            });
        }
    </script>
</body>

</html>