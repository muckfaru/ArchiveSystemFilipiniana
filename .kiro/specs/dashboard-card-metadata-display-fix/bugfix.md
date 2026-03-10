# Bugfix Requirements Document

## Introduction

The dashboard cards are displaying metadata in an inconsistent plain text format instead of using styled formatting. This creates visual inconsistency across the UI and results in duplicate information being displayed (e.g., category appearing both as a badge on the thumbnail and as plain text in the metadata list). This bugfix will ensure that dashboard card metadata is displayed consistently, without duplication, and with appropriate styling.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a dashboard card displays metadata for Category field THEN the system shows "Category: [value]" in plain text format in the metadata list

1.2 WHEN a dashboard card displays metadata for Language field THEN the system shows "Language: [value]" in plain text format using generic metadata display

1.3 WHEN a dashboard card displays metadata for Page Count field THEN the system shows "Page Count: [value]" in plain text format using generic metadata display

1.4 WHEN a dashboard card displays Category metadata THEN the system shows the category both as a badge on the thumbnail AND as plain text in the metadata list (duplicate display)

1.5 WHEN the renderCardMetadata() function processes Category, Language, and Page Count fields THEN the system treats them as generic custom fields and applies the generic "label: value" format

### Expected Behavior (Correct)

2.1 WHEN a dashboard card displays metadata THEN the system SHALL NOT show Category in the metadata list (only as badge on thumbnail)

2.2 WHEN a dashboard card displays metadata THEN the system SHALL NOT show Language in the metadata list (visible in modal/reader only)

2.3 WHEN a dashboard card displays metadata THEN the system SHALL NOT show Page Count in the metadata list (visible in modal/reader only)

2.4 WHEN a dashboard card displays metadata THEN the system SHALL show only Publication Date, Publisher, and Tags (if configured) with consistent styling

2.5 WHEN the renderCardMetadata() function processes metadata fields THEN the system SHALL filter out Category, Language, and Page Count from card display

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a dashboard card displays Publication Date metadata THEN the system SHALL CONTINUE TO show it with the calendar icon and styled format

3.2 WHEN a dashboard card displays Publisher metadata THEN the system SHALL CONTINUE TO show it with the building icon and styled format

3.3 WHEN a dashboard card displays Tags metadata THEN the system SHALL CONTINUE TO show tags with proper badge styling

3.4 WHEN the category badge is displayed on the thumbnail THEN the system SHALL CONTINUE TO show it in its current styled format

3.5 WHEN metadata is displayed in the modal or reader view THEN the system SHALL CONTINUE TO show all metadata fields including Category, Language, and Page Count

3.6 WHEN custom metadata fields (other than Category, Language, Page Count) are displayed on cards THEN the system SHALL CONTINUE TO display them according to their configured display settings
