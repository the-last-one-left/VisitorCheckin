<?php
/**
 * Clear Visit History API Endpoint
 * 
 * **DESTRUCTIVE OPERATION**
 * Permanently deletes all visit_log records while preserving visitor information
 * and orientation completions. Use with extreme caution.
 * 
 * HTTP Method: POST (destructive operation requires POST)
 * Content-Type: application/json
 * 
 * Request: No parameters required
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "deleted_count": 523,
 *   "message": "Successfully cleared 523 visit records"
 * }
 * 
 * Error Response:
 * {
 *   "error": "Error message"
 * }
 * 
 * What Gets Deleted:
 * - ALL visit_log entries (check-ins and check-outs)
 * - All visit duration data
 * - All historical visit timestamps
 * 
 * What Is Preserved:
 * - Visitor personal information (name, email, phone, company)
 * - Orientation completion status
 * - Training certification dates
 * - Badge numbers
 * - Staff contacts
 * 
 * Use Cases:
 * - Starting fresh after testing/setup
 * - Annual data cleanup (after exporting for records)
 * - GDPR compliance (remove visit tracking while keeping contacts)
 * 
 * **WARNING: THIS OPERATION CANNOT BE UNDONE!**
 * 
 * Best Practices:
 * 1. ALWAYS export CSV report BEFORE clearing
 * 2. ALWAYS backup database BEFORE clearing
 * 3. Consider using auto-purge for ongoing maintenance instead
 * 4. Document reason for clearing in change management log
 * 
 * Security Notes:
 * - Should require admin authentication
 * - Should prompt for confirmation in UI
 * - Consider implementing soft-delete instead
 * - Audit log recommended for compliance
 * 
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.0
 */

// Set JSON response header
header('Content-Type: application/json');

// Include database class
require_once 'database.php';

// ============================================================================
// REQUEST METHOD VALIDATION
// Only POST requests accepted (destructive operation)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ============================================================================
// CLEAR ALL VISIT HISTORY
// **DESTRUCTIVE OPERATION** - Permanently deletes all visit_log records
// ============================================================================

try {
    // Initialize database connection
    $db = new VisitorDatabase();
    
    // ========================================================================
    // GET COUNT OF RECORDS TO BE DELETED
    // For confirmation message and audit trail
    // ========================================================================
    
    $stmt = $db->pdo->prepare("SELECT COUNT(*) as count FROM visit_log");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // ========================================================================
    // DELETE ALL VISIT RECORDS
    // Removes all check-in/check-out history
    // ========================================================================
    
    $stmt = $db->pdo->prepare("DELETE FROM visit_log");
    $success = $stmt->execute();
    
    if (!$success) {
        throw new Exception('Failed to clear visit records');
    }
    
    // ========================================================================
    // RESET AUTO-INCREMENT COUNTER
    // SQLite-specific: Reset ID sequence to start fresh
    // ========================================================================
    
    $db->pdo->exec("DELETE FROM sqlite_sequence WHERE name='visit_log'");
    
    // ========================================================================
    // SUCCESS RESPONSE
    // Return count of deleted records for confirmation
    // ========================================================================
    
    echo json_encode([
        'success' => true,
        'deleted_count' => $count,
        'message' => "Successfully cleared $count visit records"
    ]);
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING
    // Return error response with appropriate HTTP status code
    // ========================================================================
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
