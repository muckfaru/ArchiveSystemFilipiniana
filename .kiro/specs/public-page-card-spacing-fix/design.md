# Public Page Card Spacing Fix - Bugfix Design

## Overview

The file cards on the public page display with excessive spacing (48px gaps) due to Bootstrap's `g-5` class, creating poor visual density and an unprofessional appearance. This fix will reduce the gap to a more balanced 16-24px range by replacing `g-5` with `g-3` or `g-4`, improving the overall layout aesthetics while preserving all existing functionality including responsive behavior, hover effects, modal interactions, and pagination.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when the file grid is rendered with the `g-5` Bootstrap class
- **Property (P)**: The desired behavior - cards should display with reasonable spacing (16-24px) creating a balanced, professional layout
- **Preservation**: All existing functionality must remain unchanged: responsive grid structure, hover effects, modal interactions, pagination, and filtering
- **g-5 class**: Bootstrap utility class that creates 48px (3rem) gaps between grid items
- **g-3 class**: Bootstrap utility class that creates 16px (1rem) gaps between grid items
- **g-4 class**: Bootstrap utility class that creates 24px (1.5rem) gaps between grid items
- **public-file-card**: The card component displaying file information on the public page

## Bug Details

### Fault Condition

The bug manifests when the public page file grid is rendered with Bootstrap's `g-5` class on line 131 of `views/public.php`. This class creates 48px gaps between cards, resulting in excessive whitespace that makes cards appear oversized and reduces visual density.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type HTMLElement (the grid container)
  OUTPUT: boolean
  
  RETURN input.classList.contains('g-5')
         AND input.classList.contains('row')
         AND input.parentElement.classList.contains('public-grid-container')
END FUNCTION
```

### Examples

- **Desktop View (4 columns)**: Cards display with 48px horizontal gaps, creating excessive whitespace that makes the grid feel sparse and unprofessional
- **Mobile View (2 columns)**: Cards display with 48px gaps both horizontally and vertically, consuming excessive screen space and reducing content density
- **With Pagination**: The oversized spacing persists across all pages, affecting the entire browsing experience
- **Edge Case - Single Row**: Even with fewer than 4 cards, the excessive spacing makes the layout appear unbalanced

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Responsive grid structure must continue to display 2 columns on mobile and 4 columns on desktop
- Hover effects (transform and shadow) must continue to work on card hover
- Click interactions must continue to open the file preview modal with all metadata
- Pagination controls must continue to display and function correctly
- Search and category filters must continue to display filtered results correctly

**Scope:**
All functionality that does NOT involve the visual spacing between cards should be completely unaffected by this fix. This includes:
- Card content rendering (thumbnail, title, date)
- Modal functionality and data attributes
- Event handlers for card clicks
- Pagination logic and URL parameters
- Filter and search functionality

## Hypothesized Root Cause

Based on the bug description, the root cause is clear:

1. **Incorrect Bootstrap Gap Class**: The `g-5` class on line 131 of `views/public.php` creates 48px gaps, which is too large for a card grid layout
   - Bootstrap's gap utilities: `g-1` (4px), `g-2` (8px), `g-3` (16px), `g-4` (24px), `g-5` (48px)
   - The current `g-5` value is appropriate for form layouts but excessive for card grids

2. **No Custom CSS Override**: There is no custom CSS in `assets/css/user_pages/public.css` that overrides the Bootstrap gap value

3. **Design Intent Mismatch**: The original implementation likely chose `g-5` for generous spacing but didn't account for the visual density requirements of a card grid

## Correctness Properties

Property 1: Fault Condition - Reduced Card Spacing

_For any_ file grid rendering where the bug condition holds (g-5 class is present), the fixed code SHALL display cards with reasonable spacing (16-24px) that creates a balanced, professional layout with appropriate visual density.

**Validates: Requirements 2.1, 2.2**

Property 2: Preservation - Existing Functionality

_For any_ user interaction or page behavior that does NOT involve the visual spacing between cards, the fixed code SHALL produce exactly the same behavior as the original code, preserving responsive grid structure, hover effects, modal interactions, pagination, and filtering functionality.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

The fix is straightforward and requires a single change:

**File**: `views/public.php`

**Line**: 131

**Specific Changes**:
1. **Replace Bootstrap Gap Class**: Change `g-5` to `g-3` or `g-4`
   - `g-3` provides 16px gaps (more compact, higher density)
   - `g-4` provides 24px gaps (balanced, professional)
   - Recommendation: Start with `g-4` for a balanced approach

**Before**:
```php
<div class="row g-5">
```

**After**:
```php
<div class="row g-4">
```

**Rationale**: Bootstrap's `g-4` class (24px gap) provides a balanced spacing that maintains visual breathing room while significantly improving density compared to the current 48px gaps. This is a standard spacing value for card grids in modern web design.

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, visually confirm the bug exists with excessive spacing on unfixed code, then verify the fix reduces spacing appropriately while preserving all existing functionality.

### Exploratory Fault Condition Checking

**Goal**: Visually confirm the bug BEFORE implementing the fix. Measure the actual gap size and assess visual density issues.

**Test Plan**: Open the public page in a browser, inspect the grid container, and measure the gap between cards using browser developer tools. Observe the visual density on both desktop and mobile viewports.

**Test Cases**:
1. **Desktop View Test**: Load public page on desktop (>768px width), measure horizontal gaps between cards (should show 48px on unfixed code)
2. **Mobile View Test**: Load public page on mobile (<768px width), measure gaps between cards (should show 48px on unfixed code)
3. **Multiple Rows Test**: Scroll through paginated results to observe vertical gaps between rows (should show 48px on unfixed code)
4. **Browser DevTools Inspection**: Use computed styles to confirm `gap: 3rem` (48px) is applied to the `.row.g-5` element

**Expected Counterexamples**:
- Gap measurement shows 48px (3rem) between cards
- Visual assessment confirms excessive whitespace and poor density
- Cards appear oversized relative to the viewport

### Fix Checking

**Goal**: Verify that after changing to `g-4`, the cards display with 24px gaps creating a balanced, professional layout.

**Pseudocode:**
```
FOR ALL viewport sizes (mobile, tablet, desktop) DO
  result := measureCardGap(publicPageGrid)
  ASSERT result == 24px (1.5rem)
  ASSERT visualDensity(publicPageGrid) == "balanced"
END FOR
```

**Test Plan**: After applying the fix, reload the public page and measure gaps using browser developer tools. Visually assess the improved density and professional appearance.

**Test Cases**:
1. **Desktop Gap Measurement**: Verify horizontal gaps are 24px
2. **Mobile Gap Measurement**: Verify gaps are 24px on mobile
3. **Visual Density Assessment**: Confirm cards appear balanced without excessive whitespace
4. **Cross-Browser Check**: Test in Chrome, Firefox, and Safari to ensure consistent rendering

### Preservation Checking

**Goal**: Verify that all existing functionality continues to work exactly as before the fix.

**Pseudocode:**
```
FOR ALL user interactions (hover, click, pagination, filtering) DO
  ASSERT behavior_after_fix == behavior_before_fix
END FOR
```

**Testing Approach**: Manual testing is sufficient for this fix since we're only changing a CSS class value. The change cannot affect JavaScript functionality, event handlers, or data processing.

**Test Plan**: Systematically test all interactive features on the public page after applying the fix.

**Test Cases**:
1. **Responsive Grid Preservation**: Verify grid displays 2 columns on mobile (<768px) and 4 columns on desktop (≥768px)
2. **Hover Effects Preservation**: Hover over cards and verify transform and shadow effects still work
3. **Modal Interaction Preservation**: Click on cards and verify modal opens with correct metadata
4. **Pagination Preservation**: Navigate between pages and verify pagination controls work correctly
5. **Filter Preservation**: Apply category filters and search queries, verify results display correctly with new spacing

### Unit Tests

- Visual regression test comparing before/after screenshots of the card grid
- Gap measurement test using browser automation (Playwright/Puppeteer)
- Responsive breakpoint test verifying column counts at different viewport sizes

### Property-Based Tests

Not applicable for this CSS-only fix. Property-based testing is more suitable for logic and data transformation bugs.

### Integration Tests

- Full user flow test: Load public page → Search for documents → Click card → Verify modal → Navigate pagination
- Cross-browser compatibility test: Verify consistent spacing in Chrome, Firefox, Safari, and Edge
- Mobile device test: Verify spacing on actual mobile devices (iOS Safari, Chrome Android)
