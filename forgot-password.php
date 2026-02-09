<?php
/**
 * Forgot Password Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

// Create password_resets table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table might already exist, continue
}

$showSuccessModal = false;
$showErrorModal = false;
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $showErrorModal = true;
        $errorMessage = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $showErrorModal = true;
        $errorMessage = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Create reset token
            $token = createPasswordResetToken($pdo, $user['id']);

            if ($token) {
                // Generate reset link
                $resetLink = APP_URL . '/reset-password.php?token=' . $token;

                // Send email
                $result = sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);

                if ($result['success']) {
                    $showSuccessModal = true;
                } else {
                    $showErrorModal = true;
                    $errorMessage = 'Failed to send email. Please try again later.';
                }
            } else {
                $showErrorModal = true;
                $errorMessage = 'An error occurred. Please try again.';
            }
        } else {
            // For security, show success even if email doesn't exist
            $showSuccessModal = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= APP_NAME ?></title>

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap"
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

        .forgot-wrapper {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .forgot-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?= APP_URL ?>/assets/images/login-bg.jpg') center/cover no-repeat;
            filter: blur(8px);
            transform: scale(1.1);
            z-index: -2;
        }

        .forgot-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        .forgot-card {
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

        .forgot-title {
            font-family: 'IM Fell English', Georgia, serif;
            font-size: 22px;
            font-weight: 400;
            color: #2C1810;
            margin-bottom: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .forgot-subtitle {
            font-size: 13px;
            color: #666;
            margin-bottom: 30px;
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
            margin-top: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-transform: uppercase;
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
            margin-top: 25px;
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
            border-radius: 8px;
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
        }

        .modal-btn.secondary {
            background-color: #f5f5f5;
            color: #333;
        }

        .modal-btn.secondary:hover {
            background-color: #e0e0e0;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .forgot-card {
                margin: 20px;
                padding: 35px 25px;
            }

            .forgot-title {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="forgot-wrapper">
        <div class="forgot-bg"></div>
        <div class="forgot-overlay"></div>

        <div class="forgot-card">
            <div class="lock-icon">
                <img src="<?= APP_URL ?>/assets/images/lock-icon.png" alt="Lock">
            </div>
            <h1 class="forgot-title">FORGOT PASSWORD</h1>
            <p class="forgot-subtitle">Enter your Email Address and we'll send you a link to reset your password</p>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter Email Address"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <button type="submit" class="submit-btn">Send Reset Link</button>
            </form>

            <a href="<?= APP_URL ?>/index.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
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
                    <h3 class="modal-title-custom">Email Sent!</h3>
                    <p class="modal-message">
                        We've sent a password reset link to your email address.<br>
                        Please check your inbox and click the link to reset your password.
                    </p>
                    <a href="<?= APP_URL ?>/index.php" class="modal-btn primary">Back to Login</a>
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