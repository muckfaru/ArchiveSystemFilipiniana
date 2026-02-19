<?php
/**
 * Reset Password View
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password -
        <?= APP_NAME ?>
    </title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/pages/reset-password.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/pages/reset-password-validation.css" rel="stylesheet">
</head>

<body>
    <div class="reset-wrapper">
        <div class="reset-bg"></div>
        <div class="reset-overlay"></div>

        <div class="reset-card">
            <?php if (!$validToken && !$showSuccessModal): ?>
                <div class="invalid-token-card">
                    <div class="invalid-icon">
                        <i class="bi bi-x-lg"></i>
                    </div>
                    <h1 class="reset-title">Invalid Link</h1>
                    <p class="reset-subtitle">This password reset link is invalid or has expired.</p>
                    <a href="<?= APP_URL ?>/forgot-password.php" class="modal-btn primary" style="margin-bottom: 15px;">
                        Request New Link
                    </a>
                    <br>
                    <a href="<?= APP_URL ?>/index.php" class="back-link">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="lock-icon">
                    <img src="<?= APP_URL ?>/assets/images/reset_pw.png" alt="Reset Password Icon">
                </div>
                <h1 class="reset-title">RESET PASSWORD</h1>
                <p class="reset-subtitle">
                    Resetting for: <strong><?= htmlspecialchars($tokenData['username']) ?></strong>
                </p>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="password-toggle">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Enter new password" required minlength="6">
                            <button type="button" class="toggle-btn" onclick="togglePassword('password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="password-feedback" class="validation-message"></div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="password-toggle">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                placeholder="Confirm new password" required minlength="6">
                            <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="confirm-password-feedback" class="validation-message"></div>
                    </div>

                    <button type="submit" class="submit-btn">Reset Password</button>
                </form>

                <a href="<?= APP_URL ?>/index.php" class="back-link">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="modal-icon success">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3 class="modal-title-custom">Password Reset Successful!</h3>
                    <p class="modal-message">
                        Your password has been successfully changed.<br>
                        You can now login with your new password.
                    </p>
                    <a href="<?= APP_URL ?>/index.php" class="modal-btn primary">Login Now</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="modal-icon error">
                        <i class="bi bi-x-lg"></i>
                    </div>
                    <h3 class="modal-title-custom">Error</h3>
                    <p class="modal-message">
                        <?= $errorMessage ?? '' ?>
                    </p>
                    <button type="button" class="modal-btn secondary" data-bs-dismiss="modal">Try Again</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- State Variables for JS -->
    <script>
        const showSuccessModal = <?= json_encode($showSuccessModal ?? false) ?>;
        const showErrorModal = <?= json_encode($showErrorModal ?? false) ?>;
    </script>

    <!-- Page JS -->
    <script src="<?= APP_URL ?>/assets/js/pages/reset-password.js"></script>
</body>

</html>