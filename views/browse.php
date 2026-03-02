<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Archives - Quezon City Public Library</title>
    <meta name="description" content="Browse and filter through our comprehensive digital archive collection.">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            <a href="<?= APP_URL ?>/public.php" class="public-nav-link">
                <i class="bi bi-house-door"></i>
                Home
            </a>
            <a href="<?= APP_URL ?>/browse.php" class="public-nav-link active">
                <i class="bi bi-grid-3x3-gap"></i>
                Browse
            </a>
        </nav>
        
        <button id="adminLoginTrigger" class="public-admin-login-btn" type="button">
            <i class="bi bi-person-lock"></i>
            Admin Login
        </button>
    </header>

    <!-- ==================== BROWSE LAYOUT ==================== -->
    <div class="browse-layout-redesign">

        <!-- Sidebar Filters -->
        <aside class="browse-sidebar-redesign">
            <div class="browse-sidebar-header">
                <h3 class="browse-sidebar-title">Filters</h3>
                <?php if ($searchQuery || $categoryFilter || $languageFilter || $editionFilter || $dateFrom || $dateTo): ?>
                    <a href="<?= APP_URL ?>/browse.php" class="browse-clear-all">
                        <i class="bi bi-x-circle"></i>
                        Clear all
                    </a>
                <?php endif; ?>
            </div>

            <!-- Categories Section -->
            <div class="browse-filter-section">
                <button class="browse-filter-toggle" type="button" data-target="categories">
                    <span>CATEGORIES</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="browse-filter-content" id="categories">
                    <ul class="browse-category-list-redesign">
                        <!-- All Categories Option -->
                        <li class="browse-category-item-redesign">
                            <label class="browse-checkbox-label">
                                <input type="checkbox" name="category" <?= empty($categoryFilter) ? 'checked' : '' ?> 
                                    onchange="if(this.checked) window.location.href='?q=<?= urlencode($searchQuery) ?>&language=<?= urlencode($languageFilter) ?>&edition=<?= urlencode($editionFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&sort=<?= urlencode($sortFilter) ?>'">
                                <span>All Categories</span>
                                <span class="browse-count-badge"><?= number_format($totalCollectionsCount) ?></span>
                            </label>
                        </li>
                        <?php foreach ($categoriesWithCounts as $cat): ?>
                            <?php $isActive = $categoryFilter == $cat['id']; ?>
                            <li class="browse-category-item-redesign">
                                <label class="browse-checkbox-label">
                                    <input type="checkbox" name="category" <?= $isActive ? 'checked' : '' ?> 
                                        onchange="window.location.href='?category=<?= $cat['id'] ?>&q=<?= urlencode($searchQuery) ?>&language=<?= urlencode($languageFilter) ?>&edition=<?= urlencode($editionFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&sort=<?= urlencode($sortFilter) ?>'">
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                    <span class="browse-count-badge"><?= number_format($cat['count']) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Edition Filter -->
            <?php if (!empty($editions)): ?>
                <div class="browse-filter-section">
                    <button class="browse-filter-toggle" type="button" data-target="editions">
                        <span>EDITION</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="browse-filter-content" id="editions">
                        <ul class="browse-category-list-redesign">
                            <?php foreach ($editions as $edition): ?>
                                <?php $isActive = $editionFilter == $edition; ?>
                                <li class="browse-category-item-redesign">
                                    <label class="browse-checkbox-label">
                                        <input type="checkbox" name="edition" <?= $isActive ? 'checked' : '' ?>
                                            onchange="window.location.href='?edition=<?= urlencode($edition) ?>&q=<?= urlencode($searchQuery) ?>&category=<?= urlencode($categoryFilter) ?>&language=<?= urlencode($languageFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&sort=<?= urlencode($sortFilter) ?>'">
                                        <span><?= htmlspecialchars($edition) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Language Filter -->
            <?php if (!empty($languages)): ?>
                <div class="browse-filter-section">
                    <button class="browse-filter-toggle" type="button" data-target="languages">
                        <span>LANGUAGES</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="browse-filter-content" id="languages">
                        <ul class="browse-category-list-redesign">
                            <?php foreach ($languages as $lang): ?>
                                <?php 
                                $isActive = $languageFilter == $lang['id'];
                                // Count documents for this language
                                $langCountSql = "SELECT COUNT(*) FROM newspapers WHERE deleted_at IS NULL AND language_id = ?";
                                $langCountStmt = $pdo->prepare($langCountSql);
                                $langCountStmt->execute([$lang['id']]);
                                $langCount = $langCountStmt->fetchColumn();
                                ?>
                                <li class="browse-category-item-redesign">
                                    <label class="browse-checkbox-label">
                                        <input type="checkbox" name="language" <?= $isActive ? 'checked' : '' ?>
                                            onchange="window.location.href='?language=<?= $lang['id'] ?>&q=<?= urlencode($searchQuery) ?>&category=<?= urlencode($categoryFilter) ?>&edition=<?= urlencode($editionFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&sort=<?= urlencode($sortFilter) ?>'">
                                        <span><?= htmlspecialchars($lang['name']) ?></span>
                                        <span class="browse-count-badge"><?= number_format($langCount) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Publication Period Filter -->
            <div class="browse-filter-section">
                <button class="browse-filter-toggle" type="button" data-target="publication-period">
                    <span>PUBLICATION PERIOD</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="browse-filter-content" id="publication-period">
                    <form method="GET" action="" class="browse-date-range-form" id="dateRangeForm">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                        <input type="hidden" name="language" value="<?= htmlspecialchars($languageFilter) ?>">
                        <input type="hidden" name="edition" value="<?= htmlspecialchars($editionFilter) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortFilter) ?>">
                        
                        <div class="browse-date-inputs">
                            <div class="browse-date-input-group">
                                <label>FROM</label>
                                <input type="number" name="date_from" class="browse-year-input" 
                                    placeholder="1990" 
                                    value="<?= htmlspecialchars($dateFrom) ?>"
                                    min="<?= $minYear ?>" max="<?= $maxYear ?>">
                            </div>
                            <div class="browse-date-input-group">
                                <label>TO</label>
                                <input type="number" name="date_to" class="browse-year-input" 
                                    placeholder="<?= $maxYear ?>" 
                                    value="<?= htmlspecialchars($dateTo) ?>"
                                    min="<?= $minYear ?>" max="<?= $maxYear ?>">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Apply Filters Button (Outside dropdown) -->
            <button type="button" class="browse-apply-filters-btn" onclick="document.getElementById('dateRangeForm').submit()">
                Apply Filters
            </button>

        </aside>

        <!-- Main Results Column -->
        <main class="browse-main-redesign">

            <!-- Search Box -->
            <div class="browse-search-box-redesign">
                <form method="GET" action="" id="browseSearchForm">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                    <input type="hidden" name="language" value="<?= htmlspecialchars($languageFilter) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortFilter) ?>">
                    <div class="browse-search-input-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" class="browse-search-input-redesign" name="q"
                            placeholder="Search newspapers, journals, or archives..."
                            value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off">
                    </div>
                </form>
            </div>

            <!-- Results Bar -->
            <div class="browse-results-bar-redesign">
                <div class="browse-results-count-redesign">
                    <?php if ($searchQuery): ?>
                        Showing results for "<strong><?= htmlspecialchars($searchQuery) ?></strong>"
                    <?php else: ?>
                        Showing <?= number_format($totalResults) ?> newspapers
                    <?php endif; ?>
                </div>
                
                <!-- Sort Filter -->
                <div class="browse-sort-wrap-redesign">
                    <form action="" method="GET" id="sortFilterForm" class="browse-sort-form-redesign">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                        <input type="hidden" name="language" value="<?= htmlspecialchars($languageFilter) ?>">
                        
                        <label for="sortSelect">Sort by:</label>
                        <select class="browse-sort-select-redesign" name="sort" id="sortSelect" onchange="this.form.submit()">
                            <option value="newest" <?= $sortFilter === 'newest' || !$sortFilter ? 'selected' : '' ?>>Relevance</option>
                            <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="a-z" <?= $sortFilter === 'a-z' ? 'selected' : '' ?>>A-Z</option>
                            <option value="z-a" <?= $sortFilter === 'z-a' ? 'selected' : '' ?>>Z-A</option>
                        </select>
                    </form>
                    
                    <!-- View Toggle -->
                    <div class="browse-view-toggle">
                        <button class="browse-view-btn active" data-view="grid">
                            <i class="bi bi-grid-3x3-gap"></i>
                        </button>
                        <button class="browse-view-btn" data-view="list">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Filters Display -->
            <?php if ($hasActiveFilters): ?>
                <div class="browse-active-filters">
                    <div class="active-filters-label">
                        <i class="bi bi-funnel-fill me-2"></i>
                        <strong>Active Filters:</strong>
                    </div>
                    <div class="active-filters-tags">
                        <?php foreach ($activeFilters as $filterTag): ?>
                            <?= $filterTag ?>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?= APP_URL ?>/browse.php<?= $searchQuery ? '?q=' . urlencode($searchQuery) : '' ?>" class="clear-filters-btn">
                        <i class="bi bi-x-circle me-1"></i>Clear All Filters
                    </a>
                </div>
            <?php endif; ?>

            <!-- Document Grid -->
            <?php if (empty($documents)): ?>
                <div class="browse-empty-state">
                    <i class="bi bi-search"></i>
                    <h5>No Results Found</h5>
                    <p>We couldn't find any documents matching your criteria.</p>
                    <a href="<?= APP_URL ?>/browse.php" class="browse-clear-btn">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="browse-grid-compact">
                    <?php foreach ($documents as $paper): ?>
                        <?php
                        $catName = $paper['category_name'] ?? 'Uncategorized';
                        $catClass = 'public-cat-' . strtolower(preg_replace('/[^a-z0-9]/i', '-', $catName));
                        $publicationLabel = $paper['publication_date'] ? formatPublicationDate($paper['publication_date'], true) : '';
                        ?>
                        <div class="public-file-card browse-file-card-compact" data-id="<?= $paper['id'] ?>"
                            data-title="<?= htmlspecialchars($paper['title']) ?>"
                            data-thumbnail="<?= $paper['thumbnail_path'] ? APP_URL . '/' . $paper['thumbnail_path'] : '' ?>"
                            data-date="<?= htmlspecialchars($publicationLabel) ?>"
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
                                        <?= highlightSearch(strtoupper($publicationLabel), $searchQuery) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Title -->
                                <div class="public-file-title">
                                    <?= highlightSearch($paper['title'], $searchQuery) ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($paper['description'])): ?>
                                    <div class="public-file-description">
                                        <?= highlightSearch($paper['description'], $searchQuery) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Metadata for List View Only (Below Description) -->
                                <div class="browse-list-metadata">
                                    <?php if (!empty($paper['publisher'])): ?>
                                        <div class="browse-meta-item" data-label="Publisher:">
                                            <span><?= htmlspecialchars($paper['publisher']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($paper['language_name'])): ?>
                                        <div class="browse-meta-item" data-label="Language:">
                                            <span><?= htmlspecialchars($paper['language_name']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($paper['page_count'])): ?>
                                        <div class="browse-meta-item" data-label="Pages:">
                                            <span><?= $paper['page_count'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($paper['edition'])): ?>
                                        <div class="browse-meta-item" data-label="Edition:">
                                            <span><?= htmlspecialchars($paper['edition']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($paper['volume_issue'])): ?>
                                        <div class="browse-meta-item" data-label="Volume:">
                                            <span><?= htmlspecialchars($paper['volume_issue']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <?php
                    function getBrowsePaginationUrl($page, $paramsArr) {
                        $paramsArr['page'] = $page;
                        return '?' . http_build_query($paramsArr);
                    }
                    ?>
                    <div class="public-pagination">
                        <!-- Prev arrow -->
                        <a href="<?= getBrowsePaginationUrl(max(1, $currentPage - 1), $_GET) ?>"
                            class="public-page-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>

                        <?php
                        $startPage = max(1, $currentPage - 1);
                        $endPage = min($totalPages, $currentPage + 1);

                        if ($startPage > 1): ?>
                            <a href="<?= getBrowsePaginationUrl(1, $_GET) ?>" class="public-page-btn">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="public-page-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="<?= getBrowsePaginationUrl($i, $_GET) ?>"
                                class="public-page-btn <?= $i === $currentPage ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="public-page-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="<?= getBrowsePaginationUrl($totalPages, $_GET) ?>" class="public-page-btn">
                                <?= $totalPages ?>
                            </a>
                        <?php endif; ?>

                        <!-- Next arrow -->
                        <a href="<?= getBrowsePaginationUrl(min($totalPages, $currentPage + 1), $_GET) ?>"
                            class="public-page-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </main>
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

                <span id="publicModalCategory" class="public-modal-category-badge">CATEGORY</span>
                <h2 id="publicModalTitle" class="public-modal-title">File Title</h2>

                <div id="publicModalDescriptionWrap" class="public-modal-description-wrap" style="display: none;">
                    <p id="publicModalDescription" class="public-modal-description"></p>
                </div>

                <p class="public-modal-meta-section-title">Document Details</p>

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
    <!-- Public Page JS -->
    <script src="<?= APP_URL ?>/assets/js/pages/public.js"></script>
    
    <!-- Browse Filter Toggle Script -->
    <script>
        // Filter toggle functionality
        document.querySelectorAll('.browse-filter-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.classList.toggle('collapsed');
                const targetId = this.getAttribute('data-target');
                const content = document.getElementById(targetId);
                if (content) {
                    content.style.display = content.style.display === 'none' ? 'block' : 'none';
                }
            });
        });

        // View toggle functionality
        const viewBtns = document.querySelectorAll('.browse-view-btn');
        const gridContainer = document.querySelector('.browse-grid-compact');
        
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                viewBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.getAttribute('data-view');
                if (view === 'list') {
                    gridContainer.classList.add('browse-list-view');
                    gridContainer.classList.remove('browse-grid-compact');
                } else {
                    gridContainer.classList.remove('browse-list-view');
                    gridContainer.classList.add('browse-grid-compact');
                }
            });
        });

        // Live search functionality
        const searchInput = document.querySelector('.browse-search-input-redesign');
        let searchTimeout;
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }, 500); // Wait 500ms after user stops typing
            });
        }
    </script>
    
    <!-- Admin Login Modal JS -->
    <script>
        (function() {
            const backdrop = document.getElementById('adminLoginBackdrop');
            const trigger = document.getElementById('adminLoginTrigger');
            const closeBtn = document.getElementById('adminLoginClose');
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

            const backToHome = document.getElementById('adminBackToHome');
            const backToHomeForgot = document.getElementById('adminBackToHomeForgot');
            
            backToHome.addEventListener('click', closeModal);
            backToHomeForgot.addEventListener('click', closeModal);

            togglePass.addEventListener('click', () => {
                const isPass = passInput.type === 'password';
                passInput.type = isPass ? 'text' : 'password';
                passIcon.className = isPass ? 'bi bi-eye-slash-fill' : 'bi bi-eye';
            });

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
