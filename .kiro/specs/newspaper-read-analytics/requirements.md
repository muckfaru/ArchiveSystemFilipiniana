# Requirements Document

## Introduction

This document specifies requirements for a newspaper read analytics feature in an existing PHP newspaper archiving system. The feature tracks how many unique visitors read each newspaper file and provides administrators with statistical views aggregated by time periods (daily, weekly, monthly, yearly). The system must work for non-authenticated public users and use session-based deduplication to prevent counting multiple page refreshes as separate reads.

## Glossary

- **Analytics_System**: The newspaper read analytics feature being implemented
- **View_Tracker**: The component responsible for recording newspaper view events
- **View_Record**: A database entry containing newspaper_id, ip_address, and view_date
- **Session_Manager**: The component managing PHP sessions for deduplication
- **Analytics_Display**: The admin interface component showing read statistics
- **Newspaper_File**: An archived newspaper document in the system
- **Unique_Reader**: A visitor identified by unique IP address within a time period
- **Admin_User**: An authenticated administrator with access to analytics data
- **Public_User**: A non-authenticated visitor browsing newspaper archives

## Requirements

### Requirement 1: Database Schema for View Tracking

**User Story:** As a system administrator, I want a dedicated database table to store view records, so that the system can track newspaper readership over time.

#### Acceptance Criteria

1. THE Analytics_System SHALL create a table named newspaper_views with columns: id (INT AUTO_INCREMENT PRIMARY KEY), newspaper_id (INT NOT NULL), ip_address (VARCHAR 45), view_date (DATETIME DEFAULT CURRENT_TIMESTAMP)
2. THE Analytics_System SHALL create an index on (newspaper_id, view_date) for query performance
3. THE Analytics_System SHALL store ip_address values using VARCHAR(45) to support both IPv4 and IPv6 addresses

### Requirement 2: View Recording with Session Deduplication

**User Story:** As a system administrator, I want to record each unique newspaper view without counting page refreshes, so that analytics reflect actual readership rather than refresh behavior.

#### Acceptance Criteria

1. WHEN a Public_User opens a Newspaper_File, THE View_Tracker SHALL start a PHP session if one does not exist
2. WHEN a Public_User opens a Newspaper_File, THE View_Tracker SHALL check if the current session has already recorded a view for that newspaper_id
3. IF the current session has not recorded a view for the newspaper_id, THEN THE View_Tracker SHALL insert a View_Record with the newspaper_id, IP address from $_SERVER['REMOTE_ADDR'], and current timestamp
4. WHEN a View_Record is inserted, THE Session_Manager SHALL store a session key indicating the view has been recorded for that newspaper_id
5. IF the current session has already recorded a view for the newspaper_id, THEN THE View_Tracker SHALL not insert a duplicate View_Record

### Requirement 3: Daily Read Statistics

**User Story:** As an Admin_User, I want to see how many unique readers viewed a newspaper today, so that I can monitor current interest levels.

#### Acceptance Criteria

1. WHEN an Admin_User requests daily statistics for a Newspaper_File, THE Analytics_System SHALL count distinct ip_address values WHERE DATE(view_date) equals CURDATE()
2. THE Analytics_Display SHALL present the daily read count with the label "Reads Today"

### Requirement 4: Weekly Read Statistics

**User Story:** As an Admin_User, I want to see how many unique readers viewed a newspaper this week, so that I can track weekly engagement trends.

#### Acceptance Criteria

1. WHEN an Admin_User requests weekly statistics for a Newspaper_File, THE Analytics_System SHALL count distinct ip_address values WHERE YEARWEEK(view_date, 1) equals YEARWEEK(CURDATE(), 1)
2. THE Analytics_Display SHALL present the weekly read count with the label "Reads This Week"

### Requirement 5: Monthly Read Statistics

**User Story:** As an Admin_User, I want to see how many unique readers viewed a newspaper this month, so that I can analyze monthly readership patterns.

#### Acceptance Criteria

1. WHEN an Admin_User requests monthly statistics for a Newspaper_File, THE Analytics_System SHALL count distinct ip_address values WHERE MONTH(view_date) equals MONTH(CURDATE()) AND YEAR(view_date) equals YEAR(CURDATE())
2. THE Analytics_Display SHALL present the monthly read count with the label "Reads This Month"

### Requirement 6: Yearly Read Statistics

**User Story:** As an Admin_User, I want to see how many unique readers viewed a newspaper this year, so that I can evaluate annual readership performance.

#### Acceptance Criteria

1. WHEN an Admin_User requests yearly statistics for a Newspaper_File, THE Analytics_System SHALL count distinct ip_address values WHERE YEAR(view_date) equals YEAR(CURDATE())
2. THE Analytics_Display SHALL present the yearly read count with the label "Reads This Year"

### Requirement 7: Analytics Display Integration

**User Story:** As an Admin_User, I want to see reading analytics when viewing a newspaper file, so that I can quickly assess its popularity.

#### Acceptance Criteria

1. WHEN an Admin_User views a Newspaper_File, THE Analytics_Display SHALL show all four time period statistics (daily, weekly, monthly, yearly) in a single summary
2. THE Analytics_Display SHALL format the statistics as "Reading Analytics - Today: [count], This Week: [count], This Month: [count], This Year: [count]"
3. THE Analytics_Display SHALL display statistics only to Admin_User accounts, not to Public_User accounts

### Requirement 8: Most Read Newspapers Report

**User Story:** As an Admin_User, I want to see which newspapers are most popular, so that I can understand user preferences and prioritize digitization efforts.

#### Acceptance Criteria

1. WHERE the most read newspapers feature is enabled, THE Analytics_System SHALL aggregate View_Records by newspaper_id and count total distinct ip_address values
2. WHERE the most read newspapers feature is enabled, THE Analytics_Display SHALL show the top 10 newspapers ordered by total unique reader count in descending order
3. WHERE the most read newspapers feature is enabled, THE Analytics_Display SHALL label the report as "Top 10 Most Read Newspapers"

### Requirement 9: Performance and Non-Interference

**User Story:** As a system administrator, I want the analytics feature to operate efficiently without impacting existing functionality, so that users experience no degradation in system performance.

#### Acceptance Criteria

1. THE View_Tracker SHALL use indexed database queries for all analytics calculations
2. THE Analytics_System SHALL not modify existing newspaper upload, metadata management, category management, file preview, or admin authentication features
3. WHEN a Public_User opens a Newspaper_File, THE View_Tracker SHALL complete view recording within 100 milliseconds
4. THE Analytics_System SHALL extend the existing system without requiring changes to core archive functionality
