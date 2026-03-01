# Dashboard Improvements - Design Document

## Architecture Overview

This feature enhances the admin dashboard with three major improvements:
1. Multi-select functionality for bulk operations
2. Unified reader view matching the public interface
3. Redesigned file cards matching the public page design

## Component Design

### 1. Multi-Select System

#### UI Components

**Selection Checkbox**
- Position: Top-left corner of each file card
- Style: Circular checkbox with primary color accent
- States: Unchecked, Checked, Indeterminate (for "Select All")
- Visibility: Always visible or show on hover

**Select All Control**
- Location: Dashboard header, near search/filter controls
- Label: "Select All" with count indicator
- Behavior: Toggles all visible files

**Selection Bar**
- Appears when files are selected
- Shows: Count of selected files
- Actions: Clear Selection, Bulk Actions dropdown
- Position: Sticky at top of content area

#### JavaScript Architecture

```javascript
// Selection State Management
const selectionManager = {
    selectedFiles: new Set(),
    
    toggleFile(fileId) {
        if (this.selectedFiles.has(fileId)) {
            this.selectedFiles.delete(fileId);
        } else {
            this.selectedFiles.add(fileId);
        }
        this.updateUI();
    },
    
    selectAll() {
        document.querySelectorAll('.file-card').forEach(card => {
            this.selectedFiles.add(card.dataset.id);
        });
        this.updateUI();
    },
    
    clearAll() {
        this.selectedFiles.clear();
        this.updateUI();
    },
    
    updateUI() {
        // Update checkboxes
        // Update selection bar
        // Update card visual states
    }
};
```

#### CSS Classes

```css
.file-card-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
    width: 24px;
    height: 24px;
    cursor: pointer;
}

.file-card.selected {
    border: 2px solid #3A9AFF;
    box-shadow: 0 0 0 3px rgba(58, 154, 255, 0.1);
}

.selection-bar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #F3F4F6;
    padding: 12px 20px;
    border-bottom: 1px solid #E5E7EB;
}
```

### 2. Admin Reader View Enhancement

#### Design Approach
Copy the public reader view implementation to admin reader while preserving admin-specific features.

#### Key Components to Copy

**From public reader:**
- Chrome controls (top bar with navigation)
- Page navigation UI
- Zoom controls
- Full-screen toggle
- Progress indicator
- Keyboard shortcuts

**Admin-specific additions:**
- Edit button in chrome controls
- Delete button (with confirmation)
- Back to Dashboard link

#### File Structure

```
pages/reader.php (admin reader)
├── Copy UI from: reader.php (public)
├── Preserve: Admin authentication
├── Update: Navigation links to dashboard
└── Add: Admin action buttons
```

### 3. Dashboard File Card Redesign

#### Card Structure

```html
<div class="dashboard-file-card">
    <!-- Selection Checkbox -->
    <input type="checkbox" class="file-card-checkbox">
    
    <!-- Thumbnail with Category Badge -->
    <div class="card-thumb-wrap">
        <img src="..." class="card-thumbnail">
        <span class="card-category-badge">Category</span>
    </div>
    
    <!-- Card Info -->
    <div class="card-info">
        <div class="card-date">FEBRUARY 15, 2024</div>
        <div class="card-title">Document Title</div>
        <div class="card-publisher">Publisher Name</div>
    </div>
    
    <!-- Admin Actions (on hover) -->
    <div class="card-actions">
        <button class="btn-edit">Edit</button>
        <button class="btn-delete">Delete</button>
    </div>
</div>
```

#### CSS Design

Copy from `assets/css/pages/public.css`:
- `.public-file-card` → `.dashboard-file-card`
- `.public-thumb-wrap` → `.card-thumb-wrap`
- `.public-file-thumbnail` → `.card-thumbnail`
- `.pub-thumb-badge` → `.card-category-badge`
- `.public-file-info` → `.card-info`
- `.public-file-date-line` → `.card-date`
- `.public-file-title` → `.card-title`

Add admin-specific styles:
- `.card-actions` for edit/delete buttons
- `.file-card-checkbox` for selection
- `.selected` state styling

## Data Flow

### Multi-Select Flow

```
User clicks checkbox
    ↓
JavaScript updates selectionManager.selectedFiles
    ↓
UI updates (checkbox state, card border, selection bar)
    ↓
User clicks bulk action
    ↓
Confirmation modal (if needed)
    ↓
AJAX request to backend with file IDs
    ↓
Backend processes bulk operation
    ↓
Response updates UI
```

### Reader View Flow

```
User clicks file card
    ↓
Navigate to pages/reader.php?id=X
    ↓
Load file with public reader UI
    ↓
User clicks "Back to Dashboard"
    ↓
Return to dashboard.php
```

## Implementation Plan

### Phase 1: File Card Redesign
1. Copy CSS from public.css to dashboard.css
2. Update views/dashboard.php card HTML structure
3. Add admin action buttons
4. Test responsive design

### Phase 2: Multi-Select Functionality
1. Add checkbox to each card
2. Implement JavaScript selection manager
3. Create selection bar UI
4. Add "Select All" control
5. Test selection state management

### Phase 3: Reader View Enhancement
1. Compare public and admin reader files
2. Copy UI components from public reader
3. Update navigation links
4. Add admin action buttons
5. Test all reader functionality

### Phase 4: Integration & Testing
1. Test multi-select with various file counts
2. Verify reader view on different file types
3. Test responsive design on all screen sizes
4. Verify no regression in existing features

## Security Considerations

- Multi-select operations require admin authentication
- Validate all file IDs on backend
- Prevent unauthorized bulk operations
- Sanitize all user inputs
- Log bulk operations in activity history

## Performance Considerations

- Lazy load thumbnails for large file sets
- Debounce selection state updates
- Optimize checkbox rendering for 100+ files
- Cache reader view assets
- Minimize DOM manipulations during selection

## Accessibility

- Checkboxes have proper ARIA labels
- Keyboard navigation for selection (Shift+Click for range select)
- Screen reader announcements for selection count
- Focus management in reader view
- High contrast mode support

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Fallback for older browsers (graceful degradation)
- Touch-friendly on tablets
- Responsive on mobile devices
