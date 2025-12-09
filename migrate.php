<?php
/**
 * Database Migration: Dual Orientation System
 * 
 * Upgrades the database from single-orientation (v1.0) to dual-orientation (v2.0).
 * This migration adds support for separate contractor and visitor orientations.
 * 
 * WHEN TO RUN:
 * Only needed when upgrading from version 1.0 to version 2.0 or later.
 * New installations using setup.php do NOT need this migration.
 * 
 * USAGE:
 * 1. Visit http://yourserver/migrate.php
 * 2. Wait for success confirmation
 * 3. Delete this file (optional, but recommended)
 * 
 * WHAT IT DOES:
 * - Adds 'contractor_orientation_completed' column
 * - Adds 'visitor_orientation_completed' column
 * - Migrates data from old 'orientation_completed' column (if exists)
 * - Preserves all existing visitor and visit data
 * 
 * SAFETY FEATURES:
 * - Checks if migration already completed (safe to re-run)
 * - Non-destructive (adds columns, doesn't delete data)
 * - Keeps old column for reference (SQLite limitation)
 * 
 * ROLLBACK:
 * Not needed - old column remains intact if issues occur.
 * 
 * @package    SuterraGuestCheckin
 * @subpackage Migrations
 * @author     Pacific Office Automation
 * @version    2.0
 * @since      2024-08-15
 */

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

$db_path = __DIR__ . '/data/visitors.db';

if (!file_exists($db_path)) {
    die('❌ Error: Database not found. Please run setup.php first.');
}

// ============================================================================
// MIGRATION PROCESS
// ============================================================================

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ========================================================================
    // CHECK EXISTING SCHEMA
    // Determine what columns exist to decide migration path
    // ========================================================================
    
    $stmt = $pdo->query("PRAGMA table_info(visitors)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasOldColumn = false;
    $hasNewColumns = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'orientation_completed') {
            $hasOldColumn = true;
        }
        if ($column['name'] === 'contractor_orientation_completed' || 
            $column['name'] === 'visitor_orientation_completed') {
            $hasNewColumns = true;
        }
    }
    
    // ========================================================================
    // CHECK IF ALREADY MIGRATED
    // ========================================================================
    
    if ($hasNewColumns) {
        echo "✅ Database already updated to support dual orientations.<br><br>";
        echo "No migration needed. This database is ready for version 2.0.<br><br>";
        echo "<strong>You can safely delete this migrate.php file.</strong>";
        exit;
    }
    
    // ========================================================================
    // ADD NEW ORIENTATION COLUMNS
    // ========================================================================
    
    echo "<h2>Running Dual Orientation Migration...</h2>";
    
    $pdo->exec("ALTER TABLE visitors ADD COLUMN contractor_orientation_completed BOOLEAN DEFAULT 0");
    echo "✅ Added contractor_orientation_completed column<br>";
    
    $pdo->exec("ALTER TABLE visitors ADD COLUMN visitor_orientation_completed BOOLEAN DEFAULT 0");
    echo "✅ Added visitor_orientation_completed column<br><br>";
    
    // ========================================================================
    // MIGRATE EXISTING DATA
    // If old column exists, migrate its data to new visitor orientation column
    // ========================================================================
    
    if ($hasOldColumn) {
        // Move old orientation data to visitor orientation (default assumption)
        // Most v1.0 users were using it for general visitors
        $pdo->exec("UPDATE visitors SET visitor_orientation_completed = orientation_completed WHERE orientation_completed = 1");
        
        echo "<strong>Data Migration:</strong><br>";
        echo "✅ Old orientation data moved to visitor_orientation_completed<br><br>";
        
        echo "<strong>Note:</strong><br>";
        echo "The old 'orientation_completed' column still exists but is no longer used.<br>";
        echo "This is normal - SQLite doesn't support DROP COLUMN directly.<br>";
        echo "The old column can be ignored safely.<br><br>";
    }
    
    // ========================================================================
    // SUCCESS SUMMARY
    // ========================================================================
    
    echo "<hr>";
    echo "<h3>✅ Migration Completed Successfully!</h3>";
    echo "<p>Database updated to support:</p>";
    echo "<ul>";
    echo "<li><strong>Contractor Safety Orientation 2024</strong> - Annual renewal required</li>";
    echo "<li><strong>Suterra Visitor Orientation 2025</strong> - One-time only</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Delete this migrate.php file (optional, but recommended)</li>";
    echo "<li>Test check-in with both contractor and visitor types</li>";
    echo "<li>Update orientation videos in /res/ folder if needed</li>";
    echo "</ol>";
    
    echo "<p><a href='index.php'>Go to Check-in Page</a> | ";
    echo "<a href='admin/'>Go to Admin Dashboard</a></p>";
    
} catch (PDOException $e) {
    // ========================================================================
    // ERROR HANDLING
    // ========================================================================
    
    echo "❌ Migration failed: " . $e->getMessage() . "<br><br>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Ensure database file has write permissions<br>";
    echo "2. Check that no other process has the database locked<br>";
    echo "3. Verify SQLite version supports ALTER TABLE<br>";
    echo "4. Contact support with the error message above<br>";
}
?>
