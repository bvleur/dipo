#!/bin/bash
php-fpm &
exec /usr/local/bin/caddy --conf=/srv/docker-scripts/Caddyfile --log stdout
