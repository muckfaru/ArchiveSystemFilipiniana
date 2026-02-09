<?php
/**
 * Reset Password Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$token = $_GET['token'] ?? '';
$showSuccessModal = false;
$showErrorModal = false;
$errorMessage = '';
$validToken = false;
$tokenData = null;

// Validate token
if (!empty($token)) {
    $tokenData = validatePasswordResetToken($pdo, $token);
    if ($tokenData) {
        $validToken = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $showErrorModal = true;
        $errorMessage = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $showErrorModal = true;
        $errorMessage = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $showErrorModal = true;
        $errorMessage = 'Passwords do not match.';
    } else {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

        if ($stmt->execute([$hashedPassword, $tokenData['user_id']])) {
            // Mark token as used
            markTokenAsUsed($pdo, $token);

            // Log activity
            logActivity($tokenData['user_id'], 'password_reset', 'Password reset via email');

            $showSuccessModal = true;
            $validToken = false;
        } else {
            $showErrorModal = true;
            $errorMessage = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= APP_NAME ?></title>

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow: hidden;
        }

        .reset-wrapper {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reset-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?= APP_URL ?>/assets/images/login-bg.jpg') center/cover no-repeat;
            z-index: -1;
        }

        .reset-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        .reset-card {
            background: #fff;
            border-radius: 20px;
            padding: 45px 35px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .lock-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
        }

        .lock-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .reset-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 22px;
            font-weight: 700;
            color: #2C1810;
            margin-bottom: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .reset-subtitle {
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 13px;
            text-align: left;
            display: block;
        }

        .form-control {
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background-color: #fff;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4C3939;
            box-shadow: 0 0 0 3px rgba(76, 57, 57, 0.1);
            background-color: #fff;
        }

        .form-control::placeholder {
            color: #aaa;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background-color: #4C3939;
            border: none;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            border-radius: 8px;
            margin-top: 15px;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #3D2D2D;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(76, 57, 57, 0.3);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #444;
            font-size: 13px;
            margin-top: 20px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #4C3939;
        }

        .back-link i {
            font-size: 14px;
            transition: transform 0.2s;
        }

        .back-link:hover i {
            transform: translateX(-3px);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
        }

        .password-toggle .toggle-btn:hover {
            color: #4C3939;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-body {
            padding: 40px;
            text-align: center;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        .modal-icon.success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .modal-icon.error {
            background-color: #ffebee;
            color: #c62828;
        }

        .modal-title-custom {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .modal-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-btn {
            padding: 12px 40px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .modal-btn.primary {
            background-color: #4C3939;
            color: #fff;
        }

        .modal-btn.primary:hover {
            background-color: #3D2D2D;
            color: #fff;
        }

        .modal-btn.secondary {
            background-color: #f5f5f5;
            color: #333;
        }

        .modal-btn.secondary:hover {
            background-color: #e0e0e0;
        }

        .invalid-token-card {
            text-align: center;
        }

        .invalid-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #ffebee;
            color: #c62828;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .reset-card {
                margin: 20px;
                padding: 35px 25px;
            }

            .reset-title {
                font-size: 18px;
            }
        }
    </style>
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
                    <img src="<?= APP_URL ?>/assets/images/lock-icon.png" alt="Lock">
                </div>
                <h1 class="reset-title">RESET PASSWORD</h1>
                <p class="reset-subtitle">Enter your new password below</p>

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
                    <p class="modal-message"><?= $errorMessage ?></p>
                    <button type="button" class="modal-btn secondary" data-bs-dismiss="modal">Try Again</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
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

        <?php if ($showSuccessModal): ?>
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('successModal')).show();
            });
        <?php endif; ?>

        <?php if ($showErrorModal): ?>
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('errorModal')).show();
            });
        <?php endif; ?>
    </script>
</body>

</html>