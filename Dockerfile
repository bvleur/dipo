FROM php:7.1-fpm

# Imagemagick library and PHP extension
RUN apt-get update && apt-get install -y libmagickwand-6.q16-dev --no-install-recommends \
&& ln -s /usr/lib/x86_64-linux-gnu/ImageMagick-6.8.9/bin-Q16/MagickWand-config /usr/bin \
&& pecl install imagick \
&& echo "extension=imagick.so" > /usr/local/etc/php/conf.d/ext-imagick.ini

RUN docker-php-ext-install exif
RUN docker-php-ext-install zip

RUN curl https://getcaddy.com | bash -s filemanager

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

WORKDIR /srv

ENV COMPOSER_ALLOW_SUPERUSER 1

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize

EXPOSE 2015

ENTRYPOINT /srv/docker-scripts/start.sh
