<?php
/**
 * Training Management API Endpoint
 * 
 * Returns training data for contractors and visitors who have been active
 * within the last 2 years. Contractors are shown with their training status
 * and expiration dates. Results are ordered by most recent visit first.
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "contractors": [
 *     {
 *       "id": 45,
 *       "name": "John Smith",
 *       "company": "ABC Corp",
 *       "email": "john@company.com",
 *       "last_training_date": "2024-06-15",
 *       "training_expires_date": "2025-06-15",
 *       "training_status": "current",
 *       "total_visits": 12,
 *       "last_visit": "2025-01-10 14:30:00"
 *     }
 *   ],
 *   "contractor_count": 25,
 *   "visitors": [
 *     {
 *       "id": 67,
 *       "name": "Jane Doe",
 *       "company": "XYZ Inc",
 *       "email": "jane@xyz.com",
 *       "total_visits": 5,
 *       "last_visit": "2025-01-08 10:15:00"
 *     }
 *   ],
 *   "visitor_count": 50
 * }
 * 
 * Training Status Values:
 * - "current": Training valid (expires in more than 30 days)
 * - "expiring_soon": Training expires within 30 days
 * - "expired": Training has expired
 * - "no_date": No training date on record
 * 
 * Filter Criteria:
 * - Only visitors with check-ins within the last 2 years are included
 * - Contractors: Have contractor_orientation_completed = 1
 * - Visitors: Have visitor_orientation_completed = 1 but not contractor
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
    
    // Calculate cutoff date (2 years ago)
    $two_years_ago = date('Y-m-d H:i:s', strtotime('-2 years'));
    
    // ========================================================================
    // CONTRACTORS QUERY
    // Get contractors with activity in last 2 years, ordered by most recent visit
    // ========================================================================
    
    $stmt = $db->pdo->prepare("
        SELECT 
            v.id, 
            v.name, 
            v.company, 
            v.email,
            v.last_training_date,
            v.training_expires_date,
            CASE 
                WHEN v.training_expires_date IS NULL THEN 'no_date'
                WHEN DATE(v.training_expires_date) < DATE('now') THEN 'expired'
                WHEN DATE(v.training_expires_date) BETWEEN DATE('now') AND DATE('now', '+30 days') THEN 'expiring_soon'
                ELSE 'current'
            END as training_status,
            COUNT(vl.id) as total_visits,
            MAX(vl.check_in_time) as last_visit
        FROM visitors v
        INNER JOIN visit_log vl ON v.id = vl.visitor_id
        WHERE v.contractor_orientation_completed = 1
        GROUP BY v.id
        HAVING last_visit >= ?
        ORDER BY last_visit DESC
    ");
    $stmt->execute([$two_years_ago]);
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // VISITORS QUERY
    // Get visitors (non-contractors) with activity in last 2 years
    // ========================================================================
    
    $stmt = $db->pdo->prepare("
        SELECT 
            v.id, 
            v.name, 
            v.company, 
            v.email,
            COUNT(vl.id) as total_visits,
            MAX(vl.check_in_time) as last_visit
        FROM visitors v
        INNER JOIN visit_log vl ON v.id = vl.visitor_id
        WHERE v.visitor_orientation_completed = 1 
          AND v.contractor_orientation_completed = 0
        GROUP BY v.id
        HAVING last_visit >= ?
        ORDER BY last_visit DESC
    ");
    $stmt->execute([$two_years_ago]);
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'contractors' => $contractors,
        'contractor_count' => count($contractors),
        'visitors' => $visitors,
        'visitor_count' => count($visitors),
        'filter_cutoff' => $two_years_ago
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
