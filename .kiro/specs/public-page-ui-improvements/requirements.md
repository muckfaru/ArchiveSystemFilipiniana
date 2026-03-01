# Requirements Document

## Introduction

This document specifies the requirements for UI improvements to the public.php page of the Quezon City Public Library Archive System. The improvements focus on enhancing user navigation within the admin login modal and updating the application's color scheme to improve visual consistency and accessibility.

## Glossary

- **Admin_Login_Modal**: The modal dialog that appears when users click the "Admin Login" button in the public page header
- **Sidebar**: The fixed navigation panel displayed on the left side of admin pages (dashboard.php and related pages)
- **Primary_Color**: The main brand color used throughout the application (#3A9AFF)
- **Public_Page**: The public-facing landing page (public.php) where users can browse and search the archive
- **Back_Button**: A navigation button that returns users to the public page from the admin login modal

## Requirements

### Requirement 1: Back to Home Navigation

**User Story:** As a user viewing the admin login modal, I want a way to return to the public page, so that I can easily navigate back without closing the modal manually.

#### Acceptance Criteria

1. WHEN the Admin_Login_Modal is displayed, THE System SHALL display a "Back to Home" button below the "Admin Login" button
2. WHEN a user clicks the "Back to Home" button, THE System SHALL close the Admin_Login_Modal and return focus to the Public_Page
3. WHEN the "Back to Home" button is displayed, THE System SHALL style it consistently with the existing modal design patterns
4. WHEN the Admin_Login_Modal switches to the forgot password view, THE "Back to Home" button SHALL remain visible and functional

### Requirement 2: Sidebar Color Scheme Update

**User Story:** As an administrator using the system, I want the sidebar to have a clean white background with grey text, so that the interface feels modern and less visually overwhelming.

#### Acceptance Criteria

1. WHEN the Sidebar is rendered, THE System SHALL apply a white background color (#FFFFFF)
2. WHEN the Sidebar is rendered, THE System SHALL display navigation icons and text in grey color
3. WHEN a navigation item is in an active state, THE System SHALL maintain sufficient contrast for accessibility
4. WHEN a user hovers over navigation items, THE System SHALL provide visual feedback using the grey color scheme
5. WHEN the Sidebar footer displays user information, THE System SHALL use grey text colors consistent with the navigation

### Requirement 3: Primary Color Consistency

**User Story:** As a user of the system, I want all interactive elements to use a consistent blue color (#3A9AFF), so that the interface feels cohesive and professional.

#### Acceptance Criteria

1. THE System SHALL use #3A9AFF as the Primary_Color for all buttons, links, and interactive elements
2. WHEN displaying active navigation states in the Sidebar, THE System SHALL use the Primary_Color for highlighting
3. WHEN rendering the search button on the Public_Page, THE System SHALL use the Primary_Color
4. WHEN displaying the admin login submit button, THE System SHALL use the Primary_Color
5. WHEN showing hover states for interactive elements, THE System SHALL use appropriate shades of the Primary_Color

### Requirement 4: Functionality Preservation

**User Story:** As a system administrator, I want all existing functionality to continue working after the UI changes, so that no features are broken by the visual updates.

#### Acceptance Criteria

1. WHEN the UI changes are applied, THE System SHALL preserve all existing modal open and close functionality
2. WHEN the UI changes are applied, THE System SHALL preserve all existing sidebar navigation functionality
3. WHEN the UI changes are applied, THE System SHALL preserve all existing form submission functionality
4. WHEN the UI changes are applied, THE System SHALL preserve all existing authentication workflows
5. WHEN the UI changes are applied, THE System SHALL preserve all existing responsive behavior for mobile devices
