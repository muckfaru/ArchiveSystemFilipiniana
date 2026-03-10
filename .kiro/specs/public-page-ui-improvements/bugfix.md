# Bugfix Requirements Document

## Introduction

This bugfix addresses multiple UI/UX issues affecting user experience across public pages and the admin dashboard in a PHP application running on XAMPP with Bootstrap 5.3.2. The issues include non-responsive navigation on mobile devices, misleading dashboard statistics, inaccessible collections page routing, and excessive padding in metadata display configuration. These issues impact usability across different screen sizes and prevent users from accessing key features.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the public navigation (`.public-nav`) is viewed on mobile devices (screen width < 768px) THEN the Home and Browse buttons break layout due to `position: absolute; left: 50%; transform: translateX(-50%);` CSS positioning

1.2 WHEN the admin dashboard is loaded THEN the system displays an "Issues Count" stat card showing page count instead of meaningful analytics

1.3 WHEN a user navigates to the collections page (`user_pages/collections.php`) THEN the system returns a 404 error despite the file existing

1.4 WHEN the metadata display configuration page is loaded THEN the system displays excessive spacing above the "Metadata Display Configuration Configure how..." text due to improper margin/padding settings

### Expected Behavior (Correct)

2.1 WHEN the public navigation is viewed on mobile devices (screen width < 768px) THEN the system SHALL display a hamburger menu that expands/collapses navigation items vertically

2.2 WHEN the admin dashboard is loaded THEN the system SHALL display a "Total Views" stat card showing the sum of all file views from the `newspaper_views` table with a `bi-eye` or `bi-graph-up` icon

2.3 WHEN a user navigates to the collections page THEN the system SHALL successfully load the page without 404 errors by resolving routing or controller issues

2.4 WHEN the metadata display configuration page is loaded THEN the system SHALL display proper spacing with `margin-top: 0; padding-top: 0;` applied to the configuration text container

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the public navigation is viewed on desktop/laptop screens (screen width >= 768px) THEN the system SHALL CONTINUE TO display the centered horizontal navigation layout

3.2 WHEN other dashboard stat cards are displayed THEN the system SHALL CONTINUE TO show their current metrics without modification

3.3 WHEN users navigate to other public pages (public.php, browse.php) THEN the system SHALL CONTINUE TO load successfully without routing errors

3.4 WHEN the metadata display configuration page functionality is used THEN the system SHALL CONTINUE TO save and load configuration settings correctly

3.5 WHEN the responsive navigation hamburger menu is used on mobile THEN the system SHALL CONTINUE TO maintain Bootstrap 5.3.2 styling consistency with the rest of the application

3.6 WHEN the Total Views stat card is displayed THEN the system SHALL CONTINUE TO use the existing analytics infrastructure and `newspaper_views` table schema without database modifications

3.7 WHEN users interact with other admin dashboard features THEN the system SHALL CONTINUE TO function without interference from the stat card changes
