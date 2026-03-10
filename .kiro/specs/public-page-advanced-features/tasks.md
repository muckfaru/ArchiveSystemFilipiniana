# Implementation Plan: Public Page Advanced Features

## Overview

This implementation plan breaks down the development of advanced UI/UX features for the public archive page into discrete, manageable tasks. The implementation will proceed in phases: backend API setup, frontend controllers, styling, admin modal adaptation, and integration. Each task builds on previous work to ensure incremental progress and early validation.

## Tasks

- [ ] 1. Create backend API endpoint for infinite scroll
  - Create `backend/api/public-files.php` file
  - Implement pagination logic with page and limit parameters
  - Add support for search query and category filter parameters
  - Build SQL WHERE clause dynamically based on filters
  - Return JSON response with file documents array
  - Include proper error handling and HTTP status codes
  - Sanitize all input parameters to prevent SQL injection
  - _Requirements: 3.1, 3.4, 3.8, 3.9, 5.1_

- [ ]* 1.1 Write unit tests for API endpoint
  - Test pagination returns correct page of results
  - Test limit parameter is respected and capped at 60
  - Test search query filters results correctly
  - Test category filter works properly
  - Test response format is valid JSON
  - Test error responses return correct status codes
  - Test SQL injection prevention
  - _Requirements: 3.1, 3.4, 3.8, 3.9_

- [ ]* 1.2 Write property test for API response sanitization
  - **Property 7: API Response Sanitization**
  - **Validates: Requirements 4.8**

- [ ] 2. Implement infinite scroll controller
  - [ ] 2.1 Create InfiniteScrollController class in public.js
    - Initialize with container, page size (12), max files (60), and threshold (200px)
    - Track current page, total loaded files, loading state, and hasMore flag
    - Store search query and category filter from URL parameters
    - _Requirements: 3.1, 3.4, 3.5, 3.11_

  - [ ] 2.2 Implement scroll position monitoring
    - Add scroll event listener to window
    - Calculate distance from bottom of page
    - Trigger loadMore() when within threshold and not loading
    - Prevent duplicate requests using isLoading flag
    - _Requirements: 3.1, 3.12, 5.2_

  - [ ] 2.3 Implement file loading logic
    - Create fetchFiles() method to call API endpoint
    - Build URL with page, limit, search, and category parameters
    - Handle fetch response and parse JSON
    - Implement retry logic (up to 2 retries) for failed requests
    - _Requirements: 3.1, 3.4, 3.8, 5.1, 5.3_

  - [ ] 2.4 Implement file rendering
    - Create createFileCard() method to generate card HTML
    - Use same structure as existing file cards
    - Include all data attributes for modal functionality
    - Append new cards to existing grid
    - Attach click event listeners to new cards
    - _Requirements: 3.3, 5.8_

  - [ ] 2.5 Implement 60-file limit and Browse More button
    - Check if totalLoaded >= 60 before loading
    - Set hasMore = false when limit reached
    - Create showBrowseMoreButton() method
    - Generate button with link to browse.php
    - Preserve search query and category filter in URL
    - _Requirements: 3.5, 3.6, 3.7, 3.8_

  - [ ] 2.6 Implement loading states and indicators
    - Create showLoadingIndicator() method with spinner
    - Create hideLoadingIndicator() method
    - Create showEndMessage() for when all files loaded
    - Create showError() method with retry button
    - Add smooth fade-in/fade-out animations (200ms)
    - _Requirements: 3.2, 3.9, 3.10, 5.4_

  - [ ]* 2.7 Write unit tests for infinite scroll controller
    - Test initial state is correct
    - Test scroll triggers loading at threshold
    - Test loading stops at 60 files
    - Test Browse More button appears after 60 files
    - Test loading indicator shows/hides correctly
    - Test error handling displays error message
    - Test retry functionality works
    - Test duplicate requests are prevented
    - _Requirements: 3.1, 3.2, 3.5, 3.6, 3.9, 3.10, 3.12_

  - [ ]* 2.8 Write property test for infinite scroll load limit
    - **Property 2: Infinite Scroll Load Limit**
    - **Validates: Requirements 3.5, 3.6**

  - [ ]* 2.9 Write property test for infinite scroll idempotency
    - **Property 3: Infinite Scroll Idempotency**
    - **Validates: Requirements 3.12, 5.2**

  - [ ]* 2.10 Write property test for modal event handler persistence
    - **Property 5: Modal Event Handler Persistence**
    - **Validates: Requirements 5.8**

- [ ] 3. Checkpoint - Test infinite scroll functionality
  - Ensure infinite scroll loads files correctly
  - Verify 60-file limit works
  - Verify Browse More button appears and links correctly
  - Test error handling and retry
  - Ask the user if questions arise

- [ ] 4. Implement sticky navigation controller
  - [ ] 4.1 Create StickyNavController class in public.js
    - Initialize with navbar element and scroll threshold (100px)
    - Track isSticky state flag
    - Add scroll event listener
    - _Requirements: 2.1, 2.5_

  - [ ] 4.2 Implement scroll detection and state management
    - Create handleScroll() method to check scroll position
    - Call makeSticky() when scrollY > threshold and not sticky
    - Call makeNormal() when scrollY <= threshold and is sticky
    - Prevent redundant state changes
    - _Requirements: 2.1, 2.5, 2.6_

  - [ ] 4.3 Implement sticky mode layout changes
    - Create makeSticky() method
    - Add 'sticky' class to navbar
    - Clone search box from hero and insert into navbar center
    - Move navigation links to right side (before admin button)
    - Hide original search box in hero
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 4.4 Implement normal mode layout restoration
    - Create makeNormal() method
    - Remove 'sticky' class from navbar
    - Remove cloned search box from navbar
    - Restore navigation links to original position
    - Show original search box in hero
    - _Requirements: 2.5, 2.6_

  - [ ] 4.5 Add CSS styles for sticky navigation
    - Create .public-header.sticky styles with position: fixed
    - Add smooth transitions for all layout changes (300ms)
    - Style compact search box in navbar
    - Style right-aligned navigation links
    - Ensure navbar doesn't obscure content
    - Use CSS transforms for GPU acceleration
    - _Requirements: 2.4, 2.7, 2.8, 5.7_

  - [ ] 4.6 Handle edge cases and browser compatibility
    - Add resize event listener to recalculate positions
    - Debounce resize events (250ms)
    - Test with different viewport heights
    - Ensure no layout shift occurs during transitions
    - _Requirements: 2.9, 5.9, 5.10_

  - [ ]* 4.7 Write unit tests for sticky navigation
    - Test navbar becomes sticky at threshold
    - Test navbar returns to normal at threshold
    - Test search box moves to correct position
    - Test nav links reposition correctly
    - Test transitions apply smoothly
    - Test resize handling works
    - _Requirements: 2.1, 2.2, 2.3, 2.5, 2.6_

  - [ ]* 4.8 Write property test for sticky navigation state consistency
    - **Property 1: Sticky Navigation State Consistency**
    - **Validates: Requirements 2.1, 2.5, 2.6**

  - [ ]* 4.9 Write property test for navbar layout reversibility
    - **Property 6: Navbar Layout Reversibility**
    - **Validates: Requirements 2.6, 2.9**

- [ ] 5. Checkpoint - Test sticky navigation functionality
  - Ensure navbar becomes sticky on scroll
  - Verify layout changes are smooth
  - Test scroll back to top restores original layout
  - Test on different screen sizes
  - Ask the user if questions arise

- [ ] 6. Implement dynamic filter display
  - [ ] 6.1 Create filter label generation function in public.php
    - Create generateFilterLabel() function
    - Accept category, edition, dateFrom, dateTo parameters
    - Build label parts array
    - Handle "All Categories" default case
    - Append edition if selected
    - Append date range if selected
    - Sanitize all values with htmlspecialchars()
    - Return formatted "Showing: ..." string
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10_

  - [ ] 6.2 Add filter display HTML to public.php view
    - Call generateFilterLabel() function
    - Add filter display div above search results grid
    - Style with prominent visibility
    - Ensure proper spacing and alignment
    - _Requirements: 4.11_

  - [ ] 6.3 Handle undefined parameters gracefully
    - Use null coalescing operator (??) for all GET parameters
    - Validate category ID against available categories
    - Provide fallback values for missing data
    - Ensure no PHP undefined index errors
    - _Requirements: 4.12_

  - [ ]* 6.4 Write unit tests for filter display
    - Test "All Categories" displays when no category selected
    - Test category name displays when selected
    - Test edition appends to label
    - Test date range formats correctly
    - Test multiple filters concatenate properly
    - Test XSS prevention works
    - Test undefined parameters handled gracefully
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.12_

  - [ ]* 6.5 Write property test for filter display completeness
    - **Property 4: Filter Display Completeness**
    - **Validates: Requirements 4.4, 4.6, 4.7**

- [ ] 7. Adapt admin modal to public design
  - [ ] 7.1 Copy public modal HTML structure to dashboard view
    - Open views/dashboard.php
    - Locate existing file preview modal
    - Replace with public modal structure from views/public.php
    - Keep modal ID as 'filePreviewModal' for compatibility
    - Preserve Bootstrap modal classes if needed
    - _Requirements: 1.1, 1.2_

  - [ ] 7.2 Add admin action buttons to modal
    - Add Edit button with icon in modal actions section
    - Add Delete button with icon in modal actions section
    - Style buttons to match admin theme
    - Position buttons below "Read Full Document" button
    - _Requirements: 1.3_

  - [ ] 7.3 Update dashboard.js to populate new modal structure
    - Locate modal open function in assets/js/pages/dashboard.js
    - Update to populate public modal structure elements
    - Map all metadata fields to new element IDs
    - Ensure category badge styling is applied
    - Handle thumbnail display logic
    - _Requirements: 1.4, 1.5, 1.6_

  - [ ] 7.4 Preserve admin functionality
    - Ensure Edit button triggers existing edit flow
    - Ensure Delete button triggers existing delete flow
    - Test modal close functionality works
    - Verify all existing admin features still work
    - _Requirements: 1.7_

  - [ ]* 7.5 Write unit tests for admin modal
    - Test modal opens with correct data
    - Test admin buttons are present
    - Test edit button triggers edit flow
    - Test delete button triggers delete flow
    - Test modal matches public design
    - Test all metadata fields display correctly
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [ ] 8. Integration and final testing
  - [ ] 8.1 Test sticky navigation with infinite scroll
    - Verify sticky navbar works while scrolling to load more files
    - Ensure search box in sticky navbar remains functional
    - Test navigation links work in both states
    - _Requirements: 2.1, 3.1, 3.11_

  - [ ] 8.2 Test filter display with infinite scroll
    - Verify filter label remains visible during infinite scroll
    - Ensure filter values are preserved when loading more files
    - Test that infinite scroll respects active filters
    - _Requirements: 3.11, 3.13, 4.11_

  - [ ] 8.3 Test admin modal with infinite scroll
    - Load additional files via infinite scroll
    - Click on newly loaded file cards
    - Verify admin modal opens correctly
    - Test edit and delete functionality on new cards
    - _Requirements: 1.7, 5.8_

  - [ ] 8.4 Test all features together
    - Perform end-to-end user flow: scroll, filter, view modal
    - Test browser back/forward navigation
    - Test page refresh resets state correctly
    - Verify no JavaScript errors in console
    - Test on different browsers (Chrome, Firefox, Safari)
    - Test on different screen sizes (mobile, tablet, desktop)
    - _Requirements: 5.6, 5.9_

  - [ ]* 8.5 Write property test for loading state mutual exclusion
    - **Property 8: Loading State Mutual Exclusion**
    - **Validates: Requirements 3.2, 3.9**

- [ ] 9. Final checkpoint - Ensure all tests pass
  - Run all unit tests and verify they pass
  - Run all property tests and verify they pass
  - Test all features manually on different browsers
  - Verify no existing functionality is broken
  - Ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- The implementation preserves all existing functionality while adding new features
