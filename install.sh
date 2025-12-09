#!/bin/bash

#==============================================================================
# Visitor Check-In System - Linux Installer
#==============================================================================
# 
# SYNOPSIS
#     Automated installer for Visitor Check-In System on Linux with Apache/Nginx
# 
# DESCRIPTION
#     This bash script automates deployment of the Visitor Check-In System on
#     Linux servers. It handles prerequisite checking, web server configuration,
#     directory creation, permission setting, and launches the setup wizard.
# 
# PARAMETERS
#     --install-path    Target installation directory (default: /var/www/html/visitor-checkin)
#     --site-name       Site name for web server (default: visitor-checkin)
#     --port            HTTP port (default: 80)
#     --web-server      Web server type: apache|nginx (default: auto-detect)
#     --domain          Domain name (optional, for virtual host)
#     --skip-webserver  Skip web server configuration
#     --open-browser    Open browser after installation (default: false)
#     --help            Show this help message
# 
# EXAMPLES
#     Basic installation:
#         sudo ./install.sh
# 
#     Custom path and port:
#         sudo ./install.sh --install-path /opt/visitor-checkin --port 8080
# 
#     With domain name:
#         sudo ./install.sh --domain visitors.example.com
# 
#     Apache only:
#         sudo ./install.sh --web-server apache
# 
# NOTES
#     Author: Yeyland Wutani LLC
#     Email: yeyland.wutani@tcpip.network
#     Version: 1.0.0
#     Requires: PHP 7.4+, SQLite3, Apache or Nginx
# 
#==============================================================================

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Default parameters
INSTALL_PATH="/var/www/html/visitor-checkin"
SITE_NAME="visitor-checkin"
PORT=80
WEB_SERVER=""
DOMAIN=""
SKIP_WEBSERVER=false
OPEN_BROWSER=false
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Function to print colored output
print_header() {
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}  ${BLUE}Visitor Check-In System - Linux Installer${NC}           ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}  Version 1.0.0                                          ${CYAN}║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_step() {
    echo -e "${CYAN}▶${NC} $1"
}

# Function to show help
show_help() {
    cat << EOF
Visitor Check-In System - Linux Installer

Usage: sudo ./install.sh [OPTIONS]

Options:
    --install-path PATH    Installation directory (default: /var/www/html/visitor-checkin)
    --site-name NAME       Site name (default: visitor-checkin)
    --port PORT            HTTP port (default: 80)
    --web-server TYPE      Web server: apache|nginx (default: auto-detect)
    --domain DOMAIN        Domain name (optional)
    --skip-webserver       Skip web server configuration
    --open-browser         Open browser after installation
    --help                 Show this help message

Examples:
    sudo ./install.sh
    sudo ./install.sh --install-path /opt/visitor-checkin --port 8080
    sudo ./install.sh --domain visitors.example.com --web-server apache

Requirements:
    - Root/sudo access
    - PHP 7.4 or later
    - SQLite3 PHP extension
    - Apache 2.4+ or Nginx 1.18+
    - Ubuntu 20.04+, Debian 10+, RHEL 8+, or similar

EOF
    exit 0
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --install-path)
            INSTALL_PATH="$2"
            shift 2
            ;;
        --site-name)
            SITE_NAME="$2"
            shift 2
            ;;
        --port)
            PORT="$2"
            shift 2
            ;;
        --web-server)
            WEB_SERVER="$2"
            shift 2
            ;;
        --domain)
            DOMAIN="$2"
            shift 2
            ;;
        --skip-webserver)
            SKIP_WEBSERVER=true
            shift
            ;;
        --open-browser)
            OPEN_BROWSER=true
            shift
            ;;
        --help)
            show_help
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root or with sudo"
   exit 1
fi

print_header

#==============================================================================
# STEP 1: Prerequisites Check
#==============================================================================
print_step "Step 1: Checking Prerequisites"
echo ""

PREREQ_FAILED=false

# Check PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    if php -r 'exit(version_compare(PHP_VERSION, "7.4.0", ">=") ? 0 : 1);'; then
        print_success "PHP $PHP_VERSION installed"
    else
        print_error "PHP version must be 7.4 or higher (found: $PHP_VERSION)"
        PREREQ_FAILED=true
    fi
else
    print_error "PHP is not installed"
    print_info "Install with: sudo apt install php php-sqlite3 php-mbstring (Ubuntu/Debian)"
    print_info "              sudo yum install php php-pdo (RHEL/CentOS)"
    PREREQ_FAILED=true
fi

# Check PHP SQLite extension
if php -m | grep -q pdo_sqlite; then
    print_success "PHP PDO SQLite extension installed"
else
    print_error "PHP PDO SQLite extension not found"
    print_info "Install with: sudo apt install php-sqlite3 (Ubuntu/Debian)"
    PREREQ_FAILED=true
fi

# Check PHP mbstring extension
if php -m | grep -q mbstring; then
    print_success "PHP mbstring extension installed"
else
    print_warning "PHP mbstring extension not found (recommended but not required)"
fi

# Auto-detect web server if not specified
if [[ -z "$WEB_SERVER" && "$SKIP_WEBSERVER" == false ]]; then
    if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
        WEB_SERVER="apache"
    elif command -v nginx &> /dev/null; then
        WEB_SERVER="nginx"
    fi
fi

# Check web server
if [[ "$SKIP_WEBSERVER" == false ]]; then
    if [[ "$WEB_SERVER" == "apache" ]]; then
        if command -v apache2 &> /dev/null; then
            APACHE_VERSION=$(apache2 -v | head -n 1 | grep -oP 'Apache/\K[0-9.]+')
            print_success "Apache $APACHE_VERSION installed"
        elif command -v httpd &> /dev/null; then
            APACHE_VERSION=$(httpd -v | head -n 1 | grep -oP 'Apache/\K[0-9.]+')
            print_success "Apache $APACHE_VERSION installed"
        else
            print_error "Apache is not installed"
            print_info "Install with: sudo apt install apache2 libapache2-mod-php (Ubuntu/Debian)"
            print_info "              sudo yum install httpd php (RHEL/CentOS)"
            PREREQ_FAILED=true
        fi
    elif [[ "$WEB_SERVER" == "nginx" ]]; then
        if command -v nginx &> /dev/null; then
            NGINX_VERSION=$(nginx -v 2>&1 | grep -oP 'nginx/\K[0-9.]+')
            print_success "Nginx $NGINX_VERSION installed"
        else
            print_error "Nginx is not installed"
            print_info "Install with: sudo apt install nginx php-fpm (Ubuntu/Debian)"
            print_info "              sudo yum install nginx php-fpm (RHEL/CentOS)"
            PREREQ_FAILED=true
        fi
    else
        print_warning "No web server detected or specified"
        print_info "Use --web-server apache or --web-server nginx"
        print_info "Or install manually and run with --skip-webserver"
    fi
fi

if [[ "$PREREQ_FAILED" == true ]]; then
    echo ""
    print_error "Prerequisites check failed. Please install missing components."
    exit 1
fi

echo ""
print_success "All prerequisites satisfied"
echo ""

#==============================================================================
# STEP 2: Create Installation Directory
#==============================================================================
print_step "Step 2: Creating Installation Directory"
echo ""

if [[ -d "$INSTALL_PATH" ]]; then
    print_warning "Directory already exists: $INSTALL_PATH"
    read -p "Overwrite existing installation? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_error "Installation cancelled"
        exit 1
    fi
fi

# Create directory structure
mkdir -p "$INSTALL_PATH"
mkdir -p "$INSTALL_PATH/data"
mkdir -p "$INSTALL_PATH/config"
mkdir -p "$INSTALL_PATH/setup"
mkdir -p "$INSTALL_PATH/admin"
mkdir -p "$INSTALL_PATH/api"
mkdir -p "$INSTALL_PATH/assets"

print_success "Created directory: $INSTALL_PATH"

#==============================================================================
# STEP 3: Copy Application Files
#==============================================================================
print_step "Step 3: Copying Application Files"
echo ""

# Copy all files from current directory to install path
if [[ "$SCRIPT_DIR" != "$INSTALL_PATH" ]]; then
    print_info "Copying files from $SCRIPT_DIR to $INSTALL_PATH"
    
    # Copy main files
    cp "$SCRIPT_DIR"/index.php "$INSTALL_PATH/" 2>/dev/null
    cp "$SCRIPT_DIR"/web.config "$INSTALL_PATH/" 2>/dev/null
    
    # Copy directories
    cp -r "$SCRIPT_DIR"/setup/* "$INSTALL_PATH/setup/" 2>/dev/null
    cp -r "$SCRIPT_DIR"/config/* "$INSTALL_PATH/config/" 2>/dev/null
    cp -r "$SCRIPT_DIR"/admin/* "$INSTALL_PATH/admin/" 2>/dev/null
    cp -r "$SCRIPT_DIR"/api/* "$INSTALL_PATH/api/" 2>/dev/null
    cp -r "$SCRIPT_DIR"/assets/* "$INSTALL_PATH/assets/" 2>/dev/null
    
    print_success "Application files copied"
else
    print_info "Installing in current directory"
fi

#==============================================================================
# STEP 4: Set Permissions
#==============================================================================
print_step "Step 4: Setting File Permissions"
echo ""

# Determine web server user
if [[ "$WEB_SERVER" == "apache" ]]; then
    if id -u www-data &>/dev/null; then
        WEB_USER="www-data"
    elif id -u apache &>/dev/null; then
        WEB_USER="apache"
    else
        WEB_USER="www-data"
    fi
elif [[ "$WEB_SERVER" == "nginx" ]]; then
    if id -u www-data &>/dev/null; then
        WEB_USER="www-data"
    elif id -u nginx &>/dev/null; then
        WEB_USER="nginx"
    else
        WEB_USER="www-data"
    fi
else
    WEB_USER="www-data"
fi

# Set ownership
chown -R $WEB_USER:$WEB_USER "$INSTALL_PATH"
print_success "Set ownership to $WEB_USER:$WEB_USER"

# Set directory permissions
find "$INSTALL_PATH" -type d -exec chmod 755 {} \;
print_success "Set directory permissions (755)"

# Set file permissions
find "$INSTALL_PATH" -type f -exec chmod 644 {} \;
print_success "Set file permissions (644)"

# Make data directory writable
chmod 775 "$INSTALL_PATH/data"
chmod 775 "$INSTALL_PATH/config"
print_success "Set writable permissions on data and config directories"

#==============================================================================
# STEP 5: Configure Web Server
#==============================================================================
if [[ "$SKIP_WEBSERVER" == false ]]; then
    print_step "Step 5: Configuring Web Server ($WEB_SERVER)"
    echo ""
    
    if [[ "$WEB_SERVER" == "apache" ]]; then
        # Apache configuration
        APACHE_CONF_DIR="/etc/apache2/sites-available"
        if [[ ! -d "$APACHE_CONF_DIR" ]]; then
            APACHE_CONF_DIR="/etc/httpd/conf.d"
        fi
        
        CONF_FILE="$APACHE_CONF_DIR/$SITE_NAME.conf"
        
        cat > "$CONF_FILE" << EOF
<VirtualHost *:$PORT>
    ServerName ${DOMAIN:-localhost}
    DocumentRoot $INSTALL_PATH
    
    <Directory $INSTALL_PATH>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Enable PHP
        <FilesMatch \.php$>
            SetHandler application/x-httpd-php
        </FilesMatch>
        
        # Disable directory listing
        Options -Indexes
        
        # Security headers
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-XSS-Protection "1; mode=block"
    </Directory>
    
    # Custom error pages
    ErrorDocument 404 /index.php
    
    # Logging
    ErrorLog \${APACHE_LOG_DIR}/$SITE_NAME-error.log
    CustomLog \${APACHE_LOG_DIR}/$SITE_NAME-access.log combined
</VirtualHost>
EOF
        
        print_success "Created Apache configuration: $CONF_FILE"
        
        # Enable site (Ubuntu/Debian style)
        if command -v a2ensite &> /dev/null; then
            a2ensite "$SITE_NAME" > /dev/null 2>&1
            print_success "Enabled site: $SITE_NAME"
        fi
        
        # Enable required modules
        if command -v a2enmod &> /dev/null; then
            a2enmod rewrite > /dev/null 2>&1
            a2enmod headers > /dev/null 2>&1
            print_success "Enabled Apache modules (rewrite, headers)"
        fi
        
        # Test configuration
        if command -v apache2ctl &> /dev/null; then
            if apache2ctl configtest > /dev/null 2>&1; then
                print_success "Apache configuration valid"
            else
                print_warning "Apache configuration test returned warnings"
            fi
        fi
        
        # Reload Apache
        if systemctl is-active --quiet apache2; then
            systemctl reload apache2
            print_success "Reloaded Apache"
        elif systemctl is-active --quiet httpd; then
            systemctl reload httpd
            print_success "Reloaded Apache (httpd)"
        else
            print_warning "Apache is not running. Start with: sudo systemctl start apache2"
        fi
        
    elif [[ "$WEB_SERVER" == "nginx" ]]; then
        # Nginx configuration
        NGINX_CONF_DIR="/etc/nginx/sites-available"
        if [[ ! -d "$NGINX_CONF_DIR" ]]; then
            NGINX_CONF_DIR="/etc/nginx/conf.d"
        fi
        
        CONF_FILE="$NGINX_CONF_DIR/$SITE_NAME.conf"
        
        # Detect PHP-FPM socket
        PHP_FPM_SOCKET=""
        if [[ -S "/run/php/php8.1-fpm.sock" ]]; then
            PHP_FPM_SOCKET="/run/php/php8.1-fpm.sock"
        elif [[ -S "/run/php/php8.0-fpm.sock" ]]; then
            PHP_FPM_SOCKET="/run/php/php8.0-fpm.sock"
        elif [[ -S "/run/php/php7.4-fpm.sock" ]]; then
            PHP_FPM_SOCKET="/run/php/php7.4-fpm.sock"
        elif [[ -S "/var/run/php-fpm/www.sock" ]]; then
            PHP_FPM_SOCKET="/var/run/php-fpm/www.sock"
        else
            PHP_FPM_SOCKET="127.0.0.1:9000"
        fi
        
        cat > "$CONF_FILE" << EOF
server {
    listen $PORT;
    server_name ${DOMAIN:-localhost};
    root $INSTALL_PATH;
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Disable directory listing
    autoindex off;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:$PHP_FPM_SOCKET;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Deny access to data directory
    location ~ ^/data/ {
        deny all;
    }
    
    # Logging
    access_log /var/log/nginx/$SITE_NAME-access.log;
    error_log /var/log/nginx/$SITE_NAME-error.log;
}
EOF
        
        print_success "Created Nginx configuration: $CONF_FILE"
        
        # Enable site (Ubuntu/Debian style)
        if [[ -d "/etc/nginx/sites-enabled" ]]; then
            ln -sf "$CONF_FILE" "/etc/nginx/sites-enabled/$SITE_NAME.conf" 2>/dev/null
            print_success "Enabled site: $SITE_NAME"
        fi
        
        # Test configuration
        if nginx -t > /dev/null 2>&1; then
            print_success "Nginx configuration valid"
        else
            print_warning "Nginx configuration test returned warnings"
        fi
        
        # Reload Nginx
        if systemctl is-active --quiet nginx; then
            systemctl reload nginx
            print_success "Reloaded Nginx"
        else
            print_warning "Nginx is not running. Start with: sudo systemctl start nginx"
        fi
        
        # Start PHP-FPM if not running
        if systemctl list-units --type=service | grep -q php.*fpm; then
            PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep php.*fpm | awk '{print $1}' | head -1)
            if ! systemctl is-active --quiet "$PHP_FPM_SERVICE"; then
                systemctl start "$PHP_FPM_SERVICE"
                print_success "Started PHP-FPM"
            fi
        fi
    fi
    
    echo ""
fi

#==============================================================================
# STEP 6: Firewall Configuration
#==============================================================================
print_step "Step 6: Configuring Firewall (if applicable)"
echo ""

# UFW (Ubuntu/Debian)
if command -v ufw &> /dev/null && ufw status | grep -q "Status: active"; then
    if [[ "$PORT" == "80" ]]; then
        ufw allow 80/tcp > /dev/null 2>&1
        print_success "Added UFW rule for port 80 (HTTP)"
    elif [[ "$PORT" == "443" ]]; then
        ufw allow 443/tcp > /dev/null 2>&1
        print_success "Added UFW rule for port 443 (HTTPS)"
    else
        ufw allow $PORT/tcp > /dev/null 2>&1
        print_success "Added UFW rule for port $PORT"
    fi
# Firewalld (RHEL/CentOS)
elif command -v firewall-cmd &> /dev/null && systemctl is-active --quiet firewalld; then
    if [[ "$PORT" == "80" ]]; then
        firewall-cmd --permanent --add-service=http > /dev/null 2>&1
        print_success "Added firewalld rule for HTTP"
    elif [[ "$PORT" == "443" ]]; then
        firewall-cmd --permanent --add-service=https > /dev/null 2>&1
        print_success "Added firewalld rule for HTTPS"
    else
        firewall-cmd --permanent --add-port=$PORT/tcp > /dev/null 2>&1
        print_success "Added firewalld rule for port $PORT"
    fi
    firewall-cmd --reload > /dev/null 2>&1
else
    print_info "No active firewall detected (UFW/firewalld)"
fi

echo ""

#==============================================================================
# STEP 7: Health Check
#==============================================================================
print_step "Step 7: Running Health Checks"
echo ""

HEALTH_PASS=true

# Check if directory exists
if [[ -d "$INSTALL_PATH" ]]; then
    print_success "Installation directory exists"
else
    print_error "Installation directory not found"
    HEALTH_PASS=false
fi

# Check if index.php exists
if [[ -f "$INSTALL_PATH/index.php" ]]; then
    print_success "Main application file exists"
else
    print_error "Main application file not found"
    HEALTH_PASS=false
fi

# Check data directory permissions
if [[ -w "$INSTALL_PATH/data" ]]; then
    print_success "Data directory is writable"
else
    print_error "Data directory is not writable"
    HEALTH_PASS=false
fi

# Check config directory permissions
if [[ -w "$INSTALL_PATH/config" ]]; then
    print_success "Config directory is writable"
else
    print_error "Config directory is not writable"
    HEALTH_PASS=false
fi

# Check web server status
if [[ "$SKIP_WEBSERVER" == false ]]; then
    if [[ "$WEB_SERVER" == "apache" ]]; then
        if systemctl is-active --quiet apache2 || systemctl is-active --quiet httpd; then
            print_success "Apache is running"
        else
            print_warning "Apache is not running"
        fi
    elif [[ "$WEB_SERVER" == "nginx" ]]; then
        if systemctl is-active --quiet nginx; then
            print_success "Nginx is running"
        else
            print_warning "Nginx is not running"
        fi
    fi
fi

echo ""

if [[ "$HEALTH_PASS" == true ]]; then
    print_success "All health checks passed"
else
    print_error "Some health checks failed"
fi

echo ""

#==============================================================================
# Installation Summary
#==============================================================================
print_header
echo -e "${GREEN}Installation Complete!${NC}"
echo ""
echo -e "${CYAN}Installation Details:${NC}"
echo -e "  Install Path:  ${YELLOW}$INSTALL_PATH${NC}"
echo -e "  Web Server:    ${YELLOW}${WEB_SERVER:-none}${NC}"
echo -e "  Port:          ${YELLOW}$PORT${NC}"
if [[ -n "$DOMAIN" ]]; then
    echo -e "  Domain:        ${YELLOW}$DOMAIN${NC}"
fi
echo ""

# Determine URL
if [[ -n "$DOMAIN" ]]; then
    URL="http://$DOMAIN"
    if [[ "$PORT" != "80" ]]; then
        URL="$URL:$PORT"
    fi
else
    # Try to get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')
    if [[ -z "$SERVER_IP" ]]; then
        SERVER_IP="localhost"
    fi
    URL="http://$SERVER_IP"
    if [[ "$PORT" != "80" ]]; then
        URL="$URL:$PORT"
    fi
fi

echo -e "${CYAN}Next Steps:${NC}"
echo -e "  1. Access the setup wizard: ${GREEN}$URL/setup/${NC}"
echo -e "  2. Complete the 6-step configuration process"
echo -e "  3. Configure your organization branding"
echo -e "  4. Set up admin credentials"
echo -e "  5. Start using the system!"
echo ""

if [[ "$WEB_SERVER" == "nginx" ]]; then
    echo -e "${YELLOW}Note:${NC} If setup wizard doesn't load, ensure PHP-FPM is running:"
    echo -e "  sudo systemctl status php*-fpm"
    echo ""
fi

# Open browser if requested
if [[ "$OPEN_BROWSER" == true ]]; then
    print_info "Opening browser..."
    if command -v xdg-open &> /dev/null; then
        xdg-open "$URL/setup/" 2>/dev/null &
    elif command -v open &> /dev/null; then
        open "$URL/setup/" 2>/dev/null &
    fi
fi

echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Installation successful!${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
echo ""

exit 0