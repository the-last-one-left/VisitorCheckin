/**
 * Setup Wizard JavaScript
 * 
 * Handles wizard navigation, form validation, live preview,
 * and configuration submission.
 * 
 * @package    VisitorManagement
 * @subpackage Setup
 * @author     Yeyland Wutani LLC
 * @version    1.0.0
 */

(function() {
    'use strict';

    // ========================================================================
    // STATE MANAGEMENT
    // ========================================================================

    let currentStep = 1;
    const totalSteps = 6;
    const formData = {};

    // ========================================================================
    // DOM ELEMENTS
    // ========================================================================

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const finishBtn = document.getElementById('finishBtn');
    const progressBar = document.getElementById('progressBar');

    // ========================================================================
    // INITIALIZATION
    // ========================================================================

    document.addEventListener('DOMContentLoaded', function() {
        setupEventListeners();
        updateWizardDisplay();
        initializeFormFields();
    });

    // ========================================================================
    // EVENT LISTENERS
    // ========================================================================

    function setupEventListeners() {
        // Navigation buttons
        prevBtn.addEventListener('click', () => navigateStep(-1));
        nextBtn.addEventListener('click', () => navigateStep(1));
        finishBtn.addEventListener('click', submitConfiguration);

        // Visitor mode radio buttons
        const visitorModeRadios = document.querySelectorAll('input[name="visitor_mode"]');
        visitorModeRadios.forEach(radio => {
            radio.addEventListener('change', handleVisitorModeChange);
        });

        // Orientation method radio buttons
        const orientationRadios = document.querySelectorAll('input[name="orientation_method"]');
        orientationRadios.forEach(radio => {
            radio.addEventListener('change', handleOrientationMethodChange);
        });

        // Color pickers
        setupColorPickers('color_primary');
        setupColorPickers('color_secondary');
        setupColorPickers('color_accent');

        // Logo upload
        const logoUpload = document.getElementById('logo_upload');
        if (logoUpload) {
            logoUpload.addEventListener('change', handleLogoUpload);
        }

        // Organization name for preview
        const orgNameInput = document.getElementById('org_name');
        if (orgNameInput) {
            orgNameInput.addEventListener('input', updateBrandingPreview);
        }

        // Password validation
        const passwordInput = document.getElementById('admin_password');
        const passwordConfirm = document.getElementById('admin_password_confirm');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', validatePasswordStrength);
        }
        
        if (passwordConfirm) {
            passwordConfirm.addEventListener('input', validatePasswordMatch);
        }
    }

    // ========================================================================
    // NAVIGATION
    // ========================================================================

    function navigateStep(direction) {
        // Validate current step before moving forward
        if (direction > 0 && !validateCurrentStep()) {
            return;
        }

        // Update step
        currentStep += direction;

        // Bounds checking
        if (currentStep < 1) currentStep = 1;
        if (currentStep > totalSteps) currentStep = totalSteps;

        // Update display
        updateWizardDisplay();

        // If we're on step 6, generate summary
        if (currentStep === 6) {
            generateConfigSummary();
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateWizardDisplay() {
        // Update progress bar
        const progress = (currentStep / totalSteps) * 100;
        progressBar.style.width = progress + '%';

        // Update step indicators
        document.querySelectorAll('.step').forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNum < currentStep) {
                step.classList.add('completed');
            } else if (stepNum === currentStep) {
                step.classList.add('active');
            }
        });

        // Update step visibility
        document.querySelectorAll('.wizard-step').forEach((step, index) => {
            step.classList.remove('active');
            if (index + 1 === currentStep) {
                step.classList.add('active');
            }
        });

        // Update button visibility
        prevBtn.style.display = currentStep === 1 ? 'none' : 'inline-block';
        nextBtn.style.display = currentStep === totalSteps ? 'none' : 'inline-block';
        finishBtn.style.display = currentStep === totalSteps ? 'inline-block' : 'none';
    }

    // ========================================================================
    // VALIDATION
    // ========================================================================

    function validateCurrentStep() {
        switch(currentStep) {
            case 1:
                return true; // Welcome screen, no validation needed
            
            case 2:
                return validateOrganizationDetails();
            
            case 3:
                return validateBranding();
            
            case 4:
                return validateFeatures();
            
            case 5:
                return validateAdminAccount();
            
            default:
                return true;
        }
    }

    function validateOrganizationDetails() {
        const orgName = document.getElementById('org_name').value.trim();
        const orgShortName = document.getElementById('org_short_name').value.trim();
        const timezone = document.getElementById('timezone').value;

        if (!orgName) {
            showAlert('Please enter your organization name', 'error');
            return false;
        }

        if (!orgShortName) {
            showAlert('Please enter a short name', 'error');
            return false;
        }

        if (!timezone) {
            showAlert('Please select a timezone', 'error');
            return false;
        }

        // Store in formData
        formData.org_name = orgName;
        formData.org_short_name = orgShortName;
        formData.org_tagline = document.getElementById('org_tagline').value.trim();
        formData.timezone = timezone;

        return true;
    }

    function validateBranding() {
        const colorPrimary = document.getElementById('color_primary_hex').value;
        const colorSecondary = document.getElementById('color_secondary_hex').value;
        const colorAccent = document.getElementById('color_accent_hex').value;

        // Validate hex colors
        const hexPattern = /^#[0-9A-Fa-f]{6}$/;
        
        if (!hexPattern.test(colorPrimary)) {
            showAlert('Primary color must be a valid hex color', 'error');
            return false;
        }
        
        if (!hexPattern.test(colorSecondary)) {
            showAlert('Secondary color must be a valid hex color', 'error');
            return false;
        }

        if (!hexPattern.test(colorAccent)) {
            showAlert('Accent color must be a valid hex color', 'error');
            return false;
        }

        // Store in formData
        formData.color_primary = colorPrimary;
        formData.color_secondary = colorSecondary;
        formData.color_accent = colorAccent;

        // Handle logo if uploaded
        const logoInput = document.getElementById('logo_upload');
        if (logoInput && logoInput.files.length > 0) {
            formData.logo_file = logoInput.files[0];
        }

        return true;
    }

    function validateFeatures() {
        const visitorMode = document.querySelector('input[name="visitor_mode"]:checked').value;
        formData.visitor_mode = visitorMode;

        if (visitorMode === 'visitors_contractors') {
            const orientationMethod = document.querySelector('input[name="orientation_method"]:checked').value;
            formData.orientation_method = orientationMethod;

            if (orientationMethod === 'external_form') {
                const externalFormUrl = document.getElementById('external_form_url').value.trim();
                if (externalFormUrl && !isValidUrl(externalFormUrl)) {
                    showAlert('Please enter a valid URL for the external form', 'error');
                    return false;
                }
                formData.external_form_url = externalFormUrl;
            }

            formData.training_renewal_months = document.getElementById('training_renewal_months').value;
        }

        formData.staff_contacts = document.getElementById('staff_contacts').value;
        formData.recent_visits_limit = document.getElementById('recent_visits_limit').value;
        formData.auto_purge_months = document.getElementById('auto_purge_months').value;

        return true;
    }

    function validateAdminAccount() {
        const username = document.getElementById('admin_username').value.trim();
        const password = document.getElementById('admin_password').value;
        const passwordConfirm = document.getElementById('admin_password_confirm').value;

        if (!username) {
            showAlert('Please enter an admin username', 'error');
            return false;
        }

        if (username.length < 3) {
            showAlert('Username must be at least 3 characters', 'error');
            return false;
        }

        if (!password) {
            showAlert('Please enter a password', 'error');
            return false;
        }

        // Password strength check
        const passwordStrength = checkPasswordStrength(password);
        if (passwordStrength.score < 3) {
            showAlert('Password is too weak. Please use a stronger password.', 'error');
            return false;
        }

        if (password !== passwordConfirm) {
            showAlert('Passwords do not match', 'error');
            return false;
        }

        formData.admin_username = username;
        formData.admin_password = password;

        return true;
    }

    // ========================================================================
    // PASSWORD VALIDATION
    // ========================================================================

    function validatePasswordStrength() {
        const password = document.getElementById('admin_password').value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        if (!password) {
            strengthBar.className = 'strength-bar';
            strengthText.textContent = 'Enter password';
            return;
        }

        const strength = checkPasswordStrength(password);
        
        strengthBar.className = 'strength-bar ' + strength.label.toLowerCase();
        strengthText.textContent = strength.label + ' - ' + strength.feedback;
        strengthText.style.color = strength.color;
    }

    function checkPasswordStrength(password) {
        let score = 0;
        const feedback = [];

        // Length check
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;

        // Complexity checks
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        // Generate feedback
        if (password.length < 8) feedback.push('Use at least 8 characters');
        if (!/[a-z]/.test(password)) feedback.push('Add lowercase letters');
        if (!/[A-Z]/.test(password)) feedback.push('Add uppercase letters');
        if (!/[0-9]/.test(password)) feedback.push('Add numbers');
        if (!/[^a-zA-Z0-9]/.test(password)) feedback.push('Add special characters');

        let label, color;
        if (score < 3) {
            label = 'Weak';
            color = '#dc3545';
        } else if (score < 5) {
            label = 'Medium';
            color = '#ffc107';
        } else {
            label = 'Strong';
            color = '#28a745';
        }

        return {
            score: score,
            label: label,
            color: color,
            feedback: feedback.length > 0 ? feedback.join(', ') : 'Good password!'
        };
    }

    function validatePasswordMatch() {
        const password = document.getElementById('admin_password').value;
        const passwordConfirm = document.getElementById('admin_password_confirm').value;
        const matchMessage = document.getElementById('passwordMatchMessage');

        if (!passwordConfirm) {
            matchMessage.textContent = '';
            matchMessage.className = 'validation-message';
            return;
        }

        if (password === passwordConfirm) {
            matchMessage.textContent = 'Passwords match';
            matchMessage.className = 'validation-message success';
        } else {
            matchMessage.textContent = 'Passwords do not match';
            matchMessage.className = 'validation-message error';
        }
    }

    // ========================================================================
    // FEATURE TOGGLES
    // ========================================================================

    function handleVisitorModeChange(event) {
        const contractorOptions = document.getElementById('contractor_options');
        
        if (event.target.value === 'visitors_contractors') {
            contractorOptions.style.display = 'block';
        } else {
            contractorOptions.style.display = 'none';
        }
    }

    function handleOrientationMethodChange(event) {
        const externalFormGroup = document.getElementById('external_form_url_group');
        
        if (event.target.value === 'external_form') {
            externalFormGroup.style.display = 'block';
        } else {
            externalFormGroup.style.display = 'none';
        }
    }

    // ========================================================================
    // COLOR PICKERS
    // ========================================================================

    function setupColorPickers(baseId) {
        const colorPicker = document.getElementById(baseId);
        const hexInput = document.getElementById(baseId + '_hex');

        if (!colorPicker || !hexInput) return;

        // Sync color picker to hex input
        colorPicker.addEventListener('input', function() {
            hexInput.value = this.value.toUpperCase();
            updateBrandingPreview();
        });

        // Sync hex input to color picker
        hexInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                colorPicker.value = value;
                updateBrandingPreview();
            }
        });
    }

    // ========================================================================
    // LOGO UPLOAD
    // ========================================================================

    function handleLogoUpload(event) {
        const file = event.target.files[0];
        
        if (!file) return;

        // Validate file type
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
        if (!validTypes.includes(file.type)) {
            showAlert('Please upload a PNG, JPG, or SVG file', 'error');
            event.target.value = '';
            return;
        }

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            showAlert('Logo file must be less than 2MB', 'error');
            event.target.value = '';
            return;
        }

        // Preview the logo
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewContainer = document.getElementById('logo_preview_container');
            const previewImg = document.getElementById('logo_preview');
            const previewLogoInBox = document.getElementById('preview_logo');
            
            previewImg.src = e.target.result;
            previewLogoInBox.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    // ========================================================================
    // BRANDING PREVIEW
    // ========================================================================

    function updateBrandingPreview() {
        const colorPrimary = document.getElementById('color_primary').value;
        const colorSecondary = document.getElementById('color_secondary').value;
        const orgName = document.getElementById('org_name').value || 'Your Organization';

        // Update preview
        const previewContent = document.getElementById('brandingPreview');
        if (previewContent) {
            previewContent.style.setProperty('--preview-primary', colorPrimary);
            previewContent.style.setProperty('--preview-secondary', colorSecondary);
        }

        const previewOrgName = document.getElementById('preview_org_name');
        if (previewOrgName) {
            previewOrgName.textContent = orgName;
        }
    }

    // ========================================================================
    // CONFIGURATION SUMMARY
    // ========================================================================

    function generateConfigSummary() {
        const summaryDiv = document.getElementById('config_summary');
        
        let html = '';

        // Organization
        html += '<div class="summary-section">';
        html += '<h4>Organization Details</h4>';
        html += '<div class="summary-item"><strong>Name:</strong> <span>' + escapeHtml(formData.org_name) + '</span></div>';
        html += '<div class="summary-item"><strong>Short Name:</strong> <span>' + escapeHtml(formData.org_short_name) + '</span></div>';
        if (formData.org_tagline) {
            html += '<div class="summary-item"><strong>Tagline:</strong> <span>' + escapeHtml(formData.org_tagline) + '</span></div>';
        }
        html += '<div class="summary-item"><strong>Timezone:</strong> <span>' + escapeHtml(formData.timezone) + '</span></div>';
        html += '</div>';

        // Branding
        html += '<div class="summary-section">';
        html += '<h4>Branding</h4>';
        html += '<div class="summary-item"><strong>Primary Color:</strong> <span style="color: ' + formData.color_primary + '">&#9632;</span> ' + formData.color_primary + '</div>';
        html += '<div class="summary-item"><strong>Secondary Color:</strong> <span style="color: ' + formData.color_secondary + '">&#9632;</span> ' + formData.color_secondary + '</div>';
        html += '<div class="summary-item"><strong>Accent Color:</strong> <span style="color: ' + formData.color_accent + '">&#9632;</span> ' + formData.color_accent + '</div>';
        if (formData.logo_file) {
            html += '<div class="summary-item"><strong>Logo:</strong> <span>' + escapeHtml(formData.logo_file.name) + '</span></div>';
        }
        html += '</div>';

        // Features
        html += '<div class="summary-section">';
        html += '<h4>Features</h4>';
        html += '<div class="summary-item"><strong>Mode:</strong> <span>' + (formData.visitor_mode === 'visitors_only' ? 'Visitors Only' : 'Visitors & Contractors') + '</span></div>';
        
        if (formData.visitor_mode === 'visitors_contractors') {
            let orientationLabel = 'None';
            if (formData.orientation_method === 'video') orientationLabel = 'Video';
            if (formData.orientation_method === 'external_form') orientationLabel = 'External Form';
            
            html += '<div class="summary-item"><strong>Orientation:</strong> <span>' + orientationLabel + '</span></div>';
            
            if (formData.orientation_method === 'external_form' && formData.external_form_url) {
                html += '<div class="summary-item"><strong>Form URL:</strong> <span style="font-size: 0.85em; word-break: break-all;">' + escapeHtml(formData.external_form_url) + '</span></div>';
            }
            
            html += '<div class="summary-item"><strong>Training Renewal:</strong> <span>' + formData.training_renewal_months + ' months</span></div>';
        }
        
        html += '<div class="summary-item"><strong>Recent Visits:</strong> <span>' + formData.recent_visits_limit + ' entries</span></div>';
        html += '<div class="summary-item"><strong>Data Retention:</strong> <span>' + (formData.auto_purge_months == 0 ? 'Never purge' : formData.auto_purge_months + ' months') + '</span></div>';
        
        if (formData.staff_contacts && formData.staff_contacts.trim()) {
            const contacts = formData.staff_contacts.trim().split('\n').filter(c => c.trim());
            if (contacts.length > 0) {
                html += '<div class="summary-item"><strong>Staff Contacts:</strong></div>';
                html += '<ul class="summary-list">';
                contacts.slice(0, 5).forEach(contact => {
                    html += '<li>' + escapeHtml(contact.trim()) + '</li>';
                });
                if (contacts.length > 5) {
                    html += '<li>... and ' + (contacts.length - 5) + ' more</li>';
                }
                html += '</ul>';
            }
        }
        html += '</div>';

        // Admin
        html += '<div class="summary-section">';
        html += '<h4>Admin Account</h4>';
        html += '<div class="summary-item"><strong>Username:</strong> <span>' + escapeHtml(formData.admin_username) + '</span></div>';
        html += '<div class="summary-item"><strong>Password:</strong> <span>●●●●●●●●</span></div>';
        html += '</div>';

        summaryDiv.innerHTML = html;
    }

    // ========================================================================
    // CONFIGURATION SUBMISSION
    // ========================================================================

    function submitConfiguration() {
        const setupStatus = document.getElementById('setup_status');
        const setupComplete = document.getElementById('setup_complete');
        const finishBtnElement = document.getElementById('finishBtn');

        // Disable button
        finishBtnElement.disabled = true;
        finishBtnElement.textContent = 'Setting up...';

        // Show status
        setupStatus.style.display = 'block';
        setupStatus.className = 'alert alert-info';
        setupStatus.innerHTML = '<strong>Setting up your system...</strong><br>Please wait while we configure everything.';

        // Prepare form data
        const submitData = new FormData();
        
        // Add all configuration values
        for (const key in formData) {
            if (formData.hasOwnProperty(key) && key !== 'logo_file') {
                submitData.append(key, formData[key]);
            }
        }

        // Add logo file if present
        if (formData.logo_file) {
            submitData.append('logo_file', formData.logo_file);
        }

        // Submit configuration
        fetch('save-config.php', {
            method: 'POST',
            body: submitData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                setupStatus.style.display = 'none';
                setupComplete.style.display = 'block';
                
                // Hide finish button
                finishBtnElement.style.display = 'none';
            } else {
                throw new Error(data.message || 'Configuration failed');
            }
        })
        .catch(error => {
            setupStatus.className = 'alert alert-error';
            setupStatus.innerHTML = '<strong>Setup Failed</strong><br>' + escapeHtml(error.message);
            
            // Re-enable button
            finishBtnElement.disabled = false;
            finishBtnElement.textContent = 'Complete Setup';
        });
    }

    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================

    function initializeFormFields() {
        // Set default values if needed
        updateBrandingPreview();
    }

    function showAlert(message, type) {
        // Simple alert for now - can be enhanced with custom modal
        alert(message);
    }

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
