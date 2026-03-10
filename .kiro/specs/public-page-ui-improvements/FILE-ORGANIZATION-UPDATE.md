# File Organization Update - Upload System

## Overview
Updated the file upload system to organize files by publication year, month, and file type instead of storing all files in a flat structure.

## Changes Made

### New Folder Structure

**Previous Structure:**
```
uploads/
├── newspapers/
│   ├── file1.pdf
│   ├── file2.epub
│   └── bulk_timestamp/
│       ├── image1.jpg
│       └── image2.jpg
└── thumbnails/
    ├── thumb1.jpg
    └── thumb2.jpg
```

**New Structure:**
```
uploads/
├── newspapers/
│   └── YYYY/
│       └── MM/
│           ├── ebooks/
│           │   ├── file1.epub
│           │   └── file2.mobi
│           ├── pdf/
│           │   └── file1.pdf
│           ├── images/
│           │   ├── single_image.jpg
│           │   ├── bulk_image1.jpg
│           │   ├── bulk_image2.jpg
│           │   └── bulk_image3.jpg
│           └── documents/
│               └── file1.doc
└── thumbnails/
    └── YYYY/
        └── MM/
            ├── thumb1.jpg
            └── thumb2.jpg
```

### File Type Categories

Files are organized into the following type folders:

1. **ebooks/** - EPUB and MOBI files
2. **pdf/** - PDF files
3. **images/** - JPG, JPEG, PNG, WEBP, TIFF, TIF files
4. **documents/** - All other document types (DOC, DOCX, XML, etc.)

### Implementation Details

#### 1. Single File Upload
- Extracts year and month from `publication_date` field
- Falls back to current date if no publication date provided
- Creates directory structure: `uploads/newspapers/YYYY/MM/filetype/`
- Stores file with unique timestamp-based filename

#### 2. Bulk Image Upload
- Uses publication date to determine year/month
- Creates directory structure: `uploads/newspapers/YYYY/MM/images/`
- All bulk images stored directly in the images folder (no subfolder)
- Maintains sequential ordering with numbered prefixes (001_, 002_, etc.)

#### 3. Thumbnail Upload
- Organized by year/month: `uploads/thumbnails/YYYY/MM/`
- Applies to both single and bulk uploads
- Uses same year/month as the main file

#### 4. Edit Mode
- When replacing files during edit, uses the updated publication date
- Maintains same organizational structure as new uploads

### Code Changes

**File:** `pages/upload.php`

**Modified Sections:**
1. Single file upload logic (lines ~120-160)
2. Bulk image upload logic (lines ~280-340)
3. Edit mode file replacement (lines ~420-480)
4. Thumbnail handling in all three sections

**Key Functions:**
- Directory creation with `mkdir($dir, 0777, true)` for recursive creation
- Path construction using year/month/type variables
- Relative path storage in database for portability

### Database Impact

**No database schema changes required.**

The `file_path` and `thumbnail_path` columns already store relative paths, so the new structure is fully compatible.

**Example paths stored:**
- Old: `uploads/newspapers/1234567890_abc123.pdf`
- New: `uploads/newspapers/2014/03/pdf/1234567890_abc123.pdf`

### Benefits

1. **Better Organization** - Files grouped by time period and type
2. **Easier Maintenance** - Can archive or backup specific months/years
3. **Improved Performance** - Smaller directory listings (fewer files per folder)
4. **Scalability** - Structure handles large volumes of files efficiently
5. **Logical Browsing** - Easy to locate files by publication date

### Migration Notes

**For Existing Files:**
- Old files in flat structure will continue to work
- New uploads will use the new structure
- Both structures can coexist
- Optional: Create migration script to reorganize existing files

### Testing Checklist

- [ ] Upload single PDF file with publication date
- [ ] Upload single EPUB file with publication date
- [ ] Upload single MOBI file with publication date
- [ ] Upload single image file
- [ ] Upload bulk images with publication date
- [ ] Upload file without publication date (should use current date)
- [ ] Upload with custom thumbnail
- [ ] Edit existing file and replace with new file
- [ ] Edit existing file and replace thumbnail
- [ ] Verify files are accessible via reader/viewer
- [ ] Check that file paths in database are correct
- [ ] Verify folder permissions (0777)

### Example Upload Scenarios

**Scenario 1: EPUB file published March 2014**
```
Publication Date: 2014-03-15
File: book.epub
Result: uploads/newspapers/2014/03/ebooks/1234567890_abc123.epub
Thumbnail: uploads/thumbnails/2014/03/1234567890_thumb_xyz789.jpg
```

**Scenario 2: Bulk images published December 2023**
```
Publication Date: 2023-12-25
Files: img1.jpg, img2.jpg, img3.jpg
Result: uploads/newspapers/2023/12/images/
  - 001_1234567890_abc12.jpg
  - 002_1234567890_def34.jpg
  - 003_1234567890_ghi56.jpg
Thumbnail: uploads/thumbnails/2023/12/1234567890_thumb_xyz789.jpg
```

**Scenario 3: PDF file with no publication date**
```
Publication Date: (empty - uses current date)
File: document.pdf
Current Date: 2024-02-28
Result: uploads/newspapers/2024/02/pdf/1234567890_abc123.pdf
```

## Files Modified

1. `pages/upload.php` - Main upload controller with new folder organization logic

## Backward Compatibility

✅ Fully backward compatible - existing files continue to work with their old paths.

## Status

✅ **COMPLETE** - All upload paths now use organized folder structure.
