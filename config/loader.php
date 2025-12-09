<?php
/**
 * Configuration Loader
 * 
 * Handles loading and validation of system configuration.
 * Include this file at the beginning of every PHP script.
 * 
 * @package    VisitorManagement
 * @author     Yeyland Wutani LLC
 * @version    1.0.0
 */

define('CONFIG_LOADED', true);
ob_start();

// Check if configuration file exists
$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    // Allow access to setup directory
    if (!isset($_SERVER['REQUEST_URI']) || 
        (strpos($_SERVER['REQUEST_URI'], '/setup/') === false && 
         strpos($_SERVER['REQUEST_URI'], 'setup/') !== 0)) {
        // Redirect to setup wizard
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $setupUrl = $protocol . '://' . $host . $baseDir . '/setup/';
        header('Location: ' . $setupUrl);
        exit;
    }
    // Temporary config for setup wizard
    define('ORG_NAME', 'Visitor Management System');
    define('DEBUG_MODE', true);
    return;
}

require_once $config_path;

// Validate required constants
$required_constants = ['ORG_NAME', 'COLOR_PRIMARY', 'COLOR_SECONDARY', 'TIMEZONE', 'DB_PATH', 'ADMIN_USERNAME', 'ADMIN_PASSWORD'];
$missing_constants = [];
foreach ($required_constants as $constant) {
    if (!defined($constant)) {
        $missing_constants[] = $constant;
    }
}

if (!empty($missing_constants)) {
    die('Configuration error: Missing required constants: ' . implode(', ', $missing_constants));
}

if (!isset($VISITOR_TYPES) || empty($VISITOR_TYPES)) {
    die('Configuration error: $VISITOR_TYPES must be defined');
}

if (!isset($STAFF_CONTACTS) || !is_array($STAFF_CONTACTS)) {
    $STAFF_CONTACTS = [];
}

date_default_timezone_set(TIMEZONE);

if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

if (defined('SESSION_NAME')) {
    ini_set('session.name', SESSION_NAME);
}
if (defined('SESSION_LIFETIME')) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

function get_config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

function get_visitor_type($type_key) {
    global $VISITOR_TYPES;
    return isset($VISITOR_TYPES[$type_key]) ? $VISITOR_TYPES[$type_key] : null;
}

function get_staff_contacts() {
    global $STAFF_CONTACTS;
    return $STAFF_CONTACTS;
}

function get_smartsheet_config() {
    global $SMARTSHEET_CONFIG;
    return isset($SMARTSHEET_CONFIG) ? $SMARTSHEET_CONFIG : ['enabled' => false];
}

function get_notification_settings() {
    global $NOTIFICATION_SETTINGS;
    return isset($NOTIFICATION_SETTINGS) ? $NOTIFICATION_SETTINGS : ['email' => ['enabled' => false], 'sms' => ['enabled' => false]];
}

function generate_color_css() {
    $css = ":root {\n";
    $css .= "    --color-primary: " . COLOR_PRIMARY . ";\n";
    $css .= "    --color-secondary: " . COLOR_SECONDARY . ";\n";
    $css .= "    --color-accent: " . get_config('COLOR_ACCENT', COLOR_PRIMARY) . ";\n";
    $css .= "    --color-text-light: " . get_config('COLOR_TEXT_LIGHT', '#FFFFFF') . ";\n";
    $css .= "    --color-text-dark: " . get_config('COLOR_TEXT_DARK', '#333333') . ";\n";
    $css .= "}\n";
    return $css;
}

function log_message($message, $level = 'INFO') {
    if (!get_config('ENABLE_LOGGING', false)) {
        return;
    }
    
    $log_file = get_config('LOG_FILE', __DIR__ . '/../data/system.log');
    $timestamp = date('Y-m-d H:i:s T');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>
