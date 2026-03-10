# Requirements Document

## Introduction

This document specifies requirements for improving the metadata display configuration page UI in the Archive System. The improvements focus on enhancing visual clarity, replacing browser alerts with proper modal confirmations, and streamlining the actions interface by removing redundant controls and adding color-coded visual feedback.

## Glossary

- **Metadata_Display_Page**: The administrative interface at views/metadata-display.php that allows configuration of metadata field visibility
- **Field_Row**: A single row in the configuration panel representing one metadata field with its icon, label, description, and controls
- **Actions_Column**: The rightmost section of each Field_Row containing interactive controls (currently toggle switches)
- **Status_Toggle**: A slider control that enables or disables a metadata field's visibility in the current view context (card or modal)
- **View_Eye_Icon**: The existing icon in the actions column that will be removed (currently serves no functional purpose)
- **Three_Dot_Menu**: The existing dropdown menu in the actions column that will be replaced with direct toggle controls
- **Bootstrap_Modal**: A proper modal dialog component from Bootstrap framework used for user confirmations
- **Browser_Alert**: The native JavaScript alert() function that displays blocking confirmation dialogs (to be replaced)
- **Action_Icon**: Any clickable icon in the Field_Row that triggers an operation (edit, delete, reorder, etc.)
- **Card_View**: The "Basic Viewing" tab showing how metadata appears on document cards in grid layouts
- **Modal_View**: The "Detailed Modal" tab showing how metadata appears in the detailed document information modal
- **Auto_Save**: The existing debounced save functionality that persists configuration changes automatically
- **API_Endpoint**: The backend/api/metadata-display-config.php file that handles configuration updates

## Requirements

### Requirement 1: Remove View Eye Icon

**User Story:** As an administrator, I want the view eye icon removed from the actions column, so that the interface is cleaner and less cluttered with non-functional elements.

#### Acceptance Criteria

1. THE Metadata_Display_Page SHALL NOT display any view eye icon in the Actions_Column of any Field_Row
2. WHEN the page loads, THE Metadata_Display_Page SHALL render Field_Rows without view eye icons
3. THE Field_Row layout SHALL maintain proper spacing and alignment after the view eye icon removal

### Requirement 2: Replace Three-Dot Menu with Status Toggle

**User Story:** As an administrator, I want the three-dot dropdown menu replaced with a direct status toggle slider, so that I can enable or disable fields with a single click instead of opening a menu.

#### Acceptance Criteria

1. THE Metadata_Display_Page SHALL NOT display any three-dot dropdown menu in the Actions_Column
2. THE Actions_Column SHALL display a Status_Toggle slider as the primary control for each Field_Row
3. WHEN a user clicks the Status_Toggle, THE Metadata_Display_Page SHALL immediately update the field's visibility state for the active view context
4. WHEN the Card_View tab is active, THE Status_Toggle SHALL control the show_on_card property
5. WHEN the Modal_View tab is active, THE Status_Toggle SHALL control the show_in_modal property
6. THE Status_Toggle SHALL visually indicate the current enabled/disabled state using color and position
7. THE Auto_Save functionality SHALL persist Status_Toggle changes within 800 milliseconds
8. THE Status_Toggle SHALL maintain the existing toggle switch visual design (42px width, 24px height, rounded slider)

### Requirement 3: Add Colors to Action Icons

**User Story:** As an administrator, I want action icons to have distinct colors, so that I can quickly identify different actions and the interface feels more modern and usable.

#### Acceptance Criteria

1. WHEN an Action_Icon represents an edit operation, THE Metadata_Display_Page SHALL display it in blue color (#3A9AFF or similar)
2. WHEN an Action_Icon represents a delete operation, THE Metadata_Display_Page SHALL display it in red color (#EF4444 or similar)
3. WHEN an Action_Icon represents a reorder operation, THE Metadata_Display_Page SHALL display it in gray color (#6B7280 or similar)
4. WHEN an Action_Icon represents a view operation, THE Metadata_Display_Page SHALL display it in green color (#10B981 or similar)
5. WHEN a user hovers over an Action_Icon, THE Metadata_Display_Page SHALL increase the icon's opacity or brightness to provide visual feedback
6. THE Action_Icon colors SHALL maintain sufficient contrast ratio (minimum 4.5:1) against the background for accessibility
7. THE Status_Toggle enabled state SHALL use blue color (#3A9AFF) and disabled state SHALL use gray color (#D1D5DB)

### Requirement 4: Replace Browser Alerts with Bootstrap Modals

**User Story:** As an administrator, I want proper modal confirmations instead of browser alerts, so that the confirmation experience is consistent with the rest of the application and more user-friendly.

#### Acceptance Criteria

1. THE Metadata_Display_Page SHALL NOT use JavaScript alert() function for any user confirmations
2. WHEN a user initiates a destructive action (delete, reset), THE Metadata_Display_Page SHALL display a Bootstrap_Modal confirmation dialog
3. THE Bootstrap_Modal SHALL display a clear title describing the action being confirmed
4. THE Bootstrap_Modal SHALL display a message explaining the consequences of the action
5. THE Bootstrap_Modal SHALL provide two buttons: a cancel button and a confirm button
6. THE Bootstrap_Modal confirm button SHALL use red color (#EF4444) for destructive actions
7. THE Bootstrap_Modal cancel button SHALL use gray color (#6B7280) for non-destructive dismissal
8. WHEN a user clicks the confirm button, THE Metadata_Display_Page SHALL execute the action and close the Bootstrap_Modal
9. WHEN a user clicks the cancel button or clicks outside the modal, THE Metadata_Display_Page SHALL dismiss the Bootstrap_Modal without executing the action
10. WHEN a user presses the Escape key while a Bootstrap_Modal is open, THE Metadata_Display_Page SHALL dismiss the modal without executing the action
11. THE Bootstrap_Modal SHALL use the existing Bootstrap modal component classes and styling for consistency
12. WHEN an action completes successfully after modal confirmation, THE Metadata_Display_Page SHALL display a success toast notification

### Requirement 5: Maintain Existing Functionality

**User Story:** As an administrator, I want all existing features to continue working after the UI improvements, so that no functionality is lost during the enhancement.

#### Acceptance Criteria

1. THE Auto_Save functionality SHALL continue to debounce changes and save configuration within 800 milliseconds
2. THE live preview panel SHALL continue to update in real-time when Status_Toggle changes occur
3. THE tab switching between Card_View and Modal_View SHALL continue to work without page reload
4. THE API_Endpoint SHALL continue to accept and process configuration updates in the same format
5. THE Field_Row hover effects SHALL continue to provide visual feedback
6. THE toast notifications SHALL continue to display save confirmations and error messages
7. THE Field_Row SHALL continue to display field icon, label, description, and metadata information
8. THE Status_Toggle state SHALL persist across page reloads by loading from the database
