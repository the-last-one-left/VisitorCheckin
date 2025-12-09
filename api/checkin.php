<?php
/**
 * Visitor Check-In API Endpoint
 * 
 * Handles visitor check-in requests including:
 * - New visitor registration
 * - Returning visitor updates
 * - Contractor training requirement detection (new/expired only)
 * - Direct check-in for all visitors and trained contractors
 * 
 * Flow Logic:
 * - Contractors (new): needs_orientation = true → External form
 * - Contractors (expired training): needs_orientation = true → External form
 * - Contractors (valid training): needs_orientation = false → Direct check-in
 * - Visitors (any): needs_orientation = false → Direct check-in
 * 
 * HTTP Method: POST
 * Content-Type: application/json
 * 
 * Request Body:
 * {
 *   "name": "John Smith",
 *   "email": "john@company.com",
 *   "phone": "5551234567",
 *   "company": "ABC Corp",
 *   "visitor_type": "contractor|visitor",
 *   "badge_number": "123" (optional),
 *   "staff_contact": "Jane Doe" (optional),
 *   "tablet_id": "tablet_abc123" (optional),
 *   "contractor_orientation_completed": true (optional)
 * }
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "needs_orientation": false,
 *   "visitor_id": 123,
 *   "message": "Successfully checked in",
 *   "training_warning": null (or warning message)
 * }
 * 
 * Contractor Training Required Response:
 * {
 *   "success": true,
 *   "needs_orientation": true,
 *   "visitor_id": 123,
 *   "visitor_type": "contractor",
 *   "message": "Please complete training before checking in",
 *   "training_expired": false,
 *   "training_warning": null
 * }
 * 
 * Error Response:
 * {
 *   "error": "Error message"
 * }
 * 
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.1
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
// MAIN CHECK-IN PROCESSING
// ============================================================================

try {
    // Parse JSON request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['name', 'email', 'phone', 'company', 'visitor_type'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Extract visitor type (determines training requirements)
    $visitor_type = $input['visitor_type']; // 'contractor' or 'visitor'
    
    // Initialize database connection
    $db = new VisitorDatabase();
    
    // ========================================================================
    // VISITOR LOOKUP
    // Check if visitor exists by name
    // ========================================================================
    
    $existing_visitor = $db->findVisitorByName($input['name']);
    
    // Initialize training status flags
    $training_expired = false;
    $training_warning = null;
    $needs_orientation = false; // Default: no orientation needed
    
    // ========================================================================
    // EXISTING VISITOR PROCESSING
    // Update info and check training status for contractors
    // ========================================================================
    
    if ($existing_visitor) {
        // Prevent duplicate check-ins
        if ($db->isCurrentlyCheckedIn($existing_visitor['id'])) {
            throw new Exception('Visitor is already checked in');
        }
        
        // ====================================================================
        // CONTRACTOR TRAINING VALIDATION
        // Check if contractor training has expired or is expiring soon
        // ====================================================================
        
        if ($visitor_type === 'contractor') {
            $training_info = $db->getTrainingInfo($existing_visitor['id']);
            
            if ($training_info && $training_info['is_expired']) {
                // Training EXPIRED - require re-certification via external form
                $training_expired = true;
                $needs_orientation = true;
                $training_warning = 'Training expired on ' . date('M j, Y', strtotime($training_info['training_expires_date'])) . '. Please complete the training form to renew.';
            } elseif ($training_info && $training_info['expires_soon']) {
                // Training expires within 30 days - warn but allow direct check-in
                $training_warning = 'Training expires on ' . date('M j, Y', strtotime($training_info['training_expires_date'])) . '. Please schedule retraining soon.';
            }
            
            // Check if they've ever completed contractor training (not expired)
            if (!$training_expired && !$existing_visitor['contractor_orientation_completed']) {
                // Never completed training - require external form
                $needs_orientation = true;
            }
        }
        // NOTE: Visitors NEVER need orientation - they check in directly
        
        // Update visitor information (email, phone, company can change)
        $db->updateVisitor(
            $existing_visitor['id'],
            $input['name'],
            $input['email'],
            $input['phone'],
            $input['company'],
            $input['badge_number'] ?? null,
            $input['staff_contact'] ?? null
        );
        
        $visitor_id = $existing_visitor['id'];
        
    } else {
        // ====================================================================
        // NEW VISITOR REGISTRATION
        // Create new visitor record
        // ====================================================================
        
        $contractor_completed = ($visitor_type === 'contractor' && isset($input['contractor_orientation_completed']) && $input['contractor_orientation_completed']);
        
        $visitor_id = $db->createVisitor(
            $input['name'],
            $input['email'],
            $input['phone'],
            $input['company'],
            $input['badge_number'] ?? null,
            $contractor_completed,
            true, // Visitors are always marked as orientation complete (no video required)
            $input['staff_contact'] ?? null
        );
        
        // New contractors need training via external form (unless just completed)
        if ($visitor_type === 'contractor' && !$contractor_completed) {
            $needs_orientation = true;
        }
        // NOTE: New visitors check in directly - no orientation needed
    }
    
    // ========================================================================
    // CHECK IF CONTRACTOR TRAINING WAS JUST COMPLETED
    // ========================================================================
    
    $has_contractor_completed = isset($input['contractor_orientation_completed']) && $input['contractor_orientation_completed'];
    
    // If contractor just completed training, they can proceed
    if ($has_contractor_completed) {
        $needs_orientation = false;
    }
    
    // ========================================================================
    // CONTRACTOR TRAINING REQUIRED RESPONSE
    // Return special response if external form is needed
    // ========================================================================
    
    if ($needs_orientation) {
        $orientation_message = 'Please complete the contractor training form before checking in.';
        
        if ($training_expired) {
            $orientation_message = 'Your training has expired. Please complete the training form to renew your certification.';
        }
        
        echo json_encode([
            'success' => true,
            'needs_orientation' => true,
            'visitor_id' => $visitor_id,
            'visitor_type' => $visitor_type,
            'message' => $orientation_message,
            'training_expired' => $training_expired,
            'training_warning' => $training_warning
        ]);
        exit;
    }
    
    // ========================================================================
    // MARK CONTRACTOR TRAINING COMPLETE
    // If training was just completed via external form, record it
    // ========================================================================
    
    if ($has_contractor_completed) {
        $db->markOrientationCompleted($visitor_id, 'contractor');
    }
    
    // ========================================================================
    // PERFORM CHECK-IN
    // Create visit_log entry recording the check-in
    // ========================================================================
    
    $checkInResult = $db->checkIn($visitor_id, $input['tablet_id'] ?? null);
    
    if (!$checkInResult) {
        throw new Exception('Failed to create visit log entry');
    }
    
    // ========================================================================
    // SUCCESS RESPONSE
    // Build appropriate success message
    // ========================================================================
    
    $success_message = 'Successfully checked in';
    
    // Add training renewal message if applicable
    if ($training_expired && $has_contractor_completed) {
        $success_message = 'Successfully checked in! Training certification has been renewed.';
    }
    
    echo json_encode([
        'success' => true,
        'needs_orientation' => false,
        'visitor_id' => $visitor_id,
        'message' => $success_message,
        'training_warning' => (!$training_expired && $training_warning) ? $training_warning : null
    ]);
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING
    // Return error response with appropriate HTTP status code
    // ========================================================================
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
