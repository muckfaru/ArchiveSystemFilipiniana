<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= APP_URL ?>/assets/images/logo.png" alt="QCPL Logo" class="sidebar-logo">
        <h6 class="sidebar-title">QUEZON CITY PUBLIC LIBRARY</h6>
        <span class="sidebar-subtitle">ADMIN</span>
    </div>

    <nav class="sidebar-nav">
        <!-- General Section -->
        <div class="nav-section">
            <span class="nav-section-title">General</span>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/dashboard.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/users.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/history.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : '' ?>">
                        <i class="bi bi-clock-history"></i>
                        <span>History</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Archive Management Section -->
        <div class="nav-section">
            <span class="nav-section-title">Archive Management</span>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/upload.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>">
                        <i class="bi bi-plus-circle"></i>
                        <span>Upload</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/trash.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'trash.php' ? 'active' : '' ?>">
                        <i class="bi bi-trash3"></i>
                        <span>Trash</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/settings.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                        <i class="bi bi-gear-fill"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>