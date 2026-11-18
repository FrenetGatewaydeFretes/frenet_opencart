ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

RUN . /etc/os-release \
  && if [ "$VERSION_CODENAME" = "buster" ] || [ "$VERSION_CODENAME" = "bullseye" ]; then \
       if [ "$VERSION_CODENAME" = "buster" ]; then security="buster/updates"; else security="${VERSION_CODENAME}-security"; fi; \
       rm -f /etc/apt/sources.list.d/*; \
       printf 'deb http://archive.debian.org/debian %s main\ndeb http://archive.debian.org/debian %s-updates main\ndeb http://archive.debian.org/debian-security %s main\n' \
         "$VERSION_CODENAME" "$VERSION_CODENAME" "$security" > /etc/apt/sources.list; \
       echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive; \
     fi

RUN apt-get update \
  && apt-get install -y --no-install-recommends unzip libjpeg62-turbo-dev libpng-dev libzip-dev \
  && if php -r 'exit(PHP_VERSION_ID >= 70400 ? 0 : 1);'; then \
       docker-php-ext-configure gd --with-jpeg; \
     else \
       docker-php-ext-configure gd --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/; \
     fi \
  && docker-php-ext-install -j"$(nproc)" gd zip mysqli \
  && rm -rf /var/lib/apt/lists/*

RUN printf 'session.save_path = /tmp\nopcache.revalidate_freq = 0\n' > /usr/local/etc/php/conf.d/zz-e2e.ini
