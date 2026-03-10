# Bugfix Requirements Document

## Introduction

The collections page allows users to filter documents by format (PDF, MOBI, IMAGES) using checkboxes in the sidebar. However, when a format filter is applied, there is no visual indication of active filters at the top of the page, and no remove/clear button is provided to quickly remove individual format filters. This is inconsistent with the browse page, which displays active filters as tags with remove buttons. Users must manually uncheck the format checkboxes in the sidebar to clear filters, which is less intuitive and requires scrolling back to the sidebar.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user selects one or more format filters (PDF, MOBI, or IMAGES) on the collections page THEN the system does not display any active filter tags at the top of the results area

1.2 WHEN format filters are active on the collections page THEN the system does not provide any remove/clear buttons to quickly deselect individual format filters

1.3 WHEN a user wants to remove a format filter on the collections page THEN the system requires the user to scroll back to the sidebar and manually uncheck the checkbox

### Expected Behavior (Correct)

2.1 WHEN a user selects one or more format filters on the collections page THEN the system SHALL display active filter tags at the top of the results area showing which formats are currently filtered

2.2 WHEN format filter tags are displayed on the collections page THEN the system SHALL provide an x/remove button beside each format filter tag to allow quick removal

2.3 WHEN a user clicks the remove button on a format filter tag THEN the system SHALL remove that format filter and refresh the results without requiring sidebar interaction

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user selects or deselects format checkboxes in the sidebar THEN the system SHALL CONTINUE TO filter results and submit the form as it currently does

3.2 WHEN a user applies category filters or search queries on the collections page THEN the system SHALL CONTINUE TO function as it currently does

3.3 WHEN format filters are applied alongside other filters (category, search, sort) THEN the system SHALL CONTINUE TO preserve all filter parameters in URLs and form submissions

3.4 WHEN no format filters are active on the collections page THEN the system SHALL CONTINUE TO display results without showing any format filter tags
