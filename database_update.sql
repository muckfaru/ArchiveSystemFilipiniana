-- Create Books Table
CREATE TABLE IF NOT EXISTS books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path_mobi VARCHAR(500) NOT NULL,
    file_path_epub VARCHAR(500),
    remote_job_id VARCHAR(100),
    status ENUM(
        'uploaded',
        'converting',
        'converted',
        'failed'
    ) DEFAULT 'uploaded',
    conversion_error TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Add support for bulk image uploads
ALTER TABLE newspapers
ADD COLUMN IF NOT EXISTS is_bulk_image TINYINT DEFAULT 0 AFTER file_type;

ALTER TABLE newspapers
ADD COLUMN IF NOT EXISTS image_paths JSON DEFAULT NULL AFTER is_bulk_image;

ALTER TABLE newspapers MODIFY COLUMN file_path VARCHAR(500) NULL;

-- Add soft delete support for users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS deleted_by INT DEFAULT NULL;

ALTER TABLE users
ADD CONSTRAINT fk_users_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL;