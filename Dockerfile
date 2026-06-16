FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    default-mysql-client \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader
RUN npm install && npm run build
# Patch Laravel ServeCommand to cast port to int (Railway injects PORT as string)
RUN sed -i 's/return $port + $this->portOffset;/return (int)$port + $this->portOffset;/' \
    vendor/laravel/framework/src/Illuminate/Foundation/Console/ServeCommand.php


# Set permissions for dirs that exist at build time
RUN chmod -R 775 bootstrap/cache

# Expose port
EXPOSE 8000
ENTRYPOINT ["/bin/bash", "-c"]

# Runtime: volume is mounted here so sqlite and storage setup happens now
CMD bash -c "\
    mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && touch storage/app/database.sqlite \
    && chmod -R 775 storage \
    && php artisan storage:link --force \
    && php artisan migrate --force \
    && php artisan db:seed --force \
    && unset PHP_CLI_SERVER_WORKERS \
    && php -d upload_max_filesize=512M -d post_max_size=512M -d memory_limit=512M -d max_input_time=600 -S 0.0.0.0:${PORT:-8000} -t public public/router.php"
