# Requirements Document

## Introduction

This document specifies the requirements for fixing a bug in the upload page where file cards persist with "Pending" status after all files have been removed. The system should completely reset the upload interface to its initial clean state when no files remain.

## Glossary

- **Upload_Page**: The web interface where users select and upload files to the archive system
- **File_Card**: A visual UI component displaying information about a selected file, including its name, size, and status
- **Status_Indicator**: A visual element showing the current state of a file (e.g., "Pending", "Ready", "Total Files")
- **Bulk_Stats_Container**: The UI section displaying aggregate statistics about uploaded files (Total Files, Ready, Pending counts)
- **Drop_Zone**: The initial file selection area where users can drag and drop or click to browse for files
- **Reset_State**: The initial clean state of the upload page with no files selected and no status information displayed

## Requirements

### Requirement 1: Complete UI Reset on File Removal

**User Story:** As a user, I want the upload page to return to its initial clean state when I remove all files, so that I don't see confusing residual status information.

#### Acceptance Criteria

1. WHEN a user removes the last remaining file from the upload page, THE Upload_Page SHALL hide all File_Cards
2. WHEN a user removes the last remaining file from the upload page, THE Upload_Page SHALL hide the Bulk_Stats_Container
3. WHEN a user removes the last remaining file from the upload page, THE Upload_Page SHALL display the Drop_Zone
4. WHEN a user removes the last remaining file from the upload page, THE Upload_Page SHALL reset all Status_Indicators to zero or hidden state
5. WHEN the upload page is in Reset_State, THE Upload_Page SHALL display no file-related UI components except the Drop_Zone

### Requirement 2: Status Counter Reset

**User Story:** As a user, I want all file counters to show zero or be hidden when no files are present, so that I don't see misleading status information.

#### Acceptance Criteria

1. WHEN all files are removed, THE Upload_Page SHALL set the "Total Files" counter to zero or hide it
2. WHEN all files are removed, THE Upload_Page SHALL set the "Ready" counter to zero or hide it
3. WHEN all files are removed, THE Upload_Page SHALL set the "Pending" counter to zero or hide it
4. WHEN all files are removed, THE Upload_Page SHALL hide any duplicate file status messages

### Requirement 3: File Card Cleanup

**User Story:** As a user, I want all file cards to be removed from the DOM when I remove all files, so that the interface is clean and ready for new uploads.

#### Acceptance Criteria

1. WHEN the last file is removed, THE Upload_Page SHALL remove all File_Card elements from the DOM
2. WHEN the last file is removed, THE Upload_Page SHALL clear the file tabs container
3. WHEN the last file is removed, THE Upload_Page SHALL clear the bulk tabs container
4. FOR ALL file card containers in the DOM, removing the last file SHALL result in empty containers

### Requirement 4: State Consistency

**User Story:** As a developer, I want the internal state to match the UI state, so that the application behaves predictably.

#### Acceptance Criteria

1. WHEN all files are removed, THE Upload_Page SHALL set the bulkFiles array to empty
2. WHEN all files are removed, THE Upload_Page SHALL set activeFileIndex to -1
3. WHEN all files are removed, THE Upload_Page SHALL set isBulkMode to false
4. WHEN all files are removed, THE Upload_Page SHALL set isBindMode to false
5. WHEN the resetForm function completes, THE Upload_Page SHALL log confirmation that bulkFiles array is empty

### Requirement 5: Visual State Restoration

**User Story:** As a user, I want the upload page to look exactly as it did when I first loaded it after removing all files, so that I can start a fresh upload session.

#### Acceptance Criteria

1. WHEN all files are removed, THE Upload_Page SHALL display the Drop_Zone with its original styling
2. WHEN all files are removed, THE Upload_Page SHALL hide the selected file preview section
3. WHEN all files are removed, THE Upload_Page SHALL hide the bulk upload controls section
4. WHEN all files are removed AND not in edit mode, THE Upload_Page SHALL display the Drop_Zone container
5. WHEN all files are removed AND in edit mode, THE Upload_Page SHALL display the edit mode indicator

### Requirement 6: Button State Reset

**User Story:** As a user, I want the upload and discard buttons to be properly disabled when no files are present, so that I cannot accidentally trigger invalid actions.

#### Acceptance Criteria

1. WHEN all files are removed, THE Upload_Page SHALL disable the upload button
2. WHEN all files are removed, THE Upload_Page SHALL update the discard button state based on form dirty status
3. WHEN the resetForm function is called, THE Upload_Page SHALL call updateButtons to refresh button states
