# Requirements Document

## Introduction

This document specifies the requirements for enhancing the public-facing archive page with advanced UI/UX features. The enhancements include modal consistency between admin and public views, sticky navigation with dynamic layout changes, and infinite scroll functionality with pagination limits. These features aim to improve user experience, navigation efficiency, and content discovery on the public archive pages.

## Glossary

- **Public_Page**: The main public-facing landing page (public.php) where users can browse and search archives without authentication
- **Admin_Dashboard**: The authenticated admin interface where administrators manage archive content
- **Preview_Modal**: A modal dialog that displays detailed information and preview of an archive document
- **Navbar**: The navigation bar at the top of the page containing branding, navigation links, and search functionality
- **Sticky_Navigation**: A navigation bar that remains fixed at the top of the viewport when the user scrolls down
- **Infinite_Scroll**: A pagination technique that automatically loads more content as the user scrolls down the page
- **File_Card**: A visual card component representing a single archive document in the grid layout
- **Browse_Page**: The dedicated browse page (browse.php) with advanced filtering options

## Requirements

### Requirement 1: Admin Preview Modal Consistency

**User Story:** As an administrator, I want the preview modal in the admin dashboard to match the public page modal design, so that I have a consistent and familiar user experience across both interfaces.

#### Acceptance Criteria

1. WHEN an administrator clicks on a file card in the admin dashboard, THE System SHALL display a preview modal with the same visual design as the public page modal
2. THE Admin_Preview_Modal SHALL include the same layout structure as the public modal (left side for image and read button, right side for metadata)
3. THE Admin_Preview_Modal SHALL include admin-specific action buttons (edit, delete) in addition to the standard preview functionality
4. THE Admin_Preview_Modal SHALL display all metadata fields in the same format and order as the public modal (category badge, title, description, publication date, publisher, language, pages, volume/issue, edition, keywords)
5. THE Admin_Preview_Modal SHALL use the same CSS styling classes and visual hierarchy as the public modal
6. WHEN the admin modal displays a document thumbnail, THE System SHALL use the same image container and placeholder styling as the public modal
7. THE Admin_Preview_Modal SHALL maintain existing admin functionality while adopting the public modal's visual design

### Requirement 2: Sticky Navigation with Dynamic Layout

**User Story:** As a user browsing the public archive, I want the navigation bar to remain visible when I scroll down and reorganize itself for better space utilization, so that I can easily access search and navigation without scrolling back to the top.

#### Acceptance Criteria

1. WHEN a user scrolls down more than 100 pixels on the Public_Page, THE Navbar SHALL become sticky and remain fixed at the top of the viewport
2. WHEN the Navbar becomes sticky, THE System SHALL move the search box from the hero section to the center of the navbar
3. WHEN the Navbar becomes sticky, THE System SHALL move the HOME and BROWSE navigation links to the right side of the navbar (before the Admin Login button)
4. WHEN the Navbar layout changes, THE System SHALL apply smooth CSS transitions for all position and layout changes (minimum 300ms duration)
5. WHEN a user scrolls back to the top of the page (within 100 pixels of the top), THE Navbar SHALL return to its original non-sticky state
6. WHEN the Navbar returns to non-sticky state, THE System SHALL restore the original layout with navigation links in their default position and search box in the hero section
7. THE Sticky_Navigation SHALL maintain the same height and visual styling as the original navbar
8. THE Sticky_Navigation SHALL not obscure page content when fixed at the top
9. WHEN the navbar transitions between states, THE System SHALL ensure no layout shift or content jump occurs

### Requirement 3: Infinite Scroll with Pagination Limit

**User Story:** As a user exploring the public archive home page, I want new files to load automatically as I scroll down, so that I can browse content seamlessly without clicking pagination buttons.

#### Acceptance Criteria

1. WHEN a user scrolls to within 200 pixels of the bottom of the file grid on the Public_Page, THE System SHALL automatically load the next page of files
2. WHEN loading additional files, THE System SHALL display a loading indicator at the bottom of the grid
3. WHEN new files are loaded, THE System SHALL append them to the existing grid without page refresh
4. THE System SHALL load files in batches of 12 files per request (matching the current page size)
5. WHEN the total number of loaded files reaches 60, THE System SHALL stop automatic loading
6. WHEN 60 files have been loaded, THE System SHALL display a "Browse More" button at the bottom of the grid
7. WHEN a user clicks the "Browse More" button, THE System SHALL navigate to the Browse_Page (browse.php)
8. THE System SHALL maintain the current search query and category filter when navigating to the Browse_Page
9. WHEN a network error occurs during file loading, THE System SHALL display an error message and provide a retry option
10. WHEN no more files are available to load (before reaching 60 files), THE System SHALL display a message indicating all files have been loaded
11. THE Infinite_Scroll SHALL not interfere with existing search and filter functionality
12. WHEN files are loading, THE System SHALL prevent duplicate load requests
13. THE Infinite_Scroll implementation SHALL not break existing category, edition, or date filter functionality

### Requirement 4: Dynamic Filter Display

**User Story:** As a user browsing the public archive, I want to see a clear summary of my active filters in the results section, so that I understand what content I am currently viewing.

#### Acceptance Criteria

1. WHEN a user selects a category filter on the Public_Page, THE System SHALL display a contextual label showing the selected category name
2. WHEN a user selects an edition filter, THE System SHALL append the edition value to the filter display label
3. WHEN a user selects a date filter, THE System SHALL append the date value to the filter display label
4. THE System SHALL format multiple active filters as a comma-separated list in the display label
5. WHEN no category is selected, THE System SHALL display "All Categories" as the default category label
6. THE System SHALL display the filter label in the format: "Showing: [Category], [Edition], [Date]"
7. THE System SHALL only display filter values that are actively selected (no empty or unselected filters)
8. THE System SHALL sanitize all filter values before displaying them to prevent XSS vulnerabilities
9. THE System SHALL capitalize filter values appropriately for consistent presentation
10. WHEN a user clears all filters, THE System SHALL display "Showing: All Categories" as the default state
11. THE Filter_Display SHALL appear above the search results grid in a prominent location
12. THE System SHALL handle undefined filter parameters gracefully without generating PHP errors

### Requirement 5: Performance and Error Handling

**User Story:** As a user, I want the page to load smoothly and handle errors gracefully, so that I have a reliable and responsive browsing experience.

#### Acceptance Criteria

1. WHEN the System loads additional files via infinite scroll, THE request SHALL complete within 2 seconds under normal network conditions
2. THE System SHALL implement request debouncing to prevent multiple simultaneous load requests when scrolling rapidly
3. WHEN a file load request fails, THE System SHALL retry the request up to 2 times before displaying an error
4. WHEN displaying loading indicators, THE System SHALL use smooth fade-in and fade-out animations (minimum 200ms duration)
5. THE System SHALL cache loaded file data in memory to prevent redundant requests when scrolling up and down
6. WHEN the user navigates away from the page and returns, THE System SHALL reset to the initial state (first 12 files)
7. THE Sticky_Navigation transitions SHALL use CSS transforms for optimal performance (GPU acceleration)
8. THE System SHALL ensure all File_Card click handlers remain functional after new files are loaded via infinite scroll
9. WHEN the browser window is resized, THE Sticky_Navigation SHALL maintain correct positioning and layout
10. THE System SHALL handle edge cases where the viewport height is larger than the initial content (no scroll trigger)
