<?php
/**
 * Visitor Management System - Configuration Example
 * 
 * This is a template configuration file. Copy this to config.php and customize
 * for your organization, or use the setup wizard for automated configuration.
 * 
 * @package    VisitorManagement
 * @author     Yeyland Wutani LLC
 * @version    1.0.0
 * @since      2024-12-08
 */

if (!defined('CONFIG_LOADED')) {
    die('Direct access to configuration file is not allowed.');
}

// ORGANIZATION SETTINGS
define('ORG_NAME', 'Your Organization Name');
define('ORG_SHORT_NAME', 'OrgName');
define('ORG_TAGLINE', 'Welcome to our facility');

// BRANDING COLORS (use your organization's colors)
define('COLOR_PRIMARY', '#0066CC');
define('COLOR_SECONDARY', '#0052A3');
define('COLOR_ACCENT', '#FF6600');
define('COLOR_TEXT_LIGHT', '#FFFFFF');
define('COLOR_TEXT_DARK', '#333333');

// LOGO AND ASSETS
define('LOGO_PATH', 'assets/logo.svg');

// TIMEZONE
define('TIMEZONE', 'America/Los_Angeles');

// DATABASE
define('DB_PATH', __DIR__ . '/../data/visitors.db');

// VISITOR TYPES
// Configure your visitor types and their requirements
$VISITOR_TYPES = [
    'visitor' => [
        'label' => 'Visitor (General)',
        'requires_orientation' => false,
        'requires_annual_training' => false,
        'video_path' => null
    ],
    'contractor' => [
        'label' => 'Contractor',
        'requires_orientation' => true,
        'requires_annual_training' => true,
        'training_expires_months' => 12,
        'video_path' => 'res/contractor_orientation.mp4',
        'smartsheet_url' => ''  // Leave empty if not using external forms
    ]
];

// STAFF CONTACTS
// List of people visitors can check in to see
$STAFF_CONTACTS = [
    'John Smith',
    'Jane Doe',
    'Bob Johnson',
    'Alice Williams'
];

// ADMIN AUTH (CHANGE THESE!)
// Create a secure password and generate hash with: password_hash('YourPassword', PASSWORD_BCRYPT)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', password_hash('changeme', PASSWORD_BCRYPT));

// SESSION SETTINGS
define('SESSION_NAME', 'visitor_mgmt_session');
define('SESSION_LIFETIME', 3600);        // 1 hour
define('SESSION_TIMEOUT', 1800);         // 30 minutes

// DATA RETENTION
define('AUTO_PURGE_ENABLED', true);
define('AUTO_PURGE_MONTHS', 24);         // Keep records for 24 months
define('AUTO_PURGE_RUN_TIME', '03:00');  // Run purge daily at 3:00 AM

// FEATURE FLAGS
define('ENABLE_BADGE_NUMBER', true);
define('ENABLE_VISITOR_CONTACT', true);
define('ENABLE_ORIENTATION_VIDEO', false);
define('ENABLE_TRAINING_MANAGEMENT', true);

// UI CUSTOMIZATION
define('AUTO_REFRESH_INTERVAL', 30);     // Seconds
define('RECENT_VISITS_LIMIT', 20);       // Number of recent visits to display
define('CURRENT_VISITORS_REFRESH', 30);  // Seconds

// LOGGING
define('ENABLE_LOGGING', true);
define('LOG_FILE', __DIR__ . '/../data/system.log');
define('LOG_LEVEL', 'INFO');

// DEBUG MODE (set to false in production!)
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);

// CUSTOM CSS (optional)
// Path to custom CSS file for additional styling
define('CUSTOM_CSS_PATH', null);

// Load environment-specific overrides if they exist
// Create config.local.php for local development overrides
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
?>
