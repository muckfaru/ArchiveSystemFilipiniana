# Implementation Plan

- [ ] 1. Write bug condition exploration test
  - **Property 1: Fault Condition** - Excessive Card Spacing (48px gaps)
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: For this deterministic CSS bug, scope the property to the concrete failing case (g-5 class on public page grid)
  - Test that the public page file grid has g-5 class applied (from Fault Condition in design)
  - Measure the actual gap size using browser automation or manual inspection
  - Verify gap is 48px (3rem) on both desktop and mobile viewports
  - The test assertions should match the Expected Behavior Properties from design (cards should have 16-24px gaps)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: "Grid has g-5 class with 48px gaps instead of g-3/g-4 with 16-24px gaps"
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1, 2.2_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Existing Functionality Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for all non-spacing functionality
  - Write tests capturing observed behavior patterns from Preservation Requirements:
    - Responsive grid structure (2 columns mobile, 4 columns desktop)
    - Hover effects (transform and shadow on card hover)
    - Click interactions (modal opens with correct metadata)
    - Pagination controls (display and function correctly)
    - Search and category filters (display filtered results correctly)
  - Manual testing is sufficient since this is a CSS-only change
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 3. Fix for excessive card spacing on public page

  - [ ] 3.1 Implement the fix
    - Open `views/public.php` and locate line 131
    - Replace `g-5` with `g-4` in the row class
    - Change `<div class="row g-5">` to `<div class="row g-4">`
    - This reduces gap from 48px to 24px (balanced, professional spacing)
    - _Bug_Condition: isBugCondition(input) where input.classList.contains('g-5') AND input.classList.contains('row') AND input.parentElement.classList.contains('public-grid-container')_
    - _Expected_Behavior: Cards display with 24px gaps creating balanced, professional layout with appropriate visual density_
    - _Preservation: Responsive grid structure, hover effects, modal interactions, pagination, and filtering functionality remain unchanged_
    - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Reduced Card Spacing (24px gaps)
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - Verify grid now has g-4 class with 24px gaps
    - Measure gaps on desktop and mobile viewports
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2_

  - [ ] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Functionality Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - Verify responsive grid structure still works (2 columns mobile, 4 columns desktop)
    - Verify hover effects still work (transform and shadow)
    - Verify click interactions still open modal with correct metadata
    - Verify pagination controls still display and function correctly
    - Verify search and category filters still work correctly
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
