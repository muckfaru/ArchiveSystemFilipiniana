<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="<?= APP_URL ?>/assets/images/sidebarlogo.png" alt="QCPL Logo" class="sidebar-logo">
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- General Section -->
        <div class="nav-section">
            <span class="nav-section-title">GENERAL</span>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin_pages/dashboard.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin_pages/users.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin_pages/history.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : '' ?>">
                        <i class="bi bi-clock-history"></i>
                        <span>History</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin_pages/trash.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'trash.php' ? 'active' : '' ?>">
                        <i class="bi bi-trash3-fill"></i>
                        <span>Trash</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Archive Management Section -->
        <div class="nav-section">
            <span class="nav-section-title">ARCHIVE MANAGEMENT</span>
            <ul class="nav-list">
                <li class="nav-item position-relative">
                    <a href="<?= APP_URL ?>/admin_pages/upload.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>">
                        <i class="bi bi-cloud-upload"></i>
                        <span>Upload</span>
                    </a>
                    <a href="#uploadSubmenu" data-bs-toggle="collapse"
                        aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['upload.php', 'form-library.php', 'form-builder.php', 'metadata-display.php']) ? 'true' : 'false' ?>"
                        class="submenu-toggle position-absolute d-flex align-items-center justify-content-center"
                        style="right: 20px; top: 2px; height: 44px; width: 32px; z-index: 5; color: #6B7280; text-decoration: none;">
                        <i class="bi bi-chevron-down dropdown-indicator"></i>
                    </a>
                    <ul class="collapse sidebar-submenu list-unstyled <?= in_array(basename($_SERVER['PHP_SELF']), ['upload.php', 'form-library.php', 'form-builder.php', 'metadata-display.php']) ? 'show' : '' ?>"
                        id="uploadSubmenu">
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin_pages/form-library.php"
                                class="nav-link sub-nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['form-library.php', 'form-builder.php']) ? 'active' : '' ?>">
                                <i class="bi bi-ui-checks-grid"></i>
                                <span>Custom Metadata</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin_pages/metadata-display.php"
                                class="nav-link sub-nav-link <?= basename($_SERVER['PHP_SELF']) == 'metadata-display.php' ? 'active' : '' ?>">
                                <i class="bi bi-eye"></i>
                                <span>Metadata Display</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin_pages/report.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin_pages/settings.php"
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
    <div class="modal-dialog modal-dialog-centered modal-standard">
        <div class="modal-content modal-minimalist">
            <div class="modal-header">
                <div class="modal-icon icon-warning">
                    <i class="bi bi-box-arrow-right"></i>
                </div>
                <h5 class="modal-title">Confirm Logout</h5>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to log out of your account?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= APP_URL ?>/auth/logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>