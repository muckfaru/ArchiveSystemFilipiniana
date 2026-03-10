<?php
/**
 * Reset Password View – minimalist login-modal style
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – <?= APP_NAME ?></title>

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
        .rp-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 40px 36px 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.10);
        }

        .rp-heading {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            text-align: center;
            margin-bottom: 6px;
            letter-spacing: -0.2px;
        }

        .rp-subtext {
            font-size: 13px;
            color: #6B7280;
            text-align: center;
            margin-bottom: 24px;
        }

        /* ── Fields ── */
        .rp-field {
            margin-bottom: 16px;
        }

        .rp-field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .rp-field input {
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

        .rp-field input:focus {
            border-color: #3A9AFF;
            background: #FFFFFF;
        }

        /* ── Show password checkbox ── */
        .rp-show-pass {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        .rp-show-pass input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #3A9AFF;
            cursor: pointer;
            flex-shrink: 0;
        }

        .rp-show-pass label {
            font-size: 12px;
            font-weight: 500;
            color: #6B7280;
            cursor: pointer;
            margin: 0;
            text-transform: none;
            letter-spacing: 0;
        }

        /* ── Buttons ── */
        .rp-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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
            text-decoration: none;
            transition: background 0.18s;
        }

        .rp-btn:hover {
            background: #2d87ef;
            color: #FFFFFF;
        }

        .rp-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .rp-back {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 16px;
            font-size: 13px;
            font-weight: 500;
            color: #3A9AFF;
            text-decoration: none;
            transition: color 0.15s;
        }

        .rp-back:hover {
            color: #2d87ef;
        }

        /* ── Validation messages ── */
        .rp-valid-msg {
            font-size: 12px;
            margin-top: 4px;
            min-height: 16px;
        }

        /* ── Invalid-token state ── */
        .rp-invalid {
            text-align: center;
        }

        .rp-invalid .rp-err-icon {
            width: 52px;
            height: 52px;
            background: #FEE2E2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 22px;
            color: #B91C1C;
        }

        /* ── Success screen (shown after modal-less redirect) ── */
        .rp-success {
            text-align: center;
        }

        .rp-success .rp-ok-icon {
            width: 52px;
            height: 52px;
            background: #D1FAE5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 22px;
            color: #065F46;
        }

        /* ── Alert ── */
        .rp-alert {
            border-radius: 8px;
            padding: 10px 13px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rp-alert.error {
            background: #FEE2E2;
            color: #B91C1C;
        }

        .rp-alert.success {
            background: #D1FAE5;
            color: #065F46;
        }
    </style>
</head>

<body>
    <div class="rp-card">

        <?php if ($showSuccessModal): ?>
            <!-- ── Success ── -->
            <div class="rp-success">
                <div class="rp-ok-icon"><i class="bi bi-check-lg"></i></div>
                <h2 class="rp-heading">Password Reset!</h2>
                <p class="rp-subtext">Your password has been successfully changed.<br>You can now login with your new
                    password.</p>
                <a href="<?= APP_URL ?>/auth/login.php" class="rp-btn">Login Now</a>
            </div>

        <?php elseif (!$validToken): ?>
            <!-- ── Invalid token ── -->
            <div class="rp-invalid">
                <div class="rp-err-icon"><i class="bi bi-x-lg"></i></div>
                <h2 class="rp-heading">Invalid Link</h2>
                <p class="rp-subtext">This password reset link is invalid or has expired.</p>
                <a href="<?= APP_URL ?>/auth/forgot-password.php" class="rp-btn">Request New Link</a>
                <br>
                <a href="<?= APP_URL ?>/auth/login.php" class="rp-back"><i class="bi bi-arrow-left"></i> Back to Login</a>
            </div>

        <?php else: ?>
            <!-- ── Reset form ── -->
            <h2 class="rp-heading">Reset Password</h2>
            <p class="rp-subtext">Resetting for: <strong><?= htmlspecialchars($tokenData['username']) ?></strong></p>

            <?php if (!empty($errorMessage)): ?>
                <div class="rp-alert error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="resetForm">
                <div class="rp-field">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required
                        minlength="6" oninput="validatePasswords()">
                    <div class="rp-valid-msg" id="password-feedback"></div>
                </div>

                <div class="rp-field">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password"
                        required minlength="6" oninput="validatePasswords()">
                    <div class="rp-valid-msg" id="confirm-password-feedback"></div>
                </div>

                <!-- Single show-password checkbox for both fields -->
                <div class="rp-show-pass">
                    <input type="checkbox" id="showPassCheck" onchange="toggleShowPasswords(this)">
                    <label for="showPassCheck">Show passwords</label>
                </div>

                <button type="submit" class="rp-btn" id="submitBtn">Reset Password</button>
            </form>

            <a href="<?= APP_URL ?>/auth/login.php" class="rp-back"><i class="bi bi-arrow-left"></i> Back to Login</a>
        <?php endif; ?>

    </div>

    <!-- Bootstrap JS (needed for any legacy modal JS in reset-password.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- State Variables -->
    <script>
        const showSuccessModal = <?= json_encode($showSuccessModal ?? false) ?>;
        const showErrorModal = <?= json_encode($showErrorModal ?? false) ?>;

        function toggleShowPasswords(cb) {
            const type = cb.checked ? 'text' : 'password';
            const p = document.getElementById('password');
            const cp = document.getElementById('confirm_password');
            if (p) p.type = type;
            if (cp) cp.type = type;
        }

        function validatePasswords() {
            const p = document.getElementById('password');
            const cp = document.getElementById('confirm_password');
            const pf = document.getElementById('password-feedback');
            const cf = document.getElementById('confirm-password-feedback');
            if (!p || !cp) return;

            // Length check
            if (p.value.length > 0 && p.value.length < 6) {
                pf.innerHTML = '<span style="color:#B91C1C;">At least 6 characters required</span>';
            } else {
                pf.innerHTML = '';
            }

            // Match check
            if (cp.value.length > 0) {
                if (p.value !== cp.value) {
                    cf.innerHTML = '<span style="color:#B91C1C;">Passwords do not match</span>';
                } else {
                    cf.innerHTML = '<span style="color:#065F46;">Passwords match</span>';
                }
            } else {
                cf.innerHTML = '';
            }
        }
    </script>
    <!-- Page JS (validation etc.) -->
    <script src="<?= APP_URL ?>/assets/js/auth/reset-password.js"></script>
</body>

</html>