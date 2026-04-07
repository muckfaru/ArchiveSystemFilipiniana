<?php
/**
 * Forgot Password View
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password -
        <?= APP_NAME ?>
    </title>

    <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/website_logo.jpg">

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/auth/forgot-password.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/auth/forgot-password-modal.css" rel="stylesheet">
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

            <a href="<?= route_url('login') ?>" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered custom-modal-dialog">
            <div class="modal-content custom-modal-content">
                <div class="modal-body custom-modal-body">
                    <div class="success-icon-container">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h2 class="modal-title-desktop">Email Sent!</h2>
                    <p class="modal-description-desktop">
                        We've sent a password reset link to your email address.<br>
                        Please check your inbox.
                    </p>
                    <a href="<?= route_url('login') ?>" class="btn-primary-desktop">
                        Back to Login
                    </a>
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
    <script src="<?= APP_URL ?>/assets/js/auth/forgot-password.js"></script>
</body>

</html>
