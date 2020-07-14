#!/usr/bin/env bash

echo "* * * * * /usr/local/bin/php /var/www/html/artisan schedule:run >> /var/log/cron.log 2>&1
# Don’t remove the empty line at the end of this file. It is required to run the cron job" > crontab.txt
crontab crontab.txt
cron

/usr/sbin/apache2ctl -D FOREGROUND
