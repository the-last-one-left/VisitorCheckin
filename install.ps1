<#
.SYNOPSIS
    Automated installer for Visitor Check-In System on Windows Server with IIS

.DESCRIPTION
    This PowerShell script automates the deployment of the Visitor Check-In System
    on Windows Server environments. It handles prerequisite checking, IIS configuration,
    directory creation, permission setting, and launches the setup wizard.

.PARAMETER InstallPath
    Target installation directory. Default: C:\inetpub\wwwroot\VisitorCheckin

.PARAMETER SiteName
    IIS site name. Default: VisitorCheckin

.PARAMETER Port
    HTTP port for the IIS site. Default: 8080

.PARAMETER SkipIIS
    Skip automatic IIS site creation. Default: false

.PARAMETER OpenBrowser
    Automatically open browser to setup wizard after installation. Default: true

.EXAMPLE
    .\install.ps1
    Basic installation with defaults

.EXAMPLE
    .\install.ps1 -InstallPath "D:\Web\VisitorCheckin" -Port 9000
    Custom installation path and port

.EXAMPLE
    .\install.ps1 -SkipIIS
    Install files only, skip IIS configuration

.NOTES
    Author: Yeyland Wutani LLC
    Email: yeyland.wutani@tcpip.network
    Version: 1.0.3
    Requires: PowerShell 5.1+, Administrator privileges
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory=$false)]
    [string]$InstallPath = "C:\inetpub\wwwroot\VisitorCheckin",
    
    [Parameter(Mandatory=$false)]
    [string]$SiteName = "VisitorCheckin",
    
    [Parameter(Mandatory=$false)]
    [int]$Port = 8080,
    
    [Parameter(Mandatory=$false)]
    [switch]$SkipIIS,
    
    [Parameter(Mandatory=$false)]
    [bool]$OpenBrowser = $true
)

# ============================================================================
# CONFIGURATION
# ============================================================================

$ErrorActionPreference = "Stop"
$ScriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path

# Color scheme for output
$ColorSuccess = "Green"
$ColorWarning = "Yellow"
$ColorError = "Red"
$ColorInfo = "Cyan"

# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

function Write-Step {
    param([string]$Message)
    Write-Host "`n[$(Get-Date -Format 'HH:mm:ss')] " -NoNewline -ForegroundColor Gray
    Write-Host $Message -ForegroundColor $ColorInfo
}

function Write-SuccessMessage {
    param([string]$Message)
    Write-Host "  [OK] " -NoNewline -ForegroundColor $ColorSuccess
    Write-Host $Message
}

function Write-WarningMessage {
    param([string]$Message)
    Write-Host "  [WARN] " -NoNewline -ForegroundColor $ColorWarning
    Write-Host $Message -ForegroundColor $ColorWarning
}

function Write-ErrorMessage {
    param([string]$Message)
    Write-Host "  [ERROR] " -NoNewline -ForegroundColor $ColorError
    Write-Host $Message -ForegroundColor $ColorError
}

function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-IISInstalled {
    # Try Windows Server method first
    try {
        $iisFeature = Get-WindowsFeature -Name Web-Server -ErrorAction Stop
        return ($null -ne $iisFeature -and $iisFeature.Installed)
    } catch {
        # Fall back to Windows desktop method
        try {
            $iisFeature = Get-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole -ErrorAction Stop
            return ($null -ne $iisFeature -and $iisFeature.State -eq 'Enabled')
        } catch {
            # If both fail, check if IIS service exists
            $iisService = Get-Service -Name W3SVC -ErrorAction SilentlyContinue
            return ($null -ne $iisService)
        }
    }
}

function Get-PHPVersion {
    try {
        $phpVersion = php -v 2>$null | Select-String -Pattern "PHP (\d+\.\d+\.\d+)" | ForEach-Object { $_.Matches.Groups[1].Value }
        return $phpVersion
    } catch {
        return $null
    }
}

function Test-PHPExtension {
    param([string]$Extension)
    $extensions = php -m 2>$null
    return ($extensions -match $Extension)
}

# ============================================================================
# BANNER
# ============================================================================

Clear-Host
Write-Host "================================================================" -ForegroundColor $ColorInfo
Write-Host "                                                                " -ForegroundColor $ColorInfo
Write-Host "      Visitor Check-In System - Installation Wizard            " -ForegroundColor $ColorInfo
Write-Host "                                                                " -ForegroundColor $ColorInfo
Write-Host "                 Yeyland Wutani LLC                             " -ForegroundColor $ColorInfo
Write-Host "           yeyland.wutani@tcpip.network                         " -ForegroundColor $ColorInfo
Write-Host "                                                                " -ForegroundColor $ColorInfo
Write-Host "================================================================" -ForegroundColor $ColorInfo
Write-Host ""

# ============================================================================
# PREREQUISITE CHECKS
# ============================================================================

Write-Step "Checking prerequisites..."

# Check for administrator privileges
if (-not (Test-Administrator)) {
    Write-ErrorMessage "This script requires administrator privileges"
    Write-Host "`nPlease run PowerShell as Administrator and try again." -ForegroundColor $ColorWarning
    exit 1
}
Write-SuccessMessage "Administrator privileges confirmed"

# Check PowerShell version
$psVersion = $PSVersionTable.PSVersion
if ($psVersion.Major -lt 5) {
    Write-ErrorMessage "PowerShell 5.1 or higher required (Current: $psVersion)"
    exit 1
}
Write-SuccessMessage "PowerShell version: $psVersion"

# Check for IIS
if (-not $SkipIIS) {
    if (-not (Test-IISInstalled)) {
        Write-WarningMessage "IIS is not installed"
        Write-Host "`nWould you like to install IIS? (Y/N): " -NoNewline
        $response = Read-Host
        if ($response -eq 'Y' -or $response -eq 'y') {
            Write-Step "Installing IIS..."
            try {
                # Try Windows Server method first
                Install-WindowsFeature -Name Web-Server -IncludeManagementTools -ErrorAction Stop
                Write-SuccessMessage "IIS installed successfully"
            } catch {
                # Fall back to Windows desktop method
                try {
                    Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole,IIS-WebServer,IIS-CommonHttpFeatures,IIS-ManagementConsole,IIS-HttpErrors,IIS-HttpRedirect,IIS-WindowsAuthentication,IIS-StaticContent,IIS-DefaultDocument,IIS-HttpCompressionStatic,IIS-DirectoryBrowsing -All -NoRestart -ErrorAction Stop
                    Write-SuccessMessage "IIS installed successfully"
                    Write-WarningMessage "A system restart may be required"
                } catch {
                    Write-ErrorMessage "Failed to install IIS: $_"
                    Write-Host "Please install IIS manually from Windows Features" -ForegroundColor $ColorWarning
                }
            }
        } else {
            Write-WarningMessage "Continuing without IIS installation (use -SkipIIS to suppress this check)"
        }
    } else {
        Write-SuccessMessage "IIS is installed"
    }
    
    # Check for CGI/FastCGI module (required for PHP)
    try {
        $cgiFeature = Get-WindowsOptionalFeature -Online -FeatureName IIS-CGI -ErrorAction Stop
        if ($cgiFeature.State -eq "Enabled") {
            Write-SuccessMessage "CGI/FastCGI module is installed"
        } else {
            Write-WarningMessage "CGI/FastCGI module is not installed (required for PHP)"
            Write-Host "`nPHP requires the CGI/FastCGI module. Install it now? (Y/N): " -NoNewline
            $response = Read-Host
            if ($response -eq 'Y' -or $response -eq 'y') {
                Write-Step "Installing CGI/FastCGI module..."
                try {
                    Enable-WindowsOptionalFeature -Online -FeatureName IIS-CGI -All -NoRestart -ErrorAction Stop
                    Write-SuccessMessage "CGI/FastCGI module installed successfully"
                    
                    # Restart IIS to load the new module
                    Write-Step "Restarting IIS to load module..."
                    iisreset /restart | Out-Null
                    Start-Sleep -Seconds 3
                    Write-SuccessMessage "IIS restarted"
                } catch {
                    Write-ErrorMessage "Failed to install CGI/FastCGI module: $_"
                    Write-Host "Please install manually:" -ForegroundColor $ColorWarning
                    Write-Host "  1. Open 'Turn Windows features on or off'" -ForegroundColor $ColorWarning
                    Write-Host "  2. Expand 'Internet Information Services > World Wide Web Services'" -ForegroundColor $ColorWarning
                    Write-Host "  3. Expand 'Application Development Features'" -ForegroundColor $ColorWarning
                    Write-Host "  4. Check 'CGI' and click OK" -ForegroundColor $ColorWarning
                    exit 1
                }
            } else {
                Write-ErrorMessage "CGI/FastCGI module is required for PHP to work with IIS"
                Write-Host "Installation cannot continue without this module." -ForegroundColor $ColorWarning
                exit 1
            }
        }
    } catch {
        Write-WarningMessage "Could not check CGI/FastCGI module status (may need to be checked manually)"
    }
}

# Check for PHP
$phpVersion = Get-PHPVersion
if ($null -eq $phpVersion) {
    Write-ErrorMessage "PHP is not installed or not in PATH"
    Write-Host "`nPlease install PHP 7.4 or higher with the following extensions:" -ForegroundColor $ColorWarning
    Write-Host "  - pdo_sqlite" -ForegroundColor $ColorWarning
    Write-Host "  - sqlite3" -ForegroundColor $ColorWarning
    Write-Host "  - mbstring" -ForegroundColor $ColorWarning
    Write-Host "`nDownload from: https://windows.php.net/download/" -ForegroundColor $ColorInfo
    exit 1
}
Write-SuccessMessage "PHP version: $phpVersion"

# Check PHP version meets minimum requirement
$phpVersionParts = $phpVersion -split '\.'
$phpMajor = [int]$phpVersionParts[0]
$phpMinor = [int]$phpVersionParts[1]
if ($phpMajor -lt 7 -or ($phpMajor -eq 7 -and $phpMinor -lt 4)) {
    Write-ErrorMessage "PHP 7.4 or higher required (Current: $phpVersion)"
    exit 1
}

# Check required PHP extensions
$requiredExtensions = @('pdo_sqlite', 'sqlite3', 'mbstring')
$missingExtensions = @()

foreach ($ext in $requiredExtensions) {
    if (Test-PHPExtension $ext) {
        Write-SuccessMessage "PHP extension: $ext"
    } else {
        $missingExtensions += $ext
        Write-WarningMessage "Missing PHP extension: $ext"
    }
}

if ($missingExtensions.Count -gt 0) {
    Write-Host "`nMissing required extensions:" -ForegroundColor $ColorError
    $missingExtensions | ForEach-Object { Write-Host "  - $_" -ForegroundColor $ColorError }
    Write-Host "`nPlease enable these extensions in php.ini and try again." -ForegroundColor $ColorWarning
    exit 1
}

# ============================================================================
# INSTALLATION CONFIRMATION
# ============================================================================

Write-Host "`n" + "="*70 -ForegroundColor Gray
Write-Host "Installation Configuration:" -ForegroundColor $ColorInfo
Write-Host "="*70 -ForegroundColor Gray
Write-Host "  Installation Path : " -NoNewline; Write-Host $InstallPath -ForegroundColor White
Write-Host "  IIS Site Name     : " -NoNewline; Write-Host $SiteName -ForegroundColor White
Write-Host "  HTTP Port         : " -NoNewline; Write-Host $Port -ForegroundColor White
Write-Host "  Skip IIS Config   : " -NoNewline; Write-Host $SkipIIS -ForegroundColor White
Write-Host "="*70 -ForegroundColor Gray

Write-Host "`nProceed with installation? (Y/N): " -NoNewline
$confirm = Read-Host
if ($confirm -ne 'Y' -and $confirm -ne 'y') {
    Write-Host "Installation cancelled." -ForegroundColor $ColorWarning
    exit 0
}

# ============================================================================
# CREATE INSTALLATION DIRECTORY
# ============================================================================

Write-Step "Creating installation directory..."

if (Test-Path $InstallPath) {
    Write-WarningMessage "Directory already exists: $InstallPath"
    Write-Host "Overwrite existing files? (Y/N): " -NoNewline
    $overwrite = Read-Host
    if ($overwrite -ne 'Y' -and $overwrite -ne 'y') {
        Write-Host "Installation cancelled." -ForegroundColor $ColorWarning
        exit 0
    }
} else {
    New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null
    Write-SuccessMessage "Created directory: $InstallPath"
}

# ============================================================================
# COPY APPLICATION FILES
# ============================================================================

Write-Step "Copying application files..."

$filesToCopy = @(
    "index.php",
    "setup.php"
)

$directoriesToCopy = @(
    "api",
    "admin",
    "config",
    "setup"
)

# Copy individual files
foreach ($file in $filesToCopy) {
    $sourcePath = Join-Path $ScriptPath $file
    if (Test-Path $sourcePath) {
        Copy-Item -Path $sourcePath -Destination $InstallPath -Force
        Write-SuccessMessage "Copied: $file"
    } else {
        Write-WarningMessage "File not found: $file"
    }
}

# Copy directories
foreach ($dir in $directoriesToCopy) {
    $sourcePath = Join-Path $ScriptPath $dir
    $destPath = Join-Path $InstallPath $dir
    
    if (Test-Path $sourcePath) {
        if (Test-Path $destPath) {
            Remove-Item -Path $destPath -Recurse -Force
        }
        Copy-Item -Path $sourcePath -Destination $destPath -Recurse -Force
        Write-SuccessMessage "Copied directory: $dir"
    } else {
        Write-WarningMessage "Directory not found: $dir"
    }
}

# Create necessary directories
$requiredDirs = @("data", "assets", "res")
foreach ($dir in $requiredDirs) {
    $dirPath = Join-Path $InstallPath $dir
    if (-not (Test-Path $dirPath)) {
        New-Item -ItemType Directory -Path $dirPath -Force | Out-Null
        Write-SuccessMessage "Created directory: $dir"
    }
}

# Create .gitkeep files
foreach ($dir in $requiredDirs) {
    $gitkeepPath = Join-Path $InstallPath "$dir\.gitkeep"
    if (-not (Test-Path $gitkeepPath)) {
        New-Item -ItemType File -Path $gitkeepPath -Force | Out-Null
    }
}

# ============================================================================
# SET PERMISSIONS
# ============================================================================

Write-Step "Configuring permissions..."

# Grant IIS_IUSRS write access to data directory
$dataPath = Join-Path $InstallPath "data"
$configPath = Join-Path $InstallPath "config"
$assetsPath = Join-Path $InstallPath "assets"

try {
    $acl = Get-Acl $dataPath
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "IIS_IUSRS",
        "Modify",
        "ContainerInherit,ObjectInherit",
        "None",
        "Allow"
    )
    $acl.SetAccessRule($rule)
    Set-Acl $dataPath $acl
    Write-SuccessMessage "Set permissions on data directory"
    
    $acl = Get-Acl $configPath
    $acl.SetAccessRule($rule)
    Set-Acl $configPath $acl
    Write-SuccessMessage "Set permissions on config directory"
    
    $acl = Get-Acl $assetsPath
    $acl.SetAccessRule($rule)
    Set-Acl $assetsPath $acl
    Write-SuccessMessage "Set permissions on assets directory"
} catch {
    Write-WarningMessage "Could not set permissions automatically"
    Write-Host "  Please manually grant IIS_IUSRS Modify permissions to:" -ForegroundColor $ColorWarning
    Write-Host "  - $dataPath" -ForegroundColor $ColorWarning
    Write-Host "  - $configPath" -ForegroundColor $ColorWarning
    Write-Host "  - $assetsPath" -ForegroundColor $ColorWarning
}

# ============================================================================
# CONFIGURE IIS
# ============================================================================

if (-not $SkipIIS) {
    Write-Step "Configuring IIS..."
    
    Import-Module WebAdministration -ErrorAction SilentlyContinue
    
    # Check if site already exists
    $existingSite = Get-Website -Name $SiteName -ErrorAction SilentlyContinue
    if ($existingSite) {
        Write-WarningMessage "IIS site '$SiteName' already exists"
        Write-Host "Remove existing site and recreate? (Y/N): " -NoNewline
        $recreate = Read-Host
        if ($recreate -eq 'Y' -or $recreate -eq 'y') {
            Remove-Website -Name $SiteName
            Write-SuccessMessage "Removed existing site"
        } else {
            Write-WarningMessage "Skipping IIS site creation"
            $SkipIIS = $true
        }
    }
    
    if (-not $SkipIIS) {
        # Create application pool
        $appPoolName = "${SiteName}AppPool"
        
        # Check if app pool exists
        try {
            $existingPool = Get-WebAppPoolState -Name $appPoolName -ErrorAction Stop
            if ($existingPool) {
                Remove-WebAppPool -Name $appPoolName
                Write-SuccessMessage "Removed existing application pool"
            }
        } catch {
            # App pool doesn't exist, which is fine
        }
        
        New-WebAppPool -Name $appPoolName -Force | Out-Null
        Set-ItemProperty "IIS:\AppPools\$appPoolName" -Name managedRuntimeVersion -Value ""
        Write-SuccessMessage "Created application pool: $appPoolName"
        
        # Create IIS site
        New-Website -Name $SiteName `
                    -PhysicalPath $InstallPath `
                    -Port $Port `
                    -ApplicationPool $appPoolName `
                    -Force | Out-Null
        Write-SuccessMessage "Created IIS site: $SiteName on port $Port"
        
        # Start the site
        Start-Website -Name $SiteName
        Write-SuccessMessage "Started IIS site"
        
        # Configure PHP handler
        Write-Step "Configuring PHP handler..."
        
        # Find php-cgi.exe
        $phpCgiPath = $null
        $possiblePaths = @(
            "C:\php\php-cgi.exe",
            "D:\PHP\php-cgi.exe",
            "C:\Program Files\PHP\php-cgi.exe"
        )
        
        foreach ($path in $possiblePaths) {
            if (Test-Path $path) {
                $phpCgiPath = $path
                break
            }
        }
        
        if ($null -eq $phpCgiPath) {
            Write-WarningMessage "php-cgi.exe not found in common locations"
            Write-Host "Please configure PHP handler manually in IIS Manager" -ForegroundColor $ColorWarning
        } else {
            try {
                # Configure FastCGI application
                $configPath = "MACHINE/WEBROOT/APPHOST"
                $fastCgiApp = Get-WebConfiguration -Filter "system.webServer/fastCgi/application[@fullPath='$phpCgiPath']" -PSPath $configPath -ErrorAction SilentlyContinue
                
                if ($null -eq $fastCgiApp) {
                    Add-WebConfiguration -Filter "system.webServer/fastCgi" -PSPath $configPath -Value @{
                        fullPath = $phpCgiPath
                        monitorChangesTo = (Join-Path (Split-Path $phpCgiPath) "php.ini")
                        activityTimeout = 600
                        requestTimeout = 600
                        instanceMaxRequests = 10000
                        protocol = "NamedPipe"
                    }
                    Write-SuccessMessage "Configured FastCGI application"
                } else {
                    Write-SuccessMessage "FastCGI application already configured"
                }
                
                # Configure PHP handler
                $handlerExists = Get-WebConfiguration -Filter "system.webServer/handlers/add[@name='PHP-FastCGI']" -PSPath $configPath -ErrorAction SilentlyContinue
                
                if ($null -eq $handlerExists) {
                    # Unlock handlers section
                    Set-WebConfiguration -Filter "/system.webServer/handlers" -PSPath $configPath -Metadata overrideMode -Value Allow -ErrorAction SilentlyContinue
                    
                    # Add handler at machine level
                    $appcmd = "$env:SystemRoot\system32\inetsrv\appcmd.exe"
                    & $appcmd set config -section:system.webServer/handlers /+"[name='PHP-FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='$phpCgiPath',resourceType='Either']" | Out-Null
                    
                    Write-SuccessMessage "Configured PHP handler"
                } else {
                    Write-SuccessMessage "PHP handler already configured"
                }
                
                # Restart IIS to apply changes
                Write-Step "Restarting IIS to apply PHP configuration..."
                iisreset /restart | Out-Null
                Start-Sleep -Seconds 3
                Write-SuccessMessage "IIS restarted"
                
            } catch {
                Write-WarningMessage "Could not configure PHP handler automatically: $_"
                Write-Host "PHP handler may need to be configured manually in IIS Manager" -ForegroundColor $ColorWarning
            }
        }
    }
}

# ============================================================================
# FIREWALL CONFIGURATION
# ============================================================================

if (-not $SkipIIS) {
    Write-Step "Configuring Windows Firewall..."
    
    $ruleName = "Visitor Check-In System - Port $Port"
    $existingRule = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    
    if ($existingRule) {
        Write-WarningMessage "Firewall rule already exists"
    } else {
        try {
            New-NetFirewallRule -DisplayName $ruleName `
                               -Direction Inbound `
                               -Protocol TCP `
                               -LocalPort $Port `
                               -Action Allow `
                               -Enabled True | Out-Null
            Write-SuccessMessage "Added firewall rule for port $Port"
        } catch {
            Write-WarningMessage "Could not add firewall rule automatically"
            Write-Host "  Please manually allow port $Port in Windows Firewall" -ForegroundColor $ColorWarning
        }
    }
}

# ============================================================================
# HEALTH CHECK
# ============================================================================

Write-Step "Running health checks..."

$healthPass = $true

# Check if directory exists
if (Test-Path $InstallPath) {
    Write-SuccessMessage "Installation directory exists"
} else {
    Write-ErrorMessage "Installation directory not found"
    $healthPass = $false
}

# Check if index.php exists
$indexPath = Join-Path $InstallPath "index.php"
if (Test-Path $indexPath) {
    Write-SuccessMessage "Main application file exists"
} else {
    Write-ErrorMessage "Main application file not found"
    $healthPass = $false
}

# Check data directory permissions
$dataPath = Join-Path $InstallPath "data"
if (Test-Path $dataPath) {
    $acl = Get-Acl $dataPath
    $hasWriteAccess = $false
    foreach ($access in $acl.Access) {
        if ($access.IdentityReference -like "*IIS_IUSRS*" -and $access.FileSystemRights -match "Modify") {
            $hasWriteAccess = $true
            break
        }
    }
    if ($hasWriteAccess) {
        Write-SuccessMessage "Data directory is writable"
    } else {
        Write-WarningMessage "Data directory may not be writable by IIS"
    }
} else {
    Write-ErrorMessage "Data directory not found"
    $healthPass = $false
}

# Check IIS site status
if (-not $SkipIIS) {
    $site = Get-Website -Name $SiteName -ErrorAction SilentlyContinue
    if ($site -and $site.State -eq 'Started') {
        Write-SuccessMessage "IIS site is running"
    } else {
        Write-WarningMessage "IIS site is not running"
    }
}

if ($healthPass) {
    Write-Host "`n  All health checks passed!" -ForegroundColor $ColorSuccess
} else {
    Write-Host "`n  Some health checks failed. Please review the errors above." -ForegroundColor $ColorWarning
}

# ============================================================================
# COMPLETION
# ============================================================================

Write-Host "`n" + "="*70 -ForegroundColor Gray
Write-Host "Installation Complete!" -ForegroundColor $ColorSuccess
Write-Host "="*70 -ForegroundColor Gray

Write-Host "`nNext Steps:" -ForegroundColor $ColorInfo
Write-Host "  1. Open your browser to the setup wizard" -ForegroundColor White

if (-not $SkipIIS) {
    $setupUrl = "http://localhost:$Port/setup/"
    Write-Host "  2. Navigate to: " -NoNewline -ForegroundColor White
    Write-Host $setupUrl -ForegroundColor $ColorInfo
} else {
    Write-Host "  2. Configure your web server to point to: $InstallPath" -ForegroundColor White
}

Write-Host "  3. Complete the 6-step configuration wizard" -ForegroundColor White
Write-Host "  4. Configure your organization branding" -ForegroundColor White
Write-Host "  5. Set up admin credentials" -ForegroundColor White
Write-Host "  6. Start using your visitor management system!" -ForegroundColor White

Write-Host "`nInstallation Details:" -ForegroundColor $ColorInfo
Write-Host "  Location: " -NoNewline
Write-Host $InstallPath -ForegroundColor White

if (-not $SkipIIS) {
    Write-Host "  Site URL: " -NoNewline
    Write-Host "http://localhost:$Port/" -ForegroundColor White
    Write-Host "  Setup:    " -NoNewline
    Write-Host "http://localhost:$Port/setup/" -ForegroundColor White
}

# Open browser to setup wizard
if ($OpenBrowser -and -not $SkipIIS) {
    Write-Host "`nOpening setup wizard in browser..." -ForegroundColor $ColorInfo
    Start-Sleep -Seconds 2
    Start-Process "http://localhost:$Port/setup/"
}

Write-Host "`n" + "="*70 -ForegroundColor Gray
Write-Host "Thank you for using Visitor Check-In System by Yeyland Wutani LLC" -ForegroundColor $ColorInfo
Write-Host "="*70 -ForegroundColor Gray
Write-Host ""
