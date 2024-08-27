FROM php:8.2-apache

# Install SQLite and enable PHP SQLite extension
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev
RUN docker-php-ext-install pdo pdo_sqlite

# Enable Apache modules
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Create data directory, SQLite database file, and set permissions
RUN mkdir -p /data && \
	touch /data/database.sqlite && \
	chown -R www-data:www-data /data && \
	chmod 777 /data && \
	chmod 666 /data/database.sqlite

# Configure PHP error logging
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/php.ini && \
	echo "display_errors = Off" >> /usr/local/etc/php/php.ini && \
	echo "log_errors = On" >> /usr/local/etc/php/php.ini && \
	echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini

# Configure Apache to handle PHP errors gracefully
RUN echo "php_flag log_errors on" >> /etc/apache2/apache2.conf && \
	echo "php_value error_reporting 32767" >> /etc/apache2/apache2.conf && \
	echo "php_flag display_errors off" >> /etc/apache2/apache2.conf

# Start Apache
CMD ["apache2-foreground"]