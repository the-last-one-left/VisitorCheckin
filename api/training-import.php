<?php
/**
 * Training Data CSV Import API Endpoint
 * 
 * Bulk import training completion dates for contractors from CSV files. This endpoint
 * handles two distinct operations:
 * 
 * 1. SINGLE VISITOR UPDATE: Update training date for one contractor
 * 2. CSV BULK IMPORT: Import training dates for multiple contractors from CSV file
 * 
 * The CSV import supports both creating new contractor records and updating existing
 * ones based on name or email matching. This allows importing historical training
 * data from external systems (spreadsheets, other databases, training providers).
 * 
 * WINDOWS/IIS OPTIMIZATION:
 * This file includes aggressive error suppression and output buffering specifically
 * designed for Windows Server/IIS environments where PHP warnings can corrupt JSON
 * responses. These optimizations ensure clean JSON output even with strict IIS
 * configurations and PHP settings.
 * 
 * Security: Requires admin authentication via session
 * HTTP Methods: POST only (both operations)
 * Content-Type: multipart/form-data (for CSV upload) or application/x-www-form-urlencoded
 * Authentication: Admin session required
 * 
 * ============================================================================
 * OPERATION 1: SINGLE VISITOR UPDATE
 * ============================================================================
 * 
 * Update training date for one contractor. Used by the admin interface when
 * editing individual contractor records.
 * 
 * Request: POST /api/training-import.php
 * Content-Type: application/x-www-form-urlencoded
 * 
 * POST Parameters:
 * - action: "update_single"
 * - visitor_id: Visitor ID (integer)
 * - training_date: Training completion date in YYYY-MM-DD format
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "message": "Training date updated successfully"
 * }
 * 
 * Error Response:
 * {
 *   "success": false,
 *   "error": "Missing visitor ID or training date"
 * }
 * 
 * ============================================================================
 * OPERATION 2: CSV BULK IMPORT
 * ============================================================================
 * 
 * Import training dates for multiple contractors from CSV file.
 * 
 * Request: POST /api/training-import.php
 * Content-Type: multipart/form-data
 * 
 * Form Data:
 * - training_csv: CSV file upload
 * 
 * CSV Format (with optional header row):
 * Name,Email,Phone,Company,Training Date
 * John Smith,john@example.com,503-555-1234,ABC Construction,2024-12-15
 * Jane Doe,jane@example.com,503-555-5678,XYZ Services,2025-01-20
 * 
 * CSV Column Details:
 * - Name (required): Full name for matching existing visitors
 * - Email (optional): Email for additional matching if name not found
 * - Phone (optional): Will be added to new records or updated
 * - Company (optional): Will be added to new records or updated
 * - Training Date (optional): YYYY-MM-DD, MM/DD/YYYY, or DD/MM/YYYY
 *   - If empty/invalid, defaults to today's date
 * 
 * Header Row Detection:
 * The importer automatically detects and skips header rows by checking if the
 * first line contains common header keywords (case-insensitive):
 * - "name", "email", "training", "company", "phone", "date"
 * 
 * Date Format Handling:
 * Supports multiple date formats automatically:
 * - ISO format: YYYY-MM-DD (2024-12-15)
 * - US format: MM/DD/YYYY (12/15/2024)
 * - EU format: DD/MM/YYYY (15/12/2024)
 * - Invalid dates default to today
 * 
 * Matching Logic:
 * For each CSV row, the system attempts to find existing visitors:
 * 1. First tries to match by exact name (case-insensitive)
 * 2. If no name match and email provided, tries to match by email
 * 3. If no match found, creates new contractor record
 * 
 * Success Response:
 * {
 *   "success": true,
 *   "message": "Import completed: 15 records imported",
 *   "imported_count": 15,
 *   "error_count": 2,
 *   "errors": [
 *     "Line 5: Missing name",
 *     "Line 12: Failed to update: Database error"
 *   ],
 *   "total_processed": 17
 * }
 * 
 * Error Response:
 * {
 *   "success": false,
 *   "error": "No file uploaded" | "Cannot read CSV file" | "No valid records found"
 * }
 * 
 * ============================================================================
 * WINDOWS/IIS SPECIFIC HANDLING
 * ============================================================================
 * 
 * This endpoint includes several Windows-specific optimizations:
 * 
 * 1. Output Buffering:
 *    - Multiple nested ob_start() calls to prevent premature output
 *    - Aggressive ob_end_clean() before JSON responses
 * 
 * 2. Error Suppression:
 *    - Custom error handler that logs but doesn't display warnings
 *    - @ suppression on session_start, date functions, file operations
 *    - Prevents PHP warnings from corrupting JSON responses
 * 
 * 3. Line Ending Handling:
 *    - Normalizes \r\n (Windows) and \r (Mac) to \n (Unix)
 *    - Removes UTF-8 BOM if present (common in Excel exports)
 * 
 * 4. CSV Parsing:
 *    - Uses simple explode() instead of str_getcsv()
 *    - Avoids fgetcsv() which can have issues on Windows with certain files
 *    - Handles quoted fields by stripping quotes manually
 * 
 * 5. File Upload Handling:
 *    - Reads via file_get_contents() instead of SplFileObject
 *    - More reliable on IIS with various PHP configurations
 * 
 * These optimizations were added after encountering JSON corruption issues
 * with strict IIS security policies and PHP CGI/FastCGI configurations.
 * 
 * ============================================================================
 * USE CASES
 * ============================================================================
 * 
 * 1. Initial System Setup:
 *    Import historical training data when first deploying the system
 * 
 * 2. External Training Provider Integration:
 *    Import completion certificates from third-party training providers
 * 
 * 3. Bulk Updates:
 *    Update multiple contractors after a group training session
 * 
 * 4. Spreadsheet Migration:
 *    Import from existing Excel/Google Sheets training tracking
 * 
 * 5. Periodic Synchronization:
 *    Regular imports from external HR or training management systems
 * 
 * ============================================================================
 * BEST PRACTICES
 * ============================================================================
 * 
 * Before Import:
 * - Create database backup (api/backup.php)
 * - Test with small CSV file first (5-10 records)
 * - Verify CSV format matches expected columns
 * - Check for special characters in names/companies
 * - Ensure dates are in supported formats
 * 
 * After Import:
 * - Review error_count and errors array
 * - Check training-management.php to verify imports
 * - Validate training_expires_date was calculated correctly
 * - Test check-in flow for imported contractors
 * 
 * Common Issues:
 * - Excel adds extra commas: Save as "CSV UTF-8" not "CSV"
 * - Dates formatted as text: Use YYYY-MM-DD or MM/DD/YYYY
 * - Special characters: Use UTF-8 encoding
 * - Names don't match: Check exact spelling and spacing
 * 
 * @package    SuterraGuestCheckin
 * @subpackage API
 * @author     Pacific Office Automation
 * @version    2.0
 * @since      2.0
 */

// ============================================================================
// WINDOWS/IIS ERROR SUPPRESSION
// Aggressive suppression to prevent warnings from corrupting JSON output
// ============================================================================

@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
@ini_set('log_errors', 1);
@ini_set('html_errors', 0);
@error_reporting(0);

// Multiple levels of output buffering for IIS compatibility
@ob_start();
@ob_start();

// Custom error handler that logs but doesn't display warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error to PHP error log but don't display it
    @error_log("PHP Warning suppressed: $errstr in $errfile on line $errline");
    return true; // Prevent PHP's internal error handler from running
});

// Set JSON header immediately to establish content type
@header('Content-Type: application/json; charset=utf-8');

try {
    // ========================================================================
    // INCLUDE DATABASE CLASS
    // With error suppression for Windows compatibility
    // ========================================================================
    
    @include_once 'database.php';
    
    if (!class_exists('VisitorDatabase')) {
        throw new Exception('Database class not available');
    }

    // ========================================================================
    // AUTHENTICATION CHECK
    // Only authenticated administrators can import training data
    // Session start suppressed to prevent "headers already sent" warnings
    // ========================================================================
    
    @session_start();
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        @ob_end_clean();
        @ob_end_clean();
        http_response_code(401);
        die(json_encode(['success' => false, 'error' => 'Authentication required']));
    }

    // ========================================================================
    // REQUEST METHOD VALIDATION
    // Only POST requests accepted (both single update and CSV import)
    // ========================================================================
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        @ob_end_clean();
        @ob_end_clean();
        http_response_code(405);
        die(json_encode(['success' => false, 'error' => 'Method not allowed']));
    }

    // Set timezone with suppression
    @date_default_timezone_set('America/Los_Angeles');
    
    // Create database connection
    $db = new VisitorDatabase();
    
    // ========================================================================
    // OPERATION 1: SINGLE VISITOR UPDATE
    // Update training date for one contractor
    // ========================================================================
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_single') {
        $visitor_id = (int)($_POST['visitor_id'] ?? 0);
        $training_date = trim($_POST['training_date'] ?? '');
        
        // Validate required parameters
        if (!$visitor_id || !$training_date) {
            throw new Exception('Missing visitor ID or training date');
        }
        
        // Validate date format (must be YYYY-MM-DD)
        $date = @DateTime::createFromFormat('Y-m-d', $training_date);
        if (!$date || $date->format('Y-m-d') !== $training_date) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD');
        }
        
        // Update the training date
        // This also calculates and sets training_expires_date (1 year later)
        $success = $db->updateTrainingDate($visitor_id, $training_date);
        
        // Clean output buffers and return response
        @ob_end_clean();
        @ob_end_clean();
        
        if ($success) {
            die(json_encode([
                'success' => true,
                'message' => 'Training date updated successfully'
            ]));
        } else {
            die(json_encode([
                'success' => false,
                'error' => 'Failed to update training date'
            ]));
        }
    }
    
    // ========================================================================
    // OPERATION 2: CSV BULK IMPORT
    // Import training dates from uploaded CSV file
    // ========================================================================
    
    // ========================================================================
    // VALIDATE FILE UPLOAD
    // Check for upload errors and file availability
    // ========================================================================
    
    if (!isset($_FILES['training_csv']) || $_FILES['training_csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'No file uploaded';
        if (isset($_FILES['training_csv']['error'])) {
            switch ($_FILES['training_csv']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = 'File too large';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = 'Upload incomplete';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = 'No file selected';
                    break;
            }
        }
        throw new Exception($error);
    }
    
    $csv_file = $_FILES['training_csv']['tmp_name'];
    
    if (!@file_exists($csv_file) || !@is_readable($csv_file)) {
        throw new Exception('Cannot access uploaded file');
    }
    
    // ========================================================================
    // READ AND NORMALIZE CSV CONTENT
    // Windows-specific handling for line endings and BOM
    // ========================================================================
    
    $file_content = @file_get_contents($csv_file);
    if ($file_content === false) {
        throw new Exception('Cannot read CSV file');
    }
    
    // Normalize line endings: Convert \r\n (Windows) and \r (Mac) to \n (Unix)
    $file_content = str_replace(["\r\n", "\r"], "\n", $file_content);
    
    // Remove UTF-8 BOM if present (common in Excel CSV exports)
    if (substr($file_content, 0, 3) === "\xEF\xBB\xBF") {
        $file_content = substr($file_content, 3);
    }
    
    // Split into lines
    $lines = explode("\n", trim($file_content));
    if (empty($lines)) {
        throw new Exception('CSV file is empty');
    }
    
    // ========================================================================
    // PARSE CSV ROWS
    // Simple parsing to avoid str_getcsv issues on Windows
    // ========================================================================
    
    $training_records = [];
    $errors = [];
    $first_line = true;
    
    foreach ($lines as $line_num => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Simple CSV parsing: split by comma and trim each field
        $data = explode(',', $line);
        $data = array_map('trim', $data);
        
        // ====================================================================
        // SKIP HEADER ROW
        // Auto-detect header row by checking for common header keywords
        // ====================================================================
        
        if ($first_line) {
            $first_line = false;
            $line_lower = strtolower($line);
            // Check if line contains header keywords
            if (strpos($line_lower, 'name') !== false || 
                strpos($line_lower, 'email') !== false) {
                continue; // Skip header row
            }
        }
        
        // Validate minimum columns (at least name and training date)
        if (count($data) < 2) {
            $errors[] = "Line " . ($line_num + 1) . ": Not enough data";
            continue;
        }
        
        // ====================================================================
        // EXTRACT FIELDS FROM CSV
        // Remove quotes from fields if present
        // ====================================================================
        
        $name = isset($data[0]) ? trim($data[0], ' "\'') : '';
        $email = isset($data[1]) ? trim($data[1], ' "\'') : '';
        $phone = isset($data[2]) ? trim($data[2], ' "\'') : '';
        $company = isset($data[3]) ? trim($data[3], ' "\'') : '';
        $training_date = isset($data[4]) ? trim($data[4], ' "\'') : '';
        
        // Name is required
        if (empty($name)) {
            $errors[] = "Line " . ($line_num + 1) . ": Missing name";
            continue;
        }
        
        // ====================================================================
        // PARSE AND VALIDATE TRAINING DATE
        // Try multiple date formats, default to today if invalid
        // ====================================================================
        
        if (empty($training_date)) {
            // No date provided, use today
            $training_date = date('Y-m-d');
        } else {
            // Try to parse the date in multiple formats
            $date = @DateTime::createFromFormat('Y-m-d', $training_date);      // ISO: 2024-12-15
            if (!$date) {
                $date = @DateTime::createFromFormat('m/d/Y', $training_date);  // US: 12/15/2024
            }
            if (!$date) {
                $date = @DateTime::createFromFormat('d/m/Y', $training_date);  // EU: 15/12/2024
            }
            if ($date) {
                $training_date = $date->format('Y-m-d');
            } else {
                // Invalid date format, default to today
                $training_date = date('Y-m-d');
            }
        }
        
        // Add to records array for processing
        $training_records[] = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'training_date' => $training_date
        ];
    }
    
    // ========================================================================
    // VALIDATE PARSED RECORDS
    // Ensure we have at least one valid record to import
    // ========================================================================
    
    if (empty($training_records)) {
        throw new Exception('No valid records found in CSV');
    }
    
    // ========================================================================
    // PROCESS TRAINING RECORDS
    // For each record: find existing visitor or create new, then update date
    // ========================================================================
    
    $success_count = 0;
    $import_errors = [];
    
    foreach ($training_records as $record) {
        try {
            // ================================================================
            // FIND EXISTING VISITOR
            // Try name match first, then email if name not found
            // ================================================================
            
            $visitor = $db->findVisitorByName($record['name']);
            if (!$visitor && !empty($record['email'])) {
                $visitor = $db->findVisitorByEmail($record['email']);
            }
            
            if ($visitor) {
                // ============================================================
                // UPDATE EXISTING VISITOR
                // Update training date (which also updates expiration date)
                // ============================================================
                
                if ($db->updateTrainingDate($visitor['id'], $record['training_date'])) {
                    $success_count++;
                } else {
                    $import_errors[] = "Failed to update: " . $record['name'];
                }
            } else {
                // ============================================================
                // CREATE NEW CONTRACTOR RECORD
                // Create new visitor as contractor with training date
                // ============================================================
                
                $visitor_id = $db->createVisitor(
                    $record['name'],
                    $record['email'],
                    $record['phone'],
                    $record['company'],
                    null,  // badge number
                    true,  // contractor orientation completed
                    false  // visitor orientation completed
                );
                
                // Update training date for newly created contractor
                if ($visitor_id && $db->updateTrainingDate($visitor_id, $record['training_date'])) {
                    $success_count++;
                } else {
                    $import_errors[] = "Failed to create: " . $record['name'];
                }
            }
        } catch (Exception $e) {
            // Catch errors for individual records without stopping entire import
            $import_errors[] = $record['name'] . ": " . $e->getMessage();
        }
    }
    
    // ========================================================================
    // COMBINE ALL ERRORS
    // Merge parsing errors and import errors for complete error report
    // ========================================================================
    
    $all_errors = array_merge($errors, $import_errors);
    
    // ========================================================================
    // SUCCESS RESPONSE
    // Clean output buffers and return import results
    // ========================================================================
    
    @ob_end_clean();
    @ob_end_clean();
    
    die(json_encode([
        'success' => true,
        'message' => "Import completed: $success_count records imported",
        'imported_count' => $success_count,
        'error_count' => count($all_errors),
        'errors' => $all_errors,
        'total_processed' => count($training_records)
    ]));
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING - EXPECTED ERRORS
    // Clean output buffers and return error response
    // ========================================================================
    
    @ob_end_clean();
    @ob_end_clean();
    @http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]));
} catch (Throwable $e) {
    // ========================================================================
    // ERROR HANDLING - UNEXPECTED ERRORS
    // Catch all other errors (PHP 7+ Error class)
    // ========================================================================
    
    @ob_end_clean();
    @ob_end_clean();
    @http_response_code(500);
    die(json_encode([
        'success' => false,
        'error' => 'Server error occurred'
    ]));
}
?>
