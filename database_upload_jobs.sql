USE archive_system;

CREATE TABLE IF NOT EXISTS upload_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    job_type ENUM(
        'bulk_image_cbz',
        'bulk_document_import'
    ) NOT NULL,
    status ENUM(
        'pending',
        'processing',
        'completed',
        'failed'
    ) DEFAULT 'pending',
    total_files INT DEFAULT 0,
    processed_files INT DEFAULT 0,
    result_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS upload_job_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    temp_file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size BIGINT DEFAULT 0,
    -- For document bulk upload, we might need metadata per item
    metadata JSON DEFAULT NULL,
    status ENUM('pending', 'success', 'error') DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (job_id) REFERENCES upload_jobs (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;