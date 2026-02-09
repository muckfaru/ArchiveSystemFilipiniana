<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header" style="text-align: center; padding: 25px 20px 20px;">
        <img src="<?= APP_URL ?>/assets/images/sidebarlogo.png" alt="QCPL Logo"
            style="width: 130px; height: auto; margin: 0 auto 15px; display: block; object-fit: contain; filter: drop-shadow(0 0 2px rgba(255,255,255,0.8)) drop-shadow(0 0 4px rgba(255,255,255,0.5));">
        <h2
            style="color: #fff; font-size: 13px; font-weight: 600; letter-spacing: 1.5px; margin: 0 0 5px; text-transform: uppercase;">
            QUEZON CITY PUBLIC LIBRARY</h2>
        <span style="color: rgba(255,255,255,0.7); font-size: 12px; letter-spacing: 3px; font-weight: 500;">A D M I
            N</span>
    </div>

    <nav class="sidebar-nav" style="padding: 0 15px; flex: 1;">
        <!-- General Section -->
        <div class="nav-section" style="margin-bottom: 25px;">
            <div style="width: 100%; height: 1px; background: rgba(255,255,255,0.2); margin-bottom: 15px;"></div>
            <span class="nav-section-title"
                style="font-size: 10px; color: rgba(255,255,255,0.4); letter-spacing: 1px; padding: 0 10px; margin-bottom: 10px; display: block;">General</span>
            <ul class="nav-list" style="list-style: none; padding: 0; margin: 0;">
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/dashboard.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
                        style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 10px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <i class="bi bi-grid-fill" style="font-size: 18px;"></i>
                        <span style="font-size: 14px;">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/users.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>"
                        style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 10px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <i class="bi bi-people-fill" style="font-size: 18px;"></i>
                        <span style="font-size: 14px;">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/history.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : '' ?>"
                        style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 10px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <i class="bi bi-clock-history" style="font-size: 18px;"></i>
                        <span style="font-size: 14px;">History</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Archive Management Section -->
        <div class="nav-section">
            <span class="nav-section-title"
                style="font-size: 10px; color: rgba(255,255,255,0.4); letter-spacing: 1px; padding: 0 10px; margin-bottom: 10px; display: block;">Archive
                Management</span>
            <ul class="nav-list" style="list-style: none; padding: 0; margin: 0;">
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/upload.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>"
                        style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 10px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <i class="bi bi-plus-circle" style="font-size: 18px;"></i>
                        <span style="font-size: 14px;">Upload</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/trash.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'trash.php' ? 'active' : '' ?>"
                        style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 10px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; <?= basename($_SERVER['PHP_SELF']) == 'trash.php' ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <i class="bi bi-trash3" style="font-size: 18px;"></i>
                        <span style="font-size: 14px;">Trash</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/pages/settings.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>"
                        style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 10px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <i class="bi bi-gear-fill" style="font-size: 18px;"></i>
                        <span style="font-size: 14px;">Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar Footer (Profile) -->
    <div class="sidebar-footer"
        style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 12px;">
        <div
            style="width: 40px; height: 40px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-person" style="font-size: 20px; color: rgba(255,255,255,0.7);"></i>
        </div>
        <div style="flex: 1;">
            <div style="color: #fff; font-size: 13px; font-weight: 500;">
                <?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?>
            </div>
            <div style="color: rgba(255,255,255,0.5); font-size: 11px;">Administrator</div>
        </div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"
            style="color: rgba(255,255,255,0.5); font-size: 18px;" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden;">
            <div class="modal-body text-center" style="padding: 40px 30px;">
                <div
                    style="width: 80px; height: 80px; background: linear-gradient(135deg, #FFE5E5 0%, #FFC9C9 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px;">
                    <i class="bi bi-box-arrow-right" style="font-size: 36px; color: #E74C3C;"></i>
                </div>
                <h4 style="font-weight: 700; color: #333; margin-bottom: 10px;">Confirm Logout</h4>
                <p style="color: #888; font-size: 14px; margin-bottom: 30px;">Are you sure you want to log out of your
                    account?</p>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                        style="background: #f5f5f5; color: #666; padding: 12px 30px; border-radius: 10px; font-weight: 600; border: none;">
                        Cancel
                    </button>
                    <a href="<?= APP_URL ?>/logout.php" class="btn"
                        style="background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%); color: white; padding: 12px 30px; border-radius: 10px; font-weight: 600; text-decoration: none;">
                        <i class="bi bi-box-arrow-right me-2"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>