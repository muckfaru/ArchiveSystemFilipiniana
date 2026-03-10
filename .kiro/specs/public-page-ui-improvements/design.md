# Public Page UI Improvements Bugfix Design

## Overview

This bugfix addresses four distinct UI/UX issues affecting the public-facing pages and admin dashboard of a PHP-based archive system. The issues include: (1) non-responsive navigation on mobile devices due to absolute positioning, (2) misleading dashboard statistics showing page count instead of meaningful analytics, (3) 404 errors when accessing the collections page despite file existence, and (4) excessive padding in the metadata display configuration page. The fix approach involves implementing responsive CSS with a hamburger menu for mobile, updating the dashboard stat card to display total views from the analytics table, resolving routing/controller issues for the collections page, and adjusting CSS spacing for the metadata configuration page.

## Glossary

- **Bug_Condition (C)**: The set of conditions that trigger each of the four UI/UX bugs
- **Property (P)**: The desired correct behavior for each bug condition
- **Preservation**: Existing desktop layouts, other stat cards, routing for other pages, and configuration functionality that must remain unchanged
- **`.public-nav`**: The navigation element in `assets/css/user_pages/public.css` that uses absolute positioning causing mobile layout issues
- **`newspaper_views` table**: The analytics table created in migration `005_create_newspaper_views_table.php` that stores file view counts
- **Stat Card**: Dashboard metric display component showing system statistics
- **Collections Page**: The page at `user_pages/collections.php` that returns 404 errors
- **Metadata Display Configuration**: The admin page at `views/metadata-display.php` with spacing issues

## Bug Details

### Fault Condition

The bugs manifest in four distinct scenarios:

**Bug 1 - Mobile Navigation**: When the public navigation is viewed on mobile devices (screen width < 768px), the `.public-nav` CSS uses `position: absolute; left: 50%; transform: translateX(-50%);` which breaks the layout because the centered horizontal navigation doesn't adapt to smaller screens.

**Bug 2 - Dashboard Statistics**: When the admin dashboard loads, the "Issues Count" stat card displays `$totalIssues` which represents page count rather than meaningful analytics like total file views.

**Bug 3 - Collections Routing**: When a user navigates to `user_pages/collections.php`, the system returns a 404 error despite the file existing, indicating a routing or controller configuration issue.

**Bug 4 - Metadata Display Spacing**: When the metadata display configuration page loads, excessive spacing appears above the "Metadata Display Configuration Configure how..." text due to improper margin/padding settings in the page header.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type { screenWidth: number, page: string, element: string }
  OUTPUT: boolean
  
  RETURN (input.screenWidth < 768 AND input.element == '.public-nav')
         OR (input.page == 'dashboard' AND input.element == 'Issues Count stat card')
         OR (input.page == 'user_pages/collections.php' AND httpStatus == 404)
         OR (input.page == 'metadata-display.php' AND input.element == '.md-page-header')
END FUNCTION
```

### Examples

**Bug 1 - Mobile Navigation:**
- User opens public.php on iPhone (375px width) → Navigation buttons overlap and break layout
- User opens browse.php on tablet (768px width) → Navigation is cramped and not user-friendly
- Expected: Hamburger menu icon appears, clicking it expands/collapses navigation vertically

**Bug 2 - Dashboard Statistics:**
- Admin loads dashboard.php → "Issues Count" shows "1,234" (total pages)
- Expected: "Total Views" shows "5,678" (sum of all views from `newspaper_views` table) with `bi-eye` or `bi-graph-up` icon

**Bug 3 - Collections Routing:**
- User clicks link to collections page → 404 Not Found error
- User directly navigates to `/user_pages/collections.php` → 404 Not Found error
- Expected: Collections page loads successfully showing categorized documents

**Bug 4 - Metadata Display Spacing:**
- Admin opens metadata-display.php → Large gap above "Metadata Display Configuration" text
- Expected: Proper spacing with `margin-top: 0; padding-top: 0;` applied to `.md-page-header` or similar container

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Desktop/laptop navigation (screen width >= 768px) must continue to display the centered horizontal layout with absolute positioning
- Other dashboard stat cards (Total Files, Years Covered, etc.) must continue to show their current metrics without modification
- Navigation to other public pages (public.php, browse.php) must continue to load successfully without routing errors
- Metadata display configuration functionality (save/load settings) must continue to work correctly
- Bootstrap 5.3.2 styling consistency must be maintained across all responsive changes
- The `newspaper_views` table schema must not be modified
- Other admin dashboard features must function without interference from stat card changes

**Scope:**
All inputs that do NOT involve the four specific bug conditions should be completely unaffected by this fix. This includes:
- Desktop navigation display and interactions
- Other dashboard statistics and metrics
- Routing to pages other than collections.php
- Other admin page layouts and spacing
- All existing JavaScript functionality
- Database queries unrelated to the total views calculation

## Hypothesized Root Cause

Based on the bug descriptions, the most likely issues are:

### Bug 1 - Mobile Navigation Root Cause

1. **Absolute Positioning on Mobile**: The `.public-nav` uses `position: absolute; left: 50%; transform: translateX(-50%);` which works for desktop but doesn't adapt to mobile screens
   - The navigation needs a responsive breakpoint at 768px
   - Mobile should use a hamburger menu pattern with collapsible navigation
   - The hamburger icon and menu toggle functionality are missing

2. **Missing Media Query**: No `@media (max-width: 768px)` rule exists to override the absolute positioning for mobile devices

### Bug 2 - Dashboard Statistics Root Cause

1. **Wrong Variable Used**: The dashboard displays `$totalIssues` which likely counts pages/issues rather than meaningful analytics
   - Should query `SUM(view_count)` from `newspaper_views` table
   - The variable name and query need to be updated in dashboard.php

2. **Misleading Label**: The stat card title "Issues Count" doesn't clearly communicate what metric is being displayed
   - Should be renamed to "Total Views" to reflect actual analytics

### Bug 3 - Collections Routing Root Cause

1. **Missing Route Configuration**: The collections.php file exists but may not be registered in the routing system
   - Possible .htaccess rewrite rule issue
   - Possible missing include or require statement in index.php or router

2. **Incorrect Path Reference**: Links to collections page may use incorrect URL format
   - Should use `APP_URL . '/user_pages/collections.php'` format

3. **Missing Controller Logic**: The collections.php file may have PHP errors preventing it from loading
   - Possible missing database connection or authentication check

### Bug 4 - Metadata Display Spacing Root Cause

1. **Excessive Top Margin/Padding**: The `.md-page-header` class or parent container has too much top spacing
   - CSS rule needs `margin-top: 0;` and/or `padding-top: 0;`
   - May be inheriting spacing from parent layout or Bootstrap classes

2. **Layout Container Issue**: The page may be using a container class that adds unwanted top spacing
   - Need to inspect the actual HTML structure in metadata-display.php

## Correctness Properties

Property 1: Fault Condition - Mobile Navigation Responsiveness

_For any_ viewport where the screen width is less than 768px and the user is viewing a public page (public.php or browse.php), the fixed navigation SHALL display a hamburger menu icon that, when clicked, expands/collapses the navigation items vertically, allowing users to access Home and Browse links without layout breakage.

**Validates: Requirements 2.1**

Property 2: Fault Condition - Dashboard Total Views Display

_For any_ dashboard page load, the fixed stat card SHALL display "Total Views" as the title with a `bi-eye` or `bi-graph-up` icon, and SHALL show the sum of all view counts from the `newspaper_views` table as the metric value, providing meaningful analytics instead of page count.

**Validates: Requirements 2.2**

Property 3: Fault Condition - Collections Page Routing

_For any_ navigation attempt to the collections page (via link click or direct URL access to `user_pages/collections.php`), the fixed routing SHALL successfully load the collections page without 404 errors, displaying the categorized documents interface.

**Validates: Requirements 2.3**

Property 4: Fault Condition - Metadata Display Spacing

_For any_ load of the metadata display configuration page, the fixed CSS SHALL display proper spacing above the configuration text by applying `margin-top: 0;` and `padding-top: 0;` to the `.md-page-header` or relevant container, eliminating excessive whitespace.

**Validates: Requirements 2.4**

Property 5: Preservation - Desktop Navigation Layout

_For any_ viewport where the screen width is 768px or greater, the fixed navigation SHALL continue to display the centered horizontal layout using absolute positioning, preserving the existing desktop user experience.

**Validates: Requirements 3.1**

Property 6: Preservation - Other Dashboard Statistics

_For any_ dashboard stat card that is NOT the "Issues Count" / "Total Views" card, the fixed dashboard SHALL continue to display the same metrics and values as before, ensuring no interference with other statistics.

**Validates: Requirements 3.2**

Property 7: Preservation - Other Page Routing

_For any_ navigation to public pages other than collections.php (such as public.php, browse.php), the fixed routing SHALL continue to load successfully without errors, maintaining existing navigation functionality.

**Validates: Requirements 3.3**

Property 8: Preservation - Metadata Configuration Functionality

_For any_ interaction with the metadata display configuration page functionality (saving or loading configuration settings), the fixed page SHALL continue to operate correctly, preserving all existing features beyond the spacing fix.

**Validates: Requirements 3.4, 3.5, 3.6, 3.7**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

#### Bug 1 - Mobile Navigation Fix

**File**: `assets/css/user_pages/public.css`

**Section**: Responsive media query for `.public-nav`

**Specific Changes**:
1. **Add Hamburger Menu HTML**: Insert hamburger button in public.php and browse.php header
   - Add `<button class="public-nav-toggle">` with Bootstrap icon `bi-list`
   - Add `data-bs-toggle` and `data-bs-target` attributes for Bootstrap collapse

2. **Update CSS for Mobile**: Add media query at `@media (max-width: 768px)`
   - Remove `position: absolute; left: 50%; transform: translateX(-50%);` for mobile
   - Change `.public-nav` to `display: none;` by default on mobile
   - Add `.public-nav.show` class to display navigation when hamburger is clicked
   - Style `.public-nav` as vertical flex column with full width
   - Style `.public-nav-toggle` button to display only on mobile

3. **Add JavaScript Toggle**: Add minimal JavaScript to toggle `.show` class
   - Use Bootstrap 5.3.2 collapse component (already available)
   - Or add simple vanilla JS click handler if Bootstrap collapse isn't suitable

4. **Preserve Desktop Layout**: Ensure media query only affects mobile
   - Desktop styles remain unchanged with absolute positioning

#### Bug 2 - Dashboard Statistics Fix

**File**: `views/dashboard.php`

**Section**: Stat card for "Issues Count" (around line 64-71)

**Specific Changes**:
1. **Update Query**: Replace `$totalIssues` calculation with total views query
   - Add query: `SELECT SUM(view_count) FROM newspaper_views`
   - Store result in `$totalViews` variable

2. **Update Stat Card Title**: Change "Issues Count" to "Total Views"
   - Update the `<span class="stat-card-title">` text

3. **Update Icon**: Change `bi-files-alt` to `bi-eye` or `bi-graph-up`
   - Update the `<i>` element class

4. **Update Value Display**: Change `<?= number_format($totalIssues) ?>` to `<?= number_format($totalViews) ?>`

5. **Handle Null Values**: Add null coalescing to handle empty table
   - Use `$totalViews = $result ?? 0;`

#### Bug 3 - Collections Routing Fix

**File**: Depends on routing system (likely `.htaccess` or `index.php`)

**Specific Changes**:
1. **Verify File Permissions**: Ensure `user_pages/collections.php` has correct read permissions
   - Check file exists and is readable by web server

2. **Check .htaccess Rules**: Review rewrite rules that may be blocking the path
   - Ensure `user_pages/` directory is not being rewritten incorrectly
   - Add exception rule if needed: `RewriteCond %{REQUEST_URI} !^/user_pages/`

3. **Verify PHP Includes**: Check that collections.php has correct paths
   - Ensure `require_once __DIR__ . '/../backend/core/auth.php';` resolves correctly
   - Verify database connection is established

4. **Test Direct Access**: Verify collections.php can be accessed directly
   - Navigate to full URL: `http://localhost/archive-system/user_pages/collections.php`
   - Check PHP error logs for any runtime errors

5. **Update Navigation Links**: Ensure links use correct format
   - Use `<?= APP_URL ?>/user_pages/collections.php` in navigation

#### Bug 4 - Metadata Display Spacing Fix

**File**: `assets/css/admin_pages/metadata-display.css` or inline styles in `views/metadata-display.php`

**Section**: `.md-page-header` class (around line 12-14)

**Specific Changes**:
1. **Remove Excessive Top Spacing**: Update `.md-page-header` CSS
   - Add or update: `margin-top: 0;`
   - Add or update: `padding-top: 0;`

2. **Check Parent Container**: Verify no parent element adds unwanted spacing
   - Inspect `.container` or `.content-wrapper` classes
   - Override if necessary with more specific selector

3. **Verify Heading Spacing**: Ensure `.md-page-header h4` doesn't have excessive top margin
   - Current CSS shows `margin-bottom: 4px;` but check for top margin

4. **Test Responsive Layout**: Ensure spacing fix works across all screen sizes
   - Verify no negative impact on mobile or tablet views

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate each bug on unfixed code, then verify the fixes work correctly and preserve existing behavior. Each bug will be tested independently with its own exploratory and fix checking phases.

### Exploratory Fault Condition Checking

**Goal**: Surface counterexamples that demonstrate all four bugs BEFORE implementing the fixes. Confirm or refute the root cause analysis for each bug. If we refute, we will need to re-hypothesize.

#### Bug 1 - Mobile Navigation Exploration

**Test Plan**: Use browser developer tools to simulate mobile viewports and observe navigation layout breakage. Test on actual mobile devices if available.

**Test Cases**:
1. **iPhone SE Test (375px)**: Open public.php in Chrome DevTools with iPhone SE viewport (will fail on unfixed code - navigation breaks layout)
2. **iPad Test (768px)**: Open browse.php in Chrome DevTools with iPad viewport (will fail on unfixed code - navigation is cramped)
3. **Desktop Test (1920px)**: Open public.php in full desktop viewport (should pass - desktop layout works correctly)
4. **Hamburger Menu Test**: Look for hamburger menu icon on mobile (will fail on unfixed code - no hamburger exists)

**Expected Counterexamples**:
- Navigation buttons overlap or extend beyond viewport width
- Absolute positioning causes navigation to be misaligned on mobile
- No hamburger menu icon visible on mobile devices
- Possible causes: missing media query, absolute positioning not overridden, no hamburger HTML/JS

#### Bug 2 - Dashboard Statistics Exploration

**Test Plan**: Load the dashboard and inspect the "Issues Count" stat card value. Compare with actual view counts in the `newspaper_views` table.

**Test Cases**:
1. **Stat Card Value Test**: Load dashboard.php and record "Issues Count" value (will show page count on unfixed code)
2. **Database Query Test**: Run `SELECT SUM(view_count) FROM newspaper_views` directly in phpMyAdmin (will show different value than stat card)
3. **Icon Test**: Inspect the stat card icon class (will show `bi-files-alt` on unfixed code)
4. **Label Test**: Inspect the stat card title text (will show "Issues Count" on unfixed code)

**Expected Counterexamples**:
- "Issues Count" displays page count (e.g., 1,234) instead of total views (e.g., 5,678)
- Icon is `bi-files-alt` instead of `bi-eye` or `bi-graph-up`
- Label says "Issues Count" instead of "Total Views"
- Possible causes: wrong variable used, incorrect SQL query, misleading label

#### Bug 3 - Collections Routing Exploration

**Test Plan**: Attempt to navigate to collections.php through various methods and observe 404 errors. Check server error logs for additional clues.

**Test Cases**:
1. **Direct URL Test**: Navigate to `http://localhost/archive-system/user_pages/collections.php` (will fail with 404 on unfixed code)
2. **Link Click Test**: Click any link pointing to collections page (will fail with 404 on unfixed code)
3. **File Existence Test**: Verify file exists at `user_pages/collections.php` using file explorer (should pass - file exists)
4. **PHP Error Log Test**: Check Apache/PHP error logs for any runtime errors (may reveal additional issues)

**Expected Counterexamples**:
- 404 Not Found error when accessing collections.php
- Possible causes: .htaccess rewrite issue, incorrect path in links, PHP runtime error, missing route configuration

#### Bug 4 - Metadata Display Spacing Exploration

**Test Plan**: Load the metadata display configuration page and measure the spacing above the header text using browser developer tools.

**Test Cases**:
1. **Visual Spacing Test**: Load metadata-display.php and observe excessive whitespace above "Metadata Display Configuration" (will show large gap on unfixed code)
2. **CSS Inspection Test**: Use DevTools to inspect `.md-page-header` computed styles (will show excessive margin-top or padding-top on unfixed code)
3. **Parent Container Test**: Inspect parent elements for inherited spacing (may reveal additional spacing sources)
4. **Comparison Test**: Compare spacing with other admin pages (will show inconsistency on unfixed code)

**Expected Counterexamples**:
- Large gap (e.g., 40-60px) above "Metadata Display Configuration" text
- `.md-page-header` has excessive `margin-top` or `padding-top` value
- Inconsistent spacing compared to other admin pages
- Possible causes: excessive margin/padding in CSS, inherited spacing from parent, Bootstrap class conflict

### Fix Checking

**Goal**: Verify that for all inputs where each bug condition holds, the fixed functions/pages produce the expected behavior.

#### Bug 1 - Mobile Navigation Fix Checking

**Pseudocode:**
```
FOR ALL viewport WHERE viewport.width < 768 DO
  page := loadPublicPage(viewport)
  ASSERT hamburgerMenuExists(page)
  ASSERT navigationIsHidden(page) OR navigationIsCollapsed(page)
  
  hamburgerButton := page.querySelector('.public-nav-toggle')
  hamburgerButton.click()
  
  ASSERT navigationIsVisible(page)
  ASSERT navigationIsVertical(page)
  ASSERT navigationLinksAreAccessible(page)
END FOR
```

**Test Cases**:
1. Test hamburger menu appears on mobile viewports (375px, 414px, 768px)
2. Test hamburger menu toggles navigation visibility when clicked
3. Test navigation displays vertically when expanded
4. Test navigation links are clickable and functional
5. Test navigation collapses when hamburger is clicked again

#### Bug 2 - Dashboard Statistics Fix Checking

**Pseudocode:**
```
FOR ALL dashboardLoad DO
  page := loadDashboard()
  statCard := page.querySelector('.stat-card:contains("Total Views")')
  
  ASSERT statCard.title == "Total Views"
  ASSERT statCard.icon IN ['bi-eye', 'bi-graph-up']
  
  expectedValue := queryDatabase("SELECT SUM(view_count) FROM newspaper_views")
  actualValue := statCard.value
  
  ASSERT actualValue == expectedValue
END FOR
```

**Test Cases**:
1. Test stat card title is "Total Views"
2. Test stat card icon is `bi-eye` or `bi-graph-up`
3. Test stat card value matches `SUM(view_count)` from database
4. Test stat card handles null/empty table gracefully (displays 0)
5. Test stat card updates when new views are recorded

#### Bug 3 - Collections Routing Fix Checking

**Pseudocode:**
```
FOR ALL navigationAttempt WHERE navigationAttempt.target == 'collections.php' DO
  response := navigateToCollections()
  
  ASSERT response.statusCode == 200
  ASSERT response.pageLoaded == true
  ASSERT response.containsCollectionsContent == true
END FOR
```

**Test Cases**:
1. Test direct URL access to collections.php returns 200 status
2. Test link click navigation to collections.php loads successfully
3. Test collections page displays expected content (categories, documents)
4. Test collections page functionality works (filtering, sorting)
5. Test no PHP errors appear in error logs

#### Bug 4 - Metadata Display Spacing Fix Checking

**Pseudocode:**
```
FOR ALL pageLoad WHERE pageLoad.page == 'metadata-display.php' DO
  page := loadMetadataDisplayPage()
  header := page.querySelector('.md-page-header')
  
  computedStyles := getComputedStyle(header)
  
  ASSERT computedStyles.marginTop == '0px' OR computedStyles.marginTop <= '20px'
  ASSERT computedStyles.paddingTop == '0px' OR computedStyles.paddingTop <= '20px'
  ASSERT visualSpacingIsAppropriate(header)
END FOR
```

**Test Cases**:
1. Test `.md-page-header` has `margin-top: 0` or minimal value
2. Test `.md-page-header` has `padding-top: 0` or minimal value
3. Test visual spacing above header text is consistent with other admin pages
4. Test spacing looks appropriate on desktop, tablet, and mobile viewports
5. Test no layout shift or visual regression occurs

### Preservation Checking

**Goal**: Verify that for all inputs where the bug conditions do NOT hold, the fixed pages produce the same result as the original pages.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT fixedPage(input) = originalPage(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

#### Preservation Test Plan

**Test Plan**: Observe behavior on UNFIXED code first for non-bug scenarios, then write property-based tests capturing that behavior.

**Test Cases**:

1. **Desktop Navigation Preservation**: Observe that desktop navigation (>= 768px) displays correctly on unfixed code, then write test to verify this continues after fix
   - Test viewports: 1024px, 1366px, 1920px
   - Verify absolute positioning still works
   - Verify centered horizontal layout is maintained

2. **Other Stat Cards Preservation**: Observe that other dashboard stat cards display correctly on unfixed code, then write test to verify they remain unchanged after fix
   - Test "Total Files" stat card value and display
   - Test "Years Covered" stat card value and display
   - Test any other stat cards present

3. **Other Page Routing Preservation**: Observe that public.php and browse.php load correctly on unfixed code, then write test to verify routing continues after fix
   - Test direct URL access to public.php
   - Test direct URL access to browse.php
   - Test navigation between pages

4. **Metadata Configuration Functionality Preservation**: Observe that save/load configuration works on unfixed code, then write test to verify functionality continues after fix
   - Test saving metadata display configuration
   - Test loading saved configuration
   - Test configuration applies correctly to display

5. **Bootstrap Styling Preservation**: Observe that Bootstrap 5.3.2 styles apply correctly on unfixed code, then write test to verify consistency after fix
   - Test button styles remain consistent
   - Test form styles remain consistent
   - Test grid layout remains consistent

6. **Analytics Infrastructure Preservation**: Observe that view tracking works on unfixed code, then write test to verify it continues after fix
   - Test that views are still recorded in `newspaper_views` table
   - Test that view tracking doesn't break with new query
   - Test that table schema remains unchanged

### Unit Tests

**Bug 1 - Mobile Navigation:**
- Test hamburger button renders on mobile viewports
- Test hamburger button has correct Bootstrap classes and attributes
- Test navigation has correct CSS classes for mobile display
- Test navigation toggle functionality works with click events
- Test navigation displays vertically when expanded
- Test desktop navigation remains unchanged (>= 768px)

**Bug 2 - Dashboard Statistics:**
- Test total views query returns correct sum from database
- Test stat card displays "Total Views" title
- Test stat card uses `bi-eye` or `bi-graph-up` icon
- Test stat card value matches database query result
- Test stat card handles empty table (displays 0)
- Test other stat cards remain unchanged

**Bug 3 - Collections Routing:**
- Test collections.php file exists and is readable
- Test direct URL access returns 200 status code
- Test collections page renders without PHP errors
- Test collections page displays expected content
- Test other page routing remains unchanged

**Bug 4 - Metadata Display Spacing:**
- Test `.md-page-header` has `margin-top: 0` or minimal value
- Test `.md-page-header` has `padding-top: 0` or minimal value
- Test visual spacing is consistent with other admin pages
- Test spacing works across all viewport sizes
- Test other page layouts remain unchanged

### Property-Based Tests

**Bug 1 - Mobile Navigation:**
- Generate random mobile viewport widths (320px - 767px) and verify hamburger menu appears and functions correctly
- Generate random desktop viewport widths (768px - 2560px) and verify horizontal navigation layout is preserved
- Test navigation toggle behavior across many click sequences (open, close, open, close)

**Bug 2 - Dashboard Statistics:**
- Generate random view count data in `newspaper_views` table and verify stat card displays correct sum
- Test stat card display with edge cases (0 views, NULL values, very large numbers)
- Verify other stat cards remain unchanged across many dashboard loads

**Bug 3 - Collections Routing:**
- Test collections page access through various URL formats and query parameters
- Generate random navigation sequences and verify collections page loads successfully
- Verify other page routing works correctly across many navigation attempts

**Bug 4 - Metadata Display Spacing:**
- Test spacing across many viewport sizes (320px - 2560px)
- Generate random page loads and verify consistent spacing
- Test spacing with different browser zoom levels (50% - 200%)

### Integration Tests

**Bug 1 - Mobile Navigation:**
- Test full user flow: load public page on mobile → click hamburger → click Browse link → verify browse page loads
- Test navigation across all public pages on mobile devices
- Test navigation behavior when switching between mobile and desktop viewports (responsive design)

**Bug 2 - Dashboard Statistics:**
- Test full admin flow: login → view dashboard → verify Total Views displays correctly
- Test stat card updates after new file views are recorded
- Test dashboard displays correctly with all stat cards together

**Bug 3 - Collections Routing:**
- Test full user flow: navigate from public page → click collections link → verify collections page loads → filter by category → verify results display
- Test collections page functionality (search, filter, sort, pagination)
- Test navigation between collections and other pages

**Bug 4 - Metadata Display Spacing:**
- Test full admin flow: login → navigate to metadata display configuration → verify spacing is correct → save configuration → verify functionality works
- Test metadata display configuration across all tabs and sections
- Test configuration applies correctly to public page metadata display
