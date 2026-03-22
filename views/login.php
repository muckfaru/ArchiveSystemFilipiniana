<?php
/**
 * Login View – minimalist modal style
 * Archive System - Quezon City Public Library
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= APP_NAME ?></title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: #F3F4F6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            padding: 24px;
        }

        /* ── Card ── */
        .login-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 40px 36px 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.10);
        }

        .login-heading {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            text-align: center;
            margin-bottom: 6px;
            letter-spacing: -0.2px;
        }

        .login-subtext {
            font-size: 13px;
            color: #6B7280;
            text-align: center;
            margin-bottom: 24px;
        }

        /* ── Fields ── */
        .login-field {
            margin-bottom: 16px;
        }

        .login-field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .login-field input {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid #E5E7EB;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #111827;
            background: #FAFAFA;
            outline: none;
            transition: border-color 0.15s;
        }

        .login-field input:focus {
            border-color: #3A9AFF;
            background: #FFFFFF;
        }

        /* ── Password wrapper with eye toggle ── */
        .pass-wrap {
            position: relative;
        }

        .pass-wrap input {
            padding-right: 40px;
        }

        .pass-wrap .eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9CA3AF;
            cursor: pointer;
            padding: 2px;
            font-size: 15px;
            line-height: 1;
            transition: color 0.15s;
        }

        .pass-wrap .eye-btn:hover {
            color: #374151;
        }

        /* ── Forgot password link ── */
        .forgot-link-wrap {
            margin-top: 6px;
        }

        .forgot-link {
            background: none;
            border: none;
            padding: 0;
            font-size: 12px;
            font-weight: 500;
            color: #3A9AFF;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            transition: color 0.15s;
        }

        .forgot-link:hover {
            color: #2d87ef;
            text-decoration: underline;
        }

        /* ── Submit button ── */
        .login-btn {
            width: 100%;
            padding: 11px;
            margin-top: 20px;
            background: #3A9AFF;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.18s;
        }

        .login-btn:hover:not(:disabled) {
            background: #2d87ef;
        }

        .login-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* ── Alert ── */
        .error-alert {
            background: #FEE2E2;
            color: #B91C1C;
            border-radius: 8px;
            padding: 10px 13px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .success-alert {
            background: #DCFCE7;
            color: #166534;
            border-radius: 8px;
            padding: 10px 13px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div id="loginView">
            <h1 class="login-heading">Admin Login</h1>
            <p class="login-subtext">Please login to continue</p>

            <div id="alert-container"></div>

            <form id="loginForm" novalidate>
                <div class="login-field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username"
                        autocomplete="username" required>
                </div>

                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="pass-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password"
                            autocomplete="current-password" required>
                        <button type="button" class="eye-btn" id="togglePassword" tabindex="-1">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="forgot-link-wrap">
                        <button type="button" id="showForgotPassword" class="forgot-link">Forgot password?</button>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Login</span>
                </button>
            </form>
        </div>

        <div id="forgotView" style="display:none;">
            <h1 class="login-heading">Forgot Password</h1>
            <p class="login-subtext">Enter your email and we'll send you a reset link.</p>

            <div id="forgot-alert-container"></div>

            <form id="forgotPasswordForm" novalidate>
                <div class="login-field">
                    <label for="forgotEmail">Email Address</label>
                    <input type="email" id="forgotEmail" name="email" placeholder="Enter your email"
                        autocomplete="email" required>
                </div>

                <button type="submit" class="login-btn" id="forgotSubmitBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="forgotSpinner" role="status" aria-hidden="true"></span>
                    <span id="forgotBtnText">Send Reset Link</span>
                </button>
            </form>

            <div class="forgot-link-wrap" style="margin-top:14px;">
                <button type="button" id="backToLogin" class="forgot-link">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const APP_URL = "<?= APP_URL ?>";
    </script>

    <!-- Page JS (handles form submit + alert) -->
    <script src="<?= APP_URL ?>/assets/js/auth/login.js"></script>
</body>

</html>
