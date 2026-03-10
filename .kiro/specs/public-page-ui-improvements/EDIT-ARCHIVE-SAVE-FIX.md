# Edit Archive Save Button Fix

## Issue
When editing an existing file in the upload page, minor changes to form fields were not enabling the "Save Changes" button, preventing users from saving their edits.

## Root Cause
The `updateButtons()` function in `assets/js/pages/upload.js` was not properly checking for duplicate errors in edit mode. The logic was:

```javascript
if (isEdit) {
    // Edit Mode: Enable only if Dirty AND Valid
    const newFileSelected = (fileInput && fileInput.files) ? fileInput.files.length > 0 : false;
    shouldEnable = (isDirty || newFileSelected) && isFormValid;
}
```

This was missing the `!hasError` check, which meant that even if the form was valid and had changes, the button could remain disabled if there was a lingering error state.

## Solution
Updated the edit mode logic to include the error check:

```javascript
if (isEdit) {
    // Edit Mode: Enable if (Dirty OR New File Selected) AND Form Valid AND No Error
    const newFileSelected = (fileInput && fileInput.files) ? fileInput.files.length > 0 : false;
    shouldEnable = (isDirty || newFileSelected) && isFormValid && !hasError;
}
```

## Changes Made
- **File**: `assets/js/pages/upload.js`
- **Function**: `updateButtons()`
- **Line**: ~1525
- **Change**: Added `&& !hasError` condition to edit mode button enable logic

## Testing
To verify the fix:
1. Navigate to an existing file in the dashboard
2. Click "Edit" to open the edit page
3. Make a minor change to any field (title, publisher, description, etc.)
4. The "Save Changes" button should now be enabled
5. Click "Save Changes" to confirm the changes are saved

## Related Files
- `assets/js/pages/upload.js` - Main upload/edit page JavaScript
- `views/upload.php` - Upload/edit page view
- `pages/upload.php` - Upload/edit page controller

## Status
✅ Fixed - Save button now properly enables when form fields are modified in edit mode
