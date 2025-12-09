<?php
/**
 * Setup Wizard - Main Interface
 * 
 * First-run configuration wizard for Visitor Check-In System.
 * Automatically loads when no config.php exists.
 * 
 * @package    VisitorManagement
 * @subpackage Setup
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    1.0.0
 * @since      2024-12-08
 */

// Check if already configured
if (file_exists(__DIR__ . '/../config/config.php')) {
    header('Location: ../');
    exit;
}

// Check for basic requirements
$php_version_ok = version_compare(PHP_VERSION, '7.4.0', '>=');
$pdo_sqlite = extension_loaded('pdo_sqlite');
$sqlite3 = extension_loaded('sqlite3');
$mbstring = extension_loaded('mbstring');

$all_requirements_met = $php_version_ok && $pdo_sqlite && $sqlite3 && $mbstring;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - Visitor Check-In System</title>
    <link rel="stylesheet" href="wizard.css">
</head>
<body>
    <div class="wizard-container">
        <!-- Header -->
        <div class="wizard-header">
            <h1>Visitor Check-In System</h1>
            <p class="subtitle">Configuration Wizard</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar-container">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        <div class="progress-steps">
            <div class="step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-label">Welcome</span>
            </div>
            <div class="step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-label">Organization</span>
            </div>
            <div class="step" data-step="3">
                <span class="step-number">3</span>
                <span class="step-label">Branding</span>
            </div>
            <div class="step" data-step="4">
                <span class="step-number">4</span>
                <span class="step-label">Features</span>
            </div>
            <div class="step" data-step="5">
                <span class="step-number">5</span>
                <span class="step-label">Admin</span>
            </div>
            <div class="step" data-step="6">
                <span class="step-number">6</span>
                <span class="step-label">Complete</span>
            </div>
        </div>

        <!-- Wizard Content -->
        <div class="wizard-content">
            
            <!-- Step 1: Welcome -->
            <div class="wizard-step active" data-step="1">
                <h2>Welcome to Visitor Check-In System</h2>
                <p class="step-intro">Let's get your visitor management system configured and ready to use.</p>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3>Flexible Visitor Types</h3>
                        <p>Configure for visitors only or include contractor training management</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎨</div>
                        <h3>Custom Branding</h3>
                        <p>Use your organization's colors and logo throughout the system</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📋</div>
                        <h3>Training Management</h3>
                        <p>Track contractor certifications with automatic expiration alerts</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Real-time Dashboard</h3>
                        <p>See who's on-site, recent visits, and training status at a glance</p>
                    </div>
                </div>

                <div class="requirements-check">
                    <h3>System Requirements</h3>
                    <div class="requirement <?php echo $php_version_ok ? 'met' : 'unmet'; ?>">
                        <span class="check-icon"><?php echo $php_version_ok ? '✓' : '✗'; ?></span>
                        PHP 7.4 or higher (Current: <?php echo PHP_VERSION; ?>)
                    </div>
                    <div class="requirement <?php echo $pdo_sqlite ? 'met' : 'unmet'; ?>">
                        <span class="check-icon"><?php echo $pdo_sqlite ? '✓' : '✗'; ?></span>
                        PDO SQLite Extension
                    </div>
                    <div class="requirement <?php echo $sqlite3 ? 'met' : 'unmet'; ?>">
                        <span class="check-icon"><?php echo $sqlite3 ? '✓' : '✗'; ?></span>
                        SQLite3 Extension
                    </div>
                    <div class="requirement <?php echo $mbstring ? 'met' : 'unmet'; ?>">
                        <span class="check-icon"><?php echo $mbstring ? '✓' : '✗'; ?></span>
                        Mbstring Extension
                    </div>
                </div>

                <?php if (!$all_requirements_met): ?>
                <div class="alert alert-error">
                    <strong>Requirements Not Met</strong><br>
                    Please install the required PHP extensions before continuing.
                </div>
                <?php endif; ?>
            </div>

            <!-- Step 2: Organization Details -->
            <div class="wizard-step" data-step="2">
                <h2>Organization Details</h2>
                <p class="step-intro">Tell us about your organization</p>

                <div class="form-group">
                    <label for="org_name">Organization Name *</label>
                    <input type="text" id="org_name" name="org_name" required 
                           placeholder="Your Company Name">
                    <small>Full organization name displayed in the system</small>
                </div>

                <div class="form-group">
                    <label for="org_short_name">Short Name *</label>
                    <input type="text" id="org_short_name" name="org_short_name" required 
                           placeholder="CompanyName">
                    <small>Abbreviated name for compact displays</small>
                </div>

                <div class="form-group">
                    <label for="org_tagline">Welcome Tagline</label>
                    <input type="text" id="org_tagline" name="org_tagline" 
                           placeholder="Welcome to our facility">
                    <small>Optional greeting message on check-in screen</small>
                </div>

                <div class="form-group">
                    <label for="timezone">Timezone *</label>
                    <select id="timezone" name="timezone" required>
                        <option value="">-- Select Timezone --</option>
                        <optgroup label="US Timezones">
                            <option value="America/New_York">Eastern (ET)</option>
                            <option value="America/Chicago">Central (CT)</option>
                            <option value="America/Denver">Mountain (MT)</option>
                            <option value="America/Phoenix">Arizona (no DST)</option>
                            <option value="America/Los_Angeles" selected>Pacific (PT)</option>
                            <option value="America/Anchorage">Alaska (AKT)</option>
                            <option value="Pacific/Honolulu">Hawaii (HST)</option>
                        </optgroup>
                        <optgroup label="Other Timezones">
                            <option value="UTC">UTC</option>
                            <option value="Europe/London">London</option>
                            <option value="Europe/Paris">Paris</option>
                            <option value="Asia/Tokyo">Tokyo</option>
                            <option value="Australia/Sydney">Sydney</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- Step 3: Branding -->
            <div class="wizard-step" data-step="3">
                <h2>Branding & Colors</h2>
                <p class="step-intro">Customize the look and feel</p>

                <div class="form-group">
                    <label for="logo_upload">Organization Logo</label>
                    <input type="file" id="logo_upload" name="logo_upload" 
                           accept="image/png,image/jpeg,image/jpg,image/svg+xml">
                    <small>Recommended: SVG or PNG with transparent background (max 2MB)</small>
                    
                    <div id="logo_preview_container" style="display: none; margin-top: 10px;">
                        <img id="logo_preview" src="" alt="Logo Preview" style="max-width: 200px; max-height: 100px;">
                    </div>
                </div>

                <div class="color-grid">
                    <div class="form-group">
                        <label for="color_primary">Primary Color *</label>
                        <div class="color-input-group">
                            <input type="color" id="color_primary" name="color_primary" value="#0066CC">
                            <input type="text" id="color_primary_hex" value="#0066CC" 
                                   pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                        </div>
                        <small>Main brand color for buttons and headers</small>
                    </div>

                    <div class="form-group">
                        <label for="color_secondary">Secondary Color *</label>
                        <div class="color-input-group">
                            <input type="color" id="color_secondary" name="color_secondary" value="#0052A3">
                            <input type="text" id="color_secondary_hex" value="#0052A3" 
                                   pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                        </div>
                        <small>Accent color for interactive elements</small>
                    </div>

                    <div class="form-group">
                        <label for="color_accent">Accent Color</label>
                        <div class="color-input-group">
                            <input type="color" id="color_accent" name="color_accent" value="#FF6600">
                            <input type="text" id="color_accent_hex" value="#FF6600" 
                                   pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                        </div>
                        <small>Highlight color for alerts and notifications</small>
                    </div>
                </div>

                <div class="preview-box">
                    <h4>Live Preview</h4>
                    <div class="preview-content" id="brandingPreview">
                        <div class="preview-header">
                            <img id="preview_logo" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='40'%3E%3Ctext x='10' y='25' font-size='20' fill='%23333'%3EYour Logo%3C/text%3E%3C/svg%3E" alt="Logo">
                            <h3 id="preview_org_name">Your Organization</h3>
                        </div>
                        <button class="preview-button">Check In</button>
                        <div class="preview-card">
                            <p>Sample content with your branding colors</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Features & Configuration -->
            <div class="wizard-step" data-step="4">
                <h2>Features & Configuration</h2>
                <p class="step-intro">Configure system features for your needs</p>

                <div class="form-group">
                    <label>Visitor Management Mode *</label>
                    <div class="radio-group">
                        <label class="radio-card">
                            <input type="radio" name="visitor_mode" value="visitors_only" checked>
                            <div class="radio-content">
                                <strong>Visitors Only</strong>
                                <p>Simple check-in for general visitors. Perfect for offices and retail.</p>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="visitor_mode" value="visitors_contractors">
                            <div class="radio-content">
                                <strong>Visitors & Contractors</strong>
                                <p>Include contractor training management. Ideal for manufacturing and construction.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="contractor_options" style="display: none;">
                    <div class="form-group">
                        <label>Contractor Orientation Method</label>
                        <div class="radio-group">
                            <label class="radio-card">
                                <input type="radio" name="orientation_method" value="none" checked>
                                <div class="radio-content">
                                    <strong>None</strong>
                                    <p>No orientation required for check-in</p>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="orientation_method" value="video">
                                <div class="radio-content">
                                    <strong>Video</strong>
                                    <p>Watch orientation video on tablet (upload later)</p>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="orientation_method" value="external_form">
                                <div class="radio-content">
                                    <strong>External Form</strong>
                                    <p>Redirect to external form (e.g., Smartsheet)</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="external_form_url_group" class="form-group" style="display: none;">
                        <label for="external_form_url">External Form URL</label>
                        <input type="url" id="external_form_url" name="external_form_url" 
                               placeholder="https://app.smartsheet.com/...">
                        <small>Full URL to redirect contractors for training/orientation</small>
                    </div>

                    <div class="form-group">
                        <label for="training_renewal_months">Training Renewal Period</label>
                        <select id="training_renewal_months" name="training_renewal_months">
                            <option value="12" selected>12 months (Annual)</option>
                            <option value="6">6 months (Semi-annual)</option>
                            <option value="24">24 months (Biennial)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="staff_contacts">Staff Contacts (one per line)</label>
                    <textarea id="staff_contacts" name="staff_contacts" rows="6" 
                              placeholder="John Smith&#10;Jane Doe&#10;Department - Operations"></textarea>
                    <small>People that visitors can check in to see (optional)</small>
                </div>

                <div class="form-group">
                    <label for="recent_visits_limit">Recent Visits Display Limit</label>
                    <select id="recent_visits_limit" name="recent_visits_limit">
                        <option value="10">10 visits</option>
                        <option value="20" selected>20 visits</option>
                        <option value="30">30 visits</option>
                        <option value="50">50 visits</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="auto_purge_months">Data Retention Period</label>
                    <select id="auto_purge_months" name="auto_purge_months">
                        <option value="0">Never purge</option>
                        <option value="12">12 months</option>
                        <option value="18">18 months</option>
                        <option value="24" selected>24 months</option>
                        <option value="36">36 months</option>
                    </select>
                    <small>Automatically remove old visit records</small>
                </div>
            </div>

            <!-- Step 5: Admin Account -->
            <div class="wizard-step" data-step="5">
                <h2>Admin Account</h2>
                <p class="step-intro">Create your administrator credentials</p>

                <div class="form-group">
                    <label for="admin_username">Admin Username *</label>
                    <input type="text" id="admin_username" name="admin_username" 
                           value="admin" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="admin_password">Admin Password *</label>
                    <input type="password" id="admin_password" name="admin_password" 
                           required autocomplete="new-password">
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                        <span class="strength-text" id="strengthText">Enter password</span>
                    </div>
                    <small>Minimum 8 characters, must include uppercase, lowercase, number, and special character</small>
                </div>

                <div class="form-group">
                    <label for="admin_password_confirm">Confirm Password *</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" 
                           required autocomplete="new-password">
                    <span class="validation-message" id="passwordMatchMessage"></span>
                </div>
            </div>

            <!-- Step 6: Complete -->
            <div class="wizard-step" data-step="6">
                <h2>Configuration Complete!</h2>
                <p class="step-intro">Review your settings and finalize setup</p>

                <div id="config_summary" class="summary-box">
                    <!-- Populated by JavaScript -->
                </div>

                <div id="setup_status" class="alert" style="display: none;">
                    <!-- Status messages during setup -->
                </div>

                <div id="setup_complete" style="display: none;">
                    <div class="success-message">
                        <div class="success-icon">✓</div>
                        <h3>Setup Complete!</h3>
                        <p>Your Visitor Check-In System is now configured and ready to use.</p>
                        <a href="../" class="btn btn-primary btn-large">Go to System</a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Navigation -->
        <div class="wizard-navigation">
            <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">
                ← Previous
            </button>
            <button type="button" class="btn btn-primary" id="nextBtn" <?php echo !$all_requirements_met ? 'disabled' : ''; ?>>
                Next →
            </button>
            <button type="button" class="btn btn-primary" id="finishBtn" style="display: none;">
                Complete Setup
            </button>
        </div>

        <!-- Footer -->
        <div class="wizard-footer">
            <p>Visitor Check-In System by <strong>Yeyland Wutani LLC</strong></p>
            <p>Contact: <a href="mailto:yeyland.wutani@tcpip.network">yeyland.wutani@tcpip.network</a></p>
        </div>
    </div>

    <script src="wizard.js"></script>
</body>
</html>
