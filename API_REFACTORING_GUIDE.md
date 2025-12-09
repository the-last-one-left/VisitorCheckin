# API Files Refactoring Guide
# 
# This document contains find/replace patterns to complete the API refactoring.
# Apply these changes to each remaining API file.

## ========================================================================
## PATTERN 1: Update Package Headers
## ========================================================================

FIND:
 * @package    SuterraGuestCheckin
 * @subpackage API
 * @author     Pacific Office Automation

REPLACE WITH:
 * @package    VisitorManagement
 * @subpackage API
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>

## ========================================================================
## PATTERN 2: Update Timezone References in Comments
## ========================================================================

FIND:
All timestamps use Pacific Time (America/Los_Angeles timezone)

REPLACE WITH:
All timestamps use the configured system timezone

FIND:
Pacific Time (PST/PDT)

REPLACE WITH:
Configured system timezone

## ========================================================================
## PATTERN 3: Update Suterra Contact References
## ========================================================================

FIND:
suterra_contact

KEEP AS IS (database column name stays the same for compatibility)
But update COMMENTS that say "Suterra contact" or "Suterra employee"

FIND IN COMMENTS:
Suterra contact
Suterra employee

REPLACE WITH:
Staff contact
Staff member

## ========================================================================
## FILES COMPLETED
## ========================================================================

✅ api/database.php - Fully refactored
✅ api/checkin.php - Fully refactored
✅ api/checkout.php - Fully refactored
✅ api/current.php - Fully refactored

## ========================================================================
## FILES REMAINING
## ========================================================================

⏳ api/recent.php
⏳ api/stats.php
⏳ api/backup.php
⏳ api/export.php
⏳ api/clear-visits.php
⏳ api/db-stats.php
⏳ api/search-visitors.php
⏳ api/training-alerts.php
⏳ api/training-import.php
⏳ api/training-management.php

## ========================================================================
## QUICK CHECK LIST
## ========================================================================

For each file, verify:
1. [ ] Header updated (package, author)
2. [ ] No hardcoded "Pacific Office Automation"
3. [ ] No hardcoded "Suterra" (except in db column names)
4. [ ] No hardcoded timezone references in comments
5. [ ] No hardcoded color values

## ========================================================================
## NOTES
## ========================================================================

- Database column "suterra_contact" stays as-is (backwards compatibility)
- All timezone logic handled by database.php (uses config)
- No color values in API files (they're JSON endpoints)
- Focus is on removing company branding from comments/docs
