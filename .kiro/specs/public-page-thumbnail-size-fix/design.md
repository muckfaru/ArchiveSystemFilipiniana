# Public Page Thumbnail Size Fix Design

## Overview

The thumbnails on the public.php landing page are displaying at an oversized scale due to missing height constraints in the CSS. The `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` classes currently define `aspect-ratio: 3/4` but lack explicit height limitations, allowing thumbnails to grow beyond reasonable dimensions. This fix will add a `max-height` constraint to limit thumbnail size while preserving the 3/4 aspect ratio and ensuring other views (browse list view, compact view) remain unaffected.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when thumbnails are rendered on the public.php landing page without height constraints
- **Property (P)**: The desired behavior - thumbnails should be constrained to a reasonable maximum height while maintaining the 3/4 aspect ratio
- **Preservation**: Existing thumbnail behavior in other views (browse list view, compact view) and hover effects that must remain unchanged by the fix
- **`.public-file-thumbnail`**: The CSS class in `assets/css/user_pages/public.css` that styles actual thumbnail images on the public landing page
- **`.public-file-thumbnail-placeholder`**: The CSS class in `assets/css/user_pages/public.css` that styles placeholder thumbnails (when no image is available) on the public landing page
- **aspect-ratio**: The CSS property that maintains the 3/4 width-to-height ratio for thumbnails

## Bug Details

### Fault Condition

The bug manifests when thumbnails are rendered on the public.php landing page. The `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` classes define `width: 100%` and `aspect-ratio: 3/4` but lack a `max-height` constraint, causing thumbnails to scale to excessively large dimensions based on their container width.

**Formal Specification:**
```
FUNCTION isBugCondition(element)
  INPUT: element of type HTMLElement (thumbnail on public.php)
  OUTPUT: boolean
  
  RETURN element.classList.contains('public-file-thumbnail') OR 
         element.classList.contains('public-file-thumbnail-placeholder')
         AND element.closest('.public-file-card') exists
         AND NOT element.closest('.browse-list-view')
         AND NOT element.closest('.browse-file-card-compact')
         AND element.style.maxHeight is undefined OR element.style.maxHeight == 'none'
END FUNCTION
```

### Examples

- **Public Landing Page Grid**: A thumbnail in the public file grid displays at 400px+ height when the card width allows it, making the page feel cluttered and unprofessional
- **Wide Viewport**: On larger screens, thumbnails grow disproportionately large, dominating the visual hierarchy
- **Expected Behavior**: Thumbnails should be constrained to approximately 300-350px maximum height while maintaining the 3/4 aspect ratio
- **Edge Case**: Very narrow viewports (mobile) should still allow thumbnails to scale down naturally with the container

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Browse list view thumbnails must continue to use their existing `height: 100% !important` rule
- Browse compact view thumbnails must continue to use their existing `height: auto` rule
- Thumbnail hover effects (scale transformation) must continue to work exactly as before
- The 3/4 aspect ratio must be preserved in all contexts

**Scope:**
All thumbnail styling that does NOT involve the public landing page grid should be completely unaffected by this fix. This includes:
- `.browse-list-view .public-file-thumbnail` and `.browse-list-view .public-file-thumbnail-placeholder` (lines 1668-1672)
- `.browse-file-card-compact .public-file-thumbnail` and `.browse-file-card-compact .public-file-thumbnail-placeholder` (lines 1921-1925)
- Hover transform effects (lines 2902-2909)
- Modal thumbnail display
- Any other thumbnail contexts outside the public landing page grid

## Hypothesized Root Cause

Based on the bug description and CSS analysis, the root cause is:

1. **Missing Height Constraint**: The `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` classes (lines 271-287) define `width: 100%` and `aspect-ratio: 3/4` but no `max-height` property
   - With `width: 100%`, thumbnails expand to fill their container width
   - The `aspect-ratio: 3/4` then calculates height as (width * 4/3)
   - Without a `max-height`, there's no upper bound on thumbnail size

2. **Container Width Variability**: The `.public-file-card` containers in the grid layout can be quite wide on larger viewports, causing thumbnails to scale proportionally

3. **CSS Specificity**: The fix must target only the public landing page thumbnails without affecting the more specific rules for browse list view and compact view

## Correctness Properties

Property 1: Fault Condition - Thumbnail Height Constraint

_For any_ thumbnail element on the public.php landing page where the bug condition holds (no max-height constraint), the fixed CSS SHALL apply a max-height constraint of approximately 320px, preventing thumbnails from displaying at oversized dimensions while maintaining the 3/4 aspect ratio.

**Validates: Requirements 2.1, 2.2**

Property 2: Preservation - Other View Thumbnail Behavior

_For any_ thumbnail element that is NOT on the public landing page grid (browse list view, compact view, modal view), the fixed CSS SHALL produce exactly the same rendering as the original CSS, preserving all existing height rules, aspect ratios, and hover effects.

**Validates: Requirements 3.1, 3.2, 3.3**

## Fix Implementation

### Changes Required

**File**: `assets/css/user_pages/public.css`

**Target Classes**: `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` (lines 271-287)

**Specific Changes**:
1. **Add max-height Constraint**: Add `max-height: 320px;` to both `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` classes
   - This limits thumbnail height to a reasonable maximum
   - The aspect-ratio will still be maintained, so width will adjust accordingly
   - Value of 320px provides good visual balance in the grid layout

2. **Preserve object-fit**: Ensure `object-fit: cover;` remains on `.public-file-thumbnail` to handle image cropping properly

3. **Verify Specificity**: Confirm that the more specific rules for `.browse-list-view` and `.browse-file-card-compact` will continue to override this base rule due to higher specificity

**Modified CSS** (lines 271-287):
```css
.public-file-thumbnail {
    width: 100%;
    aspect-ratio: 3/4;
    max-height: 320px;
    object-fit: cover;
    display: block;
    background: #F3F4F6;
}

.public-file-thumbnail-placeholder {
    width: 100%;
    aspect-ratio: 3/4;
    max-height: 320px;
    background: linear-gradient(135deg, #E5E7EB 0%, #D1D5DB 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9CA3AF;
    font-size: 48px;
}
```

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, visually confirm the bug exists on the unfixed code by observing oversized thumbnails, then verify the fix constrains thumbnail height correctly while preserving all other thumbnail behaviors.

### Exploratory Fault Condition Checking

**Goal**: Visually confirm the bug BEFORE implementing the fix. Observe oversized thumbnails on the public landing page and measure their dimensions.

**Test Plan**: Open public.php in a browser at various viewport widths and measure thumbnail heights using browser DevTools. Document the oversized dimensions to confirm the bug exists.

**Test Cases**:
1. **Desktop Viewport (1920px)**: Measure thumbnail height on public landing page (will show excessive height on unfixed code)
2. **Laptop Viewport (1440px)**: Measure thumbnail height on public landing page (will show excessive height on unfixed code)
3. **Tablet Viewport (768px)**: Measure thumbnail height on public landing page (may show moderate oversizing on unfixed code)
4. **Mobile Viewport (375px)**: Measure thumbnail height on public landing page (should be reasonable even on unfixed code due to narrow container)

**Expected Counterexamples**:
- Thumbnails display at 400px+ height on wide viewports
- Thumbnails dominate the visual hierarchy, making cards feel unbalanced
- Possible root cause: missing max-height constraint allowing unlimited scaling

### Fix Checking

**Goal**: Verify that for all thumbnails on the public landing page where the bug condition holds, the fixed CSS constrains height to the specified maximum.

**Pseudocode:**
```
FOR ALL thumbnail WHERE isBugCondition(thumbnail) DO
  result := getComputedStyle(thumbnail).maxHeight
  ASSERT result == '320px'
  ASSERT getComputedStyle(thumbnail).aspectRatio == '3 / 4'
END FOR
```

### Preservation Checking

**Goal**: Verify that for all thumbnails where the bug condition does NOT hold (other views), the fixed CSS produces the same result as the original CSS.

**Pseudocode:**
```
FOR ALL thumbnail WHERE NOT isBugCondition(thumbnail) DO
  ASSERT getComputedStyle_original(thumbnail) == getComputedStyle_fixed(thumbnail)
END FOR
```

**Testing Approach**: Visual regression testing is recommended for preservation checking because:
- It captures the complete rendered appearance across different contexts
- It catches subtle layout shifts or styling changes that unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy contexts

**Test Plan**: Before applying the fix, capture screenshots of browse list view, browse compact view, and modal view thumbnails. After applying the fix, capture the same screenshots and compare pixel-by-pixel to ensure no changes.

**Test Cases**:
1. **Browse List View Preservation**: Verify thumbnails in browse list view continue to use `height: 100%` and display correctly
2. **Browse Compact View Preservation**: Verify thumbnails in browse compact view continue to use `height: auto` and display correctly
3. **Hover Effect Preservation**: Verify thumbnail hover scale transformation continues to work on public landing page
4. **Aspect Ratio Preservation**: Verify 3/4 aspect ratio is maintained in all contexts after fix

### Unit Tests

- Test that `.public-file-thumbnail` has `max-height: 320px` applied
- Test that `.public-file-thumbnail-placeholder` has `max-height: 320px` applied
- Test that aspect ratio remains `3/4` after fix
- Test that browse list view thumbnails are not affected by the fix

### Property-Based Tests

Not applicable for this CSS-only fix. Visual regression testing is more appropriate.

### Integration Tests

- Test full public landing page rendering at multiple viewport widths (375px, 768px, 1440px, 1920px)
- Test that thumbnails are constrained to reasonable heights across all viewport sizes
- Test that switching between public landing page and browse views shows correct thumbnail sizing in each context
- Test that thumbnail hover effects continue to work correctly after fix
