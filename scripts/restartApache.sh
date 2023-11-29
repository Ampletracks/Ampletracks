#!/bin/bash

# can't just use ../ here because this will break if the scripts directory is symlinked
certificate=`dirname \`pwd\``/data/acme/certificate.crt

# Check if apache2 is installed
if command -v apache2 >/dev/null 2>&1; then
    service=apache2
# Check if httpd is installed
elif command -v httpd >/dev/null 2>&1; then
    service=httpd
else
    echo "Neither apache2 nor httpd is installed"
    exit;
fi

pidFile=/var/run/$service/$service.pid

# Check if the SSL certificate for this site is newer than the current Apache process
# If it is then restart apache
if [ ! -e "$certificate" ]; then exit; fi

if [ ! -e "$pidFile" ]; then exit; fi

if [ "$certificate" -nt "$pidFile" ]; then
	/usr/sbin/service $service restart
fi
