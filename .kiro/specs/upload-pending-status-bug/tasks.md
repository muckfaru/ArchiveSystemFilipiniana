# Implementation Plan: Upload Pending Status Bug Fix

## Overview

This plan implements a focused bug fix for the upload page where file cards with "Pending" status persist after all files have been removed. The fix enhances the existing `resetForm()` function in `assets/js/pages/upload.js` to ensure complete UI cleanup and state synchronization when the last file is removed.

## Tasks

- [ ] 1. Enhance resetForm() function to clear all file card containers
  - Modify the `resetForm()` function in `assets/js/pages/upload.js`
  - Ensure all file card containers (`fileTabs`, `bulkTabs`, `bulkFileTabs`) are cleared using `innerHTML = ''`
  - Verify the clearing happens after state variables are reset
  - Add console logging to confirm container cleanup
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ]* 1.1 Write property test for file card container cleanup
  - **Property 1: File Card Container Cleanup**
  - **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
  - Verify all file card containers are empty after removing all files
  - Test with various file counts (0-10 files)
  - Use fast-check with minimum 100 iterations

- [ ]* 1.2 Write unit tests for file card cleanup
  - Test single file removal clears containers
  - Test multiple file removal clears containers
  - Test that containers remain empty after reset
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 2. Verify status counter reset logic
  - Review the existing status counter reset code in `resetForm()`
  - Ensure all counter elements are set to '0': `totalFilesCount`, `readyFilesCount`, `pendingFilesCount`, `totalFiles`, `readyFiles`, `pendingFiles`
  - Verify the duplicate status container is hidden
  - Confirm photo/document stats are reset
  - _Requirements: 1.4, 2.1, 2.2, 2.3, 2.4_

- [ ]* 2.1 Write property test for status counter reset
  - **Property 2: Status Counter Reset**
  - **Validates: Requirements 1.4, 2.1, 2.2, 2.3**
  - Verify all status counters display '0' after removing all files
  - Test with files in various states (pending, ready, mixed)
  - Use fast-check with minimum 100 iterations

- [ ]* 2.2 Write unit tests for status counters
  - Test counters reset to '0' after file removal
  - Test duplicate status message is hidden
  - Test photo/document stats are hidden
  - _Requirements: 1.4, 2.1, 2.2, 2.3, 2.4_

- [ ] 3. Verify bulk stats container visibility logic
  - Review the existing code that hides `bulkStatsContainer` in `resetForm()`
  - Ensure the container is properly hidden (display: none)
  - Verify `bulkUploadContainer` is also hidden
  - _Requirements: 1.2, 5.3_

- [ ]* 3.1 Write property test for bulk stats container hidden
  - **Property 3: Bulk Stats Container Hidden**
  - **Validates: Requirements 1.2**
  - Verify bulk stats container is hidden after removing all files
  - Test with various file configurations
  - Use fast-check with minimum 100 iterations

- [ ]* 3.2 Write property test for bulk upload controls hidden
  - **Property 9: Bulk Upload Controls Hidden**
  - **Validates: Requirements 5.3**
  - Verify bulk upload controls container is hidden after removing all files
  - Use fast-check with minimum 100 iterations

- [ ] 4. Verify drop zone visibility logic
  - Review the existing drop zone visibility code in `resetForm()`
  - Ensure drop zone shows when not in edit mode
  - Ensure edit mode indicator shows when in edit mode
  - Verify the logic correctly checks the action input value
  - _Requirements: 1.3, 5.1, 5.4_

- [ ]* 4.1 Write property test for drop zone visibility
  - **Property 4: Drop Zone Visibility**
  - **Validates: Requirements 1.3, 5.1, 5.4**
  - Verify drop zone is visible after removing all files (when not in edit mode)
  - Test both normal mode and edit mode scenarios
  - Use fast-check with minimum 100 iterations

- [ ]* 4.2 Write unit tests for drop zone visibility
  - Test drop zone shows in normal mode after reset
  - Test edit mode indicator shows in edit mode after reset
  - Test drop zone hidden when files are present
  - _Requirements: 1.3, 5.1, 5.4, 5.5_

- [ ] 5. Verify state variable reset logic
  - Review the existing state reset code in `resetForm()`
  - Ensure `bulkFiles = []` is set
  - Ensure `activeFileIndex = -1` is set
  - Ensure `isBulkMode = false` is set
  - Ensure `isBindMode = false` is set
  - Verify state is reset BEFORE UI cleanup
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ]* 5.1 Write property test for state variables reset
  - **Property 5: State Variables Reset**
  - **Validates: Requirements 4.1, 4.2, 4.3, 4.4**
  - Verify all state variables are reset to initial values after removing all files
  - Test with various initial states
  - Use fast-check with minimum 100 iterations

- [ ]* 5.2 Write unit tests for state variables
  - Test bulkFiles array is empty after reset
  - Test activeFileIndex is -1 after reset
  - Test isBulkMode is false after reset
  - Test isBindMode is false after reset
  - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [ ] 6. Verify button state update logic
  - Review the existing `updateButtons()` call in `resetForm()`
  - Ensure upload button is disabled when no files present
  - Ensure discard button state is based on form dirty status
  - Verify `updateButtons()` is called at the end of `resetForm()`
  - _Requirements: 6.1, 6.2, 6.3_

- [ ]* 6.1 Write property test for upload button disabled
  - **Property 6: Upload Button Disabled**
  - **Validates: Requirements 6.1**
  - Verify upload button is disabled after removing all files
  - Use fast-check with minimum 100 iterations

- [ ]* 6.2 Write unit tests for button states
  - Test upload button is disabled after reset
  - Test discard button state reflects form dirty status
  - Test buttons are enabled when files are present
  - _Requirements: 6.1, 6.2_

- [ ] 7. Verify selected file preview is hidden
  - Review the existing code that hides `selectedFilePreview` in `resetForm()`
  - Ensure the element is properly hidden (display: none)
  - _Requirements: 5.2_

- [ ]* 7.1 Write property test for selected file preview hidden
  - **Property 8: Selected File Preview Hidden**
  - **Validates: Requirements 5.2**
  - Verify selected file preview is hidden after removing all files
  - Use fast-check with minimum 100 iterations

- [ ] 8. Verify duplicate status container is hidden
  - Review the existing code that hides `duplicateStatusContainer` in `resetForm()`
  - Ensure the element is properly hidden (display: none)
  - _Requirements: 2.4_

- [ ]* 8.1 Write property test for duplicate status hidden
  - **Property 7: Duplicate Status Hidden**
  - **Validates: Requirements 2.4**
  - Verify duplicate status container is hidden after removing all files
  - Use fast-check with minimum 100 iterations

- [ ] 9. Checkpoint - Verify all resetForm() enhancements
  - Run all unit tests and property tests
  - Manually test the upload page by adding and removing files
  - Verify no file cards persist after removing all files
  - Verify all status counters show '0' or are hidden
  - Verify drop zone is visible after reset
  - Ensure all tests pass, ask the user if questions arise

- [ ] 10. Write integration test for complete reset state
  - Test the complete flow: add files → remove all → verify reset state
  - Verify no file-related UI components are visible except drop zone
  - Test interaction between `removeFile()` and `resetForm()`
  - _Requirements: 1.5_

- [ ]* 10.1 Write property test for complete reset state
  - **Property 10: Complete Reset State**
  - **Validates: Requirements 1.5**
  - Verify page displays no file-related UI components except drop zone after removing all files
  - Test with various file configurations
  - Use fast-check with minimum 100 iterations

- [ ] 11. Final checkpoint - Ensure all tests pass
  - Run complete test suite with coverage report
  - Verify 100% coverage of `resetForm()` function
  - Verify all 10 correctness properties are implemented and passing
  - Manually test edge cases: edit mode, single file, multiple files
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The existing `resetForm()` function already has most of the required logic in place
- Focus is on verification and testing rather than major code changes
- Each property test references a specific correctness property from the design document
- All property tests should use fast-check with minimum 100 iterations
- Test file location: `assets/js/pages/upload.test.js`
- Run tests with: `npm test` or `npm test -- --watch` for development
