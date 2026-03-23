<?php
require_once __DIR__ . '/../../backend/core/config.php';
// Auth logic is usually handled by the page before including header, 
// to allow redirects before output. But if we want consistent auth, we can check it here 
// if not already checked. For now, we assume page handles auth.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?? 'Dashboard' ?> -
        <?= APP_NAME ?>
    </title>

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">

    <!-- Page Specific CSS -->
    <?php if (isset($pageCss)): ?>
        <?php foreach ((array) $pageCss as $css): ?>
            <link href="<?= APP_URL ?>/assets/css/admin_pages/<?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        const APP_URL = "<?= APP_URL ?>";
    </script>
</head>

<body class="admin-shell <?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
