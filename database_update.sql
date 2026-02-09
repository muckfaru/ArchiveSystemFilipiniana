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