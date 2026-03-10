# Requirements Document

## Introduction

This document specifies requirements for the Metadata Display Customization feature, which enables administrators to control the visibility and display order of custom metadata fields in two distinct contexts: file cards (basic view) and preview modals (detailed view). The feature applies to both the admin dashboard (dashboard.php) and public page (public.php), providing consistent customization across the Archive System.

## Glossary

- **Display_Configuration_System**: The system component that manages metadata field display settings
- **File_Card**: The basic file view shown before a user clicks on a file, displaying limited metadata
- **Preview_Modal**: The detailed file view shown after a user clicks on a file, displaying full metadata
- **Custom_Metadata_Field**: A user-defined metadata field stored in the custom_metadata_fields table
- **Display_Order**: A numeric value determining the sequence in which fields appear
- **Visibility_Setting**: A boolean flag indicating whether a field should be displayed in a specific context
- **Admin_Dashboard**: The dashboard.php page where administrators manage files
- **Public_Page**: The public.php page where public users view files
- **Configuration_UI**: The administrative interface for managing display settings
- **Default_Display_Behavior**: The system behavior when no custom display settings are configured

## Requirements

### Requirement 1: Display Configuration Storage

**User Story:** As an administrator, I want the system to store my display preferences for each metadata field, so that my customizations persist across sessions.

#### Acceptance Criteria

1. THE Display_Configuration_System SHALL store visibility settings for File_Card display per Custom_Metadata_Field
2. THE Display_Configuration_System SHALL store visibility settings for Preview_Modal display per Custom_Metadata_Field
3. THE Display_Configuration_System SHALL store Display_Order values for File_Card display per Custom_Metadata_Field
4. THE Display_Configuration_System SHALL store Display_Order values for Preview_Modal display per Custom_Metadata_Field
5. WHEN no display configuration exists for a Custom_Metadata_Field, THE Display_Configuration_System SHALL apply Default_Display_Behavior
6. THE Display_Configuration_System SHALL maintain referential integrity with the custom_metadata_fields table

### Requirement 2: Configuration UI Access

**User Story:** As an administrator, I want to access a configuration interface, so that I can customize metadata display settings.

#### Acceptance Criteria

1. THE Configuration_UI SHALL be accessible only to authenticated administrators
2. THE Configuration_UI SHALL display all existing Custom_Metadata_Field entries
3. THE Configuration_UI SHALL display current visibility and Display_Order settings for each Custom_Metadata_Field
4. THE Configuration_UI SHALL provide controls to modify File_Card visibility per Custom_Metadata_Field
5. THE Configuration_UI SHALL provide controls to modify Preview_Modal visibility per Custom_Metadata_Field
6. THE Configuration_UI SHALL provide controls to modify Display_Order for File_Card per Custom_Metadata_Field
7. THE Configuration_UI SHALL provide controls to modify Display_Order for Preview_Modal per Custom_Metadata_Field

### Requirement 3: Display Order Management

**User Story:** As an administrator, I want to set the order in which metadata fields appear, so that the most important information is displayed first.

#### Acceptance Criteria

1. WHEN an administrator assigns a Display_Order value, THE Configuration_UI SHALL accept positive integer values
2. WHEN multiple Custom_Metadata_Field entries have the same Display_Order value, THE Display_Configuration_System SHALL sort them by field_name alphabetically
3. THE Display_Configuration_System SHALL allow independent Display_Order values for File_Card and Preview_Modal contexts
4. WHEN an administrator saves Display_Order changes, THE Configuration_UI SHALL validate that all values are non-negative integers
5. THE Display_Configuration_System SHALL apply Display_Order settings in ascending numeric order

### Requirement 4: Visibility Control

**User Story:** As an administrator, I want to control which fields appear on file cards versus preview modals, so that I can show summary information on cards and detailed information in modals.

#### Acceptance Criteria

1. WHEN a Custom_Metadata_Field has File_Card visibility disabled, THE Admin_Dashboard SHALL exclude that field from File_Card display
2. WHEN a Custom_Metadata_Field has File_Card visibility disabled, THE Public_Page SHALL exclude that field from File_Card display
3. WHEN a Custom_Metadata_Field has Preview_Modal visibility disabled, THE Admin_Dashboard SHALL exclude that field from Preview_Modal display
4. WHEN a Custom_Metadata_Field has Preview_Modal visibility disabled, THE Public_Page SHALL exclude that field from Preview_Modal display
5. THE Display_Configuration_System SHALL allow a Custom_Metadata_Field to be visible in File_Card only, Preview_Modal only, both, or neither
6. WHEN all Custom_Metadata_Field entries have File_Card visibility disabled, THE File_Card SHALL display a default message indicating no metadata is configured for display

### Requirement 5: Admin Dashboard Display

**User Story:** As an administrator, I want to see customized metadata display on the admin dashboard, so that I can efficiently review file information.

#### Acceptance Criteria

1. WHEN displaying a File_Card on Admin_Dashboard, THE Display_Configuration_System SHALL show only Custom_Metadata_Field entries with File_Card visibility enabled
2. WHEN displaying a File_Card on Admin_Dashboard, THE Display_Configuration_System SHALL order fields according to File_Card Display_Order settings
3. WHEN displaying a Preview_Modal on Admin_Dashboard, THE Display_Configuration_System SHALL show only Custom_Metadata_Field entries with Preview_Modal visibility enabled
4. WHEN displaying a Preview_Modal on Admin_Dashboard, THE Display_Configuration_System SHALL order fields according to Preview_Modal Display_Order settings
5. WHEN display settings are updated, THE Admin_Dashboard SHALL reflect changes without requiring page refresh

### Requirement 6: Public Page Display

**User Story:** As a public user, I want to see metadata organized according to administrator preferences, so that I can quickly find relevant file information.

#### Acceptance Criteria

1. WHEN displaying a File_Card on Public_Page, THE Display_Configuration_System SHALL show only Custom_Metadata_Field entries with File_Card visibility enabled
2. WHEN displaying a File_Card on Public_Page, THE Display_Configuration_System SHALL order fields according to File_Card Display_Order settings
3. WHEN displaying a Preview_Modal on Public_Page, THE Display_Configuration_System SHALL show only Custom_Metadata_Field entries with Preview_Modal visibility enabled
4. WHEN displaying a Preview_Modal on Public_Page, THE Display_Configuration_System SHALL order fields according to Preview_Modal Display_Order settings
5. THE Public_Page SHALL apply identical display logic as Admin_Dashboard for consistency

### Requirement 7: Default Display Behavior

**User Story:** As an administrator, I want the system to have sensible defaults when I haven't configured display settings, so that the system works without requiring immediate configuration.

#### Acceptance Criteria

1. WHEN no display configuration exists for a Custom_Metadata_Field, THE Display_Configuration_System SHALL display the field on both File_Card and Preview_Modal
2. WHEN no Display_Order is configured for a Custom_Metadata_Field, THE Display_Configuration_System SHALL use the field's creation order from custom_metadata_fields table
3. WHEN a new Custom_Metadata_Field is created, THE Display_Configuration_System SHALL apply Default_Display_Behavior until explicitly configured
4. THE Display_Configuration_System SHALL maintain backward compatibility with existing metadata fields created before this feature

### Requirement 8: Configuration Persistence

**User Story:** As an administrator, I want my display configuration changes to save reliably, so that I don't lose my customization work.

#### Acceptance Criteria

1. WHEN an administrator submits configuration changes, THE Configuration_UI SHALL validate all input values before saving
2. WHEN validation succeeds, THE Display_Configuration_System SHALL persist changes to the database
3. WHEN validation fails, THE Configuration_UI SHALL display specific error messages indicating which fields have invalid values
4. WHEN database persistence fails, THE Configuration_UI SHALL display an error message and retain the administrator's input values
5. WHEN configuration changes are saved successfully, THE Configuration_UI SHALL display a confirmation message
6. THE Display_Configuration_System SHALL complete save operations within 2 seconds under normal load conditions

### Requirement 9: Query Performance

**User Story:** As a system user, I want pages to load quickly even with custom display settings, so that the system remains responsive.

#### Acceptance Criteria

1. WHEN retrieving display configuration for File_Card rendering, THE Display_Configuration_System SHALL complete the query within 100 milliseconds
2. WHEN retrieving display configuration for Preview_Modal rendering, THE Display_Configuration_System SHALL complete the query within 100 milliseconds
3. THE Display_Configuration_System SHALL use database indexes on frequently queried columns
4. WHEN rendering multiple File_Card instances, THE Display_Configuration_System SHALL retrieve display configuration once per page load, not per file
5. THE Display_Configuration_System SHALL cache display configuration settings for the duration of a page request

### Requirement 10: Configuration Reset

**User Story:** As an administrator, I want to reset display settings to defaults, so that I can start over if my customizations become problematic.

#### Acceptance Criteria

1. THE Configuration_UI SHALL provide a reset function for individual Custom_Metadata_Field entries
2. THE Configuration_UI SHALL provide a reset function for all Custom_Metadata_Field entries simultaneously
3. WHEN an administrator triggers a reset for a Custom_Metadata_Field, THE Display_Configuration_System SHALL restore Default_Display_Behavior for that field
4. WHEN an administrator triggers a global reset, THE Configuration_UI SHALL require confirmation before proceeding
5. WHEN a reset operation completes, THE Configuration_UI SHALL display a confirmation message indicating which settings were reset

### Requirement 11: Field Label Display

**User Story:** As a user, I want to see descriptive labels for metadata fields, so that I understand what each field represents.

#### Acceptance Criteria

1. WHEN displaying a Custom_Metadata_Field on File_Card, THE Display_Configuration_System SHALL use the field_label value from custom_metadata_fields table
2. WHEN displaying a Custom_Metadata_Field on Preview_Modal, THE Display_Configuration_System SHALL use the field_label value from custom_metadata_fields table
3. WHEN a Custom_Metadata_Field has no associated value for a specific file, THE Display_Configuration_System SHALL omit that field from display rather than showing an empty value
4. THE Display_Configuration_System SHALL display field_label and field value pairs in a consistent format across File_Card and Preview_Modal

### Requirement 12: Configuration Validation

**User Story:** As an administrator, I want the system to prevent invalid configurations, so that display settings always work correctly.

#### Acceptance Criteria

1. WHEN an administrator enters a Display_Order value, THE Configuration_UI SHALL reject negative numbers
2. WHEN an administrator enters a Display_Order value, THE Configuration_UI SHALL reject non-numeric values
3. WHEN an administrator attempts to save configuration, THE Configuration_UI SHALL verify that at least one Custom_Metadata_Field has Preview_Modal visibility enabled
4. IF no Custom_Metadata_Field entries exist, THEN THE Configuration_UI SHALL display a message indicating that metadata fields must be created first
5. THE Configuration_UI SHALL prevent submission of incomplete or invalid configuration data

## Notes

### Performance Considerations
- Display configuration should be loaded once per page request and cached
- Database queries should use appropriate indexes on custom_metadata_fields and display configuration tables
- Consider implementing a configuration cache that invalidates only when settings change

### Backward Compatibility
- Existing files with metadata must continue to display correctly
- Migration should create default display settings for existing Custom_Metadata_Field entries
- No data loss should occur during feature deployment

### Future Enhancements (Out of Scope)
- Per-user display preferences
- Different display settings for different file types
- Conditional display rules based on metadata values
- Export/import of display configurations
