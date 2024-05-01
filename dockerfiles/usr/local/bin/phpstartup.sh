#!/bin/sh
if [ "$TZ" ]; then
    echo "Setting timezone to $TZ"
    echo "date.timezone=\"$TZ\"" >> /etc/php83/conf.d/custom.ini
else 
    echo "No timezone set. Using UTC timezone."
    echo "date.timezone=\"UTC\"" >> /etc/php83/conf.d/custom.ini
fi

sleep 3

/usr/sbin/php-fpm -F