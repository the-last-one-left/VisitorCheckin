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
    Version: 1.0.0
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

function Write-Success {
    param([string]$Message)
    Write-Host "  ✓ " -NoNewline -ForegroundColor $ColorSuccess
    Write-Host $Message
}

function Write-Warning {
    param([string]$Message)
    Write-Host "  ⚠ " -NoNewline -ForegroundColor $ColorWarning
    Write-Host $Message -ForegroundColor $ColorWarning
}

function Write-Error {
    param([string]$Message)
    Write-Host "  ✗ " -NoNewline -ForegroundColor $ColorError
    Write-Host $Message -ForegroundColor $ColorError
}

function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-IISInstalled {
    $iisFeature = Get-WindowsFeature -Name Web-Server -ErrorAction SilentlyContinue
    return ($null -ne $iisFeature -and $iisFeature.Installed)
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
Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor $ColorInfo
Write-Host "║                                                                ║" -ForegroundColor $ColorInfo
Write-Host "║         Visitor Check-In System - Installation Wizard         ║" -ForegroundColor $ColorInfo
Write-Host "║                                                                ║" -ForegroundColor $ColorInfo
Write-Host "║                    Yeyland Wutani LLC                          ║" -ForegroundColor $ColorInfo
Write-Host "║              yeyland.wutani@tcpip.network                      ║" -ForegroundColor $ColorInfo
Write-Host "║                                                                ║" -ForegroundColor $ColorInfo
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor $ColorInfo
Write-Host ""

# ============================================================================
# PREREQUISITE CHECKS
# ============================================================================

Write-Step "Checking prerequisites..."

# Check for administrator privileges
if (-not (Test-Administrator)) {
    Write-Error "This script requires administrator privileges"
    Write-Host "`nPlease run PowerShell as Administrator and try again." -ForegroundColor $ColorWarning
    exit 1
}
Write-Success "Administrator privileges confirmed"

# Check PowerShell version
$psVersion = $PSVersionTable.PSVersion
if ($psVersion.Major -lt 5) {
    Write-Error "PowerShell 5.1 or higher required (Current: $psVersion)"
    exit 1
}
Write-Success "PowerShell version: $psVersion"

# Check for IIS
if (-not $SkipIIS) {
    if (-not (Test-IISInstalled)) {
        Write-Warning "IIS is not installed"
        Write-Host "`nWould you like to install IIS? (Y/N): " -NoNewline
        $response = Read-Host
        if ($response -eq 'Y' -or $response -eq 'y') {
            Write-Step "Installing IIS..."
            Install-WindowsFeature -Name Web-Server -IncludeManagementTools
            Write-Success "IIS installed successfully"
        } else {
            Write-Warning "Continuing without IIS installation (use -SkipIIS to suppress this check)"
        }
    } else {
        Write-Success "IIS is installed"
    }
}

# Check for PHP
$phpVersion = Get-PHPVersion
if ($null -eq $phpVersion) {
    Write-Error "PHP is not installed or not in PATH"
    Write-Host "`nPlease install PHP 7.4 or higher with the following extensions:" -ForegroundColor $ColorWarning
    Write-Host "  - pdo_sqlite" -ForegroundColor $ColorWarning
    Write-Host "  - sqlite3" -ForegroundColor $ColorWarning
    Write-Host "  - mbstring" -ForegroundColor $ColorWarning
    Write-Host "`nDownload from: https://windows.php.net/download/" -ForegroundColor $ColorInfo
    exit 1
}
Write-Success "PHP version: $phpVersion"

# Check PHP version meets minimum requirement
$phpVersionParts = $phpVersion -split '\.'
$phpMajor = [int]$phpVersionParts[0]
$phpMinor = [int]$phpVersionParts[1]
if ($phpMajor -lt 7 -or ($phpMajor -eq 7 -and $phpMinor -lt 4)) {
    Write-Error "PHP 7.4 or higher required (Current: $phpVersion)"
    exit 1
}

# Check required PHP extensions
$requiredExtensions = @('pdo_sqlite', 'sqlite3', 'mbstring')
$missingExtensions = @()

foreach ($ext in $requiredExtensions) {
    if (Test-PHPExtension $ext) {
        Write-Success "PHP extension: $ext"
    } else {
        $missingExtensions += $ext
        Write-Warning "Missing PHP extension: $ext"
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
    Write-Warning "Directory already exists: $InstallPath"
    Write-Host "Overwrite existing files? (Y/N): " -NoNewline
    $overwrite = Read-Host
    if ($overwrite -ne 'Y' -and $overwrite -ne 'y') {
        Write-Host "Installation cancelled." -ForegroundColor $ColorWarning
        exit 0
    }
} else {
    New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null
    Write-Success "Created directory: $InstallPath"
}

# ============================================================================
# COPY APPLICATION FILES
# ============================================================================

Write-Step "Copying application files..."

$filesToCopy = @(
    "index.php",
    "setup.php",
    "migrate.php"
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
        Write-Success "Copied: $file"
    } else {
        Write-Warning "File not found: $file"
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
        Write-Success "Copied directory: $dir"
    } else {
        Write-Warning "Directory not found: $dir"
    }
}

# Create necessary directories
$requiredDirs = @("data", "assets", "res")
foreach ($dir in $requiredDirs) {
    $dirPath = Join-Path $InstallPath $dir
    if (-not (Test-Path $dirPath)) {
        New-Item -ItemType Directory -Path $dirPath -Force | Out-Null
        Write-Success "Created directory: $dir"
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
    Write-Success "Set permissions on data directory"
    
    $acl = Get-Acl $configPath
    $acl.SetAccessRule($rule)
    Set-Acl $configPath $acl
    Write-Success "Set permissions on config directory"
    
    $acl = Get-Acl $assetsPath
    $acl.SetAccessRule($rule)
    Set-Acl $assetsPath $acl
    Write-Success "Set permissions on assets directory"
} catch {
    Write-Warning "Could not set permissions automatically"
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
        Write-Warning "IIS site '$SiteName' already exists"
        Write-Host "Remove existing site and recreate? (Y/N): " -NoNewline
        $recreate = Read-Host
        if ($recreate -eq 'Y' -or $recreate -eq 'y') {
            Remove-Website -Name $SiteName
            Write-Success "Removed existing site"
        } else {
            Write-Warning "Skipping IIS site creation"
            $SkipIIS = $true
        }
    }
    
    if (-not $SkipIIS) {
        # Create application pool
        $appPoolName = "${SiteName}AppPool"
        $existingPool = Get-WebAppPoolState -Name $appPoolName -ErrorAction SilentlyContinue
        
        if ($existingPool) {
            Remove-WebAppPool -Name $appPoolName
        }
        
        New-WebAppPool -Name $appPoolName -Force | Out-Null
        Set-ItemProperty "IIS:\AppPools\$appPoolName" -Name managedRuntimeVersion -Value ""
        Write-Success "Created application pool: $appPoolName"
        
        # Create IIS site
        New-Website -Name $SiteName `
                    -PhysicalPath $InstallPath `
                    -Port $Port `
                    -ApplicationPool $appPoolName `
                    -Force | Out-Null
        Write-Success "Created IIS site: $SiteName on port $Port"
        
        # Start the site
        Start-Website -Name $SiteName
        Write-Success "Started IIS site"
    }
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

Write-Host "  3. Complete the configuration wizard" -ForegroundColor White
Write-Host "  4. Start using your visitor management system!" -ForegroundColor White

Write-Host "`nInstallation Location: " -NoNewline
Write-Host $InstallPath -ForegroundColor $ColorInfo

if (-not $SkipIIS) {
    Write-Host "Site URL: " -NoNewline
    Write-Host "http://localhost:$Port/" -ForegroundColor $ColorInfo
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
