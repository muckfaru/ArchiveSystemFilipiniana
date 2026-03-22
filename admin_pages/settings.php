<?php
/**
 * Settings Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';

// Get alert message
$alert = getAlert();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);

        // Check if email already exists (for other users)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $currentUser['id']]);

        if ($checkStmt->fetch()) {
            showAlert('danger', 'Email already in use by another account.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $currentUser['id']]);

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
            header("Location: " . route_url('login'));
            exit;
        }
        redirect($_SERVER['PHP_SELF']);
    }

    // Removed remove_photo action
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
    <link href="<?= APP_URL ?>/assets/css/admin_pages/settings.css" rel="stylesheet">
</head>

<body class="<?= $darkMode === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../views/layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header" style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
                <h1 class="page-title"
                    style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Poppins', sans-serif;">
                    Settings</h1>
                <p class="page-subtitle" style="color: #888; margin: 0;">Manage your account settings and preferences
                </p>
            </div>
            <div class="page-actions" style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-cancel-edit d-none" onclick="cancelEdit()"
                    style="background: white; border: 1px solid #ddd; color: #333; padding: 10px 24px; border-radius: 8px; font-weight: 500;">Cancel</button>
                <button type="button" class="btn btn-edit-profile" onclick="toggleEditMode()"
                    style="background: #3A9AFF; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 500;">
                    <i class="bi bi-pencil me-2"></i>Edit Profile
                </button>
                <button type="submit" form="profileForm" class="btn btn-save-changes d-none"
                    style="background: #3A9AFF; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 500;">
                    <i class="bi bi-check2 me-2"></i>Save Changes
                </button>
            </div>
        </div>

        <!-- Alerts replaced by Modals -->

        <div class="row g-4">
            <!-- Profile Settings -->
            <div class="col-lg-12">
                <div class="settings-card"
                    style="background: white; border-radius: 0; border: 1px solid #eaeaea; padding: 40px; margin-bottom: 30px;">
                    <div class="settings-card-header"
                        style="display: flex; align-items: center; gap: 10px; margin-bottom: 35px; border-bottom: 1px solid #f1f1f1; padding-bottom: 20px;">
                        <i class="bi bi-person" style="color: #6B7280; font-size: 18px;"></i>
                        <span
                            style="font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 1.5px; font-family: 'Poppins', sans-serif;">PROFILE
                            SETTINGS</span>
                    </div>

                    <form method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-5">
                            <!-- Left Column: Name & Email -->
                            <div class="col-md-6">
                                <div class="mb-4 pb-2">
                                    <label
                                        style="font-size: 11px; font-weight: 700; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block;">NAME</label>
                                    <input type="text" class="form-control profile-field" name="full_name"
                                        value="<?= htmlspecialchars($currentUser['full_name']) ?>" required readonly
                                        style="background: #f8f9fa; border: 1px solid transparent; padding: 14px 18px; border-radius: 8px; font-size: 14px; color: #2c3e50; font-weight: 500;">
                                </div>
                                <div class="mb-4">
                                    <label
                                        style="font-size: 11px; font-weight: 700; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block;">EMAIL
                                        ADDRESS</label>
                                    <input type="email" class="form-control profile-field" name="email"
                                        value="<?= htmlspecialchars($currentUser['email']) ?>" required readonly
                                        style="background: #f8f9fa; border: 1px solid transparent; padding: 14px 18px; border-radius: 8px; font-size: 14px; color: #2c3e50; font-weight: 500;">
                                </div>

                                <div class="mt-5 pt-3">
                                    <button type="button" class="btn" data-bs-toggle="modal"
                                        data-bs-target="#changePasswordModal"
                                        style="background: #3A9AFF; border: 1px solid #3A9AFF; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; font-family: 'Poppins', sans-serif;">
                                        <i class="bi bi-key-fill" style="color: white; font-size: 16px;"></i> Change
                                        Password
                                    </button>
                                </div>
                            </div>

                            <!-- Right Column: Username & Role (Read Only) -->
                            <div class="col-md-6">
                                <div class="mb-4 pb-2">
                                    <label
                                        style="font-size: 11px; font-weight: 700; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block;">USERNAME</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control"
                                            value="<?= htmlspecialchars($currentUser['username']) ?>"
                                            style="background: #f1f3f5; border: 1px solid transparent; padding: 14px 18px; border-radius: 8px; font-size: 14px; color: #7f8c8d; font-weight: 500; cursor: not-allowed;"
                                            readonly disabled>
                                        <i class="bi bi-lock-fill position-absolute"
                                            style="right: 18px; top: 50%; transform: translateY(-50%); color: #bdc3c7; font-size: 14px;"></i>
                                    </div>
                                    <small class="text-muted"
                                        style="font-size: 11px; font-style: italic; margin-top: 8px; display: block; color: #bdc3c7 !important;">Username
                                        cannot be changed</small>
                                </div>

                                <div class="mb-4">
                                    <label
                                        style="font-size: 11px; font-weight: 700; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block;">ROLE</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control"
                                            value="<?= ucfirst($currentUser['role'] === 'super_admin' ? 'Administrator' : $currentUser['role']) ?>"
                                            style="background: #f1f3f5; border: 1px solid transparent; padding: 14px 18px; border-radius: 8px; font-size: 14px; color: #7f8c8d; font-weight: 500; cursor: not-allowed;"
                                            readonly disabled>
                                        <i class="bi bi-lock-fill position-absolute"
                                            style="right: 18px; top: 50%; transform: translateY(-50%); color: #bdc3c7; font-size: 14px;"></i>
                                    </div>
                                    <small class="text-muted"
                                        style="font-size: 11px; font-style: italic; margin-top: 8px; display: block; color: #bdc3c7 !important;">Role
                                        cannot be changed</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="settings-card danger-zone"
                    style="background: #fffcfc; border: 1px solid #ffdcdb; box-shadow: none; border-radius: 4px; padding: 30px; margin-top: 40px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: flex-start; gap: 20px;">
                            <div
                                style="width: 48px; height: 48px; min-width: 48px; background: #ffebeb; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-exclamation-triangle-fill" style="color: #ff4d4f; font-size: 20px;"></i>
                            </div>
                            <div>
                                <span
                                    style="font-size: 11px; font-weight: 800; color: #ff4d4f; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">DANGER
                                    ZONE</span>
                                <h5 style="font-weight: 700; color: #2c2c2c; margin: 0 0 6px 0; font-size: 18px;">Delete
                                    Account</h5>
                                <p style="color: #6c757d; font-size: 13px; margin: 0; line-height: 1.5;">Once you delete
                                    your account, there is no going back. All associated<br>data will be purged
                                    immediately. Please be certain.</p>
                            </div>
                        </div>
                        <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#deleteAccountModal"
                            style="background: #DC2626; border: none; color: #fff; padding: 10px 24px; border-radius: 4px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                            DELETE ACCOUNT
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Profile Update Success Modal -->
    <div class="modal fade" id="profileSuccessModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
            <div class="modal-content" style="border-radius: 16px; border: none; text-align: center; padding: 30px;">
                <div
                    style="width: 70px; height: 70px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="bi bi-check-lg" style="font-size: 36px; color: #28a745;"></i>
                </div>
                <h5 style="font-weight: 600; color: #333; margin-bottom: 10px;">Profile Updated!</h5>
                <p style="color: #888; font-size: 14px; margin-bottom: 20px;">Your profile changes have been saved
                    successfully.</p>
                <button type="button" class="btn" data-bs-dismiss="modal"
                    style="background: #3A9AFF; color: white; padding: 10px 30px; border-radius: 8px; font-weight: 500;">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
            <div class="modal-content"
                style="border-radius: 16px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.12);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title" style="font-weight: 700; color: #111827; font-size: 16px; margin: 0;">Change
                        Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity: 0.5;"></button>
                </div>
                <form method="POST" id="changePasswordForm">
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px;">Current
                                Password</label>
                            <input type="password" class="form-control" name="current_password" id="cpCurrent"
                                placeholder="Current password" required
                                style="border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; padding: 10px 14px;">
                            <div id="cpCurrentWarning"
                                style="font-size: 12px; color: #DC2626; margin-top: 4px; display:none;">
                                <i class="bi bi-exclamation-circle-fill"></i> Current password is incorrect.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px;">New
                                Password</label>
                            <input type="password" class="form-control" name="new_password" id="cpNew"
                                placeholder="New password (min 6 chars)" required minlength="6"
                                style="border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; padding: 10px 14px;">
                        </div>

                        <div class="mb-2">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px;">Confirm
                                Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="cpConfirm"
                                placeholder="Re-type new password" required minlength="6"
                                style="border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; padding: 10px 14px;">
                            <div id="cpMatchMsg" style="font-size: 12px; margin-top: 4px;"></div>
                        </div>

                        <!-- Show all passwords checkbox -->
                        <div style="display:flex; align-items:center; gap:7px; margin-top:10px; margin-bottom:2px;">
                            <input type="checkbox" id="cpShowAll"
                                style="width:15px;height:15px;accent-color:#3A9AFF;cursor:pointer;flex-shrink:0;">
                            <label for="cpShowAll"
                                style="font-size:12px;font-weight:500;color:#6B7280;margin:0;cursor:pointer;">Show
                                passwords</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <div style="display:flex; gap:10px; width:100%;">
                            <button type="button" class="btn" data-bs-dismiss="modal"
                                style="flex:1; background:#F3F4F6; color:#374151; border:none; border-radius:8px; font-weight:600; font-size:13px; padding:11px 0;">Cancel</button>
                            <button type="submit" id="cpSubmit" class="btn"
                                style="flex:1; background-color:#3A9AFF; color:white; border:none; border-radius:8px; font-weight:600; font-size:13px; padding:11px 0;">Update
                                Password</button>
                        </div>

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

    <?php include __DIR__ . '/../views/layouts/footer.php'; ?>

    <!-- Generic Success Modal -->
    <div class="modal fade" id="genericSuccessModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
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
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
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

        // Change Password Modal - new UI wiring
        (function () {
            const cpCurrent = document.getElementById('cpCurrent');
            const cpNew = document.getElementById('cpNew');
            const cpConfirm = document.getElementById('cpConfirm');
            const cpShowAll = document.getElementById('cpShowAll');
            const cpMatchMsg = document.getElementById('cpMatchMsg');
            const cpCurrentWarning = document.getElementById('cpCurrentWarning');

            // Show all passwords checkbox
            cpShowAll.addEventListener('change', function () {
                const t = this.checked ? 'text' : 'password';
                cpCurrent.type = t;
                cpNew.type = t;
                cpConfirm.type = t;
            });

            // Live match indicator
            function checkMatch() {
                const nv = cpNew.value;
                const cv = cpConfirm.value;
                if (!cv) { cpMatchMsg.textContent = ''; return; }
                if (nv === cv) {
                    cpMatchMsg.innerHTML = '<span style="color:#22C55E;"><i class="bi bi-check-circle-fill"></i> Passwords match</span>';
                } else {
                    cpMatchMsg.innerHTML = '<span style="color:#DC2626;"><i class="bi bi-x-circle-fill"></i> Passwords do not match</span>';
                }
            }
            cpNew.addEventListener('input', checkMatch);
            cpConfirm.addEventListener('input', checkMatch);

            // Current password AJAX check (debounced)
            let cpTimer;
            cpCurrent.addEventListener('input', function () {
                clearTimeout(cpTimer);
                cpCurrentWarning.style.display = 'none';
                const val = this.value;
                if (!val) return;
                cpTimer = setTimeout(function () {
                    fetch('<?= APP_URL ?>/backend/api/auth/check_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'password=' + encodeURIComponent(val)
                    }).then(r => r.json()).then(function (d) {
                        if (d.match === false) {
                            cpCurrentWarning.style.display = 'block';
                        } else {
                            cpCurrentWarning.style.display = 'none';
                        }
                    }).catch(function () {  });
                }, 700);
            });

            // Reset on modal close
            document.getElementById('changePasswordModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('changePasswordForm').reset();
                cpMatchMsg.textContent = '';
                cpCurrentWarning.style.display = 'none';
                cpCurrent.type = 'password';
                cpNew.type = 'password';
                cpConfirm.type = 'password';
                cpShowAll.checked = false;
            });
        })();

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
                field.style.border = '1px solid #3A9AFF';
                field.style.background = '#fff';
            });
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
                field.style.background = '#f8f9fa';
            });
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
    </script>
</body>

</html>
