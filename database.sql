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

-- Custom Metadata Fields Table
-- Stores dynamic metadata field definitions created by admins
CREATE TABLE IF NOT EXISTS custom_metadata_fields (
    id INT PRIMARY KEY AUTO_INCREMENT,
    field_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Internal identifier (e.g., author_name)',
    field_label VARCHAR(255) NOT NULL COMMENT 'Display label shown to users',
    field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio') NOT NULL,
    field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio options',
    is_required TINYINT(1) DEFAULT 0 COMMENT '1 = required field, 0 = optional',
    is_enabled TINYINT(1) DEFAULT 1 COMMENT '1 = active, 0 = disabled/soft-deleted',
    display_order INT DEFAULT 0 COMMENT 'Sort order for display on forms',
    validation_rules TEXT DEFAULT NULL COMMENT 'JSON object with validation config (regex, min/max)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled_order (is_enabled, display_order),
    INDEX idx_field_name (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Templates Table
-- Stores reusable form templates with predefined field configurations
CREATE TABLE IF NOT EXISTS form_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    field_config TEXT NOT NULL COMMENT 'JSON array of field configurations',
    is_default TINYINT(1) DEFAULT 0 COMMENT '1 = default template, 0 = custom template',
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_template_name (template_name),
    INDEX idx_is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newspapers Table
-- Note: Metadata fields (publication_date, edition, category, language, etc.) 
-- are now stored dynamically in custom_metadata_fields and custom_metadata_values tables
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
    image_paths TEXT DEFAULT NULL,
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
    -- Bulk image gallery support
    is_bulk_image TINYINT(1) DEFAULT 0,
    image_paths JSON DEFAULT NULL,
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
    field_id INT DEFAULT NULL COMMENT 'References custom_metadata_fields.id',
    field_value TEXT DEFAULT NULL COMMENT 'User-entered value for this field',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES newspapers(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES custom_metadata_fields(id) ON DELETE SET NULL,
    INDEX idx_file_id (file_id),
    INDEX idx_field_id (field_id),
    UNIQUE KEY unique_file_field (file_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
        'form_template_delete'
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