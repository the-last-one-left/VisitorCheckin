<?php
/**
 * Visitor Database Management Class
 * 
 * Handles all database operations for the Visitor Check-In System including
 * visitor management, check-in/check-out tracking, training certification tracking,
 * and automated data purging.
 * 
 * Database: SQLite (visitors.db)
 * Timezone: Configured via system settings
 * 
 * @package    VisitorManagement
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.0
 * @since      2024-12-08
 */

/**
 * Class VisitorDatabase
 * 
 * Main database class providing CRUD operations for visitors and visit logs,
 * training management, and automated maintenance features.
 * 
 * Key Features:
 * - Visitor registration and management
 * - Check-in/check-out tracking
 * - Dual orientation system (contractor/visitor)
 * - Training expiration tracking (configurable renewal cycle)
 * - Automated data purging
 * - Staff contact tracking
 * 
 * @property PDO $pdo PDO database connection instance
 */
class VisitorDatabase {
    /**
     * PDO database connection
     * @var PDO
     */
    public $pdo;
    
    /**
     * Constructor - Initialize database connection
     * 
     * Sets up SQLite database connection and configures timezone from system config.
     * Database path is determined from DB_PATH constant or defaults to ../data/visitors.db.
     * 
     * @throws Exception If database connection fails
     */
    public function __construct() {
        // Set timezone from config or default to America/Los_Angeles
        if (defined('TIMEZONE')) {
            date_default_timezone_set(TIMEZONE);
        } else {
            date_default_timezone_set('America/Los_Angeles');
        }
        
        // Get database path from config or use default
        $db_path = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/visitors.db';
        
        try {
            $this->pdo = new PDO("sqlite:$db_path");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    // ============================================================================
    // VISITOR LOOKUP METHODS
    // Methods for finding existing visitor records
    // ============================================================================
    
    /**
     * Find visitor by email address
     * 
     * Searches for an exact email match (case-sensitive).
     * 
     * @param string $email Email address to search for
     * @return array|false Visitor record array or false if not found
     */
    public function findVisitorByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM visitors WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find visitor by name
     * 
     * Performs case-insensitive name search. This is the preferred lookup method
     * based on user feedback, as it handles returning visitors more naturally.
     * 
     * @param string $name Full name to search for (case-insensitive)
     * @return array|false Visitor record array or false if not found
     */
    public function findVisitorByName($name) {
        $stmt = $this->pdo->prepare("SELECT * FROM visitors WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($name)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ============================================================================
    // VISITOR MANAGEMENT METHODS
    // Methods for creating and updating visitor records
    // ============================================================================
    
    /**
     * Create new visitor record
     * 
     * Registers a new visitor in the system. Automatically sets training dates
     * for contractors (expiration based on config). Visitors don't require annual retraining.
     * 
     * Training Logic:
     * - Contractor: Sets training_date to today, expires based on config
     * - Visitor: No expiration, one-time orientation only
     * - None: No orientation completed yet
     * 
     * @param string      $name                   Full name of visitor
     * @param string      $email                  Email address
     * @param string      $phone                  Phone number (10 digits)
     * @param string      $company                Company name
     * @param string|null $badge_number           Optional badge number
     * @param bool        $contractor_orientation Whether contractor orientation completed
     * @param bool        $visitor_orientation    Whether visitor orientation completed
     * @param string|null $staff_contact          Staff member they're visiting
     * @return int Newly created visitor ID
     */
    public function createVisitor($name, $email, $phone, $company, $badge_number = null, $contractor_orientation = false, $visitor_orientation = false, $staff_contact = null) {
        // Initialize training tracking variables
        $training_type = 'none';
        $last_training_date = null;
        $training_expires_date = null;
        
        // Set training dates based on orientation type
        if ($contractor_orientation) {
            $training_type = 'contractor';
            $last_training_date = date('Y-m-d');
            
            // Get training expiration months from config or default to 12
            $expiration_months = defined('TRAINING_EXPIRES_MONTHS') ? TRAINING_EXPIRES_MONTHS : 12;
            $training_expires_date = date('Y-m-d', strtotime("+{$expiration_months} months"));
        } elseif ($visitor_orientation) {
            $training_type = 'visitor';
            // Visitors don't need annual retraining, so no expiration date
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO visitors (
                name, email, phone, company, badge_number, suterra_contact,
                contractor_orientation_completed, visitor_orientation_completed,
                training_type, last_training_date, training_expires_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $name, $email, $phone, $company, $badge_number, $staff_contact,
            $contractor_orientation ? 1 : 0, $visitor_orientation ? 1 : 0,
            $training_type, $last_training_date, $training_expires_date
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update existing visitor information
     * 
     * Updates basic visitor details. Does NOT modify orientation or training data.
     * Use markOrientationCompleted() or updateTrainingDate() for those updates.
     * 
     * @param int         $id             Visitor ID to update
     * @param string      $name           Updated full name
     * @param string      $email          Updated email address
     * @param string      $phone          Updated phone number
     * @param string      $company        Updated company name
     * @param string|null $badge_number   Updated badge number
     * @param string|null $staff_contact  Updated staff contact
     * @return bool True on success, false on failure
     */
    public function updateVisitor($id, $name, $email, $phone, $company, $badge_number = null, $staff_contact = null) {
        $stmt = $this->pdo->prepare("UPDATE visitors SET name = ?, email = ?, phone = ?, company = ?, badge_number = ?, suterra_contact = ? WHERE id = ?");
        return $stmt->execute([$name, $email, $phone, $company, $badge_number, $staff_contact, $id]);
    }
    
    /**
     * Mark orientation as completed for a visitor
     * 
     * Records orientation completion and sets training dates for contractors.
     * Contractor orientations automatically set expiration dates based on config.
     * 
     * @param int    $visitor_id       Visitor ID
     * @param string $orientation_type Either 'contractor' or 'visitor'
     * @return bool True on success, false on failure
     */
    public function markOrientationCompleted($visitor_id, $orientation_type) {
        if ($orientation_type === 'contractor') {
            // Update both orientation completion AND training dates
            $training_date = date('Y-m-d');
            
            // Get training expiration months from config or default to 12
            $expiration_months = defined('TRAINING_EXPIRES_MONTHS') ? TRAINING_EXPIRES_MONTHS : 12;
            $expiration_date = date('Y-m-d', strtotime("+{$expiration_months} months"));
            
            $stmt = $this->pdo->prepare("
                UPDATE visitors SET 
                    contractor_orientation_completed = 1,
                    training_type = 'contractor',
                    last_training_date = ?,
                    training_expires_date = ?
                WHERE id = ?
            ");
            return $stmt->execute([$training_date, $expiration_date, $visitor_id]);
        } else {
            // Visitor orientation - no expiration tracking needed
            $stmt = $this->pdo->prepare("
                UPDATE visitors SET 
                    visitor_orientation_completed = 1,
                    training_type = CASE 
                        WHEN training_type = 'none' THEN 'visitor'
                        ELSE training_type
                    END
                WHERE id = ?
            ");
            return $stmt->execute([$visitor_id]);
        }
    }
    
    // ============================================================================
    // CHECK-IN / CHECK-OUT METHODS
    // Methods for tracking visitor presence on-site
    // ============================================================================
    
    /**
     * Check in a visitor
     * 
     * Creates a new visit_log entry recording the check-in time.
     * Uses configured timezone for all timestamps. Logs are written for audit trail.
     * 
     * @param int         $visitor_id Visitor ID to check in
     * @param string|null $tablet_id  Optional tablet/kiosk identifier
     * @return bool True on success, false on failure
     */
    public function checkIn($visitor_id, $tablet_id = null) {
        try {
            // Use PHP's current time in configured timezone (set in constructor)
            $current_time = date('Y-m-d H:i:s');
            
            $stmt = $this->pdo->prepare("INSERT INTO visit_log (visitor_id, tablet_id, check_in_time) VALUES (?, ?, ?)");
            $result = $stmt->execute([$visitor_id, $tablet_id, $current_time]);
            
            if (!$result) {
                $this->logToFile("Failed to insert visit_log entry for visitor_id: $visitor_id");
                return false;
            }
            
            $insert_id = $this->pdo->lastInsertId();
            $this->logToFile("Successfully created visit_log entry with ID: $insert_id for visitor_id: $visitor_id at time: $current_time");
            
            return $result;
        } catch (Exception $e) {
            $this->logToFile("Exception in checkIn: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check out a visitor
     * 
     * Updates the most recent visit_log entry for the visitor with check-out time.
     * Only updates records where check_out_time is NULL (currently checked in).
     * 
     * @param int $visitor_id Visitor ID to check out
     * @return bool True on success, false on failure
     */
    public function checkOut($visitor_id) {
        // Use PHP's current time in configured timezone
        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $this->pdo->prepare("UPDATE visit_log SET check_out_time = ? WHERE visitor_id = ? AND check_out_time IS NULL");
        return $stmt->execute([$current_time, $visitor_id]);
    }
    
    /**
     * Check if visitor is currently checked in
     * 
     * @param int $visitor_id Visitor ID to check
     * @return bool True if currently checked in, false otherwise
     */
    public function isCurrentlyCheckedIn($visitor_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM visit_log WHERE visitor_id = ? AND check_out_time IS NULL");
        $stmt->execute([$visitor_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    // ============================================================================
    // VISITOR RETRIEVAL METHODS
    // Methods for querying visitor and visit data
    // ============================================================================
    
    /**
     * Get all currently checked-in visitors
     * 
     * Returns visitors with active check-ins (no check-out time).
     * Ordered by check-in time descending (most recent first).
     * 
     * @return array Array of visitor records with check-in details
     */
    public function getCurrentVisitors() {
        $stmt = $this->pdo->prepare("
            SELECT v.*, vl.check_in_time, vl.id as visit_id
            FROM visitors v
            JOIN visit_log vl ON v.id = vl.visitor_id
            WHERE vl.check_out_time IS NULL
            ORDER BY vl.check_in_time DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all visitor records
     * 
     * @return array Array of all visitor records, ordered by creation date
     */
    public function getAllVisitors() {
        $stmt = $this->pdo->prepare("SELECT * FROM visitors ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get visit history
     * 
     * Returns recent visit records with visitor details and calculated duration.
     * Duration is calculated in minutes for completed visits.
     * 
     * @param int $limit Maximum number of records to return (default: 100)
     * @return array Array of visit records with visitor information
     */
    public function getVisitHistory($limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT v.name, v.email, v.phone, v.company, v.badge_number,
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ============================================================================
    // TRAINING MANAGEMENT METHODS
    // Methods for tracking and managing contractor training certifications
    // ============================================================================
    
    /**
     * Check if contractor training is expired
     * 
     * Contractors must renew training based on configured interval. This method 
     * checks if the training_expires_date has passed.
     * 
     * @param int $visitor_id Visitor ID to check
     * @return bool True if training expired, false if current or not a contractor
     */
    public function isTrainingExpired($visitor_id) {
        $stmt = $this->pdo->prepare("
            SELECT training_expires_date, training_type
            FROM visitors 
            WHERE id = ? AND training_type = 'contractor'
        ");
        $stmt->execute([$visitor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['training_expires_date']) {
            return false; // No expiration date set or not a contractor
        }
        
        $expiration_date = new DateTime($result['training_expires_date']);
        $today = new DateTime();
        
        return $today > $expiration_date;
    }
    
    /**
     * Get training information for a visitor
     * 
     * Returns comprehensive training status including expiration dates and flags
     * for expired or soon-to-expire training.
     * 
     * @param int $visitor_id Visitor ID to query
     * @return array|false Training information array or false if not found
     *                     Array keys: training_type, last_training_date, training_expires_date,
     *                                is_expired (bool), expires_soon (bool, within 30 days)
     */
    public function getTrainingInfo($visitor_id) {
        $stmt = $this->pdo->prepare("
            SELECT training_type, last_training_date, training_expires_date,
                   CASE 
                       WHEN training_expires_date IS NOT NULL AND DATE(training_expires_date) < DATE('now') 
                       THEN 1 
                       ELSE 0 
                   END as is_expired,
                   CASE 
                       WHEN training_expires_date IS NOT NULL AND DATE(training_expires_date) BETWEEN DATE('now') AND DATE('now', '+30 days')
                       THEN 1 
                       ELSE 0 
                   END as expires_soon
            FROM visitors 
            WHERE id = ?
        ");
        $stmt->execute([$visitor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update training completion date (admin function)
     * 
     * Sets training completion date and automatically calculates expiration based on config.
     * Used by admin dashboard for manual training record updates.
     * 
     * @param int    $visitor_id     Visitor ID to update
     * @param string $training_date  Training completion date (Y-m-d format)
     * @return bool True on success, false on failure
     */
    public function updateTrainingDate($visitor_id, $training_date) {
        // Get training expiration months from config or default to 12
        $expiration_months = defined('TRAINING_EXPIRES_MONTHS') ? TRAINING_EXPIRES_MONTHS : 12;
        $expiration_date = date('Y-m-d', strtotime($training_date . " +{$expiration_months} months"));
        
        $stmt = $this->pdo->prepare("
            UPDATE visitors 
            SET last_training_date = ?, 
                training_expires_date = ?,
                contractor_orientation_completed = 1,
                training_type = 'contractor'
            WHERE id = ?
        ");
        return $stmt->execute([$training_date, $expiration_date, $visitor_id]);
    }
    
    /**
     * Import training data from CSV (admin function)
     * 
     * Batch imports training records from CSV file. For each record:
     * - If visitor exists: Updates training date
     * - If visitor doesn't exist: Creates new contractor record
     * 
     * @param array $training_records Array of training records with keys:
     *                               name, email, phone, company, training_date
     * @return array Results array with keys:
     *               success_count (int), error_count (int), errors (array)
     */
    public function importTrainingData($training_records) {
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($training_records as $record) {
            try {
                // Find visitor by name (or email as fallback)
                $visitor = $this->findVisitorByName($record['name']);
                if (!$visitor && !empty($record['email'])) {
                    $visitor = $this->findVisitorByEmail($record['email']);
                }
                
                if ($visitor) {
                    // Update existing visitor
                    $this->updateTrainingDate($visitor['id'], $record['training_date']);
                    $success_count++;
                } else {
                    // Create new visitor with training data
                    $visitor_id = $this->createVisitor(
                        $record['name'],
                        $record['email'] ?? '',
                        $record['phone'] ?? '',
                        $record['company'] ?? '',
                        null, // badge number
                        true, // contractor orientation completed
                        false // visitor orientation
                    );
                    
                    // Update the training date to the imported date (not today)
                    $this->updateTrainingDate($visitor_id, $record['training_date']);
                    $success_count++;
                }
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Error importing {$record['name']}: " . $e->getMessage();
            }
        }
        
        return [
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ];
    }
    
    /**
     * Get training alert list
     * 
     * Returns contractors with expired or soon-to-expire training (within 30 days).
     * Used by admin dashboard for proactive training management.
     * 
     * @return array Array of contractor records with training status
     */
    public function getTrainingAlerts() {
        $stmt = $this->pdo->prepare("
            SELECT name, email, company, last_training_date, training_expires_date,
                   CASE 
                       WHEN DATE(training_expires_date) < DATE('now') THEN 'expired'
                       WHEN DATE(training_expires_date) BETWEEN DATE('now') AND DATE('now', '+30 days') THEN 'expiring_soon'
                       ELSE 'current'
                   END as status
            FROM visitors 
            WHERE training_type = 'contractor' 
            AND training_expires_date IS NOT NULL
            AND DATE(training_expires_date) <= DATE('now', '+30 days')
            ORDER BY training_expires_date ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ============================================================================
    // AUTO-PURGE METHODS
    // Automated database maintenance - removes inactive visitor records
    // ============================================================================
    
    /**
     * Purge old visitor records
     * 
     * Automatically removes visitor records with NO check-ins for the configured period.
     * This keeps the database lean and maintains GDPR/data retention compliance.
     * 
     * Process:
     * 1. Identifies visitors with last check-in > configured months ago (or never)
     * 2. Deletes their visit_log entries (due to foreign key constraints)
     * 3. Deletes the visitor records
     * 4. Logs all actions for audit trail
     * 
     * @return array Results with keys:
     *               deleted_count (int), message (string), 
     *               deleted_visitors (array), error (string, if failed)
     */
    public function purgeOldVisitors() {
        try {
            // Get purge period from config or default to 24 months
            $purge_months = defined('AUTO_PURGE_MONTHS') ? AUTO_PURGE_MONTHS : 24;
            
            // Calculate cutoff date
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$purge_months} months"));
            
            // Find visitors who haven't checked in for the configured period (or never checked in)
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT v.id, v.name, MAX(vl.check_in_time) as last_checkin
                FROM visitors v
                LEFT JOIN visit_log vl ON v.id = vl.visitor_id
                GROUP BY v.id
                HAVING last_checkin IS NULL OR last_checkin < ?
            ");
            $stmt->execute([$cutoff_date]);
            $old_visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($old_visitors)) {
                return [
                    'deleted_count' => 0,
                    'message' => "No visitors found older than {$purge_months} months"
                ];
            }
            
            // Extract visitor IDs and create SQL placeholders
            $visitor_ids = array_map(function($v) { return $v['id']; }, $old_visitors);
            $placeholders = implode(',', array_fill(0, count($visitor_ids), '?'));
            
            // Delete visit logs first (foreign key constraint requirement)
            $stmt = $this->pdo->prepare("DELETE FROM visit_log WHERE visitor_id IN ($placeholders)");
            $stmt->execute($visitor_ids);
            
            // Delete the visitor records
            $stmt = $this->pdo->prepare("DELETE FROM visitors WHERE id IN ($placeholders)");
            $stmt->execute($visitor_ids);
            
            // Log purge action for audit trail
            $this->logToFile("Auto-purge: Deleted " . count($old_visitors) . " visitors with no check-ins since $cutoff_date");
            
            return [
                'deleted_count' => count($old_visitors),
                'message' => 'Successfully purged ' . count($old_visitors) . ' old visitor records',
                'deleted_visitors' => $old_visitors
            ];
            
        } catch (Exception $e) {
            $this->logToFile("Auto-purge error: " . $e->getMessage());
            return [
                'deleted_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if auto-purge should run today
     * 
     * Uses a lock file to ensure purge runs only once per day.
     * Lock file stores the last purge date in Y-m-d format.
     * 
     * @return bool True if purge should run, false if already ran today
     */
    public function shouldRunAutoPurge() {
        $lock_file = __DIR__ . '/../data/last_purge.txt';
        
        // If lock file doesn't exist, purge has never run
        if (!file_exists($lock_file)) {
            return true;
        }
        
        // Read last purge date from lock file
        $last_purge = file_get_contents($lock_file);
        $last_purge_date = DateTime::createFromFormat('Y-m-d', $last_purge);
        $today = new DateTime();
        
        // Run if last purge was before today
        if (!$last_purge_date || $last_purge_date->format('Y-m-d') < $today->format('Y-m-d')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Mark auto-purge as completed for today
     * 
     * Updates the lock file with today's date to prevent multiple purge runs.
     * Called automatically after successful purge operation.
     * 
     * @return void
     */
    public function markPurgeComplete() {
        $lock_file = __DIR__ . '/../data/last_purge.txt';
        file_put_contents($lock_file, date('Y-m-d'));
    }
    
    // ============================================================================
    // UTILITY METHODS
    // Internal helper methods for logging and system operations
    // ============================================================================
    
    /**
     * Safe file logging that doesn't interfere with JSON API responses
     * 
     * Writes log messages to file only (not stdout/stderr) to prevent
     * corrupting JSON responses from API endpoints. Includes timezone
     * in timestamps for accurate audit trails.
     * 
     * Log Format: [Y-m-d H:i:s TZ] Message
     * 
     * @param string $message Message to log
     * @return void
     */
    private function logToFile($message) {
        // Use configured log file or default
        $log_file = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/../data/visitor_system.log';
        $timestamp = date('Y-m-d H:i:s T'); // Include timezone
        $log_message = "[$timestamp] $message" . PHP_EOL;
        
        // Use @ to suppress any file operation warnings that could break JSON responses
        // LOCK_EX ensures atomic writes in multi-user environment
        @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    }
}
?>
