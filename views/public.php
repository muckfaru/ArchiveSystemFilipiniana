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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Public Page CSS -->
    <link href="<?= APP_URL ?>/assets/css/user_pages/public.css?v=<?= time() ?>" rel="stylesheet">

    <script>const APP_URL = "<?= APP_URL ?>";</script>
</head>

<body class="public-page public-page-home">

    <!-- ==================== HEADER ==================== -->
    <header class="public-header">
        <div class="public-header-left">
            <button class="public-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavCollapse" aria-controls="publicNavCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>

            <nav class="public-nav navbar-collapse collapse" id="publicNavCollapse">
                <a href="<?= route_url('home') ?>" class="public-nav-link <?= !isset($_GET['view']) || $_GET['view'] !== 'browse' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i>
                    Home
                </a>
                <a href="<?= route_url('browse') ?>" class="public-nav-link <?= isset($_GET['view']) && $_GET['view'] === 'browse' ? 'active' : '' ?>">
                    <i class="bi bi-grid-3x3-gap"></i>
                    Browse
                </a>
            </nav>
        </div>

        <div class="public-header-center">
            <a href="<?= route_url('home') ?>" class="public-header-brand public-header-brand-center">
                <img src="<?= APP_URL ?>/assets/images/public_logo.png" alt="QCPL Logo" class="public-header-logo">
                <span class="public-header-brand-name">Quezon City Public Library</span>
            </a>

            <form method="GET" action="<?= route_url('home') ?>" id="publicHeaderSearchForm" class="public-header-search" role="search">
                <?php if ($categoryFilter): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                <?php endif; ?>
                <label for="publicHeaderSearchInput" class="visually-hidden">Search archives</label>
                <div class="public-header-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="publicHeaderSearchInput"
                        class="public-header-search-input"
                        name="q"
                        placeholder="Search archives..."
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        autocomplete="off">
                    <button type="button" class="public-header-search-clear" id="publicHeaderSearchClear" aria-label="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="submit" class="public-header-search-submit">Search</button>
                </div>
            </form>
        </div>

        <div class="public-header-actions">
            <button type="button" class="public-header-icon-btn" id="publicHeaderSearchToggle" aria-label="Open search" aria-expanded="false">
                <i class="bi bi-search"></i>
            </button>

            <div class="dropdown">
                <button
                    class="public-header-icon-btn"
                    type="button"
                    id="publicHeaderMenuToggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Open menu">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end public-header-menu" aria-labelledby="publicHeaderMenuToggle">
                    <li>
                        <button id="adminLoginTrigger" class="dropdown-item public-header-menu-item" type="button">
                            <i class="bi bi-person-lock"></i>
                            <span>Admin Login</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div id="publicContentArea">
    <!-- ==================== FILE GRID / CATALOG ==================== -->
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

    function renderPublicDiscoveryCard(array $paper, string $extraClass = ''): void
    {
        $catName = getCategoryFromMetadata($paper['custom_metadata'] ?? []);
        $catClass = 'public-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $catName));
        $modalMetaJson = htmlspecialchars(json_encode($paper['modal_metadata']), ENT_QUOTES, 'UTF-8');
        $pubDate = getMetadataValueByLabel(
            $paper['custom_metadata'] ?? [],
            ['publication date', 'date published', 'date issued', 'date'],
            ''
        );
        ?>
        <div class="public-file-card <?= htmlspecialchars($extraClass) ?>"
            data-id="<?= url_encrypt($paper['id']) ?>"
            data-title="<?= htmlspecialchars($paper['title']) ?>"
            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
            data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
            data-category="<?= htmlspecialchars($catName) ?>"
            data-modal-metadata="<?= $modalMetaJson ?>">
            <div class="public-thumb-wrap">
                <?php if ($paper['thumbnail_path']): ?>
                    <img src="<?= APP_URL ?>/<?= htmlspecialchars($paper['thumbnail_path']) ?>"
                        class="public-file-thumbnail" alt="<?= htmlspecialchars($paper['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="public-file-thumbnail-placeholder">
                        <i class="bi bi-newspaper"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="public-file-info">
                <?php if ($catName !== 'Uncategorized'): ?>
                    <span class="public-file-category-top <?= $catClass ?>"><?= htmlspecialchars($catName) ?></span>
                <?php endif; ?>
                <div class="public-file-title"><?= htmlspecialchars($paper['title']) ?></div>
                <?php if ($pubDate): ?>
                    <div class="public-file-date"><?= htmlspecialchars(formatPublicationDate($pubDate)) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    ?>

    <?php if ($isSearchMode): ?>
        <!-- ── SEARCH RESULTS: flat grid with pagination (same as before) ── -->
        <div class="public-grid-container" data-total="<?= (int) $totalResults ?>">
            <div class="public-section-heading">
                <div>
                    <span class="public-section-kicker">Search Results</span>
                    <h2 class="public-section-title">
                        <?php if (empty($documents)): ?>
                            No matches for &ldquo;<?= htmlspecialchars($searchQuery ?: 'your search') ?>&rdquo;
                        <?php elseif ($searchQuery): ?>
                            <?= number_format($totalResults) ?> <?= $totalResults === 1 ? 'result' : 'results' ?> for &ldquo;<?= htmlspecialchars($searchQuery) ?>&rdquo;
                        <?php else: ?>
                            <?= number_format($totalResults) ?> filtered <?= $totalResults === 1 ? 'result' : 'results' ?>
                        <?php endif; ?>
                    </h2>
                </div>
            </div>
            <?php if (empty($documents)): ?>
                <div class="public-empty-state public-empty-state-search">
                    <i class="bi bi-search"></i>
                    <h5>No Results Found</h5>
                    <p>We couldn't find any documents matching your search. Try another keyword, or open the full archive catalog.</p>
                    <div class="public-empty-state-actions">
                        <a href="<?= route_url('browse') ?>" class="public-read-btn public-empty-state-browse-btn">
                            Browse All Archives
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($documents as $paper): ?>
                        <?php
                        $catName = getCategoryFromMetadata($paper['custom_metadata'] ?? []);
                        $catClass = 'public-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $catName));
                        ?>
                        <div class="col-6 col-md-3">
                            <?php 
                            $modalMetaJson = htmlspecialchars(json_encode($paper['modal_metadata']), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="public-file-card" 
                                data-id="<?= url_encrypt($paper['id']) ?>"
                                data-title="<?= htmlspecialchars($paper['title']) ?>"
                                data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                                data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                                data-category="<?= htmlspecialchars($catName) ?>"
                                data-modal-metadata="<?= $modalMetaJson ?>">

                                <div class="public-thumb-wrap">
                                    <?php if ($paper['thumbnail_path']): ?>
                                        <img src="<?= APP_URL ?>/<?= htmlspecialchars($paper['thumbnail_path']) ?>"
                                            class="public-file-thumbnail" alt="<?= htmlspecialchars($paper['title']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="public-file-thumbnail-placeholder">
                                            <i class="bi bi-newspaper"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="public-file-info">
                                    <?php if ($catName !== 'Uncategorized'): ?>
                                        <span class="public-file-category-top <?= $catClass ?>"><?= htmlspecialchars($catName) ?></span>
                                    <?php endif; ?>
                                    <div class="public-file-title">
                                        <?= pubHighlight($paper['title'], $searchQuery) ?>
                                    </div>
                                    <?php if (!empty($paper['display_metadata'])): ?>
                                        <?php foreach ($paper['display_metadata'] as $meta): ?>
                                            <?php 
                                            $label = strtolower(trim($meta['label']));
                                            if ($label === 'publication date' || $label === 'date published' || $label === 'date'): 
                                            ?>
                                                <div class="public-file-date">
                                                    <?= pubHighlight(formatPublicationDate($meta['value']), $searchQuery) ?>
                                                </div>
                                            <?php 
                                                break;
                                            endif; 
                                            ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <?php
                    function buildPublicPageUrl($p, $q, $cat)
                    {
                        $params = ['page' => $p];
                        if ($q) $params['q'] = $q;
                        if ($cat) $params['category'] = $cat;
                        return '?' . http_build_query($params);
                    }
                    ?>
                    <div class="public-pagination">
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
                        <a href="<?= buildPublicPageUrl(min($totalPages, $currentPage + 1), $searchQuery, $categoryFilter) ?>"
                            class="public-page-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ══════════════ CATALOG SHELVES (PressReader-style) ══════════════ -->


        <div class="catalog-container" data-total="<?= array_sum(array_column($catalogShelves, 'total')) ?>">
            <?php if (empty($catalogShelves)): ?>
                <div class="public-grid-container">
                    <div class="public-empty-state">
                        <i class="bi bi-journal-richtext"></i>
                        <h5>No Archives Yet</h5>
                        <p style="font-size: 14px;">There are no documents in the archive at this time.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($catalogShelves as $shelf): ?>
                    <section class="catalog-shelf">
                        <!-- Shelf Header -->
                        <div class="catalog-shelf-header">
                            <div class="catalog-shelf-title-wrap">
                                <?php
                                $shelfIcon = 'bi-collection';
                                $typeLower = strtolower($shelf['type']);
                                if (strpos($typeLower, 'newspaper') !== false) $shelfIcon = 'bi-newspaper';
                                elseif (strpos($typeLower, 'magazine') !== false) $shelfIcon = 'bi-journal-richtext';
                                elseif (strpos($typeLower, 'book') !== false) $shelfIcon = 'bi-book';
                                elseif (strpos($typeLower, 'journal') !== false) $shelfIcon = 'bi-journal-text';
                                elseif ($shelf['type'] === 'All Archives') $shelfIcon = 'bi-archive';
                                ?>
                                <i class="bi <?= $shelfIcon ?> catalog-shelf-icon"></i>
                                <h2 class="catalog-shelf-title"><?= htmlspecialchars($shelf['type']) ?>s</h2>
                                <span class="catalog-shelf-count"><?= number_format($shelf['total']) ?></span>
                            </div>
                            <?php if ($shelf['type'] !== 'All Archives'): ?>
                                <a href="<?= htmlspecialchars($shelf['see_all_url'] ?? route_url('browse', ['publication_type' => $shelf['type']])) ?>" class="catalog-see-all">
                                    See All <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?= route_url('browse') ?>" class="catalog-see-all">
                                    Browse All <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Shelf Scroll Track -->
                        <div class="catalog-shelf-track-wrap">
                            <button class="catalog-scroll-arrow catalog-scroll-left" aria-label="Scroll left">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <div class="catalog-shelf-track">
                                <?php foreach ($shelf['docs'] as $paper): ?>
                                    <?php
                                    $catName = getCategoryFromMetadata($paper['custom_metadata'] ?? []);
                                    $catClass = 'public-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $catName));
                                    $modalMetaJson = htmlspecialchars(json_encode($paper['modal_metadata']), ENT_QUOTES, 'UTF-8');
                                    
                                    // Extract publication date
                                    $pubDate = '';
                                    if (!empty($paper['display_metadata'])) {
                                        foreach ($paper['display_metadata'] as $meta) {
                                            $metaLabel = strtolower(trim($meta['label']));
                                            if ($metaLabel === 'publication date' || $metaLabel === 'date published' || $metaLabel === 'date') {
                                                $pubDate = $meta['value'];
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="catalog-card public-file-card"
                                        data-id="<?= url_encrypt($paper['id']) ?>"
                                        data-title="<?= htmlspecialchars($paper['title']) ?>"
                                        data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                                        data-is-bulk="<?= $paper['is_bulk_image'] ?? 0 ?>"
                                        data-category="<?= htmlspecialchars($catName) ?>"
                                        data-modal-metadata="<?= $modalMetaJson ?>">

                                        <!-- Thumbnail -->
                                        <div class="catalog-card-thumb">
                                            <?php if ($paper['thumbnail_path']): ?>
                                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($paper['thumbnail_path']) ?>"
                                                    alt="<?= htmlspecialchars($paper['title']) ?>" loading="lazy">
                                            <?php else: ?>
                                                <div class="catalog-card-thumb-placeholder">
                                                    <i class="bi bi-newspaper"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Card Info -->
                                        <div class="catalog-card-info">
                                            <?php if ($catName !== 'Uncategorized'): ?>
                                                <span class="public-file-category-top <?= $catClass ?>"><?= htmlspecialchars($catName) ?></span>
                                            <?php endif; ?>
                                            <div class="catalog-card-title"><?= htmlspecialchars($paper['title']) ?></div>
                                            <?php if ($pubDate): ?>
                                                <div class="catalog-card-date"><?= htmlspecialchars(formatPublicationDate($pubDate)) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="catalog-scroll-arrow catalog-scroll-right" aria-label="Scroll right">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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

                <!-- Title -->
                <h2 id="publicModalTitle" class="public-modal-title">File Title</h2>

                <!-- Section label -->
                <p class="public-modal-meta-section-title">Document Details</p>

                <!-- Dynamic metadata rows container -->
                <div id="publicModalMetadata"></div>
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
                <p class="admin-login-subtext" id="adminForgotSubtext">Enter your email and we'll send you a reset link.</p>

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
                <div class="admin-forgot-actions">
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
    <script src="<?= APP_URL ?>/assets/js/user_pages/public.js"></script>
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
            const forgotEmailField = document.querySelector('#adminForgotForm .admin-login-field');
            const forgotSubtext = document.getElementById('adminForgotSubtext');

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

            function openModalWithView(view) {
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
                showView(view);
                setTimeout(() => {
                    const target = view === 'forgot'
                        ? document.getElementById('adminForgotEmail')
                        : document.getElementById('adminUsername');
                    if (target) target.focus();
                }, 80);
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
                forgotForm.style.display = '';
                if (forgotEmailField) forgotEmailField.style.display = '';
                if (forgotSubtext) forgotSubtext.style.display = '';
                backToHomeForgot.classList.remove('admin-back-to-home-btn-primary');
                backdrop.querySelector('.admin-login-modal')?.classList.remove('admin-forgot-success');
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

            const adminMode = new URLSearchParams(window.location.search).get('admin');
            if (adminMode === 'forgot') {
                openModalWithView('forgot');
            } else if (adminMode === 'login') {
                openModalWithView('login');
            }

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
                    const res = await fetch(`<?= route_url('forgot-password') ?>`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: fd
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Failed to send reset email.');
                    }
                    forgotAlert.innerHTML = `<div class="admin-login-success"><i class="bi bi-check-circle-fill"></i><span>If that email is registered, a reset link has been sent.</span></div>`;
                    forgotForm.reset();
                    if (forgotEmailField) forgotEmailField.style.display = 'none';
                    forgotSubmit.style.display = 'none';
                    if (forgotSubtext) forgotSubtext.style.display = 'none';
                    backToHomeForgot.classList.add('admin-back-to-home-btn-primary');
                    backdrop.querySelector('.admin-login-modal')?.classList.add('admin-forgot-success');
                } catch (err) {
                    forgotAlert.innerHTML = `<div class="admin-login-error"><i class="bi bi-exclamation-circle-fill"></i> ${err.message}</div>`;
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
