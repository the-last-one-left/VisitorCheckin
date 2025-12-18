# Implementation Plan for TO-DO Items

## Overview
This document outlines the implementation plan for completing the remaining TO-DO items in the Visitor Management System.

## Issues to Address

### 1. Contractor Video Workflow Bug ⚠️ CRITICAL
**Current Problem**: 
- When contractor orientation method is set to "video" in configuration, contractors are still redirected to external form instead of playing the video
- The system doesn't properly differentiate between video, form, and both options

**Root Cause**:
- `index.php` line 1066 hardcodes the external form URL to a production Smartsheet form
- No video playback functionality exists in the frontend
- Configuration uses `smartsheet_url` in `$VISITOR_TYPES` array, but code looks for non-existent `EXTERNAL_FORM_URL` constant
- No logic to handle "both" (video + form) scenario

**Solution**:
1. Add `EXTERNAL_FORM_URL` constant to config that reads from `$VISITOR_TYPES['contractor']['smartsheet_url']`
2. Create video player modal in `index.php` for playing orientation videos
3. Modify `openExternalForm()` method to check orientation method and route appropriately:
   - If `video_path` set → play video in modal
   - If `smartsheet_url` set → redirect to form
   - If both set → play video first, then offer form option
4. Add video file upload capability in setup wizard with copy to `/res` directory
5. Update `api/checkin.php` to track video completion separately from form completion

### 2. External Form Placeholder ⚠️ CRITICAL
**Current Problem**:
- External form URL is hardcoded to Suterra's production Smartsheet form: `https://app.smartsheet.com/b/form/dbe783baf6fb47a6b886b96d54e4c770`
- This should be a placeholder/empty unless configured by user

**Solution**:
1. Change default in `index.php` from hardcoded URL to empty string or proper config reference
2. Add validation in `api/checkin.php` to check if external_form_url is configured before setting `needs_orientation = true`
3. Show error message in UI if orientation is required but no form URL configured

### 3. Contractor-Only Mode 🆕 FEATURE
**Current Problem**:
- Only "Visitors Only" and "Visitors & Contractors" modes exist
- Need mode that hides visitor type selector but still tracks training like contractor mode

**Solution**:
1. Add `contractors_only` option to `visitor_mode` in setup wizard Step 4
2. Update `save-config.php` to handle contractors_only mode:
   - Set `ENABLE_TRAINING_MANAGEMENT = true`
   - Hide visitor type selector in `index.php`
   - Default all check-ins to `visitor_type = 'contractor'`
3. Modify `index.php`:
   - Add PHP check: if `VISITOR_MODE === 'contractors_only'`, hide selector and set hidden input to 'contractor'
4. Update admin dashboard to show training data in contractors_only mode

### 4. Linux Installer Testing 🧪 TESTING
**Current Problem**:
- `install.sh` script exists but hasn't been tested

**Solution**:
1. Review `install.sh` for any issues
2. Test on Ubuntu/Debian system
3. Document any fixes needed
4. Update installation documentation

## Implementation Priority

### Phase 1: Critical Fixes (Issues #1 & #2)
- [ ] Fix external form URL placeholder issue
- [ ] Implement video player functionality  
- [ ] Add video upload to setup wizard
- [ ] Update config generation to include EXTERNAL_FORM_URL

### Phase 2: New Features (Issue #3)
- [ ] Add contractors_only mode to setup wizard
- [ ] Implement frontend changes for contractors_only
- [ ] Update admin dashboard for contractors_only mode

### Phase 3: Testing (Issue #4)
- [ ] Review install.sh script
- [ ] Test on Linux system
- [ ] Document findings

## Files to Modify

### Phase 1:
- `/index.php` - Add video player, fix form URL, add video completion tracking
- `/config/config.example.php` - Add EXTERNAL_FORM_URL constant example
- `/setup/index.php` - Add video upload field
- `/setup/save-config.php` - Handle video file upload, generate EXTERNAL_FORM_URL
- `/api/checkin.php` - Add video completion tracking

### Phase 2:
- `/setup/index.php` - Add contractors_only radio option
- `/setup/save-config.php` - Handle contractors_only mode in config generation
- `/index.php` - Add logic to hide selector in contractors_only mode
- `/admin/index.php` - Ensure training data visible in contractors_only mode

### Phase 3:
- `/install.sh` - Review and test

## Testing Plan

### Video Workflow Testing:
1. Configure system with video orientation → verify video plays on check-in
2. Configure system with form orientation → verify form opens
3. Configure system with no orientation → verify direct check-in
4. Upload video file during setup → verify copied to /res correctly

### External Form Testing:
1. Setup without external form URL → verify no redirects
2. Setup with external form URL → verify redirects work
3. Verify placeholder text appears when no URL configured

### Contractors-Only Testing:
1. Configure contractors_only mode → verify selector hidden
2. Check-in new contractor → verify marked as contractor type
3. Verify training management works correctly
4. Verify admin dashboard shows training data

## Notes
- All changes must maintain backwards compatibility with existing deployments
- Configuration changes should preserve database structure
- Remember to update PHPDoc blocks for modified functions
- Test on both Windows/IIS and Linux/Apache environments
