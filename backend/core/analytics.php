<?php
/**
 * Newspaper Read Analytics Functions
 * 
 * This file contains functions for tracking newspaper views and retrieving
 * analytics data. It implements session-based deduplication to prevent
 * counting multiple page refreshes as separate views.
 */

/**
 * Ensure the analytics storage table exists.
 *
 * Some deployments import an older database snapshot and never run migrations.
 * This keeps report/reader analytics working without a manual repair step.
 *
 * @param PDO $pdo Database connection
 * @return bool
 */
function ensureNewspaperViewsTable($pdo): bool {
    static $ensured = false;

    if ($ensured) {
        return true;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS newspaper_views (
                id INT PRIMARY KEY AUTO_INCREMENT,
                newspaper_id INT NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                view_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_newspaper_date (newspaper_id, view_date),
                INDEX idx_view_date (view_date),
                CONSTRAINT fk_newspaper_views_newspaper
                    FOREIGN KEY (newspaper_id) REFERENCES newspapers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ensured = true;
        return true;
    } catch (PDOException $e) {
        error_log("Analytics table ensure failed: " . $e->getMessage());
        return false;
    }
}

function isMissingNewspaperViewsTableError(PDOException $e): bool {
    $errorInfo = $e->errorInfo ?? [];
    $sqlState = $errorInfo[0] ?? $e->getCode();
    $driverCode = $errorInfo[1] ?? null;

    return $sqlState === '42S02' || (int) $driverCode === 1146;
}

function ensureAnalyticsSessionAvailable(): bool {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    if (headers_sent()) {
        return false;
    }

    @session_start();
    return session_status() === PHP_SESSION_ACTIVE;
}

/**
 * Check if a newspaper has been viewed in the current session
 * 
 * @param int $newspaperId The newspaper ID to check
 * @return bool True if already viewed in this session
 */
function hasViewedInSession($newspaperId): bool {
    if (!ensureAnalyticsSessionAvailable()) {
        return false;
    }
    
    // Check if session key exists for this newspaper
    $sessionKey = "viewed_newspaper_{$newspaperId}";
    return isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] === true;
}

/**
 * Mark a newspaper as viewed in the current session
 * 
 * @param int $newspaperId The newspaper ID to mark
 * @return void
 */
function markViewedInSession($newspaperId): void {
    if (!ensureAnalyticsSessionAvailable()) {
        return;
    }
    
    // Set session key for this newspaper
    $sessionKey = "viewed_newspaper_{$newspaperId}";
    $_SESSION[$sessionKey] = true;
}

function insertNewspaperViewRecord($pdo, int $newspaperId, string $ipAddress): bool {
    $insertView = function () use ($pdo, $newspaperId, $ipAddress): void {
        $stmt = $pdo->prepare("
            INSERT INTO newspaper_views (newspaper_id, ip_address, view_date)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$newspaperId, $ipAddress]);
    };

    try {
        $insertView();
        return true;
    } catch (PDOException $e) {
        if (!isMissingNewspaperViewsTableError($e)) {
            error_log("Analytics view recording failed: " . $e->getMessage());
            return false;
        }
    }

    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return false;
        }

        $insertView();
        return true;
    } catch (PDOException $e) {
        error_log("Analytics view recording failed after table ensure: " . $e->getMessage());
        return false;
    }
}

/**
 * Queue view recording after the response has been sent so the reader opens
 * without waiting on the analytics write.
 *
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID being viewed
 * @return void
 */
function recordNewspaperViewDeferred($pdo, $newspaperId): void {
    // Only record views for public users (not logged-in admins)
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        return;
    }

    $newspaperId = intval($newspaperId);
    if ($newspaperId <= 0) {
        return;
    }

    if (hasViewedInSession($newspaperId)) {
        return;
    }

    markViewedInSession($newspaperId);

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        error_log("Invalid IP address: $ipAddress");
        $ipAddress = '0.0.0.0';
    }

    register_shutdown_function(function () use ($pdo, $newspaperId, $ipAddress) {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        if (session_status() === PHP_SESSION_ACTIVE && function_exists('session_write_close')) {
            @session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        insertNewspaperViewRecord($pdo, $newspaperId, $ipAddress);
    });
}

/**
 * Record a newspaper view if not already viewed in this session
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID being viewed
 * @return bool True if view was recorded, false if already viewed in session
 */
function recordNewspaperView($pdo, $newspaperId): bool {
    // Only record views for public users (not logged-in admins)
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        return false;
    }

    // Validate newspaper ID is positive integer
    $newspaperId = intval($newspaperId);
    if ($newspaperId <= 0) {
        error_log("Invalid newspaper ID: $newspaperId");
        return false;
    }

    if (hasViewedInSession($newspaperId)) {
        return false;
    }
    
    // Get IP address from $_SERVER['REMOTE_ADDR'] with fallback to '0.0.0.0'
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Validate IP address using filter_var
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        error_log("Invalid IP address: $ipAddress");
        $ipAddress = '0.0.0.0';
    }

    if (insertNewspaperViewRecord($pdo, $newspaperId, $ipAddress)) {
        markViewedInSession($newspaperId);
        return true;
    }

    return false;
}

/**
 * Get daily view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of all views today
 */
function getDailyViews($pdo, $newspaperId): int {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return 0;
        }

        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getDailyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM newspaper_views
            WHERE newspaper_id = ? AND DATE(view_date) = CURDATE()
        ");
        $stmt->execute([$newspaperId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return integer count, default to 0 on error
        return $result ? intval($result['count']) : 0;
        
    } catch (PDOException $e) {
        // Add try-catch for PDOException with error logging
        error_log("Analytics getDailyViews failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get weekly view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of all views this week
 */
function getWeeklyViews($pdo, $newspaperId): int {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return 0;
        }

        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getWeeklyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM newspaper_views
            WHERE newspaper_id = ? AND YEARWEEK(view_date, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $stmt->execute([$newspaperId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return integer count, default to 0 on error
        return $result ? intval($result['count']) : 0;
        
    } catch (PDOException $e) {
        // Add try-catch for PDOException with error logging
        error_log("Analytics getWeeklyViews failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get monthly view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of all views this month
 */
function getMonthlyViews($pdo, $newspaperId): int {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return 0;
        }

        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getMonthlyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM newspaper_views
            WHERE newspaper_id = ? 
              AND MONTH(view_date) = MONTH(CURDATE()) 
              AND YEAR(view_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$newspaperId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return integer count, default to 0 on error
        return $result ? intval($result['count']) : 0;
        
    } catch (PDOException $e) {
        // Add try-catch for PDOException with error logging
        error_log("Analytics getMonthlyViews failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get yearly view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of all views this year
 */
function getYearlyViews($pdo, $newspaperId): int {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return 0;
        }

        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getYearlyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM newspaper_views
            WHERE newspaper_id = ? AND YEAR(view_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$newspaperId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return integer count, default to 0 on error
        return $result ? intval($result['count']) : 0;
        
    } catch (PDOException $e) {
        // Add try-catch for PDOException with error logging
        error_log("Analytics getYearlyViews failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get view statistics for a newspaper across all time periods
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return array Associative array with keys: daily, weekly, monthly, yearly
 */
function getNewspaperAnalytics($pdo, $newspaperId): array {
    return [
        'daily' => getDailyViews($pdo, $newspaperId),
        'weekly' => getWeeklyViews($pdo, $newspaperId),
        'monthly' => getMonthlyViews($pdo, $newspaperId),
        'yearly' => getYearlyViews($pdo, $newspaperId)
    ];
}

/**
 * Get top 10 most read newspapers (optional feature)
 * 
 * @param PDO $pdo Database connection
 * @return array Array of newspapers with id, title, and view_count
 */
function getTopReadNewspapers($pdo, $period = 'all', $uploadedBy = null): array {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return [];
        }

        $whereClause = "n.deleted_at IS NULL";
        $params = [];

        if ($uploadedBy !== null) {
            $whereClause .= " AND n.uploaded_by = ?";
            $params[] = intval($uploadedBy);
        }

        // Add time-based filtering
        switch ($period) {
            case 'today':
                $whereClause .= " AND DATE(v.view_date) = CURDATE()";
                break;
            case 'week':
                $whereClause .= " AND v.view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $whereClause .= " AND v.view_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $whereClause .= " AND v.view_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            // 'all' doesn't need additional filtering
        }

        // Count all views GROUP BY newspaper_id
        $stmt = $pdo->prepare("
            SELECT 
                n.*,
                COUNT(v.id) as view_count
            FROM newspapers n
            LEFT JOIN newspaper_views v ON n.id = v.newspaper_id
            WHERE $whereClause
            GROUP BY n.id
            ORDER BY view_count DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $newspapers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch custom metadata for these newspapers in the rich format needed by the frontend
        if (!empty($newspapers)) {
            $fileIds = array_column($newspapers, 'id');
            // Ranking list uses 'card' context metadata for summary/category
            $customMetadata = getFilesMetadataForDisplay($pdo, $fileIds, 'card');

            foreach ($newspapers as &$newspaper) {
                $newspaper['custom_metadata'] = $customMetadata[$newspaper['id']] ?? [];
            }
        }
        
        return $newspapers;
        
    } catch (PDOException $e) {
        error_log("Analytics getTopReadNewspapers failed: " . $e->getMessage());
        return [];
    }
}


/**
 * Get total unique views for a newspaper across all time
 *
 * @param PDO $pdo
 * @param int $newspaperId
 * @return int
 */
function getTotalViews($pdo, $newspaperId): int {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return 0;
        }

        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) return 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM newspaper_views WHERE newspaper_id = ?");
        $stmt->execute([$newspaperId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? intval($row['cnt']) : 0;
    } catch (PDOException $e) {
        error_log("Analytics getTotalViews failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total views across multiple newspapers, optionally scoped by uploader.
 *
 * @param PDO $pdo
 * @param int|null $uploadedBy
 * @return int
 */
function getAggregateViews($pdo, $uploadedBy = null): int {
    try {
        if (!ensureNewspaperViewsTable($pdo)) {
            return 0;
        }

        if ($uploadedBy !== null) {
            $stmt = $pdo->prepare("
                SELECT COUNT(v.id) as cnt
                FROM newspaper_views v
                INNER JOIN newspapers n ON n.id = v.newspaper_id
                WHERE n.deleted_at IS NULL AND n.uploaded_by = ?
            ");
            $stmt->execute([intval($uploadedBy)]);
        } else {
            $stmt = $pdo->query("
                SELECT COUNT(v.id) as cnt
                FROM newspaper_views v
                INNER JOIN newspapers n ON n.id = v.newspaper_id
                WHERE n.deleted_at IS NULL
            ");
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? intval($row['cnt']) : 0;
    } catch (PDOException $e) {
        error_log("Analytics getAggregateViews failed: " . $e->getMessage());
        return 0;
    }
}
