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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
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
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login -
        <?= APP_NAME ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">

    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
                url('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }

        .login-card {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(10px);
        }

        .login-logo {
            width: 120px;
            margin: 0 auto 20px;
            display: block;
        }

        .login-title {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 5px;
            letter-spacing: 1px;
            color: #333;
        }

        .login-subtitle {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #5D4037;
            box-shadow: 0 0 0 3px rgba(93, 64, 55, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background-color: #5D4037;
            border: none;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            border-radius: 8px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }

        .login-btn:hover {
            background-color: #4E342E;
        }

        .forgot-password {
            display: block;
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-top: 15px;
            text-decoration: underline;
        }

        .forgot-password:hover {
            color: #5D4037;
        }

        .show-password {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }

        .show-password input {
            width: 16px;
            height: 16px;
        }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="QCPL Logo" class="login-logo">
            <h1 class="login-title">QUEZON CITY PUBLIC LIBRARY</h1>
            <p class="login-subtitle">Please Log in to Continue</p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter Username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password"
                        placeholder="Enter Password" required>
                    <label class="show-password">
                        <input type="checkbox" id="showPassword"> Show Password
                    </label>
                </div>

                <a href="#" class="forgot-password">Forgot Password?</a>

                <button type="submit" class="login-btn">LOGIN</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide password
        document.getElementById('showPassword').addEventListener('change', function () {
            const passwordInput = document.getElementById('password');
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>

</html>