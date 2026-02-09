<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header" style="text-align: center; padding: 30px 20px;">
        <img src="<?= APP_URL ?>/assets/images/sidebarlogo.png" alt="QCPL Logo"
            style="width: 70px; height: 70px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.15); margin: 0 auto 15px; display: block; object-fit: cover;">
        <h2 style="color: #fff; font-size: 14px; font-weight: 600; letter-spacing: 1px; margin: 0 0 5px;">QUEZON CITY
            PUBLIC LIBRARY</h2>
        <span style="color: rgba(255,255,255,0.5); font-size: 11px; letter-spacing: 1px;">ADMIN PANEL</span>
    </div>

    <nav class="sidebar-nav" style="padding: 0 15px; flex: 1;">
        <!-- General Section -->
        <div class="nav-section" style="margin-bottom: 25px;">
            <span class="nav-section-title"
                style="font-size: 10px; color: rgba(255,255,255,0.4); letter-spacing: 1px; padding: 0 10px; margin-bottom: 10px; display: block;">GENERAL</span>
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
                style="font-size: 10px; color: rgba(255,255,255,0.4); letter-spacing: 1px; padding: 0 10px; margin-bottom: 10px; display: block;">ARCHIVE
                MANAGEMENT</span>
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
            style="width: 40px; height: 40px; background: #C08B5C; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 600; color: white; font-size: 14px;">
            <?php
            $initials = 'SA';
            if (!empty($currentUser['full_name'])) {
                $nameParts = explode(' ', $currentUser['full_name']);
                $initials = strtoupper(substr($nameParts[0], 0, 1));
                if (count($nameParts) > 1) {
                    $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
                }
            }
            echo $initials;
            ?>
        </div>
        <div style="flex: 1;">
            <div style="color: #fff; font-size: 13px; font-weight: 500;">
                <?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?></div>
            <div style="color: rgba(255,255,255,0.5); font-size: 11px;">Administrator</div>
        </div>
        <a href="<?= APP_URL ?>/logout.php" style="color: rgba(255,255,255,0.5); font-size: 18px;" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>