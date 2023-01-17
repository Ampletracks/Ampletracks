#!/bin/bash

certificate=../data/acme/certificate.crt
pidFile=/var/run/apache2/apache2.pid

# Check if the SSL certificate for this site is newer than the current Apache process
# If it is then restart apache
if [ ! -e "$certificate" ]; then exit; fi

if [ ! -e "$pidFile" ]; then exit; fi

if [ "$certificate" -nt "$pidFile" ]; then
	/usr/sbin/service apache2 restart
fi
