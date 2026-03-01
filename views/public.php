<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore the Archive - Quezon City Public Library</title>
    <meta name="description"
        content="Explore centuries of global history, news, and culture through our comprehensive digital archive collection.">

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Public Page CSS -->
    <link href="<?= APP_URL ?>/assets/css/pages/public.css" rel="stylesheet">

    <script>const APP_URL = "<?= APP_URL ?>";</script>
</head>

<body class="public-page">

    <!-- ==================== HEADER ==================== -->
    <header class="public-header">
        <a href="<?= APP_URL ?>/public.php" class="public-header-brand">
            <img src="<?= APP_URL ?>/assets/images/public_logo.png" alt="QCPL Logo" class="public-header-logo">
            <span class="public-header-brand-name">Quezon City Public Library</span>
        </a>
        
        <!-- Navigation -->
        <nav class="public-nav">
            <a href="<?= APP_URL ?>/public.php" class="public-nav-link <?= !isset($_GET['view']) || $_GET['view'] !== 'browse' ? 'active' : '' ?>">
                <i class="bi bi-house-door"></i>
                Home
            </a>
            <a href="<?= APP_URL ?>/public.php?view=browse" class="public-nav-link <?= isset($_GET['view']) && $_GET['view'] === 'browse' ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i>
                Browse
            </a>
        </nav>
        
        <button id="adminLoginTrigger" class="public-admin-login-btn" type="button">
            <i class="bi bi-person-lock"></i>
            Admin Login
        </button>
    </header>

    <!-- ==================== HERO ==================== -->
    <section class="public-hero">
        <h1 class="public-hero-title">The Archive of Record</h1>
        <p class="public-hero-subtitle">
            Explore centuries of global journalism through a fully digitized and searchable newspaper collection.
        </p>

        <!-- Search Bar -->
        <form method="GET" action="" id="publicSearchForm">
            <?php if ($categoryFilter): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
            <?php endif; ?>
            <div class="public-search-bar">
                <i class="bi bi-search"></i>
                <input type="text" class="public-search-input" id="publicSearchInput" name="q"
                    placeholder="Search newspapers, publishers, categories, or dates..."
                    value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off">
                <button type="submit" class="public-search-btn">Search</button>
            </div>
        </form>
    </section>

    <!-- ==================== SEARCH ACTIVE FILTER BAR ==================== -->
    <?php if ($searchQuery || $categoryFilter): ?>
        <div class="public-grid-container" style="padding-bottom: 0;">
            <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                <span class="text-muted" style="font-size: 13px;">
                    <?php if ($searchQuery): ?>
                        Showing <strong>
                            <?= number_format($totalResults) ?>
                        </strong> results for <strong>"
                            <?= htmlspecialchars($searchQuery) ?>"
                        </strong>
                    <?php else: ?>
                        Showing <strong>
                            <?= number_format($totalResults) ?>
                        </strong> results
                    <?php endif; ?>
                </span>
                <a href="<?= APP_URL ?>/public.php" class="text-decoration-none"
                    style="font-size: 12px; color: #3A9AFF; font-weight: 600;">
                    <i class="bi bi-x-circle me-1"></i>Clear filters
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== FILE GRID ==================== -->
    <?php
    // Helper: highlight search term in a string safely
    function pubHighlight(string $text, string $q): string
    {
        if (!$q)
            return htmlspecialchars($text);
        $safe = htmlspecialchars($text);
        $safeQ = preg_quote(htmlspecialchars($q), '/');
        return preg_replace('/(' . $safeQ . ')/iu', '<mark class="pub-hl">$1</mark>', $safe);
    }
    ?>
    <div class="public-grid-container" data-total="<?= (int) $totalResults ?>">
        <?php if (empty($documents)): ?>
            <div class="public-empty-state">
                <i class="bi bi-search"></i>
                <h5>No Results Found</h5>
                <p style="font-size: 14px;">We couldn't find any documents matching your criteria.</p>
                <a href="<?= APP_URL ?>/public.php" class="public-read-btn"
                    style="width: auto; display: inline-flex; margin-top: 16px; padding: 10px 24px;">
                    Browse All
                </a>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($documents as $paper): ?>
                    <?php
                    $catName = $paper['category_name'] ?? 'Uncategorized';
                    $catClass = 'public-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $catName));
                    ?>
                    <div class="col-6 col-md-3">
                        <div class="public-file-card" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars($paper['title']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= $paper['publication_date'] ? date('F j, Y', strtotime($paper['publication_date'])) : '' ?>"
                            data-publisher="<?= htmlspecialchars($paper['publisher'] ?? '') ?>"
                            data-description="<?= htmlspecialchars($paper['description'] ?? '') ?>"
                            data-category="<?= htmlspecialchars($catName) ?>"
                            data-format="<?= strtoupper($paper['file_type'] ?? 'PDF') ?>"
                            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                            data-page-count="<?= $paper['page_count'] ?? '' ?>"
                            data-volume="<?= htmlspecialchars($paper['volume_issue'] ?? '') ?>"
                            data-edition="<?= htmlspecialchars($paper['edition'] ?? '') ?>"
                            data-language="<?= htmlspecialchars($paper['language_name'] ?? '') ?>"
                            data-keywords="<?= htmlspecialchars($paper['keywords'] ?? '') ?>">

                            <!-- Thumbnail with category badge top-left -->
                            <div class="public-thumb-wrap">
                                <?php if ($paper['thumbnail_path']): ?>
                                    <img src="<?= APP_URL ?>/<?= htmlspecialchars($paper['thumbnail_path']) ?>"
                                        class="public-file-thumbnail" alt="<?= htmlspecialchars($paper['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="public-file-thumbnail-placeholder">
                                        <i class="bi bi-newspaper"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Category badge: top-LEFT -->
                                <span class="public-file-category pub-thumb-badge <?= htmlspecialchars($catClass) ?>">
                                    <?= htmlspecialchars($catName) ?>
                                </span>
                            </div>

                            <!-- Card body -->
                            <div class="public-file-info">
                                <!-- Date -->
                                <?php if ($paper['publication_date']): ?>
                                    <div class="public-file-date-line">
                                        <?= pubHighlight(strtoupper(date('F j, Y', strtotime($paper['publication_date']))), $searchQuery) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Title -->
                                <div class="public-file-title">
                                    <?= pubHighlight($paper['title'], $searchQuery) ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($paper['description'])): ?>
                                    <div class="public-file-description">
                                        <?= pubHighlight($paper['description'], $searchQuery) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ==================== PAGINATION ==================== -->
            <?php if ($totalPages > 1): ?>
                <?php
                function buildPublicPageUrl($p, $q, $cat)
                {
                    $params = ['page' => $p];
                    if ($q)
                        $params['q'] = $q;
                    if ($cat)
                        $params['category'] = $cat;
                    return '?' . http_build_query($params);
                }
                ?>
                <div class="public-pagination">
                    <!-- Prev arrow -->
                    <a href="<?= buildPublicPageUrl(max(1, $currentPage - 1), $searchQuery, $categoryFilter) ?>"
                        class="public-page-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>

                    <?php
                    $startPage = max(1, $currentPage - 1);
                    $endPage = min($totalPages, $currentPage + 1);

                    if ($startPage > 1): ?>
                        <a href="<?= buildPublicPageUrl(1, $searchQuery, $categoryFilter) ?>" class="public-page-btn">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="public-page-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="<?= buildPublicPageUrl($i, $searchQuery, $categoryFilter) ?>"
                            class="public-page-btn <?= $i === $currentPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="public-page-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="<?= buildPublicPageUrl($totalPages, $searchQuery, $categoryFilter) ?>" class="public-page-btn">
                            <?= $totalPages ?>
                        </a>
                    <?php endif; ?>

                    <!-- Next arrow -->
                    <a href="<?= buildPublicPageUrl(min($totalPages, $currentPage + 1), $searchQuery, $categoryFilter) ?>"
                        class="public-page-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- ==================== FILE PREVIEW MODAL ==================== -->
    <div id="publicModalBackdrop" class="public-modal-backdrop">
        <div class="public-modal" role="dialog" aria-modal="true">

            <!-- Left: Image + Read Button -->
            <div class="public-modal-left">
                <div class="public-modal-img-container">
                    <img id="publicModalImg" src="" class="public-modal-img" alt="File Preview" style="display: none;">
                    <div id="publicModalNoImg" class="public-modal-no-img" style="display: none;">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>No preview available</span>
                    </div>
                </div>
                <div class="public-modal-actions">
                    <a id="publicModalReadBtn" href="#" target="_blank" class="public-read-btn">
                        <i class="bi bi-book-half"></i> Read Full Document
                    </a>
                </div>
            </div>

            <!-- Right: Metadata -->
            <div class="public-modal-right">
                <button id="publicModalClose" class="public-modal-close" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>

                <!-- Category badge ABOVE title -->
                <span id="publicModalCategory" class="public-modal-category-badge">CATEGORY</span>

                <!-- Title -->
                <h2 id="publicModalTitle" class="public-modal-title">File Title</h2>

                <!-- Description (italic quote block) -->
                <div id="publicModalDescriptionWrap" class="public-modal-description-wrap" style="display: none;">
                    <p id="publicModalDescription" class="public-modal-description"></p>
                </div>

                <!-- Section label -->
                <p class="public-modal-meta-section-title">Document Details</p>

                <!-- Meta rows -->
                <div class="public-modal-meta-row">
                    <span class="public-modal-meta-label"><i class="bi bi-calendar3"></i> Publication Date</span>
                    <span id="publicModalDate" class="public-modal-meta-value">—</span>
                </div>

                <div class="public-modal-meta-row">
                    <span class="public-modal-meta-label"><i class="bi bi-building"></i> Publisher</span>
                    <span id="publicModalPublisher" class="public-modal-meta-value">—</span>
                </div>


                <div class="public-modal-meta-row" id="modalRowLanguage">
                    <span class="public-modal-meta-label"><i class="bi bi-translate"></i> Language</span>
                    <span id="publicModalLanguage" class="public-modal-meta-value">—</span>
                </div>

                <div class="public-modal-meta-row" id="modalRowPages">
                    <span class="public-modal-meta-label"><i class="bi bi-book"></i> Pages</span>
                    <span id="publicModalPages" class="public-modal-meta-value">—</span>
                </div>

                <div class="public-modal-meta-row" id="modalRowVolume">
                    <span class="public-modal-meta-label"><i class="bi bi-layers"></i> Volume / Issue</span>
                    <span id="publicModalVolume" class="public-modal-meta-value">—</span>
                </div>

                <div class="public-modal-meta-row" id="modalRowEdition">
                    <span class="public-modal-meta-label"><i class="bi bi-sun"></i> Edition</span>
                    <span id="publicModalEdition" class="public-modal-meta-value">—</span>
                </div>

                <div class="public-modal-meta-row" id="modalRowKeywords">
                    <span class="public-modal-meta-label"><i class="bi bi-tags"></i> Keywords</span>
                    <div id="publicModalKeywords" class="public-modal-keywords-wrap"></div>
                </div>
            </div>
        </div>
    </div>

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
                            <input type="text" id="adminUsername" name="username" placeholder="Enter username"
                                autocomplete="username" required>
                        </div>
                    </div>
                    <div class="admin-login-field">
                        <label for="adminPassword">Password</label>
                        <div class="admin-pass-wrap">
                            <i class="bi bi-lock-fill admin-input-icon"></i>
                            <input type="password" id="adminPassword" name="password" placeholder="Enter password"
                                autocomplete="current-password" required>
                            <button type="button" id="adminTogglePass" tabindex="-1">
                                <i class="bi bi-eye" id="adminPassIcon"></i>
                            </button>
                        </div>
                        <div class="admin-forgot-link-wrap">
                            <button type="button" id="adminShowForgot" class="admin-forgot-link">Forgot
                                password?</button>
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
                        <input type="email" id="adminForgotEmail" name="email" placeholder="Enter your email"
                            autocomplete="email" required>
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
    <!-- Public Page JS -->
    <script src="<?= APP_URL ?>/assets/js/pages/public.js"></script>
    <!-- Admin Login Modal JS -->
    <script>
        (function () {
            const backdrop = document.getElementById('adminLoginBackdrop');
            const trigger = document.getElementById('adminLoginTrigger');
            const closeBtn = document.getElementById('adminLoginClose');

            // Login view
            const viewLogin = document.getElementById('adminViewLogin');
            const form = document.getElementById('adminLoginForm');
            const alertEl = document.getElementById('adminAlertContainer');
            const spinner = document.getElementById('adminSpinner');
            const btnText = document.getElementById('adminBtnText');
            const submitBtn = document.getElementById('adminLoginSubmit');
            const passInput = document.getElementById('adminPassword');
            const passIcon = document.getElementById('adminPassIcon');
            const togglePass = document.getElementById('adminTogglePass');
            const showForgot = document.getElementById('adminShowForgot');

            // Forgot view
            const viewForgot = document.getElementById('adminViewForgot');
            const forgotForm = document.getElementById('adminForgotForm');
            const forgotAlert = document.getElementById('adminForgotAlert');
            const forgotSpinner = document.getElementById('adminForgotSpinner');
            const forgotBtnText = document.getElementById('adminForgotBtnText');
            const forgotSubmit = document.getElementById('adminForgotSubmit');

            function showView(view) {
                viewLogin.style.display = view === 'login' ? '' : 'none';
                viewForgot.style.display = view === 'forgot' ? '' : 'none';
            }

            function openModal() {
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
                showView('login');
                setTimeout(() => document.getElementById('adminUsername').focus(), 80);
            }

            function closeModal() {
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
                form.reset();
                forgotForm.reset();
                alertEl.innerHTML = '';
                forgotAlert.innerHTML = '';
                spinner.classList.add('d-none');
                btnText.textContent = 'Login';
                submitBtn.disabled = false;
                forgotSpinner.classList.add('d-none');
                forgotBtnText.textContent = 'Send Reset Link';
                forgotSubmit.disabled = false;
                showView('login');
            }

            trigger.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && backdrop.classList.contains('active')) closeModal();
            });

            showForgot.addEventListener('click', () => {
                alertEl.innerHTML = '';
                showView('forgot');
                setTimeout(() => document.getElementById('adminForgotEmail').focus(), 60);
            });

            // Back to Home buttons
            const backToHome = document.getElementById('adminBackToHome');
            const backToHomeForgot = document.getElementById('adminBackToHomeForgot');
            
            backToHome.addEventListener('click', closeModal);
            backToHomeForgot.addEventListener('click', closeModal);

            // Password toggle
            togglePass.addEventListener('click', () => {
                const isPass = passInput.type === 'password';
                passInput.type = isPass ? 'text' : 'password';
                passIcon.className = isPass ? 'bi bi-eye-slash-fill' : 'bi bi-eye';
            });

            // Login submit
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                alertEl.innerHTML = '';
                submitBtn.disabled = true;
                spinner.classList.remove('d-none');
                btnText.textContent = 'Logging in...';
                try {
                    const res = await fetch(`${APP_URL}/backend/api/auth/login.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            username: document.getElementById('adminUsername').value,
                            password: passInput.value
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') {
                        window.location.href = data.redirect;
                    } else {
                        throw new Error(data.message || 'Login failed');
                    }
                } catch (err) {
                    alertEl.innerHTML = `<div class="admin-login-error"><i class="bi bi-exclamation-circle-fill"></i> ${err.message}</div>`;
                    submitBtn.disabled = false;
                    spinner.classList.add('d-none');
                    btnText.textContent = 'Login';
                }
            });

            // Forgot password submit
            forgotForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                forgotAlert.innerHTML = '';
                forgotSubmit.disabled = true;
                forgotSpinner.classList.remove('d-none');
                forgotBtnText.textContent = 'Sending...';
                try {
                    const fd = new FormData();
                    fd.append('email', document.getElementById('adminForgotEmail').value);
                    await fetch(`${APP_URL}/forgot-password.php`, { method: 'POST', body: fd });
                    forgotAlert.innerHTML = `<div class="admin-login-success"><i class="bi bi-check-circle-fill"></i> If that email is registered, a reset link has been sent.</div>`;
                    forgotForm.reset();
                } catch (err) {
                    forgotAlert.innerHTML = `<div class="admin-login-error"><i class="bi bi-exclamation-circle-fill"></i> Failed to send. Please try again.</div>`;
                } finally {
                    forgotSubmit.disabled = false;
                    forgotSpinner.classList.add('d-none');
                    forgotBtnText.textContent = 'Send Reset Link';
                }
            });
        })();
    </script>

</body>

</html>