#!/bin/bash

# cd to the directory this script lives in
cd "$(dirname "$0")"

every=$1
domain=$2

case $every in
    minute)
		wget -q -O - "http://$domain/acme/checkOrders.php" &
		./restartApache.sh	
	;;
	tenminute)
	;;
	hour)
	;;
	day)
	;;

    *)
        echo "Error: bad interval"
        exit 1
esac
