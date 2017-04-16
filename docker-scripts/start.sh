#!/bin/bash
chmod ugo+w /srv/web/portfolio-content
php-fpm &
/usr/local/bin/caddy --conf=/srv/docker-scripts/Caddyfile --log stdout
