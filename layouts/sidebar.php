<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="<?= APP_URL ?>/assets/images/sidebarlogo.png" alt="QCPL Logo" class="sidebar-logo">
        </div>
        <h2 class="sidebar-title">QUEZON CITY PUBLIC LIBRARY</h2>
        <div class="sidebar-subtitle-container">
            <span class="sidebar-subtitle">ADMIN</span>
        </div>
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
                <li class="nav-item mt-2">
                    <a href="<?= APP_URL ?>/pages/settings.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                        <i class="bi bi-gear-fill"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar Footer (Profile) -->
    <div class="sidebar-footer">
        <div class="sidebar-avatar">
            <i class="bi bi-person"></i>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?></span>
            <span class="sidebar-user-role">Administrator</span>
        </div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal" class="sidebar-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 24px; border: none; padding: 20px;">
            <div class="modal-body text-center p-3">
                <div
                    style="width: 64px; height: 64px; background-color: #FEF2F2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <i class="bi bi-box-arrow-right" style="font-size: 28px; color: #DC2626;"></i>
                </div>
                <h5 style="font-weight: 800; color: #111827; margin-bottom: 8px; font-size: 20px;">Confirm Logout</h5>
                <p style="color: #6B7280; font-size: 14px; margin-bottom: 24px;">Are you sure you want to log out?</p>
                <div class="d-flex gap-3">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                        style="flex: 1; background-color: #F3F4F6; color: #374151; font-weight: 600; padding: 10px; border-radius: 12px; border: none;">
                        Cancel
                    </button>
                    <a href="<?= APP_URL ?>/logout.php" class="btn"
                        style="flex: 1; background-color: #DC2626; color: white; font-weight: 600; padding: 10px; border-radius: 12px; border: none; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none;">
                        <i class="bi bi-box-arrow-right" style="font-size: 16px;"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>