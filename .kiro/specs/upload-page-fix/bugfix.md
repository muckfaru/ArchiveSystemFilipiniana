# Bugfix Requirements Document

## Introduction

The upload page has a UI state management bug where removing all files does not properly reset the interface to its initial state. When a user uploads files and then removes them, residual UI elements remain visible including file stats (Total Files, Ready, Pending), file cards/tabs, and the file preview area. This creates a confusing user experience where the drop zone reappears but old file UI elements are still visible below it, making the interface appear broken.

The root cause is in the `resetForm()` function (line 1506) which is called by `removeFile()` (line 2218) when the last file is removed. While `resetForm()` correctly clears the `bulkFiles` array and hides containers, it fails to properly clear all DOM elements that were dynamically created during file upload, specifically the file cards rendered in the tabs containers.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user removes the last file from the upload queue THEN the system leaves file cards/tabs visible in the DOM

1.2 WHEN a user removes all files THEN the system displays the drop zone BUT old file preview UI elements remain visible below it

1.3 WHEN a user removes all files THEN the system shows file stats (Total Files, Ready, Pending) with zero values instead of hiding them

1.4 WHEN resetForm() is called after removing all files THEN the system clears bulkFiles array but does not remove dynamically created file card DOM elements

1.5 WHEN the UI is in this broken state THEN the system shows both the drop zone AND residual file UI elements simultaneously

### Expected Behavior (Correct)

2.1 WHEN a user removes the last file from the upload queue THEN the system SHALL completely clear all file cards and tabs from the DOM

2.2 WHEN a user removes all files THEN the system SHALL display only the drop zone with no residual file UI elements visible

2.3 WHEN a user removes all files THEN the system SHALL hide all file stats containers (bulkStatsContainer, bulkUploadContainer)

2.4 WHEN resetForm() is called after removing all files THEN the system SHALL clear both the bulkFiles array AND all dynamically created DOM elements

2.5 WHEN the UI returns to initial state THEN the system SHALL show only the drop zone as if the page was freshly loaded

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user removes a file but other files remain THEN the system SHALL CONTINUE TO display the remaining files correctly

3.2 WHEN a user switches between file tabs THEN the system SHALL CONTINUE TO save and load form data correctly

3.3 WHEN a user uploads files in bulk mode THEN the system SHALL CONTINUE TO render file cards with status indicators

3.4 WHEN a user is in edit mode THEN the system SHALL CONTINUE TO hide the drop zone and show edit mode indicator

3.5 WHEN resetForm() is called in single file mode THEN the system SHALL CONTINUE TO reset the form without affecting bulk mode elements
