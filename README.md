# imh-sys-snap, v0.1.5
sys-snap Web Interface for cPanel/WHM and CWP

- cPanel/WHM path: `/usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/index.php`
- CWP path: `/usr/local/cwpsrv/htdocs/resources/admin/modules/imh-sys-snap.php`

# Installation
- Run as the Root user: `curl -fsSL https://raw.githubusercontent.com/gemini2463/imh-sys-snap/master/install.sh | sh`
- Maybe soon?: `curl -fsSL https://repo-ded.inmotionhosting.com/imh-plugins/imh-sys-snap/0.1.4/install.sh | sh`

# Files

## Shell installer
- install.sh

## Main script
- index.php - Identical to `imh-sys-snap.php`.
- index.php.sha256 - `sha256sum index.php > index.php.sha256`
- imh-sys-snap.php - Identical to `index.php`.
- imh-sys-snap.php.sha256 - `sha256sum imh-sys-snap.php > imh-sys-snap.php.sha256`

## Javascript
- imh-sys-snap.js - Bundle React or any other javascript in this file.
- imh-sys-snap.js.sha256 - `sha256sum imh-sys-snap.js > imh-sys-snap.js.sha256`

## Icon
- imh-sys-snap.png - [48x48 png image](https://api.docs.cpanel.net/guides/guide-to-whm-plugins/guide-to-whm-plugins-plugin-files/#icons)
- imh-sys-snap.png.sha256 - `sha256sum imh-sys-snap.png > imh-sys-snap.png.sha256`

## cPanel conf
- imh-sys-snap.conf - [AppConfig Configuration File](https://api.docs.cpanel.net/guides/guide-to-whm-plugins/guide-to-whm-plugins-appconfig-configuration-file)
- imh-sys-snap.conf.sha256 - `sha256sum imh-sys-snap.conf > imh-sys-snap.conf.sha256`

## CWP include
- imh-plugins.php - [CWP include](https://wiki.centos-webpanel.com/how-to-build-a-cwp-module)
- imh-plugins.php.sha256 - `sha256sum imh-plugins.php > imh-plugins.php.sha256`

## sha256 one-liner
- `for file in index.php imh-plugins.php imh-sys-snap.conf imh-sys-snap.js imh-sys-snap.php imh-sys-snap.png; do sha256sum "$file" > "$file.sha256"; done`