# Implementation Plan

- [-] 1. Write bug condition exploration test
  - **Property 1: Fault Condition** - Script Loads Without Syntax Errors
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the syntax error exists and breaks upload functionality
  - **Scoped PBT Approach**: Scope the property to the concrete failing case - loading upload.php with the syntax error on line 443
  - Test that upload.js parses successfully without syntax errors when upload page loads
  - Test that event listeners are attached to drop zone, file input, and thumbnail input after page load
  - Test that drag-and-drop interactions trigger appropriate visual feedback
  - Test that file browse buttons open file picker dialogs
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the syntax error exists)
  - Document counterexamples found: syntax error at line 445, no event listeners attached, upload controls unresponsive
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - All Existing Functionality Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Since the unfixed code has a syntax error that breaks all functionality, we test the INTENDED behavior (what should work after the syntax error is fixed)
  - Write property-based tests capturing expected behavior patterns from Preservation Requirements
  - Test edit mode: drop zone hidden, existing file info shown
  - Test unsaved changes warning: appears when navigating away with unsaved changes
  - Test bulk upload mode: multiple files get individual metadata forms
  - Test bind mode: cover selection works for multiple images
  - Test custom metadata fields: values are captured and submitted correctly
  - Test tag input: tags are displayed and submitted correctly
  - Test publication date validation: invalid dates trigger validation errors
  - Test form submission: progress bar and success messages display correctly
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on FIXED code (since unfixed code is completely broken)
  - **EXPECTED OUTCOME**: Tests PASS (this confirms all features work correctly after syntax fix)
  - Mark task complete when tests are written, run, and passing on fixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 3. Fix for syntax error in upload.js

  - [x] 3.1 Implement the fix
    - Open `assets/js/pages/upload.js`
    - Navigate to line 443 within the `captureOriginalState()` function
    - Remove the extra closing brace `};` on line 443
    - Verify the forEach callback on line 429 closes with `});` on line 442
    - Verify the function closes with `}` on line 444
    - Save the file
    - _Bug_Condition: isBugCondition(input) where input.page == 'upload.php' AND syntaxError at line 445_
    - _Expected_Behavior: Script loads without syntax errors, all event listeners attach, all upload interactions work_
    - _Preservation: All existing upload behaviors (edit mode, unsaved changes warning, bulk upload, custom metadata, bind mode, tag input, validation, form submission) remain unchanged_
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5_

  - [-] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Script Loads Without Syntax Errors
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the syntax error is fixed and upload functionality works
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms syntax error is fixed)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [ ] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - All Existing Functionality Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 4. Checkpoint - Ensure all tests pass
  - Verify all exploration tests pass (syntax error fixed, upload interactions work)
  - Verify all preservation tests pass (edit mode, bulk mode, custom metadata, validation all work)
  - Manually test upload page in browser to confirm all functionality works
  - Check browser console for any remaining errors
  - Test drag-and-drop file upload end-to-end
  - Test file browse button end-to-end
  - Test thumbnail upload end-to-end
  - Ask the user if questions arise
