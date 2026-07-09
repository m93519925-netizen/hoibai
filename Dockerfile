FROM php:8.2-apache

# Cài curl extension (để gọi Supabase API)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl

# Bật mod_rewrite
RUN a2enmod rewrite

# Copy toàn bộ code vào
COPY . /var/www/html/

# Phân quyền
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
