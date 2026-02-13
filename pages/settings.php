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
            // showAlert('success', 'Password changed successfully.');
            redirect($_SERVER['PHP_SELF'] . '?password_changed=true');
        }
    }

    if ($action === 'update_appearance') {
        $darkMode = isset($_POST['dark_mode']) ? '1' : '0';
        updateSetting('dark_mode', $darkMode);

        logActivity($currentUser['id'], 'settings_update', 'Appearance settings');
        // showAlert('success', 'Appearance settings updated.');
        redirect($_SERVER['PHP_SELF'] . '?appearance_updated=true');
    }

    if ($action === 'update_storage') {
        $storagePath = sanitize($_POST['storage_path']);
        updateSetting('storage_path', $storagePath);

        logActivity($currentUser['id'], 'settings_update', 'Storage path');
        // showAlert('success', 'Storage path updated.');
        redirect($_SERVER['PHP_SELF'] . '?storage_updated=true');
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

            $stmt->execute([$currentUser['id']]);
            // showAlert('success', 'Profile photo removed.');
             redirect($_SERVER['PHP_SELF'] . '?photo_removed=true');
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
        <div class="page-header" style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
                <h1 class="page-title"
                    style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Playfair Display', Georgia, serif;">
                    Settings</h1>
                <p class="page-subtitle" style="color: #888; margin: 0;">Manage your account settings and preferences
                </p>
            </div>
            <div class="page-actions" style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-cancel-edit d-none" onclick="cancelEdit()"
                    style="background: white; border: 1px solid #ddd; color: #333; padding: 10px 24px; border-radius: 8px; font-weight: 500;">Cancel</button>
                <button type="button" class="btn btn-edit-profile" onclick="toggleEditMode()"
                    style="background: #4C3939; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 500;">
                    <i class="bi bi-pencil me-2"></i>Edit Profile
                </button>
                <button type="submit" form="profileForm" class="btn btn-save-changes d-none"
                    style="background: #4C3939; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 500;">
                    <i class="bi bi-check2 me-2"></i>Save Changes
                </button>
            </div>
        </div>

        <!-- Alerts replaced by Modals -->

        <div class="row g-4">
            <!-- Profile Settings -->
            <div class="col-lg-12">
                <div class="settings-card"
                    style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <div class="settings-card-header"
                        style="display: flex; align-items: center; gap: 10px; margin-bottom: 25px;">
                        <i class="bi bi-person" style="color: #666;"></i>
                        <span
                            style="font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 1px;">Profile
                            Settings</span>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-4">
                            <!-- Profile Photo Section -->
                            <div class="col-md-3 text-center">
                                <div style="position: relative; width: 120px; height: 120px; margin: 0 auto;">
                                    <?php if ($currentUser['profile_photo']): ?>
                                        <img src="<?= APP_URL ?>/<?= $currentUser['profile_photo'] ?>"
                                            style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #f0f0f0;"
                                            alt="Profile" class="profile-photo-preview" id="profilePhotoPreview">
                                    <?php else: ?>
                                        <div id="profilePhotoPreview"
                                            style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #f5f0ed 0%, #e8e0db 100%); display: flex; align-items: center; justify-content: center; border: 3px solid #f0f0f0;">
                                            <i class="bi bi-person" style="font-size: 50px; color: #999;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <label for="profilePhotoInput" class="edit-photo-btn d-none"
                                        style="position: absolute; bottom: 0; right: 0; width: 36px; height: 36px; background: #4C3939; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border: 2px solid #fff;">
                                        <i class="bi bi-camera" style="font-size: 14px; color: white;"></i>
                                    </label>
                                    <input type="file" id="profilePhotoInput" name="profile_photo" class="d-none"
                                        accept=".jpg,.jpeg,.png" disabled>
                                </div>
                                <p class="text-muted mt-2 mb-0" style="font-size: 12px;">Profile Photo</p>
                                <div class="photo-actions d-none mt-2">
                                    <label for="profilePhotoInput" class="btn btn-sm"
                                        style="background: #f8f6f5; border: 1px solid #ddd; font-size: 11px; padding: 4px 12px;">
                                        <i class="bi bi-upload me-1"></i>Upload
                                    </label>
                                    <?php if ($currentUser['profile_photo']): ?>
                                        <button type="button" class="btn btn-sm ms-1" onclick="removePhoto()"
                                            style="background: #fff5f5; border: 1px solid #FFCDD2; font-size: 11px; padding: 4px 12px; color: #dc3545;">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Form Fields -->
                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label
                                            style="font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Full
                                            Name</label>
                                        <input type="text" class="form-control profile-field" name="full_name"
                                            value="<?= htmlspecialchars($currentUser['full_name']) ?>" required readonly
                                            style="background: #f8f6f5; border: 1px solid transparent; padding: 14px 18px; border-radius: 10px; font-size: 14px;">
                                    </div>
                                    <div class="col-md-6">
                                        <label
                                            style="font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Username</label>
                                        <input type="text" class="form-control" name="username"
                                            value="<?= htmlspecialchars($currentUser['username']) ?>"
                                            style="background: #f8f6f5; border: 1px solid transparent; padding: 14px 18px; border-radius: 10px; font-size: 14px; color: #666;"
                                            readonly disabled>
                                        <small class="text-muted" style="font-size: 10px;">Username cannot be
                                            changed</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label
                                            style="font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Email
                                            Address</label>
                                        <input type="email" class="form-control profile-field" name="email"
                                            value="<?= htmlspecialchars($currentUser['email']) ?>" readonly
                                            style="background: #f8f6f5; border: 1px solid transparent; padding: 14px 18px; border-radius: 10px; font-size: 14px;">
                                    </div>
                                    <div class="col-md-6">
                                        <label
                                            style="font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Role</label>
                                        <input type="text" class="form-control"
                                            value="<?= ucfirst($currentUser['role'] === 'super_admin' ? 'Administrator' : $currentUser['role']) ?>"
                                            style="background: #f8f6f5; border: 1px solid transparent; padding: 14px 18px; border-radius: 10px; font-size: 14px; color: #666;"
                                            readonly disabled>
                                        <small class="text-muted" style="font-size: 10px;">Role cannot be
                                            changed</small>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="button" class="btn" data-bs-toggle="modal"
                                            data-bs-target="#changePasswordModal"
                                            style="background: #f8f6f5; border: 1px solid #ddd; color: #333; padding: 12px 24px; border-radius: 8px; font-size: 13px;">
                                            <i class="bi bi-key me-2"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="settings-card danger-zone"
                    style="background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-top: 20px; border-left: 4px solid #dc3545;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <div
                                style="width: 40px; height: 40px; background: #fff5f5; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-exclamation-triangle" style="color: #dc3545; font-size: 18px;"></i>
                            </div>
                            <div>
                                <span
                                    style="font-size: 12px; font-weight: 600; color: #dc3545; text-transform: uppercase; letter-spacing: 0.5px;">Danger
                                    Zone</span>
                                <h5 style="font-weight: 600; color: #333; margin: 4px 0;">Delete Account</h5>
                                <p style="color: #888; font-size: 13px; margin: 0;">Once you delete your account, there
                                    is no going back. All associated data will be purged.</p>
                            </div>
                        </div>
                        <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#deleteAccountModal"
                            style="background: transparent; border: 2px solid #dc3545; color: #dc3545; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Delete<br>Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Profile Update Success Modal -->
    <div class="modal fade" id="profileSuccessModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: 16px; border: none; text-align: center; padding: 30px;">
                <div
                    style="width: 70px; height: 70px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="bi bi-check-lg" style="font-size: 36px; color: #28a745;"></i>
                </div>
                <h5 style="font-weight: 600; color: #333; margin-bottom: 10px;">Profile Updated!</h5>
                <p style="color: #888; font-size: 14px; margin-bottom: 20px;">Your profile changes have been saved
                    successfully.</p>
                <button type="button" class="btn" data-bs-dismiss="modal"
                    style="background: #4C3939; color: white; padding: 10px 30px; border-radius: 8px; font-weight: 500;">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius: 16px; border: none; box-shadow: 0 25px 50px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: #f5f5f5; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-arrow-repeat" style="font-size: 20px; color: #666;"></i>
                        </div>
                        <h5 class="modal-title" style="font-weight: 700; color: #2C1810; margin: 0;">CHANGE PASSWORD
                        </h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity: 0.5;"></button>
                </div>
                <form method="POST" id="changePasswordForm">
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Current
                                Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" name="current_password" id="currentPassword"
                                    placeholder="Enter current password" required
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 45px 14px 16px; border-radius: 8px; font-size: 14px;">
                                <i class="bi bi-eye-slash position-absolute" id="toggleCurrentPassword"
                                    style="right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px;"></i>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">New
                                Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" name="new_password" id="newPassword"
                                    placeholder="Enter new password" required minlength="6"
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 45px 14px 16px; border-radius: 8px; font-size: 14px;">
                                <i class="bi bi-eye-slash position-absolute" id="toggleNewPassword"
                                    style="right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px;"></i>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Confirm
                                Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" name="confirm_password"
                                    id="confirmNewPassword" placeholder="Re-type new password" required minlength="6"
                                    style="background: linear-gradient(135deg, #f8f6f5 0%, #f0eeec 100%); border: none; padding: 14px 45px 14px 16px; border-radius: 8px; font-size: 14px;">
                                <i class="bi bi-eye-slash position-absolute" id="toggleConfirmNewPassword"
                                    style="right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal"
                            style="color: #888; font-weight: 600; font-size: 14px;">CANCEL</button>
                        <button type="submit" class="btn px-4 py-2"
                            style="background-color: #4C3939; color: white; border-radius: 8px; font-weight: 600; font-size: 14px;">UPDATE
                            PASSWORD</button>
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

    <!-- Generic Success Modal -->
    <div class="modal fade" id="genericSuccessModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                         <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2" id="successModalTitle">Success</h5>
                    <p class="text-muted small mb-4" id="successModalMessage">Operation completed successfully.</p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="settingsErrorModal" tabindex="-1">
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
                    <p class="text-muted small mb-4" id="errorMessage"><?= $alert ? $alert['message'] : '' ?></p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Check for URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        
        function showSuccessModal(title, message) {
            document.getElementById('successModalTitle').textContent = title;
            document.getElementById('successModalMessage').textContent = message;
            new bootstrap.Modal(document.getElementById('genericSuccessModal')).show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (urlParams.get('password_changed') === 'true') {
            showSuccessModal('Password Changed', 'Your password has been successfully updated.');
        } else if (urlParams.get('appearance_updated') === 'true') {
            showSuccessModal('Appearance Updated', 'Your appearance settings have been saved.');
        } else if (urlParams.get('storage_updated') === 'true') {
             showSuccessModal('Storage Updated', 'Storage path has been updated.');
        } else if (urlParams.get('photo_removed') === 'true') {
             showSuccessModal('Photo Removed', 'Your profile photo has been removed.');
        }

        // Show Error Modal if PHP alert exists
        <?php if ($alert && $alert['type'] === 'danger'): ?>
            new bootstrap.Modal(document.getElementById('settingsErrorModal')).show();
        <?php endif; ?>

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

        // Password toggle for Change Password modal
        function setupPasswordToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            if (toggle) {
                toggle.addEventListener('click', function () {
                    const input = document.getElementById(inputId);
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
            }
        }

        setupPasswordToggle('toggleCurrentPassword', 'currentPassword');
        setupPasswordToggle('toggleNewPassword', 'newPassword');
        setupPasswordToggle('toggleConfirmNewPassword', 'confirmNewPassword');

        // Edit Mode Toggle
        let isEditMode = false;
        let originalValues = {};

        function toggleEditMode() {
            isEditMode = true;

            // Save original values
            document.querySelectorAll('.profile-field').forEach(field => {
                originalValues[field.name] = field.value;
            });

            // Show/hide buttons
            document.querySelector('.btn-edit-profile').classList.add('d-none');
            document.querySelector('.btn-save-changes').classList.remove('d-none');
            document.querySelector('.btn-cancel-edit').classList.remove('d-none');

            // Enable editable fields
            document.querySelectorAll('.profile-field').forEach(field => {
                field.removeAttribute('readonly');
                field.style.border = '1px solid #C08B5C';
                field.style.background = '#fff';
            });

            // Enable photo upload
            document.getElementById('profilePhotoInput').disabled = false;
            document.querySelector('.edit-photo-btn').classList.remove('d-none');
            document.querySelector('.photo-actions').classList.remove('d-none');
        }

        function cancelEdit() {
            isEditMode = false;

            // Restore original values
            document.querySelectorAll('.profile-field').forEach(field => {
                if (originalValues[field.name] !== undefined) {
                    field.value = originalValues[field.name];
                }
            });

            // Show/hide buttons
            document.querySelector('.btn-edit-profile').classList.remove('d-none');
            document.querySelector('.btn-save-changes').classList.add('d-none');
            document.querySelector('.btn-cancel-edit').classList.add('d-none');

            // Disable editable fields
            document.querySelectorAll('.profile-field').forEach(field => {
                field.setAttribute('readonly', true);
                field.style.border = '1px solid transparent';
                field.style.background = '#f8f6f5';
            });

            // Disable photo upload
            document.getElementById('profilePhotoInput').disabled = true;
            document.querySelector('.edit-photo-btn').classList.add('d-none');
            document.querySelector('.photo-actions').classList.add('d-none');
        }

        // Handle form submission with success modal
        document.getElementById('profileForm').addEventListener('submit', function (e) {
            // Store form data in session storage to show modal after reload
            sessionStorage.setItem('profileUpdated', 'true');
        });

        // Show success modal if profile was just updated
        document.addEventListener('DOMContentLoaded', function () {
            if (sessionStorage.getItem('profileUpdated') === 'true') {
                sessionStorage.removeItem('profileUpdated');
                const successModal = new bootstrap.Modal(document.getElementById('profileSuccessModal'));
                successModal.show();
            }
        });

        function removePhoto() {
            // Add hidden input to indicate photo removal
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_photo';
            input.value = '1';
            document.getElementById('profileForm').appendChild(input);

            // Update preview
            const preview = document.getElementById('profilePhotoPreview');
            if (preview.tagName === 'IMG') {
                preview.outerHTML = '<div id="profilePhotoPreview" style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #f5f0ed 0%, #e8e0db 100%); display: flex; align-items: center; justify-content: center; border: 3px solid #f0f0f0;"><i class="bi bi-person" style="font-size: 50px; color: #999;"></i></div>';
            }
        }
    </script>
</body>

</html>