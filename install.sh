#!/bin/bash

set -e

IS_CPANEL=false
if [[ ( -d /usr/local/cpanel || -d /var/cpanel || -d /etc/cpanel ) && \
      ( -f /usr/local/cpanel/cpanel || -f /usr/local/cpanel/version ) ]]; then
    IS_CPANEL=true
fi

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "${GREEN}Installing imh-sys-snap plugin v0.0.1...${NC}"
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

    echo "${GREEN}Downloading files...${NC}"
    wget -q https://raw.githubusercontent.com/gemini2463/imh-sys-snap/master/imh-sys-snap.php
    wget -q https://raw.githubusercontent.com/gemini2463/imh-sys-snap/master/imh-sys-snap.conf
    wget -q https://raw.githubusercontent.com/gemini2463/imh-sys-snap/master/imh-sys-snap.png
    echo ""

    echo "${GREEN}Moving files...${NC}"
    mv /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/imh-sys-snap.png /usr/local/cpanel/whostmgr/docroot/addon_plugins
    chmod 755 /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/imh-sys-snap.php
    echo ""
    echo "${GREEN}Installing plugin...${NC}"
    /usr/local/cpanel/bin/register_appconfig /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/imh-sys-snap.conf
fi
echo ""
echo "${GREEN}Installation complete!${NC}"