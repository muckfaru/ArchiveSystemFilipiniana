# Dashboard Improvements - Requirements

## Overview
Enhance the admin dashboard with multi-select functionality, improved reader view, and redesigned file cards to match the public page design.

## User Stories

### 1. Multi-Select Functionality
**As an** administrator  
**I want to** select multiple files at once on the dashboard  
**So that** I can perform bulk actions efficiently

**Acceptance Criteria:**
- 1.1 Each file card has a checkbox for selection
- 1.2 A "Select All" checkbox is available in the header
- 1.3 Selected files are visually highlighted
- 1.4 Selection count is displayed (e.g., "3 files selected")
- 1.5 Bulk actions menu appears when files are selected
- 1.6 Can deselect individual files or clear all selections
- 1.7 Selection state persists during page interactions

### 2. Admin Reader View Enhancement
**As an** administrator  
**I want to** have the same reader experience as the public view  
**So that** I can preview files with a consistent, user-friendly interface

**Acceptance Criteria:**
- 2.1 Admin reader view matches public reader view design
- 2.2 Navigation controls are consistent
- 2.3 File metadata display is consistent
- 2.4 Page navigation works the same way
- 2.5 Zoom and view controls match public view
- 2.6 Back button returns to dashboard (not public page)

### 3. Dashboard File Card Redesign
**As an** administrator  
**I want to** see file cards with the same design as the public page  
**So that** the interface is consistent and visually appealing

**Acceptance Criteria:**
- 3.1 File cards match public page card design
- 3.2 Thumbnail display is consistent
- 3.3 Category badges match public design
- 3.4 Date formatting is consistent
- 3.5 Title and description styling match
- 3.6 Hover effects are consistent
- 3.7 Card spacing and grid layout match
- 3.8 Admin-specific actions (edit, delete) are still accessible

## Technical Requirements

### Multi-Select Implementation
- Add checkbox overlay to each file card
- Implement JavaScript for selection management
- Create bulk actions dropdown/menu
- Add backend endpoints for bulk operations
- Maintain existing single-file actions

### Reader View Updates
- Compare public reader (public_pdf_viewer.php, reader.php) with admin reader (pages/reader.php)
- Copy UI components and styling
- Preserve admin-specific functionality
- Update navigation to return to dashboard

### File Card Redesign
- Copy CSS from public.css for file cards
- Update dashboard.php view to use new card structure
- Maintain admin action buttons
- Ensure responsive design

## Out of Scope
- Bulk delete functionality (requires separate confirmation flow)
- Advanced filtering during multi-select
- Drag-and-drop file organization

## Dependencies
- Existing dashboard.php and views/dashboard.php
- Public page file card styles (assets/css/pages/public.css)
- Public reader implementation (reader.php, public_pdf_viewer.php)
- Admin reader (pages/reader.php)

## Success Metrics
- Multi-select works smoothly with 100+ files
- Reader view provides identical experience to public view
- File cards are visually consistent across public and admin pages
- No regression in existing functionality
