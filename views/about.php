<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'About Us - Quezon City Public Library' ?></title>
    <meta name="description" content="<?= $pageDescription ?? 'Learn about the history, mission, and vision of the Quezon City Public Library.' ?>">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Public Page CSS -->
    <link href="<?= APP_URL ?>/assets/css/user_pages/public.css" rel="stylesheet">

    <script>const APP_URL = "<?= APP_URL ?>";</script>
</head>

<body class="public-page">

    <!-- ==================== HEADER ==================== -->
    <header class="public-header">
        <a href="<?= APP_URL ?>/user_pages/public.php" class="public-header-brand">
            <img src="<?= APP_URL ?>/assets/images/public_logo.png" alt="QCPL Logo" class="public-header-logo">
            <span class="public-header-brand-name">Quezon City Public Library</span>
        </a>
        
        <!-- Hamburger Menu Button (Mobile Only) -->
        <button class="public-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavCollapse" aria-controls="publicNavCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>
        
        <!-- Navigation -->
        <nav class="public-nav navbar-collapse collapse" id="publicNavCollapse">
            <a href="<?= APP_URL ?>/user_pages/public.php" class="public-nav-link">
                <i class="bi bi-house-door"></i>
                Home
            </a>
            <a href="<?= APP_URL ?>/user_pages/public.php?view=browse" class="public-nav-link">
                <i class="bi bi-grid-3x3-gap"></i>
                Browse
            </a>
            <a href="<?= APP_URL ?>/user_pages/about.php" class="public-nav-link active">
                <i class="bi bi-info-circle"></i>
                About
            </a>
        </nav>
        
        <button id="adminLoginTrigger" class="public-admin-login-btn" type="button">
            <i class="bi bi-person-lock"></i>
            Admin Login
        </button>
    </header>

    <!-- ==================== ABOUT CONTENT ==================== -->
    <div class="about-container">
        <div class="about-hero">
            <h1 class="about-title">About Us</h1>
            <p class="about-subtitle">Preserving history, empowering communities through knowledge</p>
        </div>

        <div class="about-content">
            <!-- History Section -->
            <section class="about-section">
                <h2 class="section-title">Our History</h2>
                <div class="section-content">
                    <p>The Quezon City Public Library began as a simple initiative, a collaboration between the National Library and the Quezon City Government. This partnership was established during the term of the late Mayor Ponciano Bernardo and the first City Superintendent of Libraries, Atty. Felicidad Peralta. Their efforts were made possible through Public Law No. 1935, which mandated the integration of all government-affiliated libraries into the Philippine library system.</p>
                    
                    <p>The Quezon City Public Library was established on August 16, 1948, and opened to the public on October 23, 1948. Initially, the library was housed in a one-story building near the old Post Office and behind the former Quezon City Hall, in an area then known as Highway 54, now called Epifanio de los Santos Avenue (EDSA).</p>
                    
                    <p>Over the years, the location of the Main Library changed several times. Eventually, it moved to a modern three-story building located within the Quezon City Hall Compound and was officially opened to the public on February 6, 2017.</p>
                    
                    <p>As Quezon City continued to grow, the library services had to adapt to meet the increasing needs of its residents. The Library network has expanded to a total of thirty-eight (38) branches across the city.</p>
                </div>
            </section>


            <!-- Mission Section -->
            <section class="about-section">
                <h2 class="section-title">Mission</h2>
                <div class="section-content">
                    <ul class="mission-list">
                        <li>To provide quality resources and services to meet the changing needs of the community through the introduction of innovative techniques for the advancement of learning and literacy.</li>
                        <li>To train and develop the staff to be equipped with the needed skills and competencies and strive to be an innovator in public library services.</li>
                        <li>To foster strong linkage and partnership among government and non-government agencies both local and international.</li>
                    </ul>
                </div>
            </section>

            <!-- Vision Section -->
            <section class="about-section">
                <h2 class="section-title">Vision</h2>
                <div class="section-content">
                    <p class="vision-text">To be a premier and world class public library, responsive to the information and research needs of the community.</p>
                </div>
            </section>
        </div>
    </div>


    <!-- ==================== FOOTER ==================== -->
    <footer class="public-footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3 class="footer-title">Quezon City Public Library</h3>
                <p class="footer-description">Preserving history through digital archives</p>
                <div class="footer-social">
                    <a href="https://www.facebook.com/quezoncitypubliclibrary" target="_blank" rel="noopener noreferrer" class="social-link" title="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="https://www.instagram.com/qcpubliclibrary" target="_blank" rel="noopener noreferrer" class="social-link" title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                </div>
            </div>
            <div class="footer-section">
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="<?= APP_URL ?>/user_pages/public.php">Home</a></li>
                    <li><a href="<?= APP_URL ?>/user_pages/public.php?view=browse">Browse</a></li>
                    <li><a href="<?= APP_URL ?>/user_pages/about.php">About</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4 class="footer-heading">Contact Us</h4>
                <p class="footer-contact">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Gate 3 City Hall Compound, Diliman<br>Quezon City, 1100 Metro Manila</span>
                </p>
                <p class="footer-contact">
                    <i class="bi bi-telephone-fill"></i>
                    <span>(02) 8922 4060</span>
                </p>
                <p class="footer-contact">
                    <i class="bi bi-envelope-fill"></i>
                    <span>qcplibrary@quezoncity.gov.ph</span>
                </p>
            </div>
            <div class="footer-section">
                <h4 class="footer-heading">Main Library Hours</h4>
                <div class="footer-hours">
                    <div class="hours-row">
                        <span class="day">Monday - Friday</span>
                        <span class="time">7AM - 7PM</span>
                    </div>
                    <div class="hours-row">
                        <span class="day">Saturday</span>
                        <span class="time">8AM - 4PM</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Quezon City Public Library. All rights reserved.</p>
        </div>
    </footer>


    <!-- ==================== ADMIN LOGIN MODAL ==================== -->
    <div id="adminLoginBackdrop" class="admin-login-backdrop" role="dialog" aria-modal="true" aria-label="Admin Login">
        <div class="admin-login-modal">
            <button class="admin-login-close" id="adminLoginClose" title="Close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>

            <!-- LOGIN VIEW -->
            <div id="adminViewLogin">
                <h2 class="admin-login-heading">Admin Login</h2>
                <p class="admin-login-subtext">Please login to continue</p>

                <div id="adminAlertContainer"></div>

                <form id="adminLoginForm" novalidate>
                    <div class="admin-login-field">
                        <label for="adminUsername">Username</label>
                        <div class="admin-input-wrap">
                            <i class="bi bi-person-fill admin-input-icon"></i>
                            <input type="text" id="adminUsername" name="username" placeholder="Enter username" autocomplete="username" required>
                        </div>
                    </div>
                    <div class="admin-login-field">
                        <label for="adminPassword">Password</label>
                        <div class="admin-pass-wrap">
                            <i class="bi bi-lock-fill admin-input-icon"></i>
                            <input type="password" id="adminPassword" name="password" placeholder="Enter password" autocomplete="current-password" required>
                            <button type="button" id="adminTogglePass" tabindex="-1">
                                <i class="bi bi-eye" id="adminPassIcon"></i>
                            </button>
                        </div>
                        <div class="admin-forgot-link-wrap">
                            <button type="button" id="adminShowForgot" class="admin-forgot-link">Forgot password?</button>
                        </div>
                    </div>
                    <button type="submit" class="admin-login-submit" id="adminLoginSubmit">
                        <span class="spinner-border spinner-border-sm d-none" id="adminSpinner"></span>
                        <span id="adminBtnText">Login</span>
                    </button>
                </form>
                <div style="text-align:center; margin-top:14px;">
                    <button type="button" id="adminBackToHome" class="admin-back-to-home-btn">
                        <i class="bi bi-house-door"></i> Back to Home
                    </button>
                </div>
            </div>

            <!-- FORGOT PASSWORD VIEW -->
            <div id="adminViewForgot" style="display:none;">
                <h2 class="admin-login-heading">Forgot Password</h2>
                <p class="admin-login-subtext">Enter your email and we'll send you a reset link.</p>

                <div id="adminForgotAlert"></div>

                <form id="adminForgotForm" novalidate>
                    <div class="admin-login-field">
                        <label for="adminForgotEmail">Email Address</label>
                        <input type="email" id="adminForgotEmail" name="email" placeholder="Enter your email" autocomplete="email" required>
                    </div>
                    <button type="submit" class="admin-login-submit" id="adminForgotSubmit">
                        <span class="spinner-border spinner-border-sm d-none" id="adminForgotSpinner"></span>
                        <span id="adminForgotBtnText">Send Reset Link</span>
                    </button>
                </form>
                <div style="text-align:center; margin-top:14px;">
                    <button type="button" id="adminBackToHomeForgot" class="admin-back-to-home-btn">
                        <i class="bi bi-house-door"></i> Back to Home
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin Login Modal JS -->
    <script src="<?= APP_URL ?>/assets/js/user_pages/public.js"></script>

</body>

</html>
