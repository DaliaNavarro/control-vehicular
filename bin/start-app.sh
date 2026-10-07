#!/bin/bash
set -e
php /var/www/html/bin/migrate.php
# Each invocation reconnects, so the cleaner recovers after a DB restart.
(
    while true; do
        php /var/www/html/bin/cleanup-reservations.php || true
        sleep 30
    done
) &
exec apache2-foreground
