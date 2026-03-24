-- Archive System Database Schema
-- Quezon City Public Library

CREATE DATABASE IF NOT EXISTS archive_system;

USE archive_system;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    role ENUM('super_admin', 'admin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Categories Table (kept for reference data - can be used in custom field options)
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Languages Table (kept for reference data - can be used in custom field options)
CREATE TABLE IF NOT EXISTS languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(10) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Form Templates Table
-- Stores reusable form templates with predefined field configurations
CREATE TABLE IF NOT EXISTS form_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    is_active TINYINT(1) DEFAULT 0 COMMENT '1 = currently active template, 0 = inactive',
    modified_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_is_active (is_active),
    CONSTRAINT fk_form_modified_by FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores form template definitions';

-- Form Fields Table
-- Stores fields within form templates
CREATE TABLE IF NOT EXISTS form_fields (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_id INT NOT NULL COMMENT 'References form_templates.id',
    field_label VARCHAR(255) NOT NULL COMMENT 'Display label shown to users',
    field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio', 'tags') NOT NULL,
    field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio options',
    is_required TINYINT(1) DEFAULT 0 COMMENT '1 = required field, 0 = optional',
    display_order INT DEFAULT 0 COMMENT 'Sort order for display on forms',
    help_text TEXT DEFAULT NULL COMMENT 'Optional help text displayed near field',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE CASCADE,
    INDEX idx_form_order (form_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores fields within form templates';

-- Metadata Display Configuration Table
-- Controls which fields appear on cards vs modals
CREATE TABLE IF NOT EXISTS metadata_display_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_field_id INT NOT NULL COMMENT 'References form_fields.id',
    show_on_card TINYINT(1) DEFAULT 1 COMMENT '1 = show on file cards, 0 = hide',
    show_in_modal TINYINT(1) DEFAULT 1 COMMENT '1 = show in detail modal, 0 = hide',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_form_field (form_field_id),
    FOREIGN KEY (form_field_id) REFERENCES form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Controls metadata field visibility in UI';

-- Newspapers Table
-- Note: Metadata fields (publication_date, edition, category, language, etc.) 
-- are now stored dynamically in form_fields and custom_metadata_values tables
CREATE TABLE IF NOT EXISTS newspapers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size BIGINT NOT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    -- Bulk image gallery support
    is_bulk_image TINYINT(1) DEFAULT 0,
    image_paths JSON DEFAULT NULL,
    -- Conversion tracking for MOBI to EPUB
    conversion_status ENUM(
        'uploaded',
        'converting',
        'converted',
        'failed'
    ) DEFAULT 'uploaded',
    epub_path VARCHAR(500) DEFAULT NULL,
    conversion_error TEXT DEFAULT NULL,
    converted_at DATETIME DEFAULT NULL,
    -- User tracking
    uploaded_by INT NOT NULL,
    -- Soft delete
    deleted_by INT DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users (id),
    FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Custom Metadata Values Table
-- Stores user-entered custom metadata values for each file
CREATE TABLE IF NOT EXISTS custom_metadata_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL COMMENT 'References newspapers.id',
    field_id INT DEFAULT NULL COMMENT 'References form_fields.id',
    field_value TEXT DEFAULT NULL COMMENT 'User-entered value for this field',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES newspapers(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE SET NULL,
    INDEX idx_file_id (file_id),
    INDEX idx_field_id (field_id),
    UNIQUE KEY unique_file_field (file_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newspaper Views Table
-- Stores newspaper view records for analytics
CREATE TABLE IF NOT EXISTS newspaper_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    newspaper_id INT NOT NULL COMMENT 'References newspapers.id',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP address of viewer (IPv4 or IPv6)',
    view_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when view occurred',
    INDEX idx_newspaper_date (newspaper_id, view_date),
    INDEX idx_view_date (view_date),
    FOREIGN KEY (newspaper_id) REFERENCES newspapers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores newspaper view records for analytics';

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action ENUM(
        'create_user',
        'edit_user',
        'delete_user',
        'upload',
        'edit',
        'delete',
        'restore',
        'permanent_delete',
        'login',
        'logout',
        'settings_update',
        'custom_metadata_update',
        'form_template_create',
        'form_template_update',
        'form_template_delete',
        'export_report'
    ) NOT NULL,
    target_title VARCHAR(255) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Password Resets Table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =====================
-- Default Data Inserts
-- =====================

-- Default Categories
INSERT INTO
    categories (name)
VALUES ('Politics'),
    ('Sports'),
    ('Business'),
    ('Culture'),
    ('Entertainment'),
    ('Technology'),
    ('Health'),
    ('Education'),
    ('Science'),
    ('Local News');

-- Default Languages
INSERT INTO
    languages (name, code)
VALUES ('English', 'en'),
    ('Filipino', 'fil'),
    ('Tagalog', 'tl'),
    ('Cebuano', 'ceb'),
    ('Ilocano', 'ilo');

-- Default Admin User (password: admin123)
INSERT INTO
    users (
        username,
        password,
        full_name,
        email,
        role,
        status
    )
VALUES (
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Super Administrator',
        'admin@qcpl.gov.ph',
        'super_admin',
        'active'
    );
-- Note: Default password is 'password' - change it after first login!

-- Default Settings
INSERT INTO
    settings (key_name, value)
VALUES (
        'storage_path',
        'uploads/newspapers'
    ),
    ('dark_mode', '0'),
    ('auto_delete_days', '30'),
    (
        'site_name',
        'Quezon City Public Library'
    ),
    (
        'max_upload_size',
        '104857600'
    );

-- =====================
-- Default Form Template & Fields
-- The system requires an active form template with specific field labels
-- for category filtering, date filtering, browse page, and dashboard to work.
-- =====================

-- Default Form Template
INSERT INTO form_templates (id, name, description, status, is_active)
VALUES (1, 'Default Archive Form', 'Default metadata form for archive documents', 'active', 1);

-- Default Form Fields
-- This matches the current active upload form configuration used by the team.
INSERT INTO form_fields (id, form_id, field_label, field_type, field_options, is_required, display_order, help_text)
VALUES 
    (1, 1, 'Title', 'text', NULL, 1, 0, 'Document title'),
    (2, 1, 'Publication Type', 'select', '["Newspaper","Magazine"]', 0, 1, NULL),
    (3, 1, 'Publication Date', 'date', NULL, 0, 2, 'Date of publication (YYYY-MM-DD)'),
    (4, 1, 'Category', 'select', '["Politics","Sports","Business","Culture","Entertainment","Technology","Health","Education","Science","Local News"]', 0, 3, 'Document category'),
    (5, 1, 'Language', 'select', '["English","Filipino","Tagalog","Cebuano","Ilocano"]', 0, 4, 'Document language'),
    (6, 1, 'Keywords', 'tags', NULL, 0, 5, 'Searchable keywords or tags'),
    (7, 1, 'Description', 'textarea', NULL, 0, 6, 'Brief description of the document');

-- Default Metadata Display Configuration
-- Controls which fields show on cards vs modals
INSERT INTO metadata_display_config (form_field_id, show_on_card, show_in_modal)
VALUES 
    (1, 1, 1),
    (2, 1, 1),
    (3, 1, 1),
    (4, 1, 1),
    (5, 1, 1),
    (6, 1, 1),
    (7, 1, 1);
