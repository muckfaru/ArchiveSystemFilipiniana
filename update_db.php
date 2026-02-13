<?php
/**
 * Database Updater
 * Helper script to execute schema updates
 */

require_once __DIR__ . '/includes/config.php';

try {
    echo "Updating database schema...\n";

    // Add deleted_at to users
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL");
    echo "- Added deleted_at column to users\n";

    // Add deleted_by to users
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by INT DEFAULT NULL");
    echo "- Added deleted_by column to users\n";

    // Add constraint (check if exists first usually, but simplified here since IF NOT EXISTS doesn't apply to constraints easily in all MySQL versions without procedure)
    // We'll wrap in try-catch to avoid error if it exists
    try {
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL");
        echo "- Added foreign key constraint fk_users_deleted_by\n";
    } catch (PDOException $e) {
        // Ignore if duplicate key error
        echo "- Constraint might already exist: " . $e->getMessage() . "\n";
    }

    echo "Database update completed successfully.\n";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>