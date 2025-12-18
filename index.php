<?php
/**
 * Visitor Check-In Interface - Main Entry Point
 * 
 * Primary visitor-facing interface for the visitor management system.
 * Provides touch-friendly tablet interface for visitor registration, check-in,
 * and check-out functionality.
 * 
 * Features:
 * - Dual visitor type support (Contractor/General Visitor)
 * - Auto-fill for returning visitors
 * - Real-time phone/email validation
 * - Video orientation playback OR external form integration for contractor training
 * - Current visitor display with check-out capability
 * 
 * Flow Logic:
 * - Contractors (new/expired training): Video/Form/Both based on config → Check in
 * - Contractors (valid training): Direct check-in
 * - Visitors (any): Direct check-in
 * 
 * @package    VisitorManagement
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    2.2
 */

// Load configuration
require_once __DIR__ . '/config/loader.php';

// ============================================================================
// DATABASE INITIALIZATION CHECK
// Redirect to setup if database doesn't exist
// ============================================================================

if (!file_exists(__DIR__ . '/data/visitors.db')) {
    if (file_exists(__DIR__ . '/setup/index.php')) {
        header('Location: setup/');
        exit;
    } else {
        die('Database not found. Please run setup first.');
    }
}

// Load branding configuration
$org_name = defined('ORG_NAME') ? ORG_NAME : 'Visitor Management System';
$org_tagline = defined('ORG_TAGLINE') ? ORG_TAGLINE : 'Welcome! Please check in below or find your name to check out.';
$logo_path = defined('LOGO_PATH') ? LOGO_PATH : 'sut-primary-logo.svg';
$color_primary = defined('COLOR_PRIMARY') ? COLOR_PRIMARY : '#0066CC';
$color_secondary = defined('COLOR_SECONDARY') ? COLOR_SECONDARY : '#0052A3';
$color_accent = defined('COLOR_ACCENT') ? COLOR_ACCENT : '#8B4513';

// ============================================================================
// CONTRACTOR ORIENTATION CONFIGURATION
// Get contractor orientation settings from visitor types config
// ============================================================================

$contractor_config = get_visitor_type('contractor');
$has_video = false;
$has_form = false;
$video_path = '';
$external_form_url = '';

if ($contractor_config) {
    // Check for video orientation
    if (!empty($contractor_config['video_path']) && file_exists(__DIR__ . '/' . $contractor_config['video_path'])) {
        $has_video = true;
        $video_path = $contractor_config['video_path'];
    }
    
    // Check for external form (Smartsheet or other)
    if (!empty($contractor_config['smartsheet_url'])) {
        $has_form = true;
        $external_form_url = $contractor_config['smartsheet_url'];
    }
}

// Get staff contacts for dropdown
$staff_contacts = function_exists('get_staff_contacts') ? get_staff_contacts() : [];

// Extract RGB values for rgba() with opacity
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

$primary_rgb = hexToRgb($color_primary);
$secondary_rgb = hexToRgb($color_secondary);
$accent_rgb = hexToRgb($color_accent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($org_name); ?> - Visitor Check-in</title>
    <style>
        :root {
            --color-primary: <?php echo $color_primary; ?>;
            --color-secondary: <?php echo $color_secondary; ?>;
            --color-accent: <?php echo $color_accent; ?>;
            --color-primary-rgb: <?php echo $primary_rgb['r'] . ', ' . $primary_rgb['g'] . ', ' . $primary_rgb['b']; ?>;
            --color-secondary-rgb: <?php echo $secondary_rgb['r'] . ', ' . $secondary_rgb['g'] . ', ' . $secondary_rgb['b']; ?>;
            --color-accent-rgb: <?php echo $accent_rgb['r'] . ', ' . $accent_rgb['g'] . ', ' . $accent_rgb['b']; ?>;
            --gradient-primary: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            --gradient-accent: linear-gradient(135deg, var(--color-accent) 0%, color-mix(in srgb, var(--color-accent), white 15%) 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            padding: 20px;
            touch-action: manipulation;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            min-height: calc(100vh - 40px);
        }
        
        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .logo {
            height: 80px;
            width: auto;
            filter: brightness(0) invert(1);
        }
        
        .admin-link {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9em;
            transition: all 0.3s;
            z-index: 10;
        }
        
        .admin-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .main-content {
            display: flex;
            min-height: 600px;
        }
        
        .checkin-section {
            flex: 1;
            padding: 40px;
            border-right: 2px solid #f0f0f0;
        }
        
        .visitors-section {
            flex: 1;
            padding: 40px;
            background: #f8f9fa;
        }
        
        .section-title {
            font-size: 1.8em;
            margin-bottom: 30px;
            color: #333;
            border-bottom: 3px solid var(--color-primary);
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1.1em;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        /* Number pad for phone and badge number */
        input[type="tel"], input[name="badge_number"] {
            font-size: 1.2em;
        }
        
        .optional {
            color: #666;
            font-size: 0.9em;
        }
        
        .auto-filled {
            background-color: #e8f5e8 !important;
            border-color: #4caf50 !important;
        }
        
        .auto-fill-indicator {
            background: #4caf50;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .btn {
            background: var(--gradient-primary);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 10px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(var(--color-primary-rgb), 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
        
        .btn-danger:hover {
            box-shadow: 0 10px 20px rgba(244, 67, 54, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
        
        #clear-form {
            margin-top: 10px;
        }
        
        .visitors-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .visitor-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .visitor-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .visitor-name {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .visitor-company {
            color: #666;
            font-size: 1em;
            margin-bottom: 10px;
        }
        
        .visitor-time {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .checkout-btn {
            background: var(--gradient-accent);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .checkout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(var(--color-accent-rgb), 0.3);
        }
        
        .alert {
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 1.1em;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .alert-info {
            background: #cce7ff;
            color: #0056b3;
            border: 2px solid #99d6ff;
        }
        
        .visitor-count {
            background: var(--color-primary);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .no-visitors {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            margin-top: 50px;
        }
        
        /* Video Player Modal */
        .video-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        
        .video-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 90%;
            max-height: 90%;
            text-align: center;
            position: relative;
        }
        
        .video-modal video {
            width: 100%;
            max-height: 70vh;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .video-modal h2 {
            color: var(--color-primary);
            margin-bottom: 20px;
        }
        
        .video-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-video {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-video-primary {
            background: var(--color-primary);
            color: white;
        }
        
        .btn-video-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-video:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
            .checkin-section {
                border-right: none;
                border-bottom: 2px solid #f0f0f0;
            }
            
            .header h1 {
                font-size: 1.8em;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo {
                height: 60px;
            }
            
            .checkin-section, .visitors-section {
                padding: 20px;
            }
            
            .admin-link {
                position: static;
                display: inline-block;
                margin-top: 15px;
            }
            
            .video-modal-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="admin/" class="admin-link">Admin Dashboard</a>
            <div class="header-content">
                <?php if (file_exists(__DIR__ . '/' . $logo_path)): ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($org_name); ?>" class="logo">
                <?php endif; ?>
                <div class="header-text">
                    <h1><?php echo htmlspecialchars($org_name); ?></h1>
                    <p><?php echo htmlspecialchars($org_tagline); ?></p>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Check-in Section -->
            <div class="checkin-section">
                <h2 class="section-title">Check In</h2>
                
                <div id="alert-container"></div>
                
                <div id="checkin-form">
                    <form id="visitor-form">
                        <?php if (defined('ENABLE_TRAINING_MANAGEMENT') && ENABLE_TRAINING_MANAGEMENT): ?>
                        <div class="form-group">
                            <label for="visitor_type">Visitor Type *</label>
                            <select id="visitor_type" name="visitor_type" required>
                                <option value="">Select visitor type...</option>
                                <option value="visitor">Visitor (General)</option>
                                <option value="contractor">Contractor</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" id="visitor_type" name="visitor_type" value="visitor">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="badge_number">Badge Number</label>
                            <input type="tel" id="badge_number" name="badge_number" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address <span id="email-indicator"></span></label>
                            <input type="email" id="email" name="email" required placeholder="example@company.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number * <span id="phone-indicator"></span></label>
                            <input type="tel" id="phone" name="phone" required inputmode="numeric" placeholder="(555) 123-4567" maxlength="14">
                        </div>
                        
                        <div class="form-group">
                            <label for="company">Company <span id="company-indicator"></span></label>
                            <input type="text" id="company" name="company" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="suterra_contact">Who are you visiting? <span class="optional">(Optional)</span></label>
                            <input type="text" id="suterra_contact" name="suterra_contact" list="staff-contacts" placeholder="Start typing name...">
                            <datalist id="staff-contacts">
                                <?php foreach ($staff_contacts as $contact): ?>
                                    <option value="<?php echo htmlspecialchars($contact); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <button type="submit" class="btn">Check In</button>
                        <button type="button" id="clear-form" class="btn btn-secondary">Clear Fields</button>
                    </form>
                </div>
            </div>
            
            <!-- Current Visitors Section -->
            <div class="visitors-section">
                <h2 class="section-title">Currently Here</h2>
                <div id="visitor-count" class="visitor-count">0 visitors</div>
                <div id="visitors-list" class="visitors-list">
                    <div class="no-visitors">Loading visitors...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * VisitorSystem Class
         * 
         * Handles all visitor check-in/check-out functionality including:
         * - Form submission and validation
         * - Returning visitor auto-fill
         * - Video orientation playback
         * - External form integration for contractor training
         * - Real-time visitor list updates
         */
        class VisitorSystem {
            constructor() {
                // Contractor orientation configuration from PHP
                this.hasVideo = <?php echo $has_video ? 'true' : 'false'; ?>;
                this.hasForm = <?php echo $has_form ? 'true' : 'false'; ?>;
                this.videoPath = '<?php echo $video_path; ?>';
                this.externalFormUrl = '<?php echo addslashes($external_form_url); ?>';
                
                // Current check-in state
                this.currentVisitorData = null;
                this.currentVisitorType = null;
                this.trainingExpired = false;
                this.videoCompleted = false;
                
                // Unique tablet identifier for tracking
                this.tabletId = this.getTabletId();
                
                // Configured timezone
                this.timezone = '<?php echo defined('TIMEZONE') ? TIMEZONE : 'America/Los_Angeles'; ?>';
                
                this.init();
            }
            
            /**
             * Get or create unique tablet identifier
             * Used for tracking which tablet performed check-ins
             */
            getTabletId() {
                let tabletId = localStorage.getItem('tablet_id');
                if (!tabletId) {
                    tabletId = 'tablet_' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem('tablet_id', tabletId);
                }
                return tabletId;
            }
            
            /**
             * Initialize the visitor system
             */
            init() {
                this.bindEvents();
                this.loadCurrentVisitors();
                // Refresh visitor list every 30 seconds
                setInterval(() => this.loadCurrentVisitors(), 30000);
            }
            
            /**
             * Bind all event listeners
             */
            bindEvents() {
                // Form submission
                document.getElementById('visitor-form').addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleCheckIn();
                });
                
                // Clear form button
                document.getElementById('clear-form').addEventListener('click', () => this.clearFields());
                
                // Auto-fill functionality based on name
                document.getElementById('name').addEventListener('blur', (e) => this.lookupVisitorByName(e.target.value));
                
                // Phone number formatting and validation
                const phoneInput = document.getElementById('phone');
                phoneInput.addEventListener('input', (e) => this.formatPhoneNumber(e));
                phoneInput.addEventListener('blur', (e) => this.validatePhoneNumber(e));
                
                // Email validation
                const emailInput = document.getElementById('email');
                emailInput.addEventListener('input', (e) => this.validateEmail(e));
                emailInput.addEventListener('blur', (e) => this.validateEmail(e));
            }
            
            /**
             * Look up existing visitor by name for auto-fill
             * @param {string} name - Visitor name to search
             */
            async lookupVisitorByName(name) {
                if (!name || name.trim().length < 2) return;
                
                try {
                    const response = await fetch(`api/search-visitors.php?q=${encodeURIComponent(name.trim())}&limit=1`);
                    const result = await response.json();
                    
                    if (result.success && result.visitors.length > 0) {
                        const visitor = result.visitors[0];
                        // Only auto-fill if the name matches exactly (case insensitive)
                        if (visitor.name.toLowerCase() === name.trim().toLowerCase()) {
                            this.autoFillVisitorData(visitor);
                        }
                    }
                } catch (error) {
                    console.log('Lookup failed:', error);
                }
            }
            
            /**
             * Auto-fill form fields with returning visitor data
             * @param {Object} visitor - Visitor data from database
             */
            autoFillVisitorData(visitor) {
                // Auto-select visitor type based on their previous check-ins
                const visitorTypeSelect = document.getElementById('visitor_type');
                if (visitor.suggested_visitor_type && visitorTypeSelect.value === '') {
                    visitorTypeSelect.value = visitor.suggested_visitor_type;
                    visitorTypeSelect.classList.add('auto-filled');
                    visitorTypeSelect.style.borderColor = '#4caf50';
                    visitorTypeSelect.style.background = '#e8f5e8';
                }
                
                // Auto-fill email, phone, company but keep them editable
                const emailField = document.getElementById('email');
                const phoneField = document.getElementById('phone');
                const companyField = document.getElementById('company');
                
                if (!emailField.value) {
                    emailField.value = visitor.email;
                    emailField.classList.add('auto-filled');
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    const isValidEmail = emailRegex.test(visitor.email);
                    this.updateEmailValidation(isValidEmail, true);
                    if (isValidEmail) {
                        document.getElementById('email-indicator').innerHTML = '<span class="auto-fill-indicator">Auto-filled ✓</span>';
                    } else {
                        document.getElementById('email-indicator').innerHTML = '<span class="auto-fill-indicator" style="background:#ffc107;">Auto-filled - Please verify</span>';
                    }
                }
                
                if (!phoneField.value) {
                    const cleanPhone = visitor.phone.replace(/\D/g, '');
                    if (cleanPhone.length === 10) {
                        phoneField.value = `(${cleanPhone.slice(0, 3)}) ${cleanPhone.slice(3, 6)}-${cleanPhone.slice(6, 10)}`;
                        this.updatePhoneValidation(true);
                        document.getElementById('phone-indicator').innerHTML = '<span class="auto-fill-indicator">Auto-filled ✓</span>';
                    } else {
                        phoneField.value = visitor.phone;
                        this.updatePhoneValidation(false);
                        document.getElementById('phone-indicator').innerHTML = '<span class="auto-fill-indicator" style="background:#ffc107;">Auto-filled - Please verify</span>';
                    }
                    phoneField.classList.add('auto-filled');
                }
                
                if (!companyField.value) {
                    companyField.value = visitor.company;
                    companyField.classList.add('auto-filled');
                    document.getElementById('company-indicator').innerHTML = '<span class="auto-fill-indicator">Auto-filled</span>';
                }
                
                // Auto-fill staff contact if available
                const staffContactField = document.getElementById('suterra_contact');
                if (visitor.staff_contact && !staffContactField.value) {
                    staffContactField.value = visitor.staff_contact;
                    staffContactField.classList.add('auto-filled');
                    staffContactField.style.borderColor = '#4caf50';
                    staffContactField.style.background = '#e8f5e8';
                }
                
                const typeText = visitor.suggested_visitor_type === 'contractor' ? 'contractor' : 'visitor';
                this.showAlert('info', `Information auto-filled for ${visitor.name} as ${typeText}. Please verify and update if needed.`);
            }
            
            /**
             * Handle check-in form submission
             * Routes to video/external form for contractors needing training, direct check-in otherwise
             */
            async handleCheckIn() {
                // Validate email before submitting
                const emailInput = document.getElementById('email');
                const emailValue = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailValue)) {
                    this.showAlert('error', 'Please enter a valid email address (example@company.com).');
                    emailInput.focus();
                    return;
                }
                
                // Validate phone number before submitting
                const phoneInput = document.getElementById('phone');
                const phoneValue = phoneInput.value.replace(/\D/g, '');
                if (phoneValue.length !== 10) {
                    this.showAlert('error', 'Please enter a valid 10-digit US phone number.');
                    phoneInput.focus();
                    return;
                }
                
                const formData = new FormData(document.getElementById('visitor-form'));
                const data = Object.fromEntries(formData);
                // Store clean phone number (digits only) for backend
                data.phone = phoneValue;
                data.tablet_id = this.tabletId;
                
                try {
                    const response = await fetch('api/checkin.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        if (result.needs_orientation) {
                            // Contractor needs training - route based on config
                            this.currentVisitorData = data;
                            this.currentVisitorData.visitor_id = result.visitor_id;
                            this.currentVisitorType = result.visitor_type || data.visitor_type;
                            this.trainingExpired = result.training_expired || false;
                            this.videoCompleted = false;
                            
                            // Check what orientation methods are configured
                            if (this.hasVideo && this.hasForm) {
                                // Both video and form - show video first
                                this.showOrientationVideo();
                            } else if (this.hasVideo && !this.hasForm) {
                                // Video only
                                this.showOrientationVideo();
                            } else if (!this.hasVideo && this.hasForm) {
                                // Form only
                                this.openExternalForm();
                            } else {
                                // Neither configured - this shouldn't happen but handle gracefully
                                this.showAlert('error', 'Orientation is required but not configured. Please contact administrator.');
                            }
                        } else {
                            // Direct check-in successful
                            let message = result.message;
                            if (result.training_warning) {
                                message += '\n\n⚠️ ' + result.training_warning;
                            }
                            this.showAlert('success', message);
                            this.resetForm();
                            this.loadCurrentVisitors();
                        }
                    } else {
                        this.showAlert('error', result.error);
                    }
                } catch (error) {
                    this.showAlert('error', 'Network error. Please try again.');
                }
            }
            
            /**
             * Show orientation video modal
             * Called for contractors requiring video orientation
             */
            showOrientationVideo() {
                if (!this.currentVisitorData || !this.hasVideo) return;
                
                // Create video modal
                const modal = document.createElement('div');
                modal.className = 'video-modal';
                modal.id = 'videoModal';
                
                const modalContent = document.createElement('div');
                modalContent.className = 'video-modal-content';
                
                const title = document.createElement('h2');
                title.textContent = this.trainingExpired ? '📋 Training Renewal Required' : '📋 Contractor Orientation Required';
                
                const video = document.createElement('video');
                video.id = 'orientationVideo';
                video.controls = true;
                video.autoplay = true;
                video.src = this.videoPath;
                
                const instructions = document.createElement('p');
                instructions.style.marginBottom = '20px';
                instructions.textContent = 'Please watch the complete orientation video before continuing.';
                
                const controls = document.createElement('div');
                controls.className = 'video-controls';
                
                const completeBtn = document.createElement('button');
                completeBtn.textContent = 'Complete Orientation';
                completeBtn.className = 'btn-video btn-video-primary';
                completeBtn.disabled = true;
                completeBtn.id = 'completeOrientationBtn';
                
                // Enable complete button when video ends
                video.addEventListener('ended', () => {
                    completeBtn.disabled = false;
                    this.videoCompleted = true;
                });
                
                completeBtn.onclick = () => {
                    document.body.removeChild(modal);
                    
                    // If form is also configured, offer to fill it out
                    if (this.hasForm) {
                        this.offerExternalForm();
                    } else {
                        // Video only - complete check-in
                        this.completeContractorCheckin(true, false);
                    }
                };
                
                controls.appendChild(completeBtn);
                
                // Add skip button if form is also available (for returning contractors)
                if (this.hasForm) {
                    const skipBtn = document.createElement('button');
                    skipBtn.textContent = 'Skip to Form';
                    skipBtn.className = 'btn-video btn-video-secondary';
                    skipBtn.onclick = () => {
                        video.pause();
                        document.body.removeChild(modal);
                        this.openExternalForm();
                    };
                    controls.appendChild(skipBtn);
                }
                
                modalContent.append(title, video, instructions, controls);
                modal.appendChild(modalContent);
                document.body.appendChild(modal);
            }
            
            /**
             * Offer external form after video completion (when both are configured)
             */
            offerExternalForm() {
                const modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1001;';
                
                const container = document.createElement('div');
                container.style.cssText = 'background:white;padding:30px;border-radius:15px;max-width:500px;text-align:center;border:3px solid var(--color-primary);';
                
                const title = document.createElement('h2');
                title.textContent = '✅ Video Complete!';
                title.style.cssText = 'color:var(--color-primary);margin-bottom:20px;';
                
                const message = document.createElement('p');
                message.innerHTML = 'Would you like to complete the additional training form?<br><br>This is optional but recommended for comprehensive training documentation.';
                message.style.cssText = 'font-size:1.1em;line-height:1.6;margin-bottom:25px;';
                
                const formBtn = document.createElement('button');
                formBtn.textContent = '📝 Complete Training Form';
                formBtn.style.cssText = 'background:var(--color-primary);color:white;border:none;padding:15px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:1.1em;width:100%;margin-bottom:10px;';
                formBtn.onclick = () => {
                    document.body.removeChild(modal);
                    this.openExternalForm();
                };
                
                const skipBtn = document.createElement('button');
                skipBtn.textContent = 'Skip Form & Check In';
                skipBtn.style.cssText = 'background:#6c757d;color:white;border:none;padding:15px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:1em;width:100%;';
                skipBtn.onclick = () => {
                    document.body.removeChild(modal);
                    this.completeContractorCheckin(true, false);
                };
                
                container.append(title, message, formBtn, skipBtn);
                modal.appendChild(container);
                document.body.appendChild(modal);
            }
            
            /**
             * Open pre-filled external form for contractor training
             * Called for contractors needing form-based training
             */
            openExternalForm() {
                if (!this.currentVisitorData) return;
                
                // Check if form URL is configured
                if (!this.externalFormUrl || this.externalFormUrl.trim() === '') {
                    this.showAlert('error', 'External training form is not configured. Please contact administrator.');
                    return;
                }
                
                // Build pre-filled form URL with visitor data
                const name = encodeURIComponent(this.currentVisitorData.name);
                const company = encodeURIComponent(this.currentVisitorData.company);
                const email = encodeURIComponent(this.currentVisitorData.email);
                
                const prefilledUrl = `${this.externalFormUrl}?Name=${name}&Company=${company}&Email=${email}`;
                
                // Create modal explaining what's happening
                const modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1001;';
                
                const container = document.createElement('div');
                container.style.cssText = 'background:white;padding:30px;border-radius:15px;max-width:500px;text-align:center;border:3px solid var(--color-primary);';
                
                const title = document.createElement('h2');
                title.textContent = this.trainingExpired ? '📋 Training Renewal Required' : '📋 Contractor Training Required';
                title.style.cssText = 'color:var(--color-primary);margin-bottom:20px;font-size:1.5em;';
                
                const message = document.createElement('p');
                if (this.trainingExpired) {
                    message.innerHTML = `
                        Your contractor training has expired and needs to be renewed.<br><br>
                        <strong>We've pre-filled your information in the training form.</strong><br><br>
                        Click the button below to complete the mandatory training.
                    `;
                } else {
                    message.innerHTML = `
                        As a new contractor, you must complete the safety training.<br><br>
                        <strong>We've pre-filled your information in the training form.</strong><br><br>
                        Click the button below to complete the mandatory training.
                    `;
                }
                message.style.cssText = 'font-size:1.1em;line-height:1.6;margin-bottom:25px;color:#333;';
                
                const instructions = document.createElement('div');
                instructions.innerHTML = `
                    <p style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;font-size:0.9em;color:#666;">
                        <strong>Instructions:</strong><br>
                        1. Click "Complete Training Form" to open the form<br>
                        2. Fill out all required training information<br>
                        3. Submit the form when complete<br>
                        4. You will be automatically checked in after opening the form
                    </p>
                `;
                
                const formBtn = document.createElement('button');
                formBtn.textContent = '📝 Complete Training Form';
                formBtn.style.cssText = 'background:var(--color-primary);color:white;border:none;padding:15px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:1.1em;width:100%;';
                formBtn.onclick = () => {
                    // Open the pre-filled external form in new window
                    window.open(prefilledUrl, '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
                    
                    // Close modal
                    document.body.removeChild(modal);
                    
                    // Complete the check-in process
                    this.completeContractorCheckin(this.videoCompleted, true);
                };
                
                container.append(title, message, instructions, formBtn);
                modal.appendChild(container);
                document.body.appendChild(modal);
            }
            
            /**
             * Complete contractor check-in after orientation (video/form)
             * @param {boolean} videoCompleted - Whether video orientation was completed
             * @param {boolean} formOpened - Whether external form was opened
             */
            async completeContractorCheckin(videoCompleted, formOpened) {
                if (!this.currentVisitorData) return;
                
                // Mark contractor orientation as completed
                this.currentVisitorData.contractor_orientation_completed = true;
                this.currentVisitorData.video_orientation_completed = videoCompleted;
                this.currentVisitorData.form_orientation_opened = formOpened;
                
                try {
                    const response = await fetch('api/checkin.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.currentVisitorData)
                    });
                    
                    const responseText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        this.showAlert('error', 'Server response error. Please try again.');
                        return;
                    }
                    
                    if (result.success) {
                        let successMessage = '';
                        
                        if (videoCompleted && formOpened) {
                            successMessage = 'Orientation complete! Training form opened. You are now checked in.';
                        } else if (videoCompleted && !formOpened) {
                            successMessage = 'Video orientation complete! You are now checked in.';
                        } else if (!videoCompleted && formOpened) {
                            successMessage = 'Training form opened! You are now checked in.';
                        }
                        
                        if (this.trainingExpired) {
                            successMessage += ' Your training certification has been renewed.';
                        }
                        
                        this.showAlert('success', successMessage);
                        this.resetForm();
                        await this.loadCurrentVisitors();
                    } else {
                        this.showAlert('error', result.error);
                    }
                } catch (error) {
                    this.showAlert('error', 'Network error. Please try again.');
                }
            }
            
            /**
             * Load and display current visitors
             */
            async loadCurrentVisitors() {
                try {
                    const response = await fetch('api/current.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.displayCurrentVisitors(result.visitors);
                        this.updateVisitorCount(result.count);
                    }
                } catch (error) {
                    console.error('Error loading visitors:', error);
                }
            }
            
            /**
             * Display current visitors in the sidebar
             * @param {Array} visitors - List of currently checked-in visitors
             */
            displayCurrentVisitors(visitors) {
                const container = document.getElementById('visitors-list');
                
                if (visitors.length === 0) {
                    container.innerHTML = '<div class="no-visitors">No visitors currently checked in</div>';
                    return;
                }
                
                container.innerHTML = visitors.map(visitor => `
                    <div class="visitor-card">
                        <div class="visitor-name">${this.escapeHtml(visitor.name)}</div>
                        <div class="visitor-company">${this.escapeHtml(visitor.company)}</div>
                        <div class="visitor-time">Checked in: ${this.formatDateTime(visitor.check_in_time)}</div>
                        <button class="checkout-btn" onclick="visitorSystem.checkOut(${visitor.id})">Check Out</button>
                    </div>
                `).join('');
            }
            
            /**
             * Update visitor count display
             * @param {number} count - Number of current visitors
             */
            updateVisitorCount(count) {
                document.getElementById('visitor-count').textContent = `${count} visitor${count !== 1 ? 's' : ''}`;
            }
            
            /**
             * Check out a visitor
             * @param {number} visitorId - ID of visitor to check out
             */
            async checkOut(visitorId) {
                try {
                    const response = await fetch('api/checkout.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ visitor_id: visitorId })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert('success', result.message);
                        this.loadCurrentVisitors();
                    } else {
                        this.showAlert('error', result.error);
                    }
                } catch (error) {
                    this.showAlert('error', 'Network error. Please try again.');
                }
            }
            
            /**
             * Display an alert message
             * @param {string} type - Alert type: 'success', 'error', or 'info'
             * @param {string} message - Message to display
             */
            showAlert(type, message) {
                const container = document.getElementById('alert-container');
                const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-info');
                container.innerHTML = `<div class="alert ${alertClass}">${this.escapeHtml(message)}</div>`;
                setTimeout(() => container.innerHTML = '', 5000);
            }
            
            /**
             * Clear all form fields without resetting state
             */
            clearFields() {
                document.getElementById('visitor-form').reset();
                
                // Clear auto-fill indicators and styling
                document.querySelectorAll('.auto-filled').forEach(field => {
                    field.classList.remove('auto-filled');
                });
                document.querySelectorAll('[id$="-indicator"]').forEach(indicator => {
                    indicator.innerHTML = '';
                });
                
                // Reset field styling and validation
                const emailInput = document.getElementById('email');
                const phoneInput = document.getElementById('phone');
                const visitorTypeSelect = document.getElementById('visitor_type');
                
                emailInput.style.borderColor = '#ddd';
                emailInput.setCustomValidity('');
                
                phoneInput.style.borderColor = '#ddd';
                phoneInput.setCustomValidity('');
                
                visitorTypeSelect.style.borderColor = '#ddd';
                visitorTypeSelect.style.background = 'white';
                visitorTypeSelect.classList.remove('auto-filled');
                
                // Clear any alerts
                document.getElementById('alert-container').innerHTML = '';
                
                // Focus on the first field
                document.getElementById('visitor_type').focus();
            }
            
            /**
             * Reset form and all state after successful check-in
             */
            resetForm() {
                document.getElementById('visitor-form').reset();
                this.currentVisitorData = null;
                this.currentVisitorType = null;
                this.trainingExpired = false;
                this.videoCompleted = false;
                
                // Clear auto-fill indicators
                document.querySelectorAll('.auto-filled').forEach(field => field.classList.remove('auto-filled'));
                document.querySelectorAll('[id$="-indicator"]').forEach(indicator => indicator.innerHTML = '');
                
                // Reset field styling
                const emailInput = document.getElementById('email');
                const phoneInput = document.getElementById('phone');
                const visitorTypeSelect = document.getElementById('visitor_type');
                
                emailInput.style.borderColor = '#ddd';
                emailInput.setCustomValidity('');
                
                phoneInput.style.borderColor = '#ddd';
                phoneInput.setCustomValidity('');
                
                visitorTypeSelect.style.borderColor = '#ddd';
                visitorTypeSelect.style.background = 'white';
                visitorTypeSelect.classList.remove('auto-filled');
            }
            
            /**
             * Format datetime for display
             * @param {string} dateTimeString - ISO datetime string
             * @returns {string} Formatted datetime string
             */
            formatDateTime(dateTimeString) {
                try {
                    const date = new Date(dateTimeString.replace(' ', 'T'));
                    
                    if (isNaN(date.getTime())) {
                        return 'Invalid date';
                    }
                    
                    return date.toLocaleString('en-US', {
                        timeZone: this.timezone,
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                } catch (error) {
                    console.error('DateTime formatting error:', error);
                    return 'Date error';
                }
            }
            
            /**
             * Validate email input
             * @param {Event} event - Input event
             */
            validateEmail(event) {
                const input = event.target;
                const value = input.value.trim();
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const isValid = emailRegex.test(value);
                
                this.updateEmailValidation(isValid, value.length > 0);
                
                if (!isValid && value.length > 0) {
                    input.setCustomValidity('Please enter a valid email address (example@company.com)');
                } else {
                    input.setCustomValidity('');
                }
            }
            
            /**
             * Update email validation indicator
             * @param {boolean} isValid - Whether email is valid
             * @param {boolean} hasContent - Whether field has content
             */
            updateEmailValidation(isValid, hasContent) {
                const indicator = document.getElementById('email-indicator');
                const emailInput = document.getElementById('email');
                
                if (isValid) {
                    indicator.innerHTML = '<span class="auto-fill-indicator" style="background:#28a745;">✓ Valid</span>';
                    emailInput.style.borderColor = '#28a745';
                } else if (hasContent) {
                    indicator.innerHTML = '<span class="auto-fill-indicator" style="background:#dc3545;">Invalid Format</span>';
                    emailInput.style.borderColor = '#dc3545';
                } else {
                    indicator.innerHTML = '';
                    emailInput.style.borderColor = '#ddd';
                }
            }
            
            /**
             * Format phone number as user types
             * @param {Event} event - Input event
             */
            formatPhoneNumber(event) {
                const input = event.target;
                const value = input.value.replace(/\D/g, '');
                const length = value.length;
                
                if (length === 0) {
                    input.value = '';
                } else if (length <= 3) {
                    input.value = `(${value}`;
                } else if (length <= 6) {
                    input.value = `(${value.slice(0, 3)}) ${value.slice(3)}`;
                } else {
                    input.value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6, 10)}`;
                }
                
                this.updatePhoneValidation(value.length === 10);
            }
            
            /**
             * Validate phone number on blur
             * @param {Event} event - Blur event
             */
            validatePhoneNumber(event) {
                const input = event.target;
                const value = input.value.replace(/\D/g, '');
                const isValid = value.length === 10;
                
                this.updatePhoneValidation(isValid);
                
                if (!isValid && value.length > 0) {
                    input.setCustomValidity('Please enter a valid 10-digit US phone number');
                } else {
                    input.setCustomValidity('');
                }
            }
            
            /**
             * Update phone validation indicator
             * @param {boolean} isValid - Whether phone is valid
             */
            updatePhoneValidation(isValid) {
                const indicator = document.getElementById('phone-indicator');
                const phoneInput = document.getElementById('phone');
                
                if (isValid) {
                    indicator.innerHTML = '<span class="auto-fill-indicator" style="background:#28a745;">✓ Valid</span>';
                    phoneInput.style.borderColor = '#28a745';
                } else if (phoneInput.value.length > 0) {
                    indicator.innerHTML = '<span class="auto-fill-indicator" style="background:#dc3545;">Invalid Format</span>';
                    phoneInput.style.borderColor = '#dc3545';
                } else {
                    indicator.innerHTML = '';
                    phoneInput.style.borderColor = '#ddd';
                }
            }
            
            /**
             * Escape HTML to prevent XSS
             * @param {string} text - Text to escape
             * @returns {string} Escaped text
             */
            escapeHtml(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.toString().replace(/[&<>"']/g, (m) => map[m]);
            }
        }
        
        // Initialize the system when page loads
        let visitorSystem;
        document.addEventListener('DOMContentLoaded', () => {
            visitorSystem = new VisitorSystem();
        });
    </script>
</body>
</html>