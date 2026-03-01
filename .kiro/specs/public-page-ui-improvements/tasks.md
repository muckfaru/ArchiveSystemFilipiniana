# Implementation Plan: Public Page UI Improvements

## Overview

This implementation plan outlines the steps to add a "Back to Home" button to the admin login modal and update the sidebar color scheme from blue to white with grey text. The changes are primarily CSS-based with minimal JavaScript modifications to maintain existing functionality.

## Tasks

- [x] 1. Add "Back to Home" button to admin login modal
  - Add HTML button element below the login submit button in views/public.php
  - Add corresponding button element in the forgot password view
  - Style the button to match existing modal design patterns in assets/css/pages/public.css
  - _Requirements: 1.1, 1.3, 1.4_

- [x] 2. Implement "Back to Home" button functionality
  - Add JavaScript event listener for the new button in views/public.php
  - Call the existing closeModal() function when button is clicked
  - Ensure button works in both login and forgot password views
  - _Requirements: 1.2, 1.4_

- [ ]* 2.1 Write unit tests for back button functionality
  - Test button closes modal from login view
  - Test button closes modal from forgot password view
  - Test existing modal close methods still work (backdrop click, escape key, X button)
  - _Requirements: 1.2, 4.1_

- [x] 3. Update sidebar background color
  - Modify .sidebar class in assets/css/style.css to use white background (#FFFFFF)
  - Add right border to sidebar for visual separation (#E5E7EB)
  - Remove or update any blue background gradients or overlays
  - _Requirements: 2.1_

- [x] 4. Update sidebar text and icon colors
  - Modify .nav-link class to use grey color (#6B7280) for inactive states
  - Update .nav-link:hover to use darker grey (#374151) and light background (#F9FAFB)
  - Update .nav-link.active to use light grey background (#F3F4F6) with primary color text (#3A9AFF)
  - Update .nav-link.active::before accent bar to use primary color (#3A9AFF)
  - _Requirements: 2.2, 2.3, 2.4_

- [x] 5. Update sidebar footer and header colors
  - Modify .sidebar-footer to use grey text colors consistent with navigation
  - Update .sidebar-title and .sidebar-subtitle to use grey colors
  - Update .sidebar-user-name and .sidebar-user-role to use appropriate grey shades
  - Update .sidebar-logout-btn to use grey color scheme
  - _Requirements: 2.5_

- [ ]* 5.1 Write property test for sidebar color contrast
  - **Property 2: Sidebar Color Contrast**
  - **Validates: Requirements 2.3**
  - Generate various text/background combinations from sidebar
  - Verify all combinations meet WCAG AA contrast ratio (4.5:1 minimum)

- [x] 6. Verify primary color consistency across application
  - Audit all button classes (.btn-primary, .public-search-btn, .admin-login-submit)
  - Ensure all use #3A9AFF or var(--primary-color)
  - Update any hardcoded blue values to use the CSS variable
  - Verify hover states use appropriate shades of primary color
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ]* 6.1 Write property test for primary color consistency
  - **Property 3: Primary Color Consistency**
  - **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**
  - Extract computed styles from all interactive elements
  - Verify all primary color values are #3A9AFF or derived shades

- [x] 7. Checkpoint - Test all modal functionality
  - Ensure all tests pass, ask the user if questions arise.
  - Manually verify modal opens and closes correctly
  - Verify "Back to Home" button works in both views
  - Verify existing close methods (X button, backdrop, escape key) still work
  - _Requirements: 4.1, 4.3, 4.4_

- [x] 8. Checkpoint - Test all sidebar functionality
  - Ensure all tests pass, ask the user if questions arise.
  - Manually verify sidebar navigation works on all admin pages
  - Verify hover states provide appropriate visual feedback
  - Verify active states highlight correctly with new color scheme
  - _Requirements: 4.2_

- [ ]* 8.1 Write property test for modal functionality preservation
  - **Property 4: Modal Functionality Preservation**
  - **Validates: Requirements 4.1, 4.3, 4.4**
  - Test all modal open/close interactions
  - Verify form submissions work correctly
  - Verify view switching works correctly

- [ ]* 8.2 Write property test for sidebar navigation preservation
  - **Property 5: Sidebar Navigation Preservation**
  - **Validates: Requirements 4.2**
  - Test all navigation link clicks
  - Verify hover and active states work correctly
  - Verify navigation functionality is unchanged

- [x] 9. Test responsive behavior
  - Test modal on mobile viewport sizes (320px, 375px, 768px)
  - Test sidebar on mobile viewport sizes
  - Verify all functionality works on touch devices
  - Verify color scheme looks good on different screen sizes
  - _Requirements: 4.5_

- [ ]* 9.1 Write property test for responsive behavior preservation
  - **Property 6: Responsive Behavior Preservation**
  - **Validates: Requirements 4.5**
  - Test modal and sidebar at various viewport sizes
  - Verify responsive behavior is unchanged after UI updates

- [x] 10. Cross-browser testing
  - Test in Chrome (latest version)
  - Test in Firefox (latest version)
  - Test in Safari (latest version)
  - Test in Edge (latest version)
  - Document any browser-specific issues and fixes
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 11. Final checkpoint - Verify all requirements
  - Ensure all tests pass, ask the user if questions arise.
  - Verify "Back to Home" button is present and functional
  - Verify sidebar has white background with grey text
  - Verify primary color (#3A9AFF) is used consistently
  - Verify no existing functionality is broken
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster implementation
- Most changes are CSS-only, minimizing risk of breaking functionality
- The "Back to Home" button reuses existing modal close logic
- Color changes should be tested with accessibility tools to ensure WCAG AA compliance
- Manual testing is critical for visual verification of the new color scheme
