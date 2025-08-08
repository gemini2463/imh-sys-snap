#!/bin/bash

# Exit on error, undefined variables, pipe failures
set -euo pipefail

# Enable debugging output
#set -x

# Script metadata
readonly SCRIPT_VERSION="0.1.5"
readonly SCRIPT_NAME="imh-sys-snap"
readonly BASE_URL="https://raw.githubusercontent.com/gemini2463/$SCRIPT_NAME/master"
#readonly BASE_URL="https://repo-ded.inmotionhosting.com/imh-plugins/$SCRIPT_NAME/$SCRIPT_VERSION"

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly BLUE='\033[0;34m'
readonly BRIGHTBLUE='\033[1;34m'
readonly YELLOW='\033[1;33m'
readonly NC='\033[0m' # No Color

# Function to print colored output
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to handle errors
error_exit() {
    print_message "$RED" "ERROR: $1" >&2
    cleanup
    exit 1
}

cleanup() {
    # Only try to clean up if TEMP_DIR is set, non-empty, and is a directory
    if [[ -n "${TEMP_DIR:-}" && -d "$TEMP_DIR" ]]; then
        rm -rf "$TEMP_DIR"
    fi
}

# Set up trap to ensure cleanup on exit
trap cleanup EXIT INT TERM

# Function to check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        error_exit "This script must be run as root"
    fi
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to validate URL is accessible
validate_url() {
    local url=$1
    if ! wget --spider -q "$url" 2>/dev/null; then
        error_exit "Cannot access URL: $url"
    fi
}

# Function to download file with validation
download_file() {
    local url=$1
    local destination=$2

    if [[ -d "$destination" ]]; then
        print_message "$RED" "Destination is a directory, not a file: $destination"
        return 1
    fi

    # Get the final HTTP code after redirects
    local http_code
    http_code=$(wget --server-response --spider "$url" 2>&1 | awk '/^  HTTP|^HTTP/{code=$2} END{print code}')
    #print_message "$YELLOW" "HTTP status code for $url: $http_code"

    if [[ -z "$http_code" ]]; then
        print_message "$RED" "Could not get HTTP code for $url"
        return 1
    fi
    if [[ "$http_code" != "200" ]]; then
        print_message "$RED" "File not found or inaccessible (HTTP $http_code): $url"
        return 1
    fi

    if wget -q -O "$destination" "$url"; then
        if [[ -s "$destination" ]]; then
            print_message "$GREEN" "Downloaded $url to $destination"
            return 0
        fi
        rm -f "$destination"
        print_message "$RED" "Downloaded file is empty: $url"
        return 1
    else
        print_message "$RED" "Download failed for $url (HTTP $http_code)"
        return 1
    fi
}

download_file_with_checksum() {
    local url="$1"
    local destination="$2"

    # Download the actual file
    download_file "$url" "$destination" || return 1

    # Download the checksum file (to match destination)
    download_file "${url}.sha256" "${destination}.sha256" || return 1

    # Adjust the filename in the checksum file, if necessary
    local expected_name=$(basename "$url")
    local dest_name=$(basename "$destination")
    if [[ "$expected_name" != "$dest_name" ]]; then
        sed -i "s/^\([a-fA-F0-9]*[[:space:]]\+\).*\$/\1$dest_name/" "${destination}.sha256"
    fi

    # Verify the checksum
    (
        cd "$(dirname "$destination")"
        if ! sha256sum -c "$(basename "$destination").sha256" --status; then
            print_message "$RED" "Checksum verification FAILED for $(basename "$destination")"
            rm -f "$destination"
            exit 1
        fi
    )
    print_message "$YELLOW" "Checksum verified for $(basename "$destination")"
    echo ""
    return 0
}

copy_if_changed() {
    local src="$1"
    local dest="$2"
    if [[ -f "$dest" ]]; then
        if cmp -s "$src" "$dest"; then
            print_message "$GREEN" "No change for $dest"
            return
        else
            # Optionally backup old version
            #cp -p "$dest" "${dest}.bak.$(date +%Y%m%d_%H%M%S)"
            # print_message "$YELLOW" "Backing up and replacing $dest"
            print_message "$YELLOW" "Replacing $dest"
        fi
    fi
    cp -p "$src" "$dest"
}

# Function to create directory with proper permissions
create_directory() {
    local dir=$1
    local perms=${2:-755}

    if [[ ! -d "$dir" ]]; then
        mkdir -p "$dir" || error_exit "Failed to create directory: $dir"
        chmod "$perms" "$dir" || error_exit "Failed to set permissions on: $dir"
        print_message "$GREEN" "Created directory: $dir"
    fi
}

# Function to detect control panel
detect_control_panel() {
    if [[ (-d /usr/local/cpanel || -d /var/cpanel || -d /etc/cpanel) &&
        (-f /usr/local/cpanel/cpanel || -f /usr/local/cpanel/version) ]]; then
        echo "cpanel"
    elif [[ -d /usr/local/cwpsrv ]]; then
        echo "cwp"
    else
        echo "none"
    fi
}

# Function to install sys-snap (the Perl script), either the yum package `imh-sys-snap` (the imhbase repository) or via manual download
install_package() {
    local package=$1

    echo ""
    print_message "$YELLOW" "Checking repositories for $package..."

    if command_exists yum; then
        if yum install -y "$package"; then
            print_message "$GREEN" "$package yum repository check complete."
            return 0
        fi
        print_message "$YELLOW" "Package $package not found in yum. Attempting manual installation."
    elif command_exists apt-get; then
        if apt-get update && apt-get install -y "$package"; then
            print_message "$GREEN" "$package apt repository check complete."
            return 0
        fi
        print_message "$YELLOW" "Package $package not found in apt. Attempting manual installation."
    else
        error_exit "No supported package manager found"
    fi

    # Fallback: manual install
    mkdir -p /opt/imh-sys-snap/bin || error_exit "Could not create bin directory"
    wget -O /opt/imh-sys-snap/bin/sys-snap.pl $BASE_URL/sys-snap.pl \
        || error_exit "Failed to download sys-snap.pl"
    chmod 744 /opt/imh-sys-snap/bin/sys-snap.pl
    echo y | /usr/bin/perl /opt/imh-sys-snap/bin/sys-snap.pl --start \
        || error_exit "Failed to install $package via sys-snap.pl"
}

# Function to install for cPanel
install_cpanel() {
    print_message "$YELLOW" "Installing for cPanel..."
    echo ""

    # Create required directories
    create_directory "/var/cpanel/apps"
    create_directory "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME"
    create_directory "/usr/local/cpanel/whostmgr/docroot/templates/$SCRIPT_NAME"

    # Create temporary directory for downloads
    TEMP_DIR=$(mktemp -d) || error_exit "Failed to create temporary directory"

    # Download files to temporary directory first
    print_message "$BRIGHTBLUE" "Downloading files..."
    echo ""

    download_file_with_checksum "$BASE_URL/index.php" "$TEMP_DIR/index.php" || error_exit "Failed to get script file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.conf" "$TEMP_DIR/$SCRIPT_NAME.conf" || error_exit "Failed to get .conf file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.js" "$TEMP_DIR/$SCRIPT_NAME.js" || error_exit "Failed to get JS file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.png" "$TEMP_DIR/$SCRIPT_NAME.png" || error_exit "Failed to get PNG file"

    # Move files to final destination
    print_message "$BRIGHTBLUE" "Installing files..."
    copy_if_changed "$TEMP_DIR/index.php" "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME/index.php" || error_exit "Failed to copy index.php"

    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.conf" "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME/$SCRIPT_NAME.conf" || error_exit "Failed to copy config"

    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.js" "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME/$SCRIPT_NAME.js" || error_exit "Failed to copy $SCRIPT_NAME.js"

    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.png" "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME/$SCRIPT_NAME.png" || error_exit "Failed to copy image"

    # Set permissions
    chmod 755 "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME/index.php" || error_exit "Failed to set permissions"

    # Copy image to addon_plugins if directory exists
    if [[ -d "/usr/local/cpanel/whostmgr/docroot/addon_plugins" ]]; then
        copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.png" "/usr/local/cpanel/whostmgr/docroot/addon_plugins/$SCRIPT_NAME.png" || print_message "$YELLOW" "Warning: Failed to copy image to addon_plugins"
    fi

    # Register plugin
    echo ""
    print_message "$BRIGHTBLUE" "Registering plugin..."
    if [[ -x "/usr/local/cpanel/bin/register_appconfig" ]]; then
        if [[ -f "/var/cpanel/apps/$SCRIPT_NAME.conf" ]]; then
            print_message "$YELLOW" "Plugin already registered."
        else
            /usr/local/cpanel/bin/register_appconfig "/usr/local/cpanel/whostmgr/docroot/cgi/$SCRIPT_NAME/$SCRIPT_NAME.conf" || error_exit "Failed to register plugin"
        fi
    else
        error_exit "register_appconfig not found"
    fi
}

# Function to install for CWP
install_cwp() {
    print_message "$YELLOW" "Installing for CWP..."
    echo ""

    # Verify CWP directories exist
    [[ -d "/usr/local/cwpsrv/htdocs/resources/admin/modules" ]] || error_exit "CWP modules directory not found"

    # Create temporary directory for downloads
    TEMP_DIR=$(mktemp -d) || error_exit "Failed to create temporary directory"

    # Download files to temporary directory first

    print_message "$BRIGHTBLUE" "Downloading files..."
    echo ""

    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.php" "$TEMP_DIR/$SCRIPT_NAME.php" || error_exit "Failed to get script file"
    download_file_with_checksum "$BASE_URL/imh-plugins.php" "$TEMP_DIR/imh-plugins.php" || error_exit "Failed to get include file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.png" "$TEMP_DIR/$SCRIPT_NAME.png" || error_exit "Failed to get PNG file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.js" "$TEMP_DIR/$SCRIPT_NAME.js" || error_exit "Failed to get JS file"

    # Remove immutable attributes if they exist
    print_message "$BRIGHTBLUE" "Preparing directories..."
    if command_exists chattr; then
        chattr -ifR /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    fi
    echo ""

    # Copy files to destination
    print_message "$BRIGHTBLUE" "Installing files..."
    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.php" "/usr/local/cwpsrv/htdocs/resources/admin/modules/$SCRIPT_NAME.php" || error_exit "Failed to copy PHP file"
    chmod 755 "/usr/local/cwpsrv/htdocs/resources/admin/modules/$SCRIPT_NAME.php" || error_exit "Failed to set permissions"

    # Create directories if they don't exist
    create_directory "/usr/local/cwpsrv/htdocs/admin/design/img"
    create_directory "/usr/local/cwpsrv/htdocs/admin/design/js"
    create_directory "/usr/local/cwpsrv/htdocs/resources/admin/include"

    # Move additional files
    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.png" "/usr/local/cwpsrv/htdocs/admin/design/img/$SCRIPT_NAME.png" || print_message "$YELLOW" "Warning: Failed to copy image"

    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.js" "/usr/local/cwpsrv/htdocs/admin/design/js/$SCRIPT_NAME.js" || print_message "$YELLOW" "Warning: Failed to copy $SCRIPT_NAME.js"

    copy_if_changed "$TEMP_DIR/imh-plugins.php" "/usr/local/cwpsrv/htdocs/resources/admin/include/imh-plugins.php" || error_exit "Failed to copy include file"

    # Update 3rdparty.php
    update_cwp_config
}

# No control panel install
install_plain() {
    print_message "$GREEN" "Installing plain (no control panel)..."
    echo ""

    local dest="/root/$SCRIPT_NAME"
    create_directory "$dest" 700

    TEMP_DIR=$(mktemp -d) || error_exit "Failed to create temporary directory"

    print_message "$BRIGHTBLUE" "Downloading files..."
    download_file_with_checksum "$BASE_URL/index.php" "$TEMP_DIR/index.php" || error_exit "Failed to get script file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.js" "$TEMP_DIR/$SCRIPT_NAME.js" || error_exit "Failed to get JS file"
    download_file_with_checksum "$BASE_URL/$SCRIPT_NAME.png" "$TEMP_DIR/$SCRIPT_NAME.png" || error_exit "Failed to get PNG file"

    print_message "$BRIGHTBLUE" "Installing files..."
    copy_if_changed "$TEMP_DIR/index.php" "$dest/index.php"
    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.js" "$dest/$SCRIPT_NAME.js"
    copy_if_changed "$TEMP_DIR/$SCRIPT_NAME.png" "$dest/$SCRIPT_NAME.png"
    chmod 700 "$dest/index.php"
    chmod 600 "$dest/$SCRIPT_NAME.js" "$dest/$SCRIPT_NAME.png"

    print_message "$GREEN" "Plain install complete. Files installed to $dest"
}

# Function to update CWP configuration
update_cwp_config() {
    local target="/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php"
    local include_file="/usr/local/cwpsrv/htdocs/resources/admin/include/imh-plugins.php"
    local include_statement="include('${include_file}');"

    # Validate files
    [[ -f "$target" ]] || error_exit "Target file does not exist: $target"
    [[ -r "$target" ]] || error_exit "Cannot read target file: $target"
    [[ -w "$target" ]] || error_exit "Cannot write to target file: $target"
    [[ -f "$include_file" ]] || error_exit "Include file does not exist: $include_file"

    # Optional backup
    # local backup="${target}.bak.$(date +%Y%m%d_%H%M%S)"
    # copy_if_changed "$target" "$backup" || error_exit "Failed to create backup"
    # print_message "$GREEN" "Backup created: $backup"

    # Check if include already exists
    if grep -Fq "include('${include_file}')" "$target" ||
        grep -Fq "include(\"${include_file}\")" "$target" ||
        grep -Fq "require('${include_file}')" "$target" ||
        grep -Fq "require_once('${include_file}')" "$target"; then
        print_message "$YELLOW" "Include line already exists. No changes made."
        return 0
    fi

    # Create temporary file
    local temp_file=$(mktemp "${target}.XXXXXX") || error_exit "Failed to create temp file"

    # Add include statement
    if grep -q '?>' "$target"; then
        # File has closing PHP tag - insert before it
        awk -v inc="$include_statement" '
            /\?>/ && !done {
                print inc
                done = 1
            }
            { print }
        ' "$target" >"$temp_file"
    else
        # No closing PHP tag - append to end
        copy_if_changed "$target" "$temp_file"
        echo "" >>"$temp_file"
        echo "$include_statement" >>"$temp_file"
    fi

    # Validate PHP syntax if possible
    if command_exists php; then
        if ! php -l "$temp_file" >/dev/null 2>&1; then
            rm -f "$temp_file"
            error_exit "Modified file has PHP syntax errors. Aborting."
        fi
    fi

    # Replace original file
    mv "$temp_file" "$target" || error_exit "Failed to update target file"
    print_message "$GREEN" "Successfully added include statement to $target"
}

# Function to ensure sar/sysstat is installed and running
ensure_sysstat() {
    print_message "$YELLOW" "Checking for sysstat installation and activity..."

    local installed=0

    # Check via package manager
    if command_exists rpm; then
        if rpm -q sysstat >/dev/null 2>&1; then
            installed=1
        fi
    elif command_exists dpkg; then
        if dpkg -l | grep -qw sysstat; then
            installed=1
        fi
    fi

    # If not installed, install it
    if [[ $installed -eq 0 ]]; then
        print_message "$YELLOW" "sysstat not installed, installing..."
        if command_exists apt-get; then
            apt-get update
            apt-get install -y sysstat || error_exit "Failed to install sysstat"
        elif command_exists yum; then
            yum install -y sysstat || error_exit "Failed to install sysstat"
        else
            error_exit "No supported package manager found for sysstat install"
        fi
    fi

    # At this point, sysstat should be installed. Check for systemd
    if command_exists systemctl; then
        # Try both unit names, try to enable/start, but don't fail if absent
        for svc in sysstat sysstat.service; do
            systemctl enable "$svc" 2>/dev/null || true
            systemctl start "$svc" 2>/dev/null && {
                if systemctl is-active --quiet "$svc"; then
                    print_message "$GREEN" "sysstat service ($svc) is running."
                    return 0
                fi
            }
        done
    fi

    # One more check: verify sysstat is working by creating a sample run
    if ! LANG=C sar 1 1 >/dev/null 2>&1; then
        print_message "$YELLOW" "Warning: sar command failed to collect data (may need to enable data collection in /etc/default/sysstat or /etc/sysconfig/sysstat)."
    else
        print_message "$GREEN" "sar is operational."
    fi
}

# Main installation function
main() {
    print_message "$RED" "Installing $SCRIPT_NAME plugin v$SCRIPT_VERSION..."
    echo ""

    # Check prerequisites
    check_root

    # Ensure sar/sysstat is present
    ensure_sysstat

    # Check for required commands
    for cmd in wget mktemp; do
        if ! command_exists "$cmd"; then
            error_exit "Required command not found: $cmd"
        fi
    done

    # Validate base URL is accessible
    validate_url "$BASE_URL/index.php"

    # Install imh-sys-snap yum package (the Perl script)
    install_package "$SCRIPT_NAME"
    echo ""

    # Detect control panel
    local panel=$(detect_control_panel)

    case "$panel" in
    "cpanel")
        install_cpanel
        ;;
    "cwp")
        install_cwp
        ;;
    *)
        install_plain
        ;;
    esac

    echo ""
    print_message "$BLUE" "Installation complete!"
}

# Run main function
main "$@"
