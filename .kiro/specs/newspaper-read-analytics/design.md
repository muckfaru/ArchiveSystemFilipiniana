# Design Document: Newspaper Read Analytics

## Overview

The newspaper read analytics feature tracks unique readership for newspaper files in the existing PHP-based archive system. The system records view events when users open newspaper files, using session-based deduplication to prevent counting page refreshes as separate reads. Administrators can view aggregated statistics (daily, weekly, monthly, yearly) and optionally see a "Top 10 Most Read" report.

### Design Goals

1. **Non-intrusive Integration**: Extend existing functionality without modifying core archive features
2. **Session-based Deduplication**: Use PHP sessions to prevent duplicate view counting within the same browsing session
3. **Performance**: Complete view recording within 100ms; use indexed queries for analytics
4. **Privacy-conscious**: Store only IP addresses (no user accounts required for public users)
5. **Admin-only Display**: Show analytics only to authenticated administrators

### Key Design Decisions

- **Session Storage**: Use PHP's native session mechanism with keys like `viewed_newspaper_{id}` to track viewed newspapers per session
- **IP Address Storage**: VARCHAR(45) to support both IPv4 and IPv6 addresses
- **Database Indexing**: Composite index on (newspaper_id, view_date) for efficient time-based queries
- **Integration Point**: Hook into reader.php pages (both admin and user versions) to record views
- **Display Location**: Add analytics section to the admin reader page's info panel

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Existing System                           │
│  ┌──────────────┐         ┌──────────────┐                 │
│  │ user_pages/  │         │ admin_pages/ │                 │
│  │ reader.php   │         │ reader.php   │                 │
│  └──────┬───────┘         └──────┬───────┘                 │
│         │                        │                          │
│         └────────┬───────────────┘                          │
│                  │                                           │
│                  ▼                                           │
│         ┌────────────────┐                                  │
│         │  View Tracker  │ ◄─── New Component               │
│         │  (functions)   │                                  │
│         └────────┬───────┘                                  │
│                  │                                           │
│                  ▼                                           │
│         ┌────────────────┐                                  │
│         │  newspaper_    │ ◄─── New Table                   │
│         │  views         │                                  │
│         └────────────────┘                                  │
│                                                              │
│         ┌────────────────┐                                  │
│         │  Analytics     │ ◄─── New Component               │
│         │  Query Layer   │                                  │
│         └────────┬───────┘                                  │
│                  │                                           │
│                  ▼                                           │
│         ┌────────────────┐                                  │
│         │  Admin Info    │ ◄─── Modified Display            │
│         │  Panel         │                                  │
│         └────────────────┘                                  │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **View Recording Flow**:
   ```
   User opens newspaper → reader.php loads → Check session for viewed_{id}
   → If not viewed: Record to newspaper_views + Set session flag
   → If already viewed: Skip recording
   → Continue normal page rendering
   ```

2. **Analytics Display Flow**:
   ```
   Admin views newspaper → reader.php loads → Check if user is admin
   → If admin: Query analytics for this newspaper_id
   → Display stats in info panel
   → If not admin: Skip analytics display
   ```

## Components and Interfaces

### 1. Database Migration Component

**File**: `backend/migrations/005_create_newspaper_views_table.php`

**Purpose**: Create the newspaper_views table with proper indexing

**Functions**:
- `runMigration($pdo)`: Creates newspaper_views table
- `rollbackMigration($pdo)`: Drops newspaper_views table

**Migration SQL**:
```sql
CREATE TABLE newspaper_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    newspaper_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    view_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_newspaper_date (newspaper_id, view_date),
    INDEX idx_view_date (view_date),
    FOREIGN KEY (newspaper_id) REFERENCES newspapers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. View Tracking Component

**File**: `backend/core/analytics.php`

**Purpose**: Record newspaper views with session deduplication

**Functions**:

```php
/**
 * Record a newspaper view if not already viewed in this session
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID being viewed
 * @return bool True if view was recorded, false if already viewed in session
 */
function recordNewspaperView($pdo, $newspaperId): bool

/**
 * Check if a newspaper has been viewed in the current session
 * 
 * @param int $newspaperId The newspaper ID to check
 * @return bool True if already viewed in this session
 */
function hasViewedInSession($newspaperId): bool

/**
 * Mark a newspaper as viewed in the current session
 * 
 * @param int $newspaperId The newspaper ID to mark
 * @return void
 */
function markViewedInSession($newspaperId): void
```

**Implementation Details**:
- Start session if not already started
- Use session key format: `viewed_newspaper_{id}`
- Get IP address from `$_SERVER['REMOTE_ADDR']`
- Use prepared statements for SQL injection protection
- Complete execution within 100ms

### 3. Analytics Query Component

**File**: `backend/core/analytics.php`

**Purpose**: Retrieve aggregated view statistics

**Functions**:

```php
/**
 * Get view statistics for a newspaper across all time periods
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return array Associative array with keys: daily, weekly, monthly, yearly
 */
function getNewspaperAnalytics($pdo, $newspaperId): array

/**
 * Get daily unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses today
 */
function getDailyViews($pdo, $newspaperId): int

/**
 * Get weekly unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses this week
 */
function getWeeklyViews($pdo, $newspaperId): int

/**
 * Get monthly unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses this month
 */
function getMonthlyViews($pdo, $newspaperId): int

/**
 * Get yearly unique view count for a newspaper
 * 
 * @param PDO $pdo Database connection
 * @param int $newspaperId The newspaper ID
 * @return int Count of unique IP addresses this year
 */
function getYearlyViews($pdo, $newspaperId): int

/**
 * Get top 10 most read newspapers (optional feature)
 * 
 * @param PDO $pdo Database connection
 * @return array Array of newspapers with id, title, and view_count
 */
function getTopReadNewspapers($pdo): array
```

**Query Patterns**:

- **Daily**: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND DATE(view_date) = CURDATE()`
- **Weekly**: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND YEARWEEK(view_date, 1) = YEARWEEK(CURDATE(), 1)`
- **Monthly**: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND MONTH(view_date) = MONTH(CURDATE()) AND YEAR(view_date) = YEAR(CURDATE())`
- **Yearly**: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND YEAR(view_date) = YEAR(CURDATE())`

### 4. Display Integration Component

**Files Modified**:
- `admin_pages/reader.php` (add analytics display to info panel)
- `user_pages/reader.php` (add view tracking only, no display)

**Integration Points**:

1. **View Tracking** (both admin and user reader pages):
   - Add after file fetch, before rendering
   - Call `recordNewspaperView($pdo, $fileId)`

2. **Analytics Display** (admin reader page only):
   - Add to info panel after custom metadata section
   - Only display if `$currentUser` is set (admin authenticated)
   - Format: "Reading Analytics - Today: X, This Week: Y, This Month: Z, This Year: W"

**Display HTML Structure**:
```php
<?php if (isset($currentUser)): ?>
    <?php
    require_once __DIR__ . '/../backend/core/analytics.php';
    $analytics = getNewspaperAnalytics($pdo, $fileId);
    ?>
    <div class="info-row" style="border-top: 2px solid rgba(128, 128, 128, 0.2); padding-top: 16px; margin-top: 16px;">
        <span class="info-label" style="font-weight: 700; color: var(--accent);">
            <i class="bi bi-graph-up me-1"></i>Reading Analytics
        </span>
    </div>
    <div class="info-row">
        <span class="info-label">Today</span>
        <span class="info-val"><?= $analytics['daily'] ?> reads</span>
    </div>
    <div class="info-row">
        <span class="info-label">This Week</span>
        <span class="info-val"><?= $analytics['weekly'] ?> reads</span>
    </div>
    <div class="info-row">
        <span class="info-label">This Month</span>
        <span class="info-val"><?= $analytics['monthly'] ?> reads</span>
    </div>
    <div class="info-row">
        <span class="info-label">This Year</span>
        <span class="info-val"><?= $analytics['yearly'] ?> reads</span>
    </div>
<?php endif; ?>
```

## Data Models

### newspaper_views Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier for each view record |
| newspaper_id | INT | NOT NULL, FOREIGN KEY → newspapers(id) | Reference to the newspaper being viewed |
| ip_address | VARCHAR(45) | NOT NULL | IP address of the viewer (IPv4 or IPv6) |
| view_date | DATETIME | DEFAULT CURRENT_TIMESTAMP | Timestamp when the view occurred |

**Indexes**:
- PRIMARY KEY on `id`
- INDEX `idx_newspaper_date` on `(newspaper_id, view_date)` - for time-based analytics queries
- INDEX `idx_view_date` on `(view_date)` - for global analytics and cleanup
- FOREIGN KEY on `newspaper_id` with CASCADE DELETE

**Storage Estimates**:
- Row size: ~60 bytes (4 + 4 + 45 + 8 = 61 bytes + overhead)
- 1 million views ≈ 60 MB
- Expected growth: ~1000-5000 views/day depending on traffic

### Session Data Structure

**Session Keys**:
- Format: `viewed_newspaper_{newspaper_id}`
- Value: `true` (boolean)
- Lifetime: Until session expires (default PHP session timeout)

**Example**:
```php
$_SESSION['viewed_newspaper_42'] = true;
$_SESSION['viewed_newspaper_108'] = true;
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property 1: Session Initialization on First View

*For any* HTTP request without an existing PHP session, when a newspaper file is opened, a PHP session should be started.

**Validates: Requirements 2.1**

### Property 2: View Recording for New Sessions

*For any* newspaper and any session that has not previously viewed that newspaper, opening the newspaper should result in a new view record being inserted into the newspaper_views table with the correct newspaper_id, IP address, and timestamp.

**Validates: Requirements 2.3**

### Property 3: Session-Based Deduplication

*For any* newspaper and any session, if the same newspaper is opened multiple times within the same session, only one view record should be created in the database for that session.

**Validates: Requirements 2.5**

### Property 4: Daily View Count Accuracy

*For any* newspaper and any set of view records, the daily view count should equal the number of distinct IP addresses that viewed the newspaper where DATE(view_date) equals CURDATE().

**Validates: Requirements 3.1**

### Property 5: Weekly View Count Accuracy

*For any* newspaper and any set of view records, the weekly view count should equal the number of distinct IP addresses that viewed the newspaper where YEARWEEK(view_date, 1) equals YEARWEEK(CURDATE(), 1).

**Validates: Requirements 4.1**

### Property 6: Monthly View Count Accuracy

*For any* newspaper and any set of view records, the monthly view count should equal the number of distinct IP addresses that viewed the newspaper where MONTH(view_date) equals MONTH(CURDATE()) AND YEAR(view_date) equals YEAR(CURDATE()).

**Validates: Requirements 5.1**

### Property 7: Yearly View Count Accuracy

*For any* newspaper and any set of view records, the yearly view count should equal the number of distinct IP addresses that viewed the newspaper where YEAR(view_date) equals YEAR(CURDATE()).

**Validates: Requirements 6.1**

### Property 8: Analytics Display Completeness

*For any* newspaper viewed by an authenticated admin user, the analytics display should show all four time period statistics: daily, weekly, monthly, and yearly.

**Validates: Requirements 7.1**

### Property 9: Analytics Display Labels

*For any* analytics display, the rendered output should contain the labels "Today", "This Week", "This Month", and "This Year" corresponding to their respective statistics.

**Validates: Requirements 3.2, 4.2, 5.2, 6.2**

### Property 10: Admin-Only Analytics Display

*For any* user viewing a newspaper file, analytics statistics should be displayed if and only if the user is authenticated as an admin (has a valid session with admin credentials).

**Validates: Requirements 7.3**

### Property 11: Top Newspapers Aggregation

*For any* set of view records, when the top newspapers feature is enabled, the aggregation should correctly count the total number of distinct IP addresses for each newspaper_id.

**Validates: Requirements 8.1**

### Property 12: Top Newspapers Ordering and Limiting

*For any* result set from the top newspapers query, the results should be ordered by unique reader count in descending order and limited to exactly 10 newspapers.

**Validates: Requirements 8.2**

### Property 13: Query Index Usage

*For all* analytics queries (daily, weekly, monthly, yearly), the database query execution plan should utilize the idx_newspaper_date index on the newspaper_views table.

**Validates: Requirements 9.1**

### Property 14: View Recording Performance

*For any* newspaper view recording operation, the execution time from function call to database commit should complete within 100 milliseconds.

**Validates: Requirements 9.3**

## Error Handling

### Database Errors

**Connection Failures**:
- If database connection fails during view recording, log error but continue page rendering
- User experience should not be degraded by analytics failures
- Use try-catch blocks around all database operations

**Query Failures**:
- If INSERT fails during view recording, log error silently
- If SELECT fails during analytics display, show "—" or "N/A" instead of error message
- Never expose database errors to end users

**Example Error Handling**:
```php
function recordNewspaperView($pdo, $newspaperId): bool {
    try {
        // Check session first (no DB involved)
        if (hasViewedInSession($newspaperId)) {
            return false;
        }
        
        // Record view
        $stmt = $pdo->prepare("
            INSERT INTO newspaper_views (newspaper_id, ip_address, view_date)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$newspaperId, $_SERVER['REMOTE_ADDR']]);
        
        // Mark in session
        markViewedInSession($newspaperId);
        return true;
        
    } catch (PDOException $e) {
        // Log error but don't break page rendering
        error_log("Analytics view recording failed: " . $e->getMessage());
        return false;
    }
}
```

### Session Errors

**Session Start Failures**:
- If `session_start()` fails, skip view recording
- Check `session_status()` before attempting to start session
- Handle cases where sessions are disabled in PHP configuration

**Session Data Corruption**:
- If session data is corrupted, treat as new session
- Use `isset()` checks before accessing session variables

### Input Validation

**Newspaper ID Validation**:
- Validate that newspaper_id is a positive integer
- Reject non-numeric or negative values
- Use `intval()` and range checks

**IP Address Validation**:
- Validate IP address format before storage
- Use `filter_var($ip, FILTER_VALIDATE_IP)` for validation
- Handle cases where REMOTE_ADDR is not set (CLI, proxies)
- Truncate to 45 characters if necessary

**Example Validation**:
```php
function recordNewspaperView($pdo, $newspaperId): bool {
    // Validate newspaper ID
    $newspaperId = intval($newspaperId);
    if ($newspaperId <= 0) {
        error_log("Invalid newspaper ID: $newspaperId");
        return false;
    }
    
    // Validate IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        error_log("Invalid IP address: $ipAddress");
        $ipAddress = '0.0.0.0'; // Fallback
    }
    
    // ... rest of function
}
```

### Edge Cases

**Missing IP Address**:
- If `$_SERVER['REMOTE_ADDR']` is not set, use '0.0.0.0' as fallback
- Log warning when fallback is used

**Concurrent Requests**:
- Database handles race conditions via UNIQUE constraints (if added)
- Session locking prevents concurrent session writes
- Duplicate inserts are acceptable (will be deduplicated by DISTINCT in queries)

**Time Zone Issues**:
- Use MySQL's NOW() function for consistency with database timezone
- Document that all times are in database server timezone
- Consider adding timezone configuration if needed

## Testing Strategy

### Dual Testing Approach

The testing strategy employs both unit tests and property-based tests to ensure comprehensive coverage:

- **Unit Tests**: Verify specific examples, edge cases, and error conditions
- **Property Tests**: Verify universal properties across randomized inputs
- Both approaches are complementary and necessary for complete validation

### Unit Testing

Unit tests focus on specific scenarios and edge cases:

**View Recording Tests**:
- Test view recording with valid newspaper ID
- Test view recording with invalid newspaper ID (negative, zero, non-numeric)
- Test view recording without IP address
- Test view recording with IPv4 address
- Test view recording with IPv6 address
- Test session deduplication (same newspaper, same session)
- Test multiple newspapers in same session
- Test view recording across different sessions

**Analytics Query Tests**:
- Test daily count with views from today
- Test daily count with views from yesterday (should be 0)
- Test weekly count with views from this week
- Test monthly count with views from this month
- Test yearly count with views from this year
- Test counts with no views (should return 0)
- Test counts with multiple views from same IP (should count as 1)
- Test counts with multiple views from different IPs

**Display Integration Tests**:
- Test analytics display for admin users
- Test analytics display is hidden for public users
- Test analytics display with zero views
- Test analytics display with large numbers (formatting)

**Error Handling Tests**:
- Test behavior when database connection fails
- Test behavior when session cannot be started
- Test behavior with corrupted session data
- Test behavior with invalid IP addresses

### Property-Based Testing

Property-based tests verify universal properties using randomized inputs. The system will use **PHPUnit with a property-based testing extension** (such as Eris or php-quickcheck).

**Configuration**:
- Minimum 100 iterations per property test
- Each test tagged with: `@Feature newspaper-read-analytics, Property {number}: {property_text}`

**Property Test Examples**:

```php
/**
 * @Feature newspaper-read-analytics, Property 3: Session-Based Deduplication
 * For any newspaper and any session, if the same newspaper is opened multiple 
 * times within the same session, only one view record should be created.
 */
public function testSessionBasedDeduplication() {
    $this->forAll(
        Generator\int(1, 1000), // Random newspaper ID
        Generator\int(2, 10)    // Random number of views (2-10)
    )->then(function($newspaperId, $viewCount) {
        // Setup: Clear views and start fresh session
        $this->clearViewsForNewspaper($newspaperId);
        $this->startNewSession();
        
        // Action: View the same newspaper multiple times
        for ($i = 0; $i < $viewCount; $i++) {
            recordNewspaperView($this->pdo, $newspaperId);
        }
        
        // Assert: Only one view record should exist
        $count = $this->getViewCountForNewspaper($newspaperId);
        $this->assertEquals(1, $count, 
            "Expected 1 view record after $viewCount views in same session");
    });
}

/**
 * @Feature newspaper-read-analytics, Property 4: Daily View Count Accuracy
 * For any newspaper and any set of view records, the daily view count should 
 * equal the number of distinct IP addresses that viewed it today.
 */
public function testDailyViewCountAccuracy() {
    $this->forAll(
        Generator\int(1, 1000),           // Random newspaper ID
        Generator\seq(Generator\ipv4())   // Random list of IP addresses
    )->then(function($newspaperId, $ipAddresses) {
        // Setup: Clear views and insert test data
        $this->clearViewsForNewspaper($newspaperId);
        
        // Insert views with random IPs (today)
        foreach ($ipAddresses as $ip) {
            $this->insertView($newspaperId, $ip, date('Y-m-d H:i:s'));
        }
        
        // Action: Get daily count
        $dailyCount = getDailyViews($this->pdo, $newspaperId);
        
        // Assert: Should equal distinct IP count
        $expectedCount = count(array_unique($ipAddresses));
        $this->assertEquals($expectedCount, $dailyCount,
            "Daily count should match distinct IP count");
    });
}

/**
 * @Feature newspaper-read-analytics, Property 10: Admin-Only Analytics Display
 * For any user viewing a newspaper, analytics should be displayed if and only 
 * if the user is authenticated as an admin.
 */
public function testAdminOnlyAnalyticsDisplay() {
    $this->forAll(
        Generator\int(1, 1000),  // Random newspaper ID
        Generator\bool()         // Random admin status
    )->then(function($newspaperId, $isAdmin) {
        // Setup: Create user session
        if ($isAdmin) {
            $this->loginAsAdmin();
        } else {
            $this->clearSession(); // Public user
        }
        
        // Action: Render reader page
        $html = $this->renderReaderPage($newspaperId);
        
        // Assert: Analytics should appear only for admin
        $hasAnalytics = strpos($html, 'Reading Analytics') !== false;
        $this->assertEquals($isAdmin, $hasAnalytics,
            "Analytics display should match admin status");
    });
}
```

**Property Test Coverage**:
- Property 1: Session initialization (100 iterations)
- Property 2: View recording for new sessions (100 iterations)
- Property 3: Session-based deduplication (100 iterations)
- Property 4: Daily view count accuracy (100 iterations)
- Property 5: Weekly view count accuracy (100 iterations)
- Property 6: Monthly view count accuracy (100 iterations)
- Property 7: Yearly view count accuracy (100 iterations)
- Property 8: Analytics display completeness (100 iterations)
- Property 9: Analytics display labels (100 iterations)
- Property 10: Admin-only analytics display (100 iterations)
- Property 11: Top newspapers aggregation (100 iterations)
- Property 12: Top newspapers ordering (100 iterations)
- Property 13: Query index usage (100 iterations)
- Property 14: View recording performance (100 iterations)

### Integration Testing

**End-to-End Scenarios**:
1. Public user opens newspaper → view recorded → refresh page → no duplicate view
2. Admin user opens newspaper → view recorded → analytics displayed
3. Multiple users view same newspaper → distinct counts correct
4. View newspaper → wait 1 day → daily count resets, weekly/monthly/yearly persist

### Performance Testing

**Load Testing**:
- Simulate 100 concurrent view recordings
- Verify all complete within 100ms
- Check database connection pool handling

**Query Performance**:
- Run EXPLAIN on all analytics queries
- Verify index usage (idx_newspaper_date)
- Measure query execution time with 1M+ view records

### Manual Testing Checklist

- [ ] Run migration successfully
- [ ] Verify table schema matches specification
- [ ] Record view as public user
- [ ] Verify session deduplication works
- [ ] View analytics as admin user
- [ ] Verify analytics hidden for public users
- [ ] Test with IPv4 and IPv6 addresses
- [ ] Test across different time periods
- [ ] Verify top 10 report (if enabled)
- [ ] Test error handling (disconnect database, corrupt session)

## Implementation Notes

### Migration Execution

Run the migration using:
```bash
php backend/migrations/005_create_newspaper_views_table.php
```

Or for rollback:
```bash
php backend/migrations/005_create_newspaper_views_table.php down
```

### Integration Steps

1. **Create Migration**: Implement `005_create_newspaper_views_table.php`
2. **Create Analytics Functions**: Implement `backend/core/analytics.php`
3. **Integrate View Tracking**: Modify both reader.php files to call `recordNewspaperView()`
4. **Integrate Analytics Display**: Modify `admin_pages/reader.php` to display analytics
5. **Test**: Run unit tests and property tests
6. **Deploy**: Run migration on production database

### Performance Considerations

**Database Optimization**:
- The composite index `(newspaper_id, view_date)` enables efficient time-based queries
- Consider partitioning by date if table grows beyond 10M rows
- Implement data retention policy (e.g., archive views older than 2 years)

**Caching Strategy** (Optional Future Enhancement):
- Cache analytics results for 5-15 minutes using Redis or Memcached
- Invalidate cache on new view recording
- Reduces database load for popular newspapers

**Session Performance**:
- PHP sessions are file-based by default (fast for low traffic)
- Consider Redis session handler for high-traffic deployments
- Session checks are in-memory (no database hit)

### Security Considerations

**SQL Injection Prevention**:
- All queries use prepared statements with parameterized inputs
- Never concatenate user input into SQL strings

**Session Security**:
- Use secure session configuration (httponly, secure flags)
- Regenerate session ID on admin login
- Session data contains only boolean flags (minimal attack surface)

**Privacy Considerations**:
- IP addresses are personal data under GDPR/privacy laws
- Consider implementing IP anonymization (e.g., mask last octet)
- Document data retention policy
- Provide admin interface to purge old analytics data

**Access Control**:
- Analytics display requires admin authentication
- View recording works for all users (public and admin)
- No sensitive data exposed in analytics display

### Maintenance and Monitoring

**Database Maintenance**:
- Monitor table size growth
- Implement periodic cleanup of old view records
- Optimize indexes if query performance degrades

**Monitoring Metrics**:
- View recording success rate
- Average view recording time
- Analytics query performance
- Session deduplication effectiveness

**Logging**:
- Log view recording failures (database errors)
- Log invalid input attempts
- Log performance issues (>100ms recording time)

### Future Enhancements

**Potential Features** (not in current scope):
- Geographic analytics (country/city from IP)
- Time-of-day analytics (peak reading hours)
- Reading duration tracking
- Export analytics to CSV/PDF
- Analytics dashboard with charts
- Comparison between newspapers
- Trending newspapers (views increasing)
- User agent tracking (device types)

## Appendix

### SQL Query Reference

**Daily Views**:
```sql
SELECT COUNT(DISTINCT ip_address) 
FROM newspaper_views 
WHERE newspaper_id = ? 
  AND DATE(view_date) = CURDATE()
```

**Weekly Views**:
```sql
SELECT COUNT(DISTINCT ip_address) 
FROM newspaper_views 
WHERE newspaper_id = ? 
  AND YEARWEEK(view_date, 1) = YEARWEEK(CURDATE(), 1)
```

**Monthly Views**:
```sql
SELECT COUNT(DISTINCT ip_address) 
FROM newspaper_views 
WHERE newspaper_id = ? 
  AND MONTH(view_date) = MONTH(CURDATE()) 
  AND YEAR(view_date) = YEAR(CURDATE())
```

**Yearly Views**:
```sql
SELECT COUNT(DISTINCT ip_address) 
FROM newspaper_views 
WHERE newspaper_id = ? 
  AND YEAR(view_date) = YEAR(CURDATE())
```

**Top 10 Most Read**:
```sql
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
```

### File Structure

```
backend/
├── core/
│   └── analytics.php          (NEW - view tracking and analytics functions)
├── migrations/
│   └── 005_create_newspaper_views_table.php  (NEW - database migration)
admin_pages/
├── reader.php                 (MODIFIED - add view tracking and analytics display)
user_pages/
├── reader.php                 (MODIFIED - add view tracking only)
```

### Dependencies

- PHP 7.4+ (for typed properties and arrow functions)
- MySQL 5.7+ or MariaDB 10.2+ (for JSON functions if needed)
- PDO extension (already required by existing system)
- Session support (already enabled in existing system)

### Compatibility

- Compatible with existing XAMPP setup
- No new external dependencies required
- Works with existing authentication system
- Does not conflict with custom metadata system
- Does not conflict with form templates system
