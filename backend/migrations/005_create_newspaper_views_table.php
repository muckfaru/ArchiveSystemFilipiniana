<?php
/**
 * Migration: Create Newspaper Views Table
 * Archive System - Quezon City Public Library
 * 
 * This migration creates the database schema for the newspaper read analytics system:
 * - newspaper_views: Stores view records with newspaper_id, ip_address, and view_date
 * 
 * Usage: php backend/migrations/005_create_newspaper_views_table.php
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        echo "Starting migration: Create Newspaper Views Table\n";
        echo "================================================\n\n";
        
        $pdo->beginTransaction();
        
        // Check if table already exists (idempotency)
        $stmt = $pdo->query("SHOW TABLES LIKE 'newspaper_views'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Migration already applied. Table exists.\n";
            $pdo->commit();
            return;
        }
        
        echo "Creating newspaper_views table...\n";
        
        // Create newspaper_views table
        $pdo->exec("
            CREATE TABLE newspaper_views (
                id INT PRIMARY KEY AUTO_INCREMENT,
                newspaper_id INT NOT NULL COMMENT 'References newspapers.id',
                ip_address VARCHAR(45) NOT NULL COMMENT 'IP address of viewer (IPv4 or IPv6)',
                view_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when view occurred',
                INDEX idx_newspaper_date (newspaper_id, view_date),
                INDEX idx_view_date (view_date),
                FOREIGN KEY (newspaper_id) REFERENCES newspapers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores newspaper view records for analytics'
        ");
        
        echo "✓ newspaper_views table created\n\n";
        
        $pdo->commit();
        
        echo "================================================\n";
        echo "✓ Migration completed successfully!\n\n";
        echo "Tables created:\n";
        echo "  - newspaper_views\n";
        echo "    * Composite index on (newspaper_id, view_date)\n";
        echo "    * Index on view_date\n";
        echo "    * Foreign key to newspapers(id) with CASCADE DELETE\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

function rollbackMigration($pdo) {
    try {
        echo "Starting rollback: Drop Newspaper Views Table\n";
        echo "================================================\n\n";
        
        $pdo->beginTransaction();
        
        echo "Dropping newspaper_views table...\n";
        $pdo->exec("DROP TABLE IF EXISTS newspaper_views");
        echo "✓ newspaper_views table dropped\n\n";
        
        $pdo->commit();
        
        echo "================================================\n";
        echo "✓ Rollback completed successfully!\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Rollback failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Command-line interface
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'up';
    
    if ($command === 'up') {
        runMigration($pdo);
    } elseif ($command === 'down') {
        echo "WARNING: This will delete all newspaper view records!\n";
        echo "Are you sure you want to rollback? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if ($line === 'yes') {
            rollbackMigration($pdo);
        } else {
            echo "Rollback cancelled.\n";
        }
    } else {
        echo "Usage: php 005_create_newspaper_views_table.php [up|down]\n";
        echo "  up   - Run migration (default)\n";
        echo "  down - Rollback migration\n";
    }
} else {
    // Web interface (for testing only - should be disabled in production)
    echo "<pre>";
    runMigration($pdo);
    echo "</pre>";
}
