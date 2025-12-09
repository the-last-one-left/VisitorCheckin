<?php
/**
 * Database Setup Script
 * 
 * Creates the SQLite database and initializes all required tables and indexes
 * for the Suterra Guest Check-in System.
 * 
 * USAGE:
 * Run this script once during initial installation by visiting:
 * http://yourserver/setup.php
 * 
 * The script will:
 * 1. Create the /data/ directory if it doesn't exist
 * 2. Create visitors.db SQLite database
 * 3. Create visitors table (stores visitor information)
 * 4. Create visit_log table (stores check-in/check-out records)
 * 5. Create performance indexes for efficient queries
 * 
 * SAFETY:
 * - Uses CREATE TABLE IF NOT EXISTS (safe to re-run)
 * - Will not delete or modify existing data
 * - Can be run multiple times without issues
 * 
 * DATABASE SCHEMA:
 * 
 * visitors table:
 * - id: Auto-increment primary key
 * - name: Full visitor name
 * - email: Email address
 * - phone: 10-digit phone number
 * - company: Company name
 * - badge_number: Optional facility badge number
 * - suterra_contact: Suterra employee being visited
 * - contractor_orientation_completed: Boolean flag
 * - visitor_orientation_completed: Boolean flag
 * - training_type: 'none', 'contractor', or 'visitor'
 * - last_training_date: Date of last training completion
 * - training_expires_date: Training expiration date (contractors only)
 * - created_at: Record creation timestamp
 * 
 * visit_log table:
 * - id: Auto-increment primary key
 * - visitor_id: Foreign key to visitors.id
 * - check_in_time: Check-in timestamp
 * - check_out_time: Check-out timestamp (NULL if still checked in)
 * - tablet_id: Optional kiosk/tablet identifier
 * 
 * @package    SuterraGuestCheckin
 * @author     Pacific Office Automation
 * @version    2.0
 * @since      2024-01-15
 */

// ============================================================================
// DATABASE PATH CONFIGURATION
// ============================================================================

$db_path = __DIR__ . '/data/visitors.db';

// ============================================================================
// DATA DIRECTORY CREATION
// Ensure the data directory exists for database storage
// ============================================================================

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// ============================================================================
// DATABASE INITIALIZATION
// ============================================================================

try {
    // Connect to SQLite database (creates file if doesn't exist)
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ========================================================================
    // CREATE VISITORS TABLE
    // Stores all visitor information and orientation/training status
    // ========================================================================
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL,
        company TEXT NOT NULL,
        badge_number TEXT,
        suterra_contact TEXT,
        contractor_orientation_completed BOOLEAN DEFAULT 0,
        visitor_orientation_completed BOOLEAN DEFAULT 0,
        training_type TEXT DEFAULT 'none',
        last_training_date DATE,
        training_expires_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ========================================================================
    // CREATE VISIT_LOG TABLE
    // Tracks all check-in and check-out events
    // ========================================================================
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS visit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visitor_id INTEGER NOT NULL,
        check_in_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        check_out_time DATETIME,
        tablet_id TEXT,
        FOREIGN KEY (visitor_id) REFERENCES visitors(id)
    )");
    
    // ========================================================================
    // CREATE PERFORMANCE INDEXES
    // Optimize common query patterns
    // ========================================================================
    
    // Index for email lookups (visitor search)
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visitor_email ON visitors(email)");
    
    // Index for visitor_id lookups in visit_log (join operations)
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visit_log_visitor ON visit_log(visitor_id)");
    
    // Composite index for time-based queries (current visitors, recent visits)
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visit_log_times ON visit_log(check_in_time, check_out_time)");
    
    // ========================================================================
    // SUCCESS MESSAGE
    // ========================================================================
    
    echo "✅ Database setup completed successfully!<br><br>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Delete this setup.php file (no longer needed)<br>";
    echo "2. Go to <a href='index.php'>Check-in Page</a><br>";
    echo "3. Go to <a href='admin/'>Admin Dashboard</a> (default password: admin123)<br><br>";
    echo "<strong>Security Note:</strong><br>";
    echo "Change the admin password in admin/auth.php immediately!<br>";
    
} catch (PDOException $e) {
    // ========================================================================
    // ERROR HANDLING
    // Display detailed error information for troubleshooting
    // ========================================================================
    
    echo "❌ Database setup failed: " . $e->getMessage() . "<br><br>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Ensure the web server has write permissions to the /data/ directory<br>";
    echo "2. Check that PHP has SQLite support enabled (php-sqlite3)<br>";
    echo "3. Verify disk space is available<br>";
}
?>
