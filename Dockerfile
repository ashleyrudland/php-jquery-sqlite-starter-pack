FROM php:8.2-apache

# Install SQLite and enable PHP SQLite extension
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev
RUN docker-php-ext-install pdo pdo_sqlite

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Create data directory and set permissions
RUN mkdir -p /data && chown -R www-data:www-data /data && chmod 777 /data

# Enable display_errors in php.ini
RUN echo "display_errors = On" >> /usr/local/etc/php/php.ini

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]