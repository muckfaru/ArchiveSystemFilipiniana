# Requirements Document: Custom Metadata Form Builder System

## Introduction

The Custom Metadata Form Builder System extends the existing archive upload functionality to support dynamic, administrator-defined metadata fields. This system enables administrators to create custom form fields (similar to Google Forms), display them on the upload form, store their values, and build an advanced drag-and-drop form builder interface. The implementation is divided into six phases to ensure backward compatibility and minimize disruption to existing upload workflows.

## Glossary

- **Upload_Form**: The existing archive upload page where users enter metadata and upload files
- **Progress_Bar**: A visual indicator showing completion percentage of required form fields
- **Custom_Field**: An administrator-defined metadata field with configurable properties (type, label, validation)
- **Core_Field**: Pre-existing required fields (title, publisher, date, category, language)
- **Field_Manager**: Administrative interface for creating and managing custom metadata fields
- **Form_Builder**: Advanced drag-and-drop interface for designing custom field layouts
- **Custom_Metadata_Value**: User-entered data for a custom field associated with a specific file
- **Field_Definition**: Database record defining a custom field's properties and behavior
- **Validation_Rule**: Constraint applied to custom field inputs (required, pattern, range)
- **Dashboard**: File browsing interface displaying uploaded archives
- **Browse_Page**: Public-facing archive listing page
- **Reader_Page**: File detail view showing metadata and content
- **Bulk_Upload**: Multi-file upload mode where users upload multiple files simultaneously
- **Transaction**: Database operation ensuring data integrity across multiple related changes

## Requirements

### Requirement 1: Progress Bar on Upload Form

**User Story:** As a user uploading an archive, I want to see a progress bar showing form completion, so that I know how much of the form remains to be filled.

#### Acceptance Criteria

1. WHEN the Upload_Form loads, THE Progress_Bar SHALL display at the top of the form
2. THE Progress_Bar SHALL calculate completion percentage based on filled required Core_Fields
3. WHEN a user fills a required field, THE Progress_Bar SHALL update in real-time without page refresh
4. WHEN a user clears a required field, THE Progress_Bar SHALL decrease the completion percentage
5. THE Progress_Bar SHALL display 100% completion WHEN all required Core_Fields contain valid values
6. THE Progress_Bar SHALL use visual color coding (red for 0-33%, yellow for 34-66%, green for 67-100%)
7. THE Progress_Bar SHALL display the exact percentage as text alongside the visual bar
8. WHILE in Bulk_Upload mode, THE Progress_Bar SHALL calculate completion for the currently active file tab
9. THE Progress_Bar SHALL NOT prevent form submission regardless of completion percentage
10. THE Progress_Bar SHALL persist across page refreshes in edit mode by reading existing field values

### Requirement 2: Database Schema for Custom Metadata Fields

**User Story:** As a system administrator, I want custom metadata field definitions stored in the database, so that field configurations persist and can be managed programmatically.

#### Acceptance Criteria

1. THE System SHALL create a custom_metadata_fields table with columns: id, field_name, field_label, field_type, field_options, is_required, is_enabled, display_order, validation_rules, created_at, updated_at
2. THE System SHALL create a custom_metadata_values table with columns: id, file_id, field_id, field_value, created_at, updated_at
3. THE custom_metadata_fields table SHALL support field types: text, textarea, number, date, select, checkbox, radio
4. THE custom_metadata_values table SHALL reference newspapers.id via file_id foreign key
5. THE custom_metadata_values table SHALL reference custom_metadata_fields.id via field_id foreign key
6. WHEN a custom field is deleted, THE System SHALL set the foreign key reference to NULL in custom_metadata_values
7. THE System SHALL provide a migration script that creates both tables without data loss
8. THE migration script SHALL execute successfully on existing database installations
9. THE System SHALL create database indexes on file_id and field_id columns for query performance
10. THE field_options column SHALL store JSON-encoded data for select, checkbox, and radio field types

### Requirement 3: Custom Field Manager Interface

**User Story:** As an administrator, I want a dedicated page to create and manage custom metadata fields, so that I can configure the upload form without modifying code.

#### Acceptance Criteria

1. THE System SHALL provide a metadata-fields.php page accessible from the admin sidebar
2. THE Field_Manager SHALL display a table listing all existing Custom_Fields with columns: label, type, required status, enabled status, actions
3. WHEN an administrator clicks "Add Field", THE Field_Manager SHALL display a modal form with inputs for: field_label, field_type, is_required, field_options
4. WHEN an administrator submits the add field form with valid data, THE System SHALL insert a new Field_Definition into custom_metadata_fields table
5. WHEN an administrator clicks "Edit" on a field, THE Field_Manager SHALL populate the modal form with existing field data
6. WHEN an administrator submits the edit form, THE System SHALL update the Field_Definition in the database
7. WHEN an administrator clicks "Delete" on a field, THE System SHALL display a confirmation modal
8. WHEN deletion is confirmed, THE System SHALL soft-delete the field by setting is_enabled to 0
9. THE Field_Manager SHALL provide a toggle switch to enable/disable fields without deletion
10. WHEN a field is disabled, THE Upload_Form SHALL NOT display that field
11. THE Field_Manager SHALL validate that field_name contains only alphanumeric characters and underscores
12. THE Field_Manager SHALL prevent duplicate field_name values
13. WHEN field_type is "select", "checkbox", or "radio", THE Field_Manager SHALL require field_options to be provided
14. THE Field_Manager SHALL display field_options as a comma-separated input that converts to JSON on save
15. THE Field_Manager SHALL display validation errors inline without page refresh

### Requirement 4: Display Custom Fields on Upload Form

**User Story:** As a user uploading an archive, I want to see and fill custom metadata fields on the upload form, so that I can provide additional information requested by administrators.

#### Acceptance Criteria

1. WHEN the Upload_Form loads, THE System SHALL query custom_metadata_fields table for enabled fields ordered by display_order
2. THE Upload_Form SHALL render each enabled Custom_Field below the Core_Fields section
3. WHEN field_type is "text", THE Upload_Form SHALL render an input element with type="text"
4. WHEN field_type is "textarea", THE Upload_Form SHALL render a textarea element
5. WHEN field_type is "number", THE Upload_Form SHALL render an input element with type="number"
6. WHEN field_type is "date", THE Upload_Form SHALL render an input element with type="date"
7. WHEN field_type is "select", THE Upload_Form SHALL render a select dropdown with options from field_options JSON
8. WHEN field_type is "checkbox", THE Upload_Form SHALL render checkbox inputs for each option in field_options JSON
9. WHEN field_type is "radio", THE Upload_Form SHALL render radio inputs for each option in field_options JSON
10. WHEN is_required is true, THE Upload_Form SHALL add the "required" attribute to the field
11. WHEN a user submits the upload form, THE System SHALL validate all required Custom_Fields
12. WHEN validation fails, THE System SHALL display error messages and prevent form submission
13. WHEN validation succeeds, THE System SHALL insert Custom_Metadata_Values into custom_metadata_values table
14. THE System SHALL insert one row per Custom_Field with the file_id, field_id, and field_value
15. WHEN field_type is "checkbox", THE System SHALL store selected values as JSON array in field_value
16. THE Progress_Bar SHALL include enabled required Custom_Fields in completion calculation
17. WHILE in Bulk_Upload mode, THE System SHALL save Custom_Metadata_Values for each uploaded file
18. WHEN editing an existing file, THE Upload_Form SHALL pre-populate Custom_Fields with existing Custom_Metadata_Values
19. WHEN a user updates custom field values during edit, THE System SHALL update existing Custom_Metadata_Values rows
20. THE System SHALL use database Transactions to ensure Core_Fields and Custom_Metadata_Values save atomically

### Requirement 5: Display Custom Metadata on Dashboard and Browse Pages

**User Story:** As a user browsing archives, I want to see custom metadata values in file cards and detail views, so that I can access the additional information provided during upload.

#### Acceptance Criteria

1. WHEN the Dashboard loads, THE System SHALL query custom_metadata_values for each displayed file
2. THE Dashboard file cards SHALL display up to 3 Custom_Metadata_Values below the Core_Fields
3. WHEN a file has more than 3 Custom_Metadata_Values, THE Dashboard SHALL display "View More" link
4. WHEN the Browse_Page loads, THE System SHALL query custom_metadata_values for each displayed file
5. THE Browse_Page file cards SHALL display Custom_Metadata_Values using the same layout as Dashboard
6. WHEN the Reader_Page loads for a file, THE System SHALL query all Custom_Metadata_Values for that file
7. THE Reader_Page SHALL display all Custom_Metadata_Values in the file information panel
8. THE Reader_Page SHALL display Custom_Field labels alongside their values
9. WHEN a Custom_Field has no value for a file, THE System SHALL NOT display that field on the Reader_Page
10. THE System SHALL format Custom_Metadata_Values based on field_type (dates formatted as locale strings, checkboxes as comma-separated lists)
11. WHEN searching files, THE System SHALL include Custom_Metadata_Values in the search query
12. THE search results SHALL highlight matching Custom_Metadata_Values in the result snippets

### Requirement 6: Advanced Form Builder with Drag-and-Drop

**User Story:** As an administrator, I want a visual drag-and-drop form builder, so that I can design custom field layouts intuitively without technical knowledge.

#### Acceptance Criteria

1. THE Field_Manager SHALL provide a "Form Builder" mode toggle that switches from table view to visual builder
2. WHEN Form Builder mode is active, THE System SHALL display a canvas area representing the Upload_Form layout
3. THE Form_Builder SHALL display a sidebar with draggable field type buttons (text, textarea, number, date, select, checkbox, radio)
4. WHEN an administrator drags a field type onto the canvas, THE System SHALL create a new field placeholder
5. THE field placeholder SHALL display inline editing controls for field_label, is_required, and field_options
6. WHEN an administrator clicks on a field placeholder, THE System SHALL display a properties panel with advanced options
7. THE properties panel SHALL include inputs for: validation_rules (regex pattern, min/max length, min/max value)
8. WHEN an administrator drags a field to reorder it, THE System SHALL update display_order values
9. WHEN an administrator saves the form layout, THE System SHALL persist all Field_Definitions to the database
10. THE Form_Builder SHALL provide a preview mode that renders the form exactly as users will see it
11. THE Form_Builder SHALL validate that field_name is unique before saving
12. THE Form_Builder SHALL prevent deletion of fields that have existing Custom_Metadata_Values without confirmation
13. WHEN deleting a field with existing values, THE System SHALL display a warning showing the count of affected files
14. THE Form_Builder SHALL support undo/redo functionality for layout changes
15. THE Form_Builder SHALL auto-save draft changes every 30 seconds to prevent data loss
16. THE Form_Builder SHALL display a visual indicator when unsaved changes exist
17. WHEN an administrator adds validation_rules, THE Upload_Form SHALL enforce those rules client-side and server-side
18. THE Form_Builder SHALL support field grouping with collapsible sections
19. WHEN a field is marked as required, THE Form_Builder SHALL display a red asterisk in the preview
20. THE Form_Builder SHALL provide a "Duplicate Field" action to copy existing field configurations

### Requirement 7: Backward Compatibility and Data Integrity

**User Story:** As a system administrator, I want the custom metadata system to work seamlessly with existing functionality, so that current users experience no disruption.

#### Acceptance Criteria

1. THE Upload_Form SHALL function identically to the current implementation WHEN no Custom_Fields are enabled
2. THE System SHALL allow file uploads with only Core_Fields filled WHEN no Custom_Fields are required
3. WHEN the custom_metadata_fields table is empty, THE Upload_Form SHALL NOT display any custom field sections
4. THE Progress_Bar SHALL calculate correctly based on Core_Fields alone WHEN no Custom_Fields exist
5. THE Bulk_Upload functionality SHALL continue to work without modification WHEN Custom_Fields are added
6. WHEN a Transaction fails during upload, THE System SHALL rollback both Core_Fields and Custom_Metadata_Values
7. THE System SHALL NOT break existing file records WHEN custom metadata tables are added
8. WHEN querying files without Custom_Metadata_Values, THE Dashboard and Browse_Page SHALL display normally
9. THE Reader_Page SHALL display Core_Fields normally WHEN a file has no Custom_Metadata_Values
10. THE System SHALL maintain existing file upload validation rules (file type, size, duplicates)
11. THE existing upload.js logic for bulk uploads SHALL remain functional without modification
12. WHEN editing a file uploaded before custom metadata was enabled, THE Upload_Form SHALL display Custom_Fields as empty optional fields
13. THE System SHALL handle NULL values in custom_metadata_values.field_value gracefully
14. THE migration script SHALL be idempotent (safe to run multiple times without errors)
15. THE System SHALL log all custom metadata operations to activity_logs table with action type "custom_metadata_update"

### Requirement 8: Validation and Error Handling

**User Story:** As a user, I want clear error messages when custom field validation fails, so that I can correct my inputs and successfully upload files.

#### Acceptance Criteria

1. WHEN a required Custom_Field is empty, THE System SHALL display "This field is required" below the field
2. WHEN a number field receives non-numeric input, THE System SHALL display "Please enter a valid number"
3. WHEN a date field receives an invalid date, THE System SHALL display "Please enter a valid date"
4. WHEN a field with regex validation_rules fails pattern matching, THE System SHALL display the custom error message from validation_rules
5. WHEN a text field exceeds max_length validation, THE System SHALL display "Maximum length is X characters"
6. WHEN a number field violates min_value or max_value validation, THE System SHALL display "Value must be between X and Y"
7. THE System SHALL validate Custom_Fields client-side before form submission
8. THE System SHALL validate Custom_Fields server-side as a security measure
9. WHEN server-side validation fails, THE System SHALL return JSON error response with field-specific messages
10. THE Upload_Form SHALL display server-side validation errors inline next to the corresponding fields
11. WHEN a database error occurs saving Custom_Metadata_Values, THE System SHALL rollback the entire Transaction
12. WHEN a Transaction rollback occurs, THE System SHALL delete any uploaded files from the filesystem
13. THE System SHALL log validation errors to the PHP error log with context (user_id, file_name, field_name)
14. WHEN the Field_Manager receives invalid field configuration, THE System SHALL display validation errors without saving
15. THE System SHALL prevent SQL injection by using prepared statements for all custom metadata queries

### Requirement 9: Performance and Scalability

**User Story:** As a system administrator, I want the custom metadata system to perform efficiently, so that upload times and page load speeds remain acceptable as the archive grows.

#### Acceptance Criteria

1. THE System SHALL load Custom_Field definitions once per page load and cache them in memory
2. THE Dashboard SHALL query Custom_Metadata_Values using a single JOIN query per page, not per file
3. THE System SHALL use database indexes on custom_metadata_values (file_id, field_id) for query optimization
4. WHEN displaying 50 files on Dashboard, THE System SHALL complete the page render in under 2 seconds
5. THE Upload_Form SHALL render Custom_Fields in under 500ms after Core_Fields are displayed
6. THE Progress_Bar SHALL update in under 100ms after a field value changes
7. THE Form_Builder SHALL support up to 50 Custom_Fields without performance degradation
8. WHEN saving Custom_Metadata_Values for Bulk_Upload, THE System SHALL use batch INSERT statements
9. THE System SHALL limit Custom_Field queries to only enabled fields (is_enabled = 1)
10. THE Field_Manager SHALL paginate the field list WHEN more than 100 Custom_Fields exist

### Requirement 10: Security and Access Control

**User Story:** As a system administrator, I want custom metadata management restricted to authorized users, so that unauthorized users cannot modify form configurations.

#### Acceptance Criteria

1. THE Field_Manager page SHALL require authentication and redirect unauthenticated users to login
2. THE Field_Manager page SHALL be accessible only to users with role "super_admin" or "admin"
3. WHEN a non-admin user attempts to access Field_Manager, THE System SHALL display "Access Denied" error
4. THE System SHALL validate user permissions server-side before processing Field_Manager actions
5. THE System SHALL sanitize all Custom_Field inputs to prevent XSS attacks
6. THE System SHALL escape Custom_Metadata_Values when displaying them on Dashboard, Browse_Page, and Reader_Page
7. THE System SHALL use prepared statements for all database queries involving Custom_Fields and Custom_Metadata_Values
8. THE System SHALL validate field_type against an allowed list before saving Field_Definitions
9. THE System SHALL validate field_options JSON structure before saving to prevent malformed data
10. THE System SHALL log all Field_Manager actions to activity_logs table with user_id and action details
11. WHEN a Custom_Field is deleted, THE System SHALL record the deleting user in activity_logs
12. THE System SHALL rate-limit Field_Manager API endpoints to prevent abuse (max 60 requests per minute per user)
13. THE System SHALL validate file_id in Custom_Metadata_Values to ensure it references an existing, non-deleted file
14. THE System SHALL prevent users from viewing Custom_Metadata_Values for files they don't have permission to access
15. THE Form_Builder SHALL implement CSRF protection for all form submissions

## Correctness Properties

### Property 1: Progress Bar Accuracy (Invariant)

FOR ALL states of the Upload_Form, the Progress_Bar percentage SHALL equal (count of filled required fields / total count of required fields) * 100, rounded to nearest integer.

### Property 2: Custom Metadata Atomicity (Transaction Property)

FOR ALL file uploads, IF the newspapers table INSERT succeeds AND custom_metadata_values INSERT fails, THEN the newspapers row SHALL be rolled back AND the uploaded file SHALL be deleted from filesystem.

### Property 3: Field Definition Uniqueness (Invariant)

FOR ALL rows in custom_metadata_fields table, the field_name value SHALL be unique among rows WHERE is_enabled = 1.

### Property 4: Metadata Value Referential Integrity (Invariant)

FOR ALL rows in custom_metadata_values table, the file_id SHALL reference an existing newspapers.id WHERE deleted_at IS NULL.

### Property 5: Required Field Validation (Metamorphic Property)

FOR ALL Custom_Fields WHERE is_required = 1, IF Upload_Form submission occurs WITH that field empty, THEN validation SHALL fail AND no database INSERT SHALL occur.

### Property 6: Field Type Rendering Consistency (Model-Based Property)

FOR ALL Custom_Fields, the HTML element rendered on Upload_Form SHALL match the expected element type for field_type (text→input[type=text], textarea→textarea, select→select, etc.).

### Property 7: Bulk Upload Metadata Consistency (Invariant)

FOR ALL Bulk_Upload operations uploading N files, the System SHALL insert exactly N rows into newspapers table AND exactly N * (count of enabled Custom_Fields) rows into custom_metadata_values table.

### Property 8: Form Builder Preview Equivalence (Metamorphic Property)

FOR ALL Form_Builder configurations, the preview mode rendering SHALL produce identical HTML structure to the actual Upload_Form rendering.

### Property 9: Search Inclusion Property (Metamorphic Property)

FOR ALL files with Custom_Metadata_Values, IF a search query matches a Custom_Metadata_Value, THEN that file SHALL appear in search results.

### Property 10: Backward Compatibility Invariant (Idempotence)

FOR ALL existing file records created before custom metadata system deployment, the Dashboard, Browse_Page, and Reader_Page SHALL display identically to pre-deployment rendering.

### Property 11: Validation Rule Enforcement (Round-Trip Property)

FOR ALL Custom_Fields with validation_rules, IF client-side validation passes for input X, THEN server-side validation SHALL also pass for input X.

### Property 12: Field Deletion Safety (Error Condition)

FOR ALL Custom_Fields with existing Custom_Metadata_Values, IF deletion is attempted WITHOUT confirmation, THEN the System SHALL reject the deletion AND display warning message.

### Property 13: Migration Idempotence (Idempotence Property)

FOR ALL database states, running the migration script multiple times SHALL produce the same final schema AND SHALL NOT cause errors or data loss.

### Property 14: JSON Options Parsing (Round-Trip Property)

FOR ALL Custom_Fields with field_type IN ("select", "checkbox", "radio"), the field_options JSON SHALL parse successfully AND reconstruct the original option list when rendered.

### Property 15: Performance Bound Property (Timing Constraint)

FOR ALL Dashboard page loads displaying N files WHERE N <= 50, the total query time for Custom_Metadata_Values SHALL be less than 500ms.
