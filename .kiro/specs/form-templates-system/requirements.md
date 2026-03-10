# Requirements Document

## Introduction

The Form Templates System replaces the current custom metadata field manager with a Google Forms-like form template builder. This system enables users to create, save, and manage complete form templates (collections of fields) rather than managing individual fields. The system provides a visual drag-and-drop interface for building forms, a library for managing multiple form templates, and dynamic rendering of active forms on the upload page.

## Glossary

- **Form_Template**: A named collection of form fields with metadata (title, description, status, active state)
- **Form_Builder**: The visual interface for creating and editing form templates
- **Form_Library**: The management interface displaying all form templates with filtering and actions
- **Form_Field**: An individual input element within a form template (text, textarea, number, date, select, checkbox, radio)
- **Active_Form**: The single form template currently displayed on the upload page
- **Upload_Page**: The page where users upload archives and enter metadata
- **General_Information_Section**: The section on the upload page that displays form fields
- **Empty_State**: The UI displayed when no form template is active
- **Field_Type**: The input type of a form field (Text, Textarea, Number, Date, Select, Checkbox, Radio)
- **Form_Status**: The state of a form template (Active, Draft, Archived)
- **Custom_Metadata_System**: The existing system for managing individual metadata fields

## Requirements

### Requirement 1: Empty State Display

**User Story:** As a user, I want to see clear guidance when no form template is active, so that I understand how to configure metadata fields.

#### Acceptance Criteria

1. WHEN no form template is active, THE Upload_Page SHALL display the message "No Metadata Fields Defined"
2. WHEN no form template is active, THE Upload_Page SHALL display the message "Please customize your metadata structure to start uploading archives. Define fields like Author, Date, and Keywords to keep your library organized."
3. WHEN no form template is active, THE Upload_Page SHALL display a "Configure Metadata Fields" button
4. WHEN the "Configure Metadata Fields" button is clicked, THE Upload_Page SHALL redirect to the Form_Library
5. WHEN no form template is active, THE Upload_Page SHALL keep the thumbnail upload section visible
6. WHEN no form template is active, THE Upload_Page SHALL hide all General_Information_Section fields

### Requirement 2: Form Template Creation

**User Story:** As an administrator, I want to create new form templates from scratch, so that I can define custom metadata structures for different archive types.

#### Acceptance Criteria

1. WHEN the "Create New Form" button is clicked, THE Form_Library SHALL open the Form_Builder
2. THE Form_Builder SHALL allow the user to enter a form title
3. THE Form_Builder SHALL allow the user to enter a form description
4. THE Form_Builder SHALL display a sidebar with available Field_Types (Text, Textarea, Number, Date, Select, Checkbox, Radio)
5. WHEN a Field_Type is dragged from the sidebar, THE Form_Builder SHALL add the field to the form canvas
6. THE Form_Builder SHALL allow the user to reorder fields by dragging
7. THE Form_Builder SHALL save the form as Draft or Active status
8. WHEN a form is saved, THE Form_Builder SHALL store the form template in the database

### Requirement 3: Form Field Configuration

**User Story:** As an administrator, I want to configure each form field's properties, so that I can control field behavior and validation.

#### Acceptance Criteria

1. WHEN a form field is selected, THE Form_Builder SHALL display a configuration panel
2. THE Form_Builder SHALL allow the user to edit the field label
3. THE Form_Builder SHALL allow the user to change the Field_Type
4. THE Form_Builder SHALL allow the user to toggle the required state
5. WHERE the Field_Type is Select, Checkbox, or Radio, THE Form_Builder SHALL allow the user to define options
6. THE Form_Builder SHALL allow the user to enter help text for the field
7. WHEN field configuration is changed, THE Form_Builder SHALL update the field preview immediately

### Requirement 4: Form Template Management

**User Story:** As an administrator, I want to manage all form templates in one place, so that I can organize and maintain multiple metadata structures.

#### Acceptance Criteria

1. THE Form_Library SHALL display all form templates as cards
2. FOR EACH form template card, THE Form_Library SHALL display the form title, description, status badge, field count, and last modified date
3. THE Form_Library SHALL provide filter tabs (All Forms, Active, Drafts, Archived)
4. WHEN a filter tab is selected, THE Form_Library SHALL display only form templates matching that status
5. THE Form_Library SHALL provide a search input to filter forms by name
6. WHEN search text is entered, THE Form_Library SHALL display only form templates matching the search query
7. FOR EACH form template, THE Form_Library SHALL provide actions: Edit, Preview, Duplicate, Set as Active, Archive, Delete

### Requirement 5: Active Form Management

**User Story:** As an administrator, I want to set one form template as active, so that it appears on the upload page for metadata entry.

#### Acceptance Criteria

1. THE Form_Library SHALL allow only one form template to be active at any time
2. WHEN a form template is set as active, THE Form_Library SHALL deactivate any previously active form template
3. WHEN a form template is set as active, THE Form_Library SHALL update the form status to Active
4. THE Upload_Page SHALL display only the fields from the currently active form template
5. WHEN no form template is active, THE Upload_Page SHALL display the empty state

### Requirement 6: Form Preview

**User Story:** As an administrator, I want to preview how a form will look before activating it, so that I can verify the user experience.

#### Acceptance Criteria

1. WHEN the Preview action is clicked, THE Form_Library SHALL display a modal showing the form as it will appear on the Upload_Page
2. THE preview modal SHALL render all form fields in display order
3. THE preview modal SHALL show required field indicators
4. THE preview modal SHALL show help text for fields
5. THE preview modal SHALL not allow data entry or submission

### Requirement 7: Form Duplication

**User Story:** As an administrator, I want to duplicate existing form templates, so that I can create variations without starting from scratch.

#### Acceptance Criteria

1. WHEN the Duplicate action is clicked, THE Form_Library SHALL create a copy of the form template
2. THE duplicated form SHALL have the title "[Original Title] (Copy)"
3. THE duplicated form SHALL have Draft status
4. THE duplicated form SHALL copy all form fields with their configurations
5. THE duplicated form SHALL have a new unique identifier

### Requirement 8: Dynamic Form Rendering

**User Story:** As a user, I want to see the active form's fields on the upload page, so that I can enter metadata according to the current template.

#### Acceptance Criteria

1. WHEN an Active_Form exists, THE Upload_Page SHALL display all fields from the form template in the General_Information_Section
2. THE Upload_Page SHALL render fields in the display order defined in the form template
3. FOR EACH field, THE Upload_Page SHALL render the appropriate input control based on Field_Type
4. FOR EACH required field, THE Upload_Page SHALL display a required indicator
5. WHERE help text exists, THE Upload_Page SHALL display the help text near the field
6. WHEN a Select, Checkbox, or Radio field is rendered, THE Upload_Page SHALL display the options defined in the form template

### Requirement 9: Form Validation

**User Story:** As a user, I want the system to validate required fields before submission, so that I provide complete metadata.

#### Acceptance Criteria

1. WHEN the upload form is submitted, THE Upload_Page SHALL validate all required fields
2. IF a required field is empty, THEN THE Upload_Page SHALL display an error message for that field
3. IF any required field is empty, THEN THE Upload_Page SHALL prevent form submission
4. WHEN all required fields are filled, THE Upload_Page SHALL allow form submission
5. THE Upload_Page SHALL display field-specific validation messages near the invalid field

### Requirement 10: Form Field Value Storage

**User Story:** As a user, I want my metadata entries to be saved with the archive, so that I can retrieve and search by metadata later.

#### Acceptance Criteria

1. WHEN the upload form is submitted, THE Upload_Page SHALL save all field values to the database
2. FOR EACH field value, THE Upload_Page SHALL store the form_id, field_id, archive_id, and field_value
3. THE Upload_Page SHALL associate field values with the specific form template used at submission time
4. WHEN an archive is viewed, THE System SHALL retrieve field values using the form_id and archive_id
5. THE System SHALL preserve field values even if the form template is later modified or archived

### Requirement 11: Database Schema for Form Templates

**User Story:** As a developer, I want a database schema that supports form templates, so that the system can store and retrieve form definitions.

#### Acceptance Criteria

1. THE System SHALL create a form_templates table with columns: id, name, description, status, is_active, created_at, updated_at
2. THE System SHALL ensure only one form template has is_active set to true at any time
3. THE System SHALL create a form_fields table with columns: id, form_id, field_label, field_type, field_options, is_required, display_order, help_text
4. THE form_fields table SHALL reference form_templates via foreign key on form_id
5. THE System SHALL modify the custom_metadata_values table to include form_id
6. THE custom_metadata_values table SHALL reference form_templates via foreign key on form_id

### Requirement 12: Migration from Custom Metadata System

**User Story:** As a developer, I want to migrate existing custom metadata fields to a default form template, so that existing data remains accessible.

#### Acceptance Criteria

1. WHEN the migration runs, THE System SHALL create a default form template named "Default Metadata Form"
2. THE System SHALL set the default form template status to Active
3. FOR EACH existing custom_metadata_field, THE System SHALL create a corresponding form_field in the default form template
4. THE System SHALL preserve field properties (label, type, required state, options, display order)
5. THE System SHALL update all existing custom_metadata_values to reference the default form template
6. WHEN the migration completes, THE System SHALL maintain backward compatibility with existing archive metadata

### Requirement 13: Form Template Archival

**User Story:** As an administrator, I want to archive unused form templates, so that I can keep the Form_Library organized without losing historical data.

#### Acceptance Criteria

1. WHEN the Archive action is clicked, THE Form_Library SHALL change the form status to Archived
2. THE Form_Library SHALL not display archived forms in the Active or Drafts filter tabs
3. WHEN the Archived filter tab is selected, THE Form_Library SHALL display only archived form templates
4. THE System SHALL preserve all field values associated with archived form templates
5. IF an active form is archived, THEN THE System SHALL set is_active to false and display the empty state on the Upload_Page

### Requirement 14: Form Template Deletion

**User Story:** As an administrator, I want to delete form templates that are no longer needed, so that I can remove obsolete metadata structures.

#### Acceptance Criteria

1. WHEN the Delete action is clicked, THE Form_Library SHALL display a confirmation dialog
2. THE confirmation dialog SHALL warn if the form template has associated field values
3. WHEN deletion is confirmed, THE System SHALL delete the form template and all associated form fields
4. THE System SHALL preserve field values associated with the deleted form template for historical records
5. IF the active form is deleted, THEN THE System SHALL set is_active to false and display the empty state on the Upload_Page

### Requirement 15: Form Builder Drag-and-Drop

**User Story:** As an administrator, I want to use drag-and-drop to build forms, so that I can quickly create and organize form fields.

#### Acceptance Criteria

1. WHEN a Field_Type is dragged from the sidebar, THE Form_Builder SHALL show a drop indicator on the form canvas
2. WHEN a Field_Type is dropped on the form canvas, THE Form_Builder SHALL insert the field at the drop position
3. WHEN a form field is dragged within the canvas, THE Form_Builder SHALL show a drop indicator between existing fields
4. WHEN a form field is dropped at a new position, THE Form_Builder SHALL update the display_order for all affected fields
5. THE Form_Builder SHALL use a drag-and-drop library (SortableJS or similar) for smooth interactions

### Requirement 16: Form Builder Save and Publish

**User Story:** As an administrator, I want to save forms as drafts or publish them as active, so that I can work on forms incrementally before making them available.

#### Acceptance Criteria

1. THE Form_Builder SHALL provide a "Save as Draft" button
2. THE Form_Builder SHALL provide a "Publish" button
3. WHEN "Save as Draft" is clicked, THE Form_Builder SHALL save the form with Draft status and is_active set to false
4. WHEN "Publish" is clicked, THE Form_Builder SHALL save the form with Active status and is_active set to true
5. WHEN "Publish" is clicked and another form is active, THE Form_Builder SHALL deactivate the other form before activating the new form
6. WHEN a form is saved, THE Form_Builder SHALL display a success message and redirect to the Form_Library

### Requirement 17: Form Field Options Configuration

**User Story:** As an administrator, I want to define options for select, checkbox, and radio fields, so that users can choose from predefined values.

#### Acceptance Criteria

1. WHERE the Field_Type is Select, Checkbox, or Radio, THE Form_Builder SHALL display an options editor
2. THE options editor SHALL allow the user to add new options
3. THE options editor SHALL allow the user to edit existing option labels
4. THE options editor SHALL allow the user to delete options
5. THE options editor SHALL allow the user to reorder options by dragging
6. WHEN options are saved, THE Form_Builder SHALL store them as JSON in the field_options column

### Requirement 18: Form Search Functionality

**User Story:** As an administrator, I want to search for form templates by name, so that I can quickly find specific forms in a large library.

#### Acceptance Criteria

1. THE Form_Library SHALL provide a search input field
2. WHEN text is entered in the search input, THE Form_Library SHALL filter form templates in real-time
3. THE search SHALL match against form template names (case-insensitive)
4. THE search SHALL match against form template descriptions (case-insensitive)
5. WHEN the search input is cleared, THE Form_Library SHALL display all form templates matching the current filter tab

### Requirement 19: Form Template Edit

**User Story:** As an administrator, I want to edit existing form templates, so that I can update metadata structures as requirements change.

#### Acceptance Criteria

1. WHEN the Edit action is clicked, THE Form_Library SHALL open the Form_Builder with the selected form template loaded
2. THE Form_Builder SHALL display all existing form fields in their current order
3. THE Form_Builder SHALL allow the user to add, remove, or modify fields
4. WHEN changes are saved, THE Form_Builder SHALL update the form template in the database
5. THE Form_Builder SHALL update the updated_at timestamp when changes are saved

### Requirement 20: Round-Trip Form Serialization

**User Story:** As a developer, I want form templates to serialize and deserialize correctly, so that form definitions are preserved accurately in the database.

#### Acceptance Criteria

1. WHEN a form template is saved, THE System SHALL serialize the form definition to JSON
2. WHEN a form template is loaded, THE System SHALL deserialize the JSON to reconstruct the form definition
3. FOR ALL valid form templates, THE System SHALL ensure that saving then loading produces an equivalent form structure
4. THE System SHALL preserve field order, field properties, and field options through the round-trip process
5. IF deserialization fails, THEN THE System SHALL log an error and display a user-friendly error message
