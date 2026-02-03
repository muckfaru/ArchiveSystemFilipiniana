<?php
/**
 * Settings Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get alert message
$alert = getAlert();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);

        // Check if username or email already exists (for other users)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $checkStmt->execute([$username, $email, $currentUser['id']]);

        if ($checkStmt->fetch()) {
            showAlert('danger', 'Username or email already in use by another account.');
        } else {
            // Handle profile photo upload
            $profilePhoto = $currentUser['profile_photo'];
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_photo'];
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
                    $newFileName = 'profile_' . $currentUser['id'] . '_' . time() . '.' . $fileExt;
                    $uploadPath = UPLOAD_PATH . 'profiles/' . $newFileName;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        // Delete old photo
                        if ($profilePhoto) {
                            $oldPath = UPLOAD_PATH . '../' . $profilePhoto;
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $profilePhoto = 'uploads/profiles/' . $newFileName;
                    }
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, profile_photo = ? WHERE id = ?");
            $stmt->execute([$fullName, $username, $email, $profilePhoto, $currentUser['id']]);

            logActivity($currentUser['id'], 'settings_update', 'Profile updated');
            showAlert('success', 'Profile updated successfully.');

            // Refresh current user data
            $currentUser = getCurrentUser();
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (!password_verify($currentPassword, $currentUser['password'])) {
            showAlert('danger', 'Current password is incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            showAlert('danger', 'New passwords do not match.');
        } elseif (strlen($newPassword) < 6) {
            showAlert('danger', 'Password must be at least 6 characters.');
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $currentUser['id']]);

            logActivity($currentUser['id'], 'settings_update', 'Password changed');
            showAlert('success', 'Password changed successfully.');
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'update_appearance') {
        $darkMode = isset($_POST['dark_mode']) ? '1' : '0';
        updateSetting('dark_mode', $darkMode);

        logActivity($currentUser['id'], 'settings_update', 'Appearance settings');
        showAlert('success', 'Appearance settings updated.');
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'update_storage') {
        $storagePath = sanitize($_POST['storage_path']);
        updateSetting('storage_path', $storagePath);

        logActivity($currentUser['id'], 'settings_update', 'Storage path');
        showAlert('success', 'Storage path updated.');
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'delete_account') {
        // Prevent deletion of last admin
        $adminCount = countTotalAdmins();

        if ($adminCount <= 1) {
            showAlert('danger', 'Cannot delete the last admin account.');
        } else {
            // Delete profile photo
            if ($currentUser['profile_photo']) {
                $photoPath = UPLOAD_PATH . '../' . $currentUser['profile_photo'];
                if (file_exists($photoPath)) {
                    unlink($photoPath);
                }
            }

            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);

            // Destroy session and redirect
            session_destroy();
            header("Location: " . APP_URL . "/index.php");
            exit;
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'remove_photo') {
        if ($currentUser['profile_photo']) {
            $photoPath = UPLOAD_PATH . '../' . $currentUser['profile_photo'];
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }

            $stmt = $pdo->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?");
            $stmt->execute([$currentUser['id']]);

            showAlert('success', 'Profile photo removed.');
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get current settings
$darkMode = getSetting('dark_mode', '0');
$storagePath = getSetting('storage_path', 'uploads/newspapers');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings -
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

<body class="<?= $darkMode === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-subtitle">Manage system preferences and configurations</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn btn-secondary" onclick="location.reload()">Cancel</button>
                <button type="submit" form="profileForm" class="btn btn-primary">
                    <i class="bi bi-check2 me-2"></i>Save Changes
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

        <div class="row g-4">
            <!-- Profile Settings -->
            <div class="col-lg-8">
                <div class="settings-section">
                    <div class="settings-section-title">
                        <i class="bi bi-person"></i>
                        PROFILE SETTINGS
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="profile-photo-wrapper mx-auto">
                                    <?php if ($currentUser['profile_photo']): ?>
                                        <img src="<?= APP_URL ?>/<?= $currentUser['profile_photo'] ?>" class="profile-photo"
                                            alt="Profile">
                                    <?php else: ?>
                                        <div
                                            class="profile-photo bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="bi bi-person text-white" style="font-size: 48px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <label class="profile-photo-edit" for="profilePhotoInput">
                                        <i class="bi bi-pencil"></i>
                                    </label>
                                    <input type="file" id="profilePhotoInput" name="profile_photo" class="d-none"
                                        accept=".jpg,.jpeg,.png">
                                </div>
                                <div class="mt-2">
                                    <label for="profilePhotoInput"
                                        class="btn btn-link btn-sm p-0 text-primary">UPLOAD</label>
                                    <?php if ($currentUser['profile_photo']): ?>
                                        <button type="submit" name="action" value="remove_photo"
                                            class="btn btn-link btn-sm p-0 text-danger ms-2"
                                            formaction="<?= $_SERVER['PHP_SELF'] ?>">REMOVE</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">FULL NAME</label>
                                        <input type="text" class="form-control" name="full_name"
                                            value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">USERNAME</label>
                                        <input type="text" class="form-control" name="username"
                                            value="<?= htmlspecialchars($currentUser['username']) ?>"
                                            style="background-color: #e9ecef;" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">EMAIL ADDRESS</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($currentUser['email']) ?>"
                                            style="background-color: #e9ecef;" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal"
                                            data-bs-target="#changePasswordModal">
                                            <i class="bi bi-key me-2"></i>CHANGE PASSWORD
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appearance & Storage -->
            <div class="col-lg-4">
                <!-- Appearance -->
                <div class="settings-section">
                    <div class="settings-section-title">
                        <i class="bi bi-palette"></i>
                        APPEARANCE
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_appearance">

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>DARK MODE</strong>
                                <p class="text-muted small mb-0">Reduces eye strain in low-light environments</p>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="darkModeToggle" name="dark_mode"
                                    <?= $darkMode === '1' ? 'checked' : '' ?> onchange="this.form.submit()">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Storage -->
                <div class="settings-section">
                    <div class="settings-section-title">
                        <i class="bi bi-hdd"></i>
                        STORAGE
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_storage">

                        <label class="form-label">STORAGE PATH</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="storage_path"
                                value="<?= htmlspecialchars($storagePath) ?>"
                                style="background-color: #424242; color: #fff;">
                            <button type="submit" class="btn btn-secondary">
                                <i class="bi bi-folder"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="col-12">
                <div class="settings-section danger">
                    <div class="settings-section-title danger-title">
                        <i class="bi bi-exclamation-triangle"></i>
                        DANGER ZONE
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>DELETE ACCOUNT</strong>
                            <p class="text-muted small mb-0">Once you delete your account, there is no going back</p>
                        </div>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                            data-bs-target="#deleteAccountModal">
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. Your account and all associated data
                        will be permanently deleted.
                    </div>
                    <p>Type <strong>DELETE</strong> to confirm:</p>
                    <input type="text" class="form-control" id="deleteConfirmation" placeholder="Type DELETE">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete_account">
                        <button type="submit" class="btn btn-danger" id="deleteAccountBtn" disabled>
                            Delete My Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // Delete confirmation
        document.getElementById('deleteConfirmation').addEventListener('input', function () {
            document.getElementById('deleteAccountBtn').disabled = this.value !== 'DELETE';
        });

        // Profile photo preview
        document.getElementById('profilePhotoInput').addEventListener('change', function () {
            if (this.files.length > 0) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.querySelector('.profile-photo').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>

</html>