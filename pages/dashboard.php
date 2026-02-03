<?php
/**
 * Dashboard Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get stats
$totalArchives = countArchives();
$totalIssues = countIssues();
$yearsCovered = getYearsCovered();
$totalCategories = countCategories();

// Get categories and languages for filters
$categories = getCategories();
$languages = getLanguages();

// Get recent newspapers
$recentNewspapers = getRecentNewspapers(8);

// Handle search
$searchQuery = $_GET['q'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$languageFilter = $_GET['language'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$searchResults = [];
if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo) {
    $sql = "SELECT n.*, c.name as category_name, l.name as language_name 
            FROM newspapers n 
            LEFT JOIN categories c ON n.category_id = c.id 
            LEFT JOIN languages l ON n.language_id = l.id 
            WHERE n.deleted_at IS NULL";
    $params = [];

    if ($searchQuery) {
        $sql .= " AND (n.title LIKE ? OR n.keywords LIKE ? OR n.description LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    if ($categoryFilter) {
        $sql .= " AND n.category_id = ?";
        $params[] = $categoryFilter;
    }

    if ($languageFilter) {
        $sql .= " AND n.language_id = ?";
        $params[] = $languageFilter;
    }

    if ($dateFrom) {
        $sql .= " AND n.publication_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $sql .= " AND n.publication_date <= ?";
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard -
        <?= APP_NAME ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Overview</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Archives</span>
                        <i class="bi bi-file-earmark-text stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value">
                        <?= number_format($totalArchives) ?>
                    </div>
                    <div class="stat-card-info">+2.4% from last month</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Issues Count</span>
                        <i class="bi bi-files stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value">
                        <?= number_format($totalIssues) ?>
                    </div>
                    <div class="stat-card-info">+3.6% since monday</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Years Covered</span>
                        <i class="bi bi-calendar-range stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value">
                        <?= $yearsCovered ?>
                    </div>
                    <div class="stat-card-info neutral">
                        <?= $yearsCovered !== 'N/A' ? (intval(explode('-', $yearsCovered)[1] ?? date('Y')) - intval(explode('-', $yearsCovered)[0] ?? date('Y'))) . ' years' : '' ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Categories</span>
                        <i class="bi bi-grid-3x3-gap stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value">
                        <?= $totalCategories ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Search & Filter -->
        <div class="search-filter-card">
            <div class="search-filter-title">
                <i class="bi bi-funnel"></i>
                Advanced Search & Filter
            </div>

            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" name="q"
                                placeholder="Search titles, headlines, keywords..."
                                value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Categories</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Language</label>
                        <select class="form-select" name="language">
                            <option value="">All Language</option>
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?= $lang['id'] ?>" <?= $languageFilter == $lang['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lang['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">DATE RANGE</label>
                        <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="search-btn w-100">
                            <i class="bi bi-search"></i>
                            Search
                        </button>
                    </div>
                </div>

                <!-- Recent Tags -->
                <div class="recent-tags">
                    <span class="text-muted me-2" style="font-size: 12px;">RECENTS</span>
                    <span class="recent-tag">
                        Philstar <i class="bi bi-x remove"></i>
                    </span>
                    <span class="recent-tag">
                        New York Times <i class="bi bi-x remove"></i>
                    </span>
                    <span class="recent-tag">
                        Daily Post <i class="bi bi-x remove"></i>
                    </span>
                </div>
            </form>
        </div>

        <!-- Search Results (if any) -->
        <?php if ($searchQuery || $categoryFilter || $languageFilter || $dateFrom || $dateTo): ?>
            <div class="mb-4">
                <h5>Search Results (
                    <?= count($searchResults) ?> found)
                </h5>
                <?php if (empty($searchResults)): ?>
                    <div class="alert alert-info">No results found for your search criteria.</div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($searchResults as $paper): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="newspaper-card">
                                    <?php if ($paper['thumbnail_path']): ?>
                                        <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
                                    <?php else: ?>
                                        <div class="newspaper-thumbnail bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="bi bi-newspaper text-white" style="font-size: 48px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="newspaper-info">
                                        <div class="newspaper-category">
                                            <?= $paper['category_name'] ?? 'Uncategorized' ?>
                                        </div>
                                        <h6 class="newspaper-title">
                                            <?= htmlspecialchars($paper['title']) ?>
                                        </h6>
                                        <div class="newspaper-date">
                                            <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : 'N/A' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Recent Activities -->
        <div class="recent-activities">
            <div class="recent-activities-header">
                <h2 class="recent-activities-title">Recent Activities</h2>
                <a href="<?= APP_URL ?>/pages/history.php" class="view-all-link">View all</a>
            </div>

            <?php if (empty($recentNewspapers)): ?>
                <div class="alert alert-info">No newspapers uploaded yet. <a href="<?= APP_URL ?>/pages/upload.php">Upload
                        your first newspaper</a>.</div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($recentNewspapers as $paper): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="newspaper-card">
                                <?php if ($paper['thumbnail_path']): ?>
                                    <img src="<?= APP_URL ?>/<?= $paper['thumbnail_path'] ?>" class="newspaper-thumbnail" alt="">
                                <?php else: ?>
                                    <div class="newspaper-thumbnail bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="bi bi-newspaper text-white" style="font-size: 48px;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="newspaper-info">
                                    <div class="newspaper-category <?= strtolower($paper['category_name'] ?? '') ?>">
                                        <?= strtoupper($paper['category_name'] ?? 'UNCATEGORIZED') ?>
                                    </div>
                                    <h6 class="newspaper-title">
                                        <?= htmlspecialchars($paper['title']) ?>
                                    </h6>
                                    <div class="newspaper-date">
                                        <?= $paper['publication_date'] ? date('d F Y', strtotime($paper['publication_date'])) : date('d F Y', strtotime($paper['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>