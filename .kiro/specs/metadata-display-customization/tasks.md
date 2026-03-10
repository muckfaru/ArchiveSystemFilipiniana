# Implementation Plan: Metadata Display Customization

## Overview

This implementation plan breaks down the Metadata Display Customization feature into discrete coding tasks. The feature enables administrators to control the visibility and display order of custom metadata fields in two contexts: file cards (basic view) and preview modals (detailed view). The implementation follows a layered approach: database schema, backend API, helper functions, configuration UI, and finally integration with existing pages.

## Tasks

- [x] 1. Create database schema and migration
  - [x] 1.1 Create migration script for metadata_display_config table
    - Write migration file `backend/migrations/004_create_metadata_display_config.php`
    - Include table creation with all columns (id, field_id, show_on_card, show_in_modal, card_display_order, modal_display_order, timestamps)
    - Add foreign key constraint to custom_metadata_fields with CASCADE delete
    - Add unique constraint on field_id
    - Add composite indexes for card and modal visibility queries
    - Include rollback check to prevent duplicate table creation
    - Insert default configurations for existing custom metadata fields
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6_
  
  - [ ]* 1.2 Write property test for referential integrity
    - **Property 2: Referential Integrity Enforcement**
    - **Validates: Requirements 1.6**
    - Test that configurations with invalid field_id are rejected
    - Test that deleting a field cascades to delete its configuration

- [x] 2. Implement backend helper functions
  - [x] 2.1 Add getDisplayConfig() function to backend/core/functions.php
    - Implement query with LEFT JOIN between custom_metadata_fields and metadata_display_config
    - Use COALESCE to apply default values when configuration doesn't exist
    - Implement request-level caching using static variable
    - Support context parameter ('card', 'modal', 'both')
    - Apply appropriate filtering and sorting based on context
    - _Requirements: 1.5, 7.1, 7.2, 9.4, 9.5_
  
  - [x] 2.2 Add getVisibleFields() function to backend/core/functions.php
    - Wrapper function that calls getDisplayConfig() with specific context
    - Validate context parameter (must be 'card' or 'modal')
    - Return filtered and ordered fields for the specified context
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 5.1, 5.3, 6.1, 6.3_
  
  - [x] 2.3 Add getFileMetadataForDisplay() function to backend/core/functions.php
    - Get visible fields for specified context
    - Query custom_metadata_values for the file with field_id IN clause
    - Combine fields with values maintaining display order
    - Omit fields with null or empty values
    - Return array of field-value pairs with labels
    - _Requirements: 11.1, 11.2, 11.3, 11.4_
  
  - [x] 2.4 Add validateDisplayConfig() function to backend/core/functions.php
    - Validate field_id is numeric
    - Validate show_on_card and show_in_modal are boolean (0 or 1)
    - Validate card_display_order and modal_display_order are non-negative integers
    - Return array of validation errors (empty if valid)
    - _Requirements: 3.1, 3.4, 12.1, 12.2, 12.5_
  
  - [ ]* 2.5 Write property tests for helper functions
    - **Property 3: Default Behavior Application**
    - **Validates: Requirements 1.5, 7.1, 7.2, 7.3**
    - Test that fields without configuration use defaults
    - **Property 7: Alphabetical Tie-Breaking**
    - **Validates: Requirements 3.2**
    - Test that fields with same display_order sort alphabetically
    - **Property 9: Ascending Order Application**
    - **Validates: Requirements 3.5**
    - Test that fields are sorted in ascending numeric order
    - **Property 14: Empty Value Omission**
    - **Validates: Requirements 11.3**
    - Test that fields with no values are omitted from display

- [ ] 3. Checkpoint - Ensure database and helper functions work
  - Run migration script and verify table creation
  - Test helper functions with sample data
  - Ensure all tests pass, ask the user if questions arise

- [x] 4. Implement backend API endpoints
  - [x] 4.1 Create backend/api/metadata-display-config.php with authentication
    - Add authentication check (isLoggedIn)
    - Add authorization check (admin or super_admin role only)
    - Set JSON content-type header
    - Implement action routing (list, update, reset)
    - Add try-catch error handling with appropriate HTTP status codes
    - _Requirements: 2.1_
  
  - [x] 4.2 Implement 'list' action endpoint
    - Call getDisplayConfig() with 'both' context
    - Return JSON response with all fields and their configurations
    - _Requirements: 2.2, 2.3_
  
  - [x] 4.3 Implement 'update' action endpoint
    - Parse JSON request body
    - Validate request format (configurations array)
    - Validate each configuration using validateDisplayConfig()
    - Check that at least one field has show_in_modal = 1
    - Use database transaction for atomic updates
    - Use INSERT ... ON DUPLICATE KEY UPDATE for upsert
    - Log activity on success
    - Return success/error JSON response
    - _Requirements: 2.4, 2.5, 2.6, 2.7, 8.1, 8.2, 8.3, 8.4, 8.5, 12.3_
  
  - [x] 4.4 Implement 'reset' action endpoint
    - Support resetting single field (with field_id) or all fields (without field_id)
    - Use database transaction
    - Delete configuration records (triggers default behavior)
    - Log activity on success
    - Return success JSON response
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_
  
  - [ ]* 4.5 Write property tests for API endpoints
    - **Property 1: Configuration Persistence Round Trip**
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 8.2**
    - Test save and retrieve returns same values
    - **Property 4: Access Control Enforcement**
    - **Validates: Requirements 2.1**
    - Test non-admin users are denied access
    - **Property 6: Non-Negative Order Validation**
    - **Validates: Requirements 3.1, 3.4, 12.1, 12.2**
    - Test negative and non-numeric values are rejected
    - **Property 22: At Least One Modal Field Required**
    - **Validates: Requirements 12.3**
    - Test configuration with all modal fields disabled is rejected

- [x] 5. Create configuration UI page
  - [x] 5.1 Create pages/metadata-display.php controller
    - Add authentication and authorization checks
    - Query all custom_metadata_fields with LEFT JOIN to metadata_display_config
    - Use COALESCE for default values
    - Order by field_name
    - Pass fields array to view
    - _Requirements: 2.1, 2.2, 2.3_
  
  - [x] 5.2 Create views/metadata-display.php view
    - Include header and navigation
    - Display instructions for administrators
    - Create table with columns: Field Name, Card Visibility, Modal Visibility, Card Order, Modal Order
    - Render each field as a table row with toggle switches and number inputs
    - Add "Reset All" and "Save Changes" buttons
    - Include empty state message if no fields exist
    - Include JavaScript and CSS files
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 12.4_
  
  - [x] 5.3 Create assets/css/pages/metadata-display.css
    - Style configuration table with proper spacing
    - Style toggle switches for visibility controls
    - Style number inputs for display order
    - Style action buttons (Reset All, Save Changes)
    - Style success/error message containers
    - Add responsive design for mobile devices
  
  - [x] 5.4 Create assets/js/pages/metadata-display.js
    - Implement client-side validation for display order (non-negative integers)
    - Implement form submission via AJAX to update endpoint
    - Implement reset functionality with confirmation dialog
    - Display success/error messages
    - Update UI without page refresh on successful save
    - Prevent form submission if validation fails
    - _Requirements: 3.1, 3.4, 5.5, 8.3, 8.4, 8.5, 10.4, 10.5, 12.1, 12.2, 12.5_
  
  - [ ]* 5.5 Write unit tests for configuration UI
    - Test page loads correctly for admin users
    - Test page denies access to non-admin users
    - Test form validation prevents invalid submissions
    - Test save operation updates configuration
    - Test reset operation restores defaults

- [ ] 6. Checkpoint - Ensure configuration UI works end-to-end
  - Test creating and updating display configurations
  - Test validation and error handling
  - Test reset functionality
  - Ensure all tests pass, ask the user if questions arise

- [x] 7. Integrate display configuration with dashboard.php
  - [x] 7.1 Update dashboard.php file card rendering
    - Call getVisibleFields($pdo, 'card') once at page load
    - For each file, call getFileMetadataForDisplay($pdo, $fileId, 'card')
    - Update file card template to render only visible fields in configured order
    - Display "No metadata configured for display" message if no fields visible
    - _Requirements: 4.1, 4.6, 5.1, 5.2_
  
  - [x] 7.2 Update dashboard.php preview modal rendering
    - Modify modal JavaScript to fetch metadata with context=modal parameter
    - Update backend endpoint to use getFileMetadataForDisplay($pdo, $fileId, 'modal')
    - Update modal template to render fields in configured order
    - _Requirements: 4.3, 5.3, 5.4_
  
  - [ ]* 7.3 Write integration tests for dashboard display
    - **Property 10: Visibility Filtering**
    - **Validates: Requirements 4.1, 4.3, 5.1, 5.3**
    - Test hidden fields don't appear in cards or modals
    - **Property 12: Display Order Consistency**
    - **Validates: Requirements 5.2, 5.4**
    - Test fields appear in configured order

- [x] 8. Integrate display configuration with public.php
  - [x] 8.1 Update public.php file card rendering
    - Call getVisibleFields($pdo, 'card') once at page load
    - For each file, call getFileMetadataForDisplay($pdo, $fileId, 'card')
    - Update file card template to render only visible fields in configured order
    - Display "No metadata configured for display" message if no fields visible
    - _Requirements: 4.2, 6.1, 6.2_
  
  - [x] 8.2 Update public.php preview modal rendering
    - Modify modal JavaScript to fetch metadata with context=modal parameter
    - Update backend endpoint to use getFileMetadataForDisplay($pdo, $fileId, 'modal')
    - Update modal template to render fields in configured order
    - _Requirements: 4.4, 6.3, 6.4_
  
  - [ ]* 8.3 Write integration tests for public page display
    - **Property 10: Visibility Filtering**
    - **Validates: Requirements 4.2, 4.4, 6.1, 6.3**
    - Test hidden fields don't appear in cards or modals
    - **Property 12: Display Order Consistency**
    - **Validates: Requirements 6.2, 6.4, 6.5**
    - Test fields appear in same order as dashboard

- [ ] 9. Integrate display configuration with browse.php
  - [ ] 9.1 Update browse.php file card rendering
    - Call getVisibleFields($pdo, 'card') once at page load
    - For each file, call getFileMetadataForDisplay($pdo, $fileId, 'card')
    - Update file card template to render only visible fields in configured order
    - Ensure consistency with dashboard and public pages
  
  - [ ] 9.2 Update browse.php preview modal rendering
    - Modify modal JavaScript to fetch metadata with context=modal parameter
    - Update backend endpoint to use getFileMetadataForDisplay($pdo, $fileId, 'modal')
    - Update modal template to render fields in configured order
    - Ensure consistency with dashboard and public pages
  
  - [ ]* 9.3 Write integration tests for browse page display
    - Test hidden fields don't appear in cards or modals
    - Test fields appear in configured order
    - Test consistency across all three pages

- [x] 10. Add navigation link to configuration UI
  - [x] 10.1 Update views/layouts/sidebar.php
    - Add "Metadata Display" link under admin section
    - Link to pages/metadata-display.php
    - Show only for admin and super_admin roles
    - Add appropriate icon

- [ ] 11. Performance optimization and testing
  - [ ] 11.1 Verify query performance
    - Use EXPLAIN to verify index usage on metadata_display_config queries
    - Measure query execution time (should be < 100ms)
    - Verify single query per page load (not per file)
    - _Requirements: 9.1, 9.2, 9.3, 9.4_
  
  - [ ] 11.2 Verify caching implementation
    - Confirm getDisplayConfig() uses static variable caching
    - Test that configuration is loaded only once per request
    - Verify cache is cleared between requests
    - _Requirements: 9.5_
  
  - [ ]* 11.3 Write property tests for performance requirements
    - **Property 19: Single Configuration Query Per Page**
    - **Validates: Requirements 9.4**
    - Test configuration loaded once per page, not per file
    - **Property 20: Configuration Caching**
    - **Validates: Requirements 9.5**
    - Test configuration cached for request duration

- [ ] 12. Final integration and validation
  - [ ] 12.1 Test complete workflow end-to-end
    - Create new custom metadata fields
    - Configure display settings
    - Verify changes appear on dashboard, public, and browse pages
    - Test both file cards and preview modals
    - Test with various visibility and order combinations
  
  - [ ] 12.2 Test backward compatibility
    - Verify existing files with metadata display correctly
    - Test with fields created before this feature
    - Ensure default behavior works as expected
    - _Requirements: 7.4_
  
  - [ ] 12.3 Test edge cases
    - Test with no custom fields
    - Test with all fields hidden on cards
    - Test with fields having same display order
    - Test field deletion with existing configuration
    - _Requirements: 3.2, 4.6, 12.4_
  
  - [ ]* 12.4 Run all property-based tests
    - Execute all property tests with minimum 100 iterations
    - Verify all properties hold across random inputs
    - Document any failures or edge cases discovered

- [ ] 13. Final checkpoint - Complete testing and validation
  - Ensure all unit tests pass
  - Ensure all property tests pass
  - Ensure all integration tests pass
  - Verify performance requirements met
  - Ask the user if questions arise before considering feature complete

## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP delivery
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation and provide opportunities for user feedback
- Property tests validate universal correctness properties across all inputs
- Unit tests validate specific examples and edge cases
- The implementation follows a bottom-up approach: database → backend → UI → integration
- All display logic is centralized in helper functions for consistency across pages
- Request-level caching ensures optimal performance without complex cache invalidation
