# Implementation Plan: Newspaper Read Analytics

## Overview

This implementation plan breaks down the newspaper read analytics feature into discrete coding tasks. The feature tracks unique readership for newspaper files using session-based deduplication and provides administrators with aggregated statistics across multiple time periods (daily, weekly, monthly, yearly).

## Tasks

- [x] 1. Create database migration for newspaper_views table
  - Create file `backend/migrations/005_create_newspaper_views_table.php`
  - Implement `runMigration($pdo)` function to create newspaper_views table with columns: id, newspaper_id, ip_address, view_date
  - Add composite index on (newspaper_id, view_date) for query performance
  - Add index on view_date for global analytics
  - Add foreign key constraint on newspaper_id referencing newspapers(id) with CASCADE DELETE
  - Implement `rollbackMigration($pdo)` function to drop the table
  - _Requirements: 1.1, 1.2, 1.3_

- [ ]* 1.1 Write property test for database migration
  - **Property 1: Session Initialization on First View**
  - **Validates: Requirements 2.1**
  - Verify that accessing a newspaper without an existing session starts a new PHP session

- [ ] 2. Implement view tracking functions in analytics.php
  - [x] 2.1 Create backend/core/analytics.php file with session management functions
    - Implement `hasViewedInSession($newspaperId)` to check if newspaper viewed in current session
    - Implement `markViewedInSession($newspaperId)` to mark newspaper as viewed using session key format `viewed_newspaper_{id}`
    - Ensure session is started if not already active using `session_status()` check
    - _Requirements: 2.1, 2.2, 2.4_

  - [x] 2.2 Implement recordNewspaperView function with deduplication
    - Implement `recordNewspaperView($pdo, $newspaperId)` function
    - Validate newspaper_id is positive integer
    - Check session using `hasViewedInSession()` before recording
    - Get IP address from `$_SERVER['REMOTE_ADDR']` with fallback to '0.0.0.0'
    - Validate IP address using `filter_var($ip, FILTER_VALIDATE_IP)`
    - Insert view record with prepared statement if not already viewed in session
    - Call `markViewedInSession()` after successful insert
    - Add try-catch for PDOException with error logging
    - Return bool indicating if view was recorded
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 9.3_

  - [ ]* 2.3 Write property test for view recording
    - **Property 2: View Recording for New Sessions**
    - **Validates: Requirements 2.3**
    - Verify that opening a newspaper in a new session creates exactly one view record with correct newspaper_id, IP address, and timestamp

  - [ ]* 2.4 Write property test for session deduplication
    - **Property 3: Session-Based Deduplication**
    - **Validates: Requirements 2.5**
    - Verify that opening the same newspaper multiple times in the same session creates only one view record

- [ ] 3. Checkpoint - Verify view tracking works
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Implement analytics query functions
  - [x] 4.1 Implement getDailyViews function
    - Implement `getDailyViews($pdo, $newspaperId)` function
    - Use query: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND DATE(view_date) = CURDATE()`
    - Use prepared statement with newspaper_id parameter
    - Return integer count, default to 0 on error
    - Add try-catch for PDOException with error logging
    - _Requirements: 3.1_

  - [ ]* 4.2 Write property test for daily view count
    - **Property 4: Daily View Count Accuracy**
    - **Validates: Requirements 3.1**
    - Verify that daily count equals the number of distinct IP addresses that viewed the newspaper today

  - [x] 4.3 Implement getWeeklyViews function
    - Implement `getWeeklyViews($pdo, $newspaperId)` function
    - Use query: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND YEARWEEK(view_date, 1) = YEARWEEK(CURDATE(), 1)`
    - Use prepared statement with newspaper_id parameter
    - Return integer count, default to 0 on error
    - Add try-catch for PDOException with error logging
    - _Requirements: 4.1_

  - [ ]* 4.4 Write property test for weekly view count
    - **Property 5: Weekly View Count Accuracy**
    - **Validates: Requirements 4.1**
    - Verify that weekly count equals the number of distinct IP addresses that viewed the newspaper this week

  - [x] 4.5 Implement getMonthlyViews function
    - Implement `getMonthlyViews($pdo, $newspaperId)` function
    - Use query: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND MONTH(view_date) = MONTH(CURDATE()) AND YEAR(view_date) = YEAR(CURDATE())`
    - Use prepared statement with newspaper_id parameter
    - Return integer count, default to 0 on error
    - Add try-catch for PDOException with error logging
    - _Requirements: 5.1_

  - [ ]* 4.6 Write property test for monthly view count
    - **Property 6: Monthly View Count Accuracy**
    - **Validates: Requirements 5.1**
    - Verify that monthly count equals the number of distinct IP addresses that viewed the newspaper this month

  - [x] 4.7 Implement getYearlyViews function
    - Implement `getYearlyViews($pdo, $newspaperId)` function
    - Use query: `COUNT(DISTINCT ip_address) WHERE newspaper_id = ? AND YEAR(view_date) = YEAR(CURDATE())`
    - Use prepared statement with newspaper_id parameter
    - Return integer count, default to 0 on error
    - Add try-catch for PDOException with error logging
    - _Requirements: 6.1_

  - [ ]* 4.8 Write property test for yearly view count
    - **Property 7: Yearly View Count Accuracy**
    - **Validates: Requirements 6.1**
    - Verify that yearly count equals the number of distinct IP addresses that viewed the newspaper this year

  - [x] 4.9 Implement getNewspaperAnalytics aggregation function
    - Implement `getNewspaperAnalytics($pdo, $newspaperId)` function
    - Call getDailyViews, getWeeklyViews, getMonthlyViews, getYearlyViews
    - Return associative array with keys: 'daily', 'weekly', 'monthly', 'yearly'
    - _Requirements: 7.1_

  - [ ]* 4.10 Write unit tests for analytics query functions
    - Test each time period function with views from the correct period
    - Test each function with views from outside the period (should return 0)
    - Test with no views (should return 0)
    - Test with multiple views from same IP (should count as 1)
    - Test with multiple views from different IPs
    - _Requirements: 3.1, 4.1, 5.1, 6.1_

- [ ] 5. Checkpoint - Verify analytics queries work correctly
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 6. Integrate view tracking into reader pages
  - [x] 6.1 Add view tracking to user_pages/reader.php
    - Add `require_once __DIR__ . '/../backend/core/analytics.php';` at the top
    - Call `recordNewspaperView($pdo, $fileId)` after file fetch, before rendering
    - Ensure view tracking does not break page rendering if it fails
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 9.2_

  - [x] 6.2 Add view tracking to admin_pages/reader.php
    - Add `require_once __DIR__ . '/../backend/core/analytics.php';` at the top
    - Call `recordNewspaperView($pdo, $fileId)` after file fetch, before rendering
    - Ensure view tracking does not break page rendering if it fails
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 9.2_

- [ ] 7. Implement analytics display in admin reader page
  - [x] 7.1 Add analytics display section to admin_pages/reader.php
    - Add analytics display code in the info panel section
    - Wrap display in `<?php if (isset($currentUser)): ?>` check to show only for admin users
    - Call `getNewspaperAnalytics($pdo, $fileId)` to fetch statistics
    - Display "Reading Analytics" header with graph icon
    - Display four info rows: "Today", "This Week", "This Month", "This Year" with corresponding counts
    - Format counts as "[count] reads"
    - Add visual separator (border-top) before analytics section
    - _Requirements: 7.1, 7.2, 7.3, 3.2, 4.2, 5.2, 6.2_

  - [ ]* 7.2 Write property test for analytics display completeness
    - **Property 8: Analytics Display Completeness**
    - **Validates: Requirements 7.1**
    - Verify that analytics display shows all four time period statistics for admin users

  - [ ]* 7.3 Write property test for analytics display labels
    - **Property 9: Analytics Display Labels**
    - **Validates: Requirements 3.2, 4.2, 5.2, 6.2**
    - Verify that rendered output contains labels "Today", "This Week", "This Month", and "This Year"

  - [ ]* 7.4 Write property test for admin-only display
    - **Property 10: Admin-Only Analytics Display**
    - **Validates: Requirements 7.3**
    - Verify that analytics are displayed if and only if user is authenticated as admin

- [ ] 8. Implement optional top newspapers report function
  - [x] 8.1 Implement getTopReadNewspapers function
    - Implement `getTopReadNewspapers($pdo)` function in backend/core/analytics.php
    - Use query joining newspapers and newspaper_views tables
    - Count distinct IP addresses grouped by newspaper_id
    - Filter out deleted newspapers (WHERE deleted_at IS NULL)
    - Order by view_count DESC and limit to 10 results
    - Return array of newspapers with id, title, and view_count
    - Add try-catch for PDOException with error logging
    - _Requirements: 8.1, 8.2, 8.3_

  - [ ]* 8.2 Write property test for top newspapers aggregation
    - **Property 11: Top Newspapers Aggregation**
    - **Validates: Requirements 8.1**
    - Verify that aggregation correctly counts total distinct IP addresses for each newspaper

  - [ ]* 8.3 Write property test for top newspapers ordering
    - **Property 12: Top Newspapers Ordering and Limiting**
    - **Validates: Requirements 8.2**
    - Verify that results are ordered by unique reader count descending and limited to 10

- [ ] 9. Performance validation and optimization
  - [ ]* 9.1 Write property test for query index usage
    - **Property 13: Query Index Usage**
    - **Validates: Requirements 9.1**
    - Use EXPLAIN to verify that analytics queries utilize idx_newspaper_date index

  - [ ]* 9.2 Write property test for view recording performance
    - **Property 14: View Recording Performance**
    - **Validates: Requirements 9.3**
    - Verify that view recording completes within 100 milliseconds

- [ ] 10. Final checkpoint - Integration testing
  - Run database migration on test database
  - Test complete flow: public user views newspaper → view recorded → refresh → no duplicate
  - Test admin flow: admin views newspaper → view recorded → analytics displayed
  - Test analytics display hidden for public users
  - Test with IPv4 and IPv6 addresses
  - Verify all time period counts are accurate
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties across randomized inputs
- Unit tests validate specific examples and edge cases
- The migration must be run before testing other components
- View tracking is integrated into both admin and user reader pages but analytics display is admin-only
- All database operations use prepared statements for SQL injection prevention
- Error handling ensures analytics failures don't break page rendering
