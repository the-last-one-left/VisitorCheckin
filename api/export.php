<?php
/**
 * CSV Export API Endpoint
 * 
 * Exports visit history to CSV format for reporting, analysis, and record-keeping.
 * Used by the admin dashboard's "Export CSV Report" button.
 * 
 * HTTP Method: GET
 * Content-Type: text/csv
 * 
 * Request: No parameters required
 * 
 * Success Response: CSV file download
 * - Filename format: visitor_report_YYYY-MM-DD_HH-MM-SS.csv
 * - Contains last 1000 visits (most recent first)
 * - Headers and data in comma-separated format
 * 
 * CSV Columns:
 * 1. Name                  - Visitor full name
 * 2. Email                 - Email address
 * 3. Phone                 - 10-digit phone number
 * 4. Company               - Company/organization name
 * 5. Badge Number          - Facility badge (if assigned)
 * 6. Check-in Time         - Format: YYYY-MM-DD HH:MM:SS
 * 7. Check-out Time        - Format: YYYY-MM-DD HH:MM:SS (blank if still here)
 * 8. Duration (minutes)    - Visit length in minutes (blank if still here)
 * 9. Status                - "Checked Out" or "Still Here"
 * 10. Date                 - Date only (YYYY-MM-DD) for grouping
 * 11. Check-in Hour        - Hour (0-23) for time analysis
 * 12. Contractor Orientation - "Yes" or "No"
 * 13. Visitor Orientation - "Yes" or "No"
 * 
 * Example CSV Output:
 * Name,Email,Phone,Company,Badge Number,Check-in Time,...
 * John Smith,john@company.com,5551234567,ABC Corp,456,2025-12-02 09:30:00,...
 * 
 * Error Response (JSON):
 * {
 *   "error": "Error message"
 * }
 * 
 * Usage:
 * - Click "Export CSV Report" in admin dashboard
 * - Browser will download the .csv file
 * - Open in Excel, Google Sheets, or any CSV viewer
 * - Use for reporting, compliance, or analysis
 * 
 * Data Analysis Use Cases:
 * - Peak visit times by hour
 * - Most frequent visitors
 * - Average visit duration by company
 * - Contractor vs. visitor ratios
 * - Compliance reporting for orientations
 * 
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.0
 */

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
// EXPORT VISIT DATA TO CSV
// ============================================================================

try {
    // Initialize database connection
    $db = new VisitorDatabase();
    
    // ========================================================================
    // RETRIEVE VISIT HISTORY
    // Get last 1000 visits for export (configurable limit)
    // ========================================================================
    
    $visits = $db->getVisitHistory(1000);
    
    // ========================================================================
    // SET CSV DOWNLOAD HEADERS
    // Configure browser to download as CSV file
    // ========================================================================
    
    // Generate timestamped filename
    $filename = 'visitor_report_' . date('Y-m-d_H-i-s') . '.csv';
    
    // CSV download headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // ========================================================================
    // CREATE CSV OUTPUT STREAM
    // Write directly to php://output for streaming download
    // ========================================================================
    
    $output = fopen('php://output', 'w');
    
    // ========================================================================
    // WRITE CSV HEADER ROW
    // Define column names for the spreadsheet
    // ========================================================================
    
    fputcsv($output, [
        'Name',
        'Email',
        'Phone',
        'Company',
        'Badge Number',
        'Check-in Time',
        'Check-out Time',
        'Duration (minutes)',
        'Status',
        'Date',
        'Check-in Hour',
        'Contractor Orientation',
        'Visitor Orientation'
    ]);
    
    // ========================================================================
    // WRITE DATA ROWS
    // Process each visit and write to CSV
    // ========================================================================
    
    foreach ($visits as $visit) {
        // Parse timestamps for formatting
        $check_in_time = new DateTime($visit['check_in_time']);
        $check_out_time = $visit['check_out_time'] ? new DateTime($visit['check_out_time']) : null;
        
        // Build row array with formatted values
        $row = [
            $visit['name'],
            $visit['email'],
            $visit['phone'],
            $visit['company'],
            $visit['badge_number'] ?: '',                              // Empty string if null
            $check_in_time->format('Y-m-d H:i:s'),                    // Standard datetime format
            $check_out_time ? $check_out_time->format('Y-m-d H:i:s') : '', // Empty if still checked in
            $visit['duration_minutes'] ?: '',                          // Empty if not yet checked out
            $check_out_time ? 'Checked Out' : 'Still Here',           // Human-readable status
            $check_in_time->format('Y-m-d'),                          // Date only for grouping
            $check_in_time->format('H'),                              // Hour (0-23) for time analysis
            $visit['contractor_orientation_completed'] ? 'Yes' : 'No', // Boolean to Yes/No
            $visit['visitor_orientation_completed'] ? 'Yes' : 'No'     // Boolean to Yes/No
        ];
        
        // Write row to CSV
        fputcsv($output, $row);
    }
    
    // Close output stream and end execution
    fclose($output);
    exit;
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING
    // If export fails, return JSON error response
    // ========================================================================
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
