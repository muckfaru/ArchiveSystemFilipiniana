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

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Languages Table
CREATE TABLE IF NOT EXISTS languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(10) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Newspapers Table
CREATE TABLE IF NOT EXISTS newspapers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    publication_date DATE DEFAULT NULL,
    edition VARCHAR(100) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    language_id INT DEFAULT NULL,
    page_count INT DEFAULT NULL,
    keywords TEXT DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL,
    volume_issue VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
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
    deleted_by INT DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users (id),
    FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

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
        'settings_update'
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