<?php
/**
 * Visitor Search API Endpoint
 * 
 * Provides intelligent search functionality for finding existing visitors by name,
 * email, or company. Used for quick check-in of returning visitors. Results are
 * prioritized by match quality (exact name > partial name > email > company) and
 * include visit history, orientation status, and suggested visitor type based on
 * their most recent check-in pattern.
 * 
 * This endpoint powers the autocomplete/typeahead search on the check-in form,
 * allowing staff to quickly find and check in returning visitors without re-entering
 * their full information.
 * 
 * HTTP Method: GET
 * Content-Type: application/json
 * 
 * Query Parameters:
 * - q (string, required): Search term (name, email, or company)
 * - limit (int, optional): Maximum results to return (default: 10)
 * 
 * Request Examples:
 * GET /api/search-visitors.php?q=john
 * GET /api/search-visitors.php?q=john@example.com&limit=5
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "visitors": [
 *     {
 *       "id": 123,
 *       "name": "John Smith",
 *       "email": "john.smith@example.com",
 *       "phone": "503-555-1234",
 *       "company": "ABC Construction",
 *       "badge_number": "B-042",
 *       "staff_contact": "Jane Doe",
 *       "contractor_orientation_completed": true,
 *       "visitor_orientation_completed": false,
 *       "training_type": "contractor",
 *       "suggested_visitor_type": "contractor",
 *       "last_visit": "2025-12-01 14:30:00",
 *       "visit_count": 15,
 *       "display_text": "John Smith (ABC Construction) - john.smith@example.com"
 *     }
 *   ]
 * }
 * 
 * Search Algorithm:
 * 
 * Match Priority (best to worst):
 * 1. Exact name match (case-insensitive)
 * 2. Name starts with search term
 * 3. Email contains search term
 * 4. Company contains search term
 * 5. Name contains search term (anywhere)
 * 
 * Within each priority level, results are sorted by:
 * - Most recent visit first
 * - Then alphabetically by name
 * 
 * Suggested Visitor Type Logic:
 * The system suggests a visitor type based on the person's history:
 * - If training_type = 'contractor' → suggest contractor
 * - If contractor_orientation_completed = true → suggest contractor
 * - Otherwise → suggest visitor
 * 
 * This helps maintain consistency and reduces errors during check-in.
 * 
 * Empty Search Handling:
 * If the search term is empty or only whitespace, returns an empty results
 * array rather than an error. This allows the UI to gracefully handle
 * clearing the search box.
 * 
 * Use Cases:
 * - Quick check-in: Find returning visitor and pre-fill their information
 * - Duplicate prevention: See if someone already exists before creating new record
 * - Contact lookup: Find visitor details by partial information
 * - Verify spelling: See similar names when unsure of exact spelling
 * 
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.0
 * @since      1.0
 */

// Set JSON response header
header('Content-Type: application/json');

// Include database class
require_once 'database.php';

// ============================================================================
// REQUEST METHOD VALIDATION
// Only GET requests are accepted (search is a read-only operation)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Initialize database connection (timezone set in constructor)
    $db = new VisitorDatabase();
    
    // ========================================================================
    // EXTRACT AND VALIDATE QUERY PARAMETERS
    // ========================================================================
    
    // Get search term (empty string if not provided)
    $search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    // Get result limit (default to 10, maximum enforced by SQL LIMIT)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // ========================================================================
    // HANDLE EMPTY SEARCH
    // Return empty results rather than error for better UX
    // ========================================================================
    
    if (empty($search_term)) {
        echo json_encode([
            'success' => true,
            'visitors' => []
        ]);
        exit;
    }
    
    // ========================================================================
    // BUILD SEARCH QUERY
    // Uses prioritized matching with visit history aggregation
    // ========================================================================
    
    // Create LIKE pattern for partial matching
    $search_pattern = '%' . $search_term . '%';
    
    $stmt = $db->pdo->prepare("
        SELECT v.*, 
               -- Get most recent visit date for each visitor
               MAX(vl.check_in_time) as last_visit,
               -- Count total visits for activity indicator
               COUNT(vl.id) as visit_count,
               -- Determine suggested visitor type based on their history
               -- Training type takes precedence, otherwise use orientation status
               (
                   SELECT CASE 
                       WHEN v.training_type = 'contractor' THEN 'contractor'
                       WHEN v.contractor_orientation_completed = 1 THEN 'contractor'
                       ELSE 'visitor'
                   END
               ) as suggested_visitor_type,
               -- Calculate match priority for sorting
               -- Lower number = better match
               CASE 
                   WHEN LOWER(v.name) = LOWER(?) THEN 1
                   WHEN LOWER(v.name) LIKE LOWER(?) THEN 2
                   WHEN LOWER(v.email) LIKE LOWER(?) THEN 3
                   WHEN LOWER(v.company) LIKE LOWER(?) THEN 4
                   ELSE 5
               END as match_priority
        FROM visitors v
        LEFT JOIN visit_log vl ON v.id = vl.visitor_id
        WHERE v.name LIKE ? 
           OR v.email LIKE ? 
           OR v.company LIKE ?
        GROUP BY v.id
        ORDER BY match_priority ASC, last_visit DESC, v.name ASC
        LIMIT ?
    ");
    
    // ========================================================================
    // EXECUTE SEARCH WITH MULTIPLE BIND PARAMETERS
    // Same search term used for different matching strategies
    // ========================================================================
    
    $stmt->execute([
        $search_term,           // exact name match (priority 1)
        $search_term . '%',     // name starts with (priority 2)
        $search_pattern,        // email contains (priority 3)
        $search_pattern,        // company contains (priority 4)
        $search_pattern,        // name contains (WHERE clause)
        $search_pattern,        // email contains (WHERE clause)
        $search_pattern,        // company contains (WHERE clause)
        $limit                  // result limit
    ]);
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // FORMAT RESULTS FOR CLIENT
    // Convert database types and add computed fields
    // ========================================================================
    
    $formatted_visitors = array_map(function($visitor) {
        return [
            'id' => $visitor['id'],
            'name' => $visitor['name'],
            'email' => $visitor['email'],
            'phone' => $visitor['phone'],
            'company' => $visitor['company'],
            'badge_number' => $visitor['badge_number'],
            'staff_contact' => $visitor['suterra_contact'] ?? null,
            // Convert integer flags to boolean for JavaScript
            'contractor_orientation_completed' => (bool)$visitor['contractor_orientation_completed'],
            'visitor_orientation_completed' => (bool)$visitor['visitor_orientation_completed'],
            'training_type' => $visitor['training_type'],
            'suggested_visitor_type' => $visitor['suggested_visitor_type'],
            'last_visit' => $visitor['last_visit'],
            'visit_count' => (int)$visitor['visit_count'],
            // Pre-formatted display text for dropdown/autocomplete UI
            'display_text' => $visitor['name'] . ' (' . $visitor['company'] . ') - ' . $visitor['email']
        ];
    }, $visitors);
    
    // ========================================================================
    // SUCCESS RESPONSE
    // Return formatted visitor array
    // ========================================================================
    
    echo json_encode([
        'success' => true,
        'visitors' => $formatted_visitors
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
