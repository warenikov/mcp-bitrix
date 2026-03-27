FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        mbstring \
        opcache \
        soap \
        bcmath \
        sockets \
        exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /opt/mcp-bitrix
WORKDIR /opt/mcp-bitrix

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

CMD ["php", "/opt/mcp-bitrix/bin/server"]
