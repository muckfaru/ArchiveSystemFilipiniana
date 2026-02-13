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
        <style>* {
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
            padding: 30px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .login-logo {
            width: 140px;
            height: auto;
            margin: 0 auto 20px;
            display: block;
        }

        .login-title {
            font-family: 'IM Fell English', Georgia, serif;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 5px;
            color: #1a1a1a;
            text-transform: uppercase;
        }

        .login-subtitle {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-bottom: 40px;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            font-weight: 700;
            color: #555;
            margin-bottom: 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
            z-index: 10;
        }

        .form-control {
            width: 100%;
            padding: 10px 10px 10px 40px;
            /* Space for left icon */
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            background-color: #f9f9f9;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4C3939;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(76, 57, 57, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: #aaa;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            font-size: 18px;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #4C3939;
        }

        .forgot-password {
            display: block;
            text-align: left;
            color: #4C3939;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 15px;
            margin-bottom: 25px;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background-color: #4C3939;
            border: none;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-btn:hover {
            background-color: #3A2C2C;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Error Alert */
        .error-alert {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                margin: 20px;
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-bg"></div>
        <div class="login-overlay"></div>

        <div class="login-card">
            <!-- Logo area based on visual, might be circular bg if needed, but using plain image as requested -->
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="QCPL Logo" class="login-logo">

            <h1 class="login-title">QUEZON CITY PUBLIC LIBRARY</h1>
            <p class="login-subtitle">Please login to continue</p>

            <form method="POST" action="">
                <?php if ($showErrorModal && $error): ?>
                    <div class="text-danger small fw-bold mb-3" style="font-size: 13px;">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username" class="form-label">USERNAME</label>
                    <div class="input-wrapper">
                        <i class="bi bi-person-fill input-icon"></i>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock-fill input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter your password" required style="padding-right: 45px;">
                        <i class="bi bi-eye-slash-fill password-toggle" id="togglePassword"></i>
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
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye slash icon
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash-fill');
        });
    </script>
</body>

</html>