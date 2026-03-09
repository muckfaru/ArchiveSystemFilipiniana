# Bugfix Requirements Document

## Introduction

The collections page (admin) is currently using hardcoded category functions that query the deprecated `categories` table instead of dynamically loading categories from the user-defined metadata fields in the custom metadata system. This creates inconsistency between the admin collections page and the public browse page, which correctly uses the form templates system. This bug prevents administrators from seeing and filtering by categories that users have defined through the custom metadata form builder.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the collections page loads THEN the system queries the hardcoded `categories` table using `SELECT c.id, c.name, COUNT(n.id) as count FROM categories c LEFT JOIN newspapers n ON c.id = n.category_id`

1.2 WHEN filtering by category on the collections page THEN the system uses `n.category_id = ?` to filter against the deprecated `categories.id` column

1.3 WHEN displaying category names in file cards THEN the system uses `c.name as category_name` from the hardcoded `categories` table join

1.4 WHEN the collections page sidebar renders category filters THEN the system displays categories from the hardcoded `categories` table instead of user-defined category metadata

### Expected Behavior (Correct)

2.1 WHEN the collections page loads THEN the system SHALL query categories from `custom_metadata_values` joined with `form_fields` WHERE `field_label = 'Category'` matching the browse page implementation

2.2 WHEN filtering by category on the collections page THEN the system SHALL use an EXISTS subquery checking `custom_metadata_values` WHERE `field_label = 'Category'` AND `field_value IN (selected categories)`

2.3 WHEN displaying category names in file cards THEN the system SHALL retrieve category values from `custom_metadata_values` joined with `form_fields` WHERE `field_label = 'Category'`

2.4 WHEN the collections page sidebar renders category filters THEN the system SHALL display categories dynamically loaded from user-defined metadata fields in the active form template

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the collections page displays non-category metadata THEN the system SHALL CONTINUE TO display title, publication date, edition, and other core fields correctly

3.2 WHEN filtering by format (PDF, MOBI, IMAGES) THEN the system SHALL CONTINUE TO filter using `n.file_type` and `n.is_bulk_image` columns

3.3 WHEN sorting collections (newest, oldest, a-z, z-a) THEN the system SHALL CONTINUE TO sort using the existing ORDER BY logic

3.4 WHEN exporting to CSV THEN the system SHALL CONTINUE TO export all selected documents with their metadata

3.5 WHEN paginating results THEN the system SHALL CONTINUE TO paginate correctly with the existing pagination logic

3.6 WHEN searching collections THEN the system SHALL CONTINUE TO search across title, keywords, and description fields

3.7 WHEN displaying the file preview modal THEN the system SHALL CONTINUE TO show all file details and action buttons correctly

3.8 WHEN the "All Collections" filter is selected THEN the system SHALL CONTINUE TO display all documents without category filtering
