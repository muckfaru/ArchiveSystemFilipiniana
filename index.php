<?php
/**
 * Login Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$error = '';
$showErrorModal = false;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        $showErrorModal = true;
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Log activity
            logActivity($user['id'], 'login', $user['username']);

            redirect(APP_URL . '/pages/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
            $showErrorModal = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>

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

        .login-wrapper {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-bg {
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

        .login-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 45px 40px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .login-logo {
            width: 120px;
            height: auto;
            margin: 0 auto 20px;
            display: block;
        }

        .login-title {
            font-family: 'IM Fell English', Georgia, serif;
            font-size: 22px;
            font-weight: 400;
            text-align: center;
            margin-bottom: 6px;
            letter-spacing: 1px;
            color: #2C1810;
        }

        .login-title span {
            font-weight: 400;
        }

        .error-alert {
            background: #FFEBEE;
            border: 1px solid #FFCDD2;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #C62828;
            font-size: 13px;
        }

        .error-alert i {
            font-size: 18px;
        }

        .login-subtitle {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 400;
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

        .password-wrapper {
            position: relative;
        }

        .show-password {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            /* Right aligned */
            gap: 6px;
            margin-top: 10px;
            font-size: 12px;
            color: #444;
        }

        .show-password input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #4C3939;
            cursor: pointer;
        }

        .show-password label {
            cursor: pointer;
        }

        .forgot-password {
            display: block;
            color: #444;
            font-size: 12px;
            margin-top: 15px;
            text-decoration: underline;
            text-align: left;
            /* Left aligned */
        }

        .forgot-password:hover {
            color: #4C3939;
        }

        .login-btn {
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
        }

        .login-btn:hover {
            background-color: #3D2D2D;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(76, 57, 57, 0.3);
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

        .modal-btn.secondary {
            background-color: #f5f5f5;
            color: #333;
        }

        .modal-btn.secondary:hover {
            background-color: #e0e0e0;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                margin: 20px;
                padding: 35px 25px;
            }

            .login-logo {
                width: 100px;
            }

            .login-title {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-bg"></div>
        <div class="login-overlay"></div>

        <div class="login-card">
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="QCPL Logo" class="login-logo">
            <h1 class="login-title"><span>QUEZON CITY PUBLIC LIBRARY</span></h1>
            <p class="login-subtitle">Please log in to Continue</p>

            <form method="POST" action="">
                <?php if ($showErrorModal && $error): ?>
                    <div class="error-alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                <div class="mb-3 text-start">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter Username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="mb-2 text-start">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter Password" required>
                    </div>
                    <div class="show-password">
                        <input type="checkbox" id="showPassword">
                        <label for="showPassword">Show Password</label>
                    </div>
                </div>

                <a href="<?= APP_URL ?>/forgot-password.php" class="forgot-password">Forgot Password?</a>

                <button type="submit" class="login-btn">LOGIN</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility with checkbox
        const passwordInput = document.getElementById('password');
        const showPasswordCheckbox = document.getElementById('showPassword');

        showPasswordCheckbox.addEventListener('change', function () {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>

</html>