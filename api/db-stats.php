<?php
/**
 * Database Statistics API Endpoint
 * 
 * Provides comprehensive database statistics for the admin dashboard including
 * file size, record counts, orientation statistics, date ranges, and recent activity.
 * Used to populate the "Database Information & Management" section.
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Request: No parameters required
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "database_file_size_mb": 12.45,
 *   "total_visitors": 523,
 *   "total_visits": 1847,
 *   "orientation_stats": {
 *     "contractor_completed": 125,
 *     "visitor_completed": 398,
 *     "both_completed": 15
 *   },
 *   "date_ranges": {
 *     "first_visitor": "2024-01-15 08:30:00",
 *     "last_visitor": "2025-12-02 14:22:00",
 *     "first_visit": "2024-01-15 09:00:00",
 *     "last_visit": "2025-12-02 14:25:00"
 *   },
 *   "recent_activity": {
 *     "visits_last_30_days": 245
 *   }
 * }
 * 
 * Error Response:
 * {
 *   "error": "Error message"
 * }
 * 
 * Metrics Explained:
 * 
 * File Size:
 * - Physical size of visitors.db file on disk
 * - Useful for monitoring database growth
 * - Consider auto-purge if growing too large
 * 
 * Total Visitors vs Total Visits:
 * - total_visitors: Unique people (visitors table count)
 * - total_visits: Check-in events (visit_log table count)
 * - Ratio indicates repeat visitor frequency
 * 
 * Orientation Statistics:
 * - contractor_completed: Contractors with completed orientation
 * - visitor_completed: Visitors with completed orientation
 * - both_completed: People who completed both (unusual but possible)
 * 
 * Date Ranges:
 * - first_visitor/last_visitor: Visitor record creation dates
 * - first_visit/last_visit: Actual visit event dates
 * - Useful for understanding system usage timeline
 * 
 * Recent Activity:
 * - visits_last_30_days: Activity level indicator
 * - Helps identify trends and seasonality
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
// GATHER DATABASE STATISTICS
// ============================================================================

try {
    // Initialize database connection
    $db = new VisitorDatabase();
    $db_path = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/visitors.db';
    
    // ========================================================================
    // DATABASE FILE SIZE
    // Get physical file size for storage monitoring
    // ========================================================================
    
    $file_size = file_exists($db_path) ? filesize($db_path) : 0;
    $file_size_mb = round($file_size / 1024 / 1024, 2); // Convert to MB
    
    // ========================================================================
    // TOTAL RECORD COUNTS
    // Count unique visitors and total visit events
    // ========================================================================
    
    $stmt = $db->pdo->prepare("SELECT COUNT(*) as count FROM visitors");
    $stmt->execute();
    $total_visitors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->pdo->prepare("SELECT COUNT(*) as count FROM visit_log");
    $stmt->execute();
    $total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // ========================================================================
    // ORIENTATION STATISTICS
    // Count completions by orientation type
    // Uses CASE WHEN for conditional counting
    // ========================================================================
    
    $stmt = $db->pdo->prepare("SELECT 
        COUNT(CASE WHEN contractor_orientation_completed = 1 THEN 1 END) as contractor_completed,
        COUNT(CASE WHEN visitor_orientation_completed = 1 THEN 1 END) as visitor_completed,
        COUNT(CASE WHEN contractor_orientation_completed = 1 AND visitor_orientation_completed = 1 THEN 1 END) as both_completed
        FROM visitors");
    $stmt->execute();
    $orientation_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // DATE RANGES - VISITOR RECORDS
    // Find first and last visitor registration dates
    // ========================================================================
    
    $stmt = $db->pdo->prepare("SELECT 
        MIN(created_at) as first_visitor,
        MAX(created_at) as last_visitor
        FROM visitors");
    $stmt->execute();
    $date_range = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // DATE RANGES - VISIT EVENTS
    // Find first and last actual visit dates
    // ========================================================================
    
    $stmt = $db->pdo->prepare("SELECT 
        MIN(check_in_time) as first_visit,
        MAX(check_in_time) as last_visit
        FROM visit_log");
    $stmt->execute();
    $visit_range = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // RECENT ACTIVITY
    // Count visits in last 30 days for activity monitoring
    // ========================================================================
    
    $stmt = $db->pdo->prepare("SELECT COUNT(*) as count FROM visit_log WHERE check_in_time >= datetime('now', '-30 days')");
    $stmt->execute();
    $recent_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // ========================================================================
    // SUCCESS RESPONSE
    // Return comprehensive statistics object
    // ========================================================================
    
    echo json_encode([
        'success' => true,
        'database_file_size_mb' => $file_size_mb,
        'total_visitors' => (int)$total_visitors,
        'total_visits' => (int)$total_visits,
        'orientation_stats' => [
            'contractor_completed' => (int)$orientation_stats['contractor_completed'],
            'visitor_completed' => (int)$orientation_stats['visitor_completed'],
            'both_completed' => (int)$orientation_stats['both_completed']
        ],
        'date_ranges' => [
            'first_visitor' => $date_range['first_visitor'],
            'last_visitor' => $date_range['last_visitor'],
            'first_visit' => $visit_range['first_visit'],
            'last_visit' => $visit_range['last_visit']
        ],
        'recent_activity' => [
            'visits_last_30_days' => (int)$recent_visits
        ]
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
