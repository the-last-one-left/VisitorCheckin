<?php
/**
 * Current Visitors API Endpoint
 * 
 * Returns all visitors currently checked in (no check-out time recorded).
 * Includes staff_contact to show who each visitor is meeting with.
 * Results are ordered by check-in time descending (most recent first).
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "visitors": [
 *     {
 *       "id": 45,
 *       "name": "John Smith",
 *       "email": "john@company.com",
 *       "phone": "5551234567",
 *       "company": "ABC Corp",
 *       "badge_number": "123",
 *       "suterra_contact": "Jane Doe",
 *       "contractor_orientation_completed": 1,
 *       "visitor_orientation_completed": 0,
 *       "check_in_time": "2025-01-15 09:30:00",
 *       "visit_id": 892
 *     }
 *   ],
 *   "count": 5
 * }
 * 
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.1
 */

header('Content-Type: application/json');

require_once 'database.php';

try {
    $db = new VisitorDatabase();
    
    // Query currently checked-in visitors with staff contact
    // Ordered by check-in time descending (most recent first)
    $stmt = $db->pdo->prepare("
        SELECT v.id, v.name, v.email, v.phone, v.company, v.badge_number, v.suterra_contact,
               v.contractor_orientation_completed, v.visitor_orientation_completed,
               vl.check_in_time, vl.id as visit_id
        FROM visitors v
        JOIN visit_log vl ON v.id = vl.visitor_id
        WHERE vl.check_out_time IS NULL
        ORDER BY vl.check_in_time DESC
    ");
    $stmt->execute();
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'visitors' => $visitors,
        'count' => count($visitors)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
