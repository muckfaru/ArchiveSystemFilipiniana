<?php
/**
 * Newspaper Read Analytics Functions
 * 
 * This file contains functions for tracking newspaper views and retrieving
 * analytics data. It implements session-based deduplication to prevent
 * counting multiple page refreshes as separate views.
 */

/**
 * Check if a newspaper has been viewed in the current session
 * 
 * @param int $newspaperId The newspaper ID to check
 * @return bool True if already viewed in this session
 */
function hasViewedInSession($newspaperId): bool {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
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
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session key for this newspaper
    $sessionKey = "viewed_newspaper_{$newspaperId}";
    $_SESSION[$sessionKey] = true;
}

/**
 * Record a newspaper view if not already viewed in this session
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID being viewed
 * @return bool True if view was recorded, false if already viewed in session
 */
function recordNewspaperView($pdo, $newspaperId): bool {
    try {
        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID: $newspaperId");
            return false;
        }
        
        // Check session using hasViewedInSession() before recording
        if (hasViewedInSession($newspaperId)) {
            return false;
        }
        
        // Get IP address from $_SERVER['REMOTE_ADDR'] with fallback to '0.0.0.0'
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Validate IP address using filter_var
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            error_log("Invalid IP address: $ipAddress");
            $ipAddress = '0.0.0.0'; // Fallback to default
        }
        
        // Insert view record with prepared statement if not already viewed in session
        $stmt = $pdo->prepare("
            INSERT INTO newspaper_views (newspaper_id, ip_address, view_date)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$newspaperId, $ipAddress]);
        
        // Call markViewedInSession() after successful insert
        markViewedInSession($newspaperId);
        
        return true;
        
    } catch (PDOException $e) {
        // Add try-catch for PDOException with error logging
        error_log("Analytics view recording failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get daily unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses today
 */
function getDailyViews($pdo, $newspaperId): int {
    try {
        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getDailyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) as count
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
 * Get weekly unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses this week
 */
function getWeeklyViews($pdo, $newspaperId): int {
    try {
        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getWeeklyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) as count
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
 * Get monthly unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses this month
 */
function getMonthlyViews($pdo, $newspaperId): int {
    try {
        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getMonthlyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) as count
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
 * Get yearly unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses this year
 */
function getYearlyViews($pdo, $newspaperId): int {
    try {
        // Validate newspaper ID is positive integer
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) {
            error_log("Invalid newspaper ID for getYearlyViews: $newspaperId");
            return 0;
        }
        
        // Use prepared statement with newspaper_id parameter
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) as count
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
function getTopReadNewspapers($pdo): array {
    try {
        // Use query joining newspapers and newspaper_views tables
        // Count distinct IP addresses grouped by newspaper_id
        // Filter out deleted newspapers (WHERE deleted_at IS NULL)
        // Order by view_count DESC and limit to 10 results
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.title,
                COUNT(DISTINCT v.ip_address) as view_count
            FROM newspapers n
            INNER JOIN newspaper_views v ON n.id = v.newspaper_id
            WHERE n.deleted_at IS NULL
            GROUP BY n.id, n.title
            ORDER BY view_count DESC
            LIMIT 10
        ");
        $stmt->execute();
        
        // Return array of newspapers with id, title, and view_count
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Add try-catch for PDOException with error logging
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
        $newspaperId = intval($newspaperId);
        if ($newspaperId <= 0) return 0;

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as cnt FROM newspaper_views WHERE newspaper_id = ?");
        $stmt->execute([$newspaperId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? intval($row['cnt']) : 0;
    } catch (PDOException $e) {
        error_log("Analytics getTotalViews failed: " . $e->getMessage());
        return 0;
    }
}
