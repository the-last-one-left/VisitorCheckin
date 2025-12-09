<?php
/**
 * Visitor Statistics API Endpoint
 * 
 * Provides real-time statistics for the admin dashboard including:
 * - Today's visit count
 * - Total unique visitors (all-time)
 * - Average visit duration (last 30 days)
 * - Current visitor count
 * 
 * All timestamps use the configured system timezone.
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Request: No parameters required
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "today_visits": 15,
 *   "total_visitors": 523,
 *   "avg_duration": 45.3,
 *   "current_count": 3,
 *   "timezone": "America/Los_Angeles",
 *   "current_date": "2025-12-02"
 * }
 * 
 * Error Response:
 * {
 *   "error": "Error message"
 * }
 * 
 * Calculation Details:
 * - today_visits: Count of all visits where check_in_time date matches today
 * - total_visitors: Total count of unique visitor records in database
 * - avg_duration: Average minutes for completed visits in last 30 days (rounded to 1 decimal)
 * - current_count: Number of visitors currently checked in (no check-out time)
 * 
 * Notes:
 * - Duration calculated only for completed visits (check_out_time IS NOT NULL)
 * - Duration calculated using SQLite's julianday() function for accuracy
 * - Timezone is read from system configuration for consistent date calculations
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
// Only GET requests are accepted
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ============================================================================
// CALCULATE STATISTICS
// ============================================================================

try {
    // Initialize database connection (sets timezone from config)
    $db = new VisitorDatabase();
    $pdo = $db->pdo ?? null;
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get configured timezone for response
    $timezone = defined('TIMEZONE') ? TIMEZONE : date_default_timezone_get();
    
    // ========================================================================
    // TODAY'S VISIT COUNT
    // Count all check-ins that occurred today
    // ========================================================================
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_visits 
        FROM visit_log 
        WHERE DATE(check_in_time) = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // TOTAL UNIQUE VISITORS
    // Count all visitor records (all-time)
    // ========================================================================
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_visitors FROM visitors");
    $stmt->execute();
    $total_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // AVERAGE VISIT DURATION
    // Calculate average minutes for completed visits in last 30 days
    // Uses SQLite's julianday() for accurate date arithmetic
    // ========================================================================
    
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt = $pdo->prepare("
        SELECT AVG(
            ROUND((julianday(check_out_time) - julianday(check_in_time)) * 24 * 60, 2)
        ) as avg_duration
        FROM visit_log 
        WHERE check_out_time IS NOT NULL
        AND check_in_time >= ?
    ");
    $stmt->execute([$thirty_days_ago]);
    $duration_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // CURRENT VISITORS COUNT
    // Get count of visitors currently on-site
    // ========================================================================
    
    $current_visitors = $db->getCurrentVisitors();
    
    // ========================================================================
    // SUCCESS RESPONSE
    // Return all statistics with timezone information
    // ========================================================================
    
    echo json_encode([
        'success' => true,
        'today_visits' => (int)$today_stats['today_visits'],
        'total_visitors' => (int)$total_stats['total_visitors'],
        'avg_duration' => $duration_stats['avg_duration'] ? round($duration_stats['avg_duration'], 1) : null,
        'current_count' => count($current_visitors),
        'timezone' => $timezone,
        'current_date' => $today
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
