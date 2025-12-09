<?php
/**
 * Recent Visits API Endpoint
 * 
 * Returns recent visit history with visitor details, including the staff
 * member being visited. Results are ordered by check-in time descending
 * (most recent first).
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Query Parameters:
 * - limit (optional): Number of records to return (default: 20, max: 500)
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "visits": [
 *     {
 *       "name": "John Smith",
 *       "email": "john@company.com",
 *       "phone": "5551234567",
 *       "company": "ABC Corp",
 *       "badge_number": "123",
 *       "suterra_contact": "Jane Doe",
 *       "contractor_orientation_completed": 1,
 *       "visitor_orientation_completed": 0,
 *       "check_in_time": "2025-01-15 09:30:00",
 *       "check_out_time": "2025-01-15 17:00:00",
 *       "duration_minutes": 450
 *     }
 *   ],
 *   "count": 20
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
    
    // Get limit from query parameter, default to 20, max 500
    $limit = isset($_GET['limit']) ? min(500, max(1, intval($_GET['limit']))) : 20;
    
    // Query recent visits with staff contact, ordered by most recent first
    $stmt = $db->pdo->prepare("
        SELECT v.name, v.email, v.phone, v.company, v.badge_number, v.suterra_contact,
               v.contractor_orientation_completed, v.visitor_orientation_completed,
               vl.check_in_time, vl.check_out_time, vl.tablet_id,
               CASE 
                   WHEN vl.check_out_time IS NOT NULL 
                   THEN ROUND((julianday(vl.check_out_time) - julianday(vl.check_in_time)) * 24 * 60, 2)
                   ELSE NULL
               END as duration_minutes
        FROM visitors v
        JOIN visit_log vl ON v.id = vl.visitor_id
        ORDER BY vl.check_in_time DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'visits' => $visits,
        'count' => count($visits)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
