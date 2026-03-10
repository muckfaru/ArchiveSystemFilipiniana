# Bugfix Requirements Document

## Introduction

Public users attempting to read/view full documents from the public page (http://localhost/qcpl/ArchiveSystemFilipiniana/user_pages/public.php) are incorrectly redirected to the login page. This prevents unauthenticated users from accessing document content, which contradicts the intended public access model where only administrative functions should require authentication.

The root cause is that the "Read Full Document" button in the public page modal links to `/admin_pages/reader.php`, which includes the authentication middleware (`backend/core/auth.php`) that redirects unauthenticated users to the login page.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a public user clicks "Read Full Document" on the public page THEN the system redirects them to the login page (http://localhost/qcpl/ArchiveSystemFilipiniana/auth/login.php)

1.2 WHEN a public user attempts to access `/admin_pages/reader.php?id={fileId}` directly THEN the system redirects them to the login page

1.3 WHEN a public user attempts to access `/user_pages/reader.php?id={fileId}` directly THEN the system redirects them to the login page

### Expected Behavior (Correct)

2.1 WHEN a public user clicks "Read Full Document" on the public page THEN the system SHALL display the document reader without requiring authentication

2.2 WHEN a public user attempts to access a public reader page with a valid file ID THEN the system SHALL display the document content without authentication

2.3 WHEN a public user attempts to access a public reader page with an invalid or deleted file ID THEN the system SHALL redirect them back to the public page with an appropriate message

### Unchanged Behavior (Regression Prevention)

3.1 WHEN an authenticated admin user accesses `/admin_pages/reader.php?id={fileId}` THEN the system SHALL CONTINUE TO display the admin document reader with full functionality

3.2 WHEN an unauthenticated user attempts to access any admin page (dashboard, upload, settings, etc.) THEN the system SHALL CONTINUE TO redirect them to the login page

3.3 WHEN an authenticated admin user views a document THEN the system SHALL CONTINUE TO log the activity in the activity log

3.4 WHEN any user views a document THEN the system SHALL CONTINUE TO record the view for analytics purposes

3.5 WHEN a document is marked as deleted (deleted_at IS NOT NULL) THEN the system SHALL CONTINUE TO prevent access to that document from both public and admin readers
