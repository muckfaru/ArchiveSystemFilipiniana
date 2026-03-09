# Implementation Plan: Custom Metadata Form Builder System

## Overview

This implementation plan breaks down the Custom Metadata Form Builder System into 6 incremental phases, each building on the previous phase. The system extends the existing PHP-based archive upload functionality with administrator-defined custom metadata fields. Each task includes specific requirements references and testing sub-tasks to ensure correctness.

## Tasks

### Phase 1: Progress Bar on Upload Form

- [x] 1. Create progress bar JavaScript component
  - [x] 1.1 Implement ProgressBar class in assets/js/pages/progress-bar.js
    - Create constructor accepting containerId and requiredFields array
    - Implement updateProgress() method to calculate completion percentage
    - Implement getCompletionPercentage() method returning integer 0-100
    - Implement addRequiredField() and removeRequiredField() methods
    - Add color coding logic (red 0-33%, yellow 34-66%, green 67-100%)
    - _Requirements: 1.2, 1.3, 1.6_
  
  - [ ]* 1.2 Write property test for progress bar accuracy
    - **Property 1: Progress Bar Accuracy**
    - **Validates: Requirements 1.2, 1.5**
    - Test that percentage equals (filled required fields / total required fields) * 100
  
  - [x] 1.3 Add progress bar HTML to views/upload.php
    - Insert progress bar container at top of form (after page header)
    - Add data attributes to required core fields (data-required="true")
    - Include progress-bar.js script tag
    - _Requirements: 1.1, 1.7_
  
  - [x] 1.4 Integrate progress bar with existing upload.js
    - Initialize ProgressBar instance on page load
    - Bind to field change events for all required fields
    - Update progress on input, change, and blur events
    - Handle bulk upload mode (per-tab calculation)
    - _Requirements: 1.3, 1.4, 1.8_
  
  - [ ]* 1.5 Write unit tests for progress bar edge cases
    - Test with zero required fields
    - Test with all fields filled
    - Test with partially filled fields
    - Test field clear behavior
    - _Requirements: 1.4, 1.9_

- [x] 2. Checkpoint - Verify progress bar functionality
  - Test progress bar on upload form with core fields only
  - Ensure color transitions work correctly
  - Verify no interference with existing upload functionality
  - Ensure all tests pass, ask the user if questions arise

### Phase 2: Database Schema

- [x] 3. Create database migration script
  - [x] 3.1 Implement migration script backend/migrations/001_create_custom_metadata_tables.php
    - Create custom_metadata_fields table with all specified columns
    - Create custom_metadata_values table with foreign key constraints
    - Add indexes on is_enabled, display_order, file_id, field_id
    - Add unique constraint on (file_id, field_id) in custom_metadata_values
    - Implement idempotency check (skip if tables exist)
    - Add rollback capability with try-catch and transaction
    - _Requirements: 2.1, 2.2, 2.3, 2.9_
  
  - [ ]* 3.2 Write property test for migration idempotence
    - **Property 13: Migration Idempotence**
    - **Validates: Requirements 2.7, 2.8, 7.14**
    - Test running migration multiple times produces same schema
  
  - [x] 3.3 Update activity_logs enum to include custom_metadata_update
    - Modify activity_logs.action enum to add 'custom_metadata_update'
    - _Requirements: 7.15_
  
  - [x] 3.4 Create helper functions in backend/core/functions.php
    - Add getEnabledCustomFields() function
    - Add getCustomMetadataValues($fileId) function
    - Add saveCustomMetadataValues($fileId, $values) function
    - Add validateCustomField($field, $value) function
    - _Requirements: 2.1, 2.4, 2.5_
  
  - [ ]* 3.5 Write unit tests for helper functions
    - Test getEnabledCustomFields returns only enabled fields
    - Test getCustomMetadataValues with valid and invalid file_id
    - Test saveCustomMetadataValues with various field types
    - _Requirements: 2.4, 2.5, 2.6_

- [x] 4. Checkpoint - Verify database schema
  - Run migration script on test database
  - Verify all tables, columns, and indexes created correctly
  - Test rollback functionality
  - Ensure all tests pass, ask the user if questions arise

### Phase 3: Custom Field Manager Interface

- [x] 5. Create Field Manager backend API
  - [x] 5.1 Implement backend/api/custom-fields.php
    - Add authentication and authorization checks (admin/super_admin only)
    - Implement createField() function with validation
    - Implement updateField() function with validation
    - Implement deleteField() function with soft-delete
    - Implement toggleField() function for enable/disable
    - Implement listFields() function returning JSON
    - Add field_name uniqueness validation
    - Add field_type whitelist validation
    - Add field_options JSON encoding for select/checkbox/radio types
    - _Requirements: 3.4, 3.6, 3.8, 3.9, 3.11, 3.12, 3.13, 10.4, 10.7, 10.8_
  
  - [ ]* 5.2 Write property test for field definition uniqueness
    - **Property 3: Field Definition Uniqueness**
    - **Validates: Requirements 3.12**
    - Test that duplicate field_name values are rejected
  
  - [ ]* 5.3 Write property test for field deletion safety
    - **Property 12: Field Deletion Safety**
    - **Validates: Requirements 3.7, 3.13**
    - Test deletion with existing values requires confirmation
  
  - [x] 5.4 Add activity logging to all Field Manager operations
    - Log field creation with field_label
    - Log field updates with field_label
    - Log field deletion with field_label and user_id
    - _Requirements: 7.15, 10.10, 10.11_

- [x] 6. Create Field Manager frontend interface
  - [x] 6.1 Create pages/metadata-fields.php controller
    - Add authentication check and admin role validation
    - Query all custom_metadata_fields ordered by display_order
    - Load views/metadata-fields.php view
    - _Requirements: 3.1, 10.1, 10.2, 10.3_
  
  - [x] 6.2 Create views/metadata-fields.php view
    - Render page header with "Add Field" button
    - Render fields table with columns: label, type, required, status, actions
    - Add toggle switches for enable/disable
    - Add edit and delete buttons for each field
    - Create modal form for add/edit with all field properties
    - Add field_options input that shows/hides based on field_type
    - _Requirements: 3.2, 3.3, 3.5, 3.9, 3.13, 3.14_
  
  - [x] 6.3 Implement assets/js/pages/metadata-fields.js
    - Create FieldManager object with init() method
    - Implement openModal() for add/edit
    - Implement saveField() with client-side validation
    - Implement deleteField() with confirmation dialog
    - Implement toggleField() for enable/disable
    - Implement loadFields() to refresh table without page reload
    - Add field_type change handler to show/hide options input
    - Display inline validation errors
    - _Requirements: 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.15_
  
  - [x] 6.4 Create assets/css/pages/metadata-fields.css
    - Style field manager table
    - Style modal form
    - Style toggle switches
    - Style action buttons
    - _Requirements: 3.2_
  
  - [ ]* 6.5 Write unit tests for Field Manager JavaScript
    - Test modal open/close behavior
    - Test form validation
    - Test options field visibility toggle
    - _Requirements: 3.11, 3.13, 3.15_

- [x] 7. Add Field Manager to navigation
  - [x] 7.1 Add "Custom Fields" link to admin sidebar
    - Add menu item in appropriate navigation file
    - Restrict visibility to admin/super_admin roles
    - _Requirements: 3.1, 10.2_

- [ ] 8. Checkpoint - Verify Field Manager functionality
  - Test creating fields of all types
  - Test editing existing fields
  - Test deleting fields with and without values
  - Test enable/disable toggle
  - Verify validation works correctly
  - Ensure all tests pass, ask the user if questions arise

### Phase 4: Upload Form Integration

- [x] 9. Modify upload form to display custom fields
  - [x] 9.1 Update pages/upload.php controller
    - Query enabled custom fields ordered by display_order
    - For edit mode, query existing custom_metadata_values for file
    - Pass $customFields and $customMetadataValues to view
    - _Requirements: 4.1, 4.18_
  
  - [x] 9.2 Update views/upload.php to render custom fields
    - Add "Additional Information" section divider after core fields
    - Render each custom field based on field_type
    - Add data-required attribute to required custom fields
    - Pre-populate values in edit mode
    - Handle checkbox field_options as JSON array
    - Handle radio field_options as JSON array
    - Handle select field_options as JSON array
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.15, 4.18_
  
  - [ ]* 9.3 Write property test for field type rendering consistency
    - **Property 6: Field Type Rendering Consistency**
    - **Validates: Requirements 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9**
    - Test each field_type renders correct HTML element
  
  - [ ]* 9.4 Write property test for JSON options parsing
    - **Property 14: JSON Options Parsing**
    - **Validates: Requirements 2.10, 4.7, 4.8, 4.9**
    - Test field_options round-trip (JSON encode/decode)

- [x] 10. Implement custom metadata save logic
  - [x] 10.1 Add custom metadata save to single file upload in pages/upload.php
    - After successful newspapers INSERT, get lastInsertId()
    - Loop through custom fields and extract POST values
    - Handle checkbox values as JSON array
    - Insert custom_metadata_values rows within same transaction
    - Add error handling and transaction rollback
    - _Requirements: 4.13, 4.14, 4.15, 7.6_
  
  - [x] 10.2 Add custom metadata save to bulk upload in pages/upload.php
    - After each file INSERT in bulk loop, save custom metadata
    - Use same logic as single upload
    - Ensure transaction covers all files in batch
    - _Requirements: 4.17, 7.5_
  
  - [x] 10.3 Add custom metadata update to edit action in pages/upload.php
    - Check if custom_metadata_values row exists for each field
    - Update existing rows or insert new rows as needed
    - Handle empty values (don't insert NULL values)
    - _Requirements: 4.19_
  
  - [ ]* 10.4 Write property test for custom metadata atomicity
    - **Property 2: Custom Metadata Atomicity**
    - **Validates: Requirements 7.6, 8.11, 8.12**
    - Test that failed custom metadata insert rolls back file insert
  
  - [ ]* 10.5 Write property test for bulk upload metadata consistency
    - **Property 7: Bulk Upload Metadata Consistency**
    - **Validates: Requirements 4.17, 7.5**
    - Test N files creates N * custom_fields_count metadata rows

- [x] 11. Implement custom field validation
  - [x] 11.1 Add client-side validation in assets/js/pages/upload.js
    - Validate required custom fields before submission
    - Validate number fields contain numeric values
    - Validate date fields contain valid dates
    - Display inline error messages
    - _Requirements: 4.11, 8.1, 8.2, 8.3, 8.7_
  
  - [x] 11.2 Add server-side validation in pages/upload.php
    - Validate all required custom fields are filled
    - Validate field types match expected values
    - Return JSON error response with field-specific messages
    - _Requirements: 4.11, 4.12, 8.8, 8.9, 8.14, 10.5_
  
  - [ ]* 11.3 Write property test for required field validation
    - **Property 5: Required Field Validation**
    - **Validates: Requirements 4.11, 4.12, 8.1**
    - Test empty required field prevents submission
  
  - [ ]* 11.4 Write property test for validation rule enforcement
    - **Property 11: Validation Rule Enforcement**
    - **Validates: Requirements 8.4, 8.5, 8.6, 8.7, 8.8**
    - Test client-side pass implies server-side pass

- [x] 12. Update progress bar to include custom fields
  - [x] 12.1 Modify progress-bar.js to detect custom required fields
    - Query DOM for elements with data-required="true" including custom fields
    - Update calculation to include custom fields
    - _Requirements: 4.16_
  
  - [ ]* 12.2 Write unit tests for progress bar with custom fields
    - Test progress calculation with mixed core and custom required fields
    - Test progress updates when custom fields change
    - _Requirements: 4.16_

- [ ] 13. Checkpoint - Verify upload form integration
  - Test uploading files with custom fields filled
  - Test uploading files with required custom fields empty (should fail)
  - Test editing files and updating custom metadata
  - Test bulk upload with custom fields
  - Verify progress bar includes custom fields
  - Verify transaction rollback on error
  - Ensure all tests pass, ask the user if questions arise

### Phase 5: Display Integration

- [x] 14. Display custom metadata on Dashboard
  - [x] 14.1 Update views/dashboard.php to query custom metadata
    - Add JOIN query to fetch custom_metadata_values for displayed files
    - Optimize with single query for all files (not per-file queries)
    - _Requirements: 5.1, 9.2_
  
  - [x] 14.2 Render custom metadata in Dashboard file cards
    - Display up to 3 custom metadata values below core fields
    - Show "View More" link if more than 3 values exist
    - Format values based on field_type (dates, checkbox arrays)
    - Skip fields with NULL or empty values
    - _Requirements: 5.2, 5.3, 5.9, 5.10_
  
  - [ ]* 14.3 Write property test for performance bound
    - **Property 15: Performance Bound Property**
    - **Validates: Requirements 9.4**
    - Test Dashboard with 50 files loads custom metadata in <500ms

- [x] 15. Display custom metadata on Browse page
  - [x] 15.1 Update views/browse.php to query custom metadata
    - Add JOIN query similar to Dashboard
    - Use same optimization strategy
    - _Requirements: 5.4, 9.2_
  
  - [x] 15.2 Render custom metadata in Browse page file cards
    - Use same layout as Dashboard
    - Display up to 3 values with "View More" link
    - _Requirements: 5.5_

- [x] 16. Display custom metadata on Reader page
  - [x] 16.1 Update pages/reader.php to query all custom metadata for file
    - Query custom_metadata_values with JOIN to custom_metadata_fields
    - Pass to view as $customMetadata array
    - _Requirements: 5.6_
  
  - [x] 16.2 Update reader.php view to display custom metadata
    - Add custom metadata section in file information panel
    - Display field labels alongside values
    - Format values based on field_type
    - Skip fields with no values
    - _Requirements: 5.7, 5.8, 5.9, 5.10_
  
  - [ ]* 16.3 Write property test for backward compatibility
    - **Property 10: Backward Compatibility Invariant**
    - **Validates: Requirements 7.1, 7.8, 7.9**
    - Test files without custom metadata display identically

- [x] 17. Add custom metadata to search functionality
  - [x] 17.1 Update search query to include custom_metadata_values
    - Add LEFT JOIN to custom_metadata_values in search query
    - Include field_value in WHERE clause for text matching
    - _Requirements: 5.11_
  
  - [x] 17.2 Highlight matching custom metadata in search results
    - Extract matching custom metadata values
    - Display in search result snippets
    - _Requirements: 5.12_
  
  - [ ]* 17.3 Write property test for search inclusion
    - **Property 9: Search Inclusion Property**
    - **Validates: Requirements 5.11, 5.12**
    - Test files with matching custom metadata appear in results

- [x] 18. Checkpoint - Verify display integration
  - Test Dashboard displays custom metadata correctly
  - Test Browse page displays custom metadata correctly
  - Test Reader page displays all custom metadata
  - Test search includes custom metadata
  - Verify files without custom metadata display normally
  - Ensure all tests pass, ask the user if questions arise

### Phase 6: Advanced Form Builder

- [x] 19. Create Form Builder UI foundation
  - [ ] 19.1 Add Form Builder mode toggle to views/metadata-fields.php
    - Add toggle button to switch between table view and builder view
    - Add canvas area for visual form design
    - Add sidebar with draggable field type buttons
    - _Requirements: 6.1, 6.2, 6.3_
  
  - [ ] 19.2 Create assets/js/pages/form-builder.js
    - Implement FormBuilder class with init() method
    - Set up drag-and-drop event handlers
    - Implement canvas rendering logic
    - _Requirements: 6.2, 6.3_
  
  - [ ] 19.3 Create assets/css/pages/form-builder.css
    - Style canvas area
    - Style draggable field buttons
    - Style field placeholders
    - Style properties panel
    - _Requirements: 6.2, 6.3_

- [ ] 20. Implement drag-and-drop functionality
  - [ ] 20.1 Add drag-and-drop for field creation
    - Handle dragstart, dragover, drop events
    - Create field placeholder on drop
    - Display inline editing controls
    - _Requirements: 6.4, 6.5_
  
  - [ ] 20.2 Add drag-and-drop for field reordering
    - Handle drag events for existing fields
    - Update display_order on drop
    - Animate field movement
    - _Requirements: 6.8_
  
  - [ ] 20.3 Implement field properties panel
    - Display panel when field is clicked
    - Add inputs for validation_rules (regex, min/max length, min/max value)
    - Add inputs for field_label, is_required, field_options
    - _Requirements: 6.6, 6.7_

- [ ] 21. Implement Form Builder advanced features
  - [ ] 21.1 Add preview mode
    - Render form exactly as users will see it
    - Toggle between edit and preview modes
    - _Requirements: 6.10_
  
  - [ ]* 21.2 Write property test for form builder preview equivalence
    - **Property 8: Form Builder Preview Equivalence**
    - **Validates: Requirements 6.10**
    - Test preview HTML matches actual upload form HTML
  
  - [ ] 21.3 Implement undo/redo functionality
    - Track state changes in history stack
    - Add undo/redo buttons
    - Implement keyboard shortcuts (Ctrl+Z, Ctrl+Y)
    - _Requirements: 6.14_
  
  - [ ] 21.4 Implement auto-save functionality
    - Save draft changes every 30 seconds
    - Display "Saving..." indicator
    - Display "Unsaved changes" warning
    - _Requirements: 6.15, 6.16_
  
  - [ ] 21.5 Add field grouping with collapsible sections
    - Allow creating section headers
    - Implement collapse/expand functionality
    - _Requirements: 6.18_
  
  - [ ] 21.6 Add "Duplicate Field" action
    - Copy existing field configuration
    - Generate unique field_name
    - _Requirements: 6.20_

- [ ] 22. Implement Form Builder validation and safety
  - [ ] 22.1 Add field_name uniqueness validation
    - Check uniqueness before saving
    - Display error if duplicate found
    - _Requirements: 6.11_
  
  - [ ] 22.2 Add deletion confirmation for fields with values
    - Query custom_metadata_values count
    - Display warning with affected file count
    - Require explicit confirmation
    - _Requirements: 6.12, 6.13_
  
  - [ ] 22.3 Implement validation_rules enforcement
    - Parse validation_rules JSON
    - Apply rules client-side in upload.js
    - Apply rules server-side in pages/upload.php
    - Display custom error messages
    - _Requirements: 6.17, 8.4, 8.5, 8.6_

- [ ] 23. Implement Form Builder save functionality
  - [ ] 23.1 Add save button and handler
    - Collect all field configurations from canvas
    - Validate all fields
    - POST to backend/api/custom-fields.php
    - Handle batch create/update operations
    - _Requirements: 6.9_
  
  - [ ] 23.2 Update backend/api/custom-fields.php for batch operations
    - Add batch_save action
    - Handle multiple field creates/updates in single transaction
    - Update display_order for all fields
    - _Requirements: 6.8, 6.9_

- [ ] 24. Final checkpoint - Verify Form Builder functionality
  - Test drag-and-drop field creation
  - Test field reordering
  - Test preview mode matches actual form
  - Test undo/redo functionality
  - Test auto-save
  - Test validation rules enforcement
  - Test field grouping
  - Test duplicate field action
  - Ensure all tests pass, ask the user if questions arise

### Phase 7: Final Integration and Testing

- [ ] 25. Comprehensive integration testing
  - [ ]* 25.1 Test complete workflow: create field → upload file → display metadata
    - Create custom field in Field Manager
    - Upload file with custom metadata
    - Verify metadata displays on Dashboard, Browse, Reader
    - _Requirements: All phases_
  
  - [ ]* 25.2 Test backward compatibility scenarios
    - Upload file with no custom fields enabled
    - View old files on Dashboard/Browse/Reader
    - Edit old files and add custom metadata
    - _Requirements: 7.1, 7.2, 7.3, 7.8, 7.9, 7.12_
  
  - [ ]* 25.3 Test error handling and recovery
    - Test transaction rollback on database error
    - Test file cleanup on failed upload
    - Test validation error display
    - _Requirements: 8.11, 8.12, 8.13_
  
  - [ ]* 25.4 Test security and access control
    - Test Field Manager access restrictions
    - Test XSS prevention in custom metadata display
    - Test SQL injection prevention
    - Test CSRF protection
    - _Requirements: 10.1, 10.2, 10.3, 10.5, 10.6, 10.7, 10.15_
  
  - [ ]* 25.5 Test performance with realistic data
    - Create 50 custom fields
    - Upload 100 files with custom metadata
    - Measure Dashboard load time
    - Measure upload form render time
    - _Requirements: 9.1, 9.2, 9.4, 9.5, 9.6, 9.7_

- [ ] 26. Documentation and deployment preparation
  - [ ] 26.1 Create database migration instructions
    - Document migration script execution steps
    - Document rollback procedure
    - _Requirements: 2.7, 2.8_
  
  - [ ] 26.2 Create administrator guide for Field Manager
    - Document how to create custom fields
    - Document field types and options
    - Document validation rules syntax
    - _Requirements: 3.1, 6.1_
  
  - [ ] 26.3 Update user documentation for custom metadata
    - Document how to fill custom fields on upload form
    - Document where custom metadata appears
    - _Requirements: 4.1, 5.1_

- [ ] 27. Final verification checkpoint
  - Run all property-based tests
  - Run all unit tests
  - Verify all requirements are met
  - Verify no regressions in existing functionality
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP deployment
- Each task references specific requirements for traceability
- Property-based tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- Checkpoints ensure incremental validation and provide opportunities for user feedback
- All phases build incrementally - each phase is fully functional before moving to the next
- Phase 1-5 provide core functionality; Phase 6 (Form Builder) is an enhancement
- Transaction handling is critical in Phase 4 to ensure data integrity
- Performance optimization is built into Phase 5 with JOIN queries instead of per-file queries
- Security measures (XSS prevention, SQL injection prevention, access control) are implemented throughout
