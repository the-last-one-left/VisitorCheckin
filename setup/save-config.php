<?php
/**
 * Setup Wizard - Configuration Save Handler
 * 
 * @package    VisitorManagement
 * @author     Yeyland Wutani LLC
 * @version    1.0.1
 */

// No output before JSON
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', '0');

$response = ['success' => false];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Collect configuration
    $config = [
        'org_name' => trim($_POST['org_name'] ?? ''),
        'org_short_name' => trim($_POST['org_short_name'] ?? ''),
        'org_tagline' => trim($_POST['org_tagline'] ?? 'Welcome'),
        'timezone' => trim($_POST['timezone'] ?? 'America/Los_Angeles'),
        'color_primary' => trim($_POST['color_primary'] ?? '#0066CC'),
        'color_secondary' => trim($_POST['color_secondary'] ?? '#0052A3'),
        'color_accent' => trim($_POST['color_accent'] ?? '#FF6600'),
        'visitor_mode' => trim($_POST['visitor_mode'] ?? 'visitors_only'),
        'orientation_method' => trim($_POST['orientation_method'] ?? 'none'),
        'external_form_url' => trim($_POST['external_form_url'] ?? ''),
        'training_renewal_months' => (int)($_POST['training_renewal_months'] ?? 12),
        'recent_visits_limit' => (int)($_POST['recent_visits_limit'] ?? 20),
        'auto_purge_months' => (int)($_POST['auto_purge_months'] ?? 24),
        'admin_username' => trim($_POST['admin_username'] ?? ''),
        'admin_password' => $_POST['admin_password'] ?? ''
    ];
    
    // Validate required
    if (empty($config['org_name'])) throw new Exception('Organization name required');
    if (empty($config['admin_username'])) throw new Exception('Admin username required');
    if (strlen($config['admin_password']) < 8) throw new Exception('Password must be 8+ characters');
    
    // Handle logo upload
    $logoPath = 'assets/logo.svg';
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg'])) {
            $logoFilename = 'logo.' . $ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $logoFilename)) {
                $logoPath = 'assets/' . $logoFilename;
            }
        }
    }
    
    // Staff contacts
    $staffContacts = [];
    $staffRaw = trim($_POST['staff_contacts'] ?? '');
    if (!empty($staffRaw)) {
        $staffContacts = array_filter(array_map('trim', explode("\n", $staffRaw)));
    }
    
    // Generate config.php
    $configContent = generateConfig($config, $logoPath, $staffContacts);
    $configPath = __DIR__ . '/../config/config.php';
    
    if (!file_put_contents($configPath, $configContent)) {
        throw new Exception('Failed to write config file');
    }
    
    // Initialize database
    createDatabase($config);
    
    $response['success'] = true;
    $response['message'] = 'Setup completed successfully';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;

/**
 * Generate configuration file
 */
function generateConfig($cfg, $logo, $contacts) {
    $hash = password_hash($cfg['admin_password'], PASSWORD_BCRYPT);
    
    // Visitor types
    $types = "    'visitor' => [\n";
    $types .= "        'label' => 'Visitor (General)',\n";
    $types .= "        'requires_orientation' => false,\n";
    $types .= "        'requires_annual_training' => false,\n";
    $types .= "        'video_path' => null\n";
    $types .= "    ]";
    
    if ($cfg['visitor_mode'] === 'visitors_contractors') {
        $needsOrient = ($cfg['orientation_method'] !== 'none') ? 'true' : 'false';
        $videoPath = ($cfg['orientation_method'] === 'video') ? "'res/contractor_orientation.mp4'" : 'null';
        $smartUrl = ($cfg['orientation_method'] === 'external_form' && !empty($cfg['external_form_url'])) 
            ? "'" . addslashes($cfg['external_form_url']) . "'" : "''";
        
        $types .= ",\n    'contractor' => [\n";
        $types .= "        'label' => 'Contractor',\n";
        $types .= "        'requires_orientation' => {$needsOrient},\n";
        $types .= "        'requires_annual_training' => true,\n";
        $types .= "        'training_expires_months' => {$cfg['training_renewal_months']},\n";
        $types .= "        'video_path' => {$videoPath},\n";
        $types .= "        'smartsheet_url' => {$smartUrl}\n";
        $types .= "    ]";
    }
    
    // Staff contacts
    $contactsStr = '';
    foreach ($contacts as $contact) {
        $contactsStr .= "    '" . addslashes($contact) . "',\n";
    }
    
    $enableTraining = ($cfg['visitor_mode'] === 'visitors_contractors') ? 'true' : 'false';
    $enableOrientVideo = ($cfg['orientation_method'] === 'video') ? 'true' : 'false';
    $autoPurge = ($cfg['auto_purge_months'] > 0) ? 'true' : 'false';
    
    return "<?php\n" .
"/**\n" .
" * Visitor Management System Configuration\n" .
" * Generated: " . date('Y-m-d H:i:s') . "\n" .
" */\n\n" .
"if (!defined('CONFIG_LOADED')) {\n" .
"    die('Direct access not allowed');\n" .
"}\n\n" .
"// Organization\n" .
"define('ORG_NAME', '" . addslashes($cfg['org_name']) . "');\n" .
"define('ORG_SHORT_NAME', '" . addslashes($cfg['org_short_name']) . "');\n" .
"define('ORG_TAGLINE', '" . addslashes($cfg['org_tagline']) . "');\n\n" .
"// Branding\n" .
"define('COLOR_PRIMARY', '{$cfg['color_primary']}');\n" .
"define('COLOR_SECONDARY', '{$cfg['color_secondary']}');\n" .
"define('COLOR_ACCENT', '{$cfg['color_accent']}');\n" .
"define('COLOR_TEXT_LIGHT', '#FFFFFF');\n" .
"define('COLOR_TEXT_DARK', '#333333');\n\n" .
"// Assets\n" .
"define('LOGO_PATH', '{$logo}');\n\n" .
"// Timezone\n" .
"define('TIMEZONE', '{$cfg['timezone']}');\n\n" .
"// Database\n" .
"define('DB_PATH', __DIR__ . '/../data/visitors.db');\n\n" .
"// Visitor Types\n" .
"\$VISITOR_TYPES = [\n{$types}\n];\n\n" .
"// Staff Contacts\n" .
"\$STAFF_CONTACTS = [\n{$contactsStr}];\n\n" .
"// Admin\n" .
"define('ADMIN_USERNAME', '" . addslashes($cfg['admin_username']) . "');\n" .
"define('ADMIN_PASSWORD', '{$hash}');\n\n" .
"// Session\n" .
"define('SESSION_NAME', 'visitor_mgmt_session');\n" .
"define('SESSION_LIFETIME', 3600);\n" .
"define('SESSION_TIMEOUT', 1800);\n\n" .
"// Data Retention\n" .
"define('AUTO_PURGE_ENABLED', {$autoPurge});\n" .
"define('AUTO_PURGE_MONTHS', {$cfg['auto_purge_months']});\n" .
"define('AUTO_PURGE_RUN_TIME', '03:00');\n\n" .
"// Features\n" .
"define('ENABLE_BADGE_NUMBER', true);\n" .
"define('ENABLE_VISITOR_CONTACT', true);\n" .
"define('ENABLE_ORIENTATION_VIDEO', {$enableOrientVideo});\n" .
"define('ENABLE_TRAINING_MANAGEMENT', {$enableTraining});\n\n" .
"// UI\n" .
"define('AUTO_REFRESH_INTERVAL', 30);\n" .
"define('RECENT_VISITS_LIMIT', {$cfg['recent_visits_limit']});\n" .
"define('CURRENT_VISITORS_REFRESH', 30);\n\n" .
"// Logging\n" .
"define('ENABLE_LOGGING', true);\n" .
"define('LOG_FILE', __DIR__ . '/../data/system.log');\n" .
"define('LOG_LEVEL', 'INFO');\n\n" .
"// Debug\n" .
"define('DEBUG_MODE', false);\n" .
"define('DISPLAY_ERRORS', false);\n" .
"define('CUSTOM_CSS_PATH', null);\n";
}

/**
 * Create database with proper schema
 */
function createDatabase($cfg) {
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
    
    $dbPath = $dataDir . '/visitors.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS visitors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company TEXT,
            email TEXT,
            phone TEXT,
            badge_number TEXT,
            visitor_type TEXT DEFAULT 'visitor',
            suterra_contact TEXT,
            contractor_orientation_completed INTEGER DEFAULT 0,
            visitor_orientation_completed INTEGER DEFAULT 0,
            training_type TEXT DEFAULT 'none',
            last_training_date TEXT,
            training_expires_date TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS visit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            visitor_id INTEGER NOT NULL,
            tablet_id TEXT,
            check_in_time TEXT NOT NULL,
            check_out_time TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visitor_id) REFERENCES visitors(id)
        );
        
        CREATE TABLE IF NOT EXISTS training (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            company TEXT,
            email TEXT,
            training_date TEXT,
            last_visit TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE INDEX IF NOT EXISTS idx_visitors_name ON visitors(name);
        CREATE INDEX IF NOT EXISTS idx_visitors_type ON visitors(visitor_type);
        CREATE INDEX IF NOT EXISTS idx_visit_log_visitor ON visit_log(visitor_id);
        CREATE INDEX IF NOT EXISTS idx_visit_log_checkin ON visit_log(check_in_time DESC);
        CREATE INDEX IF NOT EXISTS idx_training_name ON training(name);
        CREATE INDEX IF NOT EXISTS idx_training_date ON training(training_date DESC);
    ");
    
    chmod($dbPath, 0664);
}
