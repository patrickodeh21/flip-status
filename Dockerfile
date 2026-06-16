FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    zip unzip sqlite3 libsqlite3-dev \
    default-mysql-client nginx nodejs npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install pdo_sqlite pdo_mysql mbstring exif pcntl bcmath gd

# Increase upload limits
RUN echo "upload_max_filesize=512M\npost_max_size=512M\nmemory_limit=512M\nmax_input_time=600\nmax_execution_time=600" \
    > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-dev
RUN npm install && npm run build

# Nginx config
RUN echo 'server { \
    listen 8000; \
    root /app/public; \
    index index.php; \
    client_max_body_size 512M; \
    location / { try_files $uri $uri/ /index.php?$query_string; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; \
        include fastcgi_params; \
        fastcgi_read_timeout 600; \
    } \
    location ~* \.(jpg|jpeg|png|gif|mp4|webm|mov|avi)$ { \
        expires 30d; \
        add_header Cache-Control "public, no-transform"; \
    } \
}' > /etc/nginx/sites-available/default

# Permissions for writable dirs that exist at build time
RUN chmod -R 775 bootstrap/cache \
    && chown -R www-data:www-data bootstrap/cache

EXPOSE 8000

# Start script handles runtime setup (volume is mounted here, not at build)
CMD bash -c "\
    mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 storage \
    && chown -R www-data:www-data storage \
    && php artisan storage:link --force \
    && php artisan migrate --force \
    && php artisan db:seed --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php-fpm -D \
    && nginx -g 'daemon off;'"
