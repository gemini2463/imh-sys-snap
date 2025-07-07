#!/bin/bash

set -e

IS_CPANEL=false
if [[ ( -d /usr/local/cpanel || -d /var/cpanel || -d /etc/cpanel ) && \
      ( -f /usr/local/cpanel/cpanel || -f /usr/local/cpanel/version ) ]]; then
    IS_CPANEL=true
fi

echo "Installing imh-sys-snap plugin v0.0.1..."
echo ""

if [ "$IS_CPANEL" = true ]; then
    if [ ! -d /var/cpanel/apps ]; then
        mkdir -p /var/cpanel/apps
        chmod 755 /var/cpanel/apps
    fi

    if [ ! -d /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap ]; then
        mkdir -p /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap
        chmod 755 /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap
    fi

    if [ ! -d /usr/local/cpanel/whostmgr/docroot/templates/imh-sys-snap ]; then
        mkdir /usr/local/cpanel/whostmgr/docroot/templates/imh-sys-snap
        chmod 755 /usr/local/cpanel/whostmgr/docroot/templates/imh-sys-snap
    fi

    cd /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/

    echo "Downloading files..."
    wget -q --no-cache -O index.php https://rossu.dev/imh-sys-snap/index.txt
    wget -q --no-cache -O imh-sys-snap.conf https://rossu.dev/imh-sys-snap/imh-sys-snap.conf
    wget -q --no-cache -O imh-sys-snap.png https://rossu.dev/imh-sys-snap/imh-sys-snap.png
    echo ""

    echo "Moving files..."
    cp /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/imh-sys-snap.png /usr/local/cpanel/whostmgr/docroot/addon_plugins
    chmod 755 /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/index.php
    echo ""
    echo "Installing plugin..."
    /usr/local/cpanel/bin/register_appconfig /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/imh-sys-snap.conf
fi

if [ "$IS_CPANEL" = false ]; then
    cd /usr/local/cwpsrv/htdocs/resources/admin/modules/

    echo "Downloading files..."
    wget -q --no-cache -O imh-sys-snap.php https://rossu.dev/imh-sys-snap/index.txt
    wget -q --no-cache -O cwp-include.txt https://rossu.dev/imh-sys-snap/cwp-include.txt
    wget -q --no-cache -O imh-sys-snap.png https://rossu.dev/imh-sys-snap/imh-sys-snap.png
    echo ""

    echo "Moving files..."
    mv /usr/local/cwpsrv/htdocs/resources/admin/modules/imh-sys-snap.png /usr/local/cwpsrv/htdocs/admin/design/img
    mv /usr/local/cwpsrv/htdocs/resources/admin/modules/cwp-include.txt /usr/local/cwpsrv/htdocs/resources/admin/include/imh-sys-snap.php

    echo ""
    echo "Installing plugin..."
    chmod 755 /usr/local/cwpsrv/htdocs/resources/admin/modules/imh-sys-snap.php

    TARGET="/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php"
    INCLUDE="include('/usr/local/cwpsrv/htdocs/resources/admin/include/imh-sys-snap.php');"
    cp "$TARGET" "${TARGET}.bak"
    
    if grep -Fq "$INCLUDE" "$TARGET"; then
        echo "Include line already exists. No changes made."
        exit 0
    fi

    # Insert before closing ?>
    awk -v inc="$INCLUDE" '
    /^\s*\?>\s*$/ {
        print inc
    }
    { print }
    ' "$TARGET" > "${TARGET}.new" && mv "${TARGET}.new" "$TARGET"
fi

echo ""
echo "Installation complete!"