<?php
/**
 * Login View
 * Archive System - Quezon City Public Library
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login -
        <?= APP_NAME ?>
    </title>

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/pages/login.css" rel="stylesheet">
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

            <div id="alert-container"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">USERNAME</label>
                    <div class="input-wrapper">
                        <i class="bi bi-person-fill input-icon"></i>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Enter your username" required>
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

                <button type="submit" class="login-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span class="btn-text">LOGIN</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Config for JS -->
    <script>
        const APP_URL = "<?= APP_URL ?>";
    </script>

    <!-- Page JS -->
    <script src="<?= APP_URL ?>/assets/js/pages/login.js"></script>
</body>

</html>