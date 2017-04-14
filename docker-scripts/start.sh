#!/bin/bash
php-fpm &
/usr/local/bin/caddy --conf=/srv/docker-scripts/Caddyfile --log stdout
