<?php
/**
 * Database Backup API Endpoint
 * 
 * Provides a download link for the complete SQLite database file.
 * Used by the admin dashboard's "Backup Database" button for data protection
 * and disaster recovery.
 * 
 * HTTP Method: GET
 * Content-Type: application/octet-stream (binary download)
 * 
 * Request: No parameters required
 * 
 * Success Response: Binary file download
 * - Filename format: visitor_database_backup_YYYY-MM-DD_HH-MM-SS.db
 * - Contains complete database including:
 *   * All visitor records
 *   * All visit history
 *   * Training certifications
 *   * Orientation completions
 * 
 * Error Response (JSON):
 * {
 *   "error": "Error message"
 * }
 * 
 * Usage:
 * - Click "Backup Database" in admin dashboard
 * - Browser will download the .db file
 * - Store backup safely off-site
 * - Can be restored by replacing visitors.db file
 * 
 * Security Notes:
 * - Should be protected by admin authentication in production
 * - Contains sensitive visitor personal information
 * - Regular backups recommended (weekly or daily)
 * 
 * Restoration:
 * To restore from backup:
 * 1. Stop the web server (IIS/Apache)
 * 2. Replace data/visitors.db with backup file
 * 3. Restart web server
 * 4. Verify system functionality
 * 
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.0
 */

// ============================================================================
// REQUEST METHOD VALIDATION
// Only GET requests are accepted
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ============================================================================
// DATABASE BACKUP DOWNLOAD
// ============================================================================

try {
    // Locate database file
    $db_path = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/visitors.db';
    
    // Verify file exists before attempting download
    if (!file_exists($db_path)) {
        throw new Exception('Database file not found');
    }
    
    // ========================================================================
    // SET DOWNLOAD HEADERS
    // Configure browser to download file instead of displaying
    // ========================================================================
    
    // Generate timestamped filename for easy identification
    $filename = 'visitor_database_backup_' . date('Y-m-d_H-i-s') . '.db';
    
    // Binary file download headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($db_path));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // ========================================================================
    // STREAM FILE TO BROWSER
    // Output the file contents directly to response
    // ========================================================================
    
    readfile($db_path);
    exit;
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING
    // If download fails, return JSON error response
    // ========================================================================
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
