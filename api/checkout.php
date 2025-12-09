<?php
/**
 * Visitor Check-Out API Endpoint
 * 
 * Handles visitor check-out requests by updating the visit_log entry
 * with the current check-out time.
 * 
 * HTTP Method: POST
 * Content-Type: application/json
 * 
 * Request Body:
 * {
 *   "visitor_id": 123
 * }
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "message": "Successfully checked out"
 * }
 * 
 * Error Response:
 * {
 *   "error": "Error message"
 * }
 * 
 * Possible Errors:
 * - "Missing visitor_id" - visitor_id not provided in request
 * - "Visitor is not currently checked in" - No active check-in found
 * - "Failed to check out visitor" - Database update failed
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
// Only POST requests are accepted
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ============================================================================
// MAIN CHECK-OUT PROCESSING
// ============================================================================

try {
    // Parse JSON request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required field
    if (!$input || !isset($input['visitor_id'])) {
        throw new Exception('Missing visitor_id');
    }
    
    // Initialize database connection
    $db = new VisitorDatabase();
    
    // ========================================================================
    // VALIDATE CHECK-IN STATUS
    // Ensure visitor is actually checked in before allowing check-out
    // ========================================================================
    
    if (!$db->isCurrentlyCheckedIn($input['visitor_id'])) {
        throw new Exception('Visitor is not currently checked in');
    }
    
    // ========================================================================
    // PERFORM CHECK-OUT
    // Updates visit_log entry with current timestamp
    // ========================================================================
    
    $success = $db->checkOut($input['visitor_id']);
    
    if (!$success) {
        throw new Exception('Failed to check out visitor');
    }
    
    // ========================================================================
    // SUCCESS RESPONSE
    // ========================================================================
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully checked out'
    ]);
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING
    // Return error response with appropriate HTTP status code
    // ========================================================================
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
