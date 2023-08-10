#!/bin/bash

# Start cron scheduler
echo $(service cron start)

# Start MySQL server
echo $(service mysql start)

# Start Apache2
echo $(service apache2 start)

# Persist
tail -f /dev/null
