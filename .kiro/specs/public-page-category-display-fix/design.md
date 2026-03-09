# Public Page Category Display Fix - Bugfix Design

## Overview

The public pages (public.php and browse.php) display "UNCATEGORIZED" for all files instead of showing their actual assigned categories. This bug occurs because the Category field either doesn't exist in the form_fields table or category values aren't being saved to custom_metadata_values during file upload. The fix involves ensuring the Category field exists in the active form template and verifying that category values are properly saved during upload.

The system uses a custom metadata architecture where:
- Form templates define metadata fields (stored in form_templates and form_fields tables)
- Metadata values are stored in custom_metadata_values table
- Public pages query categories by joining custom_metadata_values with form_fields where field_label = 'Category'

The fix will be minimal and targeted: ensure the Category field exists in the active form template, and verify the upload logic correctly saves category values to custom_metadata_values.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when files are uploaded with categories but the category data is not saved or retrievable
- **Property (P)**: The desired behavior - categories should be saved during upload and displayed correctly on public pages
- **Preservation**: Existing upload behavior for other metadata fields and public page display for other metadata must remain unchanged
- **form_fields**: Database table storing field definitions for form templates (includes field_label, field_type, field_options)
- **custom_metadata_values**: Database table storing actual metadata values for files (file_id, field_id, field_value)
- **form_templates**: Database table storing form template definitions (only one can be active at a time)
- **Category field**: A form field with field_label = 'Category' that should exist in the active form template
- **Active form template**: The form template with is_active = 1 that determines which fields appear on the upload form

## Bug Details

### Fault Condition

The bug manifests when a user uploads a file through admin_pages/upload.php and expects the category to be saved and displayed on public pages. The system either fails to save the category value to custom_metadata_values, or the Category field doesn't exist in form_fields, causing queries to return no results.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type FileUploadRequest
  OUTPUT: boolean
  
  RETURN (input.hasFile = true)
         AND (input.categorySelected = true)
         AND (NOT categoryFieldExistsInFormFields() 
              OR NOT categorySavedToCustomMetadataValues(input.fileId))
END FUNCTION
```

### Examples

- **Example 1**: User uploads a newspaper PDF and selects "Politics" as the category. After upload, the file appears on browse.php with "UNCATEGORIZED" instead of "Politics".
- **Example 2**: User navigates to public.php and sees the category filter sidebar is empty because the query `SELECT DISTINCT cmv.field_value FROM custom_metadata_values cmv INNER JOIN form_fields cmf ON cmv.field_id = cmf.id WHERE cmf.field_label = 'Category'` returns no results.
- **Example 3**: User clicks on a category filter in browse.php sidebar, but no filtering occurs because no category data exists in custom_metadata_values.
- **Edge Case**: User edits an existing file and changes the category. The new category value should be saved and displayed correctly on public pages.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Upload functionality for other metadata fields (Title, Publisher, Publication Date, Language, Edition, etc.) must continue to work exactly as before
- Public page queries for other metadata fields (Language, Edition, etc.) must continue to work as expected
- Edit functionality for existing files must continue to update custom_metadata_values correctly for all fields
- Upload form display in edit mode must continue to pre-populate existing metadata values correctly

**Scope:**
All inputs that do NOT involve the Category field should be completely unaffected by this fix. This includes:
- Uploads without category selection (if category is optional)
- Queries for other metadata fields on public pages
- Display of other metadata on cards and modals
- File upload validation and processing logic

## Hypothesized Root Cause

Based on the bug description and code analysis, the most likely issues are:

1. **Missing Category Field in Active Form Template**: The active form template may not have a Category field defined in form_fields table
   - Migration 003 creates Category field in custom_metadata_fields table
   - Migration 002 migrates fields from custom_metadata_fields to form_fields
   - If migrations ran in wrong order or Category field wasn't migrated, it won't exist in form_fields

2. **Category Field Not in Active Form**: Even if Category field exists in form_fields, it may not be associated with the currently active form template
   - Only one form template can be active at a time (is_active = 1)
   - If Category field belongs to an inactive form, it won't appear on upload form

3. **Upload Logic Not Saving Category Values**: The upload.php controller may have a bug in the metadata saving logic
   - Code at line 440-460 in admin_pages/upload.php saves custom metadata
   - If Category field has wrong field_type or field_options format, it may not save correctly

4. **Field ID Mismatch**: The field_id used when saving category values may not match the actual Category field ID in form_fields
   - Migration 002 creates field mapping when migrating from custom_metadata_fields to form_fields
   - If mapping is incorrect, category values will be saved with wrong field_id

## Correctness Properties

Property 1: Fault Condition - Category Values Saved and Displayed

_For any_ file upload where a category is selected through the upload form, the fixed system SHALL save the category value to custom_metadata_values with the correct field_id referencing the Category field in form_fields, and public pages SHALL display the correct category name instead of "UNCATEGORIZED".

**Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**

Property 2: Preservation - Other Metadata Fields Unchanged

_For any_ file upload or edit operation involving metadata fields other than Category (Title, Publisher, Publication Date, Language, Edition, etc.), the fixed system SHALL produce exactly the same behavior as the original system, preserving all existing functionality for saving, querying, and displaying non-category metadata.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `backend/migrations/002_migrate_to_form_templates.php` (already exists)

**Function**: `runMigration()`

**Verification Steps**:
1. **Verify Category Field Exists**: Check if Category field exists in form_fields table with field_label = 'Category'
   - Query: `SELECT * FROM form_fields WHERE field_label = 'Category'`
   - If not found, need to create it in the active form template

2. **Verify Active Form Template**: Check if there's an active form template
   - Query: `SELECT * FROM form_templates WHERE is_active = 1`
   - If no active form, need to activate one or create default

3. **Create Category Field if Missing**: If Category field doesn't exist in active form, create it
   - Get category options from categories table
   - Insert into form_fields with form_id = active form ID
   - Set field_type = 'select', field_options = JSON array of category names

4. **Verify Upload Logic**: Ensure admin_pages/upload.php correctly saves category values
   - Check that field key is constructed correctly: 'field_' . $field['id']
   - Verify that select field values are sanitized and saved correctly
   - Confirm that field_id matches the Category field ID in form_fields

5. **Test Category Queries**: Verify that public pages can query categories correctly
   - Test query: `SELECT DISTINCT cmv.field_value FROM custom_metadata_values cmv INNER JOIN form_fields cmf ON cmv.field_id = cmf.id WHERE cmf.field_label = 'Category'`
   - Should return list of category names

### Specific Implementation

**Option 1: Create Migration Script** (Recommended)
Create a new migration file `backend/migrations/006_ensure_category_field.php` that:
- Checks if Category field exists in active form template
- Creates Category field if missing
- Populates field_options with categories from categories table
- Verifies existing category values are correctly linked

**Option 2: Manual Database Fix**
If migrations have already run, manually:
- Query active form template ID
- Insert Category field into form_fields table
- Update any orphaned category values in custom_metadata_values

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Fault Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that query the database for Category field existence and attempt to upload files with categories. Run these tests on the UNFIXED code to observe failures and understand the root cause.

**Test Cases**:
1. **Category Field Existence Test**: Query form_fields for Category field (will fail if field doesn't exist)
2. **Active Form Template Test**: Query form_templates for active form (will fail if no active form)
3. **Upload with Category Test**: Upload a file with category selected, then query custom_metadata_values (will fail if category not saved)
4. **Public Page Category Query Test**: Execute the category query used by public.php (will fail if no results returned)

**Expected Counterexamples**:
- Category field doesn't exist in form_fields table
- Category field exists but not in active form template
- Category values are saved with incorrect field_id
- Category query returns empty result set

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := uploadFile_fixed(input)
  ASSERT categoryFieldExists()
  ASSERT categorySavedToDatabase(result.fileId, input.category)
  ASSERT publicPageDisplaysCategory(result.fileId, input.category)
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT uploadFile_original(input) = uploadFile_fixed(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-category metadata

**Test Plan**: Observe behavior on UNFIXED code first for other metadata fields, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Other Metadata Fields Preservation**: Upload files with Title, Publisher, Publication Date, Language, Edition - verify all save correctly
2. **Edit Functionality Preservation**: Edit existing files and update non-category metadata - verify updates work correctly
3. **Public Page Display Preservation**: Verify that public pages display other metadata fields correctly after fix
4. **Query Performance Preservation**: Verify that public page queries for other metadata fields have same performance

### Unit Tests

- Test that Category field exists in form_fields table after fix
- Test that Category field is associated with active form template
- Test that uploading a file with category saves value to custom_metadata_values
- Test that public page category query returns correct results
- Test that category filter in browse.php works correctly
- Test that editing a file updates category value correctly

### Property-Based Tests

- Generate random file uploads with various category selections and verify all save correctly
- Generate random category queries and verify all return correct results
- Generate random file edits with category changes and verify updates work correctly
- Test that all non-category metadata fields continue to work across many upload scenarios

### Integration Tests

- Test full upload flow: select file, fill metadata including category, upload, verify appears on public page with correct category
- Test category filter flow: navigate to browse.php, select category filter, verify only files with that category are displayed
- Test edit flow: edit existing file, change category, save, verify public page shows new category
- Test migration flow: run migrations on fresh database, verify Category field is created correctly
