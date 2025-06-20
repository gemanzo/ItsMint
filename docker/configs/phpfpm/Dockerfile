FROM php:8.4.3-fpm

USER root

ADD ./docker/configs/phpfpm/.bashrc /var/www/.bashrc

ADD ./docker/configs/phpfpm/www.conf /usr/local/etc/php-fpm.d/www.conf
ADD ./docker/configs/phpfpm/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
ADD ./docker/configs/phpfpm/php.ini /usr/local/etc/php/php.ini

ENV TZ 'Europe/Rome'

RUN chown -R www-data:www-data /var/www \
    && chown  www-data:www-data /usr/local/etc/php-fpm.d/www.conf \
    && chown -R www-data:www-data /usr/local/etc/php-fpm.d/zz-docker.conf \
    && apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libpng-dev \
    zlib1g-dev \
    libicu-dev \
    g++ \
    imagemagick \
    libmagickwand-dev \
    libmagickcore-dev \
    git \
    unzip \
    vim \
    libzip-dev \
    libwebp-dev \
    libpq-dev \ 
    build-essential \
    cmake \
    libboost-dev \
    libboost-program-options-dev \
    libboost-filesystem-dev \
    libboost-regex-dev \
    libexpat1-dev \
    zlib1g-dev \
    libbz2-dev \
    liblz4-dev \
    libprotobuf-dev \
    protobuf-compiler \
    supervisor \
    zip \
    postgresql-common

RUN /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -v 17 \
    && apt-get install -y postgresql-client-17 \
    && update-alternatives --install /usr/bin/pg_dump pg_dump /usr/lib/postgresql/17/bin/pg_dump 100

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    zip \
    pdo \
    pdo_mysql \
    opcache \
    pgsql \
    pdo_pgsql \
    exif \
    pcntl \
    bcmath 

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && ln -s /usr/local/bin/composer /usr/bin/composer

# Supervisor configuration
COPY ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/supervisor/horizon.conf /etc/supervisor/conf.d/horizon.conf
COPY ./docker/supervisor/php-fpm.conf /etc/supervisor/conf.d/php-fpm.conf

RUN mkdir -p /var/log/supervisor /var/www/html/itsmint/storage/logs \
    && chown -R www-data:www-data /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/itsmint/storage

ENV HOME /var/www
WORKDIR /var/www

# USER www-data # Supervisor runs as root

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
