# Bugfix Requirements Document

## Introduction

The public pages (public.php and browse.php) display "UNCATEGORIZED" for all files instead of showing their actual assigned categories. This affects the user experience on public-facing pages where readers browse newspapers by category. The category filters in the sidebar are also not working properly because no category data is being retrieved.

The system uses a custom metadata system where:
- Form templates define metadata fields (stored in form_templates and form_fields tables)
- Metadata values are stored in custom_metadata_values table
- Public pages query categories by joining custom_metadata_values with form_fields where field_label = 'Category'

The root cause is that either:
1. The Category field doesn't exist in the form_fields table, OR
2. Category values aren't being saved to custom_metadata_values during file upload

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user uploads a file with a category assigned through admin_pages/upload.php THEN the category value is not saved to the custom_metadata_values table

1.2 WHEN public.php queries for categories using the query "SELECT DISTINCT cmv.field_value FROM custom_metadata_values cmv INNER JOIN form_fields cmf ON cmv.field_id = cmf.id WHERE cmf.field_label = 'Category'" THEN the query returns no results because either the Category field doesn't exist in form_fields or no values exist in custom_metadata_values

1.3 WHEN browse.php displays files THEN all files show "UNCATEGORIZED" because the category metadata is missing from custom_metadata_values

1.4 WHEN a user clicks on a category filter in the sidebar THEN no filtering occurs because the category data doesn't exist in custom_metadata_values

### Expected Behavior (Correct)

2.1 WHEN a user uploads a file with a category assigned through admin_pages/upload.php THEN the category value SHALL be saved to the custom_metadata_values table with the correct field_id referencing the Category field in form_fields

2.2 WHEN public.php queries for categories THEN the query SHALL return all distinct category values that have been assigned to files

2.3 WHEN browse.php displays files THEN each file SHALL display its actual assigned category from custom_metadata_values

2.4 WHEN a user clicks on a category filter in the sidebar THEN the page SHALL filter files to show only those with the selected category

2.5 WHEN the form_fields table is queried for a field with field_label = 'Category' THEN the field SHALL exist (either created during migration or manually added)

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user uploads a file with other metadata fields (Title, Publisher, Publication Date, etc.) THEN the system SHALL CONTINUE TO save those values to custom_metadata_values correctly

3.2 WHEN public.php and browse.php query for other metadata fields (Language, Edition, etc.) THEN the queries SHALL CONTINUE TO work as expected

3.3 WHEN a user edits an existing file's metadata THEN the system SHALL CONTINUE TO update the custom_metadata_values table correctly

3.4 WHEN the upload form displays in edit mode THEN the system SHALL CONTINUE TO pre-populate existing metadata values correctly
