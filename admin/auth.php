<?php
/**
 * Admin Authentication System
 * 
 * Provides password-based authentication for the admin dashboard with session
 * management and login form. Credentials are configured via the setup wizard.
 * 
 * Security Architecture:
 * - Single admin password (bcrypt hashed)
 * - PHP native session management
 * - Session flag: $_SESSION['admin_authenticated']
 * - No automatic timeout (implement if needed)
 * 
 * Functions Provided:
 * - isAuthenticated(): Check if current session is authenticated
 * - authenticate($password): Validate password and set session
 * - requireAuth(): Guard admin pages from unauthorized access
 * - showLoginForm(): Display HTML login interface
 * 
 * Usage Pattern:
 * ```php
 * require_once 'auth.php';
 * requireAuth(); // User must be logged in to proceed
 * ```
 * 
 * @package    VisitorManagement
 * @subpackage Admin
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    1.0
 */

// Load configuration
require_once __DIR__ . '/../config/loader.php';

// ============================================================================
// SESSION INITIALIZATION
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

/**
 * Check if current session is authenticated
 * 
 * @return bool True if session is authenticated, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

/**
 * Authenticate user with password
 * 
 * Validates the provided password against the configured admin password
 * using password_verify for bcrypt hash comparison.
 * 
 * @param string $password The password to validate
 * @return bool True if password is correct, false otherwise
 */
function authenticate($password) {
    $admin_password_hash = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : null;
    
    if (!$admin_password_hash) {
        return false;
    }
    
    if (password_verify($password, $admin_password_hash)) {
        $_SESSION['admin_authenticated'] = true;
        return true;
    }
    return false;
}

/**
 * Require authentication or show login form
 * 
 * Guards admin pages from unauthorized access. If not authenticated,
 * displays login form and exits to prevent page content from loading.
 * 
 * @return void Exits script if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        showLoginForm();
        exit;
    }
}

/**
 * Display login form
 * 
 * Renders HTML login interface with organization branding from config.
 * 
 * @return void Outputs HTML directly to browser
 */
function showLoginForm() {
    // Get branding from config
    $org_name = defined('ORG_NAME') ? ORG_NAME : 'Visitor Management System';
    $logo_path = defined('LOGO_PATH') ? '../' . LOGO_PATH : '';
    $color_primary = defined('COLOR_PRIMARY') ? COLOR_PRIMARY : '#0066CC';
    $color_secondary = defined('COLOR_SECONDARY') ? COLOR_SECONDARY : '#0052A3';
    
    // Generate gradient CSS from config colors
    $gradient = "linear-gradient(135deg, $color_primary 0%, $color_secondary 100%)";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - <?php echo htmlspecialchars($org_name); ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: <?php echo $gradient; ?>;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .login-container {
                background: white;
                border-radius: 20px;
                padding: 50px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
                text-align: center;
            }
            
            .logo {
                max-height: 80px;
                max-width: 200px;
                width: auto;
                height: auto;
                margin-bottom: 20px;
                opacity: 0.9;
            }
            
            .login-container h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 1.8em;
            }
            
            .login-container .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 0.9em;
            }
            
            .form-group {
                margin-bottom: 25px;
                text-align: left;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }
            
            .form-group input {
                width: 100%;
                padding: 15px;
                border: 2px solid #ddd;
                border-radius: 10px;
                font-size: 1.1em;
                transition: border-color 0.3s;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: <?php echo $color_primary; ?>;
            }
            
            .btn {
                background: <?php echo $gradient; ?>;
                color: white;
                padding: 15px 40px;
                border: none;
                border-radius: 10px;
                font-size: 1.2em;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                width: 100%;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            }
            
            .error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 20px;
                border: 1px solid #f5c6cb;
            }
            
            @media (max-width: 480px) {
                .login-container {
                    padding: 30px 20px;
                }
                
                .login-container h1 {
                    font-size: 1.5em;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <?php if ($logo_path && file_exists(__DIR__ . '/' . $logo_path)): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($org_name); ?>" class="logo">
            <?php endif; ?>
            
            <h1>Admin Login</h1>
            <p class="subtitle"><?php echo htmlspecialchars($org_name); ?></p>
            
            <?php if (isset($_POST['password']) && !authenticate($_POST['password'])): ?>
                <div class="error">Invalid password. Please try again.</div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

// ============================================================================
// FORM SUBMISSION HANDLER
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (authenticate($_POST['password'])) {
        // Success - redirect to clear POST data
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
