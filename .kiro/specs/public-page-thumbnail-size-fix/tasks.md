# Implementation Plan

- [ ] 1. Write bug condition exploration test
  - **Property 1: Fault Condition** - Thumbnail Height Constraint
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate oversized thumbnails exist
  - **Visual Testing Approach**: Manually inspect public.php at various viewport widths and measure thumbnail heights using browser DevTools
  - Test that thumbnails on public.php landing page display at excessive heights (400px+) on wide viewports (from Fault Condition in design)
  - Test that `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` elements lack max-height constraints
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (thumbnails display oversized - this is correct and proves the bug exists)
  - Document counterexamples found (e.g., "Thumbnail at 1920px viewport displays at 450px height instead of being constrained to ~320px")
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Other View Thumbnail Behavior
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy contexts (browse list view, compact view, hover effects)
  - Capture screenshots of browse list view thumbnails showing height: 100% behavior
  - Capture screenshots of browse compact view thumbnails showing height: auto behavior
  - Test hover scale transformation on public landing page thumbnails
  - Verify 3/4 aspect ratio is maintained in all contexts
  - Write visual regression tests capturing observed behavior patterns from Preservation Requirements
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 3. Fix for oversized thumbnails on public landing page

  - [ ] 3.1 Implement the CSS fix
    - Open `assets/css/user_pages/public.css`
    - Locate `.public-file-thumbnail` class (around line 271)
    - Add `max-height: 320px;` property to constrain thumbnail height
    - Locate `.public-file-thumbnail-placeholder` class (around line 279)
    - Add `max-height: 320px;` property to constrain placeholder height
    - Verify `aspect-ratio: 3/4` remains on both classes
    - Verify `object-fit: cover` remains on `.public-file-thumbnail`
    - Confirm more specific rules for `.browse-list-view` and `.browse-file-card-compact` will override due to higher specificity
    - _Bug_Condition: isBugCondition(element) where element is a thumbnail on public.php landing page without max-height constraint_
    - _Expected_Behavior: Thumbnails constrained to max-height: 320px while maintaining 3/4 aspect ratio (from Property 1 in design)_
    - _Preservation: Browse list view, compact view, hover effects, and aspect ratio must remain unchanged (from Preservation Requirements in design)_
    - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3_

  - [ ] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Thumbnail Height Constraint
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Open public.php at various viewport widths (1920px, 1440px, 768px, 375px)
    - Measure thumbnail heights using browser DevTools
    - Verify thumbnails are constrained to approximately 320px maximum height
    - Verify aspect ratio 3/4 is maintained
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2_

  - [ ] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Other View Thumbnail Behavior
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation visual regression tests from step 2
    - Compare screenshots of browse list view thumbnails (should be identical to unfixed)
    - Compare screenshots of browse compact view thumbnails (should be identical to unfixed)
    - Test hover scale transformation still works correctly
    - Verify 3/4 aspect ratio maintained in all contexts
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.1, 3.2, 3.3_

- [ ] 4. Checkpoint - Ensure all tests pass
  - Verify thumbnails on public landing page are constrained to reasonable heights
  - Verify browse list view and compact view thumbnails are unchanged
  - Verify hover effects still work correctly
  - Verify aspect ratio is maintained in all contexts
  - Ask the user if questions arise
