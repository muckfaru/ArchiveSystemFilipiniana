# Implementation Plan: Form Templates System

## Overview

This implementation plan transforms the custom metadata system from individual field management to a template-based form builder. The system provides a Google Forms-like interface with drag-and-drop form building, a library for managing multiple templates, and dynamic rendering of active forms on the upload page.

The implementation follows a phased approach: database migration, backend API, form library UI, form builder UI, upload page integration, and comprehensive testing with property-based tests using the Eris library.

## Tasks

- [x] 1. Database migration and schema setup
  - [x] 1.1 Create migration file 002_migrate_to_form_templates.php
    - Create form_templates table with columns: id, name, description, status, is_active, created_at, updated_at
    - Create form_fields table with columns: id, form_id, field_label, field_type, field_options, is_required, display_order, help_text, created_at, updated_at
    - Add form_id column to custom_metadata_values table
    - Add foreign key constraints and indexes
    - Implement transaction-based migration with rollback capability
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6_

  - [x] 1.2 Implement migration logic for existing data
    - Create default form template named "Default Metadata Form" with status 'active' and is_active = 1
    - Migrate all existing custom_metadata_fields to form_fields in default template
    - Preserve field properties: field_label, field_type, field_options, is_required, display_order
    - Update all existing custom_metadata_values to reference default form template
    - Create field mapping between old field IDs and new field IDs
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

  - [ ]* 1.3 Write property test for migration field preservation
    - **Property 18: Migration Field Preservation**
    - **Validates: Requirements 12.3, 12.4**
    - Test that for any existing custom_metadata_field, migration creates corresponding form_field with identical properties

  - [ ]* 1.4 Write property test for migration value association
    - **Property 19: Migration Value Association**
    - **Validates: Requirements 12.5**
    - Test that for any existing custom_metadata_value, migration updates form_id to reference default form

  - [ ]* 1.5 Write property test for migration backward compatibility
    - **Property 20: Migration Backward Compatibility**
    - **Validates: Requirements 12.6**
    - Test that for any archive with metadata before migration, querying after migration returns same field values

- [x] 2. Backend API for form templates
  - [x] 2.1 Create backend/api/form-templates.php with CRUD endpoints
    - Implement authentication and authorization checks (admin/super_admin only)
    - Implement createFormTemplate action with transaction support
    - Implement updateFormTemplate action with transaction support
    - Implement deleteFormTemplate action with confirmation for forms with values
    - Implement setActiveFormTemplate action (deactivates other forms)
    - Implement duplicateFormTemplate action
    - Implement archiveFormTemplate action
    - Implement listFormTemplates action (with field counts)
    - Implement getFormTemplate action (with fields)
    - Return consistent JSON responses with success/error format
    - _Requirements: 2.8, 4.7, 5.2, 5.3, 7.1, 7.2, 7.3, 7.4, 7.5, 13.1, 13.2, 14.1, 14.2, 14.3, 14.4, 19.4_

  - [x] 2.2 Implement active form uniqueness enforcement
    - In createFormTemplate: if status is 'active', deactivate all other forms before inserting
    - In updateFormTemplate: if status is 'active', deactivate all other forms before updating
    - In setActiveFormTemplate: deactivate all forms, then activate selected form
    - Use database transactions to ensure atomicity
    - _Requirements: 5.1, 5.2, 11.2, 16.5_

  - [ ]* 2.3 Write property test for active form uniqueness invariant
    - **Property 1: Active Form Uniqueness Invariant**
    - **Validates: Requirements 5.1, 11.2**
    - Test that at any point in time, database contains at most one form with is_active = 1

  - [ ]* 2.4 Write property test for active form deactivation
    - **Property 2: Active Form Deactivation**
    - **Validates: Requirements 5.2**
    - Test that when setting form as active, all other forms have is_active set to 0 first

  - [x] 2.5 Implement activity logging for all form operations
    - Log form template creation with form name
    - Log form template updates with form name
    - Log form template deletion with form name
    - Log form activation changes
    - Log form duplication with original form name
    - Log form archival with form name
    - _Requirements: 2.8, 19.4_

- [ ] 3. Checkpoint - Verify database and API functionality
  - Run migration script and verify tables created correctly
  - Test API endpoints with curl or Postman
  - Verify active form uniqueness constraint works
  - Ensure all tests pass, ask the user if questions arise

- [x] 4. Form Library page implementation
  - [x] 4.1 Create pages/form-library.php controller
    - Check admin permissions (super_admin or admin roles only)
    - Query all form templates with field counts using LEFT JOIN
    - Order templates by updated_at DESC
    - Load view with form templates data
    - _Requirements: 4.1, 4.2_

  - [x] 4.2 Create views/form-library.php view
    - Render page header with "Create New Form" button
    - Render filter tabs: All Forms, Active, Drafts, Archived
    - Render search input field
    - Render form templates grid with cards
    - For each card: display title, description, status badge, field count, last modified date
    - For each card: render action buttons (Edit, Preview, dropdown with Set as Active, Duplicate, Archive, Delete)
    - Render empty state message when no forms match filter
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 18.1_

  - [x] 4.3 Create assets/js/pages/form-library.js
    - Implement filter tab switching (all, active, draft, archived)
    - Implement real-time search filtering by name and description (case-insensitive)
    - Implement "Create New Form" button navigation to form-builder.php
    - Implement "Edit" button navigation to form-builder.php?id={formId}
    - Implement "Set as Active" action with confirmation dialog
    - Implement "Duplicate" action with API call
    - Implement "Archive" action with API call
    - Implement "Delete" action with confirmation dialog
    - Implement "Preview" modal display
    - Show/hide empty state based on visible card count
    - _Requirements: 4.3, 4.4, 4.5, 4.6, 4.7, 7.1, 13.1, 14.1, 18.2, 18.3, 18.4, 18.5_

  - [x] 4.4 Create assets/css/pages/form-library.css
    - Style form template cards with hover effects
    - Style filter tabs with active state
    - Style search input
    - Style status badges (Active: green, Draft: gray, Archived: yellow)
    - Style action buttons and dropdown menus
    - Style empty state message
    - Ensure responsive grid layout (3 columns on desktop, 1 on mobile)
    - _Requirements: 4.1, 4.2_

  - [ ]* 4.5 Write unit tests for form library filtering
    - Test filter tabs show correct forms for each status
    - Test search matches form names case-insensitively
    - Test search matches form descriptions case-insensitively
    - Test empty state displays when no forms match filter
    - _Requirements: 4.3, 4.4, 4.5, 4.6, 18.2, 18.3, 18.4, 18.5_

- [x] 5. Form Builder page implementation
  - [x] 5.1 Create pages/form-builder.php controller
    - Check admin permissions (super_admin or admin roles only)
    - If id parameter exists, load existing form template and fields
    - If no id parameter, initialize empty form builder
    - Pass form data and fields to view as JSON
    - _Requirements: 2.1, 19.1, 19.2_

  - [x] 5.2 Create views/form-builder.php view
    - Render page header with "Back to Library" button
    - Render form metadata section: form name input (required), form description textarea
    - Render three-column layout: field types sidebar, form canvas, field configuration panel
    - Render field types sidebar with draggable items: Text, Textarea, Number, Date, Select, Checkbox, Radio
    - Render form canvas with empty state message or existing fields
    - Render field configuration panel (hidden by default): field label, field type, options editor, help text, required checkbox
    - Render action buttons: "Preview", "Save as Draft", "Publish"
    - Include hidden inputs for form ID and fields data
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 16.1, 16.2, 17.1_

  - [x] 5.3 Create assets/js/pages/form-builder.js
    - Initialize SortableJS for drag-and-drop functionality
    - Implement field type drag from sidebar to canvas
    - Implement field reordering within canvas
    - Implement field selection to show configuration panel
    - Implement field configuration save (label, type, required, help text, options)
    - Implement field deletion with confirmation
    - Implement options editor for select/checkbox/radio fields (add, edit, delete, reorder)
    - Implement "Save as Draft" action (status='draft', is_active=0)
    - Implement "Publish" action (status='active', is_active=1)
    - Implement form validation (name required, at least one field)
    - Implement auto-save on field configuration changes
    - Implement preview modal display
    - Update display_order when fields are reordered
    - _Requirements: 2.5, 2.6, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 15.1, 15.2, 15.3, 15.4, 15.5, 16.3, 16.4, 16.5, 17.2, 17.3, 17.4, 17.5_

  - [x] 5.4 Create assets/css/pages/form-builder.css
    - Style three-column layout with proper spacing
    - Style field types sidebar with draggable items
    - Style form canvas with drop zones and empty state
    - Style form field items with drag handles and action buttons
    - Style field configuration panel
    - Style options editor with add/remove buttons
    - Style action buttons (Preview, Save as Draft, Publish)
    - Ensure responsive layout (stack columns on mobile)
    - _Requirements: 2.1, 2.4, 3.1, 15.1, 15.3_

  - [ ]* 5.5 Write property test for form duplication completeness
    - **Property 13: Form Duplication Completeness**
    - **Validates: Requirements 7.1, 7.4**
    - Test that for any form with N fields, duplication creates new form with N fields having identical properties

  - [ ]* 5.6 Write property test for duplicate form naming
    - **Property 14: Duplicate Form Naming**
    - **Validates: Requirements 7.2**
    - Test that for any form with name "X", duplicate has name "X (Copy)"

  - [ ]* 5.7 Write property test for duplicate form status
    - **Property 15: Duplicate Form Status**
    - **Validates: Requirements 7.3**
    - Test that for any duplicated form, new form has status='draft' and is_active=0

  - [ ]* 5.8 Write property test for duplicate form identity
    - **Property 16: Duplicate Form Identity**
    - **Validates: Requirements 7.5**
    - Test that for any duplicated form, new form has unique ID different from original

  - [ ]* 5.9 Write property test for round-trip serialization
    - **Property 17: Form Template Round-Trip Serialization**
    - **Validates: Requirements 20.1, 20.2, 20.3, 20.4**
    - Test that for any valid form, saving then loading produces equivalent structure

- [ ] 6. Checkpoint - Verify form library and builder functionality
  - Test form creation with various field types
  - Test form editing and field reordering
  - Test form duplication
  - Test form archival and deletion
  - Ensure all tests pass, ask the user if questions arise

- [-] 7. Upload page integration
  - [x] 7.1 Modify pages/upload.php controller to load active form
    - Query form_templates WHERE is_active = 1
    - If active form exists, query form_fields WHERE form_id = ? ORDER BY display_order
    - Pass active form and fields to view
    - If editing, load existing custom_metadata_values for the file
    - _Requirements: 5.4, 8.1, 8.2_

  - [x] 7.2 Modify pages/upload.php upload action to save form values
    - After inserting into newspapers table, get file_id
    - For each form field, insert into custom_metadata_values with file_id, form_id, field_id, field_value
    - Handle checkbox fields by JSON encoding array values
    - Use transaction to ensure atomicity
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 7.3 Modify views/upload.php to render empty state
    - If no active form exists, display empty state container
    - Show inbox icon, "No Metadata Fields Defined" heading
    - Show descriptive message about customizing metadata structure
    - Show "Configure Metadata Fields" button linking to form-library.php
    - Keep thumbnail upload section visible
    - Hide General_Information_Section fields
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [ ] 7.4 Modify views/upload.php to render dynamic form fields
    - If active form exists, render form fields in display_order
    - For each field, render appropriate input control based on field_type
    - For text fields: render input[type=text]
    - For textarea fields: render textarea
    - For number fields: render input[type=number]
    - For date fields: render input[type=date]
    - For select fields: render select with options from field_options JSON
    - For checkbox fields: render multiple checkboxes with options from field_options JSON
    - For radio fields: render radio buttons with options from field_options JSON
    - For required fields, add required attribute and display asterisk indicator
    - For fields with help_text, display help text below label
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

  - [ ] 7.5 Modify assets/js/pages/upload.js to validate form fields
    - Add validation for custom form fields with data-required="true"
    - On form submit, check all required fields are filled
    - Display field-specific error messages near invalid fields
    - Prevent form submission if any required field is empty
    - Allow submission when all required fields are filled
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ]* 7.6 Write property test for form field rendering completeness
    - **Property 3: Form Field Rendering Completeness**
    - **Validates: Requirements 8.1, 8.2**
    - Test that for any active form with N fields, upload page renders exactly N input controls in display_order

  - [ ]* 7.7 Write property test for field type rendering correctness
    - **Property 4: Field Type Rendering Correctness**
    - **Validates: Requirements 8.3**
    - Test that for any field with field_type T, rendered HTML contains correct input element type

  - [ ]* 7.8 Write property test for required field indicator display
    - **Property 5: Required Field Indicator Display**
    - **Validates: Requirements 8.4**
    - Test that for any field where is_required=1, rendered HTML includes visual required indicator

  - [ ]* 7.9 Write property test for help text display
    - **Property 6: Help Text Display**
    - **Validates: Requirements 8.5**
    - Test that for any field where help_text is not NULL, rendered HTML includes help text content

  - [ ]* 7.10 Write property test for field options rendering
    - **Property 7: Field Options Rendering**
    - **Validates: Requirements 8.6**
    - Test that for any field with type select/checkbox/radio and N options, rendered HTML includes exactly N option elements

  - [ ]* 7.11 Write property test for required field validation
    - **Property 8: Required Field Validation**
    - **Validates: Requirements 9.1, 9.2, 9.3**
    - Test that for any form submission with at least one empty required field, system prevents submission and displays errors

  - [ ]* 7.12 Write property test for valid form submission
    - **Property 9: Valid Form Submission**
    - **Validates: Requirements 9.4**
    - Test that for any form submission where all required fields contain values, system allows submission

  - [ ]* 7.13 Write property test for field value persistence
    - **Property 10: Field Value Persistence**
    - **Validates: Requirements 10.1, 10.2**
    - Test that for any submitted form with N fields, system inserts exactly N rows into custom_metadata_values

  - [ ]* 7.14 Write property test for form association preservation
    - **Property 11: Form Association Preservation**
    - **Validates: Requirements 10.3, 10.5**
    - Test that for any saved field value, form_id matches active form at submission time and remains unchanged

  - [ ]* 7.15 Write property test for metadata retrieval
    - **Property 12: Metadata Retrieval**
    - **Validates: Requirements 10.4**
    - Test that for any archive with saved metadata, querying by file_id returns all field values with form_id and field_id

- [ ] 8. Checkpoint - Verify upload page integration
  - Test empty state display when no active form
  - Test dynamic form rendering with various field types
  - Test required field validation
  - Test form submission and metadata storage
  - Ensure all tests pass, ask the user if questions arise

- [ ] 9. Preview modal implementation
  - [ ] 9.1 Add preview modal HTML to views/form-library.php and views/form-builder.php
    - Create Bootstrap modal with form preview container
    - Include modal header with form name
    - Include modal body with form fields preview area
    - Include modal footer with close button
    - _Requirements: 6.1_

  - [ ] 9.2 Implement preview modal rendering in assets/js/pages/form-library.js
    - On preview button click, fetch form template and fields via API
    - Render all form fields in display_order
    - Show required field indicators (asterisks)
    - Show help text for fields
    - Disable all input controls (readonly/disabled)
    - Prevent form submission
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ] 9.3 Implement preview modal rendering in assets/js/pages/form-builder.js
    - On preview button click, use current form state (not saved version)
    - Render all form fields in current display_order
    - Show required field indicators
    - Show help text for fields
    - Disable all input controls
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ]* 9.4 Write unit tests for preview modal
    - Test preview modal displays all fields in correct order
    - Test preview modal shows required indicators
    - Test preview modal shows help text
    - Test preview modal prevents data entry
    - _Requirements: 6.2, 6.3, 6.4, 6.5_

- [ ] 10. Dashboard integration for viewing metadata
  - [ ] 10.1 Verify dashboard displays custom metadata values correctly
    - Ensure existing dashboard code queries custom_metadata_values with form_id
    - Verify field values display correctly for archives with form templates
    - Test backward compatibility with migrated data
    - _Requirements: 10.4, 12.6_

  - [ ]* 10.2 Write unit tests for dashboard metadata display
    - Test dashboard displays metadata for archives with form templates
    - Test dashboard displays metadata for migrated archives
    - Test dashboard handles missing form_id gracefully
    - _Requirements: 10.4, 12.6_

- [ ] 11. Form archival and deletion implementation
  - [ ] 11.1 Implement form archival logic in backend/api/form-templates.php
    - Set form status to 'archived'
    - Set is_active to 0
    - Preserve all field values associated with archived form
    - If active form is archived, trigger empty state on upload page
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

  - [ ] 11.2 Implement form deletion logic in backend/api/form-templates.php
    - Check if form has associated field values
    - If values exist, require confirmation parameter
    - Delete form template (CASCADE deletes form_fields)
    - Preserve field values in custom_metadata_values (form_id set to NULL via SET NULL constraint)
    - If active form is deleted, trigger empty state on upload page
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

  - [ ]* 11.3 Write unit tests for form archival
    - Test archived form changes status to 'archived'
    - Test archived form sets is_active to 0
    - Test archived form preserves field values
    - Test archiving active form triggers empty state
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

  - [ ]* 11.4 Write unit tests for form deletion
    - Test deletion shows confirmation when form has values
    - Test deletion removes form template and fields
    - Test deletion preserves field values
    - Test deleting active form triggers empty state
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

- [ ] 12. Final integration and testing
  - [ ] 12.1 Run all property-based tests with Eris library
    - Configure Eris with minimum 100 iterations per property
    - Tag each test with property number and requirements
    - Verify all 20 properties pass
    - _Requirements: All_

  - [ ] 12.2 Run all unit tests
    - Verify all unit tests pass
    - Check code coverage for critical paths
    - _Requirements: All_

  - [ ] 12.3 Perform end-to-end testing
    - Test complete workflow: create form → publish → upload with form → view metadata
    - Test form duplication workflow
    - Test form archival and deletion workflows
    - Test migration with sample data
    - Test empty state display and navigation
    - _Requirements: All_

  - [ ] 12.4 Test backward compatibility
    - Run migration on copy of production database
    - Verify all existing metadata displays correctly
    - Verify default form template created correctly
    - Verify no data loss during migration
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

- [ ] 13. Final checkpoint - Complete implementation
  - Verify all features working as specified
  - Verify all property-based tests passing
  - Verify all unit tests passing
  - Verify migration tested on production-like data
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties using Eris library (minimum 100 iterations per property)
- Unit tests validate specific examples and edge cases
- Migration must be tested on copy of production data before deployment
- SortableJS library required for drag-and-drop functionality
- All API endpoints require admin/super_admin authentication
- Form templates use transaction-based operations to ensure data consistency
