<?php
/**
 * Training Alerts API Endpoint
 * 
 * Returns contractors with expired or soon-to-expire training (within 30 days).
 * Used by admin dashboard for proactive training compliance management.
 * Results are ordered by expiration date ascending (most urgent first).
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "expired": [
 *     {
 *       "id": 45,
 *       "name": "John Smith",
 *       "company": "ABC Corp",
 *       "email": "john@company.com",
 *       "training_expires_date": "2024-12-15"
 *     }
 *   ],
 *   "expired_count": 3,
 *   "expiring_soon": [
 *     {
 *       "id": 67,
 *       "name": "Jane Doe",
 *       "company": "XYZ Inc",
 *       "email": "jane@xyz.com",
 *       "training_expires_date": "2025-02-01"
 *     }
 *   ],
 *   "expiring_soon_count": 5,
 *   "total_contractors": 50,
 *   "tracked_contractors": 45
 * }
 * 
 * Alert Categories:
 * - expired: Training expiration date has passed
 * - expiring_soon: Training expires within the next 30 days
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
    
    // ========================================================================
    // EXPIRED TRAINING
    // Contractors whose training has already expired
    // Ordered by most recently expired first (DESC)
    // ========================================================================
    
    $stmt = $db->pdo->prepare("
        SELECT id, name, email, company, last_training_date, training_expires_date
        FROM visitors 
        WHERE contractor_orientation_completed = 1
          AND training_expires_date IS NOT NULL
          AND DATE(training_expires_date) < DATE('now')
        ORDER BY training_expires_date DESC
    ");
    $stmt->execute();
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // EXPIRING SOON
    // Contractors whose training expires within 30 days
    // Ordered by soonest to expire first (ASC)
    // ========================================================================
    
    $stmt = $db->pdo->prepare("
        SELECT id, name, email, company, last_training_date, training_expires_date
        FROM visitors 
        WHERE contractor_orientation_completed = 1
          AND training_expires_date IS NOT NULL
          AND DATE(training_expires_date) >= DATE('now')
          AND DATE(training_expires_date) <= DATE('now', '+30 days')
        ORDER BY training_expires_date ASC
    ");
    $stmt->execute();
    $expiring_soon = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // STATISTICS
    // Total contractors and how many have training dates tracked
    // ========================================================================
    
    $stmt = $db->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM visitors 
        WHERE contractor_orientation_completed = 1
    ");
    $stmt->execute();
    $total_contractors = $stmt->fetchColumn();
    
    $stmt = $db->pdo->prepare("
        SELECT COUNT(*) as tracked 
        FROM visitors 
        WHERE contractor_orientation_completed = 1 
          AND training_expires_date IS NOT NULL
    ");
    $stmt->execute();
    $tracked_contractors = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'expired' => $expired,
        'expired_count' => count($expired),
        'expiring_soon' => $expiring_soon,
        'expiring_soon_count' => count($expiring_soon),
        'total_contractors' => (int)$total_contractors,
        'tracked_contractors' => (int)$tracked_contractors
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
