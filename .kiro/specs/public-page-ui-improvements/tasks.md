# Implementation Plan

## Bug 1: Mobile Navigation Responsiveness

- [ ] 1. Write bug condition exploration test for mobile navigation
  - **Property 1: Fault Condition** - Mobile Navigation Layout Breakage
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate mobile navigation breaks on small screens
  - **Scoped PBT Approach**: Test viewport widths < 768px (375px iPhone SE, 414px iPhone, 768px iPad)
  - Test that hamburger menu icon exists on mobile viewports (will fail on unfixed code - no hamburger exists)
  - Test that navigation is responsive and doesn't break layout on mobile (will fail on unfixed code - absolute positioning breaks layout)
  - Test that navigation items are accessible on mobile (will fail on unfixed code - items overlap or extend beyond viewport)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: navigation overlaps, no hamburger menu, absolute positioning issues
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1_

- [ ] 2. Write preservation property tests for mobile navigation (BEFORE implementing fix)
  - **Property 2: Preservation** - Desktop Navigation Layout
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for desktop viewports (>= 768px)
  - Observe: Desktop navigation displays centered horizontal layout with absolute positioning
  - Observe: Navigation items (Home, Browse) are properly spaced and accessible
  - Write property-based test: for all viewport widths >= 768px, navigation displays horizontal centered layout
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1_

- [-] 3. Fix mobile navigation responsiveness

  - [x] 3.1 Add hamburger menu HTML to public pages
    - Add hamburger button to `views/layouts/public-header.php` or inline in public.php/browse.php
    - Use Bootstrap icon `bi-list` for hamburger icon
    - Add `data-bs-toggle="collapse"` and `data-bs-target="#publicNav"` attributes
    - Add `id="publicNav"` to `.public-nav` element for Bootstrap collapse
    - Style button to display only on mobile (< 768px)
    - _Bug_Condition: input.screenWidth < 768 AND input.element == '.public-nav'_
    - _Expected_Behavior: Hamburger menu appears and navigation is collapsible on mobile_
    - _Preservation: Desktop navigation layout remains unchanged (>= 768px)_
    - _Requirements: 2.1, 3.1_

  - [x] 3.2 Update CSS for mobile responsiveness
    - Edit `assets/css/user_pages/public.css`
    - Add media query `@media (max-width: 767px)`
    - Override absolute positioning for mobile: remove `position: absolute; left: 50%; transform: translateX(-50%);`
    - Change `.public-nav` to `display: none;` by default on mobile
    - Add `.public-nav.show` or use Bootstrap collapse classes for visibility
    - Style navigation as vertical flex column with full width on mobile
    - Style `.public-nav-toggle` button (hamburger) to display only on mobile
    - Ensure desktop styles (>= 768px) remain unchanged
    - _Bug_Condition: input.screenWidth < 768 AND input.element == '.public-nav'_
    - _Expected_Behavior: Navigation displays vertically when expanded, hidden by default_
    - _Preservation: Desktop absolute positioning and centered layout preserved_
    - _Requirements: 2.1, 3.1_

  - [ ] 3.3 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Mobile Navigation Responsiveness
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify hamburger menu exists on mobile viewports
    - Verify navigation is responsive and accessible on mobile
    - _Requirements: 2.1_

  - [ ] 3.4 Verify preservation tests still pass
    - **Property 2: Preservation** - Desktop Navigation Layout
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm desktop navigation still displays centered horizontal layout
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.1_

## Bug 2: Dashboard Statistics Display

- [ ] 4. Write bug condition exploration test for dashboard statistics
  - **Property 1: Fault Condition** - Dashboard Shows Page Count Instead of Views
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate stat card shows wrong metric
  - **Scoped PBT Approach**: Test dashboard loads and compare stat card value with database query
  - Test that stat card title is "Total Views" (will fail on unfixed code - shows "Issues Count")
  - Test that stat card icon is `bi-eye` or `bi-graph-up` (will fail on unfixed code - shows `bi-files-alt`)
  - Test that stat card value matches `SUM(view_count)` from `newspaper_views` table (will fail on unfixed code - shows page count)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: wrong title, wrong icon, wrong value
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.2_

- [ ] 5. Write preservation property tests for dashboard statistics (BEFORE implementing fix)
  - **Property 2: Preservation** - Other Dashboard Stat Cards
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for other stat cards (Total Files, Years Covered, etc.)
  - Observe: Other stat cards display their current metrics correctly
  - Observe: Other stat cards have correct titles and icons
  - Write property-based test: for all stat cards except "Issues Count", values and display remain unchanged
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.2_

- [-] 6. Fix dashboard statistics display

  - [x] 6.1 Update dashboard stat card query and display
    - Edit `views/dashboard.php`
    - Replace `$totalIssues` query with total views query: `SELECT SUM(view_count) FROM newspaper_views`
    - Store result in `$totalViews` variable
    - Add null coalescing: `$totalViews = $result ?? 0;`
    - Update stat card title from "Issues Count" to "Total Views"
    - Update icon from `bi-files-alt` to `bi-eye` or `bi-graph-up`
    - Update value display from `<?= number_format($totalIssues) ?>` to `<?= number_format($totalViews) ?>`
    - _Bug_Condition: input.page == 'dashboard' AND input.element == 'Issues Count stat card'_
    - _Expected_Behavior: Stat card displays "Total Views" with sum from newspaper_views table_
    - _Preservation: Other stat cards remain unchanged_
    - _Requirements: 2.2, 3.2_

  - [ ] 6.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Dashboard Total Views Display
    - **IMPORTANT**: Re-run the SAME test from task 4 - do NOT write a new test
    - The test from task 4 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 4
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify stat card shows "Total Views" title
    - Verify stat card shows correct icon
    - Verify stat card value matches database sum
    - _Requirements: 2.2_

  - [ ] 6.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Other Dashboard Stat Cards
    - **IMPORTANT**: Re-run the SAME tests from task 5 - do NOT write new tests
    - Run preservation property tests from step 5
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm other stat cards still display correct values
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.2_

## Bug 3: Collections Page Routing

- [ ] 7. Write bug condition exploration test for collections routing
  - **Property 1: Fault Condition** - Collections Page Returns 404 Error
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate collections page is inaccessible
  - **Scoped PBT Approach**: Test direct URL access and link navigation to collections.php
  - Test that direct URL access to `user_pages/collections.php` returns 200 status (will fail on unfixed code - returns 404)
  - Test that collections page loads without PHP errors (will fail on unfixed code - 404 error)
  - Test that collections page displays expected content (will fail on unfixed code - no content due to 404)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: 404 error, page not accessible
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.3_

- [ ] 8. Write preservation property tests for collections routing (BEFORE implementing fix)
  - **Property 2: Preservation** - Other Page Routing
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for other public pages (public.php, browse.php)
  - Observe: Other pages load successfully with 200 status
  - Observe: Navigation between other pages works correctly
  - Write property-based test: for all pages except collections.php, routing works correctly
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.3_

- [-] 9. Fix collections page routing

  - [x] 9.1 Diagnose and fix routing issue
    - Verify `user_pages/collections.php` file exists and has correct permissions
    - Check `.htaccess` rewrite rules for conflicts
    - Add exception rule if needed: `RewriteCond %{REQUEST_URI} !^/user_pages/`
    - Verify PHP includes in collections.php resolve correctly
    - Check for PHP runtime errors in error logs
    - Update navigation links to use correct format: `<?= APP_URL ?>/user_pages/collections.php`
    - Test direct URL access returns 200 status
    - _Bug_Condition: input.page == 'user_pages/collections.php' AND httpStatus == 404_
    - _Expected_Behavior: Collections page loads successfully with 200 status_
    - _Preservation: Other page routing remains unchanged_
    - _Requirements: 2.3, 3.3_

  - [ ] 9.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Collections Page Routing
    - **IMPORTANT**: Re-run the SAME test from task 7 - do NOT write a new test
    - The test from task 7 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 7
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify collections page returns 200 status
    - Verify collections page loads without errors
    - Verify collections page displays expected content
    - _Requirements: 2.3_

  - [ ] 9.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Other Page Routing
    - **IMPORTANT**: Re-run the SAME tests from task 8 - do NOT write new tests
    - Run preservation property tests from step 8
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm other pages still load successfully
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.3_

## Bug 4: Metadata Display Spacing

- [ ] 10. Write bug condition exploration test for metadata display spacing
  - **Property 1: Fault Condition** - Excessive Spacing Above Configuration Text
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate excessive spacing exists
  - **Scoped PBT Approach**: Test metadata-display.php page load and measure spacing
  - Test that `.md-page-header` has `margin-top: 0` or minimal value (will fail on unfixed code - excessive margin)
  - Test that `.md-page-header` has `padding-top: 0` or minimal value (will fail on unfixed code - excessive padding)
  - Test that visual spacing is consistent with other admin pages (will fail on unfixed code - inconsistent)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: excessive margin/padding values, visual inconsistency
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.4_

- [ ] 11. Write preservation property tests for metadata display spacing (BEFORE implementing fix)
  - **Property 2: Preservation** - Metadata Configuration Functionality
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for metadata configuration functionality
  - Observe: Save configuration works correctly
  - Observe: Load configuration works correctly
  - Observe: Configuration applies to display correctly
  - Write property-based test: for all configuration operations, functionality remains unchanged
  - Verify test passes on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.4, 3.5, 3.6, 3.7_

- [ ] 12. Fix metadata display spacing

  - [ ] 12.1 Update CSS to remove excessive spacing
    - Edit `assets/css/admin_pages/metadata-display.css`
    - Update `.md-page-header` class: add `margin-top: 0;`
    - Update `.md-page-header` class: add `padding-top: 0;`
    - Check parent container classes for inherited spacing
    - Override if necessary with more specific selector
    - Verify `.md-page-header h4` doesn't have excessive top margin
    - Test spacing across all viewport sizes (desktop, tablet, mobile)
    - _Bug_Condition: input.page == 'metadata-display.php' AND input.element == '.md-page-header'_
    - _Expected_Behavior: Proper spacing with margin-top: 0 and padding-top: 0_
    - _Preservation: Metadata configuration functionality remains unchanged_
    - _Requirements: 2.4, 3.4, 3.5, 3.6, 3.7_

  - [ ] 12.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Metadata Display Spacing
    - **IMPORTANT**: Re-run the SAME test from task 10 - do NOT write a new test
    - The test from task 10 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 10
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify `.md-page-header` has minimal margin-top
    - Verify `.md-page-header` has minimal padding-top
    - Verify visual spacing is consistent with other admin pages
    - _Requirements: 2.4_

  - [ ] 12.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Metadata Configuration Functionality
    - **IMPORTANT**: Re-run the SAME tests from task 11 - do NOT write new tests
    - Run preservation property tests from step 11
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm configuration save/load still works
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.4, 3.5, 3.6, 3.7_

## Final Checkpoint

- [ ] 13. Checkpoint - Ensure all tests pass
  - Run all exploration tests (tasks 1, 4, 7, 10) - all should PASS after fixes
  - Run all preservation tests (tasks 2, 5, 8, 11) - all should still PASS
  - Verify all four bugs are fixed:
    - Mobile navigation is responsive with hamburger menu
    - Dashboard displays "Total Views" with correct analytics
    - Collections page loads without 404 errors
    - Metadata display page has proper spacing
  - Verify no regressions in existing functionality
  - Ask the user if questions arise or if additional testing is needed
