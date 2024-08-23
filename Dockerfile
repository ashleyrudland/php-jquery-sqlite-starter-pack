FROM php:8.2-apache

# Install SQLite, Python, and other dependencies
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev python3 python3-pip

# Install PHP SQLite extension
RUN docker-php-ext-install pdo pdo_sqlite

# Install SQLite Web
RUN pip3 install sqlite-web

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

# Expose ports 80 (Apache) and 8080 (SQLite Web)
EXPOSE 80 8080

# Create a startup script
RUN echo '#!/bin/bash\n\
	if [ -z "$SQLITE_WEB_PASSWORD" ]; then\n\
	echo "SQLITE_WEB_PASSWORD is not set. SQLite Web will not start."\n\
	else\n\
	sqlite_web -H 0.0.0.0 -p 8080 -P $SQLITE_WEB_PASSWORD /data/database.sqlite &\n\
	fi\n\
	apache2-foreground' > /usr/local/bin/start.sh && \
	chmod +x /usr/local/bin/start.sh

# Start Apache and SQLite Web
CMD ["/usr/local/bin/start.sh"]