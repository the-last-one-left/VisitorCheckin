<?php
/**
 * Setup Wizard - Configuration Save Handler
 * 
 * Processes submitted configuration from the wizard and generates
 * the config.php file, initializes the database, and creates necessary
 * directories and files.
 * 
 * @package    VisitorManagement
 * @subpackage Setup
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    1.0.0
 * @since      2024-12-08
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// ============================================================================
// CONFIGURATION CHECK
// ============================================================================

$config_file = __DIR__ . '/../config/config.php';

// Don't allow running if already configured
if (file_exists($config_file)) {
    echo json_encode([
        'success' => false,
        'message' => 'System is already configured. Delete config.php to run setup again.'
    ]);
    exit;
}

// ============================================================================
// VALIDATE REQUEST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// ============================================================================
// COLLECT AND VALIDATE INPUT
// ============================================================================

$errors = [];
$config = [];

// Organization details
$config['org_name'] = trim($_POST['org_name'] ?? '');
$config['org_short_name'] = trim($_POST['org_short_name'] ?? '');
$config['org_tagline'] = trim($_POST['org_tagline'] ?? 'Welcome to our facility');
$config['timezone'] = trim($_POST['timezone'] ?? 'America/Los_Angeles');

if (empty($config['org_name'])) {
    $errors[] = 'Organization name is required';
}
if (empty($config['org_short_name'])) {
    $errors[] = 'Organization short name is required';
}

// Branding
$config['color_primary'] = trim($_POST['color_primary'] ?? '#0066CC');
$config['color_secondary'] = trim($_POST['color_secondary'] ?? '#0052A3');
$config['color_accent'] = trim($_POST['color_accent'] ?? '#FF6600');

// Validate hex colors
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $config['color_primary'])) {
    $errors[] = 'Invalid primary color format';
}
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $config['color_secondary'])) {
    $errors[] = 'Invalid secondary color format';
}
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $config['color_accent'])) {
    $errors[] = 'Invalid accent color format';
}

// Features
$config['visitor_mode'] = trim($_POST['visitor_mode'] ?? 'visitors_only');
$config['orientation_method'] = trim($_POST['orientation_method'] ?? 'none');
$config['external_form_url'] = trim($_POST['external_form_url'] ?? '');
$config['training_renewal_months'] = intval($_POST['training_renewal_months'] ?? 12);
$config['recent_visits_limit'] = intval($_POST['recent_visits_limit'] ?? 20);
$config['auto_purge_months'] = intval($_POST['auto_purge_months'] ?? 24);

// Staff contacts
$config['staff_contacts'] = [];
$staff_contacts_raw = trim($_POST['staff_contacts'] ?? '');
if (!empty($staff_contacts_raw)) {
    $config['staff_contacts'] = array_filter(
        array_map('trim', explode("\n", $staff_contacts_raw)),
        function($contact) { return !empty($contact); }
    );
}

// Admin credentials
$config['admin_username'] = trim($_POST['admin_username'] ?? '');
$config['admin_password'] = $_POST['admin_password'] ?? '';

if (empty($config['admin_username']) || strlen($config['admin_username']) < 3) {
    $errors[] = 'Admin username must be at least 3 characters';
}
if (empty($config['admin_password']) || strlen($config['admin_password']) < 8) {
    $errors[] = 'Admin password must be at least 8 characters';
}

// Return errors if any
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// ============================================================================
// HANDLE LOGO UPLOAD
// ============================================================================

$logo_path = null;

if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $logo_file = $_FILES['logo_file'];
    
    // Validate file type
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $logo_file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid logo file type. Use PNG, JPG, or SVG.'
        ]);
        exit;
    }
    
    // Determine file extension
    $extension = match($mime_type) {
        'image/svg+xml' => 'svg',
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        default => 'png'
    };
    
    // Save logo to assets directory
    $assets_dir = __DIR__ . '/../assets';
    if (!is_dir($assets_dir)) {
        mkdir($assets_dir, 0755, true);
    }
    
    $logo_filename = 'logo.' . $extension;
    $logo_destination = $assets_dir . '/' . $logo_filename;
    
    if (move_uploaded_file($logo_file['tmp_name'], $logo_destination)) {
        $logo_path = 'assets/' . $logo_filename;
    }
}

// Default logo path if none uploaded
if (!$logo_path) {
    $logo_path = 'assets/logo.svg';
}

// ============================================================================
// GENERATE CONFIG FILE CONTENT
// ============================================================================

$config_content = "<?php\n";
$config_content .= "/**\n";
$config_content .= " * Visitor Management System Configuration\n";
$config_content .= " * \n";
$config_content .= " * Generated by setup wizard on " . date('Y-m-d H:i:s T') . "\n";
$config_content .= " * \n";
$config_content .= " * @package    VisitorManagement\n";
$config_content .= " * @author     Yeyland Wutani LLC\n";
$config_content .= " * @version    1.0.0\n";
$config_content .= " */\n\n";

$config_content .= "if (!defined('CONFIG_LOADED')) {\n";
$config_content .= "    die('Direct access to configuration file is not allowed.');\n";
$config_content .= "}\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// ORGANIZATION SETTINGS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('ORG_NAME', " . var_export($config['org_name'], true) . ");\n";
$config_content .= "define('ORG_SHORT_NAME', " . var_export($config['org_short_name'], true) . ");\n";
$config_content .= "define('ORG_TAGLINE', " . var_export($config['org_tagline'], true) . ");\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// BRANDING COLORS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('COLOR_PRIMARY', " . var_export($config['color_primary'], true) . ");\n";
$config_content .= "define('COLOR_SECONDARY', " . var_export($config['color_secondary'], true) . ");\n";
$config_content .= "define('COLOR_ACCENT', " . var_export($config['color_accent'], true) . ");\n";
$config_content .= "define('COLOR_TEXT_LIGHT', '#FFFFFF');\n";
$config_content .= "define('COLOR_TEXT_DARK', '#333333');\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// LOGO AND ASSETS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('LOGO_PATH', " . var_export($logo_path, true) . ");\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// TIMEZONE\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('TIMEZONE', " . var_export($config['timezone'], true) . ");\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// DATABASE\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('DB_PATH', __DIR__ . '/../data/visitors.db');\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// VISITOR TYPES\n";
$config_content .= "// ============================================================================\n\n";

// Build visitor types array based on mode
$config_content .= "\$VISITOR_TYPES = [\n";
$config_content .= "    'visitor' => [\n";
$config_content .= "        'label' => 'Visitor (General)',\n";
$config_content .= "        'requires_orientation' => false,\n";
$config_content .= "        'requires_annual_training' => false,\n";
$config_content .= "        'video_path' => null\n";
$config_content .= "    ]";

if ($config['visitor_mode'] === 'visitors_contractors') {
    $config_content .= ",\n    'contractor' => [\n";
    $config_content .= "        'label' => 'Contractor',\n";
    $config_content .= "        'requires_orientation' => " . ($config['orientation_method'] !== 'none' ? 'true' : 'false') . ",\n";
    $config_content .= "        'requires_annual_training' => true,\n";
    $config_content .= "        'training_expires_months' => " . $config['training_renewal_months'] . ",\n";
    
    if ($config['orientation_method'] === 'video') {
        $config_content .= "        'video_path' => 'res/contractor_orientation.mp4',\n";
    } else {
        $config_content .= "        'video_path' => null,\n";
    }
    
    if ($config['orientation_method'] === 'external_form' && !empty($config['external_form_url'])) {
        $config_content .= "        'smartsheet_url' => " . var_export($config['external_form_url'], true) . "\n";
    } else {
        $config_content .= "        'smartsheet_url' => ''\n";
    }
    
    $config_content .= "    ]";
}

$config_content .= "\n];\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// STAFF CONTACTS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "\$STAFF_CONTACTS = [\n";
foreach ($config['staff_contacts'] as $contact) {
    $config_content .= "    " . var_export($contact, true) . ",\n";
}
$config_content .= "];\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// ADMIN AUTHENTICATION\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('ADMIN_USERNAME', " . var_export($config['admin_username'], true) . ");\n";
$config_content .= "define('ADMIN_PASSWORD', " . var_export(password_hash($config['admin_password'], PASSWORD_BCRYPT), true) . ");\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// SESSION SETTINGS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('SESSION_NAME', 'visitor_mgmt_session');\n";
$config_content .= "define('SESSION_LIFETIME', 3600);        // 1 hour\n";
$config_content .= "define('SESSION_TIMEOUT', 1800);         // 30 minutes\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// DATA RETENTION\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('AUTO_PURGE_ENABLED', " . ($config['auto_purge_months'] > 0 ? 'true' : 'false') . ");\n";
$config_content .= "define('AUTO_PURGE_MONTHS', " . $config['auto_purge_months'] . ");\n";
$config_content .= "define('AUTO_PURGE_RUN_TIME', '03:00');\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// FEATURE FLAGS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('ENABLE_BADGE_NUMBER', true);\n";
$config_content .= "define('ENABLE_VISITOR_CONTACT', true);\n";
$config_content .= "define('ENABLE_ORIENTATION_VIDEO', " . ($config['orientation_method'] === 'video' ? 'true' : 'false') . ");\n";
$config_content .= "define('ENABLE_TRAINING_MANAGEMENT', " . ($config['visitor_mode'] === 'visitors_contractors' ? 'true' : 'false') . ");\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// UI CUSTOMIZATION\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('AUTO_REFRESH_INTERVAL', 30);     // Seconds\n";
$config_content .= "define('RECENT_VISITS_LIMIT', " . $config['recent_visits_limit'] . ");\n";
$config_content .= "define('CURRENT_VISITORS_REFRESH', 30);  // Seconds\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// LOGGING\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('ENABLE_LOGGING', true);\n";
$config_content .= "define('LOG_FILE', __DIR__ . '/../data/system.log');\n";
$config_content .= "define('LOG_LEVEL', 'INFO');\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// DEBUG MODE\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('DEBUG_MODE', false);\n";
$config_content .= "define('DISPLAY_ERRORS', false);\n\n";

$config_content .= "// ============================================================================\n";
$config_content .= "// CUSTOM CSS\n";
$config_content .= "// ============================================================================\n\n";

$config_content .= "define('CUSTOM_CSS_PATH', null);\n";

$config_content .= "?>";

// ============================================================================
// WRITE CONFIG FILE
// ============================================================================

try {
    $config_dir = __DIR__ . '/../config';
    
    if (!is_writable($config_dir)) {
        throw new Exception('Config directory is not writable');
    }
    
    if (file_put_contents($config_file, $config_content) === false) {
        throw new Exception('Failed to write configuration file');
    }
    
    // Set proper permissions
    chmod($config_file, 0644);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create configuration: ' . $e->getMessage()
    ]);
    exit;
}

// ============================================================================
// INITIALIZE DATABASE
// ============================================================================

try {
    $data_dir = __DIR__ . '/../data';
    
    // Create data directory if it doesn't exist
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
    }
    
    $db_path = $data_dir . '/visitors.db';
    
    // Create database
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables
    $schema = "
        CREATE TABLE IF NOT EXISTS visitors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company TEXT,
            email TEXT,
            badge_number TEXT,
            visitor_type TEXT DEFAULT 'visitor',
            suterra_contact TEXT,
            check_in_time TEXT NOT NULL,
            check_out_time TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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
        CREATE INDEX IF NOT EXISTS idx_visitors_check_in ON visitors(check_in_time DESC);
        CREATE INDEX IF NOT EXISTS idx_visitors_type ON visitors(visitor_type);
        CREATE INDEX IF NOT EXISTS idx_training_name ON training(name);
        CREATE INDEX IF NOT EXISTS idx_training_date ON training(training_date DESC);
    ";
    
    $db->exec($schema);
    
    // Set proper permissions
    chmod($db_path, 0664);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to initialize database: ' . $e->getMessage()
    ]);
    exit;
}

// ============================================================================
// CREATE NECESSARY DIRECTORIES
// ============================================================================

$directories = [
    __DIR__ . '/../assets',
    __DIR__ . '/../res',
    __DIR__ . '/../data'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ============================================================================
// SUCCESS RESPONSE
// ============================================================================

echo json_encode([
    'success' => true,
    'message' => 'Configuration completed successfully'
]);
?>
